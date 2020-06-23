<?php namespace Accent\AccentCore\Debug;
/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


/**
 * Tracer.
 *
 * There is three levels of message importance: debug, info and error.
 * An component should emit message on info level at just few places in code, for example information about state of
 * component or important changes. Developers usually set 'DefaultLevel' in tracer configuration to 'info' to have
 * overview of application's lifecycle so don't overwhelm them with tons of messages.
 */

use \Accent\AccentCore\Component;
use \Accent\AccentCore\Debug\Debug;


class Tracer extends Component {


    protected static $DefaultOptions= array(

        // location of logging file (without extension)
        'FilePath'=> '@AppDir/Data/Logs/tracer',

        // triggering levels of each section ['Debug', 'Info', 'Error']
        // items here are typically declared as 'Debug' or 'Info', omitted items will use DefaultLevel
        'SectionLevel'=> array(
            //'Kernel'=> 'Info',
            //'Router'=> 'Debug',
        ),

        // apply this level for sections omitted from 'SectionLevel'
        'DefaultLevel'=> 'Error', // typically 'Error'
    );

    // level of importance, method Trace will ignore messages with levels above its $Level value
    // descending class can inject more levels in this list
    const LEVEL_DEBUG  = 30;
    const LEVEL_INFO   = 20;
    const LEVEL_ERROR  = 10;
    const LEVEL_ALWAYS = 0;

    // internal property
    protected $Debugger;


    public function __construct($Options) {

        parent::__construct($Options);

        // because tracer is typically instantied very early we need to setup event listener
        // to update configuration after loading of application's configuration
        //$Event= $this->GetService('Event');
        //$Event->AttachListener('Kernel.Config', array($this, 'UpdateConfig'), 4);
    }


	/**
	 * Main method. It will store message in tracing log.
	 *
	 * @param string $Section
	 * @param int $Level
	 * @param string $Message
	 * @param bool $AppendCallStack
	 */
    public function Trace($Section, $Level, $Message, $AppendCallStack=false) {

        if (!$this->Debugger) {

            // create its own debugger instance
            $this->Debugger= new Debug;

            // init profiler
            $LogFilePath= $this->ResolvePath($this->GetOption('FilePath'));
            $this->Debugger->ProfilerStart($LogFilePath, 'PROFILER REPORT');

            // special case for first call: if $AppendCallStack is supplied as array use them as debug-mark profiler data
            if (is_array($AppendCallStack)) {
                $this->Debugger->Mark($Message, $AppendCallStack[0], $AppendCallStack[1]);
                // return now, message is already inserted
                return;
            }
        }

        // check is this message important enough for storing
        if (!$this->IsAllowedLevel($Section, $Level)) {
            return;
        }

        // attach call-stack table to message?
        if ($AppendCallStack === true) {
            $RemovePrefixes= array(
                $this->GetOption('Paths.AppDir'),
                $this->GetOption('Paths.AccentDir'));
            $Lines= $this->Debugger->ShowSimplifiedStack($RemovePrefixes, 3);
            $Message .= "\n\nCall-stack:\n".$Lines."\n\n".str_repeat(' ', 200)."\n\n";
        }

        // send message to debugger
        $this->Debugger->Mark($Message);
    }


    /**
     * Compare supplied $Level against configured level for $Section.
     *
     * @param string $Section
     * @param int $Level
     * @return bool
     */
    protected function IsAllowedLevel($Section, $Level) {

        // special case, always allow level zero
        // it is used to create and close log file
        if ($Level === 0) {
            return true;
        }

        // get configured minimum tracing level
        $ConfiguredLevel= ($Section)
            ? $this->GetOption('SectionLevel.'.$Section, $this->GetOption('DefaultLevel'))
            : $this->GetOption('DefaultLevel');

        // convert to integer if defined as string
        if (is_string($Level)) {
            $Level= constant('self::LEVEL_'.strtoupper($Level));
        }
        if (is_string($ConfiguredLevel)) {
            $ConfiguredLevel= constant('self::LEVEL_'.strtoupper($ConfiguredLevel));
        }

        // return false if $Level greater then allowed level
        return $Level <= $ConfiguredLevel;
    }


    /**
     * Event listener on 'Kernel.Config', late execution.
     * Updating internal configuration.
     */
    public function OnUpdateConfig() {

        // get configuration from application
        $Config= $this->GetService('Config')->GetConfig('main')->GetAll();

        // merge and save
        $this->Options= $this->MergeArrays(array($this->Options, $Config['Tracer']));
    }


    /**
     * Export collected data.
     *
     * @return array
     */
    public function GetProfilerData() {

        if ($this->Debugger) {
            return $this->Debugger->GetProfilerData();
        } else {
            return null;
        }
    }

}

