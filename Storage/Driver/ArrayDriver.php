<?php namespace Accent\Storage\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


/**
 * Storage driver for storing data in PHP memory.
 *
 * Warning 1: this is not permanent storage, it can be used only within current PHP request.
 *
 * Warning 2: storing large data using this driver can cause "Out of memory" fatal error.
 *
 * Memory storage always store meta data with values.
 */



class ArrayDriver extends AbstractDriver {

    // predefined options
    protected static $DefaultOptions= array(

        // TTL (time to live) in seconds or false for unlimited
        'Expire' => false,
    );

    // JSON format supports arrays
    protected $CapabilityMultiValue= true;

    // internal properties

    // buffer to store values
    protected $Storage= array();

    // buffer to store tags
    protected $Tags= array();


    public function Exist($Key, $Validate=true) {

        // existance check
        if (!isset($this->Storage[$Key])) {
            return false;
        }
        if (!$Validate) {
            return true;
        }
        $Entry= $this->Storage[$Key];
        $Expire= $this->GetOption('Expire');
        if ($Expire !== null && intval($Entry['Timestamp'])+$Expire < time()) {
            return false; // entry is expired
        }
        if (!$this->ValidateTags($Entry['Tags'], $Entry['Timestamp'])) {
            return false; // some of tags has newer timestamp
        }
        return true;
    }


    public function Read($Key) {

        // existance check
        if (!isset($this->Storage[$Key])) {
            return null;
        }
        $Entry= $this->Storage[$Key];
        $Expire= $this->GetOption('Expire');
        $Expired= $Expire != null && $Entry['Timestamp']+$Expire < $this->GetMicrotime();
        if ($Expired || !$this->ValidateTags($Entry['Tags'],$Entry['Timestamp'])) {
            unset($this->Storage[$Key]);
            return null;
        }
        // return value
        return $Entry['Value'];
    }


    public function ReadAll() {

        $Expire= $this->GetOption('Expire');
        $Oldest= $this->GetMicrotime() - $Expire;
        $Result= array();
        foreach ($this->Storage as $Key => $Entry) {
            $Expired= $Expire != null && $Entry['Timestamp'] < $Oldest;
            if ($Expired || !$this->ValidateTags($Entry['Tags'],$Entry['Timestamp'])) {
                unset($this->Storage[$Key]);
            } else {
                $Result[$Key]= $Entry['Value'];
            }
        }
        return $Result;
    }


    public function Write($Key, $Value, $Tags) {

        $this->WriteAll(array($Key=>$Value), $Tags, false);
        return true;
    }


    public function WriteAll($Values, $CommonTags, $OverwriteWholeStorage) {

        if (is_string($CommonTags)) {
            $CommonTags= array($CommonTags);
        }
        if ($OverwriteWholeStorage) {
            $this->Storage= array();
            $this->Tags= array();
        }
        $Timestamp= $this->GetMicrotime();
        foreach ($Values as $Key => $Value) {
            $this->Storage[$Key]= array(
                'Timestamp'=> $Timestamp,
                'Tags'=> $CommonTags,
                'Value'=> $Value,
            );
        }
        $this->Tags= array_fill_keys($CommonTags, $Timestamp) + $this->Tags;
        return true;
    }


    protected function ValidateTags($Tags, $Timestamp) {
        // check is any of tags has newer timestamp
        if (empty($Tags)) {
            return true;
        }
        foreach(array_unique($Tags) as $Tag) {
            if (!isset($this->Tags[$Tag]) || $this->Tags[$Tag] > $Timestamp) {
                return false;
            }
        }
        return true;
    }


    public function Delete($Key) {

        unset($this->Storage[$Key]);
        return true;
    }


    public function Clear($Tags) {
        // special case
        if($Tags === '*') {
            $this->Storage= $this->Tags= array();
            return;
        }
        // ensure array type
        if(is_string($Tags)) {
            $Tags= array($Tags);
        }
        // delete tags
        foreach($Tags as $Tag) {
            unset($this->Tags[$Tag]);
        }
    }


    public function GarbageCollection() {

        $Timeout= $this->GetMicrotime() - (float)$this->GetOption('Expire');
        foreach($this->Storage as $Key=>$Pack) {
            if (intval($Pack['Timestamp']) < $Timeout) {
                unset($this->Storage[$Key]);
            }
        }
    }


    protected function GetMicrotime($Offset=0) {

        return microtime(true)+$Offset;
    }

}

?>