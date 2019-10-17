<?php namespace Accent\Form\Control;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Group of 'input-checkbox' input form elements.
 *
 * Like: <input type="checkbox" name="task[]" value="1" checked="checked" />
 *       <input type="checkbox" name="task[]" value="2" />
 *       <input type="checkbox" name="task[]" value="3" checked="checked" />
 */

use \Accent\Form\Control\BaseControl;


class CheckboxGroupControl extends BaseControl {

    protected static $DefaultOptions= array(

        // values for attribut "value" like key=>value pairs
        // where key will be used for "value" and value for label (via localization)
        'List'=> array(),

        // how many columns to have in grid of checkboxes
        //'Columns'=> 1,
        // This option is abadoned becouse number of columns is matter of presentation,
        // do that from CSS by specifying width of "<li>" to 25% (for 4 columns).
        // That aproach will allow on-fly changing number of columns on responsive designs.
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
        $Attr= $this->BuildAttributes(array('type'=>'checkbox'));
        // append "[]" at end of control name
        $Attr['name']= $Attr['name'].'[]';
        // unset "id" attribute becouse all checboxes has same id but it must be unique
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

        $InitValueArray= $this->GetValue('InitValue');
        $InitValue= $InitValueArray === null
            ? null
            : in_array($Key, $InitValueArray);
        $ValueArray= $this->GetValue('Value');
        if ($ValueArray === false) {
            // HTTP sumission happen but this control was silent = usuccessfull control
            return false;
        }
        $Value= $ValueArray === null
            ? null
            : in_array($Key, $ValueArray);
        // true if value found in HTTP submission or (initied as true, but no HTTP submission)
        return ($Value === true) || ($InitValue === true && $Value !== false)
            ? true
            : false;
    }


    protected function ExportValue_Getter() {

        return $this->Value;
        
        // gather array under key of this control
        //return array($this->Name => $this->Value);  ????????????????
    }


    protected function DiffValueForLog() {

        if (empty($this->Value)) {
            return '';
        }
        $List= $this->GetOption('List');
        $Out= array();
        foreach($this->Value as $Value) {
            $Out[]= $List[$Value];
        }
        return implode(',', $Out);
    }

}
?>