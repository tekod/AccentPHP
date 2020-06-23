<?php namespace Accent\AccentCore\AutoLoader;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


class AutoLoader {


    protected $DefaultOptions= array(
        'Cache'=> array(
            'Method'=> '', // name of storage method: 'File', 'APC'
            'Path'=> '', // only for 'File' method - full path where to save PHP file
            'Key'=> '', // only for 'APC' method - key for record in APC engine
        ),
    );


    // registry related props

    protected $Registry= array();

    protected $RegistryModified= false;

    protected $ClearRegistry= false;

    protected $Rules= array();

    protected $Processors= array();

	protected $Options;


   // methods

	/**
	 * AutoLoader constructor.
	 *
	 * @param array $Options
	 */
    public function __construct($Options=array()) {

        // add default options and store them
        $this->DefaultOptions += array(
            'ErrorFunc'=> function($Message) {trigger_error($Message, E_USER_WARNING);}
        );
        $this->Options= (array)$Options + $this->DefaultOptions;

        // initialize built in processors
        $this->InitInternalProcessors();

        // set shutdown function to save modified registry
        register_shutdown_function(array(&$this, 'Shutdown'));
    }


    /**
     * Dispatch error message using $Options['ErrorFunc']
	 *
	 * @param string $Message
     */
    protected function Error($Message) {

        $Func= $this->Options['ErrorFunc'];
        $Func('Autoloader error: '.$Message);
    }


    /**
     * Append rule to specified processor.
     *
     * @param string $Processor  name of processor
     * @param string $SearchFragment  part of classname, usually namespace
     * @param string $Path
     */
    public function AddRule($Processor, $SearchFragment, $Path) {

        // validate
        if (!isset($this->Rules[$Processor])) {
            $this->Error('Unknown processor "'.$Processor.'".');
        }

        // sanitize
        $SearchFragment= ltrim($SearchFragment, ' \\');
        $Path= rtrim($Path, DIRECTORY_SEPARATOR);

        // add to rules list
        if (!isset($this->Rules[$Processor][$SearchFragment])) {
            $this->Rules[$Processor][$SearchFragment]= array();
        }
        $this->Rules[$Processor][$SearchFragment][]= $Path;
    }


    /**
     * Register multiple rules in single function call.
     * Usable for passing huge structure from configuration.
     * Example:
     *   array('Namespace'=>array(
     *      array('test1', 'my/folder1'),
     *      array('test2', 'my/folder2'),
     *   ))
     * @param array $Rules
     */
    public function AddRules(array $Rules) {

        foreach ($Rules as $Processor=>$Group) {
            // call AddRule in loop
            foreach($Group as $Rule) {
                $this->AddRule($Processor, $Rule[0], $Rule[1]);
            }
        }
    }


    /**
     * Setup built-in processors.
     */
    protected function InitInternalProcessors() {

        $this->RegisterProcessor('Namespace', array(__CLASS__,'Processor_Namespace'));
        $this->RegisterProcessor('PSR0',      array(__CLASS__,'Processor_PSR0'));
        $this->RegisterProcessor('Prefix',    array(__CLASS__,'Processor_Prefix'));
        $this->RegisterProcessor('Underscore',array(__CLASS__,'Processor_Underscore'));
        $this->RegisterProcessor('CamelCase', array(__CLASS__,'Processor_CamelCase'));
    }


    /**
     * Extend ability to find class file by adding specialized resolvers.
     *
     * @param string $Name  simple identifier of processor
     * @param callable  $Callable method to execute
     */
    public function RegisterProcessor($Name, $Callable) {

        $this->Processors[$Name]= $Callable;
        $this->Rules[$Name]= array();
    }


    /**
     * Register single class location.
     * Classes added here will not be searched for but explicitly loaded from it's path.
     *
     * @param string $ClassName
     * @param string $Path
     */
    public function AddClass($ClassName, $Path) {

        $this->Registry[$ClassName]= $Path;
    }


    /**
     * Activate this autoloader.
     *
     * @param boolean $Prepend  whether to prepend the autoloader or not
     */
    public function Register($Prepend=false) {
        // target self::Load
        spl_autoload_register(array($this, 'Load'), true, $Prepend);
    }


    /**
     * Deactivate this autoloader.
     */
    public function Unregister() {

        spl_autoload_unregister(array($this, 'Load'));
    }


    /**
     * Autoloading handler.
	 *
     * @param string $ClassName
     * @return null|boolean
     */
    public function Load($ClassName) {

        $Path= $this->GetLocation($ClassName);
        if (!$Path) {
            return null;
        }
        include $Path;
        return true;
    }


    /**
     * Search for specified class.
     *
     * @param string $ClassName
     * @return string
     */
    public function GetLocation($ClassName) {

    	$Path= '';

        // check is already resolved
        if (isset($this->Registry[$ClassName]) && is_file($this->Registry[$ClassName])) {
            return $this->Registry[$ClassName];
        }

        // call processors
        foreach($this->Processors as $Name=>$Callable) {
            if (empty($this->Rules[$Name])) {
                continue;
            }
            $Path= call_user_func_array($Callable, array($ClassName, &$this->Rules[$Name]));
            if ($Path) {
                break;
            }
        }

        // save in registry if found
        if ($Path) {
            $this->Registry[$ClassName]= $Path;
            $this->RegistryModified= true;
        }
        return $Path;
    }


    /**
     * Use this method to import initial values to class registry.
     *
     * @param array $Array
     */
    public function InitRegistry($Array) {

        $this->Registry= $Array;
    }


    /**
     * Get map of classes from cache and append it to registry.
     */
    protected function LoadRegistry() {

        $Map= array();

        // 'File' method
        if ($this->Options['Cache']['Method'] == 'File') {
            $Map= include $this->Options['Cache']['Path'];
        }

        // 'APC' method
        if ($this->Options['Cache']['Method'] == 'APC' && extension_loaded('apc')) {
            $Map= apc_fetch($this->Options['Cache']['Key']);
        }

        // add to registry
        $this->Registry += (array)$Map;
    }


    /**
     * Store map of classes into cache.
     */
    protected function SaveRegistry() {

        // 'File' method
        if ($this->Options['Cache']['Method'] == 'File') {
            file_put_contents($this->Options['Cache']['Path'],
                    sprintf('<?php return %s;', var_export($this->Registry, true)));
        }
        // 'APC' method
        if ($this->Options['Cache']['Method'] == 'APC' && extension_loaded('apc')) {
            apc_store($this->Options['Cache']['Key'], $this->Registry, 3600);
        }
        // otherwise, don't save anything
    }


    /**
     * Clear buffer with knowledge of resolved class locations.
     *
     * @param bool $Safe
     */
    public function ClearCache($Safe=true) {

        if ($Safe) {
            $this->ClearRegistry= true; // postpone actual clearing
        } else {
            $this->Registry= array();
            $this->SaveRegistry();
        }
    }


    /**
     * Event function that should be registered on PHP shuting down to store paths in cache.
     */
    public function Shutdown() {

        if ($this->ClearRegistry) {
            $this->Registry= array();
            $this->RegistryModified= true;
        }
        if (!$this->RegistryModified) {
            return;
        }
        $this->SaveRegistry();
    }



    /************************************************************************
     |                                                                      |
     |                        Internal processors                           |
     |                                                                      |
     ************************************************************************/


    /**
     * Processor (resolver) for 'Namespace' rule.
     * It search for file trying namespace-path => directory-path.
     *
     * @param string $ClassName  name of class
     * @param array $Rules  list of rules for this processor
     * @return boolean|string  full file path or false
     */
    protected static function Processor_Namespace($ClassName, &$Rules) {

        // check is namespace part exist
        $Parts= explode('\\', $ClassName);

        if (count($Parts) == 1) {
            //return false;   // temporary disabled as feature
        }
        $RelativePath= '';

        // go through the parts of the fully-qualified class name
        while ($Parts) {

            // prepend the last part to the relative path, it is filename in first loop pass
            $RelativePath= DIRECTORY_SEPARATOR.array_pop($Parts).$RelativePath;

            // the remaining elements indicate the namespace
            $nsPrefix= implode('\\', $Parts);

            // are there any directories for this namespace?
            if (!isset($Rules[$nsPrefix])) {
                continue;   // no
            }

            // look through directories for this namespace
            foreach ($Rules[$nsPrefix] as $Dir) {
                $FilePath= $Dir.$RelativePath.'.php';
                if (is_file($FilePath)) {
                    return $FilePath;
                }
            }
        }
        return false;
    }


    /**
     * Processor (resolver) for 'PSR0' rule.
     * It is identical to 'Namespace' processor but additionally
     *  converts all underscore chars in class name into slashes.
     *
     * @param string $ClassName name of class
     * @param array $Rules list of rules for this processor
     * @return boolean|string full file path or false
     */
    protected static function Processor_PSR0($ClassName, &$Rules) {

        // check is namespace part exist
        $Parts= explode('\\', $ClassName);
        if (count($Parts) == 1) {
            // return false;        // temporary disabled as feature
        }
        $RelativePath= '';

        // go through the parts of the fully-qualified class name
        while ($Parts) {

            // prepend the last part to the relative path, it is filename in first loop pass
            $LastPart= ($RelativePath)
                ? array_pop($Parts)
                : str_replace('_', DIRECTORY_SEPARATOR, array_pop($Parts));
            $RelativePath= DIRECTORY_SEPARATOR.$LastPart.$RelativePath;

            // the remaining elements indicate the namespace
            $nsPrefix= implode('\\', $Parts);

            // are there any directories for this namespace?
            if (!isset($Rules[$nsPrefix])) {
                continue;   // no
            }

            // look through directories for this namespace
            foreach ($Rules[$nsPrefix] as $Dir) {
                $FilePath= $Dir.$RelativePath.'.php';
                if (is_file($FilePath)) {
                    return $FilePath;
                }
            }
        }
        return false;
    }


    /**
     * Processor (resolver) for 'Prefix' rule.
     * It simple checks how name of class starts and search in dirs attached for that prefix.
     *
     * @param string $ClassName name of class
     * @param array $Rules list of rules for this processor
     * @return boolean|string full file path or false
     */
    protected static function Processor_Prefix($ClassName, &$Rules) {

        // remove namespace part and add .php
        $ClassFileName= substr($ClassName, strrpos($ClassName, '\\')).'.php';

        // loop
        foreach($Rules as $Prefix => $Dirs) {
            if (strpos($ClassName, $Prefix) !== 0) {
                continue;
            }
            foreach ($Dirs as $Dir) {
                $FilePath= $Dir.DIRECTORY_SEPARATOR.$ClassFileName;
                if (is_file($FilePath)) {
                    return $FilePath;
                }
            }
        }
        return false;
    }


    /**
     * Processor (resolver) for 'Underscore' rule.
     * It tries to resolve classname by replacing underscores with slashes (PEAR style).
     *
     * @param string $ClassName name of class
     * @param array $Rules list of rules for this processor
     * @return boolean|string full file path or false
     */
    protected static function Processor_Underscore($ClassName, &$Rules) {

        // PEAR-like classname contains at least 1 underscore
        $Ex= explode('_', $ClassName);
        if (count($Ex) == 1) {
            // return false;   // temporary disabled as feature
        }

        array_shift($Ex); // remove first segment of name, we are already in that dir.
        $ClassFileName= implode(DIRECTORY_SEPARATOR, $Ex).'.php';
        // loop
        foreach($Rules as $Prefix => $Dirs) {
            if (strpos($ClassName, $Prefix) !== 0) {
                continue;
            }
            foreach($Dirs as $Dir) {
                $FilePath= $Dir.DIRECTORY_SEPARATOR.$ClassFileName;
                if (is_file($FilePath)) {
                    return $FilePath;
                }
            }
        }
        return false;
    }


    /**
     * Processor (resolver) for 'CamelCase' rule.
     * It tries to resolve classname by injecting slash in front of each upper letter of name.
     * @param string $ClassName name of class
     * @param array $Rules list of rules for this processor
     * @return boolean|string full file path or false
     */
    protected static function Processor_CamelCase($ClassName, &$Rules) {

        // inject slashes in name
        $Normalized= preg_replace('/([a-z])([A-Z])/', '$1/$2', $ClassName);

        // remove first segment because we are already in that dir.
        $ClassFileName= substr($Normalized, strpos($Normalized,'/')).'.php';

        // loop
        foreach($Rules as $Prefix => $Dirs) {
            if (strpos($ClassName, $Prefix) !== 0) {
                continue;
            }
            foreach($Dirs as $Dir) {
                $FilePath= $Dir.DIRECTORY_SEPARATOR.$ClassFileName;
                if (is_file($FilePath)) {
                    return $FilePath;
                }
            }
        }
        return false;
    }

}
