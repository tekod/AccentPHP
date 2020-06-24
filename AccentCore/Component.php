<?php namespace Accent\AccentCore;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Base class for almost all Accent components.
 *
 * Provide following features:
 *  - full customization through constructor's parameters
 *  - controllable error dispatching
 *  - controllable access to services
 *  - dynamic resolving of system paths
 *  - dynamic adding methods thru plugins
 */


use Accent\AccentCore\ServiceManager\LazyLoader;
use Accent\AccentCore\RequestContext;
use Accent\AccentCore\Event\BaseEvent;


abstract class Component {


    // Basic constructor options for all components, avoid to modify this in descendant classes.
    // Existence of fields in this array are mandatory for all subclasses.
    // Subclasses should add theirs specific options in $DefaultOptions array
    // This options will be overwritten by $DefaultOptions property and $Options in constructor.
    protected static $DefaultOptions= array(

        // list of system paths, will be used by ResolvePath()
        'Paths'=> array(),

        // callable for invoking Error()
        'ErrorFunc'=> null,

        // list of plugins configs (like array('Class'=>'\A\B\Class'))
        'Plugins'=> array(),

        // key=>value array translating local ServiceName to global ServiceName
        'Services'=> array(),

        // object of service manager
        'ServiceManager'=> null,

        // version of component
        'Version'=> '0.0.0',
    );

    // Resulting options set after object constructing.
    // Object should read options from this property.
    protected $Options= array();

    // This marker should be set during object construction, to inform caller
    // is object successfully prepared for work or not.
    protected $Initiated= null;

    // Class basename
    protected $ClassName;

    // File path of loaded component, do not read directly, use $this->GetClassFile()
    private $ClassFile;

    // Name of section for Debug/Tracer
    protected $TracerSection= '';

    // Plugins storage
    protected $Plugins= array(
        'Configs'=> array(),
        'Objects'=> array(),
    );

    /**
     * Constructor.
     * @param array $Options
     */
    public function __construct($Options=array()) {

        // get base class name
        $FQCN= get_class($this);
        $this->ClassName= substr($FQCN, strrpos($FQCN, '\\') + 1);

        // set $TracerSection if ommited
        if (!$this->TracerSection) {
            $this->TracerSection= $this->ClassName;
        }

        // combine options from all sources and store them in $this->Options
        $this->MergeDefaultOptions($Options);

        // register plugins
        foreach ($this->GetOption('Plugins') as $Conf) {
            $this->RegisterPlugin($Conf);
        }

        // confirm successfully creation
        // there is no dependencies (services,files,...) to check so it is always true
        // descendant constructors can change this value according to its own dependencies
        $this->Initiated= true;
    }


    /**
     * Combine $DefaultOptions from all parents of final class and options passed to
     * constructor, using smart appending method.
     * Result will be stored into $this->Options property.
     *
     * @param array $ConstructorOptions
     */
    private function MergeDefaultOptions($ConstructorOptions) {

        $Class= get_class($this);
        $Definitions= array();
        // add options from class constructor
        $Definitions[]= $ConstructorOptions;
        // add options from all ancestors
        do {
            if (property_exists($Class, 'DefaultOptions')) {
                $Definitions[]= $Class::$DefaultOptions;
            }
            $Class= get_parent_class($Class);
        } while ($Class !== __CLASS__);
        // loop end in this class, add local options as last
        $Definitions[]= self::$DefaultOptions;
        // reverse array and merge it into $Options
        $this->Options= $this->MergeArrays(array_reverse($Definitions));
    }


    /**
     * Report that object is successfully constructed.
	 *
     * @return boolean
     */
    public function IsInitiated() {

        return $this->Initiated;
    }


    /**
     * Return version of component/service.
     * If parameter is supplied method will compare it and return boolean true if
     * current version is above or equal to $MinimumVersion.
     *
     * @param string (optional)
     * @return string|bool
     */
    public function GetVersion($MinimumVersion=null) {

        $Version= $this->GetOption('Version');

        return $MinimumVersion === NULL
            ? $Version
            : version_compare($MinimumVersion, $Version) > 0;
    }


    /**
     * Return location of file where current class was found.
     *
     * @return string
     */
    public function GetClassFile() {

        if (!$this->ClassFile) {
            $Reflection= new \ReflectionClass($this);
            $this->ClassFile= $Reflection->getFileName();
        }
        return $this->ClassFile;
    }


    /**
     * Smarter variant of array_merge function, overwriting nodes only if necessary.
     * Arrays will be recursive append instead of simply overwritten.
     * Items from later array will overwrite items from previous.
     *
     * There is two ways to instruct deletion of items in array:
     *  - adding item element with key null and value null (deleting siblings),
     *  - prefixing key with "_" char (deleting children)
     *
     * @param array $ArrayOfArrays
     * @return array
     */
    protected function MergeArrays($ArrayOfArrays) {

        $Merged= array();
        while ($ArrayOfArrays) {
            $Array= array_shift($ArrayOfArrays); // extract first argument
            if (!is_array($Array)) {
                $Msg= get_class($this).'.MergeOptions: Not an array argument.';
                $this->Error($Msg);
                continue;
            }
            if (empty($Array)) {
                continue;   // empty array, skip
            }
            foreach ($Array as $Key => $Value) {
                if (($Key === null || $Key === '') && $Value === null) { // clear array
                    $Merged= array();
                } else if (is_string($Key)) {  // note that null key is converted into "" internaly by PHP
                    // string key
                    if ($Key !== '' && $Key[0] === '_') {
                        $TmpKey= substr($Key, 1);
                        if (isset($Merged[$TmpKey]) && is_array($Merged[$TmpKey])) { // clear array
                            $Key= $TmpKey;
                            $Merged[$Key]= array();
                        }
                    }
                    $Merged[$Key]= is_array($Value)
                        && array_key_exists($Key, $Merged)
                        && is_array($Merged[$Key])
                        ? $this->MergeArrays(array($Merged[$Key], $Value)) // recursion
                        : $Value;
                } else if (!isset($Merged[$Key])) {
                    // it is numeric key, use that key if available ...
                    $Merged[$Key]= $Value;
                } else {
                    // ... but if not just append value with next available numeric key
                    $Merged[]= $Value;
                }
            }
        }
        return $Merged;
    }


    /**
     * Handle error situation.
     * By default it will forward message to system's "trigger_error()" method,
     * but can be redirected if callable found in option "ErrorFunc".
     *
     * @param string $Message
     * @param int $Step
     * @param bool $FatalError
     */
    protected function Error($Message, $Step=1, $FatalError=false) {

        $Func= $this->Options['ErrorFunc'];
        if (is_callable($Func)) {
            $Func($Message, intval($Step), $FatalError);
        } else  {
            $Backtrace= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            if (isset($Backtrace[$Step]['file'])) {
                $F= basename($Backtrace[$Step]['file']);
                $N= intval($Backtrace[$Step]['line']);
                $Message .= " [$F ($N)]";
            }
            trigger_error($Message, $FatalError ? E_USER_ERROR : E_USER_WARNING);
        }
    }


    /**
     * Synonym for method "Error" with enabled FatalError parameter.
     *
     * @param string $Message
     * @param int $Step
     */
    protected function FatalError($Message, $Step=1) {

        $this->Error($Message, $Step+1, true);
    }


    /**
     * Returns path with resolved prefix
     *
     * @param string $Path
     * @return string
     */
    public function ResolvePath($Path) {

        $Parts= explode('/', str_replace('\\', '/', $Path));
        switch ($Parts[0]) {
            case '_':
            case '@AppDir':
                    // full path to 'protected' dir of app.
                    // (useful for loading application scripts)
                    $Parts[0]= $this->Options['Paths']['AppDir']; break;
            case '@':
            case '@AccentDir':
                    // full path to directory where Accent packages are stored
                    // (useful for including libraries)
                    $Parts[0]= $this->Options['Paths']['AccentDir']; break;
            case '~':
            case '@DomainDir':
                    // path relative to domain root, usually "/"
                    // (useful for prefixing JS, img,... in HTML)
                    $Parts[0]= $this->Options['Paths']['DomainDir']; break;
            case '*':
            case '@SiteDir':
                    // full path to site root (entry index.php)
                    // (useful for manipulation with public accessible files)
                    $Parts[0]= $this->Options['Paths']['SiteDir']; break;
            case '@ExtDir':
                    // full path to root of current extension
                    // resolvable only inside of an extension
                    $Parts[0]= $this->Options['Paths']['ExtDir']; break;
        }
        $Path= implode('/', $Parts);

        // resolve double slashes, "/../", "/./", but preserve "http://"
        $Ex= explode('://', $Path, 2);
        $Scheme= count($Ex) > 1 ? array_shift($Ex).'://' : '';
        $Path= end($Ex);
        $Path= str_replace(array('//','/./'), '/', $Path);
        do {
            $Path= preg_replace("~\/(?!\.\.)[^\/]+\/\.\.\/~", "/", $Path, -1, $Found);
        } while($Found > 0);
        return $Scheme.$Path;
    }


    /**
     * Convert relative path to absolute by resolving '..' and prepending root.
     *
     * @param string $InputPath
     * @return string
     */
    protected function GetAbsolutePath($InputPath) {

        // normalize directory separators
        $Path= str_replace('\\', '/', $InputPath);
        // DOS-like start: "c:/" or UNIX-like start: "/home/myname/site/...."
        if (substr($Path,1,2) === ':/' || substr($Path,0,1) === '/') {
            return $Path; // it is already absolute path
        }
        $SiteDirParts= explode('/', $this->Options['Paths']['SiteDir']);
        $Parts= explode('/', $Path);
        $CountOfRedirects= 0;
        while(reset($Parts) === '..') {
            $CountOfRedirects++;
            array_shift($Parts);
            array_pop($SiteDirParts);
        }
        return implode('/', array_merge($SiteDirParts, $Parts));
    }


    /**
     * Resolving specified path and returns array of two strings:
     *  - full filesystem path
     *  - path relative to root of domain (URL path), or false if not accessible
     * Supplied path must beginning with one of prefixes from ResolvePath().
     *
     * @param string $Path
     * @return array
     */
    //public function GetUrlOfFile($Path) {
    public function AbsolutizePath($Path) {

        // first step, absolutize to filesystem
        if (substr($Path, 0, 11) === '@DomainDir/') {
            $Path= '@SiteDir/'.$this->ResolvePath($Path);
        }
        $FilesystemPath= $this->ResolvePath($Path);

        // now find its URL path
        $Len= strlen($this->Options['Paths']['SiteDir']);
        $UrlPath= substr($FilesystemPath, 0, $Len) === $this->Options['Paths']['SiteDir']
            ? str_replace('\\', '/', substr($FilesystemPath, $Len))
            : false;
        return array($FilesystemPath, $UrlPath);
    }


    /**
     * Convert some user-friendly ways to specify callable to PHP's standard forms:
     *  - "MyClass::MyMethod" into array("MyClass", "MyMethod")
     *  - "@::MyMethod" into array($this-class-name, "MyMethod")
     *  - "@/MyMethod" into array($this, "MyMethod")
     *  - "@Debug" into object of registered service "Debug"
     *  - "@Debug:Mark" into array(object "Debug", "Mark")
     *
     * @param mixed $Callable  source as string,array,object
     * @param object $Parent  parameter to be used as $this in array result
     * @return mixed  valid callable
     */
    public function ResolveCallable($Callable, $Parent=null) {

        if ($Parent === null) {
            $Parent= $this;
        }
        // analyze only string value, skip others (array, object, closure)
        if (is_string($Callable)) {
            // resolve prefix '@::' which means 'local method', statically called
            if (substr($Callable, 0, 3) === '@::') {
                $Callable= array(get_class($Parent), substr($Callable, 3));
            }
            // resolve prefix '@/' which means 'local method', called as $this->...
            else if (substr($Callable, 0, 2) === '@/') {
                $Callable= array($Parent, substr($Callable, 2));
            }
            // resolve prefix '@' which referencing to a service
            else if ($Callable[0] === '@') {
                // resolve referencing method too, like "@Sanitizer:Sanitize"
                $Parts= explode(':', substr($Callable, 1));
                $Callable= isset($Parts[1])
                    ? array($this->GetService($Parts[0]), $Parts[1])
                    : $this->GetService($Parts[0]);
            }
            // resolve arbitr. static call like "Namespace\\SomeClass::Method"
            else if (strpos($Callable, '::') !== false) {
                $Callable= explode('::', $Callable);
            }
        }
        // resolve referencing a service with method
        if (is_array($Callable) && is_string($Callable[0]) && $Callable[0][0] === '@') {
            $Callable[0]= $this->GetService(substr($Callable[0], 1));
        }
        // return result
        return $Callable;
    }


    /**
     * Fetch constructor's option.
     * Use comma-separated syntax to reach deeper array node.
     *
     * @param string $Name
     * @param mixed $DefaultValue
     * @return mixed
     */
    public function GetOption($Name, $DefaultValue=null) {

        $K= explode('.',$Name);
        $Count= min(10, count($K));
        // for root option
        if ($Count === 1) {
            return isset($this->Options[$Name])
                ? $this->Options[$Name]
                : $DefaultValue;
        }
        // using comma-separated keys
        $Pointer= &$this->Options;
        for($x=0; $x<$Count; $x++) {
            if (!isset($Pointer[$K[$x]])) {
                return $DefaultValue;
            }
            $Pointer= &$Pointer[$K[$x]];
        }
        return $Pointer;
    }


    /**
     * Fetch all configuration options.
     *
     * @param array $WithoutKeys  list of keys that need to be removed from result
     * @return array
     */
    public function GetAllOptions($WithoutKeys=array()) {

        return empty($WithoutKeys)
            ? $this->Options
            : array_diff_key($this->Options, array_flip($WithoutKeys));
    }



    //-----------------------------------------------------------------
    //
    //             Common methods for dealing with services
    //
    //-----------------------------------------------------------------


    /**
     * Initiate and provide service.
     * To ensure lazy loading do not call this method until real usage point.
     *
     * @param string  $ServiceName Name of registered service
     * @return object|null|bool  Instance of requested service
     */
    protected function GetService($ServiceName, $AsBool=false) {

        $Service= isset($this->Options['Services'][$ServiceName])
            ? $this->Options['Services'][$ServiceName]
            : null;
        // if service is object it is either:
        //  - passed as instantiated object
        //  - not called for first time, it is instantiated in previous calls
        if (is_object($Service)) {
            return $AsBool ? true : $Service;
        }
        // get service manager
        if (!is_object($this->Options['ServiceManager'])) {
            return null;
        }
        $Manager= $this->Options['ServiceManager'];
        if (!method_exists($Manager, 'Get')) {
            return null;
        }
        // if service not defined in this component check $ServiceName in the manager
        if ($Service === null && $Manager->Has($ServiceName)) {
            $Service= $ServiceName;
            $this->Options['Services'][$ServiceName]= $ServiceName;
        }
        // is service passed as fully qualified classname?
        if (is_string($Service) && strpos($Service,'\\') !== false) {
            // register service using original $ServiceName
            $Manager->Register($ServiceName, array(
                'Class'=> $Service,
            ));
            // replace classname with original service name for further usage
            $this->Options['Services'][$ServiceName]= $ServiceName;
            $Service= $ServiceName;
        }
        // return
        return $AsBool
            ? $Manager->Has($Service)
            : $Manager->Get($Service);
    }


    /**
     * Similar as GetService but without resolving ServiceName.
     * Returns anonymous function which will call GetService on execution time.
     *
     * @param string $ServiceName
     * @return object
     */
    protected function GetServiceLazy($ServiceName) {

        return new LazyLoader($ServiceName, $this->Options['ServiceManager']);
    }


    /**
     * Check does specified service exist.
     *
	 * @param string $ServiceName
     * @return bool
     */
    protected function HasService($ServiceName) {

        return $this->GetService($ServiceName, true);
    }


    /**
     * Returns object of service manager.
     *
     * @return Accent\AccentCore\ServiceManager\ServiceManager
     */
    protected function GetServiceManager() {

        return $this->Options['ServiceManager'];
    }


    /**
     * Call ServiceManager and register new service.
     * Previously some common options are added to Options array.
     *
     * @param string $Name  name of service
     * @param string $Class  FQCN of service
     * @param array $Options  configuration
     * @return object|bool  instance of newly created service
     */
    protected function RegisterService($Name, $Class, $Options=array()) {

        $Options= $this->MergeArrays(array($Options, $this->GetCommonOptions()));
        $Success= $this->GetServiceManager()->Register($Name, $Class, $Options);
        return $Success === true
            ? $this->GetService($Name)
            : false;
    }


    /**
     * Returns array of common (prepopulated) options for initializin of new services
     *
     * @return array
     */
    protected function GetCommonOptions() {

        return array(
            // mandatory values
            'Paths'=> $this->Options['Paths'],
            'ErrorFunc'=> $this->Options['ErrorFunc'],
            'Services'=> $this->Options['Services'],
            'ServiceManager'=> $this->Options['ServiceManager'],
            // optional values
            'RequestContext'=> isset($this->Options['RequestContext']) ? $this->Options['RequestContext'] : null,
            'App'=> isset($this->Options['App']) ? $this->Options['App'] : null,
        );
    }


    /**
     * Short way to instantiate new object with appened common options to $Options.
     *
     * @param string $Class
     * @param array $Options
     * @return object
     */
    protected function BuildComponent($Class, $Options=array()) {

        $Options= $this->MergeArrays(array($this->GetCommonOptions(), $Options));
        return new $Class($Options);
    }



    //-----------------------------------------------------------------
    //
    //                Methods for dealing with plugins
    //
    //-----------------------------------------------------------------

    /**
     * Add new plugin to component.
     * Parameter is building configuration.
     * Plugin will not be instantiated immediately, config will be stored in building roaster waiting for first usage.
     * It means that plugin can remain uninstantied in application lifecycle.
     *
     * @param $Conf array
     */
    public function RegisterPlugin($Conf) {

        $this->Plugins['Configs'][]= $Conf + array(
                // append default values for optional config item
                'Class'=> '',      // FQCN
                'Name'=> '',       // specify unique name to be able to unregister plugin
                'Priority'=> 3,    // specify bigger number to defer plugin execution later
            );
    }


    /**
     * Remove registered plugin.
     * To allow unregistering plugin must been configured with unique name and must have public property $Name.
     *
     * @param $Name string
     */
    public function UnregisterPlugin($Name) {

        // remove from building roaster
        foreach ($this->Plugins['Configs'] as $Key=>$Conf) {
            if ($Conf['Name'] === $Name) {
                unset($this->Plugins['Configs'][$Key]);
            }
        }

        // remove from built objects
        foreach ($this->Plugins['Objects'] as $Priority=>$Objects) {
            foreach ($Objects as $Key=>$Obj) {
                if (property_exists($Obj, 'Name') && $Obj->Name === $Name) {
                    unset($this->Plugins['Objects'][$Priority][$Key]);
                }
            }
        }
    }


    /**
     * Builds all plugins waiting in building roaster.
     */
    protected function BuildPlugins() {

        foreach ($this->Plugins['Configs'] as $Key => $Conf) {

            // get plugin's fully qualified class name
            $Class= $Conf['Class'];
            if ($Class === '' || !class_exists($Class)) {
                $this->Error($this->ClassName.': BuildPlugins: Class "'.$Class.'" not found.');
                continue;      // sorry, "Class" is mandatory setting
            }

            // prepare priority
            $Priority= intval($Conf['Priority']);
            if (!isset($this->Plugins['Objects'][$Priority])) {
                $this->Plugins['Objects'][$Priority]= array();
                ksort($this->Plugins['Objects']);
            }

            // create object, it must be desc. of Component
            $Plugin= new $Class(
                $Conf                        // pass $Conf to constructor
                + $this->GetCommonOptions()  // add App, ServiceManager, Paths
                + array('Owner'=>$this)      // add calling component
            );

            // add object to registry
            if ($Plugin->IsInitiated()) {
                $this->Plugins['Objects'][$Priority][]= $Plugin;
            } else {
                $this->Error($this->ClassName.': BuildPlugins: Class "'.$Class.'" failed to build.');
            }

            // remove from building roster
            unset($this->Plugins['Configs'][$Key]);
        }
    }



    //-----------------------------------------------------------------
    //
    //                Shortcuts to some common services
    //
    //-----------------------------------------------------------------


    /**
     * Return current Request object.
     * Accessing Request object in this way allows creation of sub-requests.
     *
     * @return false|\Accent\Request\Request
     * DEPRECATED!
     */
    /*protected function GetRequest() {

        $Service= $this->GetService('RequestStack');
        return $Service
            ? $Service->StackerGet()
            : false;
    }*/


    /**
     * Return Application object.
     *
     * @return \Accent\Application\Kernel
     */
    public function GetApp() {

        return $this->GetOption('App');
    }


    /**
     * Shortcut to RequestObject options
     * or context of Request service as fallback
     * or create context from super-globals as last fallback.
     *
     * @return \Accent\AccentCore\RequestContext
     */
    protected function GetRequestContext() {

        $Context= $this->GetOption('RequestContext');
        if ($Context) {
            return $Context;
        }
        // try to get it from Request service
        $RequestService= $this->GetService('Request');
        if ($RequestService) {
            return $RequestService->GetRequestContext();
        }
        // no? ok, create from globals and save it in options for further usage
        $this->Options['RequestContext']= new RequestContext($this->GetCommonOptions());
        $this->Options['RequestContext']->FromGlobals();
        return $this->Options['RequestContext'];
    }


    /**
     * Shortcut to RequestContext::Input method.
     *
     * @param string $Key  key of _GET/_POST/_COOKIE item to fetch
     * @param string $Sanitizers  list of sanitizers separated by "|"
     * @param mixed $DefaultValue  return this value if key not found
     * @return mixed
     */
    protected function Input($Key, $Sanitizers='', $DefaultValue=null) {

        $Context= $this->GetRequestContext();
        return $Context
            ? $Context->Input($Key, $Sanitizers, $DefaultValue)
            : null;
    }


    /**
     * Shortcut for Event service - Execute(), additionaly checks for
     * event listeners within plugins of this component and execute them too.
     *
     * @param string $EventName  identifier of event
     * @param BaseEvent|array $EventObject  instance of event object or array of it options
     * @param bool $ReturnEvent  whether to return event object instead of execution status
     * @return bool  indication was any listener terminate execution loop
     */
    protected function EventDispatch($EventName, $EventObject=null, $ReturnEvent=false) {

        // get service
        $EventService= $this->GetService('Event');

        // if service not found, return false
        if (!is_object($EventService)) {
            return false;
        }

        // collect plugin listeners
        $PluginMethod= 'On'.preg_replace('/[^\pL\d]+/u', '', $EventName); // strip out all invalid chars and prepend "On"
        $this->BuildPlugins();
        $PluginListeners= [];
        foreach($this->Plugins['Objects'] as $PluginGroup) {
            foreach ($PluginGroup as $Obj) {
                if (is_callable(array($Obj, $PluginMethod))) {
                    $PluginListeners[]= array($Obj,$PluginMethod);
                }
            }
        }

        // call service
        return $EventService->Execute($EventName, $EventObject, $ReturnEvent, $PluginListeners);
    }


    /**
     * Shorthand for attaching event listener.
     *
     * @param string $EventId  id of event
     * @param string|array $MethodName  name of local method or callable-array
     * @param int $Priority   position in order of execution, lowest number will be executed first
     * @param null|object $Owner  can be used to detach all listeners in single call
     * @return mixed
     */
    protected function RegisterEventListener($EventId, $MethodName, $Priority=0, $Owner=null) {

        $Event= $this->GetService('Event');
        if (!is_object($Event)) {
            return;
        }
        $Callable= $MethodName;

        // resolve references on "this"
        if (is_string($MethodName)) {
            if (substr($MethodName, 0, 2) === '@/') {
                $Callable= array($this, substr($MethodName, 2));
            } else if (substr($MethodName, 0, 3) === '@::') {
                $Callable= array(get_class($this), substr($MethodName, 3));
            }
        }

        // attach
        return $Event->AttachListener($EventId, $Callable, $Priority, $Owner);
    }



    /**
     * Shortcut for translation service,
     * return translated message if found but fallback to default message if not.
     *
     * Usage example: echo $this->MsgDef('Forget password?', 'Auth.ForgetPass');
     * This will search glossary for Auth.ForgetPass in current language and return
     * sentence found, or 'Forget password?' if not.
     *
     * All parameters can be packed in array and supplied as first parameter.
     *
     * Short MsgCode format: "code#book#modifier#lang".
     *
     * For detailed descriptions of all parameters see:
     *  Accent\Localization\Localization::Translate
     *
     * @param string $Message  sentence in default language
     * @param string|null $MsgCode  key for translation
     * @param string|null $Modifier  one of following:
     *                      - '|' to return array exploded by '|'
     *                      - '1..99' to explode and return 99th element of array
     *                      - '=99' execute 'choice pattern' and return part of message
     *                      - '*99' execute 'pluralization choice' and return part of message
     * @param string|null $Lang  identifier of language
     * @param array|null $Replace  values for placeholders
     * @return string
     */
    protected function MsgDef($Message, $MsgCode=null, $Modifier=null, $Lang=null, $Replace=null) {

        if (is_array($Message)) {  // unpack parameters is passed as array
            while(count($Message) < 5) {$Message[]=null;}
            list($Message,$MsgCode,$Modifier,$Lang,$Replace)= $Message;
        }
        if ($MsgCode === null || $MsgCode === '') {
            return $Message; // nothing to translate, return original message
        }
        $TS= $this->GetService('Localization');
        if (!is_object($TS)) {
            return $Message; // sorry, translator service is not available
        }
        $Translated= $TS->Translate($MsgCode, $Modifier, $Lang, $Replace);
        $MsgCodeParts= explode('#',$MsgCode);
        if ($Translated !== reset($MsgCodeParts)) {
            // translation found
            return $Translated;
        }
        // not found, return default message
        return ($Replace === null && $Modifier === null)
            ? $Message
            : $TS->Translate($Message, $Modifier, $Lang, $Replace);
    }


    /**
     * Shortcut for translation service, return localized message.
     * If not found method will return $MsgCode.
     *
     * Usage example: echo $this->Msg('Auth.ForgetPass');
     * This will search glossary for Auth.ForgetPass in current language and return
     * localized message if found, or 'Auth.ForgetPass' if not.
     *
     * If developer is NOT SURE that localized message will be found, it is
     * recommended to use $this->MsgDef method.
     *
     * Short MsgCode format: "code#book#modifier#lang".
     *
     * @param string|null $MsgCode  key for translation
     * @param string|null $Modifier  one of following:
     *                      - '|' to return exploded by '|'
     *                      - '1..99' to explode and return 99th element of array
     *                      - '=99' execute 'choice pattern' and return part of message
     *                      - '*99' execute 'pluralization choice' and return part of message
     * @param string|null $Lang  identifier of language
     * @param array|null $Replace  values for placeholders
     * @return string
     */
    protected function Msg($MsgCode=null, $Modifier=null, $Lang=null, $Replace=null) {

        $TS= $this->GetService('Localization');
        if (is_object($TS)) {
            return $TS->Translate($MsgCode, $Modifier, $Lang, $Replace);
        } else {
            $MsgCodeParts= explode('#',$MsgCode);
            return reset($MsgCodeParts); // sorry, translator service is not available
        }
    }


    /**
     * Log helper.
     * This method will check is logger service available and send message to it,
     * additionally it will call TraceInfo with same message.
     * $Level and $Data values will be sent to log service as-is, their format depends on service itself.
     *
     * @param string|array $Message  textual content to store in log or array to translate with MsgDef()
     * @param mixed $Level  (optional) value indicating importance of message
     * @param mixed $Data  (optional) additional info to be stored in log
     */
    protected function Log($Message, $Level=null, $Data=null) {

        // call translation if passed as array
        if (is_array($Message)) {
            $Message= $this->MsgDef($Message);
        }

        // send message to logger service
        $Service= $this->GetService('Log');
        if ($Service) {
            $Service->Log($Message, $Level, $Data);
        }

        // send it also to tracer, as "Info" level
        $this->TraceInfo($Message);
    }


    /**
     * Tracer helpers.
     * Specifying level using Tracer::LEVEL_INFO is replaced by descriptive string to remove dependency on Tracer class.
     * Note that triggering this method before initialisation of Tracer service will simply reject messages.
     * There is no sense to implement temporary buffer to flush messages later because each component will have its own
     * buffer which making impossible to represent real order of messages in log file.
     */
    protected function TraceDebug($Message, $AppendCallStack=false) {

        $this->TracerMessage('Debug', $Message, $AppendCallStack);
    }

    protected function TraceInfo($Message, $AppendCallStack=false) {

        $this->TracerMessage('Info', $Message, $AppendCallStack);
    }

    protected function TraceError($Message, $AppendCallStack=false) {

        $this->TracerMessage('Error', $Message, $AppendCallStack);
    }

    protected $Tracer;

    protected function TracerMessage($Level, $Message, $AppendCallStack=false) {
        // Tracer service may be not initialized jet, call GetService, if it fails try again on next call
        if (!$this->Tracer) {
            $this->Tracer= $this->GetService('Tracer');
        }

        // send message if service is ready
        if (is_object($this->Tracer)) {
            $this->Tracer->Trace($this->TracerSection, $Level, $Message, $AppendCallStack);
        }
    }

    protected function TraceDB() {
        // special trace call, prepare and store list of all database queries
        $List= array();
        foreach($this->GetService('DB')->GetDebugQueriesList() as $Line) {
            $List[]= $Line[1];
        }
        $this->TracerMessage('Info', "Database queries:\n".implode("\n", $List));
    }



}

