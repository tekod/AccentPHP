<?php namespace Accent\Queue\Test;

/**
 * Testing Accent\Queue\Worker
 */

use Accent\AccentCore\Event\Event;
use Accent\Test\AccentTestCase;


class Test__Queue_Worker extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Worker';

    // title of testing group
    const TEST_GROUP= 'Queue';

    // location of database
    protected $DatabaseInMemory= false;

    // internal
    protected $DB;   /* @var \Accent\DB\Driver\AbstractDriver $DB */


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
     * @return \Accent\Queue\Worker
     */
    protected function BuildComponent($Options=array()) {

        $Options += array(
            'Table'=> 'queue',
            'Services'=> array(),
        );
        $Options['Services'] += array(
            'DB'=> $this->DB,
            'Event'=> new \Accent\AccentCore\Event\EventService,
        );
        return new DemoWorker($Options);
    }


    public function TestBuild() {

        $Worker= $this->BuildComponent();
        // check is driver created successfully
        $this->assertTrue($Worker->IsInitied());
    }


    public function TestInstanceId() {

        $Worker= $this->BuildComponent();
        $Id= $Worker->GetInstanceId();
        $this->assertTrue(is_string($Id));
        $this->assertEqual(strlen($Id), 8);
    }


    public function TestGetters() {

        $Worker = $this->BuildComponent();
        $this->assertEqual($Worker->GetCount('TestWork'), 0);
        $this->assertEqual($Worker->GetList(), array());
    }


    public function TestAdd() {

        $Worker = $this->BuildComponent();
        $this->assertNotEqual($Worker->Add('TestWork', 'a'), false);
        // validate
        $this->assertEqual($Worker->GetCount('TestWork'), 1);
        $Jobs= $Worker->GetList();
        $this->assertEqual($Jobs[0]['JobData'], 'a');
    }


    public function TestClaim() {

        $Worker = $this->BuildComponent();
        $this->ClearTable();
        // add few tasks
        $Worker->Add('SkipWork', 'skipped', -1);  // this is the most prioritized but with wrong jobname
        $Worker->Add('TestWork', 'x', 1);
        $Worker->Add('TestWork', 'y', 1);
        $Worker->Add('TestWork', 'z', 0);
        // claim one
        $this->assertEqual($Worker->GetCount(null), 4);
        $this->assertEqual($Worker->GetCount('TestWork'), 3);
        $Jobs= $Worker->Claim('TestWork');
        // it must be 'z' because of priority '0'
        $this->assertEqual($Jobs[0]['JobData'], 'z');
        // claim another
        $Jobs= $Worker->Claim('TestWork');
        // it must be 'x' because it is oldest within its priority group
        $this->assertEqual($Jobs[0]['JobData'], 'x');
        // only one 'TestWork' job left available
        $this->assertEqual($Worker->GetCount('TestWork'), 1);
        // claim another but with any jobname, it must claim 'SkipWork'
        $Jobs= $Worker->Claim();
        $this->assertEqual($Jobs[0]['JobData'], 'skipped');
    }


    public function TestRelease() {

        $Worker = $this->BuildComponent();
        $this->ClearTable();
        // add few tasks
        $Worker->Add('TestWork', 'x');
        $Worker->Add('TestWork', 'y');
        $Worker->Add('TestWork', 'z');
        // claim one and release it
        $Jobs= $Worker->Claim();
        $Worker->Release($Jobs[0]);
        // claim another and release it, it must be 'y' because 'x' has bigger fail-count
        $Jobs= $Worker->Claim();
        $this->assertEqual($Jobs[0]['JobData'], 'y');
        $Worker->Release($Jobs[0]);
        // claim and release again, it must be 'z'
        $Jobs= $Worker->Claim();
        $this->assertEqual($Jobs[0]['JobData'], 'z');
        $Worker->Release($Jobs[0]);
        // claim again, it must be 'x' again because all of them has same priority and fail-count but this is oldest
        $Jobs= $Worker->Claim();
        $this->assertEqual($Jobs[0]['JobData'], 'x');
        // this time, release it without incrementing fail-count
        // on next claim we must get 'x' again
        $Worker->Release($Jobs[0], false);
        $Jobs= $Worker->Claim();
        $this->assertEqual($Jobs[0]['JobData'], 'x');
        // ok, release it again without incrementing fail-count but with RunAfter param
        // on next claim it must be skipped
        $Worker->Release($Jobs[0], false, time()+86400);
        $Jobs= $Worker->Claim();
        $this->assertEqual($Jobs[0]['JobData'], 'y');
        // test what happen if RunAfter point in past
        $Worker->Release($Jobs[0], false, time()-3600);
        $Jobs= $Worker->Claim();
        $this->assertEqual($Jobs[0]['JobData'], 'y');
    }


    public function TestDelete() {

        $C = $this->BuildComponent();
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
    }


    public function TestRun() {

        // create separate event service
        $EventService= new \Accent\AccentCore\Event\EventService;
        // register demo job-processor as listener
        $DemoJobHandler= new DemoProcessor;    // non-Component descender
        $EventService->AttachListener('Queue.Worker.Process:TestWork', array($DemoJobHandler,'HandleTestWork'));
        $EventService->AttachListener('Queue.Worker.Process:*', array($DemoJobHandler,'HandleWildcard'));
        // build component
        $Worker = $this->BuildComponent(array('Services'=> array('Event'=>$EventService)));
        $this->ClearTable();

        // add 3 tasks
        $Worker->Add('TestWork', 'x');  // this task must be successfully executed
        $Worker->Add('TestWork', 'y');  // this task must fail on first call and successfully executed on second call
        $Worker->Add('TestWork', 'z');  // this task must fail everytime
        $Worker->Add('SomeWeirdName', 'a');   // this task must be captured by wildcard listener
        // run worker
        $Worker->Run();
        // fetch logfile and search for expected strings
        $Dump= file_get_contents(__DIR__.'/tmp/log.txt');
        $Lines= array(
            '#1:0: "x": ok.',
            '#2:0: "y": release.',
            '#3:0: "z": release.',
            '#2:1: "y": ok.',
            '#3:1: "z": release.',
            '#3:2: "z": release.',
            '#3:3: "z": release.',
            '#4:0: "a": ok.',
            'TOOMANYFAILS #3',
        );
        foreach($Lines as $Line) {
            $this->assertTrue(strpos($Dump, $Line) !== false);
        }
        //$this->ShowDatabaseQueries();
    }


    public function TestRunTerminate() {

        // create separate event service
        $EventService= new \Accent\AccentCore\Event\EventService;
        // register demo job-processor as listener
        $DemoJobHandler= new DemoProcessor;    // non-Component descender
        $EventService->AttachListener('Queue.Worker.Loop', array($DemoJobHandler,'OnLoop'));
        $EventService->AttachListener('Queue.Worker.Process:TestWork', array($DemoJobHandler,'HandleTestWork'));
        // build component
        $Worker = $this->BuildComponent(array('Services'=> array('Event'=>$EventService)));
        $this->ClearTable();
        // add 3 tasks
        $Worker->Add('TestWork', 't');  // this task must terminate worker
        $Worker->Add('TestWork', 'x');  // this task must not be executed
        // remove log2 file and run worker
        @unlink(__DIR__.'/tmp/log2.txt');
        $Worker->Run();
        // fetch logfile and search for expected strings
        $Dump= file_get_contents(__DIR__.'/tmp/log.txt');
        $this->assertTrue(strpos($Dump, '"t": ok.') !== false);
        $this->assertTrue(strpos($Dump, '"x"') === false);
        
        // clear playground
        (new \Accent\AccentCore\File\File)->DirectoryClear( __DIR__.'/tmp');
    }

    // TODO: test OnShutdown?
}



?>