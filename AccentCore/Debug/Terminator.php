<?php namespace Accent\AccentCore\Debug;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Terminator service will check for existence of specified "flag" files
 * and if found any of them terminate execution of program.
 *
 * There are two methods
 *
 * Usage examples:
 *
 *   $this->RegisterService('Terminator', 'Accent\\AccentCore\\Debug\\Terminator', array(
 *      'Dir'=> '@AppDir/Data/Log/Terminator',
 *   ));
 *
 *   $this->GetService('Terminator')->Check('Queue');  // this will "die" if flag file exist
 *
 *   if ($this->GetService('Terminator')->GetStatus('Queue')) {  // this will return true as signal that flag file exist
 *      break;
 *   }
 *
 *   $this->GetService('Terminator')->AddMonitor('myterm')->AddMonitor('secondterm'); // will check on evey event and "die" if any file exist
 */


use Accent\AccentCore\Component;


class Terminator extends Component {


    protected static $DefaultOptions= array(

        // initial state of component
        'Dir'=> '@AppDir/Data/Logs/Terminator',

        // version of component
        'Version'=> '0.1',

        // services
        'Services'=> array(
            'Event'=> 'Event',
        ),
    );

    // internal properties
    protected $Dir;
    protected $Monitors= array();
    protected $EventListenerId= null;

    /**
     * Constructor.
     */
    public function __construct($Options) {

        parent::__construct($Options);

        // export 'Dir' to local property
        $this->Dir= $this->ResolvePath($this->GetOption('Dir'));
    }


    /**
     * Check for marker file and die if found.
     *
     * @param string $File  filename
     */
    public function Check($File) {

        $Path= $this->Dir.'/'.$File;
        if (is_file($Path)) {
            file_put_contents($Path, 'TERMINATED @ '.date('r'));
            die("\nTerminated by flag file \"$File\".");
        }
    }


    /**
     * Only check existence of marker file.
     *
     * @param string $File  filename
     * @return bool
     */
    public function GetStatus($File) {

        return is_file($this->Dir.'/'.$File);
    }


    /**
     * Add marker file to internal list for continous monitoring.
     * Also start monitoring process.
     *
     * @param string $FileName
     * @return self
     */
    public function AddMonitor($FileName) {

        $this->Monitors[]= $FileName;
        array_unique($this->Monitors);

        $this->StartMonitoring();
        return $this;
    }


    /**
     * Delete marker file from internal list.
     *
     * @param string $FileName
     * @return self
     */
    public function RemoveMonitor($FileName) {

        $Ind= array_search($FileName, $this->Monitors);
        if ($Ind !== false) {
            unset($this->Monitors[$Ind]);
        }
        return $this;
    }


    /**
     * Start watching monitored files.
     */
    public function StartMonitoring() {

        if ($this->EventListenerId !== null) {
            return;         // already started
        }
        $this->EventListenerId= $this->RegisterEventListener('*', 'OnMonitor', 0, get_class());
    }


    /**
     * Stop watching monitored files.
     */
    public function StopMonitoring() {

        $this->GetService('Event')->DetachListener($this->EventListenerId);
        $this->EventListenerId= null;
    }


    /**
     * Event handler.
     */
    public function OnMonitor() {

        foreach($this->Monitors as $FileName) {
            $this->Check($FileName);
        }
    }

}
