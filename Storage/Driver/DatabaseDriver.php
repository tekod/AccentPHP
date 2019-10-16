<?php namespace Accent\Storage\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Driver for storing data in database.
 *
 * Structure of tables:
 *
 * CREATE TABLE `storage` (
  `Name` varchar(80) NOT NULL,
  `Value` varchar(2000) NOT NULL,
  `Created` char(17) NOT NULL,      ; optionally, can be skipped if option "Expire"=false
  `Tags` varchar(80) NOT NULL,      ; optionally, can be skipped if option "TagTable"=false
  `Group` varchar(32) NOT NULL      ; optionally, can be skipped if option "Group"=false
  ) ENGINE=InnoDB;
 * ALTER TABLE `storage` ADD PRIMARY KEY (`Name`);
 *
 * CREATE TABLE `storage_tags` (     ; optional table, can be skipped option "TagTable"=false
  `Tag` varchar(80) NOT NULL,
  `Created` char(17) NOT NULL,
  `Group` varchar(32) NOT NULL       ; optionally, can be skipped if option "Group"=false
  ) ENGINE=InnoDB;
 * ALTER TABLE `storage_tags` ADD PRIMARY KEY (`Tag`);
 *
 *
 * If not using tags table 'storage_tag' can be ommited entirely.
 *
 */


use Accent\AccentCore\ArrayUtils\Mapper;


class DatabaseDriver extends AbstractDriver {


    // predefined options
    protected static $DefaultOptions= array(

        // TTL (time to live) in seconds, or false
        'Expire' => false,

        // name of database table (without prefix) with stored data
        'Table'=> 'storage',

        // name of database table for tags (leave false if not using tags)
        'TagTable'=> false,

        // set unique group name to share database table with other instances
        'Group'=> false,

        // fieldname mapping: array of from=>to pairs or instance of ArrayUtils/Mapper
        // note that only main table utilizing map, tag table is not adaptive
        'TableMap'=> array(
            'Name'   => 'Name',
            'Value'  => 'Value',
            'Created'=> 'Created',
            'Tags'   => 'Tags',
            'Group'  => 'Group',
        ),

        // specify database service
        'Services'=> array(
            'DB'=> 'DB',
        ),
    );


    // database is non volatile
    protected $CapabilityNonVolatile= true;

    // internal properties
    protected $Mapper;



    public function __construct($Options) {

        // call parent
        parent::__construct($Options);

        // prepare mapper object
        $this->Mapper= is_object($this->GetOption('TableMap'))
            ? $this->GetOption('TableMap')
            : new Mapper($this->GetOption('TableMap'));

        // successfully initied
        $this->Initied= true;
    }


    public function StorageExist() {

        // optimistic check, do not validate existence of table and columns
        $DB= $this->GetService('DB');
        return is_object($DB);
    }


    public function Exist($Key, $Validate=true) {

        if ($Validate) {
            $Value= $this->Read($Key);
            return $Value !== null;
        } else {
            $DB= $this->GetService('DB');
            if (!is_object($DB)) {
                $this->Error("AccentCore/Storage: Database connection not provided.");
                return false;
            }
            $Query= $DB
                ->Query($this->GetOption('Table'), '*')
                ->Where($this->Mapper->MapKey('Name'), $Key);
            $Data= $this->ApplyGroup($Query)
                ->FetchRow();
            return is_array($Data);
        }
    }


    public function Read($Key) {

        $DB= $this->GetService('DB');
        $Query= $DB
            ->Query($this->GetOption('Table'), '*')
            ->Where($this->Mapper->MapKey('Name'), $Key);
        $Data= $this->ApplyGroup($Query)
            ->FetchRow();
        if (!is_array($Data)) {
            return null;
        }
        $Data= $this->Mapper->ReMapArray($Data, 1, false);
        // validate TTL
        $Timestamp= isset($Data['Created']) ? $Data['Created'] : 0;
        $Expire= $this->GetOption('Expire');
        if ($Expire !== false && $Timestamp < $this->GetMicrotime(-$Expire)) {
            return null;
        }
        // validate tags
        $Tags= isset($Data['Tags']) ? $Data['Tags'] : '';
        if (!$this->ValidateTags($Tags, $Timestamp)) {
            return null;
        }
        // return value
        return $this->GetOption('Serialization')
            ? json_decode($Data['Value'], true, 128, JSON_BIGINT_AS_STRING)
            : $Data['Value'];
    }


    public function ReadAll() {

        $DB= $this->GetService('DB');
        $Query= $DB->Query($this->GetOption('Table'), '*');
        $Rows= $this->ApplyGroup($Query)
            ->FetchAll();
        if (!is_array($Rows)) {
            return null;
        }
        $Rows= $this->Mapper->ReMapArray2D($Rows, 1, false);
        // validate TTL
        $Expire= $this->GetOption('Expire');
        if ($Expire !== false) {
            $Oldest= $this->GetMicrotime(-$Expire);
            // check each record and remove invalid ones
            foreach ($Rows as $Key => $Data) {
                if (isset($Data['Created']) && $Data['Created'] < $Oldest) {
                    unset($Rows[$Key]);
                }
            }
        }
        // validate tags
        if ($this->GetOption('TagTable') !== false) {
            // load all tags
            $Query= $DB->Query($this->GetOption('TagTable'), '*');
            // append 'group' option
            $this->ApplyGroup($Query);
            // fetch tags
            $Tags= $Query->FetchAll();
            // create map
            $TagMap= array();
            foreach ($Tags as $Tag) {
                $TagMap[$Tag['Tag']]= $Tag['Created'];
            }
            // check each record and remove invalid ones
            foreach ($Rows as $Key => $Data) {
                $DataTags= array_filter(explode('|', $Data['Tags']));
                foreach ($DataTags as $DataTag) {
                    if (!isset($TagMap[$DataTag])
                            || $TagMap[$DataTag] > $Data['Created']) {
                        unset($Rows[$Key]);
                    }
                }
            }
        }
        // repack remaining rows
        $Result= array();
        foreach($Rows as $Record) {
            $Result[$Record['Name']]= $Record['Value'];
        }
        return $Result;
    }


    protected function ValidateTags($Tags, $Timestamp) {

        // check is any of tags has newer timestamp
        if ($Tags === array() || $Tags === false || $Tags === '') {
            return true;
        }
        if (!is_array($Tags)) {
            $Tags= array_filter(explode('|',$Tags));
        }

        // prepare query
        $Query= $this->GetService('DB')
            ->Query($this->GetOption('TagTable'), '*')
            ->Where('Tag', 'IN', $Tags);

        // append 'group' option
        $this->ApplyGroup($Query);

        // fetch records
        $Records= $Query->FetchAll();

        // check each tag
        foreach ($Tags as $Tag) {
            $Found= false;
            foreach ($Records as $Key => $Record) {
                if ($Record['Tag'] !== $Tag) {
                    continue;
                }
                if ($Record['Created'] > $Timestamp) {
                    return false;    // newer timestamp
                }
                unset($Record[$Key]);
                $Found= true;
            }
            if (!$Found) {
                return false;  // tag must be registered
            }
        }

        // all tags found
        return true;
    }


    public function Write($Key, $Value, $Tags) {

        $TX= $this->GetService('DB')->TransactionStart();

        $Tags= $this->NormalizeTags($Tags);

        return $this->WriteTags($Tags)
            && $this->WriteValues(array($Key=>$Value), $Tags);
    }


    public function WriteAll($Values, $CommonTags, $OverwriteWholeStorage) {

        $TX= $this->GetService('DB')->TransactionStart();

        $Tags= $this->NormalizeTags($CommonTags);

        if ($OverwriteWholeStorage) {
            // yes, this can produce short glitch in non-transactional database engines
            // but it is is much more faster then deleting discrete keys
            $this->Clear('*');
            // todo: detect non-transactional engine and build dedicated function for that case
        }

        return $this->WriteTags($Tags)
            && $this->WriteValues($Values, $Tags);
    }


    protected function NormalizeTags($Tags) {
        // ensure array type
        if (is_string($Tags)) {
            $Tags= $Tags === '' ? array() : array($Tags);
        }
        // remove "|" from tags
        foreach($Tags as &$Tag) {
            $Tag= str_replace('|', '', $Tag);
        }
        return $Tags;
    }


    protected function WriteTags($Tags) {

        $TagTable= $this->GetOption('TagTable');
        if (empty($Tags) || !$TagTable) {
            return true;
        }
        // remove old records
        $DB= $this->GetService('DB');
        $Query= $DB->Delete($TagTable)->Where('Tag', 'IN', $Tags);
        $this->ApplyGroup($Query)->Execute();
        // prepare new records
        $Group= $this->GetOption('Group');
        $DefaultRecord= $Group === false
            ? array()
            : array('Group'=>$Group);
        $DefaultRecord['Created']= $this->GetMicrotime();
        $Records= array();
        foreach($Tags as $Tag) {
            $Records[]= array(
                'Tag'=> $Tag,
            ) + $DefaultRecord;
        }
        return $DB->Insert($TagTable)->Values($Records)->Execute();
    }


    protected function WriteValues($Values, $Tags) {

        $DB= $this->GetService('DB');
        $Table= $this->GetOption('Table');
        $Group= $this->GetOption('Group');
        $DefaultRecord= array();
        if ($Group !== false) {
            $DefaultRecord['Group']= $Group;
        }
        if ($this->GetOption('Expire') !== false) {
            $DefaultRecord['Created']= $this->GetMicrotime();
        }
        if ($this->GetOption('TagTable') !== false) {
            $DefaultRecord['Tags']= implode('|', $Tags);
        }
        $Success= true;
        foreach($Values as $Key => $Value) {
            if (!is_scalar($Value)) {
                $this->Error('Storage/DatabaseDriver/Write: value for key "'.$Key.'" must be scalar.');
                $Success= false;
                continue;
            }
            $Record= array('Value'=>$Value) + $DefaultRecord;
            $Success &= $DB->Upsert($Table, $this->Mapper->MapKey('Name'), $Key)
                ->Values($this->Mapper->MapArray($Record, 1, false))
                ->Execute() !== false;
        }
        return $Success;
    }


    public function Delete($Key) {

        $DB= $this->GetService('DB');
        $Table= $this->GetOption('Table');
        $Query= $DB
            ->Delete($Table)
            ->Where($this->Mapper->MapKey('Name'), $Key);
        $this->ApplyGroup($Query);
        return $Query->Execute();
    }


    public function Clear($Tags) {

        $DB= $this->GetService('DB');
        // clear all
        if ($Tags === '*') {
            // clear whole table but only within current group
            $Query= $DB->Delete($this->GetOption('Table'));
            $this->ApplyGroup($Query)->Execute();
            // clear tagtable too if it is used
            $TagTable= $this->GetOption('TagTable');
            if ($TagTable) {
                $Query= $DB->Delete($TagTable);
                $this->ApplyGroup($Query)->Execute();
            }
            return;
        }
        // clear specified tag (or tags)
        $Tags= $this->NormalizeTags($Tags);
        if (!empty($Tags)) {
            // remove specified tags
            $Query= $DB
                ->Delete($this->GetOption('TagTable'))
                ->Where('Tag', 'IN', $Tags);
            $this->ApplyGroup($Query)
                ->Execute();
        }
    }


    public function GarbageCollection() {

        // remove all entries with expired timestamp, from all groups
        $Expire= $this->GetOption('Expire');
        if ($Expire === false) {
            return; // expiration not configured
        }
        $DB= $this->GetService('DB');
        $MicroTimestamp= $this->GetMicrotime(-$Expire);
        $DB->Delete($this->GetOption('TagTable'))
           ->Where('Created', '<', $MicroTimestamp)
           ->Execute();
        $DB->Delete($this->GetOption('Table'))
           ->Where($this->Mapper->MapKey('Created'), '<', $MicroTimestamp)
           ->Execute();
    }


    protected function ApplyGroup($Query) {

        $Group= $this->GetOption('Group');
        if ($Group !== false) {
            $Query->Where($this->Mapper->MapKey('Group'), $Group);
        }
        return $Query; // chaining
    }


}

?>