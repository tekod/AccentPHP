<?php namespace Accent\Storage;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Storage component provide several benefits for their consumers:
 *  - storage driver and location can be easily changed without needs to modify consumers,
 *  - allows assigning tags to values to makes posible searching or deleting by tags
 *  - allows defining expiration time for stored values
 */

use Accent\AccentCore\Component;
use Accent\Storage\Driver\NoneDriver;


class Storage extends Component {


    // default options
    protected static $DefaultOptions= array(

        // name of driver (like 'php')
        // or FQCN (like '\Namespace\Folder\Class')
        // or already instantied driver object
        'Driver'=> 'php',

        // data TTL (time to live) in seconds or false for permanent
        'Expire'=> false,

        // version of component
        'Version'=> '1.0.0',
    );

    // internal properties

    protected $Driver;      // instance of storage driver


    /**
     * Constructor
     */
    public function __construct($Options) {

        parent::__construct($Options);

        // instantiate specified driver
        $this->CreateDriver($this->GetOption('Driver'));
    }


    /**
     * Constructor's helper method, instantiating driver object.
     *
     * @param string $Driver
     */
    protected function CreateDriver($Driver) {

        // is it string?
        if (is_string($Driver)) {
            // not FQCN?
            $Class= strpos('\\', $Driver) === false
                ? '\\Accent\\Storage\\Driver\\'.ucfirst($Driver).'Driver'
                : $Driver;
            // create driver object
            if (!class_exists($Class)) {
                $this->FatalError("Storage: driver class '$Class' not found.");
                return;
            }
            $this->Driver= new $Class($this->Options); // forward parent's options
        } else {
            // it must be object
            $this->Driver= $Driver;
        }

        // status of this object depends on status of driver object
        $this->Initied= $this->Driver->IsInitied();

        // fallback to "none" driver if failed to initiate specified driver

        if (!$this->Initied) {
            $this->Driver= new NoneDriver($this->Options);
        }
    }


    /**
     * Returns array with capabilities of configured storage space.
     */
    public function GetCapabilities($CapabilityName=null) {
        // proxy request to driver
        return $this->Driver->GetCapabilities($CapabilityName);
    }


    /**
     * Check existence of storage space.
     * False means that storage probably not exist or is inaccessible so drivers should not try to access it.
     * True means that storage probably exist and drivers can try to load data from it.
     * This is only basic (fast) check which covers most cases, performing full check can degrade performances.
     *
     * @return mixed
     */
    public function StorageExist() {

        return $this->Driver->StorageExist();
    }


    /**
     * Check existence of key in storage.
     * This method will NOT remove key from storage if expired value found.
     *
     * @param string $Key
     * @return bool
     */
    public function Exist($Key) {

        $nKey= $this->NormalizeKey($Key);
        return $nKey === ''
            ? false
            : $this->Driver->Exist($nKey);
    }


    /**
     * Get value from storage.
     * This method will remove expired value from storage.
     *
     * @param string $Key
     * @return null|mixed
     */
    public function Read($Key) {

        $nKey= $this->NormalizeKey($Key);
        return $nKey === ''
            ? null
            : $this->Driver->Read($nKey);
    }


    /**
     * Get all values from storage, packed in key-value array.
     * In case of
     *
     * @return array
     */
    public function ReadAll() {
        // storage is empty by default
        return $this->Driver->ReadAll();
    }


    /**
     * Put value in storage.
     *
     * @param string $Key
     * @param mixed $Value
     * @param array $Tags
     * @return bool
     */
    public function Write($Key, $Value, $Tags=array()) {

        $nKey= $this->NormalizeKey($Key);
        if (!is_array($Tags)) {
            $Tags= array($Tags);
        }
        return $nKey === ''
            ? false
            : $this->Driver->Write($nKey, $Value, $Tags);
    }


    /**
     * Put multiple values in storage, specified as array of key=>value pairs.
     * Return true only if all values are stored successfully.
     *
     * @param array $ArrayOfValues  key=>value pairs to store
     * @param array $CommonTags  list of tags that will be assigned to all keys
     * @param bool $OverwriteWholeStorage  clear all other keys from storage
     */
    public function WriteAll($ArrayOfValues, $CommonTags=array(), $OverwriteWholeStorage=true) {

        $NormalizedArray= array_filter(array_combine(
            array_map(array($this,'NormalizeKey'), array_keys($ArrayOfValues)),
            array_values($ArrayOfValues)
        ));
        $IsReduced= count($NormalizedArray) <> count($ArrayOfValues);
        if (!is_array($CommonTags)) {
            $CommonTags= array($CommonTags);
        }
        return $this->Driver->WriteAll($NormalizedArray, $CommonTags, $OverwriteWholeStorage) && !$IsReduced;
    }


    /**
     * Remove value from storage.
     *
     * @param string $Key
     * @return bool
     */
    public function Delete($Key) {

        $nKey= $this->NormalizeKey($Key);
        return $nKey === ''
            ? false
            : $this->Driver->Delete($nKey);
    }


    /**
     * Removed values with specified $Tags setted from storage.
     * Special case: passing "*" as parameter will remove ALL keys.
     *
     * @param '*'|array $Tags
     */
	public function Clear($Tags) {

        $this->Driver->Clear($Tags);
    }


    /**
     * Checks all values in storage and removes expired ones.
     */
	public function GarbageCollection() {

        $this->Driver->GarbageCollection();
    }


    /**
     * Checks is connection to the storage successfully established.
     * This will establish connection if it was not (lazy) established before.
     *
     * @return boolean
     */
    /*public function TestConnection() {

        return $this->Driver->TestConnection();
    }
*/

    /**
     * Normalization of key string:
     * - ensure string type (for boolean and null typed keys)
     * - in order to preserve efficiency maintain a reasonable length of keys
     *
     * Note that Read & Write methods will reject operations with "" key
     * to avoid cross-linking data in case of inproperly forged keys.
     *
     * @param string $Key
     * @return string
     */
    protected function NormalizeKey($Key) {

        $nKey= (string)$Key;

        return (strlen($nKey) >= 64)
            ? substr($nKey,0,20).'..'.hash('md5',$nKey)
            : $nKey;
    }


    /**
     * Return current driver object, for debugging purposes.
     */
    public function GetDriver() {
        return $this->Driver;
    }

}

?>