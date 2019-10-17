<?php namespace Accent\Form\Control;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Password control is "input-text" field with additional "input-checkbox" labeled with
 * "Hide password" which will convert first field into "input-password" for field.
 *
 * Why not ordinary "password" type?
 * See why: http://www.lukew.com/ff/entry.asp?1941
 */


class PasswordControl extends TextControl {


    protected static $DefaultOptions= array(

        // should password be visible by default or not
        'VisibleByDefault'=> true,
    );


    public function RenderElement() {

        $Visible= $this->Value === null
            ? $this->GetOption('VisibleByDefault')                 // get configured state
            : $this->Form->GetHttpValue($this->Name.'_cb') === false;  // follow state

        // prepare most important attributes
        $Attr= $this->BuildAttributes(array('type'=> $Visible?'text':'password'));
        // add other missing attributes but they will NOT overwrite these from above
        $Attr += array(
            'value'=> $this->RenderValue(),
        );
        // render input-text tag
        $TextTag= $this->Form->RenderTag('input', $Attr, false);

        // prepare attributes for checkbox
        $AttrCB= array(
            'type'=> 'checkbox',
            'name'=> $this->Name.'_cb',
            'onchange'=> 'document.getElementById(\''.$this->Id.'\').type=this.checked?\'password\':\'text\';',
            'checked'=> $Visible ? '' : 'checked',
            'value'=> '1',
        );
        // render checkbox
        $Checkbox= '<label>'  // clickable wrapper
                    .$this->Form->RenderTag('input', $AttrCB, false)
                    .  '<sub style="vertical-align:text-top;">'
                    .    $this->MsgDef('Hide password', 'Form.Password.Hide')
                    .  '</sub>'
                    .'</label>';

        // pack it together in inline-block wrapper so markers can appear right of text field
        return '<span style="display:inline-block; vertical-align:top;">'
                .$TextTag.'<br />'
                .$Checkbox
                .'</span>';
    }


}
?>