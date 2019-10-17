<?php namespace Accent\Form\Renderer;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Form factory class.
 *
 */

use \Accent\AccentCore\Component;


class BaseRenderer extends Component {


    protected static $DefaultOptions= array(

        // caller
        'Form'=> '',
    );


    // templates are defined outside of DefaultOptions to prevent developer to modify
    // them becouse changing templates without changing processing methods can lead to
    // unpredictible results.
    protected $Templates= array(

        // how to pack all parts of control into single row
        'Row'=> '
            <tr id="{{Id}}_row"{{RowClass}}>
                <th>{{Label}}</th>
                <td>{{PreHTML}}{{Element}}{{Markers}}{{PostHTML}}{{Errors}}{{Description}}</td>
            </tr>',

        // same as previous but dedicated for controls with enabled 'RenderInLine' property,
        // default "null" means that in this design such controls will be rendered like
        // all others, using 'Row' template.
        // usecase for this template is checkbox control in verticaly-aligned form
        // where label can be placed at right side of element, reducing height of form.
        // furthermore control can insist in using 3rd template using GetRowTemplateName().
        'RowForInline'=> null,

        // what to put between rows
        'RowSeparator'=> '',

        // how to enclose collection of rows
        'RowsPack'=> '<table>{{RowsPack}}</table>',

        // how to build bottom of form - row with submit butons
        'RowWithButtons'=> '<div class="fButtonsRow">{{Buttons}}</div>',

        // how to build label of control
        'Label'=> '<label for="{{Id}}">{{Label}}:</label>',

        // how to display element
        'Element'=> '{{Element}}',

        // how to display brief description
        'Description'=> '<div class="fDesc">{{Description}}</div>',

        // how to display markers
        'Markers'=> '<span class="fMarkers">{{Markers}}</span>',

         // how to display validation errors
        'ErrorsPack'=> '<div class="fError"><ul>{{ErrorsPack}}</ul></div>',

        // how to display each error inside of ErrorsPack
        'ErrorLine'=> '<li>{{ErrorLine}}</li>',
    );

    // temporary storage for processed control
    protected $Control= null;

    // caller form
    protected $Form;




    public function __construct($Options= array()) {

        parent::__construct($Options);

        $this->Form= $this->GetOption('Form');
    }




    //-----------------------------------------------------------------
    //
    //                       Workers classes
    //
    //-----------------------------------------------------------------


    /**
     * Task: render label part of control.
     */
    public function RenderLabel($Control) {

        $this->Control= $Control;
        return $this->ApplyTpl('Label', $Control->GetLabel());
    }


    /**
     * Task: render element.
     * It will usually return unmodified element fetched by control's class itself.
     */
    public function RenderElement($Control) {

        $this->Control= $Control;
        return $this->ApplyTpl('Element', $Control->RenderElement(), false);
    }


    /**
     * Task: render markers part of control.
     */
    public function RenderMarkers($Control) {

        $this->Control= $Control;
        $Markers= $Control->GetMarkers();
        if (!is_array($Markers)) {
            // multiple markers inline: Req|ML
            $Markers= explode('|',$Markers);
        }
        $HTML= '';
        foreach ($Markers as $M) {
            if (!is_array($M)) {
                // title can be specified like: "Req:TranslationCode#Book"
                $M= explode(':', $M, 2);
            }
            if (!$M[0]) {
                continue; // empty setting
            }
            $Class= 'fm'.$M[0];
            $Title= isset($M[1])
                ? ' title="'.$this->Escape($this->Msg($M[1])).'"'
                : '';
            $HTML .= '<span class="'.$Class.'"'.$Title.'></span>';
        }
        return $this->ApplyTpl('Markers', $HTML, false);
    }


    /**
     * Task: render part with validation errors.
     */
    public function RenderErrors($Control) {

        $this->Control= $Control;
        $Errors= $Control->GetErrors();
        $HTML= '';
        foreach($Errors as $Error) {
            $HTML .= $this->ApplyTpl('ErrorLine', $Error);
        }
        $KeepEmpty= $this->Form->GetUseOfJavascript();
        return $this->ApplyTpl('ErrorsPack', $HTML, false, $KeepEmpty);
    }


    /**
     * Task: render description part.
     */
    public function RenderDescription($Control) {

        $this->Control= $Control;
        return $this->ApplyTpl('Description', $Control->GetDescription());
    }



    /**
     * Task: render single row.
     */
    public function RenderRow($Control) {

        if (!$Control->IsValidScenario()) {
            return '';
        }
        return $this->RenderRowInternal($Control, null);
    }


    /**
     * Task: render all rows and enclose them in common container.
     * Row with bottom buttons is not included.
     */
    public function RenderRows($Controls) {

        $Rows= array();
        $RowCount= 0;
        foreach($Controls as $Control) {
            if (!$Control->IsValidScenario()) {
                continue;
            }
            $Rows[]= $this->RenderRowInternal($Control, $RowCount);
            $RowCount++;
        }
        $HTML= implode($this->Templates['RowSeparator'], $Rows);
        return $this->ApplyTpl('RowsPack', $HTML, false);
    }


    /**
     * Task: render row with bottom-buttons.
     */
    public function RenderButtonsRow($ButtonsList) {

        if (empty($ButtonsList)) {
            return ''; // avoid rendering empty div
        }
        $Buttons= array();
        foreach($ButtonsList as $Name=>$Button) {
            if (isset($Button['Scenario']) && !$this->IsValidBtnScenario($Button['Scenario'])) {
                 continue;
            }
            $Type= strtolower($Button['Type']);
            $Caption= $this->Escape($this->Msg($Button['Label']));
            unset($Button['Type'],$Button['Label'],$Button['Scenario']);
            $Button += array(
                'type'=> $Type,
                'id'=> $this->Form->GetName().'_btn'.$Name,
            );
            $Buttons[]= $this->Form->RenderTag('button', $Button, $Caption);
        }
        $Out= "\n         ".implode("&nbsp;\n         ", $Buttons);
        return str_replace('{{Buttons}}', $Out, $this->Templates['RowWithButtons']);
    }



    //-----------------------------------------------------------------
    //
    //                        Internal methods
    //
    //-----------------------------------------------------------------


    /**
     * Worker for render control's row.
     * This method is called by RenderRow and RenderRows to perform actual rendering.
     */
    protected function RenderRowInternal($Control, $RowCount) {

        $this->Control= $Control;
        $Replacements= array(
            '{{Element}}' => $this->RenderElement($Control),
            '{{Id}}'     =>  $Control->GetId(),
            '{{Label}}'   => $this->RenderLabel($Control),
            '{{Markers}}'  => $this->RenderMarkers($Control),
            '{{Errors}}'    => $this->RenderErrors($Control),
            '{{Description}}'=> $this->RenderDescription($Control),
            '{{RowClass}}'  => $this->GetRowClassAttribute($RowCount),
            '{{PreHTML}}'   =>  $Control->GetPreHTML(),
            '{{PostHTML}}'  =>  $Control->GetPostHTML(),
        );
        // replace and return
        return strtr($this->GetRowTemplate(), $Replacements);
    }


    /**
     * Choose and return template for rendering control's row.
     */
    protected function GetRowTemplate() {

        $TemplateName= $this->Control->GetRowTemplateName();
        if ($this->Templates[$TemplateName] === null) {
            return $this->Templates['Row'];
        }
        return $this->Templates[$TemplateName];
    }


    /**
     * Calculate "class" attribute for control's row tag.
     */
    protected function GetRowClassAttribute($RowCount) {


        // get class specified inside of control
        $RowClass= $this->Control->GetRowClass();
        // highlight whole row if error exist
        $ControlErrors= $this->Control->GetErrors();
        if (!empty($ControlErrors)) {
            $RowClass.= ' frError';     // "fr" is "form row"
        }
        // make zebra
        if ($RowCount !== null) {
            if ($RowCount % 2 === 1) {
                $RowClass .= ' frOdd';
            }
        }
        return ($RowClass)
            ? ' class="'.trim($RowClass).'"'
            : '';
    }


    /**
     * Internal method, decorating value with specified template.
     */
    protected function ApplyTpl($TplName, $Value, $Escape=true, $KeepEmpty=false) {

        // convert non-string values to empty string (except array)
        if ($Value === null || is_bool($Value)) {
            $Value= '';
        }
        // escape content
        if ($Escape) {
            if (is_array($Value)) {
                array_walk($Value, array($this,'Escape'));
            } else {
                $Value= $this->Escape($Value);
            }
        }
        // glue array
        if (is_array($Value)) {
            $Value= implode('<br />', $Value);
        }
        // don't render template with empty value
        if ($Value === '' && !$KeepEmpty) {
            return '';
        }
        // inject value in template
        $Result= str_replace(
                array('{{Id}}', '{{'.$TplName.'}}'),
                array($this->Control->GetId(), $Value),
                $this->Templates[$TplName]
        );
        // for empty value render only outermost part of template
        if ($Value === '') {
            $Parts= explode('<', $Result);
            return (count($Parts) > 1) ? '<'.$Parts[1].'<'.end($Parts) : $Result;
        }
        return $Result;
    }


    /**
     * Escape string to be HTML safe.
     */
    protected function Escape($String) {

        return htmlspecialchars($String, ENT_COMPAT, 'UTF-8');
    }


    /**
     * Check should current bottom-button be rendered or not.
     */
    protected function IsValidBtnScenario($Scenario) {

        $CurrentScenario= $this->Form->GetScenario();
        if ($CurrentScenario === $Scenario                      // if matched
             || $Scenario === '*' || $Scenario === ''             // if omitted in control
             || $CurrentScenario === '*' || $CurrentScenario === '') { // if not specified
            return true;
        }
        $Parts= array_map('trim', explode(',', $Scenario));  // explode CSV
        foreach($Parts as $P) {
            if ($P === $CurrentScenario || ($P{0} === '-' && $P <> "-$CurrentScenario")) {
                return true;         // found exact match or "all scenarios except XY"
            }
        }
        return false;
    }


}



?>