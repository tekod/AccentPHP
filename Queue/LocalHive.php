<?php namespace Accent\Queue;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

use Accent\AccentCore\Component;

class LocalHive extends Component {


    /* default values for constructor array */
    protected static $DefaultOptions= array(

        'LogDir'=> '@AppDir/Data/Logs/Queue',

        'WorkerConf'=> array(
            'MyName'=> array(
                'Class'=> 'Accent\\Queue\\Worker',
                'Instances'=> 1,    // max number of workers with this configuration
                'JobTTL'=> 60,      // max TTL for each individual job, if it expire hive will assume that worker is stucked
                'JobName'=> null,
                'WorkerTTL'=> 600,
                'MaxFails'=> 2,
            ),
        ),

        'Services'=> array(
            'Event'=>'Event',
        ),
    );

    // internal properties
    protected $Enabled=null;
    protected $LogDir;


    public function __construct($Options) {

        // call parent
        parent::__construct($Options);

        // setup internal prop
        $this->LogDir= $this->ResolvePath($this->GetOption('LogDir'));
        if (!is_dir($this->LogDir)) {
            @mkdir($this->LogDir, 0777, true);
        }
    }


    public function GetStatus() {

        // gather information about live workers
        $Logs= $this->CollectLogs();

        // sum count of all workers
        $Count= 0;
        foreach($Logs as $Name=>$Structs) {
            $Count += count($Structs);
        }
        return $Count;
    }


    public function Spawn() {

        // spawn new workers only in enabled state
        if (!$this->IsEnabled()) {
            return;
        }
        // gather information about live workers
        $Logs= $this->CollectLogs();
        // try to create one new worker, note: only one worker per thread can be created
        $Worker= null;
        foreach($this->GetOption('WorkerConf') as $Name => $Conf) {
            if (!isset($Logs[$Name]) || count($Logs[$Name]) < $Conf['Instances']) {
                $Worker= $this->BuildWorker($Name, $Conf);
                break;
            }
        }
        // terminate if worker does not created
        if (!$Worker) {
            return;
        }
        // setup event listener,
        // method Spawn is typically called once per request so it is safe to register listener here
        $this->RegisterEventListener('Queue.Worker.Loop', function($Param) {
            // notify hive that worker is still alive
            // this event is in interrupt mode, it should return true if Ack answers that worker should be stopped
            $Id= $Param['Worker']->GetInstanceId();
            //$Hive= $this->GetApp()->GetExtension('SimpleQueueTicker')->BuildHive();
            return !$this->Ack($Id);
        });
        // run worker
        $Worker->Run();
        // at this point worker has finished loop because there is no jobs to execute
        // instead of re-run worker in endless loop give system a small break and close current thread
        // that allows hive to spawn new worker in next (fresh) thread
        sleep(3);
        // remove log file to inform hive that this worker is over
        @unlink($this->LogDir.'/'.$Worker->GetInstanceId());
    }


    /**
     * Perform execution of one single job from queue, without any validations is it enabled or number of instances.
     * Use this during development phase but later on delegate it to hive->spawn().
     *
     * @param string|null $WorkerName
     */
    public function Step($WorkerName=null) {

        // try to create one new worker
        if ($WorkerName === null) {
            $Workers= array_keys($this->GetOption('WorkerConf'));
            $WorkerName= reset($Workers);
        }
        $Conf= $this->GetOption('WorkerConf.'.$WorkerName);
        if (!$Conf) {
            return;
        }
        $Worker= $this->BuildWorker($WorkerName, array('Loop'=>false)+$Conf, false);

        // run worker
        return $Worker->Run();
    }


    protected function BuildWorker($ConfName, $ConfData, $CreateLogFile=true) {

        // build worker object
        $Class= $ConfData['Class'];
        $Options= $ConfData + array('ConfName'=>$ConfName) + $this->GetCommonOptions();
        $Worker= new $Class($Options);
        // store worker's log file
        $Id= $Worker->GetInstanceId();
        $Path= $this->LogDir.'/'.$Id;
        $LogData= array('ConfName'=>$ConfName) + $ConfData;
        if ($CreateLogFile) {
            file_put_contents($Path, json_encode($LogData));
        }
        // return instance
        return $Worker;
    }


    public function Enable() {

        $this->SetEnabled(true);
    }


    public function Disable() {

        $this->SetEnabled(false);
    }


    /**
     * Return true if worker can continue to work.
     *
     * @param $WorkerId
     * @return bool
     */
    public function Ack($WorkerId) {
        // terminate worker if hive is disabled
        if (!$this->IsEnabled(true)) {
            return false;  // this will terminate worker loop and after small delay delete log file
        }
        // terminate worker if logfile missing
        $Path= $this->LogDir.'/'.$WorkerId;
        if (!is_file($Path)) {
            return false;  // this will terminate worker loop and after small delay delete log file
        }
        // set fresh timestamp
        touch($Path);
        return true;
    }


    public function IsEnabled($Reload=false) {

        if ($this->Enabled === null || $Reload) {
            $this->Enabled = intval(@file_get_contents($this->LogDir.'/.Enabled'));
        }
        return $this->Enabled;
    }


    protected function SetEnabled($Bool) {

        $this->Enabled= boolval($Bool);
        file_put_contents($this->LogDir.'/.Enabled', intval($Bool));
    }


    protected function CollectLogs() {

        $WorkerConf= $this->GetOption('WorkerConf');
        $Logs= array();
        $F= @dir($this->LogDir);
        if ($F === false) {
            $this->Error('LocalHive: log directory not found.');
            return false;
        }
        do {
            $Entry= $F->read();
            $Path= $this->LogDir.'/'.$Entry;
            if ($Entry === false) {
                break;
            }
            if ($Entry{0} === '.' || is_dir($Path)) {
                continue;
            }
            $Struct= (array)json_decode(file_get_contents($Path), true);
            $Struct += array(
                    'ConfName'=> '?',
                    'JobTTL'=> -1,
                );
            $ConfName= $Struct['ConfName'];
            $WorkerId= $Entry;
            // delete expired log files
            if (filemtime($Path)+$Struct['JobTTL'] < time()) {
                $this->TraceInfo('LocalHive: removed expired worker #'.$WorkerId.' "'.$ConfName.'".');
                @unlink($Path);
                continue;
            }
            // delete log files with unregistered ConfName, this will also remove all non-log files from directory
            if (!isset($WorkerConf[$ConfName])) {
                @unlink($Path);
                $this->TraceInfo('LocalHive: removed abandoned worker #'.$WorkerId.' "'.$ConfName.'".');
                continue;
            }
            // append log to collection
            if (!isset($Logs[$ConfName])) {
                $Logs[$ConfName]= array();
            }
            if (count($Logs[$ConfName]) < $WorkerConf[$ConfName]['Instances']) {
                $Logs[$ConfName][$WorkerId] = $Struct;
            } else {
                @unlink($Path);
                $this->TraceInfo('LocalHive: number of instances exceeded for worker #'.$WorkerId.' "'.$ConfName.'".');
                continue;
            }
        } while (true);
        $F->close();
        return $Logs;
    }


}

?>