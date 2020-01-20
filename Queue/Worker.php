<?php namespace Accent\Queue;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Class Worker implementing long-term execution of queued jobs.
 *
 * Typically it is called in two scenarios:
 *  - called by console controller, triggered by cron or supervisor system, as mid-term process
 *  - called by web front controller as long-term process
 *
 * First method is way to achieve parallel multiprocessing and ensures that execution will continue after unexpected
 * termination. Drawback is that some jobs may be terminated in middle of execution or may be executed multiple times.
 * This is because of nature of console process: only way to terminate it if it hangs is timeout so application should
 * set reasonable timeout to prevent out-of-memory in case of thousands hanged workers in memory.
 *
 * Second method is simplier to implement. It is typically triggered by AJAX call or request to image (spacer.gif)
 * with src to PHP file. Drawback is that execution will be terminated if user close its browser/tab (unless proxy
 * messed that).
 *
 * Loop execution will be terminated when all jobs are executed. Application must sense that and spawn new worker
 * after small delay.
 */

use Accent\AccentCore\Component;
use Accent\Queue\Event\WorkerEvent;
use Accent\Queue\Event\JobEvent;


class Worker extends Component {


    /* default values for constructor array */
    protected static $DefaultOptions= array(

        // name or instance of queue-storage driver
        // currently only (local) DB driver is supported
        // there are plans for supporting popular services like Resque, ActiveMQ, RabbitMQ, Gearman
        'Driver'=> 'Db',

        // maximum allowed time for script (in seconds), remember this is only way to terminate hanged script
        'WorkerTTL'=> 600,

        // how many times to repeat execution of failed job before give-up
        'MaxFails'=> 3,

        // select which job type to process, null means all jobs
        'JobName'=> null,

        // should worker execute all jobs in loop or only first available
        'Loop'=> true,

        // services
        'Services'=> array(
            'Event'=> 'Event',
            // 'DB'=> 'DB', // optional, if using DbDriver
        ),

        // other keys may be required by chosen driver
    );


    // internal properties
    protected $Driver;
    protected $InstanceId;
    protected $StartTime;
    protected $JobCounter;
    /* @var \Accent\Queue\Job */
    protected $Job;



    public function __construct($Options) {

        parent::__construct($Options);
        $this->StartTime= time();

        // setup unique identifier
        $this->SetInstanceId();

        // setup driver
        $this->Driver= $this->BuildDriver();
        $this->Initied= $this->Driver->IsInitied();
    }


    /**
     * Generate random 8-char string and set it as unique instance identifier.
     */
    protected function SetInstanceId() {
        // There is no need for high quality random generator, this will be fine...
        $this->InstanceId= substr(md5(mt_rand(0,999999).uniqid().microtime(false)), 0, 8);
    }


    /**
     * Returns identifier of this instance.
     *
     * @return string
     */
    public function GetInstanceId() {

        return $this->InstanceId;
    }


    /**
     * Create driver object.
     *
     * @return \Accent\Queue\Driver\AbstractDriver
     */
    protected function BuildDriver() {

        $Driver= $this->GetOption('Driver');
        // is parameter already instantiated object?
        if (is_object($Driver)) {
            return $Driver;
        }
        // is it FQCN or short class name?
        if (strpos($Driver, '\\') === false) {
            $Driver= "Accent\\Queue\\Driver\\{$Driver}Driver";
        }
        // instantiate class passing all options
        return new $Driver(array(
            'WorkerId'=> $this->InstanceId,
        ) + $this->GetAllOptions());
    }



    /**
     * Get number of jobs waiting for processing.
     *
     * @param string|null $JobName  narrow search by specified job name
     * @param bool $IncludingDeferred  whether to include jobs scheduled in future or only currently available
     * @return int
     */
    public function GetCount($JobName=null, $IncludingDeferred=false) {

        return $this->Driver->GetCount($JobName, $IncludingDeferred);
    }


    /**
     * Get array of jobs waiting for processing.
     *
     * @param null|string $JobName  specify name of job to get list of that jobs
     * @param bool $IncludingDeferred  whether to include jobs scheduled in future or only currently available
     * @return array
     */
    public function GetList($JobName='', $IncludingDeferred=false) {

        return $this->Driver->GetList($JobName, $IncludingDeferred);
    }


    /**
     * Insert new job to queue.
     * Yes, worker (or job object) can call this method to append additional jobs, this is a way to achieve jobs chaining
     * (execution of next job is conditioned by successful execution of previous).
     *
     * @param string $JobName
     * @param mixed $JobData
     * @param int $Priority
     * @param int $RunAfter
     * @return bool
     */
    public function Add($JobName, $JobData, $Priority=0, $RunAfter=null) {

        return $this->Driver->Add($JobName, $JobData, $Priority, $RunAfter);
    }


    /**
     * Query queue storage and find available job(s).
     * Selected jobs will remain on storage but locked (claimed) to prevent concurrent workers to mess with them.
     * Later on worker will either Release() or Delete() reserved jobs.
     * Return value is a array of all claimed records.
     *
     * Job processor can call this method to load additional jobs to process all of them in one go
     * (for example: bulk email sending) but it must manually delete/release them at finnish.
     *
     * @param null|string $JobName  identifier of job used to find appropriate job processor or all jobs
     * @param bool $SingleJob  find and return only first job in queue or all available jobs
     * @return array
     */
    public function Claim($JobName=null, $SingleJob=true) {

        $MaxFails= $this->GetOption('MaxFails');
        do {
            // load from storage
            $Jobs= $this->Driver->Claim($JobName, $SingleJob);
            // remove jobs exceeding fail limit
            $Removed= false;
            foreach ($Jobs as $Key=>$Job) {
                if ($Job['FailCount'] > $MaxFails) {
                    $this->TooManyFails($Job);
                    $Removed= true;
                    unset($Jobs[$Key]);
                }
            }
            // try again if only one job requested and it was removed
        } while ($SingleJob && $Removed);

        // return array of jobs
        return $Jobs;
    }


    /**
     * Remove specified job from queue.
     * Note that worker will remove all processed jobs by itself, there is no need to explicitly call this from job processor.
     *
     * @param int $Id
     */
    public function Delete($Id) {

        $this->Driver->Delete($Id);
    }


    /**
     * Unclaim specified job.
     * Note that worker will unclaim all failed jobs by itself, there is no need to explicitly call this from job processor.
     *
     * @param array $Record
     * @param bool $IncFailCount
     * @param null|int $RunAfter
     * @return mixed
     */
    public function Release($Record, $IncFailCount=true, $RunAfter=null) {

        return $this->Driver->Release($Record, $IncFailCount, $RunAfter);
    }

    /**
     * In case of fatal error or timeout automatically unclaim current job so next tick can take ownership and try again.
     */
    public function OnShutdown() {

        if ($this->Job === false) {
            // clean exit
            return;
        }

        // unclaim current job and increment fail counter
        $this->Release($this->Job->GetRecord(), true, null);

        // log this
        $this->Log('Worker loop unexpectedly terminated (OnShutdown), last error:'."\n".var_export(error_get_last(),true));
    }


    /**
     * Main execution method.
     *
     * @return bool|null  status of last executed job
     */
    public function Run() {

        // adjust environment
        ini_set('memory_limit','512M');  // maximize memory pool
        ignore_user_abort(true);  // make it immune to closing browser
        set_time_limit($this->GetOption('WorkerTTL'));  // extend timeout (default 1 hour)
        $LastStatus= false;
        $this->JobCounter= 0;     // reset counter

        //$this->Log('Starting new worker...');

        // register on-shutdown-event, if application reference are present
        $App= $this->GetApp();
        if ($App) {
            $App->RegisterShutDown(array($this, 'OnShutdown'));
        }

        // notify listeners to register all job handlers
        $Event= new WorkerEvent(['Worker'=>$this]);
        $this->EventDispatch('Queue.Worker.RegisterHandlers', $Event);

        // start infinite loop
        do {

            // this is long term loop, allow PHP to do some internal housekeeping
            gc_collect_cycles();

            // is there any reason to terminate loop?
            if ($this->TerminateLoop()) {
                break; // jump out
            }

            // fetch next available job
            if (!$this->GetNextJob()) {
                break;  // jump out
            }

            // execute job
            $LastStatus= $this->ExecuteJob();

        } while ($this->GetOption('Loop'));

        $this->Job= false; // inform shutdown function about clean exit
        return $LastStatus;
    }


    /**
     * Check is there any reason to terminate worker loop.
     *
     * @return bool
     */
    protected function TerminateLoop() {

        // set_time_limit() will cause breaking PHP after TTL, it is better to nicely finish script before that happen
        if (time() - $this->StartTime > $this->GetOption('WorkerTTL')) {
            $this->Log('Worker TTL is exceeded, terminating loop');
            return true;
        }

        // allow event listeners to terminate loop (event interrupt mode)
        $Event= new WorkerEvent(['Worker'=>$this]);
        if ($this->EventDispatch('Queue.Worker.Loop', $Event)) {
            $this->Log('Worker loop is terminated by event listener');
            return true;
        }

        // continue loop
        return false;
    }


    /**
     * Fetch one job from the storage.
     *
     * @return false|\Accent\Queue\Job
     */
    protected function GetNextJob() {

        // get single job from driver
        $Jobs= $this->Claim($this->GetOption('JobName'));

        // no more jobs?
        if (empty($Jobs)) {
            if ($this->JobCounter > 0) {
                $this->Log('Worker finished, no more jobs.');
            }
            return false;
        }

        // instantiate job object containing first (and only) job
        $this->Job= new Job(array(
            'Worker'    => $this,
            'JobRecord' => $Jobs[0],
        ) + $this->GetCommonOptions());

        // success
        return true;
    }


    /**
     * Preform execution of current job.
     *
     * @return null|bool  true=success, false=job was released, null=job was unhandled
     */
    protected function ExecuteJob() {

        // ask event listeners to handle this job
        $JobId= $this->Job->GetRecord('Id');
        $JobName= $this->Job->GetRecord('JobName');
        $Event= new JobEvent(['Job'=>$this->Job]);
        $this->EventDispatch('Queue.Worker.Process:'.$JobName, $Event);

        // if nobody handles that job trigger orphan event
        // this is last chance to process this job
        if (!$this->Job->GetHandled()) {
            $this->EventDispatch('Queue.Worker.OrphanJob', $Event);
        }

        // if job remains unhandled
        if (!$this->Job->GetHandled()) {
            $this->EventDispatch('Queue.Worker.UnhandledJob', $Event);
            $this->Log("Unhandled job #$JobId ($JobName)");
            $this->UnhandledJob($this->Job->GetRecord());
            return null;
        }

        // if job has to be released
        if ($this->Job->GetReleased()) {
            // unclaim it and increment fail counter
            list($IncFailCount, $RunAfter)= $this->Job->GetReleased();
            $this->Driver->Release($this->Job->GetRecord(), $IncFailCount, $RunAfter);
            //$this->TraceDebug("Queue: released job #$JobId ($JobName)");
            $this->EventDispatch('Queue.Worker.ReleasedJob', $Event);
            return false;
        }

        // job was executed successfully, remove it from queue
        $this->Driver->Delete($JobId);
        //$this->TraceDebug("Queue: executed job #$JobId ($JobName)");
        $this->EventDispatch('Queue.Worker.ExecutedJob', $Event);
        return true;
    }


    /**
     * Perform specific action if job fails too many times.
     *
     * @param array $JobRecord
     */
    protected function TooManyFails($JobRecord) {

        // descendant classes may:
        //  - move record to separate "queue_failed" table
        //  - write log message about what happen
        //  - send email message
        //  - add this job to storage again but with much bigger RunAfter and delete current one

        // for this implementation simply remove it from storage
        $this->Driver->Delete($JobRecord['Id']);
    }


    /**
     * Perform specific action if nobody takes responsibility for this job.
     *
     * @param array $JobRecord
     */
    protected function UnhandledJob($JobRecord) {

        // descendant classes may:
        //  - move record to separate "queue_failed" table
        //  - write log message about what happen
        //  - send email message

        // for this implementation simply remove it from storage
        $this->Driver->Delete($JobRecord['Id']);
    }

}

?>