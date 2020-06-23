<?php namespace Accent\AccentCore\Test;

use Accent\Test\AccentTestCase;
use Accent\AccentCore\ServiceManager\ServiceManager;



class Test__ServiceManager extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'ServiceManager';

    // title of testing group
    const TEST_GROUP= 'AccentCore';

    // $Manager must be setted as global for all tests becouse it generates
    // functions which need persistent conection with service manager
    // and functions cannot be undeclared
    protected $ServiceManager;


    protected $ServicesConf= array(
        array(
            'ATP1',
            'Accent\\AccentCore\\Test\\TestingService1',
        ),
        array(
            'ATP1.secondary',
            'Accent\\AccentCore\\Test\\TestingService1', // same class
            array('Decor'=>'#')
        ),
        array(
            'ATP2',
            'Accent\\AccentCore\\Test\\TestingService2',
        ),
        array(
            'ATA',
            'Accent\\AccentCore\\Test\\TestingAdvService',
            array('SubService'=>'ATP1.secondary')
        ),
    );



    public function __construct() {

        // parent
        parent::__construct();

        // init
        $this->ServiceManager= new ServiceManager;
    }




    // HELPERS:

    protected function RegisterAll() {

        return $this->ServiceManager->RegisterAll($this->ServicesConf);
    }


    protected function RemoveAll() {

        foreach($this->ServicesConf as $S) {
            $this->ServiceManager->Remove($S[0]);
        }
    }


    // TESTS:

    public function TestCreate() {

        // create first service
        list($Name, $Class)= $this->ServicesConf[0];
        $Success= $this->ServiceManager->Register($Name, $Class);
        $this->assertTrue($Success, 'Failed to create ATP1 service.');
        // test existance check
        $this->assertTrue($this->ServiceManager->Has('ATP1'), 'Service not registered.');
        // test retrieving service
        $Service1= $this->ServiceManager->Get('ATP1');
        $this->assertTrue(is_a($Service1, $Class));
        // test method existance
        $this->assertTrue(method_exists($Service1, 'Decorate'), 'Service ATP1 cannot find method');
        // create another service of same class
        list($Name, $Class, $Opts)= $this->ServicesConf[1];
        $this->ServiceManager->Register($Name, $Class, $Opts);
        $Service1s= $this->ServiceManager->Get('ATP1.secondary');
        $this->assertNotEqual($Service1->GetId(), $Service1s->GetId(), 'ATP1.secondary is clone of ATP1');
        // // create service only once (register same service with wrong class and test it)
        $this->ServiceManager->Register('ATP1', $this->ServicesConf[2][1]);
        $this->assertTrue(is_a($this->ServiceManager->Get('ATP1'), $this->ServicesConf[0][1]));
        // create service of other class
        list($Name, $Class)= $this->ServicesConf[2];
        $this->ServiceManager->Register($Name, $Class, $Opts);
        // create advanced service
        list($Name, $Class, $Opts)= $this->ServicesConf[3];
        $this->ServiceManager->Register($Name, $Class, $Opts);
    }


    public function TestRemove() {

        $this->ServiceManager->Remove('ATP1');
        $this->ServiceManager->Remove('ATP1.secondary');
        $this->ServiceManager->Remove('ATP2');
        $this->ServiceManager->Remove('ATA');
        $this->assertFalse($this->ServiceManager->Has('ATA'), 'Service still exist.');
    }


    public function TestRegisterAll() {

        // clean
        $this->RemoveAll();
        // register whole array
        $Success= $this->ServiceManager->RegisterAll($this->ServicesConf);
        $this->assertTrue($Success, 'Error during RegisterAll()');
    }


    public function TestServiceExecutions() {

        // clean
        $this->RemoveAll();
        // register all services
        $this->RegisterAll();
        // get services
        $S1= $this->ServiceManager->Get('ATP1');
        $S1s= $this->ServiceManager->Get('ATP1.secondary');
        $S2= $this->ServiceManager->Get('ATP2');
        $SA= $this->ServiceManager->Get('ATA');
        // execute theirs methods
        $this->assertEqual($S1 ->Decorate('A'), '-A-');
        $this->assertEqual($S1s->Decorate('A'), '#A#');
        $this->assertEqual($S2 ->Decorate('A'), '==A==');
        $this->assertEqual($SA ->DoSomething('A'), '#A#');
    }


    public function TestAliases() {

        // clean
        $this->RemoveAll();
        // register all services
        $this->RegisterAll();
        // get service before aliasing
        $Service1= $this->ServiceManager->Get('ATP1');
        // create alias
        $this->ServiceManager->SetAlias('MyAlias', 'ATP1');
        // test is same class
        $AliasedService= $this->ServiceManager->Get('MyAlias');
        $this->assertTrue(is_a($AliasedService, $this->ServicesConf[0][1]));
        // test is same instance
        $this->assertEqual($AliasedService->GetId(), $Service1->GetId(), 'MyAlias is not same instance');
        // test execution
        $this->assertEqual($AliasedService->Decorate('A'), '-A-');
        // test removing alias
        $this->ServiceManager->RemoveAlias('MyAlias');
        $this->assertEqual($this->ServiceManager->Get('MyAlias'), null, 'MyAlias not removed');
    }


    public function TestCumulativeInitialization() {

        // clean
        $this->RemoveAll();
        // register all services
        $this->RegisterAll();
        // all services are registered but none is initialized, initialize ATA
        $SA= $this->ServiceManager->Get('ATA');
        // calling ATA service should internally create AT1.secondary service
        $this->assertEqual($SA->DoSomething('T'), '#T#');
    }


    public function TestLazyInitialization() {

        // remove old "marker" file
        $Path= __DIR__.'/LazyFile.dump';
        @unlink($Path);
        // ragister LS service
        $this->RemoveAll();
        $this->ServiceManager->Register('LS', 'Accent\\AccentCore\\Test\\TestingLazyService');
        // get it lazy and check "marker" file existance
        $LS= $this->ServiceManager->GetLazy('LS');
        $this->assertFalse(is_file($Path));
        // assign variable by value to another variable and check "marker" file existance
        $NNN= $LS;
        $this->assertFalse(is_file($Path));
        $this->assertTrue(is_object($NNN));
        // initialize it, marker file must exist now
        $Service= $NNN();
        $this->assertTrue(is_object($Service));
        $this->assertTrue(is_file($Path));
        // clear
        @unlink($Path);
    }

}


