<?php namespace Accent\Cache\Test;

use Accent\Test\AccentTestCase;
use Accent\Cache\Cache;


/**
 * Testing Cache package and its drivers
 *
 * This test has dependency:
 *  - \Accent\File\File class from: dirname($this->ComponentDir).'/File/File.php'
 */

class Test__Cache extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Cache service test';

    // title of testing group
    const TEST_GROUP= 'Cache';

    // location of database
    protected $DatabaseInMemory= false;

    // working Cache objects
    protected $Caches= array();

    // file service object
    protected $FileService;


    protected function BuildService($DriverName, $Opts=array()) {

        $Options= $Opts + array(
            'Storage'=> $DriverName,
            'Expire'=> 1, // all tests must be done in 1 seconds
            'SaveMetaData'=> true,
            'HashFileName'=> true,
            'ErrorFunc'=> array($this, 'ErrorFunc'),
            'Services'=> array(
                'File'=> $this->FileService,
            ),
        );
        return new Cache($Options);
    }


    // TESTS:

    public function TestSetup() {

        // init FileService
        $this->FileService= new \Accent\AccentCore\File\File();
        // creating service
        $this->Caches= array(
            'file'=> $this->BuildService('File', array(
                'Path'  => __DIR__.'/tmp/cache/T1', // unique storage directory
                'Spread'=> 2,      // how many sub-dirs to create [0..8]
                'Ext'   => '.dat', // file extension of cache file
            )),
            'array'=> $this->BuildService('Array', array()),
            'db'=> $this->BuildService('Database', array(
                'Table'=> 'Cache',
                'TagTable'=> 'CacheTag',
                'Services'=> array(
                    'DB'=> $this->BuildDatabaseService(),
                ),
            )),
        );
        // clear test space
        foreach($this->Caches as $Cache) {
            $this->assertTrue($Cache->IsInitiated());
            $Cache->Clear('*');
            $Cache->GarbageCollection();
        }
    }


    public function TestBasicOperations() {

        foreach($this->Caches as $DriverName=>$Cache) {
            // read & write
            $this->assertNull($Cache->Read('Z'));
            $this->assertFalse($Cache->Exist('Z'));
            $Cache->Write('Z', '123');
            $this->assertTrue($Cache->Exist('Z')); // now true
            $this->assertEqual($Cache->Read('Z'), '123');

            // more complex data type
            if ($DriverName === 'db') {
                continue; // db can store only ordinal values
            }

            $Data= array('a'=>1, 'c'=>array('T', array()));
            $Cache->Write('Z', $Data);
            $Cache->Write('z', 'something'); // key must be case sensitive
            $Returned= $Cache->Read('Z');
            $this->assertEqual($Returned, $Data, "Driver '$DriverName' key 'Z' returned ". var_export($Returned,true)." but expected ". var_export($Data,true));
        }
    }


    public function TestClear() {
        // test clearing
        $Tests= array(
            array('skywalker', array('pK'), false),
            array('pk+R2D2', 'CD', true),
            array('X-wing', array('AC','pK'), false),
            array('solo', array('pK','MF'), false),
            array('pK', array(), true),
            array('pK+step', array(), true),
        );
        foreach($this->Caches as $Cache) {
            foreach($Tests as $v) {$Cache->Write($v[0], 'Lorem ipsum', $v[1]);}
            $Cache->Clear('pK'); // clear "pK" tag
            foreach($Tests as $v) {$this->assertEqual($Cache->Exist($v[0]), $v[2], $v[0]);}
            $Cache->Clear('*'); // test clear all
            foreach($Tests as $v) {$this->assertEqual($Cache->Exist($v[0]), false, $v[0]);}
            // expiring and garbage collection will be tested together with other drivers
            $this->Caches['file']= $Cache;
        }
    }


    public function TestExpiration() {
        // set values
        foreach($this->Caches as $DriverName=>$Cache) {
            $Cache->Write('ExpirationTest', 'M');
            $Cache->Write('GarbageCollectionTest', 'M');
        }
        // get values, should find all values
        foreach($this->Caches as $DriverName=>$Cache) {
            $this->assertEqual($Cache->Read('ExpirationTest'), 'M', $DriverName);
        }
        // wait 2 seconds to all values expire
        sleep(2);
        // get values
        foreach($this->Caches as $DriverName=>$Cache) {
            // Exist() must fail
            $this->assertEqual($Cache->Exist('ExpirationTest'), false, $DriverName);
            // Read() must fail
            $this->assertEqual($Cache->Read('ExpirationTest'), null, $DriverName);
            // Read() removed expired item from storage, test that with Exist() again
            $this->assertEqual($Cache->Exist('ExpirationTest',false), false, $DriverName);
            // call garbage collecting and test is item removed from storage
            $Cache->GarbageCollection();
            $this->assertEqual($Cache->Exist('GarbageCollectionTest',false), false, $DriverName);
        }
        // clear playground
        (new \Accent\AccentCore\File\File)->DirectoryClear( __DIR__.'/tmp');
    }


    protected function BuildDatabaseService($Options=array()) {

        $DB= parent::BuildDatabaseService($Options);

        $DB->DropTable('cache');
        $DB->CreateTable('cache', array(
            'Columns'=> array(
                'Name'=> 'varchar(40)',
                'Value'=> 'varchar(2000)',
                'Created'=> 'char(17)',
                'Tags'=> 'varchar(80)',
            ),
            'Primary' => array('Name'),
            'Engine' => 'MyISAM',
            'Charset' => 'utf8',
        ));
        $DB->DropTable('cachetag');
        $DB->CreateTable('cachetag', array(
            'Columns'=> array(
                'Tag'=> 'varchar(80)',
                'Created'=> 'varchar(17)',
            ),
            'Primary' => array('Tag'),
        ));
        return $DB;
    }

}


?>