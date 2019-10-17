<?php namespace Accent\Form\ValueTransform;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Converting integer-typed value from storage (database) into Y/M/D formated date string.
 */
use Accent\Form\ValueTransform\BaseTransform;


class IntegerToYMDTransform extends BaseTransform {


    // default options
    protected static $DefaultOptions= array(

        // formating output, for codes see documentation for "date" function
        'Format'=> 'Y.m.d',
    );



    /**
     * Convert value taken from storage to shape suitable for form-field.
     *
     * @param string $Value
     */
    public function TransformForControl($Value) {

        $this->TransError= false;
        return date($this->GetOption('Format'), $Value);
    }


    /**
     * Convert value from form-field to shape suitable for storing in storage.
     *
     * @param integer $Value
     */
    public function TransformForStorage($Value) {

        $this->TransError= false;
        $Format= $this->GetOption('Format');
        $TZ= new \DateTimeZone('UTC');
        $DateTime= \DateTime::createFromFormat($Format, $Value, $TZ);
        $Errors= \DateTime::getLastErrors();
        if ($DateTime === false || $Errors['warning_count'] > 0 || $Errors['error_count'] > 0) {
            $this->TransError= 'Date';  // set error message code and return orig. value
            return $Value;
        }
        $DateTime->SetTime(0,0);
        return $DateTime->GetTimestamp();
    }




}

?>