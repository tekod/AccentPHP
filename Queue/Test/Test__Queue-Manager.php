<?php namespace Accent\Queue\Test;

/**
 * Testing Accent\Queue\QueueManager
 */

use Accent\Test\AccentTestCase;
use Accent\Queue\QueueManager;


class Test__Queue_Manager extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'QueueManager';

    // title of testing group
    const TEST_GROUP= 'Queue';

    // location of database
    protected $DatabaseInMemory= false;

    // internal
    protected $DB;  /* @var \Accent\DB\Driver\AbstractDriver $DB */


    public function __construct() {

        // parent
        parent::__construct();

        $this->DB= $this->BuildDatabaseService();
        $this->DB->DropTable('queue');
    }


    /*
     * Helper.
     */
    protected function ClearTable() {

        $this->DB->Truncate('queue')->Execute();
    }


    /**
     * Instantiate testing component.
     *
     * @param array $Options
     * @return \Accent\Queue\QueueManager
     */
    protected function BuildComponent($Options=array()) {

        $Options += array('Services'=>array());
        $Options['Services']['DB']= $this->DB;
        return new QueueManager($Options);
    }


    public function TestBuild() {

        $C= $this->BuildComponent();
        // check is driver created successfully
        $this->assertTrue($C->IsInitiated());
    }


    public function TestGetters() {

        $C= $this->BuildComponent();
        $this->assertEqual($C->GetCount('TestWork'), 0);
        $this->assertEqual($C->GetList(), array());
    }


    public function TestAdd() {

        $C= $this->BuildComponent();
        $this->assertNotEqual($C->Add('TestWork', 'a'), false);
        // validate
        $this->assertEqual($C->GetCount('TestWork'), 1);
        $Jobs= $C->GetList();
        $this->assertEqual($Jobs[0]['JobData'], 'a');
    }


    public function TestDelete() {

        $C= $this->BuildComponent();
        $this->ClearTable();
        // add 3 tasks
        $C->Add('TestWork', 'x');
        $C->Add('TestWork', 'y');
        $C->Add('TestWork', 'z');
        // load all
        $Jobs= $C->GetList();
        list($Job1,$Job2,$Job3)= $Jobs;
        // remove task from middle (with 'y' data)
        $C->Delete($Job2['Id']);
        // reload & validate
        $Jobs= $C->GetList();
        list($Job1,$Job2)= $Jobs;
        $this->assertEqual($Job1['JobData'], 'x');
        $this->assertEqual($Job2['JobData'], 'z');
        //$this->ShowDatabaseQueries();
    }


    public function TestRelease() {

        // Because manager cannot "claim" job we must include Queue\Worker to test this functionality,
        // but Worker class should to be isolated in its own test case.
        // This method is just proxy to driver's Release method so we decided to skip this method,
        // it will be covered in Worker testing.
    }


}


?>