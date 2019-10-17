<?php namespace Accent\Localization\Loader;

/**
 * Part of the AccentPHP project.
 *
 * Localization loader class
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


use \Accent\Localization\Loader\BaseLoader;


class DatabaseLoader extends BaseLoader {


    protected static $DefaultOptions= array(

        // name of table for SQL query
        'Table'=> 'translations_table',

        // name of columns for SQL query
        'Fields'=> array('code','lang','book','message'),

        // array of Where definitions
        'Wheres'=> array(array('published','=','1')),
    );


    protected $DbService= null;


    public function Load($Lang, $Book) {

        if ($this->DbService === null) { // ask service manager only once
            $this->DbService= $this->GetService('DB');
        }
        if (!is_object($this->DbService)) {
            return false;
        }
        // build query object
        $QueryObject= $this->BuildQueryObject($Lang, $Book);
        // execute query
        $Rows= $QueryObject->FetchAll();
        // arrange rows in structured form
        $Array= (is_array($Rows))
            ? $this->ConstructTable($Rows)
            : false;
        return $Array;
    }


    protected function BuildQueryObject($Lang, $Book) {

        $Fields= $this->GetOption('Fields');
        $SelectFields= implode(',', $Fields);
        $QueryObject= $this->DbService->Query($this->GetOption('Table'), $SelectFields);
        // to load only this book or all books?
        if (!$this->GetOption('AllBooks')) {
            $QueryObject->Where($Fields[2], '=', $Book);
        }
        // to load only this language or all languages
        if (!$this->GetOption('AllLanguages')) {
            $QueryObject->Where($Fields[1], '=', $Lang);
        }
        // add custom WHERE ....
        foreach($this->Wheres as $Where) {
            count($Where)==3
                ? $QueryObject->Where($Where[0],$Where[1],$Where[2])
                : $QueryObject->Where($Where[0],$Where[1]);
        }
        return $QueryObject;
    }


    protected function ConstructTable($Rows) {

        $Fields= $this->GetOption('Fields');
        $Table= array();
        foreach($Rows as $Row) {
            // export values
            $Code= $Row[$Fields[0]];
            $Lang= strtolower($Row[$Fields[1]]);
            $Book= $Row[$Fields[2]];
            $Msg= $Row[$Fields[3]];
            // insert message in table
            if (!isset($Table[$Lang])) {
                $Table[$Lang]= array();
            }
            if (!isset($Table[$Lang][$Book])) {
                $Table[$Lang][$Book]= array();
            }
            $Table[$Lang][$Book][$Code]= $Msg;
        }
        return $Table;
    }

}

?>