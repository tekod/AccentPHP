<?php namespace Accent\Config;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */



/**
 * @TODO: napraviti varijantu (i testirati) sa multi-storage pristupom.
 */


use Accent\AccentCore\Component;
use Accent\Storage\Storage;


class Config extends Component {


    protected static $DefaultOptions= array(

        // name of storage engine or already instantiated storage object
        'Storage'=> 'php',

        // full path to config file, only that file will be loaded
        // (unused for 'memory' or 'db' storage)
        'File'=> '',

        // full path to directory, all files will be loaded
        // (unused for 'memory' or 'db' storage)
        'Dir'=> '',

        // flag to forbid writing changes to storage
        'ReadOnly'=> false,

        // version of component
        'Version'=> '1.0.0',

        // there is much more available options, depending on chosen storage engine
        // to find all available options check header of each particular driver
    );

    // internal properties

    // buffer for values
    protected $ConfData= array();

    // instance of Storage object
    protected $Storage;

    // list of ConfData keys affected by Set method
    // this will be used in Store method to avoid storing unmodified values
    protected $Modified= array();


    /*
     * Constructor.
     */
    public function __construct($Options) {

        // call parent
        parent::__construct($Options);

        // create storage object
        $this->CreateStorage();
    }


    protected function CreateStorage() {

        $Storage= $this->GetOption('Storage');
        $File= $this->GetOption('File');
        $Dir= $this->GetOption('Dir');
        // if passed as string build storage object
        if (is_string($Storage)) {
            // in case of error storage constructor will dispatch error message
            // and initialized itself with "NoneDriver" driver
            $Storage= new Storage(array(
                'Driver'=> $Storage,
                'Path'=> $Dir === '' ? $File : $Dir,
                'Mode'=> $Dir === '' ? 'Compact' : 'Distributed',
            ) + $this->GetAllOptions(array('Dir','File')));
        }
        $this->Storage= $Storage;

        // status of this object depends on status of storage object
        $this->Initied= $this->Storage->IsInitied();
    }


    /**
     * Reading configured values.
     *
     * @param string $Name
     * @param mixed $DefaultValue
     * @return mixed
     */
    public function Get($Name, $DefaultValue=null) {

        if (isset($this->ConfData[$Name])) {
            return $this->ConfData[$Name];
        }
        // not found
        return $DefaultValue;
    }


    /**
     * Return all values from buffer.
     *
     * @return array
     */
    public function GetAll() {

        return $this->ConfData;
    }


    /**
     * Setting value to buffer but not write is to storage.
     * This will overwrite already existing values.
     *
     * @param string $Name name of value
     * @param mixed $Value
     * @return self
     */
    public function Set($Name, $Value) {

        $this->ConfData[$Name] = $Value;
        $this->Modified[$Name]= 1;
        return $this;
    }


    /**
     * Setting multiple values using array of key=>value pairs to buffer
     * without writing to storage.
     * This will overwrite already existing values.
     * Other values in buffer will be preserved.
     *
     * @param array $ArrayOfValues
     * @return self
     */
    public function SetAll($ArrayOfValues) {

        $this->ConfData= $ArrayOfValues + $this->ConfData;
        $this->Modified += array_fill_keys(array_keys($ArrayOfValues), 1);
        return $this;
    }


    /**
     * Remove value from list.
     *
     * @param string $Name name of value
     * @return self
     */
    public function Delete($Name) {

        unset($this->ConfData[$Name]);
        $this->Modified[$Name]= 1;  // also marked, can be used for deletion from database
        return $this;
    }


    /**
     * Remove all data from buffer.
     *
     * @return self
     */
    public function ClearAll() {

        $this->ConfData= array();
        $this->Modified= array();
        return $this;
    }


    /**
     * Magic __get() method.
     *
     * @param string $Name
     * @return mixed
     */
    public function __get($Name) {
        // you cannot pass default value using this method
        return $this->Get($Name);
    }


    /**
     * Magic __set() method.
     *
     * @param string $Name
     * @param mixed $Value
     */
    public function __set($Name, $Value) {

        $this->Set($Name, $Value);
    }


    /**
     * Load values from storage driver to internal buffer.
     * This will clear previuos content of buffer.
     *
     * @return self
     */
    public function Load() {

        $this->ConfData= $this->Storage->ReadAll();
        $this->Modified= array();
        return $this;
    }


    /**
     * Send values from buffer to storage.
     *
     * @return boolean
     */
    public function Store() {

        if ($this->GetOption('ReadOnly')) {
            $this->Error('Config/Store: cannot write to read-only storage.');
            return;
        }
        if (!$this->Storage) {
            $this->Error('Config/Store: Driver not initiated.');
            return false;
        }
        $ModifiedData= array_intersect_key($this->ConfData, $this->Modified);
        return $this->Storage->WriteAll($ModifiedData, array(), false);
    }



}

?>