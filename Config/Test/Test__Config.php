<?php namespace Accent\Config\Test;

use Accent\Test\AccentTestCase;
use Accent\Config\Config;


/**
 * Testing Config package and its drivers
 */

class Test__Config extends AccentTestCase {

    // title describing this test
    const TEST_CAPTION= 'Config service test';

    // title of testing group
    const TEST_GROUP= 'Config';

    // array of variously configured Config objects
    protected $Configs;

    // location of database
    protected $DatabaseInMemory= false;

    // TESTS:

    public function TestSetup() {

        $FixturesDir= $this->ComponentDir.'Test/fixtures';
        $DataDir= $this->ComponentDir.'Test/tmp';

        // prepare clean data
        $FS= new \Accent\AccentCore\File\File();
        $FS->DirectoryClear($DataDir);
        $FS->DirectoryCopy($FixturesDir, $DataDir);

        // build testing components
        $CommonOptions= array(
            'ErrorFunc'=> array($this, 'ErrorFunc'),
            'Services'=> array(
               'File'=> $FS,
               'ArrayUtils'=> new \Accent\AccentCore\ArrayUtils\ArrayUtils(),
            ),
        );
        $this->Configs= array();

        // storage = simple PHP file
        $this->Configs['php']= new \Accent\Config\Config(array(
            'Storage'=> 'php',
            'File'=> $DataDir.'/example_config.php',
        ) + $CommonOptions);

        // storage = ini file
        $this->Configs['ini']= new \Accent\Config\Config(array(
            'Storage'=> 'ini',
            'File'=> $DataDir.'/example_config.ini',
        ) + $CommonOptions);

        // storage = JSON file
        $this->Configs['json']= new \Accent\Config\Config(array(
            'Storage'=> 'json',
            'File'=> $DataDir.'/example_config.json',
        ) + $CommonOptions);

        // storage = xml file
        $this->Configs['xml']= new \Accent\Config\Config(array(
            'Storage'=> 'xml',
            'File'=> $DataDir.'/example_config.xml',
        ) + $CommonOptions);

        // storage = YAML file
        $this->Configs['yaml']= new \Accent\Config\Config(array(
            'Storage'=> 'Yaml',
            'File'=> $DataDir.'/example_config.yaml',
        ) + $CommonOptions);

        // storage = memory
        $this->Configs['array']= new \Accent\Config\Config(array(
            'Storage'=> 'Array',
        ) + $CommonOptions);
        $Array= include $DataDir.'/example_config.php';
        $this->Configs['array']->SetAll($Array)->Store();

        // storage = distributed yaml files
        $this->Configs['distr']= new \Accent\Config\Config(array(
            'Storage'=> 'Yaml',
            'Dir'=> $DataDir.'/distributed',
        ) + $CommonOptions);

        // storage = db
        $this->Configs['db']= new \Accent\Config\Config(array(
            'Storage'=> 'Database',
            'Table'=> 'config',
            'Services'=> array(
                'DB'=> $this->BuildDatabaseService(),
            ),
        ) + $CommonOptions);
    }


    public function TestLoad() {
        foreach($this->Configs as $Name => $Config) {
            $Config->Load();
            $List= $Config->GetAll();
            // should not be empty
            $this->assertTrue(count($List) > 0, "'$Name' list is empty");
        }
    }


    public function TestGet() {
        $SimpleValues= array(
            'Slashes' => '/cache/',
            "'Quoted'" => "'Quotes'",
            'MultiLine' => "First\nSecond",
            'UTF8' => 'Košta 1€',
            'Array' => array('one'=>'a', 'two'=>'b'),
        );
        $DistrValues= array(
            'cache'=>array('Driver'=>'php', 'Dir'=>'/var/www/protected/data/cache'),
            'db'=>array('Host'=>'localhost', 'Username'=>'root', 'Password'=>'xyz', 'Database'=>'demo'),
            'routes'=>array('About'=>array('Path'=>'/about'), 'Contact'=>array('Path'=>'/contact')),
        );
        foreach($this->Configs as $Name => $Conf) {
            $TestValues= $Name === 'distr' ? $DistrValues : $SimpleValues;
            foreach($TestValues as $k=>$v) {
                if ($k === "'Quoted'" && $Name === 'xml') continue; // xml cannot contain such tag
                if ($k === "Array" && $Name === 'db') continue; // db can store only ordinal values
                $Returned= $Conf->Get($k, 'what?');
                if ($k === "MultiLine" && $Name === 'ini') {  // on windows newline will be stored as \r\n
                    $Returned= str_replace("\r\n", "\n", $Returned);
                }
                $this->assertEqual($Returned, $v, "Driver '$Name' key '$k' returned ". var_export($Returned,true)." but expected ". var_export($v,true)); // test value
            }
            // test direct access
            if ($Name === 'distr') {
                $this->assertEqual($Conf->cache, $DistrValues['cache']);
            } else {
                $this->assertEqual($Conf->Slashes, '/cache/');
            }
        }
    }


    public function TestSet() {
        foreach($this->Configs as $Config) {
            // testing Set()
            $Config->Set('Testing1', 'TestValue1');
            // testing direct access
            $Config->Testing2= 'TestValue2';
            // asserts
            $this->assertEqual($Config->Testing1, 'TestValue1');
            $this->assertEqual($Config->Get('Testing2','what?'), 'TestValue2');
        }
    }


    public function TestDelete() {

        foreach($this->Configs as $Config) {
            $Config->Delete('Testing1');
            // should return default
            $this->assertEqual($Config->Get('Testing1','what?'), 'what?');
            // direct access
            $this->assertEqual($Config->Testing1, '');
            // this should be unchanged
            $this->assertEqual($Config->Testing2, 'TestValue2');
        }
    }


    public function TestStore() {

        foreach($this->Configs as $Name=>$Config) {
            // remove some values
            $Config->Delete('Testing1');
            $Config->Delete('Testing2');
            // modify existing value
            $V1= in_array($Name,array('distr')) ? array('V'=>'€€') : '€€';
            $Config->Set('UTF8', $V1);
            // append new definition
            $V2= in_array($Name,array('distr')) ? array('V'=>'#') : '#';
            $Config->Set('Testing1', $V2);
            // store
            $Success= $Config->Store();
            $this->assertTrue($Success,  "Driver '$Name' failed on store");
            // reload all
            $Config->ClearAll();
            $Config->Load();
            $Returned= $Config->Get('Testing1');
            $this->assertEqual($Returned, $V2, "Driver '$Name' returned ".var_export($Returned,true));
            $Returned= $Config->Get('Testing2');
            $this->assertEqual($Returned, '', "Driver '$Name' returned ".var_export($Returned,true));
            $Returned= $Config->Get('UTF8');
            $this->assertEqual($Returned, $V1, "Driver '$Name' returned ".var_export($Returned,true));
        }
    }



    /////////////////////////////////////////////////////////////////////////////////////


    protected function BuildDatabaseService($Options=array()) {

        $DB= parent::BuildDatabaseService($Options);
        $DB->DropTable('config');
        $DB->CreateTable('config', array(
            'Columns'=> array(
                'Name'=> 'varchar(30)',
                'Value'=> 'varchar(2000)',
            ),
            'Primary' => array('name'),
            'Engine' => 'MyISAM',
            'Charset' => 'utf8',
        ));
        $DB->Insert('config')->Values(array(
            array('Name'=>'Slashes', 'Value'=>'/cache/'),
            array('Name'=>"'Quoted'", 'Value'=>"'Quotes'"),
            array('Name'=>'MultiLine', 'Value'=>"First\nSecond"),
            array('Name'=>'UTF8', 'Value'=>'Košta 1€')
            // cannot define: 'Array' => array('one'=>'a', 'two'=>'b')
            // because database can store only ordinal values
        ))->Execute();
        return $DB;
    }


}


?>