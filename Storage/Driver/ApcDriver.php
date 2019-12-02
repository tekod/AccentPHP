<?php namespace Accent\Storage\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Storing data in APC.
 *
 * @TODO: rewrite whole codebase
 *
 *
 */



class CacheApcDriver extends AbstractDriver {


    // buffer to store values
    protected $Storage= array();

    // predefined options
    protected static $DefaultOptions= array(
        'APC_ID' => null, // we need unique identificator of our application
        'Expire' => 2600000, // TTL (time to live), default on 30 days
        'UseIndex'=> true, // maintaining of cache index involves one extra access to APC
                           // per each writing but allows usage of ->Clear('*')
    );

    // internal property
    protected $APC_ID;


    /**
     * Constructor.
     */
    public function __construct($Options) {

        // call ancestor
        parent::__construct($Options);

        // check APC extension
        if (!function_exists('apc_store')) {
            $this->Initied= false;
            $this->Error("APC extension not available.");
            return;
        }

        // find APC ID
        $this->APC_ID= $this->GetOption('APC_ID') === null
            ? $this->GenerateApcId()
            : $this->GetOption('APC_ID');

        // successfully initied
        $this->Initied= true;
    }


    public function Exist($Key, $Validate=true) {
        // existance check
        $ApcKey= $this->GetKey($Key);
        if (!$Validate) {
            // APC internally validates TTL, we cannot avoid that
            return apc_exists($ApcKey);
        }
        $Result= apc_fetch($ApcKey, $Success);
        if ($Result === false || !$Success) {
            return false;
        }
        // check index
        return $this->ValidateTags($Result['Tags'], $Result['Timestamp']);
    }


    protected function GetKey($Key) {
        // adding prefix to user's key
        return $this->APC_ID.'.'.$Key;
    }


    public function Read($Key) {
        // fetch
        $ApcKey= $this->GetKey($Key);
        $Result= apc_fetch($ApcKey, $Success);
        if ($Result === false || !$Success) {
            return null;
        }
        // check index
        if ($this->ValidateTags($Result['Tags'], $Result['Timestamp'])) {
            return $Result['Value'];
        }
        apc_delete($ApcKey);
        return null;
    }


    public function Write($Key, $Value, $Tags) {

        $ApcKey= $this->GetKey($Key);
        $Timestamp= $this->GetMicrotime(); // same time for tags and entry
        $Value= array(
            'Value'    => $Value,
            'Timestamp'=> $Timestamp,
            'Tags'     => $Tags,
        );
        apc_store($ApcKey, $Value, $this->GetOption('Expire'));
        // store tags
        $this->SaveTags($Tags, $Timestamp);
    }


    protected function GetIndex($Index=null) {
        // get list of tags
        if ($Index === null) {
            return ($Dump= apc_fetch($this->APC_ID.'-INDEX'))
                ? @unserialize($Dump)
                : array('Index'=>array(), 'SubIndexes'=>array());
        } else {
            $SubIndexKey= $this->APC_ID.'-TAG:'.$Index;
            return ($Dump= apc_fetch($SubIndexKey))
                ? @unserialize($Dump)
                : array();
        }
    }


    protected function StoreIndex($Content, $Index=null) {
        // save list of tags
        $Key= ($Index === null)
            ? $this->APC_ID.'-INDEX'
            : $this->APC_ID.'-TAG:'.$Index;
        apc_store($Key, serialize($Content), $this->GetOption('Expire'));
    }


    protected function SaveTags($Tags, $Timestamp) {

        if (empty($Tags)) {
            return;
        }
        $Index= $this->GetIndex();
        $IndexChanged= false;
        foreach(array_unique($Tags) as $Tag) {
            $Index['Index'][$Tag]= $Timestamp;
            $IndexChanged= true;
        }
        if ($IndexChanged) {
            $this->StoreIndex($Index);
        }
    }


    protected function ValidateTags($Tags, $Timestamp) {
        // check is any of tags has newer timestamp
        if (empty($Tags)) {
            return true;
        }
        $Index= null;
        foreach(array_unique($Tags) as $Tag) {
            if ($Index === null) {
                $Index= $this->GetIndex();
            }
            if (!isset($Index['Index'][$Tag]) || $Index['Index'][$Tag] > $Timestamp) {
                return false;
            }
        }
        return true;
    }


    public function Clear($Tags) {
        // special case
        if($Tags === '*') {
            apc_clear_cache();
            return;
        }
        // ensure array type
        if (is_string($Tags)) {
            $Tags= array($Tags);
        }
        if (empty($Tags)) {
            return;
        }
        // remove tags from indexes
        $IndexChanged= false;
        $Index= $this->GetIndex();
        foreach(array_unique($Tags) as $Tag) {
            if (!isset($Index['Index'][$Tag])) continue;
            unset($Index['Index'][$Tag]);
            $IndexChanged= true;
            $this->DeleteIndex($Tag);
        }
        if ($IndexChanged) {
            $this->StoreIndex($Index);
        }
    }


    public function GarbageCollection() {
        // APC do garbage collecting internaly
    }


    protected function GenerateApcId() {
        // ID should be sensitive on enviroment changing
        $Hash= $this->GetRequestContext()->SERVER['HTTP_HOST'].'::'.__FILE__.'::'.php_uname();
        return substr(hash('md5',$Hash), 1, 12);
    }


    protected function GetMicrotime($Offset=0) {

        return sprintf("%01.6f", microtime(true) + $Offset);
    }

}

?>