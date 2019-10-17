<?php namespace Accent\Form\Control;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * 'textarea' input form element.
 *
 * Like: <textarea name="">Some multiline text.</textarea>
 */

use \Accent\Form\Control\BaseControl;


class TextareaControl extends BaseControl {

    protected static $DefaultOptions= array(
        'Attributes'=> array(
            'cols'=> 10,
            'rows'=> 4,
        )
    );

    protected $CanBeReadOnly= true;
    protected $CanBeDisabled= true;



    /**
     * Render HTML representation of current form element.
     *
     * @return string HTML
     */
    public function RenderElement() {

        // prepare most important attributes
        $Attr= $this->BuildAttributes(array());
        // prepare value
        $Content= $this->RenderValue();
        // render tag
        return $this->Form->RenderTag('textarea', $Attr, $Content);
    }




}
?>