<?php namespace Accent\AccentCore\Test;

use Accent\Test\AccentTestCase;
use Accent\AccentCore\Event\EventService;


/**
 * Testing Event component
 *
 * @TODO: test $ReturnEvent param in Event->Execute
 */

class Test__Event extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Event service';

    // title of testing group
    const TEST_GROUP= 'AccentCore';


    public function __construct() {

        // parent
        parent::__construct();

        // init common Event component
        $Options= array('App'=>'TestApp');
        $this->EventMan= new EventService($Options);
    }


    // Demo listener A,
    // will test attaching as dynamic callable,
    // will test using execution context vaues,
    // will test SetHandled feature.
    public function DemoListener_A($Event) {

        $EventName= $Event->EventName;
        $Subject= $Event->GetSubject();

        // prepend event name to subject
        $Event->SetSubject($EventName.':'.$Subject);

        // set "handled" flag
        $Event->SetHandled();
    }


    // Demo listener B,
    // will test attaching & calling as static method,
    // also will test returning true.
    public static function DemoListener_B($Event) {

        // increment counter
        // pinging must NOT be conditioned by counter
        $Event->Ping();

        // get counter value and return true after 4 executions
        return $Event->GetCounter() > 4
            ? true
            : false;
    }



    // TESTS:




	public function TestAttachListener() {

        // test: AttachListner
		$Res= $this->EventMan->AttachListener('TestingA', array($this,'DemoListener_A'));
		$this->assertEqual($Res, true);

        // attach static callable, with $Owner
        $Res= $this->EventMan->AttachListener('TestingB', array(__CLASS__,'DemoListener_B'), 0, 'Me');
		$this->assertEqual(strlen($Res), 32);
        $this->TestB_Id= $Res; // will be used later

        // attach closure
        $Res= $this->EventMan->AttachListener('TestingC', function($Ev){
            $Ev->SetSubject('ABCD');
        });
		$this->assertEqual($Res, true);
	}


	public function TestHasListeners() {

		$Res= $this->EventMan->HasListeners('TestingA');
		$this->assertEqual($Res, true);

        $Res= $this->EventMan->HasListeners('TestingXYZ');
		$this->assertEqual($Res, false);
	}


	public function TestExecute() {

        // create event A
        $EventA= new DemoEventA;
        $EventA->SetSubject('Hello');  // intial value

        // execute event A
		$Res= $this->EventMan->Execute('TestingA', $EventA);

        // result of execution must false because loop was not terminated
		$this->assertEqual($Res, false);

        // event must be handled
        $this->assertEqual($EventA->IsHandled(), true);

        // event must contain modified subject
        $this->assertEqual($EventA->GetSubject(), 'TestingA:Hello');

        // create event B, injecting value in contructor
        $EventB= new DemoEventB(['Counter'=>73]);

        // test injected value
        $this->assertEqual($EventB->GetCounter(), 73);

        // execute event B
        $Res= $this->EventMan->Execute('TestingB', $EventB);

        // result of execution must be true because loop is terminated
        $this->assertEqual($Res, true);

        // event must be incresed by one
        $this->assertEqual($EventB->GetCounter(), 74);

        // counter must be unchanged
        $this->assertEqual($EventB->IsHandled(), false);

        // exec closure listener
        $EventA2= new DemoEventA;
        $this->EventMan->Execute('TestingC', $EventA2);
		$this->assertEqual($EventA2->GetSubject(), 'ABCD');
	}


    public function TestTerminatingLoop() {

        // attach 20 listeners
        for($x=0; $x<20; $x++) {
            $this->EventMan->AttachListener('TestingLoop', array(__CLASS__,'DemoListener_B'));
        }

        // create & execute event
        $EventB= new DemoEventB;
        $Res= $this->EventMan->Execute('TestingLoop', $EventB);

        // result of execution must be true because loop is terminated
        $this->assertEqual($Res, true);

        // counter must be 5 meaning that listener was executed only 5 times
        // remember that listener has unconditional Ping() instruction
        $this->assertEqual($EventB->GetCounter(), 5);
    }


    public function TestWildcards() {

        // attach wildcard listener,
        // with priority 4 it will execute AFTER regular listener,
        // and as last in chain it will overwrite subject with its own string
        $this->EventMan->AttachListener('Testing*', function($Ev){
            $Ev->SetSubject('QQ');
            return true;
        }, 4);

        // create & exec event 'TestingA'
        $Ev= new DemoEventA;
        $Res= $this->EventMan->Execute('TestingA', $Ev);

        // loop was terminated
		$this->assertEqual($Res, true);

        // subject must be overwritten
        $this->assertEqual($Ev->GetSubject(), 'QQ');
    }


    public function TestDetachListeners() {

        // confirm it is still present
        $this->assertEqual($this->EventMan->HasListeners('TestingB'), true);

        // detach
        $this->EventMan->DetachListener($this->TestB_Id);

        // confirm it is gone
        $this->assertEqual($this->EventMan->HasListeners('TestingB'), false);
    }


    public function TestClearByOwner() {

        // attach my own listener and confirm
        $this->assertEqual($this->EventMan->HasListeners('TestW'), false);
        $this->EventMan->AttachListener('TestW', function($Ev){}, 0, 'Me');
        $this->assertEqual($this->EventMan->HasListeners('TestW'), true);

        // remove all my listeners
        $this->EventMan->ClearByOwner('Me');

        // confirm it is gone
        $this->assertEqual($this->EventMan->HasListeners('TestW'), false);
    }


    public function TestClearByName() {

        // attach new listener and confirm
        $this->assertEqual($this->EventMan->HasListeners('TestS'), false);
        $this->EventMan->AttachListener('TestS', function($Ev){});
        $this->assertEqual($this->EventMan->HasListeners('TestS'), true);

        // remove that listener by event name
        $this->EventMan->ClearByEventName('TestS');

        // confirm it is gone
        $this->assertEqual($this->EventMan->HasListeners('TestS'), false);

        // now clear all
        $this->EventMan->ClearByEventName();

        // confirm it is empty now
        $this->assertEqual($this->EventMan->HasListeners('TestingA'), false);
    }


    public function TestGetStatistics() {

        // trigger orphan event
        $this->EventMan->Execute('SomeMissingEventName', new DemoEventA);

        // get stats
        $Res= $this->EventMan->GetStatistics();

        // get number of executed events
        $this->assertEqual($Res['Hits']['TestingA'], 2);

        // get number of misses
        $this->assertEqual($Res['Misses']['SomeMissingEventName'], 1);
    }



}


