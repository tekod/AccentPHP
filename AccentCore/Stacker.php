<?php namespace Accent\AccentCore;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


/**
 * Stacker is wrapper for an object that makes it able to hold multiple versions of itself.
 *
 * Wrapped object remains accessible via magic methods __get and __call so clients should not be aware of wrapping.
 * At other side owner of object can easily add/remove instances to stack that will serve different data to clients.
 *
 * Example usecase:
 * it is bad idea registering class Accent\Request as a service because after creating sub-request
 * (forwarding) it must serve modified input data (URL, POST data,...) to controllers and models.
 * Solution is wrapping (stacking) Request service so clients will always get actual data from service
 * and remains unaware of switching inside of service.
 *
 * Usage:
 * | $RS= $this->RegisterService('Request', 'Accent\\AccentCore\\Stacker');
 * | $Request= new Request();
 * | $RS->StackerPush($Request, $this);
 * | ...
 * | echo $this->GetService('Request')->GetIP(); // will return IP address from last Request object in stack
 *
 * All properties and methods are prefixed with "Stacker" to avoid collision with names in wrapped object.
 */


class Stacker {


    // buffer for stacked objects
    protected $StackerStack= array();


    /**
     * Return last object in stack.
     *
     * @return object|false
     */
    public function StackerGet() {

        return empty($this->StackerStack)
            ? false
            : end($this->StackerStack)[0];
    }


    /**
     * Return first object in stack (master object).
     *
     * @return object|false
     */
    public function StackerGetFirst() {

        return empty($this->StackerStack)
            ? false
            : reset($this->StackerStack)[0];
    }


    /**
     * Append new object in stack, further calls to 'Get' method will return this object.
     *
     * @param object $Object  instance of stacked objects
     * @param mixed $Owner  unique identifier of owner, usually "$this"
     * @return self
     */
    public function StackerPush($Object, $Owner) {

        $this->StackerStack[]= array($Object, $Owner);
        return $this;
    }


    /**
     * Remove last element from stack, further calls to 'Get' method will return
     * item before that element.
     * Note that element can be removed only by it's owner.
     *
     * @param mixed $Owner  unique identifier of owner, usually "$this"
     * @return boolean|null  true=success, error(empty)=null, error(not owner)=false
     */
    public function StackerPop($Owner) {

        // exit if stack is empty
        if (empty($this->StackerStack)) {
            return null;
        }

        // only owner can remove item from stack
        if (end($this->StackerStack)[1] <> $Owner) {
            return false;
        }

        // remove last element from stack
        array_pop($this->StackerStack);
        return true;
    }


    /**
     * Append cloned version of last object in stack.
     *
     * @param mixed $Owner  unique identifier of owner, usually "$this"
     * @return false|self
     */
    public function StackerClone($Owner) {

        // exit if stack is empty
        if (empty($this->StackerStack)) {
            return false;
        }

        // fetch current object
        $LastObj= $this->StackerGet();

        // push cloned object into stack
        $this->StackerPush(clone $LastObj, $Owner);
        return $this;
    }


    /**
     * Magic method: execute method of current object in stack.
     */
    public function __call($Method, $Args) {

        $Obj= $this->StackerGet();
        if (!$Obj || !method_exists($Obj, $Method)) {
            // this class is not descendant of Component so Error() is not available
            return null;
        }
        return call_user_func_array([$Obj, $Method], $Args);
    }


    /**
     * Magic method: retrieve property from current object in stack.
     */
    public function __get($Name) {

        return empty($this->StackerStack)
            ? null
            : $this->StackerGet()->$Name;
    }


    /**
     * Magic method: send specified value to current object in stack.
     */
    public function __set($Name, $Value) {

        if (!empty($this->StackerStack)) {
            $this->StackerGet()->$Name= $Value;
        }
    }

}

