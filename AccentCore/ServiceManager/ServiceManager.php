<?php namespace Accent\AccentCore\ServiceManager;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */




/**
 *
 * $App->ServiceManager= new ServiceManager;
 * $App->ServiceManager->Register('DB', array('Class'=>'\Accent\DB',...));
 * ..
 * ..
 * $DB= $App->ServiceManager->Get('DB');
 * $DB->Query(....);
 *
 * Service class will not be instantiated until request for service occured.
 *
 * Only one instance of Service manager is needed in application.
 */


class ServiceManager {


    protected $ServiceList= array();

    protected $AliasList= array();


    /**
     * Register new service.
     * @param string $Name  Unique service name
     * @param string $Class  Full class name
     * @param array $Options  Settings which will be passed to class constructor
     * @return boolean Success of registration
     */
    public function Register($Name, $Class, array $Options=array()) {

        if (!$Name || !$Class) {
            return false; // mandatory arguments
        }
        if (isset($this->ServiceList[$Name])) {
            return true; // service already exist, be silent
        }
        $Options += array(          // add few mandatory options
            'Init'=> null,              // declare method to autoexec upon registration
            'ServiceManager'=> $this,   // give services ability to use other services
        );
        $this->ServiceList[$Name]= array(
            'Name'=> $Name,
            'Options'=> $Options,
            'Class'=> $Class,
            'Instance'=> null,
        );
        if ($Options['Init']) {         // instantiate service immediately and execute specified method
            $this->InstantiateService($Name);
            call_user_func(array($this->ServiceList[$Name]['Instance'], $Options['Init']));
        }
        return true;
    }


    /**
     * Bulk register array of services.
     * @param array $List Example:array(array('AT','MyClass',array()),array('AC','OthClass'))
     * @return boolean Success
     */
    public function RegisterAll(array $List) {

        $Success= true;
        foreach($List as $Args) {
            list($Name, $Class, $Opts)= $Args + array(0=>'',1=>'',2=>array());
            if (!$this->Register($Name, $Class, $Opts)) {
                $Success= false;
            }
        }
        return $Success;
    }


    /**
     * Register new service as already instantiated object.
	 *
	 * @param string $Name
	 * @param object $Object
	 * @return bool
     */
    public function RegisterObject($Name, $Object) {

        if (isset($this->ServiceList[$Name])) {
            return true; // service already exist, be silent
        }
        $this->ServiceList[$Name]= array(
            'Name'=> $Name,
            //'Options'=> $Options,
            //'Class'=> get_class($Object),
            'Instance'=> $Object,
        );

        return true;
    }


    /**
     * Retrieve specified service.
     *
     * @param string $Name Name or alias of service
     * @return object Service instance, null if service not found, false on initialization failure
     */
    public function Get($Name) {

        $Name= $this->ResolveName($Name);
        if (!isset($this->ServiceList[$Name])) {
            return null;
        }
        if ($this->ServiceList[$Name]['Instance'] === null) {
            // initialize on first use
            if (!$this->InstantiateService($Name)) {
                return false;
            }
        }
        return $this->ServiceList[$Name]['Instance'];
    }


    /**
     * Deferred variant of method "Get".
     * Instead to immediately instantiate requested service it will return builder object.
     * This way application will spend time on creating services only when they are really required.
     * Simply call builder object as function (invoke it) to trigger instantiation of that service.
     * Example: ... $T= $SM-GetLazy('DB'); ... $DBO= $T(); ... $DBO->Query('users').....
     *
     * @param $Name
     * @return LazyLoader
     */
    public function GetLazy($Name) {

        return new LazyLoader($Name, $this);
    }


    /**
     * Build service.
     *
     * @param string $Name  name of service
     * @return boolean  success
     */
    protected function InstantiateService($Name) {

        $Class= trim($this->ServiceList[$Name]['Class'], '\\');
        $Options= $this->ServiceList[$Name]['Options'];
        try {
            $Object= new $Class($Options);
        } catch (Exception $e) {
            // exit silently and leave $this->ServiceList[$Name]['Instance'] = null
        }
        if (method_exists($Object,'IsInitiated') && !$Object->IsInitiated()) {
            // service can implement IsInitiated() method to signal
            // that something went wrong during its construction
            $this->ServiceList[$Name]['Instance']= false;
            return false;
        }
        $this->ServiceList[$Name]['Instance']= $Object;
        return true;
    }


    /*
     * Resolve alias to service name, including nested aliases.
     * Existence of resulting name is not verified.
     */
    protected function ResolveName($Name) {

        if (isset($this->AliasList[$Name])) {
            // recursion >:-)
            $Name= $this->ResolveName($this->AliasList[$Name]);
        }
        return $Name;
    }


    /**
     * Check is specified service exist
     * @param string $Name
     * @param bool $InitializedOnly
     * @return bool
     */
    public function Has($Name, $InitializedOnly=false) {

        $Name= $this->ResolveName($Name);
        return $InitializedOnly
            ? isset($this->ServiceList[$Name]) && is_object($this->ServiceList[$Name]['Instance'])
            : isset($this->ServiceList[$Name]);
    }


    /**
     * Delete specified service and all associated aliases.
     * @param string $Name
     * @return \Accent\AccentCore\ServiceManager\ServiceManager
     */
    public function Remove($Name) {

        $Name= $this->ResolveName($Name);
        if (!isset($this->ServiceList[$Name])) {
            return $this;
        }
        // should call some cleaning before unsetting ?
        $this->ServiceList[$Name]['Instance']= null;
        unset($this->ServiceList[$Name]);
        // remove all aliases, recursive
        $this->RemoveDependentAliases($Name);
        return $this;
    }


    /**
     * Register alias for an service (or another alias).
     * Target service does not need to be registered before setting its alias.
     * @param string $AliasName
     * @param string $ServiceName
     * @return \Accent\AccentCore\ServiceManager\ServiceManager
     */
    public function SetAlias($AliasName, $ServiceName) {

        $this->AliasList[$AliasName]= (string)$ServiceName;
        return $this;
    }


    /**
     * Unregister specified alias.
     * @param string $AliasName
     * @return \Accent\AccentCore\ServiceManager\ServiceManager
     */
    public function RemoveAlias($AliasName) {

        unset($this->AliasList[$AliasName]);
        $this->RemoveDependentAliases($AliasName);
        return $this;
    }


    /**
     * Internal, recursive delete all depended aliases.
     */
    protected function RemoveDependentAliases($Name) {

        $ValuesList= array($Name);
        do {
           $Intersect= array_keys(array_intersect($this->AliasList, $ValuesList));
           foreach($Intersect as $k) {
               unset($this->AliasList[$k]);
               $ValuesList[]= $k;
           }
        } while(!empty($Intersect));
    }


    /**
     * For debugging purposes
     */
    public function Debug_ShowServices() {

        echo '<h1>Services:</h1>';
        foreach($this->ServiceList as $Item) {
            echo $Item['Name'].': '.$Item['Class'].'<br>';
        }
        die();
    }

}

