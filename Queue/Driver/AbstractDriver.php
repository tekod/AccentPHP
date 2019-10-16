<?php namespace Accent\Queue\Driver;


use Accent\AccentCore\Component;
//use Accent\AccentCore\Debug\Logger;


abstract class AbstractDriver extends Component {


    /* default values for constructor array */
    protected static $DefaultOptions= array(

        // unique identifier of job executioner, typically string with 8 random chars
        'WorkerId'=> '',
    );


    /**
     * Create/append job to queue.
     *
     * @param string $JobName  name of job will be used to find appropriate class to handle/process it
     * @param $JobData  arbitrary data (usually array) that will be passed to handler
     * @param int $Priority  prioritized jobs will be executed before others (lowest first, typically [-1,0,1], default 0)
     * @param null|int $RunAfter  timestamp, used to prevent execution of job before some point in time
     * @return bool  success
     */
    abstract public function Add($JobName, $JobData, $Priority=0, $RunAfter=null);


    /**
     * Query storage and find available job(s).
     * Selected jobs will remain on storage but locked (claimed) to prevent concurrent workers to mess with them.
     * Later on application must either Release() or Delete() reserved jobs.
     * Return value is a array of all claimed records.
     *
     * @param null|string $JobName  identifier of job used to find appropriate job processor or all jobs
     * @param bool $SingleJob  find and return only first job in queue or all available jobs
     * @return array
     */
    abstract public function Claim($JobName=null, $SingleJob=true);


    /**
     * Removes specified job from queue completely.
     *
     * @param $Id
     */
    abstract public function Delete($Id);


    /**
     * Opposite of Claim() method, removes identifier from specified job making it available for claiming by other workers.
     * Optionally this will increment fail-counter.
     * Next execution can be delayed (cooldown) by specifying RunAfter timestamp in future.
     *
     * @param array $Record  record of current job
     * @param bool $IncFailCount  allow incrementing fail-counter
     * @param null|int $RunAfter  timestamp
     */
    abstract public function Release($Record, $IncFailCount=true, $RunAfter=null);


    /**
     * Remove everything from queue, even jobs currently processing.
     */
    abstract public function Clear();


    /**
     * Find number of unprocessed jobs in queue.
     *
     * @param null|string $JobName  specify name of job to get count of that jobs
     * @param bool $IncludingDeferred  whether to include jobs scheduled in future or only currently available
     * @return int
     */
    abstract public function GetCount($JobName=null, $IncludingDeferred=false);


    /**
     * Return list of unprocessed jobs in queue, without claiming or any other side effect.
     *
     * @param null|string $JobName  specify name of job to get list of that jobs
     * @param bool $IncludingDeferred  whether to include jobs scheduled in future or only currently available
     * @return array
     */
    abstract public function GetList($JobName='', $IncludingDeferred=false);

}

?>