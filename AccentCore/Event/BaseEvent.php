<?php namespace Accent\AccentCore\Event;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

use \Accent\AccentCore\Component;


class BaseEvent extends Component {


    // name of current event
    public $EventName;

    // internal properties
    protected $Data;
    private $IsHandled= false;
    //protected $Context= [];


    /**
     * Constructor.
     */
    public function __construct($Options=array()) {

        // call ancestor
        parent::__construct($Options);

        // move all unknown options into $Data buffer
        $this->Data= array_diff_key($this->Options, $this->GetCommonOptions() + parent::$DefaultOptions);
        $this->Options= $this->GetCommonOptions();
    }

    /**
     * Data getter.
     *
     * @param string $Name  key of data
     * @return mixed
     */
    public function GetData($Name) {

        return $this->Data[$Name];
    }


    /**
     * Data getter for all values.
     *
     * @return array
     */
    public function GetAllData() {

        return $this->Data;
    }


    /**
     * Data setter for single value.
     *
     * @param string $Name
     * @param mixed $Value
     */
    public function SetData($Name, $Value) {

        $this->Data[$Name]= $Value;
    }

    /**
     * Data setter for all values.
     *
     * @param array $Data
     */
    public function SetAllData($Data) {

        $this->Data= $Data;
    }


    /**
     * Magic getter.
     */
    public function __get($Name) {

        return $this->GetData($Name);
    }


    /**
     * Magic setter.
     */
    public function __set($Name, $Value) {

        return $this->SetData($Name, $Value);
    }


    /**
     *
     * @return type
     */
    public function IsHandled() {

        return $this->IsHandled;
    }

    public function SetHandled() {

        $this->IsHandled= true;
    }
/*
    public function SetContext(array $Context) {

        $this->Context= $Context;
    }


    public function GetContext($Name=null) {

         return $Name === null
            ? $this->Context
            : (isset($this->Context[$Name]) ? $this->Context[$Name] : null);
    }*/

}

?>