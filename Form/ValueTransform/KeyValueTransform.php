<?php namespace Accent\Form\ValueTransform;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Converting values using supplied indexed array as "key=>value" translator and reverse.
 * This is not common "type to type" transformer, it types depends on types from array.
 */
use Accent\Form\ValueTransform\BaseTransform;


class KeyValueTransform extends BaseTransform {


    // default options
    protected static $DefaultOptions= array(

        // translating "key=>value" array
        'Translations'=> array(),
    );


    /**
     * Convert value taken from storage to shape suitable for form-field.
     *
     * @param string $Value
     */
    public function TransformForControl($Value) {

        $this->TransError= false;
        $Translations= $this->GetOption('Translations');
        if (!isset($Translations[$Value])) {
            // dispatch "Form.ValidateError.Equal" error, it is close enough to real cause
            $this->TransError= 'Equal';
            return $Value;
        }
        return $Translations[$Value];
    }


    /**
     * Convert value from form-field to shape suitable for storing in storage.
     *
     * @param integer $Value
     */
    public function TransformForStorage($Value) {

        $this->TransError= false;
        $Translations= $this->GetOption('Translations');
        $Search= array_search($Value, $Translations);
        if ($Search === false) {
            // dispatch "Form.ValidateError.Equal" error, it is close enough to real cause
            $this->TransError= 'Equal';
            return $Value;
        }
        return $Search;
    }




}

?>