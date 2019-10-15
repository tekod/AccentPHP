<?php namespace Accent\AccentCore\Debug;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Set of methods for various debuging tasks:
 *   - measuring time of execution (benchmark, profiling)
 *   - getting tracing data to particlar execution point (call stack)
 *   - generating application execution log
 *   - displaying arbitrary variable content
 *   - getting list and sizes of currently included PHP files
 *
 * This class is self-contained in order to achieve isolation of running
 * application and current context of its execution.
 * That also allows to use methods at very early stages of application workflow.
 * Becouse of that it does NOT depend on any other classes from core package,
 * not even Component class.
 *
 * Example of using staticaly:
 *     use \Accent\AccentCore\Debug\Debug;
 *     Debug::Instance()->LoggerStart(...);
 *     Debug::Instance()->Log(...);
 *     Debug::Instance('MyAnotherInstance')->LoggerStart(...);
 * Example of calling dynamicaly:
 *     $Debug= new \Accent\AccentCore\Debug\Debug;
 *     $Debug->LoggerStart(...);
 *     $Debug->Log(...);
 * Note that only in dynamic invoking user can pass parameters in constructor.
 *
 * This class is not descendant of Component class in order to be operative as early as possible
 * and be independent and isolated from other systems.
 * That's why it directly access to superglobals.
 */


class Debug {



    protected $DebugId= false;  // set to true/false to implicitly enable logging
                                // and profiling functions or integer to enable
                                // for users with cookie containing that integer
    protected $CookieId= 0;

    protected $ProfilerFile= false;// as string to specify path of log file
                                // as boolean true to keep logs in memory
    protected $Profiler;
    protected $ProfilerStartTime;
    protected $ProfilerPrevTime;

    protected $MaxVarDumpNesting= 5;    // maximum loop level for VarDump method
    protected $VarDumpHashes= array();  // internal buffer for detecting recursions

    protected static $Instances= array();   // storage for staticaly built instances


    /**
     * Constructor.
     *
     * @param bool|integer $DebugId  see description for $this->DebugId
     */
    public function __construct($Options=array()) {

        // set permition of ID of desired user
        $this->DebugId= isset($Options['DebugId'])
            ? $Options['DebugId']
            : true; // allow to all by default
        // if DebugId is integer allow user to set cookie value via GET
        if (intval($this->DebugId) > 0 && isset($_GET['AccentDebugId'])) {
            setcookie('AccentDebugId', intval($_GET['AccentDebugId']));
            // make it valid for current request
            $_COOKIE['AccentDebugId']= intval($_GET['AccentDebugId']);
        }
        // get ID of current user
        $this->CookieId= isset($_COOKIE['AccentDebugId'])
            ? intval($_COOKIE['AccentDebugId'])
            : false;
    }


    /**
     * Factory (singleton) method for invoking class staticaly.
     *
     * @param string $InstanceName  allows to call specified instacne by its name
     * @return object
     */
    public static function Instance($InstanceName='Primary') {

        if (!isset(static::$Instances[$InstanceName])) {
            static::$Instances[$InstanceName]= new static;
        }
        return static::$Instances[$InstanceName];
    }


    /*
     * Is current visitor authenticated as developer?
     * This will allow writing log files on disk.
     */
    public function AuthorizedSession() {

        if (is_bool($this->DebugId)) {
            return $this->DebugId;
        }
        return $this->CookieId === $this->DebugId;
    }



//---------------------------------------------------------------------------
//
//                Call-stack functions
//
//---------------------------------------------------------------------------


    // display specified stack point in format: "/../include/index.php on line (146)"
    public static function ShowStackPoint($n, $ShowFullPath=true) {

    	$CallStack = debug_backtrace();
    	if (!isset($CallStack[$n]))
    		return "sorry, stack point '$n' not exist.";
    	$res= '';
    	$Line= $CallStack[$n];
    	$res .= (isset($Line['file']))
    		? (($ShowFullPath) ? $Line['file'] : basename($Line['file'])).' '
    		: "unknown file (anonymous func or call_user_func) ";
    	$res .= (isset($Line['line']))
    		? "on line ($Line[line])"
    		: "unknown line number";
    	return ($res)
    		? $res
    		: "sorry, no informations about point '$n'.";
    }


    /**
     * Display all "call stack" points.
     * This method can be called staticaly and dynamicaly.
     *
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
     * Display all "call stack" points as simplifed list.
     * This method can be called staticaly and dynamicaly.
     *
     * @return string
     */
    public static function ShowSimplifiedStack($RemovePrefixes=array()) {

        $btOptions= defined('DEBUG_BACKTRACE_IGNORE_ARGS') ? DEBUG_BACKTRACE_IGNORE_ARGS : false;
        $CallStack = debug_backtrace($btOptions);
        $Lines= array();
        foreach($CallStack as $k=>$v) {
            if ($k < 3) continue;
            if (isset($v['file'])) {
                $Where= $v['file'];
                foreach($RemovePrefixes as $Prefix) {
                    $Len= strlen($Prefix);
                    if (substr($Where, 0, $Len) === $Prefix) {
                        $Where= ltrim(substr($Where, $Len), '\\/ ');
                    }
                }
                $Where .= ":$v[line]";
                if (isset($v['function'])) {
                    $Where .= ' "'.$v['function'].'()"';
                }
            } else if (isset($v['class']) && $v['type'] === '->') {
                $Where= 'anonymous call to: '.substr($v['class'], strrpos($v['class'], '\\') + 1).'->'.$v['function'].'()';
            } else {
                $Where= "unknown file";
            }
            $Lines[]= '#'.($k-2).':'.$Where;
        }
        return implode("\n",$Lines);
    }


    /**
     * Inities logging system.
     *
     * @param string|bool $LogFile  full path to output logging file, '.php' will be appended or true to sore logs in memory
     */
    public function ProfilerStart($LogFile, $Caption='DEBUG LOG') {

        $Cols= array(
            array(' Elapsed|from start', STR_PAD_LEFT),
            array(' Elapsed|from prev.', STR_PAD_LEFT),
            array('  Memory  |   usage  ', STR_PAD_LEFT),
            array(str_pad('| Description', 80, ' '), STR_PAD_RIGHT),
        );
        $this->ProfilerFile= $LogFile;
        $this->Profiler= new TLogger($LogFile, $Caption, $Cols, $this->AuthorizedSession(), true);
    }


    /**
     * Store new entry into log.
     *
     * @param string $Message
     */
    public function Mark($Message, $Timestamp=0, $Memory=0) {

        if (!$this->AuthorizedSession() || !$this->ProfilerFile || !$this->Profiler) {
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
            ? '<style>'
                .'.AccDbgVD {background:#111;color:#fff;padding:3px;margin:0;font:normal 13px sans-serif;}'
                .'.AccDbgVD ul {display:none;padding:2px 2px 2px 16px;margin:0;background:#111;color:#fff;border-left:1px dashed #666;}'
                .'.AccDbgVD ul li {display:block;}'
                .'.AccDbgVD abbr {color:#8cf;font-weight:bold;cursor:pointer;}'
                .'.AccDbgVD b {color:#ca8;font-weight:bold;}'
                .'.AccDbgVDO {color:#866 !important}'
                .'.AccDbgVDO + ul {display:block}'
                .'</style>'
                .'<div class="AccDbgVD">'.$Dump.'</div>'
            : $Dump;
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
                    if (substr($k,0,3) ==="\x00\x2A\x00") { // remove marker 'protected'
                        $k= substr($k,3);
                    }
                    if ($k{0}==="\x00") { // remove marker 'private'
                        $k= substr($k, 1);
                        $List[]= sprintf($KeyValueFormat,
                            $AsHTML ? htmlentities($k) : $k,
                            $AsHTML ? '<b>&lt;private&gt;</b>' : '<private>');
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
     * @return array  sorted list with paths and filesizes
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
     * Shows big global variables (larger then 1kb) and their memory ocupation.
     * Because this process destroys almost all defined variables it terminates
     * application execution.
     * This method can be called statically and dynamically.
     */
    public static function VarSizesTopList() {
        // debug tool
        $keys= array_keys($GLOBALS);
        $Out= array();
        for($x=count($keys)-1; $x>=0; $x--) {
            $k= $keys[$x];
            if ($k == 'GLOBALS') continue;
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

?>