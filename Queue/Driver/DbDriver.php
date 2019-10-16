<?php namespace Accent\Queue\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


class DbDriver extends AbstractDriver {


    /* default values for constructor array */
    protected static $DefaultOptions= array(

        // name of database table
        'Table'=> 'queue',

        // required services
        'Services'=> array(
            'DB'=> 'DB',
        ),
    );




    public function __construct($Options) {

        parent::__construct($Options);

        // create DB table if necessary
        $DB= $this->GetService('DB');
        if (!in_array($this->GetOption('Table'), $DB->ListTables())) {
            $this->CreateTable();
        }
    }


    public function Add($JobName, $JobData, $Priority=0, $RunAfter=null) {

        $DB= $this->GetService('DB');
        // prepare record fields
        $Record= array(
            'Id'=> 0,
            'Priority'=> $Priority,
            'RunAfter'=> $DB->DateToSqlDatetime($RunAfter === null ? 0 : $RunAfter),
            'ClaimedBy'=> '',
            'FailCount'=> 0,
            'JobName'=> $JobName,
            'JobData'=> json_encode($JobData, JSON_UNESCAPED_UNICODE),
        );
        // insert record
        return $DB->Insert($this->GetOption('Table'))->Values($Record)->Execute() !== false;
    }


    public function Claim($JobName=null, $SingleJob=true) {

        $DB = $this->GetService('DB');
        // prepare query for locking next job
        $Stmt = $DB->Update($this->GetOption('Table'), '')
                   ->Values(array('ClaimedBy'=> $this->GetOption('WorkerId'))) // claim ownership
                   ->Where('RunAfter', '<', $DB->DateToSqlDatetime()) // job must be available
                   ->Where('ClaimedBy', '')                          // job must be unclaimed
                   ->OrderBy(array('Priority', 'FailCount', 'Id'));// mostly prioritized first, then least-faulted and then oldest one
                    // sorting by FailCount will defer execution of already faulted jobs for as much as possible later
        // only for specified job name
        if ($JobName) {
            $Stmt->Where('JobName', $JobName);
        }
        // only one record?
        if ($SingleJob) {
            $Stmt->Range(1);
        }
        // execute locking
        $Success = $Stmt->Execute();
        // nothing affected?
        if ($Success !== 1) {
            return array();
        }
        // find and return locked records
        $Query= $this->BuildQuery($this->GetOption('WorkerId'), null, false);
        return $this->FetchQuery($Query);
    }


    public function GetCount($JobName=null, $IncludingDeferred=false) {

        $Query= $this->BuildQuery('', $JobName, $IncludingDeferred);
        return $Query->FetchCountAll();
    }


    public function GetList($JobName='', $IncludingDeferred=false) {

        $Query= $this->BuildQuery('', $JobName, $IncludingDeferred);
        return $this->FetchQuery($Query);
    }


    protected function BuildQuery($Owner, $JobName, $IncludingDeferred) {

        $DB= $this->GetService('DB');
        $Query= $DB
            ->Query($this->GetOption('Table'))
            ->Where('ClaimedBy', $Owner)     // jobs claimed by this instance
            ->OrderBy('Id');                 // sorted chronologically
        // only for specified job name
        if ($JobName) {
            $Query->Where('JobName', $JobName);
        }
        // only currently available jobs
        if (!$IncludingDeferred) {
            $Query->Where('RunAfter', '<=', $DB->DateToSqlDatetime());
        }
        // return query object
        return $Query;
    }


    protected function FetchQuery($Query) {

        // execute query
        $Rows= $Query->FetchAll();
        // deserialize each job data
        foreach($Rows as $k=>&$v) {
            $v['JobData']= json_decode($v['JobData'], true, 128, JSON_BIGINT_AS_STRING);
        }
        // TODO: which fields to show? maybe to remap some fields?
        return $Rows;
    }

    /* DEPRECATED */
    protected function Fetch($Owner, $JobName) {

        die('DEPRECATED -FETCH-');
        $Query= $this->GetService('DB')
                     ->Query($this->GetOption('Table'))
                     ->Where('ClaimedBy', $Owner)     // jobs claimed by this instance
                     ->OrderBy('Id');                 // sorted chronologically
        // only for specified job name
        if ($JobName) {
            $Query->Where('JobName', $JobName);
        }
        // execute query
        $Result= $Query->FetchAll();
        // deserialize each job data
        foreach($Result as $k=>&$v) {
            $v['JobData']= json_decode($v['JobData'], true, 128, JSON_BIGINT_AS_STRING);
        }
        // TODO: which fields to show? maybe to remap some fields?
        return $Result;
    }


    public function Delete($Id) {

        $this->GetService('DB')->Delete($this->GetOption('Table'))
            ->Where('Id', is_array($Id)?'IN':'=', $Id)
            ->Execute();
    }


    public function Release($Record, $IncFailCount=true, $RunAfter=null) {

        $DB= $this->GetService('DB');
        // prepare array of modified values
        $Values= array(
            'ClaimedBy'=> '',
        );
        if ($IncFailCount) {
            $Values['FailCount']= $Record['FailCount']+1;
        }
        if ($RunAfter) {
            $Values['RunAfter']= $DB->DateToSqlDatetime($RunAfter);
        }
        // execute update
        $DB->Update($this->GetOption('Table'))
            ->Values($Values)
            ->Where('Id', $Record['Id'])
            ->Execute();
    }


    public function Clear() {

        $DB= $this->GetService('DB');
        $DB->Delete($this->GetOption('Table'))
            ->Execute();
    }


    protected function CreateTable() {

        $DB= $this->GetService('DB');
        // check is table already present
        if (in_array($this->GetOption('Table'), $DB->ListTables())) {
            return;
        }
        // no, create table
        $DB->CreateTable($this->GetOption('Table'), array(
            'Columns'=> array(
                'Id'=> 'serial',
                'Priority'=> 'int, tiny',
                'RunAfter'=> 'datetime',
                'ClaimedBy'=> 'varchar(8)',
                'FailCount'=> 'int',
                'JobName'=> 'varchar(32)',
                'JobData'=> 'text, big',
            ),
            'Primary' => array('Id'),
            'Index'=> array(
                //'Dequeue'=> array('Priority','FailCount','Id','ClaimedBy','RunAfter'),
            )));
    }


}

?>