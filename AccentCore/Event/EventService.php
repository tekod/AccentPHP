<?php namespace Accent\AccentCore\Event;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Event service object.
 */

use Accent\AccentCore\Component;
use Accent\AccentCore\Event\BaseEvent;


class EventService extends Component {


    protected static $DefaultOptions= array(

        // version of Accent/AccentCore package
        'Version'=> '1.0.0',
    );

    // internal properties
    protected $Listeners= array();
    protected $Wildcards= array();
    protected $Misses= array();


    /**
     * Assign call to $Callable to $EventName event.
     *
	 * @param string $EventName  name of event
     * @param string|array $Callable  executioner, see Component->ResolveCallable() for syntax
     * @param int $Priority   position in order of execution, lowest number will be executed first
     * @param null|object $Owner  can be used to detach all listeners in single call
     * @return mixed  identifier of created listener
     */
    public function AttachListener($EventName, $Callable, $Priority=0, $Owner=null ) {

        $IsWildcard= strpos($EventName, '*') !== false;
        if ($IsWildcard) {
            $Registry= &$this->Wildcards;
        } else {
            $Registry= &$this->Listeners;
        }
        // ensure array type
        if (!isset($Registry[$EventName])) {
  		    $Registry[$EventName]= array('List'=>array(), 'Sorted'=>true, 'ExecCount'=>0);
        }
        // generate unique ID, but dont waste time if not necessary
        $Id= ($Owner === null) ? true : hash('md5', serialize(array($EventName, $Owner, microtime())));
        // store
        $Registry[$EventName]['List'][]= array($Priority, $Owner, $Callable, $Id);
        $Registry[$EventName]['Sorted']= false;
        return $Id;
    }


    /**
     * Remove attached listener using value returned from AttachListener().
	 *
	 * @param string $Id
     */
    public function DetachListener($Id) {

        if ($Id===null) {
            return;
        }
        foreach(array_keys($this->Listeners) as $Event) {
            foreach(array_keys($this->Listeners[$Event]['List']) as $Key) {
                if ($this->Listeners[$Event]['List'][$Key][3] !== $Id) {continue;}
                unset($this->Listeners[$Event]['List'][$Key]);
                //$this->Sorted[$Event]= false;
                $this->Listeners[$Event]['Sorted']= false;
            }
        }
    }


    /**
     * Checks is there any attached listener for specified EventName.
     * Wildcards are not counted here.
     *
     * @param string $EventName
     * @return boolean
     */
    public function HasListeners($EventName) {

        return isset($this->Listeners[$EventName]) && !empty($this->Listeners[$EventName]['List']);
    }


    /**
     * Execute all event listeners assigned to $EventName.
     *
     * @param string $EventName  identifier of event
     * @param BaseEvent|array $EventObject  instance of event object or array of it options
     * @param bool $ReturnEvent  whether to return event object instead of execution status
     * @param array $ExtraListeners  list of additional callables to execute
     * @return bool|object  indication was any listener terminate execution loop
     */
    public function Execute($EventName, $EventObject=null, $ReturnEvent=false, $ExtraListeners=array()) {

        // if event object is omitted - instantiate BaseEVent without options
        if ($EventObject === null) {
            $EventObject= [];
        }

        // if event object is specified as array - instantiate BaseEvent with that options
        if (is_array($EventObject)) {
            $EventObject= new BaseEvent($EventObject + $this->GetCommonOptions());
        }

        // find list of listeners
        $Listeners= array_merge($ExtraListeners, $this->GetListeners($EventName));
        $this->TraceDebug('Event "'.$EventName.'" ('.count($Listeners).' listeners found).');

        // set EventName property
        $EventObject->EventName= $EventName;

        // loop
  	    foreach($Listeners as $Listener) {

            // execute callable
            $Result= $this->ExecuteListener($Listener, $EventObject);

            // terminate loop if listener returns true
            if ($Result === true) {
                $this->TraceDebug('Event "'.$EventName.'" terminated!');
                return $ReturnEvent ? $EventObject : true;
            }
	   }

       // not handled
	   return $ReturnEvent ? $EventObject : false;
    }


    /**
     * Run listener callable.
     */
    protected function ExecuteListener($Listener, $EventObject) {

        // get callable
        $Callable= $this->ResolveCallable($Listener[2]);

        // execute callable
        if ((is_array($Callable)) ) {
            // checking with is_callable doesnt work for array definition
            // this is for array(class,method)
            return call_user_func($Callable, $EventObject);
        } else if (is_callable($Callable)) {
            // this is for anything else
            return $Callable($EventObject);
        }
    }



    /**
     * Internal, retrieving listeners for execution.
     */
    protected function GetListeners($EventName) {

        // search in normal listeners
        $StandardListeners= array();
        if (isset($this->Listeners[$EventName])) {
            // sort standard listeners if needed
            if (!$this->Listeners[$EventName]['Sorted']) {
                sort($this->Listeners[$EventName]['List']);
                $this->Listeners[$EventName]['Sorted']= true;
            }
            $this->Listeners[$EventName]['ExecCount']++;
            $StandardListeners= $this->Listeners[$EventName]['List'];
        }
        // search in wildcards
		$WildcardListeners= array();
		foreach(array_keys($this->Wildcards) as $Key) {
            if (!$this->WildMatch($EventName, $Key)) {continue;}
            $this->Wildcards[$Key]['ExecCount']++;
            $WildcardListeners= array_merge($WildcardListeners, $this->Wildcards[$Key]['List']);
		}
        // sum
		$Result= array_merge($StandardListeners, $WildcardListeners);
        $FoundStandard= !empty($StandardListeners);
        $FoundWildcard= !empty($WildcardListeners);
        // if both listener types are found sort whole list again to align them
        if ($FoundStandard && $FoundWildcard) {
            sort($Result);
        }
        // register miss-shot
        if (!$FoundStandard && !$FoundWildcard) {
            if (!isset($this->Misses[$EventName])) {
                $this->Misses[$EventName]= 0;
            }
            $this->Misses[$EventName]++;
        }
        return $Result;
	}


    /**
     * Internal, comparing wildcards within name of event.
     */
    protected function WildMatch($Source, $Pattern) {
        // try simple cases first
        if ($Pattern === '*' || $Source === $Pattern) {
            return true;
        }
        return (is_callable('fnmatch'))
            ? fnmatch($Pattern, $Source)
            : (bool)preg_match(
                  "#^".strtr(preg_quote($Pattern, '#'),
                  array('\*'=>'.*', '\?'=>'.'))."$#i",
                  $Source
              );
    }


    /**
     * Remove listeners for specified name.
     * However statistics about target listeners will be preserved.
     * Wildcards will be removed too.
     */
    public function ClearByEventName($EventName=null) {

    	if (is_null($EventName)) {
    		// remove ALL listeners
            foreach(array_keys($this->Listeners) as $Name) {
                $this->Listeners[$Name]['List']= array();
            }
            foreach(array_keys($this->Wildcards) as $Name) {
                $this->Wildcards[$Name]['List']= array();
            }
    	} else {
    		// remove listeners for specified event
            if (strpos($EventName, '*') !== false) {
                if (isset($this->Wildcards[$EventName])) {
                    $this->Wildcards[$EventName]['List']= array();
                }
            } else {
                if (isset($this->Listeners[$EventName])) {
                    $this->Listeners[$EventName]['List']= array();
                }
            }
    	}
    }


    /**
     * Remove all events attached by specified owner.
     * Wildcards will be removed too.
     */
    public function ClearByOwner($Owner) {

        if (is_null($Owner)) {
            return; // $Owner is mandatory
        }
        foreach(array_keys($this->Listeners) as $Event) {
            foreach(array_keys($this->Listeners[$Event]['List']) as $Key) {
                if ($this->Listeners[$Event]['List'][$Key][1] !== $Owner) {
                    continue;
                }
                unset($this->Listeners[$Event]['List'][$Key]);
                $this->Listeners[$Event]['Sorted']= false;
            }
        }
        foreach(array_keys($this->Wildcards) as $Wildcard) {
            foreach(array_keys($this->Wildcards[$Wildcard]['List']) as $Key) {
                if ($this->Wildcards[$Wildcard]['List'][$Key][1] !== $Owner) {
                    continue;
                }
                unset($this->Wildcards[$Wildcard]['List'][$Key]);
                $this->Wildcards[$Event]['Sorted']= false;
            }
        }
    }


    /**
     * Returns names of all registered events with theirs execution count.
     * @return array
     */
    public function GetStatistics() {

        $List= array();
        foreach($this->Listeners as $Name=>$Struct) {
            $List[$Name]= $Struct['ExecCount'];
        }
        foreach($this->Wildcards as $Name=>$Struct) {
            $List[$Name]= $Struct['ExecCount'];
        }
        arsort($List);
        return array('Hits'=>$List, 'Misses'=>$this->Misses);
    }

}
