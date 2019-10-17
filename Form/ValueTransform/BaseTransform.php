<?php namespace Accent\Form\ValueTransform;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Base class for all form value-transformers.
 * First part of name represent type of variable in storage, usually database.
 * Second part of name represent type of variable which will be sent to
 * control's $Value property.
 */

use \Accent\AccentCore\Component;


abstract class BaseTransform extends Component {


    // default options
    protected static $DefaultOptions= array(
    );

    protected $TransError;



    /**
     * Convert value taken from storage to shape suitable for form-field.
     *
     * @param type $Value
     */
    public function TransformForControl($Value) {

        return $Value;
    }


    /**
     * Convert value from form-field to shape suitable for storing in storage.
     *
     * @param type $Value
     */
    public function TransformForStorage($Value) {

        return $Value;
    }


    /**
     * Return error status from last transformation process.
     * Returned string is part of validation error message,
     * for example to incite invalid date format return 'Date' which will produce
     * 'ValidateError.Date' message code.
     *
     * @return string|false
     */
    public function GetError() {

        return $this->TransError;
    }


}

?>