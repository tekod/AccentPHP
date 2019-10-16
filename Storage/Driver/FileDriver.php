<?php namespace Accent\Storage\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Driver for storing data in raw files.
 * Content of files are "serialized" value.
 */



class FileDriver extends AbstractDriver {


    protected static $DefaultOptions= array(

        // file extension of file
        'Ext'=> '.dat',

        // storage directory or file (depends of 'Mode' option)
        // must not be shared with other instances
        'Path'=> '',

        // should store all data in single file or distribute each key to its own file
        // descendant classes mostly use compact mode by default but this class
        // is distributed-only because it use pure binary file format
        'Mode'=> 'Distributed',       // 'COMPACT' or 'DISTRIBUTED', case-insensitive

        // TTL (time to live) in seconds or false for unlimited
        'Expire'=> false,

        // apply flock() to avoid race-condition issues (used for compact mode only)
        'LockFile'=> false,

        // how many sub-dirs to create [0..3] (used for distributed mode only)
        'Spread'=> 0,

        // use md5 hash instead of raw key for filename (used for distributed mode only)
        'HashFileName'=> false,

        // should store clean value or wrapped with meta data
        'SaveMetaData'=> false,

        // required services
        'Services'=> array(
            'File'=> 'File',                // 'File' service
            'ArrayUtils' => 'ArrayUtils',   // 'ArrayUtils' service
        ),
    );

    // files are non volatile
    protected $CapabilityNonVolatile= true;

    // this storage cannot store multiple keys in single file
    protected $CanCompactMode= false;

    // internal properties
    protected $IsCompactMode;
    protected $Path;
    protected $UseMeta;
    protected $HashFileName;
    protected $Tags;
    protected $LockFP;
    protected $CreatedTagsSubDir;



    public function __construct($Options) {
        // call parent
        parent::__construct($Options);
        // get mode
        $this->IsCompactMode= strtoupper($this->GetOption('Mode')) === 'COMPACT';
        // get storage location
        $this->Path= rtrim($this->ResolvePath($this->GetOption('Path')), '/');
        // storing meta data is required for utilising tags feature
        $this->UseMeta= $this->GetOption('SaveMetaData');
        // translating filenames into md5 hashes
        $this->HashFileName= $this->GetOption('HashFileName') && !$this->IsCompactMode;
        // prevent disaster of messing with root files
        if (!$this->Path) {
            $this->FatalError('AccentCore/Storage: param "Path" is mandatory!');
            $this->Initied= false;
            return;
        }
        // ensure existance of storage directory
        $Dir= $this->IsCompactMode ? dirname($this->Path) : $this->Path;
        if (!is_dir($Dir)) {
            mkdir($Dir, 0777, true);
        }
    }


    public function StorageExist() {

        return $this->IsCompactMode
            ? is_file($this->Path)     // compact file must exist
            : is_dir($this->Path);     // directory for distributed files must be writable
    }


    public function Exist($Key) {

        // fetch entry
        $Entry= $this->IsCompactMode
            ? $this->GetEntryFromCompactFile($Key, true)
            : $this->GetEntryFromDistribFile($Key, true);
        // return boolean
        return $Entry === false
            ? false
            : true;
    }



    public function Read($Key) {

        // fetch entry
        $Entry= $this->IsCompactMode
            ? $this->GetEntryFromCompactFile($Key, true)
            : $this->GetEntryFromDistribFile($Key, true);
        // not found?
        if ($Entry === false) {
            return null;
        }
        // return value
        return $this->UseMeta
            ? $Entry['Value']
            : $Entry;
    }


    public function ReadAll() {

        // fetch list of all entries
        $Entries= $this->IsCompactMode
            ? $this->GetAllEntriesFromCompactFile(true)
            : $this->GetAllEntriesFromDistribFiles(true);
        // silently return empty array in case of invalid storage
        if (!is_array($Entries)) {
            return array();
        }
        // return values of entries
        return $this->UseMeta
            ? array_map($Entries, function(&$v){$v=$v['Value'];})
            : $Entries;
    }


    public function WriteAll($Values, $CommonTags, $OverwriteWholeStorage) {

        // first param must be an array
        if (!is_array($Values)) {
            $this->Error('Storage/FileDriver: WriteAll accept only arrays.');
            return false;
        }
        // convert tags given as string to array
        $Tags= is_string($CommonTags) ? array($CommonTags) : $CommonTags;
        // call specialized writer
        return $this->IsCompactMode
            ? $this->WriteToCompactFile($Values, $Tags, $OverwriteWholeStorage)
            : $this->WriteToDistribFiles($Values, $Tags, $OverwriteWholeStorage);
    }


    public function Delete($Key) {

        if ($this->IsCompactMode) {
            $Struct= $this->LoadCompactFile(true);
            unset($Struct['Keys'][$Key]);
            return $this->SaveCompactFile($Struct);
        } else {
            $Path= $this->GetFilePath($Key);
            return @unlink($Path);
        }
    }


    public function Clear($Tags) {

        $FileService= $this->GetService('File');
        // check are we using tags feature
        if (!$this->UseMeta) {
            return;
        }
        // special case
        if($Tags === '*') {
            // remove all files/entries
            // note: in previous versions storage driver was just mark all entries
            // as obsolete but becouse this command is used very rarely so it is
            // safe to perform actual deletion of files
            // that way we got performance boost for reading tasks
            if ($this->IsCompactMode) {
                // save empty storage file
                $this->SaveCompactFile(array());
            } else {
                // use file-service to perform recursive deletion
                $FileService->DirectoryClear($this->Path);
                // clear marker about "Tags" subdir
                $this->CreatedTagsSubDir= false;
            }
            return;
        }
        // delete keys by tags
        if (is_string($Tags)) {
            // ensure array type
            $Tags= array($Tags);
        }
        if ($this->IsCompactMode) {
            // becouse there is only one file it is safe to remove all tagged entries
            // from storage instead of just mark them obsolete using 'Tags' buffer
            $Struct= $this->LoadCompactFile(true);
            // in loops remove tags & values
            foreach($Tags as $Tag) {
                unset($Struct['Tags'][$Tag]);
            }
            foreach ($Struct['Keys'] as $Key => $Value) {
                if (!empty(array_intersect($Tags, $Value['Tags']))) {
                    unset($Struct['Keys'][$Key]);
                }
            }
            // store updated structure
            $this->SaveCompactFile($Struct);
        } else {
            // simply delete tag-files, that will invalidate all depending values
            // however storing newer timestamp will produce same effect but this is faster
            foreach($Tags as $Tag) {
                $Path= $this->Path.'/Tags/'.$this->ValidFileName($Tag).'.tag';
                $FileService->DeleteFile($Path);
            }
        }
    }


    public function GarbageCollection() {

        // check are we specify expiriation
        $Expire= $this->GetOption('Expire');
        if ($Expire === false) {
            return;
        }
        // yes, perform garbage collection
        if ($this->IsCompactMode) {
            // without meta data validation is not possible
            if (!$this->UseMeta) {
                return;
            }
            $Struct= $this->LoadCompactFile(true);
            $TagsUsed= array();
            // for every entry check each assigned tag
            foreach ($Struct['Keys'] as $Key => $Entry) {
                foreach ($Entry['Tags'] as $Tag) {
                    if (!isset($Struct['Tags'][$Tag]) || $Entry['Timestamp'] < $Struct['Tags'][$Tag]) {
                        unset($Struct['Keys'][$Key]);
                        continue 2;
                    }
                }
                $TagsUsed += array_flip($Entry['Tags']);
            }
            // remove unused tags
            $Struct['Tags']= array_intersect_key($Struct['Tags'], $TagsUsed);
            // store updated structure
            $this->SaveCompactFile($Struct);
        } else {
            // use File:DirectoryClear with OlderThen parameter
            $this->GetService('File')->DirectoryClear($this->Path, time() - $Expire);
            // there is no checking for $this->UseMeta becouse we using filesystem timestamps
        }
    }


    protected function GetEntryFromDistribFile($Key, $Validate) {

        // find file path for specified key
        $Path= $this->GetFilePath($Key);
        // check file existance
        if (!is_readable($Path)) {
            return false;
        }
        // validate TTL
        if ($this->IsFileExpired($Path)) {
            return false;
        }
        // load file
        $Dump= $this->LoadFile($Path);
        // decode entry
        $Entry= $this->UnpackEntry($Dump);
        if ($Entry === false) {
            return false;
        }
        // perform validation of timestamp and tags
        if ($Validate && $this->UseMeta && !$this->IsValidEntry($Entry)) {
            @unlink($Path); // remove file, it is not so expensive task
            return false;
        }
        // return entry, with or without meta data
        return $Entry;
    }


    protected function GetEntryFromCompactFile($Key, $Validate) {

        // load whole file but do not waste time to validate entries
        $Entries= $this->GetAllEntriesFromCompactFile(false);
        // return false on invalid format of file, without throwing an error
        if (!is_array($Entries)) {
            return false;
        }
        // validation is not possible without meta data, return value of entry
        if (!$this->UseMeta) {
            return isset($Entries[$Key])
                ? $Entries[$Key]
                : false;
        }
        // check existance of specified key
        if (!isset($Entries[$Key])) {
            return false;
        }
        // perform validation of timestamp and tags
        if ($Validate && !$this->IsValidEntry($Entries[$Key])) {
            return false;
        }
        // return requested entry
        return $Entries[$Key];
    }


    protected function GetAllEntriesFromCompactFile($Validate) {

        // reset tag buffer
        $this->Tags= array();
        // load storge file
        $Struct= $this->LoadCompactFile(false);
        // without meta data no validation is possible, return raw structure
        if (!$this->UseMeta) {
            return $Struct;
        }
        // storage with meta data must have "Keys" branch
        if (!isset($Struct['Keys'])) {
            return false;
        }
        // cache "Tags" array, it will be used by IsValidEntry()
        $this->Tags= $Struct['Tags'];
        // perform validation of all entries
        // don't waste time on removing invalid entries from file, delegate it to GC
        if ($Validate) {
            $Struct['Keys']= array_filter($Struct['Keys'], array($this, 'IsValidEntry'));
        }
        // return array of entries
        return $Struct['Keys'];
    }


    protected function GetAllEntriesFromDistribFiles($Validate) {

        // find all keys
        $List= $this->GetAllKeys();
        // load each file and pack in output array
        $Result= array();
        foreach ($List as $Key) {
            $Entry= $this->GetEntryFromDistribFile($Key, false);
            // validate entries and pack them by keys
            if (!$this->UseMeta || !$Validate || $this->IsValidEntry($Entry)) {
                $Result[$Key]= $Entry;
            }
        }
        // return output buffer
        return $Result;
    }


    protected function WriteToDistribFiles($Values, $Tags, $OverwriteWholeStorage) {

        // using optimistic writing strategy, meaning that process will try
        // to store each file regardles of previous errors
        // returned boolean will inform are all writes was successfull or not
        $Success= true;
        // get current timestamp
        $Timestamp= $this->GetMicrotime();
        // store tags first to prevent race-condition
        if (!empty($Tags)) {
            foreach($Tags as $Tag) {
                $Path= $this->Path.'/Tags/'.$this->ValidFileName($Tag).'.tag';
                // try to create "Tags" subdir, but only once to improve performance
                if (!$this->CreatedTagsSubDir) {
                    $this->CreatedTagsSubDir= true;
                    @mkdir(dirname($Path), 0777, true);
                }
                $Success= $Success && file_put_contents($Path, $Timestamp) !== false;
            }
        }
        // remove all other keys
        if ($OverwriteWholeStorage) {
            $Remove= array_diff($this->GetAllKeys(), array_keys($Values));
            foreach ($Remove as $Key) {
                $this->Delete($Key);
            }
        }
        // now save values
        foreach ($Values as $Key => $Value) {
            // get file path
            $Path= $this->GetFilePath($Key);
            // skip invalid key
            if (!$Path) {
                $Success= false;
                continue;
            }
            // prepare payload
            $Entry= $this->UseMeta
                ? array('Value'=>$Value, 'Timestamp'=>$Timestamp, 'Tags'=>$Tags)
                : $Value;
            $Payload= $this->PackEntry($Entry);
            // store valid payload to file
            if (!$Payload || !$this->SaveFile($Path, $Payload)) {
                $Success= false;
            }
        }
        return $Success;
    }


    protected function WriteToCompactFile($Values, $Tags, $OverwriteWholeStorage) {

        // get current timestamp
        $Timestamp= $this->GetMicrotime();
        // fetch storage file
        $Struct= $OverwriteWholeStorage
            ? ($this->UseMeta ? array('Tags'=>array(),'Keys'=>array()) : array())
            : $this->LoadCompactFile(true);
        // without meta data compact file became simple one-dimensional array
        if (!$this->UseMeta) {
            $Struct= $Values + $Struct;
        } else {
            // set tags
            foreach($Tags as $Tag) {
                $Struct['Tags'][$Tag]= $Timestamp;
            }
            // set values
            foreach ($Values as $Key => $Value) {
                $Struct['Keys'][$Key]= array('Value'=>$Value, 'Timestamp'=>$Timestamp, 'Tags'=>$Tags);
            }
        }
        // store structure to file
        return $this->SaveCompactFile($Struct);
    }


    /**
     * Compare timestamps of tags against timestamp of entry.
     * This method is called only if $this->UseMeta enabled.
     */
    protected function IsValidEntry($Entry) {

        // obviously invalid entry
        if (!is_array($Entry)) {
            return false;
        }
        // check is any of tags has newer timestamp
        foreach($Entry['Tags'] as $Tag) {
            if ($this->IsCompactMode) {
                $FT= isset($this->Tags[$Tag]) && $this->Tags[$Tag] !== false
                    ? $this->Tags[$Tag]
                    : false;
            } else {
                $Path= $this->Path.'/Tags/'.$this->ValidFileName($Tag).'.tag';
                $FT= @file_get_contents($Path);
            }
            if ($FT === false || $FT > $Entry['Timestamp']) {
                return false; // tag not found or newer entry
            }
        }
        return true;
    }


    private function GetFilePath($Key) {
        // calculate FileName for this Key
        $Dir= $this->Path;
        $Key= $this->ValidFileName($Key);
        $Spread= $this->GetOption('Spread');
        if ($Key === false) {
            return false;
        }
        if ($this->HashFileName) {
            $Key= md5($Key);
        }
        $Pool= str_repeat($Key, 6); // ensure long enough pool
        for($i=0; $i < $Spread; ++$i) {
            $Prefix= substr($Pool, $i << 1, 2);
            if ($Prefix !== false) {
                $Dir .= "/$Prefix";
            }
        }
        return "$Dir/$Key".$this->GetOption('Ext');
    }


    private function GetAllKeys() {

        if ($this->IsCompactMode) {
            // load compact file
            $Struct= $this->LoadCompactFile(false);
            return array_keys($this->UseMeta ? $Struct['Keys'] : $Struct) ;
        }
        // use file-service to perform recursive reading of storage directory
        $List= $this->GetService('File')->ReadDirectoryRecursive($this->Path, array(
            'AllowDirs'=> false,
            'Mask'=> '*'.$this->GetOption('Ext'),
        ));
        // extract base filenames from results
        $Result= array();
        foreach ($List as $Item) {
            $Result[]= pathinfo($Item, PATHINFO_FILENAME);
        }
        return $Result;
    }


    /**
     * Validate key.
     * Key must contain only allowed characters and must be shorter then 64 bytes.
     * Method will return unmodified key if it pass validation or false otherwise.
     *
     * @param string $Key
     * @return false|string
     */
    private function ValidFileName($Key) {

        if ($this->HashFileName) {
            // there is no need to validate keys if configured to hash filenames
            return (string)$Key;
        }
        // check each character and length
        return $this->IsValidKey($Key) && strlen($Key) < 64
            ? $Key
            : false;
    }


    protected function IsFileExpired($Path, $TimeOut=null) {
        // check is expiriation specified
        $Expire= $this->GetOption('Expire');
        if ($Expire === false) {
            return false;
        }
        // calc $TimeOut
        if ($TimeOut === null) {
            $TimeOut= time() - $Expire;
        }
        // compare with file modification time
        // both 'time' and 'filemtime' returns GMT timestamps, unaffected by timezone
        return filemtime($Path) < $TimeOut;
    }


    protected function LoadCompactFile($UseLock=false) {

        // load file using current packing format
        // apply locking of file if needed
        if ($UseLock && $this->GetOption('LockFile')) {
            $this->LockFP= @fopen($this->Path, 'a+');
            if (!$this->LockFP) {
                return false;
            }
            flock($this->LockFP, LOCK_EX);
        }
        // load file
        $Dump= $this->LoadFile($this->Path);
        // convert flat string to structure, using same unpacker for entries
        $Struct= $this->UnpackCompactStruct($Dump);
        // correct invalid structure
        if (!is_array($Struct)) {
            $Struct= array();
        }
        if ($this->UseMeta) {
            $Struct += array('Tags'=>array(), 'Keys'=>array());
        }
        // return valid structure
        return $Struct;
    }


    /**
     * Store structure of compact file to disk.
     *
     * @param array $Struct
     * @return boolean
     */
    protected function SaveCompactFile($Struct) {

        // pack data
        $Dump= $this->PackCompactStruct($Struct);
        // save file
        if ($this->LockFP) {
            $Success=
                ftruncate($this->LockFP, 0)
                && fputs($this->LockFP, $Dump)
                && flock($this->LockFP, LOCK_UN);
            fclose($this->LockFP);
            $this->LockFP= false;   // clear
            return $Success;
        }
        // not using locking
        return file_put_contents($this->Path, $Dump) !== false;
    }


    /**
     * Fetch content from filesystem.
     * This method is used for both, individual entries and compact file.
     */
    protected function LoadFile($FilePath) {

        return @file_get_contents($FilePath);
    }


    /**
     * Decode dump from file to normal entry.
     * FileDriver utilizate "serialize()" to stringify array structure.
     *
     * @param string $Dump
     * @return mixed
     */
    protected function UnpackEntry($Dump) {

        // return entry
        return $this->UseMeta
            ? @unserialize($Dump)
            : $Dump;
    }


    /**
     * Decode dump of compact file to normal structure.
     * No validation applied on resulted array.
     *
     * @param string $Dump
     * @return array
     */
    protected function UnpackCompactStruct($Dump) {

        return @unserialize($Dump);
    }


    /**
     * Store content to file.
     *
     * @param string $FilePath
     * @param string $Dump
     * @return boolean
     */
    protected function SaveFile($FilePath, $Dump) {

        // ensure that directory exist
        // note that for spreaded paths we need to recursive create directories
        @mkdir(dirname($FilePath), 0777, true);
        // save file
        return file_put_contents($FilePath, $Dump) !== false;
    }


    /**
     * Convert entry to string that can be written to file.
     * Descended classes will probably override this method.
     *
     * @param mixed $Entry  data that need to be written
     * @return string
     */
    protected function PackEntry($Entry) {

        // FileDriver using simple "serialize()" to stringify array structures

        // only simple variable type can be stored in raw file dump
        if (!$this->CapabilityMultiValue && !$this->UseMeta && !is_scalar($Entry)) {
            $this->Error('Storage/FileDriver: only scalar values can be stored in raw file format.');
            // convert entry to something safe and readable for debugging
            $Entry= json_encode($Entry, JSON_UNESCAPED_UNICODE);
        }
        // prepare payload
        return $this->UseMeta
            ? serialize($Entry)
            : (string)$Entry;
    }



    /**
     * Stringify structure of compact file (array) to flat content of file (string).
     * Descended classes will probably override this method.
     *
     * @param array $Struct
     * @return string
     */
    protected function PackCompactStruct($Struct) {

        return serialize($Struct);
    }

}

?>