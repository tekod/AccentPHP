<?php namespace Accent\Session\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * DB session driver stores data in database.
 *
 * Service "DB" must be specified in parent's $DefaultOptions configuration.
 *
 * To simplify usage several fields are merged (serialized) in one column:
 *   OldId, TimeRotated, Data, DataOnce.
 * It is very unlikely that developers would need to search database by these fields.
 */

use Accent\DB\DB;
use Accent\AccentCore\ArrayUtils\Mapper;


class DatabaseDriver extends AbstractDriver {


    protected static $DefaultOptions= array(

        // database table
        'TableName' => 'session',

        // fieldname mapping: array of from=>to pairs or instance of ArrayUtils/Mapper
        'TableMap' => array(
            'Id'         => 'Id',      // type: char(32)
            'Timestamp'  => 'Updated', // type: datetime
            'TimeCreated'=> 'Created', // type: datetime
            'Info'       => 'Info',    // type: text (or longtext)
        ),

        // specify services
        'Services'  => array(
            'DB' => 'DB',       // 'DB' service
        ),
    );

    // internal properties
    protected $Mapper;


    public function __construct($Options) {

        // call parent
        parent::__construct($Options);

        // prepare mapper object
        $this->Mapper= is_object($this->GetOption('TableMap'))
            ? $this->GetOption('TableMap')
            : new Mapper($this->GetOption('TableMap'));
    }


    public function Read($Id, $OldId=null) {

        // fetch Id and OldId sessions from database
        $Records= $this->Fetch($OldId === null ? $Id : array($Id, $OldId));

        // does any session found?
        if (empty($Records)) {
            return false;
        }

        // choose newer if both are found
        if (count($Records) == 1) {
            $Record= $Records[0];
        } else {
            $Record= $Records[0]['Id'] == $Id
                ? $Records[0]
                : $Records[1];
        }

        // unpack Info block
        $Info= json_decode($Record['Info'], true, 128, JSON_BIGINT_AS_STRING);
        if (!is_array($Info)) {
            $Info= array();
        }

        // re-load if session is rotated
        $RotatedTo= isset($Info['RotatedTo']) ? $Info['RotatedTo'] : '';
        if ($RotatedTo) {
            $Recs= $this->Fetch($RotatedTo);
            if (empty($Recs)) {
                // wierd, ok, continue with old record
            } else {
                $Record= $Recs[0];
                $Info= json_decode($Record['Info'], true, 128, JSON_BIGINT_AS_STRING);
                if (!is_array($Info)) {
                    $Info= array();
                }
            }
        }
        // result
        return array(
            'Id'         => $Record['Id'],
            'OldId'      => isset($Info['OldId']) ? $Info['OldId'] : '',
            'Timestamp'  => $Record['Timestamp'],
            'TimeCreated'=> $Record['TimeCreated'],
            'TimeRotated'=> isset($Info['TimeRotated']) ? $Info['TimeRotated'] : 0,
            'Data'       => isset($Info['Data']) ? $Info['Data'] : array(),
            'DataOnce'   => isset($Info['DataOnce']) ? $Info['DataOnce'] : array(),
        );
    }


    /**
     * Helper method, fetching session records from database.
     */
    protected function Fetch($Id) {

        $Records= $this->GetService('DB')
            ->Query($this->GetOption('TableName'), '*')
            ->Where($this->Mapper->MapKey('Id'), is_array($Id) ? 'IN' : '=', $Id)
            ->FetchAll();
        return $this->Mapper->ReMapArray2D($Records);
    }


    public function Write($Id, $OldId, $TimeCreated, $TimeRotated, $Data, $DataOnce) {

        $PackedInfo= json_encode(array(
            'OldId'      => $OldId,
            'TimeRotated'=> $TimeRotated,
            'RotatedTo'  => '',
            'Data'       => $Data,
            'DataOnce'   => $DataOnce,
        ), JSON_UNESCAPED_UNICODE);

        // send it to database
        $DB= $this->GetService('DB'); /* @var $DB DB */
        $DB->Upsert(
            $this->GetOption('TableName'),
            $this->Mapper->MapKey('Id'),
            $Id
        )->Values(array(
            $this->Mapper->MapKey('Timestamp')  => $DB->DateToSqlDatetime(time()),
            $this->Mapper->MapKey('TimeCreated')=> $DB->DateToSqlDatetime($TimeCreated),
            $this->Mapper->MapKey('Info')       => $PackedInfo,
        ))->Execute();

        // update old session to point to new one
        if ($OldId === '') {
            return;
        }
        $RotatedInfo= array(
            'RotatedTo'=> $Id,
            'TimeRotated'=> time(),
        );
        $DB->Update($this->GetOption('TableName'))
            ->Values(array($this->Mapper->MapKey('Info')=> json_encode($RotatedInfo, JSON_UNESCAPED_UNICODE)))
            ->Where($this->Mapper->MapKey('Id'), $OldId)
            ->Execute();
    }


    public function Delete($Id) {

        $this->GetService('DB')
             ->Delete($this->GetOption('TableName'))
             ->Where($this->Mapper->MapKey('Id'), $Id)
             ->Execute();
    }


    public function GarbageCollection() {

        $DB= $this->GetService('DB'); /* @var $DB DB */
        $Expire= time() - $this->GetOption('Cookie.Expire');
        $DB->Delete($this->GetOption('TableName'))
            ->Where($this->Mapper->MapKey('Timestamp'), '<', $DB->DateToSqlDatetime($Expire))
            ->Execute();
    }

}

?>