<?php namespace Accent\Config;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */



/**
 * StackedConfig is collection of several independent storages whose data is treated in cascade manner.
 * Order of storages in collection is significant because data from later storage will overlap data from previous.
 *
 * This structure allows application to have one generic storage with all common settings and few optional which will
 * be programmatically chosen and loaded to overlap default settings,
 * for example to database connection settings depending on detected environment.
 *
 * Usage of this class is identical as base Config class (except constructing of course). Although all methods
 * are overwritten descending of Config will ensure that this class can be used as drop-in replacement of Config.
 *
 * Note that storage must be loaded to be able to operational (R/W). Unloaded storages are ignored in all operations.
 * That way accidentally added but unloaded storages will be safe from emptying in Store operation.
 * Storage became loaded by calling method Load of this class.
 * Enabling option 'RebuildMissingStorage' will create empty buffer for all unsuccessfully loaded storages,
 * accept read/write and finally send data to storage. This will effectively create all missing config files.
 *
 *
 */

use Accent\Storage\Storage;


class StackedConfig extends Config {


    protected static $DefaultOptions= array(

        // collection of storages (stack)
        'Storages'=> array(
          // array('Storage'=>'php', 'File'=>'...'), // simply specify constructing options
          // 'db'=> array('Storage'=>'DB',....),     // specify additional storage named as 'db' to be able to remove it if necessary
          // new Storage(array(..options..)),        // put already instantiated object of Storage
        ),

        // ignore fails on storage loading and assume that storage is empty
        'RebuildMissingStorage'=> false,

        // whether to store modified values to last storage in stack or to store them in their origin
        'StoreIntoLastStorage'=> true,

        // remove following options derived from parent class
        '_Storage'=> null,
        '_File'=> null,
        '_Dir'=> null,
    );

    // array of storage objects
    protected $Storages= array();

    // array of stored values, grouped by storages
    protected $StoredData= array();


    /**
     * Return instance of specified storage.
     *
     * @param string|int $StorageName
     * @return \Accent\AccentCore\Storage\Storage
     */
    public function GetStorage($StorageName) {

        return $this->Storages[$StorageName];
    }


    /**
     * Return keys (names) of all storages.
     *
     * @return array
     */
    public function GetStorages() {

        return array_keys($this->Storages);
    }


    /*
     * This method is called from constructor of Config class to initialize storage object.
     * We will override it to initialize our collection.
     */
    protected function CreateStorage() {

        // prepare configured storages, additional storages can be inserted by AddStorage()
        foreach ($this->GetOption('Storages') as $Name=>$Storage) {
            // actual instantiation of storage is delegated to AddStorage to allow Add/Remove management
            $this->AddStorage($Storage, $Name);
        }
    }


    /**
     * Append new storage to stack.
     * Name of storage can be used later for referencing in RemoveStorage() method.
     *
     * @param array|object $Struct  configuration of storage
     * @param string $Name  (optional) name of storage
     * @param bool $Load  weather to load storage or not
     * @return mixed  name (or index) of created storage
     */
    public function AddStorage($Struct, $Name='', $Load=false) {

        // instantiate storage
        if (!is_object($Struct)) {
            $Struct= $this->CreateStackingStorage($Struct);
        }

        // add it to collection
        if ($Name) {
            $this->Storages[$Name]= $Struct;
        } else {
            $this->Storages[]= $Struct;
            $StorageNames= array_keys($this->Storages);
            $Name= end($StorageNames);
        }

        // load from storage
        if ($Load) {
            $this->Storages[$Name]->Load();
        }

        // return index
        return $Name;
    }



    protected function CreateStackingStorage($Struct) {

        $File= isset($Struct['File']) ? $Struct['File'] : '';
        $Dir= isset($Struct['Dir']) ? $Struct['Dir'] : '';
        unset($Struct['File'], $Struct['Dir']);

        // instantiate storage
        $Storage= new Storage(
            array(
                'Driver'=> $Struct['Storage'],
                'Path'=> $Dir === '' ? $File : $Dir,
                'Mode'=> $Dir === '' ? 'Compact' : 'Distributed',
            )
            + $Struct                       // append all unused keys from $Struct
            + $this->GetAllOptions()        // append all options used for component constructor
        );

        // return object of storage
        // in case of error storage constructor will dispatch error message and initialized itself with "NoneDriver" driver
        return $Storage;
    }


    /**
     * Remove specified storage form array of storages.
     * Loaded values will be removed from buffer too.
     *
     * @param string $StorageName  identifier of storage
     * @return mixed
     */
    public function RemoveStorage($StorageName) {

        unset($this->Storages[$StorageName]);
        unset($this->StoredData[$StorageName]);
    }


    /**
     * Reading values from internal buffers. Storages that are not loaded are not present in buffer.
     *
     * @param string $Name
     * @param mixed $DefaultValue
     * @return mixed
     */
    public function Get($Name, $DefaultValue=null) {

        // search in loaded storages, start with default value
        $Value= $DefaultValue;

        // load value from each storage overwriting previous findings
        foreach(array_keys($this->StoredData) as $StorageName) {
            if (isset($this->StoredData[$StorageName][$Name])) {
                $Value = $this->StoredData[$StorageName][$Name];
            }
        }

        // return last value
        return $Value;
    }


    /**
     * Return merged all values from all loaded storages.
     * Using 'smart' merge algorithm.
     *
     * @return array
     */
    public function GetAll() {

        return $this->MergeArrays($this->StoredData);
    }


    /**
     * Return all values from specified storage.
     * Using 'smart' merge algorithm.
     *
     * @param string $StorageName  identifier of storage
     * @return array|false
     */
    public function GetAllFromStorage($StorageName) {

        return isset($this->StoredData[$StorageName])
            ? $this->StoredData[$StorageName]
            : false;
    }


    /**
     * Setting value to local buffer but not write it to storage.
     * After calling Store() method values from local buffer will be transferred to storages.
     *
     * @param string $Name name of value
     * @param mixed $Value
     * @return self
     */
    public function Set($Name, $Value) {

        // find in which storage to write, start with last one
        end($this->StoredData);
        $TargetStorage= key($this->StoredData);

        // if configured to store in origin storage search for last storage where it exists
        // if case that $Name is unknown in all storages store it in last one
        if (!$this->GetOption('StoreIntoLastStorage')) {
            foreach (array_reverse(array_keys($this->StoredData)) as $StorageName) {
                if (isset($this->StoredData[$StorageName][$Name])) {
                    $TargetStorage= $StorageName;
                    break; // found, get out
                }
            }
        }

        // set value
        $this->StoredData[$TargetStorage][$Name]= $Value;
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

        $Storages= array_reverse(array_keys($this->StoredData));
        $LastStorage= reset($Storages);

        if ($this->GetOption('StoreIntoLastStorage')) {
            // write all values into last storage
            $this->StoredData[$LastStorage]= $ArrayOfValues + $this->StoredData[$LastStorage];
        } else {
            // find origin of each key
            foreach ($ArrayOfValues as $Key=>$Value) {
                $TargetStorage= $LastStorage;
                foreach ($Storages as $StorageName) {
                    if (isset($this->StoredData[$StorageName][$Key])) {
                        $TargetStorage= $StorageName;
                        break; // found, get out
                    }
                }
                $this->StoredData[$TargetStorage][$Key]= $Value;
            }
        }

        return $this;
    }


    /**
     * Remove value from storage.
     * This method has somewhat different behavior then Delete() has in Config class, removal of value will be
     * performed only in one storage, last one where found, allowing values from other storages to reappear.
     *
     * @param string $Name name of value
     * @return self
     */
    public function Delete($Name) {

        // remove from last storage
        foreach(array_reverse(array_keys($this->StoredData)) as $StorageName) {
            if (isset($this->StoredData[$StorageName][$Name])) {
                unset($this->StoredData[$StorageName][$Name]);
                break; // found, get out
            }
        }
        return $this;
    }


    /**
     * Remove all data from buffers.
     *
     * @return self
     */
    public function ClearAll() {

        // set all buffers as empty string
        $this->StoredData= array_fill_keys(array_keys($this->StoredData), array());
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
     * Load values from all storages to internal buffers.
     * This will clear previous content of buffers.
     *
     * @param bool $ReloadAlreadyLoaded (optional)  should load from already loaded storages
     * @return self
     */
    public function Load($ReloadAlreadyLoaded=false) {

        foreach ($this->Storages as $Key=>$Storage) {
            // skip loaded
            if (isset($this->StoredData[$Key]) && !$ReloadAlreadyLoaded) {
                continue;
            }
            // perform loading
            if ($this->GetOption('RebuildMissingStorage') || $Storage->StorageExist()) {
                $this->StoredData[$Key]= $Storage->ReadAll();  // ReadAll will always return array
            }
        }
        return $this;
    }


    /**
     * Send values from buffers to storages.
     *
     * @return boolean  success
     */
    public function Store() {

        if ($this->GetOption('ReadOnly')) {
            $this->Error('StackedConfig/Store: cannot write to read-only storages.');
            return;
        }

        $Success= true;
        foreach ($this->StoredData as $Key=>$Data) {
            //$ModifiedData= array_intersect_key($this->StoredData, $this->Modified);
            $Success &= $this->Storages[$Key]->WriteAll($Data, array(), true);
        }

        return $Success;
    }

}

?>