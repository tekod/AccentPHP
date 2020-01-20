<?php namespace Accent\Queue;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Class for holding and manage data about single job from queue.
 *
 * Job data fetched from storage are packed into this object and passed as parameter to job processors.
 * Jobs are executed through event listeners.
 * Event listener designed for precessing queue job is called "job processor".
 * After successful handling listener must execute: $Job->SetHandled(); to inform further, lower prioritized,
 * listeners to skip their execution. Therefore each listener must begin with: if ($Job->IsHandled()) {return;}
 * If job need to postponed then call: $Job->Release(.....) together with SetHandled()
 * Advanced feature of queuing jobs is posibility to intercept and modify job record on-fly. Early executed
 * listener can use GetRecord()/SetData() methods to adjust payload and then delegate processing to next listener.

 */

use Accent\AccentCore\Component;


class Job extends Component {


    /* default values for constructor array */
    protected static $DefaultOptions= array(

        // instance of worker
        'Worker'=> null,

        // data from queue storage
        'JobRecord'=> array(),

        // services
        'Services'=> array(
            'Event'=> 'Event',
        ),

    );

    // internal
    protected $Record;
    /* @var \Accent\Queue\Worker */
    protected $Worker;
    protected $Handled= false;
    protected $Released= false;


    /**
     * Constructor.
     */
    public function __construct($Options) {

        // call ancestor
        parent::__construct($Options);

        // expose some options
        $this->Record= $this->GetOption('JobRecord');
        $this->Worker= $this->GetOption('Worker');
    }


    /**
     * Getter of job record.
     * Setter does not exist, only field allowed to modify is 'JobData' via GetData/SetData.
     *
     * @param string $Field  (optional) name of specific field to retrieve
     * @return array|string
     */
    public function GetRecord($Field=null) {

        return $Field === null
            ? $this->Record
            : $this->Record[$Field];
    }


    /**
     * Getter of job record data.
     *
     * @return mixed
     */
    public function GetData() {

        return $this->Record['JobData'];
    }


    /**
     * Setter of job record data.
     *
     * @param mixed $Data  payload of data
     */
    public function SetData($Data) {

        $this->Record['JobData']= $Data;
    }


    /**
     * Return current worker object. Job processors should use ???? instead of access worker directly but just in case...
     *
     * @return \Accent\Queue\Worker
     */
    public function GetWorker() {

        return $this->Worker;
    }


    /**
     * Fetch flag "handled".
     *
     * @return bool
     */
    public function GetHandled() {

        return $this->Handled;
    }


    /**
     * Set flag "handled".
     *
     * @param bool $Status
     */
    public function SetHandled($Status=true) {

        $this->Handled= (bool)$Status;
    }


    /**
     * Set flag "released", and logically affecting also "handled".
     *
     * @param bool $Status
     * @param bool $IncFailCount  increment count of failed attempts
     * @param null|int $RunAfter  schedule time of next attempt
     * @return mixed
     */
    public function SetReleased($Status=true, $IncFailCount=true, $RunAfter=null) {

        $this->SetHandled($Status);
        $this->Released= $Status
            ? array($IncFailCount, $RunAfter)
            : false;
    }


    /**
     * Fetch flag "released".
     *
     * @return false|array
     */
    public function GetReleased() {

        return $this->Released;
    }




}

?>