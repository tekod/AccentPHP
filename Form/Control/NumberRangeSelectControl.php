<?php namespace Accent\Form\Control;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * 'select' control with list of integers in range from Range[0] to Range[1] option.
 */


class NumberRangeSelectControl extends SelectControl {

    protected static $DefaultOptions= array(

        'Range'=> array(1990, 2015),
    );



    protected function GetSelectList($Lang=null) {

        $Range= $this->GetOption('Range');
        $From= intval($Range[0]);
        $To= isset($Range[1]) ? intval($Range[1]) : $From+10;
        $Nums= range($From, $To);
        $List= array_combine($Nums, $Nums);
        return $this->PrependEmptyOption($List, $Lang);
    }

}
?>