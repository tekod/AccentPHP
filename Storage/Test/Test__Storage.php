<?php namespace Accent\Storage\Test;

use Accent\Test\AccentTestCase;
use Accent\Storage\Storage;


/**
 * Testing AccentCore/Storage service.
 */

class Test__Storage extends AccentTestCase {

    // title describing this test
    const TEST_CAPTION= 'Storage service test';

    // title of testing group
    const TEST_GROUP= 'Storage';

    // all drivers
    protected $Drivers;


    protected function BuildDrivers($Opts=array(), $SilentWarnings=false) {

        $CommonOptions= $Opts + array(
            'Expire'=> 2, // all tests must be done in 2 seconds
            'SaveMetaData'=> false,
            'ErrorFunc'=> array($this, 'ErrorFunc'),
            'Services'=> array(
                'File'=> new \Accent\AccentCore\File\File(),
                'ArrayUtils'=> new \Accent\AccentCore\ArrayUtils\ArrayUtils(),
                'DB'=> $this->BuildDatabaseService(),
            ),
            'ErrorFunc'=> array($this, 'ErrorFunc'),
        );

        $this->Drivers= array(
            'None'=> new Storage($CommonOptions + array(
                    'Driver'=> 'None',
                )),
            'Array'=> new Storage($CommonOptions + array(
                    'Driver'=> 'Array',
                )),
            'FileComp'=> new Storage($CommonOptions + array(
                    'Driver'=> 'File',
                    'Path'=> __DIR__.'/tmp/file/compact.dat',
                    'Mode'=> 'Compact',
                )),
            'FileDist'=> new Storage($CommonOptions + array(
                    'Driver'=> 'File',
                    'Path'=> __DIR__.'/tmp/file',
                    'Mode'=> 'Distributed',
                )),
            'PhpComp'=> new Storage($CommonOptions + array(
                    'Driver'=> 'Php',
                    'Path'=> __DIR__.'/tmp/php/compact.php',
                    'Mode'=> 'Compact',
                )),
            'PhpDist'=> new Storage($CommonOptions + array(
                    'Driver'=> 'Php',
                    'Path'=> __DIR__.'/tmp/php',
                    'Mode'=> 'Distributed',
                )),
            'IniComp'=> new Storage($CommonOptions + array(
                    // using INI driver is not possible in compact mode with meta data
                    'Driver'=> $CommonOptions['SaveMetaData'] ? 'Array' : 'Ini',
                    'Path'=> __DIR__.'/tmp/ini/compact.ini',
                    'Mode'=> 'Compact',
                )),
            'IniDist'=> new Storage($CommonOptions + array(
                    'Driver'=> 'Ini',
                    'Path'=> __DIR__.'/tmp/ini',
                    'Mode'=> 'Distributed',
                )),
            'JsonComp'=> new Storage($CommonOptions + array(
                    'Driver'=> 'Json',
                    'Path'=> __DIR__.'/tmp/json/compact.json',
                    'Mode'=> 'Compact',
                )),
            'JsonDist'=> new Storage($CommonOptions + array(
                    'Driver'=> 'Json',
                    'Path'=> __DIR__.'/tmp/json',
                    'Mode'=> 'Distributed',
                )),
            'XmlComp'=> new Storage($CommonOptions + array(
                    'Driver'=> 'Xml',
                    'Path'=> __DIR__.'/tmp/xml/compact.xml',
                    'Mode'=> 'Compact',
                )),
            'XmlDist'=> new Storage($CommonOptions + array(
                    'Driver'=> 'Xml',
                    'Path'=> __DIR__.'/tmp/xml',
                    'Mode'=> 'Distributed',
                )),
            'YamlComp'=> new Storage($CommonOptions + array(
                    'Driver'=> 'Yaml',
                    'Path'=> __DIR__.'/tmp/yaml/compact.yaml',
                    'Mode'=> 'Compact',
                )),
            'YamlDist'=> new Storage($CommonOptions + array(
                    'Driver'=> 'Yaml',
                    'Path'=> __DIR__.'/tmp/yaml',
                    'Mode'=> 'Distributed',
                )),
            'NativeSession'=> new Storage($CommonOptions + array(
                    'Driver'=> 'NativeSession',
                    'Section'=> 'E',
                )),
            'Memcached'=> new Storage($CommonOptions + array(
                    'Driver'=> 'Memcached',
                    'Prefix'=> 'AccentPHP/Test',
                    'Servers'=> array(
                        array('Host' => '127.0.0.1', 'Port' => 11211, 'Weight' => 100),
                    ),
                )),
            'Redis'=> new Storage($CommonOptions + array(
                    'Driver'=> 'Redis',
                    'Prefix'=> 'AccentPHP/Test',
                    'Server'=> array('Host' => '127.0.0.1', 'Port' => 6379),
                )),
            'Database'=> new Storage($CommonOptions + array(
                    'Driver'=> 'Database',
                    'Table'=> 'storage',
                    'TagTable'=> 'storage_tags',
                )),
        );
        // remove drivers with unestablished connections
        if (!$this->Drivers['Memcached']->StorageExist()) {
            unset($this->Drivers['Memcached']);
            if (!$SilentWarnings) {
                $this->WarningMessage('Memcached connection cannot be established.');
            }
        }
        if (!$this->Drivers['Redis']->StorageExist()) {
            unset($this->Drivers['Redis']);
            if (!$SilentWarnings) {
                $this->WarningMessage('Redis connection cannot be established.');
            }
        }
        // clear all old stored data
        foreach($this->Drivers as $Driver) {
            $Driver->Clear('*');
            $Driver->GarbageCollection();
        }
    }


    // TESTS:

    public function TestSetup() {

        $FS= new \Accent\AccentCore\File\File();
        if (is_dir(__DIR__.'/tmp')) {
            $Files= $FS->ReadDirectoryRecursive(__DIR__.'/tmp');
            foreach($Files as $File) {
                $Path= __DIR__.'/tmp/'.$File;
                @unlink($Path);
                @rmdir(dirname($Path));
            }
        }
        $this->BuildDrivers();
    }


    public function TestExist() {

        foreach($this->Drivers as $Name=>$Driver) {
            $Returned= $Driver->Exist('abc');
            $this->assertFalse($Returned, "Driver '$Name' returned ".var_export($Returned, true));
        }
    }


    public function TestRead() {

        foreach($this->Drivers as $Name=>$Driver) {
            $Returned= $Driver->Read('abc');
            $this->assertFalse($Returned, "Driver '$Name' returned ".var_export($Returned, true));
        }
    }


    public function TestWrite() {

        foreach($this->Drivers as $Name=>$Driver) {
            // storage must be empty
            $Returned= $Driver->Exist('abc');
            $this->assertFalse($Returned, "Driver '$Name' returned ".var_export($Returned, true));
            $TestValue= in_array($Name, array('XmlDist','YamlDist'))
                ? array('somekey'=>'something')
                : 'something';
            $Success= $Driver->Write('abc', $TestValue);
            $this->assertTrue($Success, "Driver '$Name' failed in Write().");
            if ($Name === 'None') {
                continue;
            }
            // key must exist now
            $Returned= $Driver->Exist('abc');
            $this->assertTrue($Returned, "Driver '$Name' returned ".var_export($Returned, true));
            // value at that key must match
            $Returned= $Driver->Read('abc');
            $this->assertEqual($Returned, $TestValue, "Driver '$Name' returned ".var_export($Returned, true));
        }
    }


    public function TestWriteAll() {

        $ValuesScal= array('wa1'=>1, 'wa2'=>2, 'wa3'=>3);
        $ValuesArr= array('wa1'=>array(1,6), 'wa2'=>array(2,7), 'wa3'=>array(3,8));
        foreach($this->Drivers as $Name=>$Driver) {
            $Returned= $Driver->Exist('wa1');
            $this->assertFalse($Returned, "Driver '$Name' found 'wa1'.");
            $Values= in_array($Name,array('YamlDist','XmlDist')) ? $ValuesArr : $ValuesScal;
            $Returned= $Driver->WriteAll($Values);
            $this->assertTrue($Returned, "Driver '$Name' failed to WriteAll().");
        }
    }


    public function TestTags() {

        // rebuild drivers, this time without warnings
        $this->BuildDrivers(array(
            'SaveMetaData'=> true,
        ), true);
        unset($this->Drivers['IniComp']);  // INI driver cannot store deep arrays

        // add key 't1' with tag 'a' and test fetching
        $Key= 't1';   $Tags= array('a');
        foreach($this->Drivers as $Name=>$Driver) {
            if ($Name === 'None') {continue;}
            $Returned= $Driver->Exist($Key);
            $this->assertFalse($Returned, "Driver '$Name' key '$Key' returned ".var_export($Returned, true));

            if ($Name==='Memcached') {
                $R= $Driver->GetDriver()->GetRegistry();
            }

            $Driver->Write($Key, $Key, $Tags);

            if ($Name==='Memcached') {
                $R= $Driver->GetDriver()->GetRegistry();
            }

            $Returned= $Driver->Read($Key);
            $this->assertEqual($Returned, $Key, "Driver '$Name' key '$Key' returned ".var_export($Returned, true));
        }
        // add key 't2' with two tags 'a' & 'b' and test fetching
        $Key= 't2';   $Tags= array('a','b');
        foreach($this->Drivers as $Name=>$Driver) {
            if ($Name === 'None') {continue;}
            $Returned= $Driver->Exist($Key);
            $this->assertFalse($Returned, "Driver '$Name' key '$Key' returned ".var_export($Returned, true));
            $Driver->Write($Key, $Key, $Tags);
            $Returned= $Driver->Read($Key);
            $this->assertEqual($Returned, $Key, "Driver '$Name' key '$Key' returned ".var_export($Returned, true));
        }
        // entry 't1' must be invalid now, because 't2' updated tag 'a' with newer timestamp
        foreach($this->Drivers as $Name=>$Driver) {
            if ($Name === 'None') {continue;}
            //$TestValue= in_array($Name, array('Xml','Yaml')) ? array('Key'=>$Key) : $Key;
            $Returned= $Driver->Exist('t1');
            $this->assertFalse($Returned, "Driver '$Name' key 't1' returned ".var_export($Returned, true));
        }
        // test clearing tag,
        // add entry tagged with 'c' and remove entries tagged with 'a'
        // entry 't2' must be removed and entry 't3' must be preserved
        foreach($this->Drivers as $Name=>$Driver) {
            if ($Name === 'None') {continue;}
            $Driver->Write('t3', 't3', 'c');
            $Driver->Clear('a');
            $Returned= $Driver->Exist('t1');
            $this->assertFalse($Returned, "Driver '$Name' key 't1' returned ".var_export($Returned, true));
            $Returned= $Driver->Exist('t2');
            $this->assertFalse($Returned, "Driver '$Name' key 't2' returned ".var_export($Returned, true));
            $Returned= $Driver->Exist('t3');
            $this->assertTrue($Returned, "Driver '$Name' key 't3' returned ".var_export($Returned, true));
        }

        // clear playground
        (new \Accent\AccentCore\File\File)->DirectoryClear( __DIR__.'/tmp');
    }


    public function TestDatabaseMapping() {

        // rename columns in database table
        $Storage= new Storage(array(
            'Driver'=> 'Database',
            'Expire'=> 86400,
            'ErrorFunc'=> array($this, 'ErrorFunc'),
            'Table'=> 'storage',
            'TagTable'=> 'storage_tags',
            'TableMap'=> array(
                'Name'   => 'aName',
                'Value'  => 'aValue',
                'Created'=> 'aCreated',
                'Tags'   => 'aTags',
                'Group'  => 'aGroup',
            ),
            'Services'=> array(
                //'File'=> new \Accent\AccentCore\File\File(),
                //'ArrayUtils'=> new \Accent\AccentCore\ArrayUtils\ArrayUtils(),
                'DB'=> $this->BuildDatabaseService(true, 'a'),
        )));
        // read empty database
        $Returned= $Storage->Exist('abc');
        $this->assertFalse($Returned, "Storage returned ".var_export($Returned, true));
        // write something
        $Success= $Storage->Write('abc', '123');
        $this->assertTrue($Success);
        // test
        $Returned= $Storage->Exist('abc');
        $this->assertTrue($Returned);
        $Returned= $Storage->Read('abc');
        $this->assertEqual($Returned, '123');
        // delete
        $Success= $Storage->Delete('abc');
        $this->assertTrue($Success);
        // write two tagged values, clear one tag and test
        $Storage->Write('v1', '111', array('t1','t2'));
        $Storage->Write('v2', '222', array('t2'));
        $Storage->Clear('t1');
        $this->assertFalse($Storage->Exist('v1'));
        $this->assertTrue($Storage->Exist('v2'));
        // clear all
        $Storage->Clear('*');
        $this->assertFalse($Storage->Exist('v2'));
    }


    /////////////////////////////////////////////////////////////////////////////////////


    protected function BuildDatabaseService($InMemory=true, $Prefix='') {

        $DB= $InMemory
            ? $this->BuildMemoryDatabaseService()
            : $this->BuildRealDatabaseService();
        $DB->DropTable('storage');
        $DB->DropTable('storage_tags');
        $DB->CreateTable('storage', array(
            'Columns'=> array(
                $Prefix.'Name'=> 'varchar(80)',
                $Prefix.'Value'=> 'varchar(2000)',
                $Prefix.'Created'=> 'varchar(17)',
                $Prefix.'Tags'=> 'varchar(80)',
            ),
            'Primary' => array($Prefix.'Name'),
        ));
        $DB->CreateTable('storage_tags', array(
            'Columns'=> array(
                'Tag'=> 'varchar(80)',
                'Created'=> 'varchar(18)',
            ),
            'Primary' => array('Tag'),
        ));
        return $DB;
    }

}

?>