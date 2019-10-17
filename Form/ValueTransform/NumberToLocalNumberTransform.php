<?php namespace Accent\Form\ValueTransform;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Converting number-typed value (integer, float, decimal,..) from storage (database) into
 * formated number using localization for "thousand separator" and "decimal separator".
 */
use Accent\Form\ValueTransform\BaseTransform;


class NumberToLocalNumberTransform extends BaseTransform {


    // default options
    protected static $DefaultOptions= array(

        // how many decimals to show in control field
        'Decimals'=> 2,

        // choose locale (language) for Localization service as source of
        // decimal and thousand separator character,
        // set '@' for current frontend lang, null for backend, 'gr' for greek, ...
        'Lang'=> '@',

        // services
        'Services'=> array(
            'Localization'=> 'Localization',
        ),
    );



    /**
     * Convert value taken from storage to shape suitable for form-field.
     *
     * @param string $Value
     */
    public function TransformForControl($Value) {

        $this->TransError= false;
        $Decimals= intval($this->GetOption('Decimals'));
        $Lang= $this->GetOption('Lang');
        $LocalService= $this->GetService('Localization');
        return $LocalService->FormatNumber($Value, $Decimals, $Lang);
    }


    /**
     * Convert value from form-field to shape suitable for storing in storage.
     *
     * @param integer $Value
     */
    public function TransformForStorage($Value) {

        $this->TransError= false;
        $Lang= $this->GetOption('Lang');
        $LocalService= $this->GetService('Localization');
        return $LocalService->UnFormatNumber($Value, $Lang);
    }




}

?>