<?php namespace Accent\Form\Control;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Base class for all form controls.
 *
 *
 * Elements are "physic" part of a form controls. They are responsible for drawing
 * basic HTML form tags (textarea, input, select, checkbox,...).
 * Together with "label", "description" and "error message" they represents "form control".
 *
 * Control can contain multiple elements, for example 'Date3' control has
 * 'day', 'month' and 'year' input text fields.
 *
 * još kontrola: Password, SelectOrText, RadioArray, CheckboxArray,
 * Button, Hidden, Date, Date3, CCV, ColorPicker, ListBulider, DualListSelector,
 * Captcha, Upload
 * izvor: http://www.formfields.com/FORMgenArea/FORMgenLite/FORMgen3_0/allFields.php
 *
 *
 * RadioArray, CheckboxArray i DualListSelector treba da imaju "Other" opciju da se unese text
 *
 *
 *
 */

use \Accent\AccentCore\Component;


abstract class BaseControl extends Component {


    protected static $DefaultOptions= array(

        // name attribute of form field
        'Name'=> '',

        // id attribute of form control
        'Id'=> '',

        // title attribute of form field
        'Title'=> '',

        // additional attributes for input form tags (class, tabindex, ..) as key=>value
        'Attributes'=> array(),

        // text for <label> tag of control
        'Label'=> '',

        // briefing text explaining field
        'Description'=> '',

        // sanitizations to be applied during retrieving values from $_POST, pipe-separated
        // trim by default (remove outer blank chars)
        'Sanitize'=> 'T',

        // validations to be performed after retrieving values from $_POST, pipe-separated
        'Validate'=> '',

        // readonly attribute of form field
        'ReadOnly'=> false, // supported:  INPUT, TEXTAREA.

        // disabled attribute of form field
        'Disabled'=> false,  // supported: BUTTON, INPUT, OPTGROUP, OPTION, SELECT, TEXTAREA.

        // trailing visual flags (required,exlamation,...), pipe-separated
        // examples: "Req|Plus" or "Req:MsgCode|Plus" where MsgCode is title attr of flag
        // if you need to use pipe inside of MsgCode specify Markers as pipe-exploded array
        'Markers'=> '',

        // should this element display multilanguage value, must be displayed by javascript
        'MultiLang'=> false,

        // valid scenarios for this control, '*' by default
        // if prefixed with '-' that means 'all scenarios except this one'
        'Scenario'=> '*',

        // additional HTML which renderer will use to surround element+markers group
        'PreHTML'=> '',
        'PostHTML'=> '',

        // execute javascript on load
        'Javascript'=> '',

        // should method Diff() skip this control
        'SkipDiff'=> false,

        // fetch validation messages from this book
        'Book'=> '',

        // top-level form object
        'Form'=> null,

        // highlight this control by specifying class for whole row
        'RowClass'=> '',

        // apply value-transform, both directional, between stored value and displayed value
        // this should be specified class name or instantied tranformer object
        'Transform'=> false,

        // services
        'Services'=> array(
            'Sanitizer'=> 'Sanitizer',
            'Validator'=> 'Validator',
            'Localization'=> 'Localization',
        ),
    );


    // following properites describes behaviour of form field
    // therefore they should not be adjustable from caller object

    // flag should GetValue, SetValue, Diff skip this element or not
    protected $HasValue= true;

    // should values be escaped for safe HTML insertion
    protected $Escape= true;

    // can form field contain these attributes?
    protected $CanBeReadOnly= false;
    protected $CanBeDisabled= false;

    // does this control require "enctype=multipart/form-data" form attribute?
    protected $MultipartEncoding= false;

    // prefered template for renderer, 'Row' or 'RowForInline'?
    protected $RenderInline= false;



    // internal properties and buffers

    // value storages
    protected $InitValue, $Value;

    // collection of error messages after validation step
    protected $ValidationErrors= array();

    // id attribute of from field
    protected $Id= '';

    // name of form field
    protected $Name= '';

    // form object
    protected $Form;

    // value transformer object
    protected $Transformer;

    // current scenario
    protected $Scenario;

    // cached value for IsValidScenario method
    protected $IsValidScenario= null;



    /**
	 * Create a new form control.
     */
	public function __construct($Options=array()) {

        parent::__construct($Options);

        // set $Name, remove trailing "[]" from name
        $this->Name= str_replace('[]', '', $this->GetOption('Name'));
        // set parent
        $this->Form= $this->GetOption('Form');
        // set Id
        $this->Id= $this->GetOption('Id');
        if (!$this->Id) {
            $this->Id= $this->GenerateId();
        }
	}


    public function GetId() {

        return $this->Id;
    }


    protected function GenerateId() {

        $Id= $this->Form->GetName().'_'.$this->Name;
        $Id= str_replace(array('[',']'),'_', $Id); // id cannot contain '[' and ']'
        $Id= trim($Id, '_'); // id cannot start with '_'
        return $Id;
    }


    /*public function GetForm() {

        return $this->Form;
    }*/


    protected function GetMultiLangName() {

        $SimplifiedName= str_replace(array('[',']'),'_',$this->Name);
        return $this->Form->GetName().'_'.trim($SimplifiedName,'_').'_ml';
    }


    /**
     * Get current form's scenario.
     *
     * @return string  examples: 'AdminAdd', 'AdminEdit', 'Register', 'Profile'
     */
    protected function GetScenario() {

        if ($this->Scenario === null) {
            $this->Scenario= $this->Form->GetScenario();
        }
        return $this->Scenario;
    }


    /**
     * Form will call this method to determine should skip processing this control or not.
     * Result is cached becouse form will call this several times during request.
     *
     * Placing scenario checking routine here, instead to be part of form class, allows
     * each control to interfere with that logic.
     */
    public function IsValidScenario() {

        if ($this->IsValidScenario !== null) {
            return $this->IsValidScenario;        // return cached value
        }
        $CurrentScenario= $this->GetScenario();
        $Setting= $this->GetOption('Scenario');
        if ($CurrentScenario === $Setting                        // if matched
             || $Setting === '*' || $Setting === ''                // if omitted in control
             || $CurrentScenario === '*' || $CurrentScenario === '') {// if not specified
            $this->IsValidScenario= true;
            return true;
        }
        $Parts= array_map('trim', explode('|', $Setting));  // explode CSV
        foreach($Parts as $Part) {
            if ($Part === $CurrentScenario
                 || ($Part{0} === '-' && $Part <> "-$CurrentScenario")
            ) {
                $this->IsValidScenario= true;
                return true;         // found exact match or "all scenarios except XY"
            }
        }
        $this->IsValidScenario= false;
        return false;
    }


    /**
     * Return true if HTTP sumission happen and form sent values to controls.
     */
    protected function IsSubmitted() {

        return $this->Value !== null;
    }



    //-----------------------------------------------------------------
    //
    //               Getters of properites needed by form
    //
    //-----------------------------------------------------------------


    /**
     * Return label text.
     *
     * @param string $Lang  language parameter for Msg() method
     * @return string
     */
    public function GetLabel($Lang=null) {

        $Label= $this->Msg($this->GetOption('Label'), null, $Lang);
        return ($Label) ? $Label : $this->Name;
    }


    /**
     * Return description text.
     *
     * @return string
     */
    public function GetDescription() {

        $Description= $this->GetOption('Description');
        return $this->Msg($Description);
    }


    /**
     * Return array of markers.
     *
     * @return array
     */
    public function GetMarkers() {

        $Markers= $this->GetOption('Markers');
        return $Markers;
    }


    /**
     * Return class for highlighting row of control.
     *
     * @return string
     */
    public function GetRowClass() {

        return $this->GetOption('RowClass');
    }


    /**
     * Return 'Javascript' property.
     * Form will include this setting in resulting HTML as <script></script>.
     *
     * @return string
     */
    public function GetJavascript() {

        return $this->Javascript;
    }


    /**
     * Does control require "enctype=multipart/form-data" form attribute?
     *
     * @return boolean
     */
    public function GetMultipartEncoding() {

        return $this->MultipartEncoding;
    }



    //-----------------------------------------------------------------
    //
    //                    Value getters & setters
    //
    //-----------------------------------------------------------------


    /**
	 * Returns the element current value
	 */
	protected function GetValue($Source='Any') {

        if ($Source === 'InitValue') {
            return $this->InitValue;
        }
        if ($Source === 'Value') {
            return $this->Value;
        }
        return ($this->Value === null) // return any non-null value, check "Value" first
            ? $this->InitValue
            : $this->Value;
	}


    /**
     * Send value(s) of this control into parent form.
     * This method is triggered by Form's method GetValues().
     */
    public function ExportValue() {

        if (!$this->HasValue) {
            return;
        }
        // retrieve value
        $ExportingValue= $this->ExportValue_Getter();
        if ($ExportingValue === null) {
            return; // unsuccessfull control
        }
        if (is_scalar($ExportingValue)) {
            $ExportingValue= $this->TransformValueForStorage($ExportingValue);
        } else {
            foreach($ExportingValue as &$Value) {
                $Value= $this->TransformValueForStorage($Value);
            }
        }
        $this->Form->SetValue($this->Name, $ExportingValue);
    }


    protected function TransformValueForControl($Value) {

        $Transformer= $this->GetTranformer();
        if ($Transformer === false) {
            return $Value;
        }
        $TransformedValue= $Transformer->TransformForControl($Value);
        $Error= $Transformer->GetError();
        if ($Error !== false) {
            $this->AddValidationError($Error);
            return $Value;
        }
        return $TransformedValue;
    }


    protected function TransformValueForStorage($Value) {

        $Transformer= $this->GetTranformer();
        if ($Transformer === false) {
            return $Value;
        }
        $TransformedValue= $Transformer->TransformForStorage($Value);
        $Error= $Transformer->GetError();
        if ($Error !== false) {
            $this->AddValidationError($Error);
            return $Value;
        }
        return $TransformedValue;
    }


    /**
     * Return (and create if missing) transformer object.
     * @return false|object
     */
    protected function GetTranformer() {

        if ($this->Transformer === null) {
            $Class= $this->GetOption('Transform');
            if (is_string($Class) and strpos($Class,'\\') === false) {
                $Class= '\\Accent\\Form\\ValueTransform\\'.$Class;    // not FQCN ?
            }
            if (is_string($Class)) {
                $Parts= explode('|', $Class);
                $FQCN= array_shift($Parts);  // extract classname before first '|'
                $Options= $this->GetCommonOptions();
                foreach($Parts as $Part) {  // other parts use as 'key:value'
                    $Ex= explode(':', $Part);
                    $Options[reset($Ex)]= end($Ex);
                }
                $Class= new $FQCN($Options);
            }
            $this->Transformer= $Class;
        }
        return $this->Transformer;
    }


    /**
     * Provide value for ExportValue() method.
     * Descedants can override this to pack or modify value in special manner.
     * If control need to export multiple values return them as key=>value array.
     */
    protected function ExportValue_Getter() {

        return $this->Value;
    }


    /**
     * Fetch value(s) from Form and store them in $this->Value buffer.
     */
    public function ImportValueFromData() {

        if (!$this->HasValue) {
            return;
        }
        $Import= $this->ImportValueFromData_Getter();
        // for scalar value
        if (!is_array($Import)) {
            $this->Value= $this->TransformValueForControl($Import);
            return;
        }
        // for array of values
        $this->Value= array();
        foreach($Import as $Key=>$Value) {
            $this->Value[$Key]= $this->TransformValueForControl($Value);
        }
    }


    /**
     * Fetch value(s) from Form, sanitize and store them in $this->Value buffer.
     * Transformations will NOT be applied.
     * This method will be used to store values from $_POST.
     */
    public function ImportValueFromHttp() {

        if (!$this->HasValue) {
            return;
        }
        // get value form Form
        $Import= $this->ImportValueFromHttp_Getter();
        // skip unsuccessfull controls
        if ($Import === false) {
            return;
        }
        // for scalar value
        if (!is_array($Import)) {
            $this->Value= $this->Sanitize($Import);
            return;
        }
        // for array of values
        $this->Value= array();
        foreach($Import as $Key=>$Value) {
            $this->Value[$Key]= $this->Sanitize($Value);
        }
    }


    /**
     * Fetching method for ImportValueFromData() and ImportInitValue() methods.
     * Descedants can override this to pack or modify value in special manner.
     * If control need to import multiple values return them as key=>value array.
     */
    protected function ImportValueFromData_Getter() {

        return $this->Form->GetValue($this->Name);
    }


    /**
     * Fetching method for ImportValueFromHttp() method.
     * Descedants can override this to pack or modify value in special manner.
     * If control need to import multiple values return them as key=>value array.
     */
    protected function ImportValueFromHttp_Getter() {

        $Key= $this->GetOption('MultiLang')
            ? $this->GetMultiLangName()
            : $this->Name;
        return $this->Form->GetHttpValue($Key);
    }


    /**
     * Fetch value(s) from Form and store them in $this->InitValue buffer.
     * This method will be used to set original values from database.
     * Later on Diff() method will compare $Value and $InitValue to determine changes.
     * $this->HasValue is not used to allow "string"-typed controls to receive content.
     */
    public function ImportInitValue() {

        $Import= $this->ImportValueFromData_Getter();
        // for scalar value
        if (!is_array($Import)) {
            $this->InitValue= $this->TransformValueForControl($Import);
            return;
        }
        // for array of values
        $this->InitValue= array();
        foreach($Import as $Key=>$Value) {
            $this->InitValue[$Key]= $this->TransformValueForControl($Value);
        }

    }


    /**
     * Helper method, call sanitization service to modify input values.
     */
    protected function Sanitize($Value) {

        // sanitize only if there are sanitization instructions
        $SanitizationOption= $this->GetOption('Sanitize');
        return $SanitizationOption === ''
            ? $Value
            : $this->GetService('Sanitizer')->Sanitize($Value, $SanitizationOption);
    }




    //-----------------------------------------------------------------
    //
    //                        Diff subsystem
    //
    //-----------------------------------------------------------------


    /**
     * $MyForm->Diff($Old) will generate report about differences between
     * initial and submited values, which can be stored in log table
     */
    public function Diff($Labeled=true) {

        if ($this->GetOption('SkipDiff') || !$this->HasValue) {
            return false;
        }
        $Value1= $this->DiffValue($this->InitValue);
        $Value2= $this->DiffValue($this->Value);

        if (($Value1 === $Value2) || ($Value1 === null && $Value2 === false)) {
            return false; // skip on same values or on both uninitialized values
        }
        // return pure value or label-prefixed for
        // get label in backstage language becouse logs need to be in backstage language
        $Value= $this->DiffValueForLog();
        return $Labeled
            ? $this->GetLabel('@')."='$Value'"
            : $Value;
    }


    /**
     * Prepare $Value for comparasion in Diff process
     * Ensure literal string output, eventualy translated on default language
     * @param mixed $Value
     * @return string
     */
    protected function DiffValue($Value) {

        return $Value;
    }


    /**
     * Returns string represenation of new value, for storing in log storage.
     *
     * @return string
     */
    protected function DiffValueForLog() {

        if ($this->GetOption('MultiLang')) {
            return $this->DiffMultiLang($this->InitValue, $this->Value);
        } else {
            return $this->Value;
        }
    }


    /**
     * Returns only differences between two multilanguage packed strings,
     * separated by langs.
     *
     * @param string old multilanguage packed string
     * @param string new multilanguage packed string
     * @return string
     */
    protected function DiffMultiLang($ValOld, $ValNew) {

        // @TODO:..
        $Ex1= MultiLang::Explode($ValOld);
        $Ex2= MultiLang::Explode($ValNew);
        $Out= array();
        foreach($Ex2 as $Lang=>$Value2) {
            if ($Ex1[$Lang]<>$Value2) {
                $Out[]= $Lang.':"'.$Value2.'"';
            }
        }
        return implode(',', $Out);
    }



    //-----------------------------------------------------------------
    //
    //                         Rendering
    //
    //-----------------------------------------------------------------



    /**
     * Returns value for displaying within control value attribut.
     *
     * @return string
     */
    public function RenderValue() {

        if ($this->GetOption('MultiLang')) {
            // @TODO: doraditi
            MultiLang::AddCtrl($this->GetId(), $this->GetValue());
            return '';
        }
        if ($this->Escape) {
            $Value= $this->Escape($this->GetValue());
        }
        return $Value;
    }


    protected function Escape($String) {

        return htmlspecialchars($String, ENT_COMPAT, 'UTF-8');
    }


    /**
     * Render HTML representation of current form element.
     *
     * @return string HTML
     */
    public function RenderElement() {

        // prepare instructions for javascript validation
        // placing that feature here ensures that only rendered elements will be validated
        $this->AddJsValidation();

        // prepare most important attributes
        $Attr= $this->BuildAttributes(array('type'=>'text'));

        // add other missing attributes but they will NOT overwrite these from above
        $Attr += array(
            'value'=> $this->RenderValue(),
        );

        // render tag
        return $this->Form->RenderTag('input', $Attr, false);
    }


    /**
     * Prepare attributes for building form field tag.
     *
     * @return array
     */
    protected function BuildAttributes($PrependAttrs=array()) {

        $Attr= $PrependAttrs;

        // add main attributes, common for all controls, ordered by importance
        $Attr += array(
            'name'=> $this->Name,
            'id'=> $this->Id,
            'class'=> $this->GetElementClass(),
            'title'=> $this->Escape($this->GetOption('Title')),
        );

        // add attributes from constructor but they will NOT overwrite these from above
        $Attr += $this->GetOption('Attributes');

        // add few more attributes outside of Attributes option, if they are specified
        $Attr['readonly']= $this->GetOption('ReadOnly') ? 'readonly' : '';
        $Attr['disabled']= $this->GetOption('Disabled') ? 'disabled' : '';

        // remove forbidden attributes
        if (!$this->CanBeReadOnly) {
            unset($Attr['readonly']);
        }
        if (!$this->CanBeDisabled) {
            unset($Attr['disabled']);
        }
        return $Attr;
    }


    protected function GetElementClass() {

        $Classes= array();

        // get class name, strip "Control" trailing and prepend "fc" (form control) prefix
        $Classes[]= 'fc'.str_replace('_', '', substr($this->ClassName, 0, -7));

        // add "fcError" class if there is validation messages
        if (!empty($this->ValidationErrors)) {
            $Classes[]= 'fcError';
        }

        // get from Attributes
        $AttrClass= trim($this->GetOption('Attributes.class'));
        if ($AttrClass) {
            $Classes[]= $AttrClass;
        }

        return implode(' ', $Classes);
    }


    /**
     * Return name of row-template renderer should apply for this control.
     *
     * @return string
     */
    public function GetRowTemplateName() {

        return $this->RenderInline ? 'RowForInline' : 'Row';
    }


    /**
     * Return control`s "PreHTML" option.
     *
     * @return string
     */
    public function GetPreHTML() {

        return $this->GetOption('PreHTML');
    }

    /**
     * Return control`s "PostHTML" option.
     *
     * @return string
     */
    public function GetPostHTML() {

        return $this->GetOption('PostHTML');
    }


    /**
     * Send validation instructions to form,
     * it will be used for cline-side (javascript) validation.
     */
    protected function AddJsValidation() {

        $Validations= $this->GetValidators($this->GetScenario());

        if ($Validations) {
            $this->Form->AddJsValidation(
                $this->Name,
                $Validations
            );
        }
    }



    //-----------------------------------------------------------------
    //
    //                         Validation
    //
    //-----------------------------------------------------------------



    protected function AddError($Message, $Replacemens) {

        // message to be displayed in block above whole form
        $this->Form->AddError(vsprintf($Message, $Replacemens));
        // message to be displayed near form element
        $this->ValidationErrors[]= vsprintf($Message,
            $this->MsgDef('This value', $this->GetMsgCode('This'))
        );
    }


    protected function AddValidationError($ValidatorName) {


        $Msg= $this->Msg($this->GetMsgCode($ValidatorName));
        $Label= $this->GetLabel();
        $this->AddError($Msg, array("'$Label'"));
    }


    protected function GetMsgCode($Code) {

        $Book= $this->GetOption('Book');
        return ($Book)
            ? $Code.'#'.$Book
            : 'ValidateError.'.$Code;
    }


    /**
     * Return validation error(s) of this control.
     *
     * @return array
     */
    public function GetErrors() {

        return $this->ValidationErrors;
    }


    /**
     * Check $this->Value against all attached validators for current scenario.
     *
     * @return boolean
     */
    public function Validate($Context) {

        if (!$this->HasValue) {
            return true;
        }
        $Validators= $this->GetValidators($this->GetScenario());

        //$Context= $this->Form->GetAllValuesFromRequest();
        // call validation service
        $ValidatorService= $this->GetService('Validator');
        if (!$ValidatorService) {
            $this->Error('Form control: Validator service not found.');
            return true;
        }
        $Fails= $ValidatorService->ValidateAll($this->Value, $Validators, $Context);
        // any fail?
        if (empty($Fails)) {
            return true;
        }
        // dispatch validation error messages
        foreach($Fails as $ValidatorName) {
            $this->AddValidationError($ValidatorName);
        }
        return false;
    }


    /*
     * Return '|'-separated list of rules for usage in Validator service.
     */
    protected function GetValidators($ForScenario) {

        $Validators= $this->GetOption('Validate');
        // convert to array
        if (is_string($Validators)) {
            $Validators= array('*'=>$Validators);
        }
        // find validators for this scenario
        if (isset($Validators[$ForScenario])) {
            return $Validators[$ForScenario];
        } else {
            return isset($Validators['*'])
                ? $Validators['*']
                : '';
        }
    }

}

?>