<?php namespace Accent\Storage\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


/**
 * Storage driver for storing data in external Memcached service.
 *
 *
 */



class MemcachedDriver extends AbstractDriver {


    // predefined options
    protected static $DefaultOptions= array(

        // short string which must be unique to prevent key collision if Memcached serve multiple applications
        'Prefix'=> '',

        // TTL (time to live), default on 30 days
        'Expire' => 2600000,

        // array of servers and ports that run the memcached service
        'Servers'=> array(
            array('Host' => '127.0.0.1', 'Port' => 11211, 'Weight' => 100),
        ),
    );

    // instance of Memcached (object)
    protected $Memcached;


    protected function GetMemcached($Silent=false) {

        // create and init Memcached object on first call
        if ($this->Memcached === null) {

            // check existance of memcache extension
            if (!class_exists('Memcached', false)) {
                if (!$Silent) {
                    $this->Error('AccentCore/Storage/Memcached: memcached extension not installed.');
                }
                $this->Memcached= false;
                return false;
            }

            // create instance of \Memcached class
            $this->Memcached = new \Memcached();

            // configure all servers
            foreach ($this->GetOption('Servers') as $Server) {
                $this->Memcached->AddServer($Server['Host'], $Server['Port'], $Server['Weight']);
            }

            // validate servers
            $Versions= $this->Memcached->GetVersion();
            if (!is_array($Versions)) {
                if (!$Silent) {
                    $this->Error("AccentCore/Storage/Memcached: no servers added.");
                }
                $this->Memcached= false;
            } else if (in_array('255.255.255', $Versions) && count(array_unique($Versions)) === 1) {
                if (!$Silent) {
                    $this->Error("AccentCore/Storage/Memcached: cannot establish connection.");
                }
                $this->Memcached= false;
            }
        }
        // return object
        return $this->Memcached;
    }


    public function StorageExist() {

        return $this->GetMemcached(true) !== false;
    }


    public function Exist($Key, $Validate=true) {

        // validate connection
        if (!$this->GetMemcached()) {
            return false;
        }

        // fetch from Memcached extension
        $Record= $this->Memcached->Get($this->GetKey($Key));

        // record not found?
        if ($this->Memcached->GetResultCode() !== 0) {
            return false;
        }

        // return true or result of validation
        return $Validate
            ? $this->ValidateTags($Record['Tags'], $Record['Timestamp'])
            : true;
    }


    public function Read($Key) {

        // validate connection
        if (!$this->GetMemcached()) {
            return false;
        }

        // fetch from Memcached extension
        $Record= $this->Memcached->Get($this->GetKey($Key));

        // record not found or not valid?
        if ($this->Memcached->GetResultCode() !== 0 || $this->ValidateTags($Record['Tags'], $Record['Timestamp']) === false)  {
            return false;
        }

        // return stored value
        return $Record['Data'];
    }


    public function Write($Key, $Value, $Tags) {

        // validate connection
        if (!$this->GetMemcached()) {
            return false;
        }

        if (is_string($Tags)) {
            $Tags= array($Tags);
        }
        $Timestamp= $this->GetMicrotime();  // as string
        $Pack= array(
            'Timestamp'=> $Timestamp,
            'Tags'=> $Tags,
            'Data'=> $Value,
        );

        // send record to Memcached extension
        $this->Memcached->Set($this->GetKey($Key), $Pack, time()+$this->GetOption('Expire'));

        // update tags in TagRegistry if needed
        if (!empty($Tags)) {
            $this->UpdateTags($Tags, $Timestamp);
        }
    }


    protected function ValidateTags($Tags, $Timestamp) {

        // check is any of tags has newer timestamp
        if (empty($Tags)) {
            return true;
        }

        // fetch TagRegistry from Memcached
        $TagRegistry= $this->Memcached->Get($this->GetKeyTagRegistry());

        // check timestamp of each tag
        // tag must exist and timestamp from registry must not be newer then timestamp of the record
        foreach(array_unique($Tags) as $Tag) {
            if (!isset($TagRegistry[$Tag]) || $TagRegistry[$Tag] > $Timestamp) {
                return false;
            }
        }

        // otherwise return true
        return true;
    }


    public function Clear($Tags) {

        if (!$this->GetMemcached()) {
            return;
        }

        // special case
        if($Tags === '*') {
            // clear registry, we don't care about race-condition
            $this->Memcached->Set($this->GetKeyTagRegistry(), array(), 0); // permanent TTL
            return;
        }
        // ensure array type
        if(is_string($Tags)) {
            $Tags= array($Tags);
        }

        // try updating in loop, to avoid race-condition
        do {
            // fetch TagRegistry from Memcached
            $TagRegistry = $this->Memcached->Get($this->GetKeyTagRegistry(), null, $CAS);

            // delete tags instead of updating them, this makes further readings faster and GC unnecessary
            foreach ($Tags as $Tag) {
                unset($TagRegistry[$Tag]);
            }

            // store updated registry
            $Success = $this->Memcached->CAS($CAS, $this->GetKeyTagRegistry(), $TagRegistry); // permanent TTL

        } while (!$Success && $this->Memcached->GetResultCode() === \Memcached::RES_DATA_EXISTS);
    }


    public function GarbageCollection() {

       //...   nothing to do :)
    }


    protected function GetKey($Key) {

        // TODO: decide should keys need to be hashed or to left as is for easier debugging ?
        return $this->GetOption('Prefix').'.'.$Key;
    }


    protected function GetKeyTagRegistry() {

        return $this->GetOption('Prefix').'_TAG-REGISTRY';
    }


    protected function UpdateTags($Tags, $Timestamp) {

        $UpdatedTags= array();
        foreach(array_unique($Tags) as $Tag) {
            $UpdatedTags[$Tag]= $Timestamp;
        }

        // try updating in loop, to avoid race-condition
        do {
            // fetch TagRegistry from Memcached
            $TagRegistry= $this->Memcached->Get($this->GetKeyTagRegistry(), null, $CAS);

            // append (or overwrite if exist) new tags to registry
            $TagRegistry= $UpdatedTags + $TagRegistry;

            // store updated registry
            $Success = $this->Memcached->CAS($CAS, $this->GetKeyTagRegistry(), $TagRegistry); // permanent TTL

        } while (!$Success && $this->Memcached->GetResultCode() === \Memcached::RES_DATA_EXISTS);
    }


    protected function GetMicrotime($Offset=0) {

        return sprintf("%01.6f", microtime(true) + $Offset);
        //list($usec, $sec) = explode(" ", microtime());
        //return $sec.':'.substr($usec,2,6);
    }


    /**
     * Debug helper.
     */
    public function GetRegistry() {

        return $this->GetMemcached() ? $this->Memcached->Get($this->GetKeyTagRegistry()) : false;
    }
}

?>