<?php namespace Accent\AccentCore\Debug;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * CrashReporter will send/store usefull informations on FatalError occurances.
 *
 * It is split in two main blocks: "collector" and "writer".
 * During first stage collector will collect all informations that need to be sent to admins,
 * and on second stage writer perform actaul sending to specified devices or personal.
 *
 * Collector will gather following informations by itself:
 *   - file, line and message with cause of fatal error
 *   - contents of $_SERVER, $_POST, $_COOKIE, $_ENV
 *   - timestamp
 *
 * Additionally collector will call user defined callback to let application chance
 * to provide more usefull informations.
 * Typically via callback application returns:
 *   - id of logged user
 *   - tags (for search)
 *   - sender (name of site/application, usefull for search in centralized message storage)
 *   - version of application (usefull for search in centralized message storage)
 *
 * Writer has following built-in means for sending informations:
 *  - log file - will store report by appending "log" file in local filesystem
 *  - URL - will send (POST HTTP) report to remote web site/service as JSON packed text
 *  - email - will send report to email address(es)
 *  - callable - will forward collected informations to supplied callback function
 *
 * Writing to database requires too many parameters (dsn, username, password, table, columns)
 * and cannot be specified with simple string so "callable" writer must be use instead.
 *
 * Multiple writers can be specified, as array in constructor options,
 * to perform sending to email and log file for example.
 * Also, there is method AddWriter to allow late specification,
 * that is a good way to workaround situation when admin's email address or file location
 * are not known in moment of creating CrashReporter.
 *
 * Seems that there is no way to capture debug_backtrace data during fatal error event.
 *
 * TODO: implement some kind of throttler to prevent email flood, currently I have no
 * good solution where to store timestamp of last sending and preserve portability.
 *
 * Usage example:
 *   $CR= new CrashReporter(
 *       'Writer'=> 'Email:admin@site.com',
 *       'Callback'=> array($this, 'MyCrashReporterCallback'),
 *   );
 */


use Accent\AccentCore\Component;
use Accent\AccentCore\Debug\Debug;
use Accent\AccentCore\File\File;


class CrashReporter extends Component {


    protected static $DefaultOptions= array(

        // initial state of component
        'Enabled'=> true,

        // single writer as string or array of writers, defined as:
        //  - "LogFile:FullPathToFileLocation"
        //  - "Email:SimpleEmailAddress" multiple addresses can be specified as comma-separated
        //  - "URL:www.somewebsite.com", prefix "http://" will be prepend if not specified
        //  - "Callable:Class:Method" or array('Class','Method') or Closure
        'Writer'=> null,

        // user defined callback as provider of additional informations
        'Callback'=> null,

        // version of component
        'Version'=> '0.2',
    );

    // internal properties
    protected $Writers;
    protected $Debug;
    protected $Enabled;


    /**
     * Constructor.
     */
    public function __construct($Options) {

        // call ancestor
        parent::__construct($Options);

        // export 'Writer' to local property
        $this->Writers= $this->GetOption('Writer');

        // ensure array type, also push callable array deeper
        if (!is_array($this->Writers) || is_callable($this->Writers)) {
            $this->Writers= array($this->Writers);
        }

        // export 'Enabled' to local property
        $this->Enabled= $this->GetOption('Enabled');

        // attach 'shutdown' listener to PHP's engine
        // note that listener will work only if property 'Enabled' is set
        register_shutdown_function(array($this, 'InternalShutDownHandler'));
    }


    /**
     * Enable CrashReporter.
     */
    public function Enable() {

        $this->Enabled= true;
    }


    /**
     * Disable CrashReporter.
     */
    public function Disable() {

        $this->Enabled= false;
    }


    /**
     * Append additional writer to collection of writer.
     * Callable defined as array will receive single parameter $Data.
     * Callable defined as string will receive 'remanding part after ":"' and $Data
     *
     * @param callable $Writter
     */
    public function AddWriter($Writter) {

        $this->Writers[]= $Writter;
    }


    /**
     * Shutdown handler. Do not call this method directly.
     */
    public function InternalShutDownHandler() {

        // check is it enabled
        if (!$this->Enabled) {
            return;
        }

        // check is it FatalError happen
        $LastError= error_get_last();
        if ($LastError === null || !in_array($LastError['type'], array(E_ERROR,E_CORE_ERROR,E_COMPILE_ERROR))) {
            return;
        }

        // call handler
        $this->Handle($LastError);
    }


    /**
     * Method for simulating fatal-error-event, for debugging purposes.
     */
    public function Trigger() {

        $Error= array(
            'message'=> 'Manually triggered CrashReporter handler.',
            'file'=> __FILE__,
            'line'=> __LINE__,
        );
        $this->Handle($Error);
    }


    /**
     * Handler of fatal error event.
     *
     * @param array $Error  report from error_get_last()
     */
    protected function Handle($Error) {

        // allow later methods to use utilities from Debug component
        $this->Debug= new Debug();

        // collect informations
        $Data= $this->Collect($Error);

        // dispach informations
        $this->Write($Data);
    }


    /**
     * Collector of informations that will be sant to writers.
     *
     * @return array
     */
    protected function Collect($Error) {

        return $this->GetData($Error)
             + $this->GetFromCallback();
    }


    /**
     * Get informations that can be retrieved automaticaly.
     *
     * @param array $Error   report from error_get_last()
     * @return array
     */
    protected function GetData($Error) {

        $Context= $this->GetRequestContext();
        return array(
            'FatalError'=> "\n  $Error[message]\n  in file: $Error[file]\n  on line: $Error[line]",
            'Timestamp' => "\n  ".date('r'),
            //'CallStack' => $this->RenderCallStack(),
            '$_SERVER'  => $this->VarDump($this->GetServerPool()),
            '$_POST'    => $this->VarDump($Context->POST),
            '$_COOKIE'  => $this->VarDump($Context->COOKIE),
            '$_ENV'     => $this->VarDump($Context->ENV),
        );
    }


    /**
     * Get informations provided by developer's callback listener.
     *
     * @return array
     */
    protected function GetFromCallback() {

        // get user defined callback function
        $Callback= $this->GetOption('Callback');

        // is specified?
        if (!is_callable($Callback)) {
            return array();
        }

        // call user-defined function
        $Result= $Callback();

        // return array
        return is_array($Result)
            ? $Result
            : array('ERROR'=>'Callback did not return array value!');
    }


    /**
     * Main writing method will delegate actual writing to configured writers.
     *
     * @param array $Data  collection of debuging informations
     */
    protected function Write($Data) {

        // run all writers
        foreach ($this->Writers as $Writer) {

            // skip obviusly invalid items
            if (!$Writer) {
                continue;
            }

            // is it simple string, like "method:parameters" ?
            if (is_string($Writer)) {
                $Parts= explode(':', $Writer, 2); // example: "LogFile:/var/log/fatal-log.php"
                $Method= 'WriteTo'.ucfirst($Parts[0]);
                $this->$Method($Parts[1], $Data);
            }

            // is it callback (array or closure) ?
            if (is_callable($Writer)) {
                $Writer($Data);
            }
        }
    }



    //-----------------------------------------------------------------
    //
    //                        Writing methods
    //
    //-----------------------------------------------------------------

    /**
     * Writer "LogFile" will store informations in flat text file.
     *
     * @param string $Para  full path to log file
     * @param array $Data
     */
    protected function WriteToLogFile($Para, $Data) {

        // prepare file
        $LogFile= $Para;
        @mkdir(dirname($LogFile), true);
        if (!is_file($LogFile)) {
            file_put_contents($LogFile, "<?php __halt_compiler();  // hide rest of file...");
        }

        // prepare content
        $Dump= "\n\n=====================================";
        $Dump .= $this->RenderPlainData($Data);

        // append dump to file
        file_put_contents($LogFile, $Dump, FILE_APPEND);
    }


    /**
     * Writer "Email" will send informations via email.
     *
     * @param string $Para  string packed as "To:From:Subject"
     * @param array $Data
     */
    protected function WriteToEmail($Para, $Data) {

        // separate config parameter by ":"
        $Parts= explode(':', $Para);

        // prepare "To" field of messsage
        // multiple addresses can be specified as comma-separated string
        $To= str_replace(array("\r","\n","\t"), '', $Parts[0]);

        // prepare "From" field of message
        $FromUnsafe= isset($Parts[1])
            ? $Parts[1]
            : (string)ini_get('sendmail_from');
        if ($FromUnsafe === '' || strpos($FromUnsafe, '@') === false) {
            $Host= $this->GetRequestContext()->SERVER['HTTP_HOST'];
            $FromUnsafe= 'crashreporter@'.$Host;
        }
        $From= str_replace(array("\r","\n","\t"), '', $FromUnsafe);

        // subject of message
        $Subject= isset($Parts[2])
            ? str_replace(array("\r","\n","\t"), '', $Parts[2])
            : 'CrashReport';

        // body of message
        $Body= $this->RenderPlainData($Data);

        // few headers
        $Headers= "From: $From\r\nReply-To: $From\r\nReturn-Path: $From";

        // launch mail
        mail($To, $Subject, $Body, $Headers);
    }



    /**
     * Writer "URL" will send informations to remote host using socket.
     *
     * @param string $Para  target URL address
     * @param array $Data
     */
    protected function WriteToURL($Para, $Data) {

        // use utilities from File component
        $File= new File();

        $File->LoadWithSocket($Para, array(
            'Method'=> 'POST',
            'PostPara'=> array('CrashData'=>json_encode($Data, JSON_UNESCAPED_UNICODE)),
        ));
    }


    /**
     * Writer "Callable" will send array of informations to callback function defined
     * as "StaticClass:Method" or simple "MyFunction".
     *
     * @param string $Para  "StaticClass
     * @param array $Data
     */
     protected function WriteToCallable($Para, $Data) {

         // does $Para consist of two parts or only one?
         $CallableParts= explode(':', $Para);
         if (count($CallableParts) === 1) {
             $CallableParts= $Para;
         }

         // execute user defined callback and send array as single parameter
         call_user_func($CallableParts, $Data);
     }




    //-----------------------------------------------------------------
    //
    //                       Helper methods
    //
    //-----------------------------------------------------------------

    /**
     * Helper, pack array to nice formatted text.
     */
    protected function RenderPlainData($Data) {

        $Dump= '';
        foreach ($Data as $k => $v) {
            // put name of section in "[", "]" and prepend it with double new-line
            $Dump .= "\n\n[$k] ";
            // stringify value of section
            if (!is_string($v)) {
                $v= $this->VarDump($v);
            }
            $Dump .= $v;
        }
        return $Dump;
    }


    /**
     * Helper, preparing callstack informations.
     * Unfortunatelly it seems that PHP within shutdown event cannot access debug-backtrace
     * so this method remains unused.
     */
    protected function RenderCallStack() {

        $CallStack= debug_backtrace();
        $Lines= array();
        foreach($CallStack as $k=>$v) {
            $Where= isset($v['file']) ? $v['file'] : "unknown file";
            $Where .= isset($v['line']) ? ":$v[line]" : " (unknown line number)";
            $Args= '';
            foreach (isset($v['args']) ? $v['args'] : array() as $ArgKey => $ArgVal) {
                $Args.= "\n        ".($ArgKey+1).': '.$this->VarDump($ArgVal, 3);
            }
            if ($Args !== '') {
                $Args= ",   arguments:".$Args;
            }
            $Lines[]= '#'.($k+1).': '.$Where.$Args;
        }
        return "\n  ".implode("\n  ",$Lines);
    }


    /**
     * Helper, rendering value of variable.
     */
    protected function VarDump($Var, $Indent=1) {

        $Dump= trim($this->Debug->VarDump($Var, 3, false));
        return str_replace("\n", "\n".str_repeat(' ',$Indent*4), $Dump);
    }


    /**
     * Helper, returning $_SERVER array without few big and unneccesary items.
     */
    protected function GetServerPool() {

        $SERVER= $this->GetRequestContext()->SERVER;
        $Remove= array('PATH', 'SERVER_SIGNATURE', 'REQUEST_TIME_FLOAT', 'REQUEST_TIME');
        return array_diff_key($SERVER, array_flip($Remove));
    }

}

?>