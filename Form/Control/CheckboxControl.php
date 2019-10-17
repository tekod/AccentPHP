<?php namespace Accent\Form\Control;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * 'input-checkbox' input form element.
 *
 * Like: <input type="checkbox" name="" value="" checked="checked" />
 *
 *
 *
 */

use \Accent\Form\Control\BaseControl;


class CheckboxControl extends BaseControl {

    protected static $DefaultOptions= array(

        // value for attribut "value"
        'Value'=> 'Y',
    );

    protected $CanBeReadOnly= true;
    protected $CanBeDisabled= true;

    // try to render inline if possible
    protected $RenderInline= true;


    /**
     * Render HTML representation of current form element.
     *
     * @return string HTML
     */
    public function RenderElement() {

        // prepare most important attributes
        $Attr= $this->BuildAttributes(array('type'=>'checkbox'));
        // prepare attribute "value", remember - this is not value of [$Value, $InitValue]
        $Attr['value']= $this->GetOption('Value');
        // prepare attribute "checked"
        $Attr['checked']= $this->IsChecked() ? 'checked' : '';
        // render tag
        return $this->Form->RenderTag('input', $Attr, false);
    }


    /*
     *
     */
    protected function DiffValue($Value) {

        return $Value === (string)$this->GetOption('Value');
    }


    /*
     * Return textual "Yes" or "No" for logger.
     */
    protected function DiffValueForLog() {

        return $this->IsChecked()
            ? $this->Msg('Yes')
            : $this->Msg('No');
    }


    protected function IsChecked() {

        $InitValue= $this->InitValue;
        $HttpValue= $this->Value;
        $AttrValue= (string)$this->GetOption('Value');

        // if value from request is explicit boolean true
        /*if ($HttpValue === true) {
            return true;
        }*/

        // if value from request matching configured value
        if ($HttpValue === $AttrValue) {
            return true;
        }

        // if request didnt happen then check against initial value,
        // remember that unsucessfull control has value "false"
        /*if ($InitValue === $AttrValue && $HttpValue === null) {
            return true;
        }*/

        // well, this checkbox is not checked
        return false;
    }

}
?>