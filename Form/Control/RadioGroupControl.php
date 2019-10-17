<?php namespace Accent\Form\Control;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Group of 'input-radio' input form elements.
 *
 * Like: <input type="radio" name="gender" value="male" checked="checked" />
 *       <input type="radio" name="gender" value="female" />
 *       <input type="radio" name="gender" value="not your business" />
 */

use \Accent\Form\Control\BaseControl;


class RadioGroupControl extends BaseControl {

    protected static $DefaultOptions= array(

        // values for attribut "value" like key=>value pairs
        // where key will be used for "value" and value for label (via localization)
        'List'=> array(),
    );

    protected $CanBeReadOnly= true;
    protected $CanBeDisabled= true;

    // do not try to render inline,
    // we have main label for this control and each option has its own label
    protected $RenderInline= false;



    /**
     * Render HTML representation of current form element.
     *
     * @return string HTML
     */
    public function RenderElement() {

        // prepare most important attributes
        $Attr= $this->BuildAttributes(array('type'=>'radio'));
        // unset "id" attribute becouse all elements has same id but it must be unique
        unset($Attr['id']);
        // prepare list of "value" attributes
        $Values= $this->GetOption('List');
        if (!is_array($Values)) {
            $Values= array($Values); // don't dispatch error, just display it with key=>0
        }
        // render each checkbox
        $Rows= array();
        foreach($Values as $Key=>$Value) {
            $AV= $Attr + array(
                'value'=> $Key,
                'checked'=> $this->IsChecked($Key) ? 'checked' : '',
            );
            $Label= $this->Escape($this->Msg($Value));
            $CB= $this->Form->RenderTag('input', $AV, false);
            $Rows[]= '<li><label>'.$CB.$Label.'</label></li>';
        }
        // pack all rows
        return '<ul class="fcGroup">'.implode('',$Rows).'</ul>';
    }


    protected function IsChecked($Key) {

        $InitValue= $this->GetValue('InitValue');
        $Value= $this->GetValue('Value');
        if ($Value === false) {
            return false; // unsuccessfull control, stay blank
        }
        // true if value equals current option or (initied, but no HTTP submission)
        return ((string)$Key == $Value) || ((string)$Key === $InitValue && $Value === null);
    }


    protected function DiffValueForLog() {

        if (!$this->Value) {
            return '';
        }
        $List= $this->GetOption('List');
        return $List[$this->Value];
    }

}
?>