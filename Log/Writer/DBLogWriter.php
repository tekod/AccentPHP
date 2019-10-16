<?php namespace Accent\Log\Writer;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * DbLogWriter writes logs to database.
 * Flexibile configuration allows to developers to choose which fields
 * will be stored in table with remappimg column names on-fly.
 *
 * This writer does not use Formatters becouse database accept array of data,
 * not single string. If you need to format content of data inherit this class.
 */

use Accent\Log\Writer\BaseLogWriter;
use Accent\Log\Log;


class DBLogWriter extends BaseLogWriter  {

    protected static $DefaultOptions= array(
        // mandatory options
        'Buffered'=> false,
        'MinLevel'=> Log::INFO, // integer from LOG class
        'ClearOnStart'=> false,
        // writter specific options
        'Table'=> 'log',
        // specify names of columns
        'Fields'=> array(
            'id'     => 'id',
            'level'  => 'level',
            'logger' => 'logger',
            'message'=> 'message',
            'created'=> 'created',
            'data'   => 'data',
        ),
        // list of fields which need to be removed from $Data & written in its own columns
        'SeparatedFields'=> array(),
        // list of services, as usually
        'Services'=> array(
            'DB'=> 'DB',
        ),
    );


    protected function ProcessWrite($Message, $Level, $Data) {

        $DB= $this->GetService('DB');
        $Time= $DB->DateToSqlDatetime(time());
        // StringifyMessage can reduce size od $Data array
        $this->StringifyMessage($Message, $Data);
        // pack Values
        $SeparatedFields= $this->ExtractSeparatedFields($Data);
        $Values= $this->AddField('id', 0)
               + $this->AddField('level', $Level)
               + $this->AddField('logger', $this->LoggerName)
               + $this->AddField('message', $Message)
               + $this->AddField('created', $Time)
               + $this->AddField('data', $this->SerializeData($Data))
               + $SeparatedFields;

        // send to database
        $Success= $DB->Insert($this->GetOption('Table'))->Values($Values)->Execute();
        if ($Success === false) {
            $this->Initied= false;
            $this->Error($DB->GetError(),3);
        }
    }


    protected function AddField($Name, $Value) {

        // translate name and return array with it
        $NewFieldName= $this->GetOption('Fields.'.$Name);
        if (!$NewFieldName) {
            // skip this field
            return array();
        }
        return array($NewFieldName=>$Value);
    }


    protected function SerializeData($Data) {

        if ($Data === array()) {
            return '';
        } else if (is_string($Data)) {
            return trim($Data);
        } else {
            return serialize($Data);
        }
    }


    protected function Flush() {

        $DB= $this->GetService('DB');
        $Values= array();
        foreach($this->Buffer as $Item) {
            list($Message, $Level, $Data, $Timestamp)= $Item;
            $this->StringifyMessage($Message, $Data);
            // pack Values
            $SeparatedFields= $this->ExtractSeparatedFields($Data);
            $Values[]= $this->AddField('id', 0)
                     + $this->AddField('level', $Level)
                     + $this->AddField('logger', $this->LoggerName)
                     + $this->AddField('message', $Message)
                     + $this->AddField('created', $DB->DateToSqlDatetime($Timestamp))
                     + $this->AddField('data', serialize($Data))
                     + $SeparatedFields;
        }
        // send to database as array of arrays
        $Success= $DB->Insert($this->GetOption('Table'))->Values($Values)->Execute();
        if ($Success === false) {
            $this->Error($DB->GetError(),3);
        }
    }


    protected function ExtractSeparatedFields(&$Data) {

        if (!is_array($Data)) {
            return array();
        }
        $Result= array();
        foreach($this->GetOption('SeparatedFields') as $Name) {
            if (!isset($Data[$Name])) {
                continue;
            }
            $Result[$Name]= $Data[$Name];
            unset($Data[$Name]);
        }
        return $Result;
    }

}

?>