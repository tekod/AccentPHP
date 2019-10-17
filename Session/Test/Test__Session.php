<?php namespace Accent\Session;

use Accent\Test\AccentTestCase;



class Test__Accent_Session extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Session service test';

    // title of testing group
    const TEST_GROUP= 'Session';


    protected function BuildSession($NewOptions=array()) {

        $Options= $NewOptions + array(
            'Driver'=> 'Array',
            'Services'=> array(
                //'UTF'=> new \Accent\AccentCore\UTF\UTF,
            ),
        );
        $Session= new Session($Options);
        return $Session;
    }



    // TESTS:


    public function TestCreating() {

        $S= $this->BuildSession();
        $S->ClearSession();
        $this->assertEqual($S->GetId(), '');
        $this->assertEqual($S->SetId('baba'), false); // to short
        $this->assertEqual($S->GetId(), '');
        $this->assertEqual($S->SetId('123456789012345678901234'), true);
        $this->assertEqual($S->GetId(), '123456789012345678901234');
    }


    public function TestGetSetHasDelete() {

        $S= $this->BuildSession();
        $this->assertEqual($S->Get('a'), null);
        $this->assertEqual($S->Get(null), array());
        $S->Set('Planet', 'Earth');
        $this->assertEqual($S->Get('Planet'), 'Earth');
        $this->assertEqual($S->Get(null), array('Planet'=>'Earth'));
        $S->Set('Planet', 'Mars');
        $S->Set('Color', 'Blue');
        $this->assertEqual($S->Get('Color'), 'Blue');
        $this->assertEqual($S->Get('Planet'), 'Mars');
        $this->assertEqual($S->Get(null), array('Planet'=>'Mars','Color'=>'Blue'));
        $this->assertEqual($S->Has('Color'), true);
        $this->assertEqual($S->Has('abc'), false);
        $S->Delete('Color');
        $this->assertEqual($S->Has('Color'), false);
        $this->assertEqual($S->Get(null), array('Planet'=>'Mars'));
    }


    public function TestLoadStore() {

        $S= $this->BuildSession();
        // create first
        $S->SetId('FirstSessionIdent');
        $this->assertEqual($S->GetId(), 'FirstSessionIdent');
        $S->Set('a', '1');
        $S->StoreSession();
        // create second
        $S->ClearSession();
        $S->SetId('SecondSessionIdent');
        $S->Set('b', '2');
        $S->StoreSession();
        // fetch first
        $S->ClearSession();
        $S->SetId('FirstSessionIdent');
        $this->assertEqual($S->Get(null), array('a'=>'1'));
        // fetch second
        $S->ClearSession();
        $S->SetId('SecondSessionIdent');
        $this->assertEqual($S->Get(null), array('b'=>'2'));
        // fetch by Id
        $Info= $S->GetSessionById('FirstSessionIdent');
        $this->assertEqual($Info['Data'], array('a'=>'1'));
        // delete first, note that 'Second' is still is still in memory
        $S->DeleteSession('FirstSessionIdent');
        $Info= $S->GetSessionById('FirstSessionIdent');
        $this->assertEqual($Info['Data'], array());
    }


    public function TestReadOnce() {

        $S= $this->BuildSession();
        $S->Set('a', '4');
        $this->assertEqual($S->Get('a'), '4');
        $S->Set('a', '7', true);
        $this->assertEqual($S->Get(null), array('a'=>'7'));
        $this->assertEqual($S->Get('a'), '7');
        $this->assertEqual($S->Get('a'), '4');
        $this->assertEqual($S->Get('a'), '4');
        // test deleting
        $S->Set('a', '7', true);
        $S->Delete('a', true);
        $this->assertEqual($S->Get('a'), '4');
    }


    public function TestFileDriver() {

        $Options= array(
            'Driver'=> 'File',
            'Dir'=> __DIR__.'/tmp',
            'Services'=> array(
                'File'=> new \Accent\AccentCore\File\File,
        ));
        $S= $this->BuildSession($Options);
        // create first
        $S->SetId('FirstSessionIdent');
        $S->Set('a', '1');
        $S->StoreSession();
        // create second
        $S->ClearSession();
        $S->SetId('SecondSessionIdent');
        $S->Set('b', '2');
        $S->StoreSession();
        // fetch first
        $S->ClearSession();
        $S->SetId('FirstSessionIdent');
        $this->assertEqual($S->Get(null), array('a'=>'1'));
        // fetch second
        $S->ClearSession();
        $S->SetId('SecondSessionIdent');
        $this->assertEqual($S->Get(null), array('b'=>'2'));
    }


    public function TestFileDriverGC() {

        $Options= array(
            'Driver'=> 'File',
            'Dir'=> __DIR__.'/tmp',
            'GcProbability'=> 0,
            'Cookie'=> array(
                'Expire'=> 1,  // just 1 second, but engine will not count this as 0 for GC
            ),
            'Services'=> array(
                'File'=> new \Accent\AccentCore\File\File,
        ));
        $S= $this->BuildSession($Options);
        // create third
        // note that FirstSessionIdent and SecondSessionIdent files are still present
        $S->SetId('ThirdSessionIdent');
        $S->Set('a', '1');
        $S->StoreSession();

        // clear playground
        (new \Accent\AccentCore\File\File)->DirectoryClear( __DIR__.'/tmp');
    }


    public function TestDatabaseDriver() {

        $this->DB= $this->BuildDatabaseService(false);

        $S= $this->BuildSession(array(
            'Driver'=> 'Database',
            'TableMap'=> array(
                'Id'         => 'id',
                'Timestamp'  => 't_stamp',
                'TimeCreated'=> 't_created',
                'Info'       => 'info',
            ),
            'Services'=> array(
                'DB'=> $this->DB,
        )));
        // create first
        $S->SetId('FirstSessionIdent');
        $S->Set('a', '1');
        $S->StoreSession();
        // create second
        $S->ClearSession();
        $S->SetId('SecondSessionIdent');
        $S->Set('b', '2');
        $S->StoreSession();
        // fetch first
        $S->ClearSession();
        $S->SetId('FirstSessionIdent');
        $this->assertEqual($S->Get(null), array('a'=>'1'));
        // fetch second
        $S->ClearSession();
        $S->SetId('SecondSessionIdent');
        $this->assertEqual($S->Get(null), array('b'=>'2'));

    }


    protected function BuildDatabaseService($InMemory=true) {

        $DB= $InMemory
            ? $this->BuildMemoryDatabaseService()
            : $this->BuildRealDatabaseService();
        $DB->DropTable('session');
        $DB->CreateTable('session', array(
            'Columns' => array(
                'id'        => 'char(30)',
                't_stamp'   => 'datetime',
                't_created' => 'datetime',
                'info'      => 'text'
            ),
            'Primary' => array('id'),
            'Engine'  => 'MyISAM',
            'Charset' => 'utf8',
        ));
        return $DB;
    }

}

?>