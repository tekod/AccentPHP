<?php namespace Accent\Config\Test;

use Accent\Test\AccentTestCase;
use Accent\Config\AppConfigFactory;

/**
 * Testing AppConfigFactory service.
 * Before run this tests be sure that tests for simple Config and StackedConfig services passes.
 * Here we testing only integration logic with full confidence in the correct operation of drivers.
 */

class Test__AppConfigFactory extends AccentTestCase {

    // title describing this test
    const TEST_CAPTION= 'AppConfigFactory service test';

    // title of testing group
    const TEST_GROUP= 'Config';

    // array of variously configured Config objects
    /* @var \Accent\Config\AppConfigFactory */
    protected $Service;


    protected function BuildService($OverridingOptions=array()) {

        $Options=
            $OverridingOptions
            + array(
                'StoragePaths'=> array(
                    __DIR__.'/tmp/app/{Name}.php',
                    __DIR__.'/tmp/app/{Env}/{Name}.php',
                ),
                'Environment'=> '',
            );

        // build service
        $this->Service= new AppConfigFactory($Options);
    }


    protected function InitFixtures() {

        $FixturesDir= $this->ComponentDir.'Test/fixtures';
        $DataDir= $this->ComponentDir.'Test/tmp';

        // prepare clean data
        $FS= new \Accent\AccentCore\File\File();
        $FS->DirectoryClear($DataDir);
        $FS->DirectoryCopy($FixturesDir, $DataDir);
    }


    // TESTS:

    public function TestSetup() {

        // before first test restore fixtures to unmodified state
        $this->InitFixtures();

        // build service
        $this->BuildService();
        $this->assertTrue(is_object($this->Service));
    }


    public function TestAddRemoveStorage() {

        $this->BuildService();

        // test AddConfig
        $Conf = $this->Service->AddConfig('xyz');
        $this->assertTrue(is_object($Conf));

        // test GetConfig
        $this->assertTrue(is_object($this->Service->GetConfig('xyz')));
        $this->assertFalse(is_object($this->Service->GetConfig('xyz9')));  // get non-existent config

        // test RemoveConfig
        $this->Service->RemoveConfig('xyz');
        $this->Service->RemoveConfig('xyz1');          // removing non-existent config should have no side effects
        $this->assertFalse(is_object($this->Service->GetConfig('xyz')));    // false this time
    }


    public function TestSimpleFetchData() {

        $this->BuildService();
        $Conf= $this->Service->AddConfig('main', true);
        $this->assertEqual($Conf->Get('PostsPerPage'), 20);
    }


    public function TestFetchStackedData() {

        // load config 'main', without environment
        $this->BuildService();
        $Conf1= $this->Service->AddConfig('main', true);
        $Conf2= $this->Service->AddConfig('routes', true);
        $this->assertFalse(is_array($Conf1->GetAllFromStorage(1)));                 // only one storage can be loaded (with index 0)
        $this->assertTrue(strpos($Conf1->Get('Database'), 'overridden') !== false); // value 'Database' must be loaded from first file
        $this->assertFalse(is_array($Conf2->GetAllFromStorage(1)));                 // only one storage can be loaded
        $Route= $Conf2->Get('About');
        $this->assertEqual($Route['Path'], '/about');                               // route /about' must be loaded from first file
        // set environment
        $this->Service->ResetEnvironment('dev');
        $this->assertTrue(is_array($Conf1->GetAllFromStorage(1)));                  // two storages must be loaded
        $this->assertTrue($Conf1->Get('Database'), 'localhost');                    // value 'Database' must be loaded from 'dev' file
        $this->assertFalse(is_array($Conf2->GetAllFromStorage(1)));                 // remain false because 'routes' conf has only one file
        $Route= $Conf2->Get('About');
        $this->assertEqual($Route['Path'], '/about');                               // route '/about' must be loaded from first file
    }


    public function TestPresetEnvironment() {

        // init service with 'dev' environment
        $this->BuildService(array('Environment'=>'dev'));
        $Conf= $this->Service->AddConfig('main', true);
        $this->assertEqual($Conf->Get('Database'), 'localhost');      // value 'Database' must be loaded from 'dev' file
    }


    public function TestReResetEnvironment() {

        $this->BuildService();
        $Conf= $this->Service->AddConfig('main', true);
        // environment is not set
        $this->assertTrue(strpos($Conf->Get('Database'), 'overridden') !== false); // value 'Database' must be loaded from first file
        // reset to 'dev'
        $this->Service->ResetEnvironment('dev');
        $this->assertEqual($Conf->Get('Database'), 'localhost');      // value 'Database' must be loaded from 'dev' file
        // reset to 'test'
        $this->Service->ResetEnvironment('test');
        $this->assertEqual($Conf->Get('Database'), '70.71.72.73');    // value 'Database' must be loaded from 'test' file

        // clear playground
        (new \Accent\AccentCore\File\File)->DirectoryClear( __DIR__.'/tmp');
    }

}


?>