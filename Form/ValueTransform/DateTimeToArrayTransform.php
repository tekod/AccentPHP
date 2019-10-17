<?php namespace Accent\Form\ValueTransform;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Converting datetime-typed value from storage (database) into array(Y,M,D).
 */
use Accent\Form\ValueTransform\BaseTransform;


class DateTimeToArrayTransform extends BaseTransform {


    // default options
    protected static $DefaultOptions= array(
    );


    /**
     * Convert "yyyy-mm-dd 00:00:00" string from database to array-typed value.
     */
    public function TransformForControl($Value) {

        $this->TransError= false;
        if ($Value === null || strlen($Value) < 9) {
            $this->TransError= 'Date'; // set error message code 'Form.ValidateError.Date'
            return array(1970,1,1);
        }
        $Parts= explode(' ', $Value);
        return explode('-', trim(reset($Parts)));
    }


    /**
     * Convert array-typed value from form-field to "yyyy-mm-dd" string for database.
     */
    public function TransformForStorage($Value) {

        $this->TransError= false;
        $Year= max(1000, min(9999, $Value[0]));
        $Month= max(1, min(12, $Value[1]));
        $Day= max(1, min(31, $Value[2]));
        return $Year.'-'.$Month.'-'.$Day;
    }




}

?>