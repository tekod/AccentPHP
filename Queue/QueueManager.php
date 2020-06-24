<?php namespace Accent\Queue;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * QueueManager is interface between application and storage spaces where persistence is implemented.
 * Application can use QueueManager to add/read/delete jobs from queue.
 */

use Accent\AccentCore\Component;


class QueueManager extends Component {


    /* default values for constructor array */
    protected static $DefaultOptions= array(

        // name or instance of queue-storage driver
        // currently only (local) DB driver is supported
        // there are plans for supporting popular services like Resque, ActiveMQ, RabbitMQ, Gearman
        'Driver'=> 'Db',

        // version of Accent/Queue package
        'Version'=> '1.0.0',

        // services
        'Services'=> array(
            // required by DbDriver: 'DB'=> 'DB',
        ),

        // other keys may be required by chosen driver
    );

    protected $Driver;


    public function __construct($Options) {

        parent::__construct($Options);

        $this->Driver= $this->BuildDriver();
        $this->Initied= $this->Driver->IsInitiated();
    }


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
        return new $Driver($this->GetAllOptions());
    }


    /**
     * Get number of jobs waiting for processing.
     *
     * @param string|null $JobName  narrow search by specified job name     *
     * @param bool $IncludingDeferred  whether to include jobs scheduled in future or only currently available
     * @return int
     */
    public function GetCount($JobName=null, $IncludingDeferred=false) {

        return $this->Driver->GetCount($JobName, $IncludingDeferred);
    }


    /**
     * Get array of jobs waiting for processing, including ones scheduled in future.
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
    public function Add($JobName, $JobData=null, $Priority=0, $RunAfter=null) {

        return $this->Driver->Add($JobName, $JobData, $Priority, $RunAfter);
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

}

?>