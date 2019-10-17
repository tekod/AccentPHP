<?php namespace Accent\Form\Control;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Date control is "input-text" field which content (according to formating rule)
 * represent date.
 * Intention of this control is to attach some javascript calendar widget to it.
 */


class DateControl extends TextControl {


    protected static $DefaultOptions= array(

        'Format'=> 'd.m.Y',

        // how to export value to form: as array(Y,M,D) or timestamp or SQL's datetime
        // ['datetime', 'timestamp', 'array']
        'ExportAs'=> 'datetime',

        // ensure integer type for all 3 parts, this overriding Sanitize='T' from parent
        'Sanitize'=> 'I',
    );

    /*
     * $Value and $InitValue are buffered as arrays(Y,M,D) or null/false
     */


    public function RenderValue() {

        $DateArray= $this->GetValue();
        if (is_string($DateArray)) {
            // this happen on validation error, to display visitor what is submitted
            $Text= $DateArray;
        } else {
            if (!is_array($DateArray)) { // probably null
                $DateArray= array(1, 1, 1);
            }
            if ($DateArray[0] <= 1) { // probably 0000-00-00
                $DateArray= array(1, 1, 1);
            }
            //// mysql datetime range: '1000-01-01' - '9999-12-31'
            //if ($DateArray[0] <= 1000) {
            //    $Text= ' ';
            //} else {
                $DateTime = new \DateTime();
                $DateTime->SetTimezone(new \DateTimeZone('UTC'));
                $DateTime->setDate($DateArray[0], $DateArray[1], $DateArray[2]);
                $Text= $DateTime->Format($this->GetOption('Format'));
            //}
        }
        if ($this->Escape) {
            $Text= $this->Escape($Text);
        }
        return $Text;
    }


    /*
     * Empty value from database will be fetched like array(0,1,1)
     * but empty form field will be ""
     * so diff-checker must compensate that difference
     */
    protected function DiffValue($Value) {

        return is_array($Value) && $Value[0] > 1
            ? $Value
            : 0;
    }


    /*
     * Render empty value as empty string.
     */
    protected function DiffValueForLog() {

        return $this->Value === 0
            ? ''
            : $this->FormatSqlDate($this->Value);
    }



    /*
     * If validation rule 'required' detected and date is from year 1970 or before
     * then reset $this->Value to empty string.
     */
    public function Validate($Context) {

        $OldValue= $this->Value;
        // test date
        if (is_array($this->Value) && $this->Value[0] <= 1970) {
            $Validators= explode('|', $this->GetValidators($this->GetScenario()));
            foreach($Validators as $V) {
                if (strtolower($V) === 'required') {
                    $this->Value= '';
                }
            }
        }
        // perform validation
        $Ret= parent::Validate($Context);
        // put back old value
        $this->Value= $OldValue;
        // return result of validation
        return $Ret;
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

        $Import= $this->Form->GetHttpValue($this->Name);
        if ($Import === false) {
           return false;
        }
        $Format= $this->GetOption('Format');
        $DateTime= \DateTime::createFromFormat($Format, $Import, new \DateTimeZone('UTC'));
        $Errors= \DateTime::getLastErrors();
        if ($DateTime === false || $Errors['warning_count'] > 0 || $Errors['error_count'] > 0) {
            //$this->AddValidationError('Date');
            return $Import; // as string, allowing visitor to see what is submitted
        }
        // explode into array of integers
        return array_map('intval', explode('.', $DateTime->Format('Y.m.d')));
    }


    //-------------------------------------------------------------
    //                         Utils
    //-------------------------------------------------------------


    protected function FormatSqlDate($Value) {

        return is_array($Value)
            ? $Value[0]
                .'-'.str_pad($Value[1], 2, '0', STR_PAD_LEFT)
                .'-'.str_pad($Value[2], 2, '0', STR_PAD_LEFT)
            : trim($Value);
    }


    protected function AdaptImport($Import) {

        switch ($this->GetOption('ExportAs')) {
            case 'array': break;
            case 'timestamp': $Import= explode('.', gmdate('Y.m.d',$Import)); break;
            case 'datetime':
            case 'date': $Parts= explode(' ', $Import); $Import= explode('-', reset($Parts)); break;
            default: $Import= array(1970,1,1);
        }
        return array_map('intval', $Import + array(1,1,1));
    }


    protected function AdaptExport($Export) {

        $Ex= (array)$Export + array(1,1,1);
        switch ($this->GetOption('ExportAs')) {
            case 'array': return $Ex;
            case 'timestamp': return gmmktime(0,0,0,$Ex[1],$Ex[2],$Ex[0]);
            case 'datetime':
            case 'date': return $this->FormatSqlDate($Ex);
        }
        $this->Error('Form.'.$this->ClassName.'.Export: unknown ExportAs type.');
        return null;
    }




}
?>