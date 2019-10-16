<?php namespace Accent\Config\Test;

use Accent\Test\AccentTestCase;
use Accent\Config\StackedConfig;


/**
 * Testing StackedConfig service.
 * Before run this tests be sure that tests for simple Config service passes because it test all drivers.
 * Here we testing only integration logic with full confidence in the correct operation of drivers.
 */

class Test__StackedConfig extends AccentTestCase {

    // title describing this test
    const TEST_CAPTION= 'StackedConfig service test';

    // title of testing group
    const TEST_GROUP= 'Config';

    // array of variously configured Config objects
    protected $Service;


    protected function BuildService($OverridingOptions=array()) {

        $Options=
            $OverridingOptions
            + array(
                'Storages'=> array(
                    array(
                        'Storage'=> 'yaml',
                        'File'=> $this->ComponentDir.'Test/tmp/stacked/translation.en.yaml',
                    ),
                    array(
                        'Storage'=> 'yaml',
                        'File'=> $this->ComponentDir.'Test/tmp/stacked/translation.sr.yaml',
                    ),
                ),
                'ErrorFunc'=> array($this, 'ErrorFunc'),
                'Services'=> array(
                    'File'=> new \Accent\AccentCore\File\File(),
                    'ArrayUtils'=> new \Accent\AccentCore\ArrayUtils\ArrayUtils(),
                ),
            );

        // build service
        $this->Service= new \Accent\Config\StackedConfig($Options);
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

        // build service with empty collection of storages
        $Options= array('Storages'=>array());  // clear collection
        $this->BuildService($Options);
        $this->assertTrue(is_object($this->Service));

        // call Load, it should load nothing
        $this->Service->Load();

        // try to fetch value, it should return null because no storage are loaded
        $Label= $this->Service->Get('User.FirstName');
        $this->assertEqual($Label, null);
    }


    public function TestAddRemoveStorage() {

        $this->Service->AddStorage(array(
            'Storage'=> 'yaml',
            'File'=> $this->ComponentDir.'Test/tmp/stacked/translation.en.yaml',
        ));

        // call Load
        $this->Service->Load();

        // fetch, it should find value
        $Label= $this->Service->Get('User.FirstName');
        $this->assertEqual($Label, 'First name');

        // append another storage
        $this->Service->AddStorage(array(
            'Storage'=> 'yaml',
            'File'=> $this->ComponentDir.'Test/tmp/stacked/translation.sr.yaml',
        ), 'MyAlternativeStorage'); // as named storage this time

        // before loading another storage values must remain unchanged
        $Label= $this->Service->Get('User.FirstName');
        $this->assertEqual($Label, 'First name');

        // call Load
        $this->Service->Load();

        // fetch again
        $Label= $this->Service->Get('User.FirstName');
        $this->assertEqual($Label, 'Ime');

        // fetch non-overridden value
        $Label= $this->Service->Get('Dialog.BtnOk');
        $this->assertEqual($Label, 'Ok');

        // now remove second storage
        $this->Service->RemoveStorage('MyAlternativeStorage');

        // fetch again and we must get value from first storage
        $Label= $this->Service->Get('User.FirstName');
        $this->assertEqual($Label, 'First name');

        // remove first storage and fetch again
        $this->Service->RemoveStorage(0);  // zero is index given to first storage
        $Label= $this->Service->Get('User.FirstName');
        $this->assertEqual($Label, null);

        // test default value
        $Label= $this->Service->Get('User.FirstName', 'DummyDefaultValue');
        $this->assertEqual($Label, 'DummyDefaultValue');
    }


    public function TestSetupWithConfigurationInConstructor() {

        $this->BuildService();
        $this->Service->Load();

        // expected to get value from secondary storage
        $this->assertEqual($this->Service->Get('User.FirstName'), 'Ime');

        // expected to get value from primary storage
        $this->assertEqual($this->Service->Get('Dialog.BtnOk'), 'Ok');

        // GetAll should return 4 items
        $this->assertEqual(count($this->Service->GetAll()), 4);
    }


    public function TestSet() {

        // continue with existing values from previous test

        // set value for item present in both storages
        $this->Service->Set('User.LastName', 'Family name');
        $this->assertEqual($this->Service->Get('User.LastName'), 'Family name');

        // set value for item present in only one storage
        $this->Service->Set('Dialog.BtnCancel', 'No');
        $this->assertEqual($this->Service->Get('Dialog.BtnCancel'), 'No');

        // set value for new item
        $this->Service->Set('Dialog.BtnClose', 'Close');
        $this->assertEqual($this->Service->Get('Dialog.BtnClose'), 'Close');

        // test SetAll
        $this->Service->SetAll(array(
            'Letter.A'=> 'a',
            'Letter.B'=> 'b',
        ));
        $this->assertEqual(count($this->Service->GetAll()), 7);
    }


    public function TestMagicSetterAndGetter() {

        // continue with existing values from previous test
        // no existing value are convertible to valid PHP property name because they contains dot character

        // start testing with magic set
        $this->Service->Magic1= 'One';
        // validate
        $this->assertEqual($this->Service->Get('Magic1'), 'One');
        // magic get
        $this->assertEqual($this->Service->Magic1, 'One');
        // delete value and magic get again
        $this->Service->Delete('Magic1');
        $this->assertEqual($this->Service->Magic1, null);
    }


    public function TestDelete() {

        // continue with existing values from previous test
        // remember that all new values are assign to secondary storage

        // delete item in only one storages, expecting to fetch null
        $this->Service->Delete('Dialog.BtnOk');
        $this->assertEqual($this->Service->Get('Dialog.BtnOk'), null);

        // delete item existing in both storages, expecting to fetch value from primary storage
        $this->Service->Delete('User.FirstName');
        $this->assertEqual($this->Service->Get('User.FirstName'), 'First name');

        // delete same item again, expecting to fetch null
        $this->Service->Delete('User.FirstName');
        $this->assertEqual($this->Service->Get('User.FirstName'), null);

        // delete value that are added but not existing previously in any storage, expecting to fetch null
        $this->Service->Delete('Letter.A');
        $this->assertEqual($this->Service->Get('Letter.A'), null);
    }


    public function TestClear() {

        // continue with existing values from previous test
        $this->assertNotEqual(count($this->Service->GetAll()), 0);
        $this->Service->ClearAll();
        $this->assertEqual(count($this->Service->GetAll()), 0);
    }


    public function TestOptionStoreIntoLastStorage() {

        // rebuild service with disabled 'StoreIntoLastStorage'
        $this->BuildService(array(
            'StoreIntoLastStorage'=> false,
        ));
        $this->Service->Load();

        // set value to item existing in primary storage, expecting that new value will survive removing of secondary storage
        $this->Service->Set('Dialog.BtnOk', 'Continue ->');

        // set value to item existing in both storages, expecting that new value will not survive removing of secondary storage
        $this->Service->Set('User.FirstName', 'Your name');

        // set value for unknown item, expecting that it will not survive removing of secondary storage
        $this->Service->Set('User.Password', 'Your password');

        // remove secondary storage and run validations
        $this->Service->RemoveStorage(1);  // 1 is index of second storage
        $this->assertEqual($this->Service->Get('Dialog.BtnOk'), 'Continue ->'); // modification preserved
        $this->assertEqual($this->Service->Get('User.FirstName'), 'First name'); // value from first storage
        $this->assertEqual($this->Service->Get('User.Password'), null);  // unknown item

        // rebuild service with enabled 'StoreIntoLastStorage' (enabled by default)
        $this->BuildService(array(
            'StoreIntoLastStorage'=> true,
        ));
        $this->Service->Load();

        // set value to item existing in primary storage, expecting that new value will not survive removing of secondary storage
        $this->Service->Set('Dialog.BtnOk', 'Continue ->');

        // set value to item existing in both storages, expecting that new value will not survive removing of secondary storage
        $this->Service->Set('User.FirstName', 'Your name');

        // set value for unknown item, expecting that it will not survive removing of secondary storage
        $this->Service->Set('User.Password', 'Your password');

        // remove secondary storage and run validations
        $this->Service->RemoveStorage(1);  // 1 is index of second storage
        $this->assertEqual($this->Service->Get('Dialog.BtnOk'), 'Ok'); // value from first storage
        $this->assertEqual($this->Service->Get('User.FirstName'), 'First name'); // value from first storage
        $this->assertEqual($this->Service->Get('User.Password'), null);  // unknown item
    }


    public function TestStore() {

        // build service with disabled 'StoreIntoLastStorage' to test modifications of both storages
        $this->BuildService(array('StoreIntoLastStorage'=>false));
        $this->Service->Load();

        // modify values
        $this->Service->Set('User.FirstName', 'Your name');
        $this->Service->Set('Dialog.BtnOk', 'Go');
        $this->Service->Set('Dialog.BtnSend', 'Send');
        $this->Service->Delete('Dialog.BtnCancel');

        // save storages
        $this->Service->Store();

        // rebuild and load service
        $this->Service= null;
        $this->BuildService(array('StoreIntoLastStorage'=>false));
        $this->Service->Load();

        // validate changes
        $this->assertEqual($this->Service->Get('User.FirstName'), 'Your name');
        $this->assertEqual($this->Service->Get('Dialog.BtnOk'), 'Go');
        $this->assertEqual($this->Service->Get('Dialog.BtnSend'), 'Send');
        $this->assertEqual($this->Service->Get('Dialog.BtnCancel'), null);
    }


    public function TestOptionRebuildMissingStorage() {

        // rebuild service and add 3rd storage, with intentionally non-existing file
        $this->BuildService(array('RebuildMissingStorage'=>true));
        $this->Service->AddStorage(array(
            'Storage'=> 'yaml',
            'File'=> $this->ComponentDir.'Test/tmp/stacked/translation.xyz.yaml',
        ));
        $this->Service->Load();

        // file must not exist
        $this->assertFalse(is_file($this->ComponentDir.'Test/tmp/stacked/translation.xyz.yaml'));

        // store
        $this->Service->Store();

        // check file again
        $this->assertTrue(is_file($this->ComponentDir.'Test/tmp/stacked/translation.xyz.yaml'));

        // clear playground
        (new \Accent\AccentCore\File\File)->DirectoryClear( __DIR__.'/tmp');
    }


}


?>