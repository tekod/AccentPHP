<?php namespace Accent\Storage\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Core class for Storage drivers.
 *
 * Drivers are stateless, they do NOT hold stored values.
 * They are used only for reading/writing to
 */

use \Accent\AccentCore\Component;


abstract class AbstractDriver extends Component {


    // predefined options
    protected static $DefaultOptions= array(
        // no options
    );

    // can storage hold array natively (like PhpDriver) or not (like FileDriver)
    protected $CapabilityMultiValue= false;

    // can storage preserve its content between requests
    protected $CapabilityNonVolatile= false;

    // regex list of allowed chars for entity keys
    protected $ValidNameChars= 'A-Za-z0-9~_!&= \|\.\-\+';


    public function StorageExist() {

        return false;
    }


    public function Exist($Key) {

        // storage is empty by default
        return false;
    }


    /**
     * Get value from storage.
     * This method will remove expired value from storage.
     *
     * @param string $Key
     * @return null|mixed
     */
    public function Read($Key) {
        // storage is empty by default
        return null;
    }


    public function ReadAll() {
        // storage is empty by default
        return array();
    }


    public function Write($Key, $Value, $Tags) {
        // typicaly solution - pack value and proxy to WriteAll
        return $this->WriteAll(array($Key=>$Value), $Tags, false);
    }


    public function WriteAll($Values, $CommonTags, $OverwriteWholeStorage) {
        // do nothing
        return true;
    }


    public function Delete($Key) {

        return true;
    }


    /**
     * Removed values with specified $Tags setted from storage.
     * Special case: passing "*" as parameter will remove ALL keys.
     *
     * @param '*'|array $Tags
     */
    public function Clear($Tags) {
        // do nothing
    }


    /**
     * Checks all values in storage and removes expired ones.
     */
    public function GarbageCollection() {
        // do nothing
    }


    public function GetCapabilities($CapabilityName=null) {
        // prepare named list
        $Capabilities= array(
            'MultiValue'=> $this->CapabilityMultiValue,
            'NonVolatile'=> $this->CapabilityNonVolatile,
        );
        // return whole list or single item
        return $CapabilityName === null
            ? $Capabilities
            : $Capabilities[$CapabilityName];
    }


    protected function IsValidKey($Key) {

        // check is there any forbidden char
        preg_replace('/[^'.$this->ValidNameChars.']/', '', $Key, -1, $Count);
        if ($Count > 0) {
            $this->Error('AccentCore/Storage: key "'.$Key.'" contains invalid characters.');
            return false;
        }
        return true;
    }


    protected function GetMicrotime($Offset=0) {

        // returning microtime as float and letting PHP to latter cast it to
        // string during writing will truncate microseconds part on only 4
        // decimals
        // in order to preserve 6 decimals we must return already formated string
        // comparasion of strings instead of floats works well
        return sprintf("%01.6f", microtime(true) + $Offset);
    }


}

?>