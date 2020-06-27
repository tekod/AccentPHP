<?php namespace Accent\AccentCore\Debug;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Set of methods for various debugging tasks:
 *   - measuring time of execution (benchmark, profiling)
 *   - getting tracing data to particular execution point (call stack)
 *   - generating application execution log
 *   - displaying arbitrary variable content
 *   - getting list and sizes of currently included PHP files
 *
 * This class is self-contained in order to achieve isolation of running
 * application and current context of its execution.
 * That also allows to use methods at very early stages of application workflow.
 * Because of that it does NOT depend on any other classes from core package,
 * not even Component class.
 *
 * Example of using statically:
 *     use \Accent\AccentCore\Debug\Debug;
 *     Debug::Instance()->LoggerStart(...);
 *     Debug::Instance()->Log(...);
 *     Debug::Instance('MyAnotherInstance')->LoggerStart(...);
 * Example of calling dynamically:
 *     $Debug= new \Accent\AccentCore\Debug\Debug;
 *     $Debug->LoggerStart(...);
 *     $Debug->Log(...);
 * Note that only in dynamic invoking user can pass parameters in constructor.
 *
 * This class is not descendant of Component class in order to be operative as early as possible
 * and be independent and isolated from other systems.
 * That's why it has directly access to super-globals.
 */


class Debug {

    // identifier of session,
    // see constructor description for possible values
    protected $AuthKey= false;

    // value fetched from cookie
    protected $AuthKeyFromCookie= 0;

    // as string to specify path of log file
    // as boolean true to keep logs in memory
    protected $ProfilerFile= false;

    // TLogger object
    protected $Profiler;

    // timestamp of first profiler event
    protected $ProfilerStartTime;

    // timestamp of last profiler event
    protected $ProfilerPrevTime;

    // maximum loop level for VarDump method
    protected $MaxVarDumpNesting= 5;

    // internal buffer for detecting recursions
    protected $VarDumpHashes= array();

    // storage for statically built instances
    protected static $Instances= array();

    // name of cookie for session identification
    protected $CookieName= '';


    /**
     * Constructor.
     *
     * Possible options:
     *   AuthKey: - set to true/false to implicitly enable logging and profiling functions
     *            - set as integer to enable for users with cookie containing that integer
     *
     * @param array $Options
     */
    public function __construct($Options=array()) {

        // set default (missing) options
        $Options += array(
            'AuthKey'=> true,                   // allow to all by default
            'CookieName'=> 'AccentDebugKey',
        );

        // export options
        $this->AuthKey= $Options['AuthKey'];
        $this->CookieName= $Options['CookieName'];

        // if AuthKey is string allow user to set cookie value via GET
        if (strlen($this->AuthKey) >= 10 && isset($_GET[$this->CookieName]) && $_GET[$this->CookieName] === $this->AuthKey) {
            setcookie($this->CookieName, $_GET[$this->CookieName], time()+86400*365, '/');
            // make it valid for current request
            $_COOKIE[$this->CookieName]= $_GET[$this->CookieName];
        }

        // get ID of current user
        $this->AuthKeyFromCookie= isset($_COOKIE[$this->CookieName])
            ? $_COOKIE[$this->CookieName]
            : false;
    }


    /**
     * Factory (singleton) method for invoking class statically.
     *
     * @param string $InstanceName  allows to call specified instance by its name
     * @param array $Options  configuration for new object
     * @return object
     */
    public static function Instance($InstanceName='Primary', $Options=array()) {

        if (!isset(static::$Instances[$InstanceName])) {
            static::$Instances[$InstanceName]= new static($Options);
        }
        return static::$Instances[$InstanceName];
    }


    /**
     * Is current visitor authenticated as developer?
     * This will allow writing log files on disk.
     */
    public function IsAuthorizedSession() {

        if (is_bool($this->AuthKey)) {
            return $this->AuthKey;
        }

        return $this->AuthKeyFromCookie === $this->AuthKey;
    }



//---------------------------------------------------------------------------
//
//                Call-stack functions
//
//---------------------------------------------------------------------------


    /**
     * Display specified stack point in format: "/../include/index.php on line (146)"
     *
     * @param int $n  specify which point to show
     * @param bool $ShowFullPath  whether to show only basename of file or full path
     * @return string
     */
    public static function ShowStackPoint($n, $ShowFullPath=true) {

    	$CallStack = debug_backtrace();
    	if (!isset($CallStack[$n])) {
    		return "sorry, stack point '$n' not exist.";
        }
    	$Res= '';
    	$Line= $CallStack[$n];
    	$Res .= (isset($Line['file']))
    		? ($ShowFullPath ? $Line['file'] : basename($Line['file']))
    		: "unknown file (anonymous func or call_user_func)";
    	$Res .= (isset($Line['line']))
    		? " on line ($Line[line])"
    		: ", unknown line number";
    	return ($Res)
    		? $Res
    		: "sorry, no information about point '$n'.";
    }


    /**
     * Display all "call stack" points.
     * This method can be called statically and dynamically.
     *
	 * @param bool $RenderTable
     * @return string|array
     */
    public static function ShowStack($RenderTable=true) {

        $CallStack = debug_backtrace();
        $Lines= array();
        foreach($CallStack as $i=>$Trace) {
            if($i) {
                $What= (isset($Trace['class']))
                    ? "$Trace[class]$Trace[type]$Trace[function]"
                    : "$Trace[function]";
                $What .= "()";
            } else {
                $What= "triggered ".substr(__METHOD__, strrpos(__METHOD__, '::')+2).'()';
            }
            $Where= (isset($Trace['file']))
                ? $Trace['file']
                : "unknown file (anonymous func or call_user_func)";
            $Where .= (isset($Trace['line']))
                ? " ($Trace[line])"
                : " (unknown line number)";
            $Lines[]= array($Where, $What);
        }
        if (!$RenderTable) {
            return $Lines;
        }
        if (empty($Lines)) {
            return '';
        }
        $SkipDocRoot= isset($_SERVER['DOCUMENT_ROOT']) ? strlen($_SERVER['DOCUMENT_ROOT']) : 0;
        $Rows= array();
        foreach($Lines as $i=>$Line) {
            if ($SkipDocRoot) {
                $Line[0]= substr($Line[0], $SkipDocRoot);
            }
            $Rows[]= "<tr><th>#$i</th><td>&nbsp;$Line[0]&nbsp;</td><td>&nbsp;$Line[1]&nbsp;</td></tr>";
        }
        $Heading= '<tr><th colspan="3" style="text-align:left">&nbsp; ShowStack</th></tr>'
            .'<tr><th style="width:3em"></th><th>Location</th><th>Statement</th></tr>';
        return '<table border="1" style="background-color:#004;color:#dff;border-collapse:collapse;font:12px/18px sans-serif;">'
            .$Heading.implode('',$Rows).'</table>';
    }


    /**
     * Display all "call stack" points as simplified list.
     * This method can be called statically and dynamically.
     *
	 * @param array $RemovePrefixes
	 * @param int $SkipSteps
     * @return string
     */
    public static function ShowSimplifiedStack($RemovePrefixes=array(), $SkipSteps=0) {

        $btOptions= defined('DEBUG_BACKTRACE_IGNORE_ARGS') ? DEBUG_BACKTRACE_IGNORE_ARGS : false;
        $CallStack= debug_backtrace($btOptions);
        $CallStack= array_slice($CallStack, $SkipSteps);
        $Lines= array();
        foreach(array_reverse($CallStack) as $k=>$v) {
            if (isset($v['file'])) {
                $Where= $v['file'];
                foreach($RemovePrefixes as $Prefix) {
                    $Len= strlen($Prefix);
                    if (substr($Where, 0, $Len) === $Prefix) {
                        $Where= ltrim(substr($Where, $Len), '\\/ ');
                    }
                }
                $Where .= ": $v[line]";
                if (isset($v['function'])) {
                    $Where .= '  "'.$v['function'].'()"';
                }
            } else if (isset($v['class']) && $v['type'] === '->') {
                $Where= 'anonymous call to: '.substr($v['class'], strrpos($v['class'], '\\') + 1).'->'.$v['function'].'()';
            } else {
                $Where= "unknown file";
            }
            $Lines[]= '#'.($k+1).': '.$Where;
        }
        return implode("\n",$Lines);
    }


    /**
     * Display call stack in single text line.
     *
     * @param string $Separator  glue between stack points
	 * @param int $SkipSteps  number of final stack steps to skip
     * @return string
     */
    public static function ShowShortStack($Separator=' &rarr; ', $SkipSteps=0) {

        $btOptions= defined('DEBUG_BACKTRACE_IGNORE_ARGS') ? DEBUG_BACKTRACE_IGNORE_ARGS : false;
        $CallStack= debug_backtrace($btOptions);
		$CallStack= array_slice($CallStack, $SkipSteps);
        $ShortStackTrace= '';
        foreach($CallStack as $i=>$Trace) {
            $Where= (isset($Trace['file'])) ? basename($Trace['file']) : "unknown file";
            $Where .= (isset($Trace['line'])) ? "[$Trace[line]]" : "[?]";
            $ShortStackTrace= $Where.($i > 0 ? $Separator : '').$ShortStackTrace;
        }
        return $ShortStackTrace;
    }



//---------------------------------------------------------------------------
//
//                Profiler functions
//
//---------------------------------------------------------------------------

    /**
     * Inities logging system.
     *
     * @param string|bool $LogFile  full path to output logging file ('.php' will be append) or true to store logs in memory
	 * @param string $Caption  heading of document
     */
    public function ProfilerStart($LogFile, $Caption='DEBUG LOG') {

        $Cols= array(
            array(' Elapsed|from start', STR_PAD_LEFT),
            array(' Elapsed|from prev.', STR_PAD_LEFT),
            array('  Memory  |   usage  ', STR_PAD_LEFT),
            array(str_pad('| Description', 80, ' '), STR_PAD_RIGHT),
        );
        $this->ProfilerFile= $LogFile;
        $this->Profiler= new TLogger($LogFile, $Caption, $Cols, $this->IsAuthorizedSession(), true);
    }


    /**
     * Store new entry into log.
     *
     * @param string $Message
     */
    public function Mark($Message, $Timestamp=0, $Memory=0) {

        if (!$this->IsAuthorizedSession() || !$this->ProfilerFile || !$this->Profiler) {
            return;
        }
        if (!$Timestamp) {
            $Timestamp= microtime(true);
        }
        if (!$Memory) {
            $Memory= $this->GetMemory();
        }
        if (!$this->ProfilerStartTime) {
            $this->ProfilerStartTime= $this->ProfilerPrevTime= $Timestamp;
        }
        $Data= array($this->FormatSeconds($Timestamp - $this->ProfilerStartTime),
                    $this->FormatSeconds($Timestamp - $this->ProfilerPrevTime),
                    $this->FormatMemory($Memory),
                    $Message);
        $this->Profiler->Log($Data);
        $this->ProfilerPrevTime= $Timestamp;
    }


    /**
     * Return log entries.
     *
     * @param bool $FromFile  return content of log file instead of internal log buffer
     * @return mixed
     */
    public function GetProfilerData($FromFile=true) {

        return $this->Profiler->GetData($FromFile);
    }


    protected function GetMemory() {

        return function_exists('memory_get_usage') ? memory_get_usage(true) : 0;
    }

    protected function FormatSeconds($Timestamp) {

        $micro= intval(100000*($Timestamp - floor($Timestamp)));
    	$Text= intval($Timestamp).'::'.str_pad($micro, 5, '0', STR_PAD_LEFT);
        return $Text;
    }

    protected function FormatMemory($Amount) {

        return $Amount==0 ? '-' : $this->FormatBytes($Amount, 1);
    }



//---------------------------------------------------------------------------
//
//                VarDump functions
//
//---------------------------------------------------------------------------


    /**
     * Returns user-friendly presentation of supplied variable.
     *
     * @param mixed $VarValue  input variable
     * @return string
     */
    public function VarDump($VarValue, $Expand=2, $AsHTML=true) {

        $this->VarDumpHashes= array();

        // ensure integer value for expanding level
        if ($Expand === false) {
            $Expand= 0;
        } else if ($Expand === true) {
            $Expand= 99999;
        }

        // start recursive rendering
        $Dump= $this->VarDumpProcessor($VarValue, 0, $Expand, $AsHTML);

        // result
        return $AsHTML
            ? "\n".$this->VarDumpStyles()."\n"
                .'<div class="AccDbgVD">'.$Dump.'</div>'."\n"
            : $Dump;
    }


    public function VarDumpStyles() {

        return '<style type="text/css">'
                .'.AccDbgVD {background:#111;color:#fff;padding:3px;margin:0;font:normal 13px sans-serif;}'
                .'.AccDbgVD ul {display:none;padding:2px 2px 2px 16px;margin:0;background:#111;color:#fff;border-left:1px dashed #666;}'
                .'.AccDbgVD ul li {display:block; line-height:1.1em; margin:0; padding:3px 0 3px 0;}'
                .'.AccDbgVD abbr {color:#8cf;font-weight:bold;cursor:pointer;}'
                .'.AccDbgVD b {color:#ca8;font-weight:bold;}'
                .'.AccDbgVDO {color:#866 !important}'
                .'.AccDbgVDO + ul {display:block}'
                .'</style>';
    }


    protected function VarDumpProcessor($VarValue, $Level, $Expand, $AsHTML) {

        $Type= gettype($VarValue);
        $VarType= $Type;
        $VarDump= '';
        $ExpandButton= $AsHTML
            ? '<abbr '.($Level < $Expand ? 'class="AccDbgVDO" ' : '').'onclick="this.className=this.className===\'AccDbgVDO\'?\'\':\'AccDbgVDO\'">[...]</abbr>'
            : '';//'['.($Level < $Expand ? '-' : '+').']';
        $MsgTooDeep= sprintf($AsHTML ? "<ul><li>%s</li></ul>" : "\n%s", '- too deep nesting level -');
        $KeyValueFormat= $AsHTML ? "<li>%s =&gt; %s</li>" : "\n%s => %s";
        switch ($Type) {
            case 'array':
                $VarType= 'array('.count($VarValue).')';
                $VarDump= empty($VarValue) ? '' : ' '.$ExpandButton;
                if ($Level >= $this->MaxVarDumpNesting) {
                    $VarDump .= empty($VarValue) ? '' : $MsgTooDeep;
                    break;
                }
                $List= array();
                foreach($VarValue as $k=>$v) {
                    $List[]= sprintf($KeyValueFormat,
                        $AsHTML ? htmlentities($k) : $k,
                        $this->VarDumpProcessor($v, $Level+1, $Expand, $AsHTML));
                }
                $VarDump .= sprintf($AsHTML ? '<ul>%s</ul>' : '%s', implode('',$List));
                break;
            case 'boolean':
                $VarDump= $VarValue ? 'TRUE' : 'FALSE';
                break;
            case 'double':
                $VarType= 'float';
                $VarDump= sprintf("%0.15g",$VarValue);
                break;
            case 'integer':
                $VarDump= $VarValue.($VarValue >= 1000 ? ' &nbsp; <b><small>"'.number_format($VarValue).'"</small></b>' : '');
                break;
            case 'NULL':
                //$VarType= 'NULL';
                break;
            case 'object':
                $VarType= 'object('.get_class($VarValue).')';
                $VarDump= ' '.$ExpandButton;
                if ($Level >= $this->MaxVarDumpNesting) {
                    $VarDump .= $MsgTooDeep;
                    break;
                }
                if (!$this->VarDumpCheckLoop(spl_object_hash($VarValue))) {
                    $VarDump .=  '- LOOP DETECTED -';
                    break;
                }
                $Cast= (array)$VarValue;
                $List= array();
                foreach($Cast as $k=>$v) {
                    if (substr($k,0,3) === "\x00\x2A\x00") { // remove marker 'protected'
                        $k= substr($k,3);
                    }
                    if ($k[0] === "\x00") { // skip 'private' properties
                        continue;
                    }
                    $List[]= sprintf($KeyValueFormat,
                        $AsHTML ? htmlentities($k) : $k,
                        $this->VarDumpProcessor($v, $Level+1, $Expand, $AsHTML));
                }
                $VarDump .= sprintf($AsHTML ? '<ul>%s</ul>' : '%s', implode('',$List));
                break;
            case 'resource':
                $ResourceType= get_resource_type($VarValue);
                $VarType= 'resource('.$ResourceType.')';
                if ($ResourceType === 'stream') {
                    $VarDump= ' '.$ExpandButton;
                    $List= array();
                    foreach(stream_get_meta_data($VarValue) as $k=>$v) {
                        $List[]= sprintf($KeyValueFormat,
                            $AsHTML ? htmlentities($k) : $k,
                            $this->VarDumpProcessor($v, $Level+1, $Expand, $AsHTML));
                    }
                    $VarDump .= sprintf($AsHTML ? '<ul>%s</ul>' : '%s', implode('',$List));
                } else {
                    // 'curl' => curl_getinfo(), 'xpath context', 'xpath object', 'ftp', 'socket'
                }
                break;
            case 'string':
                $VarType= 'string('.strlen($VarValue).')';
                $VarDump= '"'.($AsHTML ? htmlentities($VarValue) : $VarValue).'"';
                break;
            default:
                $VarType= 'unknown('.$Type.')';
                break;
        }
        return $AsHTML
            ? '<b>'.$VarType.'</b> '.$VarDump
            : str_replace("\n", "\n".str_repeat(' ',$Level*4), $VarType.' '.$VarDump);
    }


    protected function VarDumpCheckLoop($Hash) {

        if (isset($this->VarDumpHashes[$Hash])) {
            return false; // loop detected
        }
        $this->VarDumpHashes[$Hash]= true;
        return true; // it is safe to go further
    }




//---------------------------------------------------------------------------
//
//                Misc functions
//
//---------------------------------------------------------------------------


    /**
     * Returns list of all files which are loaded with 'include' or 'require'.
     *
     * @return array  sorted list with paths and file sizes
     */
    public function GetIncludedFiles() {

        $Total= 0;
        $List= array();
        foreach(get_included_files() as $Path) {
            $Size= @filesize($Path);
            $Total += $Size;
            $List[]= array(
                'Size'=> $Size,
                'FSize'=> $this->FormatBytes($Size),
                'Path'=> $Path,
            );
        }
        rsort($List); // sort by size, put largest file on top
        return $List;
    }


    /**
     * Shows big global variables (larger then 1kb) and their memory occupation.
     * Because this process destroys almost all defined variables it terminates
     * application execution.
     * This method can be called statically and dynamically.
     */
    public static function VarSizesTopList() {
        // debug tool
        $keys= array_keys($GLOBALS);
        $Out= array();
        for($x= count($keys)-1; $x>=0; $x--) {
            $k= $keys[$x];
            if ($k == 'GLOBALS') {
                continue;
            }
            $before= memory_get_usage(true);
            unset($GLOBALS[$k]);
            gc_collect_cycles();
            $delta= $before - memory_get_usage(true);
            if ($delta >= 1024) $Out[$k]= $delta;
        }
        arsort($Out);
        echo '<h1>VarSizesTopList report:</h1><table border="1" cellpadding="3">';
        if (empty($Out)) {
            echo '<tr><td>No variables larger then 1kb found.</td></tr>';
        } else {
            foreach($Out as $k=>$v) {
                echo '<tr><td>$'.$k.'</td><td align="right">'.number_format($v).'</td></tr>';
            }
        }
        echo '</table>';
        die(); // terminate because most variables are destroyed
    }




//---------------------------------------------------------------------------
//
//                Helper functions
//
//---------------------------------------------------------------------------

    /**
     * convert 10240 into 10 k, and 10000 into 9,8 k - usefull for presentation
     */
    protected function FormatBytes($Amount, $Decimals=0) {

        if ($Amount == 0) {
            return '0 b';
        }
        $Sufix= array('', 'k', 'M', 'G', 'T');
        $Loop= 0;
        while((($Amount / 1024) >= 1) and ($Loop < 5)) {
            $Loop++;
            $Amount= $Amount / 1024;
        }
        if ($Amount < 10) {
            $Decimals++;
        }
        return number_format($Amount, $Decimals)." $Sufix[$Loop]b";
    }

}

