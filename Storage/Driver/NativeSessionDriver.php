<?php namespace Accent\Storage\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


/**
 * Storage driver for storing data in PHP's native session storage.
 *
 * Note that:
 *  - avaliable space in session is very limited
 *  - persistance is not guarantied
 */



class NativeSessionDriver extends AbstractDriver {

    // predefined options
    protected static $DefaultOptions= array(

        // name of section (key of $_SESSION root)
        'Section'=> '',

        // TTL (time to live) in seconds or false for unlimited
        'Expire' => false,
    );

    // JSON format supports arrays
    protected $CapabilityMultiValue= true;

    // internal properties
    protected $SessionStarted= false;
    protected $StorageRef;
    protected $TagsRef;



    public function __construct($Options) {

        // call parent
        parent::__construct($Options);

        // sessions are not available in console mode
        if (PHP_SAPI === 'cli' ) {
            $this->FatalError("Storage/NativeSessionDriver: PHP session does not work in CLI.");
        }
    }


    public function Exist($Key, $Validate=true) {

        $this->StartSession();

        // existance check
        if (!isset($this->StorageRef[$Key])) {
            return false;
        }
        if (!$Validate) {
            return true;
        }
        $Entry= $this->StorageRef[$Key];
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

        $this->StartSession();

        // existance check
        if (!isset($this->StorageRef[$Key])) {
            return null;
        }
        $Entry= $this->StorageRef[$Key];
        $Expire= $this->GetOption('Expire');
        $Expired= $Expire != null && $Entry['Timestamp']+$Expire < $this->GetMicrotime();
        if ($Expired || !$this->ValidateTags($Entry['Tags'],$Entry['Timestamp'])) {
            unset($this->StorageRef[$Key]);
            return null;
        }
        // return value
        return $Entry['Value'];
    }


    public function ReadAll() {

        $this->StartSession();

        $Expire= $this->GetOption('Expire');
        $Oldest= $this->GetMicrotime() - $Expire;
        $Result= array();
        foreach ($this->StorageRef as $Key => $Entry) {
            $Expired= $Expire != null && $Entry['Timestamp'] < $Oldest;
            if ($Expired || !$this->ValidateTags($Entry['Tags'],$Entry['Timestamp'])) {
                unset($this->StorageRef[$Key]);
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

        $this->StartSession();

        if (is_string($CommonTags)) {
            $CommonTags= array($CommonTags);
        }
        if ($OverwriteWholeStorage) {
            $this->StorageRef= array();
            $this->TagsRef= array();
        }
        $Timestamp= $this->GetMicrotime();
        foreach ($Values as $Key => $Value) {
            $this->StorageRef[$Key]= array(
                'Timestamp'=> $Timestamp,
                'Tags'=> $CommonTags,
                'Value'=> $Value,
            );
        }
        $this->TagsRef= array_fill_keys($CommonTags, $Timestamp) + $this->TagsRef;
        return true;
    }


    protected function ValidateTags($Tags, $Timestamp) {
        // check is any of tags has newer timestamp
        if (empty($Tags)) {
            return true;
        }
        foreach(array_unique($Tags) as $Tag) {
            if (!isset($this->TagsRef[$Tag]) || $this->TagsRef[$Tag] > $Timestamp) {
                return false;
            }
        }
        return true;
    }


    public function Delete($Key) {

        $this->StartSession();

        unset($this->StorageRef[$Key]);
        return true;
    }


    public function Clear($Tags) {

        $this->StartSession();

        // special case
        if($Tags === '*') {
            $this->StorageRef= array();
            $this->TagsRef= array();
            return;
        }
        // ensure array type
        if(is_string($Tags)) {
            $Tags= array($Tags);
        }
        // delete tags
        foreach($Tags as $Tag) {
            unset($this->TagsRef[$Tag]);
        }
    }


    public function GarbageCollection() {

        $this->StartSession();

        $Timeout= $this->GetMicrotime() - (float)$this->GetOption('Expire');
        foreach($this->StorageRef as $Key=>$Pack) {
            if (intval($Pack['Timestamp']) < $Timeout) {
                unset($this->StorageRef[$Key]);
            }
        }
    }


    protected function GetMicrotime($Offset=0) {

        return microtime(true)+$Offset;
    }


    protected function StartSession() {

        if ($this->SessionStarted) {
            return;
        }
        // start PHP session
        if ((function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) || session_id() === '') {
            // try to start
            session_start();
            // validate
            if ((function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) || session_id() === '') {
                $this->FatalError("Storage/NativeSessionDriver: unable to start session.");
            }
        }
        // session is started, prepare storage
        $Section= $this->GetOption('Section');
        if (!isset($_SESSION[$Section]) || !isset($_SESSION[$Section]['Storage'])) {
            $_SESSION[$Section]= array('Storage'=>array(), 'Tags'=>array());
        }
        $this->SessionStarted= true;
        $this->StorageRef= &$_SESSION[$Section]['Storage'];
        $this->TagsRef= &$_SESSION[$Section]['Tags'];
    }


}

?>