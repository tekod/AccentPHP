<?php namespace Accent\Form\Control;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Date selector consist of 3x 'select' number controls.
 */


class Date3Control extends NumberRangeSelectControl {


    protected static $DefaultOptions= array(

        'YearRange'=> array(1990, 2015),

        // how to export value to form: as array(Y,M,D) or timestamp or SQL's datetime
        // ['datetime', 'timestamp', 'array']
        'ExportAs'=> 'datetime',
    );

    /*
     * $Value and $InitValue are buffered as arrays(Y,M,D) or null/false
     */


    public function RenderElement() {

        // prepare most important attributes
        $Attr= $this->BuildAttributes(array());
        // get current values
        $Values= $this->GetSelectValues();
        // prepare list of years
        $YearsList= $this->GetYearsList($Values[2]);
        // render
        return $this->RenderSingleSelector('d', $Attr, range(1,31), $Values[2])
              .$this->RenderSingleSelector('m', $Attr, range(1,12), $Values[1])
              .$this->RenderSingleSelector('y', $Attr, $YearsList, $Values[0]);
    }


    protected function RenderSingleSelector($Letter, $Attr, $List, $Selected) {

        $Content= $this->RenderOptionsList(
            array_combine($List,$List),
            array($Selected),
            $this->GetOption('Attributes.multiple'),
            $this->GetOption('MultiLang'),
            $this->GetOption('Escape'),
            $this->GetOption('AutoSelectFirst'),
            $this->GetOption('OptionAttributes'),
            $this->GetOption('SelectedOptionAttributes')
        );
        // add suffixes to 'name' and 'id' attribute
        $Attr['name']= $this->Name.'_'.$Letter;
        $Attr['id']= $this->Id.'_'.$Letter;
        // render element
        return $this->Form->RenderTag('select', $Attr, $Content);
    }


    /*
     * Return selected values for each select list.
     * Internaly, $Value is always array-typed, no matter of 'ExportAs' option.
     */
    protected function GetSelectValues() {

        if ($this->Value === false) {
            return array(null,null,null); // unsuccessfull control, stay blank
        }
        $Values= $this->Value === null
            ? $this->InitValue  // submission not happen
            : $this->Value;
        return $Values;
    }


    /*
     * Return list of avaliable years
     */
    protected function GetYearsList($Selected) {

        $Range= $this->GetOption('YearRange');
        $From= intval($Range[0]);
        $To= isset($Range[1]) ? intval($Range[1]) : $From+10;
        // expand range to accommodate current values
        if ($Selected !== null && $Selected < $From && $Selected >= 1970) {
            $From= $Selected;
        }
        if ($Selected !== null && $Selected > $To && $To <= 2037) {
            $To= $Selected;
        }
        // build array
        return range($From, $To);
    }


    /**
     * Format date for logging.
     */
    protected function DiffValueForLog() {

        return $this->FormatSqlDate($this->Value);
    }


    //-------------------------------------------------------------
    //                      Getters and setters
    //-------------------------------------------------------------


    protected function ExportValue_Getter() {

        return $this->Value === false
            ? false
            : $this->AdaptExport($this->Value);
    }


    protected function ImportValueFromData_Getter() {

        $Import= $this->Form->GetValue($this->Name);
        return $Import === null
            ? null
            : $this->AdaptImport($Import);
    }


    protected function ImportValueFromHttp_Getter() {

        $Import= array(
            $this->Form->GetHttpValue($this->Name.'_y'),
            $this->Form->GetHttpValue($this->Name.'_m'),
            $this->Form->GetHttpValue($this->Name.'_d'),
        );
        return $Import[0] === false
           ? false
           : array_map('intval', $Import);
    }



    //-------------------------------------------------------------
    //                         Utils
    //-------------------------------------------------------------


    protected function FormatSqlDate($Value) {

        if (!$Value) {
            // display uninitializated value as empty string
            return '';
        }
        return $Value[0]
            .'-'.str_pad($Value[1], 2, '0', STR_PAD_LEFT)
            .'-'.str_pad($Value[2], 2, '0', STR_PAD_LEFT);
    }


    protected function AdaptImport($Import) {

        switch ($this->GetOption('ExportAs')) {
            case 'array': break;
            case 'timestamp': $Import= explode('.', gmdate('Y.m.d',$Import)); break;
            case 'datetime': $Parts= explode(' ', $Import); $Import= explode('-', reset($Parts)); break;
            default: $Import= array(1970,1,1);
        }
        return array_map('intval', $Import + array(1,1,1));
    }


    protected function AdaptExport($Export) {

        $Export= (array)$Export + array(1,1,1);
        switch ($this->GetOption('ExportAs')) {
            case 'array': return $Export;
            case 'timestamp': return gmmktime(0,0,0,$Export[1],$Export[2],$Export[0]);
            case 'datetime': return $this->FormatSqlDate($Export);
        }
        $this->Error('Form.'.$this->ClassName.'.Export: unknown ExportAs type.');
        return null;
    }

}
?>