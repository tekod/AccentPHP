<?php namespace Accent\Form;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Form class.
 */

use \Accent\AccentCore\Component;


class Form extends Component {


    protected static $DefaultOptions= array(

        // name attribute of form, will be used for hidden field to distinguish submision owner
        'Name'=> '',

        // action attribute of form, URL where submit will be send
        'Action'=> '',

        // HTTP protocol: "get" or "post"
        'Method'=> 'post',

        // encoding protocol
        'EncType'=> 'application/x-www-form-urlencoded', // multipart/form-data

        // additional attributes for form tag (class, id, target, ..) as key=>value
        'Attributes'=> array(),

        // definitions of controls
        'Controls'=> array(),

        // hidden-type input fields as key=>value
        'Hidden'=> array(),

        // buttons to be rendered in bottom of form
        'Buttons'=> array(),

        // classname or object of form-renderer
        'Renderer'=> 'TableRenderer',

        // current scenario
        'Scenario'=> '',

        // use client-side validation?
        'JavascriptValidation'=> false,

        // fetch validation error messages from this book
        'Book'=> '',

        // version of Accent/Form package
        'Version'=> '1.0.0',

        // services
        'Services'=> array(
            'Sanitizer'=> 'Sanitizer',
            'Validator'=> 'Validator',
            'Localization'=> 'Localization',
        ),
    );


    // internal buffers
    protected $Name;
    protected $Attributes;
    protected $Controls;
    protected $Hidden;
    protected $Buttons;
    /** @var \Accent\Form\Renderer\BaseRenderer */
    protected $Renderer;
    protected $EncType;
    protected $Builded= false;
    protected $Values;
    protected $HttpValues;

    protected $CustomValidationValues= array();
    protected $ValidationErrors=array();
    protected $JsValidations= array();



    /**
     * Constructor.
     */
    public function __construct($Options= array()) {

        parent::__construct($Options);

        $this->Name= $this->GetOption('Name');
        $this->Attributes= $this->GetOption('Attributes');
        $this->Controls= $this->GetOption('Controls');
        $this->Hidden= $this->GetOption('Hidden');
        $this->Buttons= $this->GetOption('Buttons');
        $this->Renderer= $this->GetOption('Renderer');
    }


    /**
     * Return name of form.
     *
     * @return string
     */
    public function GetName() {

        return $this->Name;
    }


    /**
     * Return ID of form.
     *
     * @return string
     */
    protected function GetId() {

        $Id= $this->GetOption('Id');
        if (!$Id) {
            $Id= $this->GetName(); // let id be same as name
            $Id= str_replace(array('[',']'),'_', $Id); // id cannot contain '[' and ']'
            $Id= trim($Id, '_'); // id cannot start with '_'
        }
        return $Id;
    }


    /**
     * Import (overwrite) whole structure of form.
     *
     * @param array $Structure
     */
    public function LoadStructure($Structure) {

        $AllowedSegments= array('Name','Attributes','Controls','Hidden','Buttons');
        foreach($AllowedSegments as $Segment) {
            if (isset($Structure[$Segment])) {
                $this->$Segment= $Structure[$Segment];
            }
        }
        $this->Builded= false;
    }


    /**
     * Build all control objects.
     */
    public function BuildControls() {

        $this->EncType= $this->GetOption('EncType');

        // convert array of arrays into array of objects
        foreach($this->Controls as $Name=>&$Control) {
            // skip already builded controls
            if (is_object($Control)) {
                continue;
            }
            // find classname
            if (!isset($Control['Type'])) {
                $Control += array('Type'=>'text', 'Attributes'=>array('UNKNOWNTYPE'=>'YES'));
            }
            if (strpos($Control['Type'], '\\') === false) {  // short name, ex.: "select"
                $Type= ucfirst($Control['Type']);
                $Class= '\\Accent\\Form\\Control\\'.$Type.'Control';
            } else {                                  // it is fully qualified class name
                $Class= $Control['Type'];
            }
            if (!class_exists($Class)) {
                $this->Error('Class "'.$Class.'" not found.');
                $Class= '\\Accent\\Form\\Control\\TextControl'; // fallback to any primitive control
            }
            // copy this array into $Options and append few more items
            // and append common options to allow usage of services
            $Options= $Control + array(
                'Name'=> $Name,
                'Form'=> $this,
                'Book'=> $this->GetOption('Book')
            ) + $this->GetCommonOptions();
            $Control= new $Class($Options);
            // switch to multipart encoding if any control require that
            if ($Control->GetMultipartEncoding()) {
                $this->EncType= 'multipart/form-data';
            }
        }
        $this->Builded= true;
    }


    /**
     * Select renderer.
     *
     * @param \Accent\Form\Renderer\BaseRenderer $Renderer
     */
    public function SetRenderer($Renderer) {

        $this->Renderer= $Renderer;
    }


    /**
     * Returns selected renderer.
     *
     * @return \Accent\Form\Renderer\BaseRenderer
     */
    protected function GetRenderer() {

        // ensure that renderer is initialized
        if (is_string($this->Renderer)) {
            if (strpos($this->Renderer, '\\') === false) {
                // not FQCN? ok, prepend Accent namespace
                $Class= '\\Accent\\Form\\Renderer\\'.$this->Renderer;
            } else {
                $Class= $this->Renderer;
            }
            // initialize
            $Options= array(
                'Form'=> $this,
            ) + $this->GetCommonOptions();
            $this->Renderer= new $Class($Options);
        }
        return $this->Renderer;
    }


    /**
     * Returns current scenario.
     *
     * @return string
     */
    public function GetScenario() {

        return $this->GetOption('Scenario');
    }

    /**
     * Returns value of option "JavascriptValidation".
     *
     * @return bool
     */
    public function GetUseOfJavascript() {

        return $this->GetOption('JavascriptValidation');
    }


    /**
     * Return control object specified by its name.
     *
     * @param string $Name
     * @return Accent\Form\Control\BaseControl
     */
    public function GetControl($Name) {

        return $this->Controls[$Name];
    }


    /**
     * Return array with names of all controls.
     *
     * @return array
     */
    public function GetControlsList() {

        return array_keys($this->Controls);
    }


    /**
     * Return list of controls that are not specified in $Except parameter.
     *
     * @param array $Except  names of controls
     * @return array
     */
    public function GetOtherControls($Except) {

        return array_diff_key($this->Controls, array_flip($Except));
    }



    //-----------------------------------------------------------------
    //
    //                      Rendering methods
    //
    //-----------------------------------------------------------------

    /**
     * Render tag with label of control.
     */
    public function RenderLabel($ControlName) {

        return $this->GetRenderer()->RenderLabel($this->Controls[$ControlName]);
    }

    /**
     * Render main element of control - form field.
     */
    public function RenderElement($ControlName) {

        return $this->GetRenderer()->RenderElement($this->Controls[$ControlName]);
    }

    /**
     * Render tag with markers of control.
     */
    public function RenderMarkers($ControlName) {

        return $this->GetRenderer()->RenderMarkers($this->Controls[$ControlName]);
    }

    /**
     * Render tag with validation errors, if any.
     */
    public function RenderErrors($ControlName) {

        return $this->GetRenderer()->RenderErrors($this->Controls[$ControlName]);
    }

    /**
     * Render tag with description of control.
     */
    public function RenderDescription($ControlName) {

        return $this->GetRenderer()->RenderDescription($this->Controls[$ControlName]);
    }

    /**
     * Render single row (label, element, errors, description, ...).
     * Using this method directly will left developer withou features "zebra" and "separator".
     */
    public function RenderRow($ControlName) {

        return $this->GetRenderer()->RenderRow($this->Controls[$ControlName]);
    }

    /**
     * Render all rows and enclose them in common container.
     * Row with bottom buttons is not included.
     */
    public function RenderRows($ControlNames=null) {

        if ($ControlNames === null) {
            $Controls= $this->Controls;
        } else {
            $Controls= array();
            foreach($ControlNames as $Name) {
                $Controls[]= $this->Controls[$Name];
            }
        }
        return $this->GetRenderer()->RenderRows($Controls);
    }

    /**
     * Render bottom-row with submit buttons.
     */
    public function RenderButtonsRow() {
        return $this->GetRenderer()->RenderButtonsRow($this->Buttons);
    }

    /**
     * Render pack of input-hidden fields.
     */
    public function RenderHidden() {

        $List= $this->Hidden;
        $List['_FormName']= $this->Name;
        $Result= array();
        foreach($List as $k=>$v) {
            $Result[]= $this->RenderTag('input', array(
                'type'=> 'hidden',
                'name'=> $k,
                'value'=> $v
            ));
        }
        return implode('', $Result);
    }

    /**
     * Render opening <form> tag.
     */
    public function RenderFormStart() {
        // prepare form's attributes
        $Attr= array(
            'id'=> $this->GetId(),
            'name'=> $this->Name,
            'action'=> $this->GetOption('Action'),
            'method'=> $this->GetOption('Method'),
            'enctype'=> $this->EncType,
        );

        // add attributes from constructor but they will NOT overwrite these from above
        $Attr += $this->GetOption('Attributes');

        // add rules for client-side (javascript) validation
        $Attr['data-afv']= $this->RenderJavascriptRules();

        // render form tag
        return $this->RenderTag('form', $Attr, '', false);
    }

    /**
     * Render closing '<form>' tag.
     */
    public function RenderFormEnd() {

        return '</form>';
    }


    /**
     * Render javascript code with list of validation messages.
     */
    public function RenderJavascriptMessages() {

        if (!$this->GetOption('JavascriptValidation')) {
            return '';
        }
        $VS= $this->GetService('Validator');
        if (!$VS) {
            return '';
        }
        $This= $this->MsgDef('This value', 'ValidateErrorThis');
        $List= array();
        foreach($VS->GetValidatorsList() as $Name) {
            $Message= $this->Msg('ValidateError.'.$Name);
            if ($Message <> 'ValidateError.'.$Name) {
                $List[]= "'$Name':'".vsprintf($Message,$This)."'";
            }
        }
        return '<script>Accent.FormValidation.SetMessages({'.implode(',',$List).'});</script>';
    }


    /**
     *  Prepare value for "data-afv" attribute of form tag
     */
    public function RenderJavascriptRules() {

        if (!$this->GetOption('JavascriptValidation')) {
            return '';
        }
        // adapt some validators for JS enviroment
		$ThoSep= $this->Msg('FormatNumber.SepThousand');
		$DecSep= $this->Msg('FormatNumber.SepDecimal');
        foreach($this->JsValidations as &$Rule) {
            $Rule= str_replace('Float', "Float:$ThoSep/$DecSep", $Rule);
            $Rule= str_replace('Decimal:', "Decimal:$ThoSep/$DecSep/", $Rule);
        }

        // pack them
        $JSON= json_encode($this->JsValidations);

        // convert doublequotes to quotes
        return str_replace('"', "'", str_replace("'", "", $JSON));
    }


    /**
     * Render whole form.
     */
    public function Render() {

        if (!$this->Name) {
            $this->Error('Form: option "Name" is mandatory.');
        }
        $this->BuildControls();

        // render parts of form
        $Rows= $this->RenderRows();
        $Buttons= $this->RenderButtonsRow();
        $Hidden= $this->RenderHidden();
        $FormStart= $this->RenderFormStart();
        $FormEnd= $this->RenderFormEnd();
        $JsMessages= $this->RenderJavascriptMessages();

        // concat and return
        return $FormStart.$Hidden.$Rows.$Buttons.$FormEnd.$JsMessages;
    }


    /**
     * Render HTML tag, with all assigned attributes and inner content
     * attributes and content must be HTML-safe.
     *
     * This tag-factory is called from renderers and controls classes.
     */
    public function RenderTag($TagName, $Attrs=array(), $Content=false, $Close=true) {

        $BoolAttrs= array('checked'=>0,'disabled'=>0,'multiple'=>0,'readonly'=>0,'selected'=>0);
        $Out= "<$TagName";
        foreach($Attrs as $key=>$value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (isset($BoolAttrs[$key])) { // force XHTML compliance
                $value= $key;
            }
            if (is_array($value)) {
                $value= implode(',',$value);//{var_dump($Attrs); echo Debug::ShowStack();die();}
            }
            if ($key === 'title') {
                $value= $this->Msg($value); // localization
            }
            if (is_bool($value)) {
                $value= $value ? 'true' : 'false'; // boolean is represented as quoted string
            }
            if (is_string($value) || is_int($value) || is_float($value)) {
                $value= '"'.$value.'"';
            }
            $Out .= " $key=$value";
        }
        if ($Content === false) {
            $Out .= $Close ? " />" : ">";
        } else {
            $Out .= $Close ? ">$Content</$TagName>" : ">$Content";
        }
        return $Out;
    }




    //-----------------------------------------------------------------
    //
    //                  Managing structure of form
    //
    //-----------------------------------------------------------------


    public function AddControl($Name, array $Struct) {

        $this->Controls[$Name]= $Struct;
        $this->Builded= false;
    }

    public function DeleteControl($Name) {

        unset($this->Controls[$Name]);
        $this->Builded= false;
    }

    public function AddButton($Name, array $Struct) {

        $this->Buttons[$Name]= $Struct;
    }

    public function DeleteDelete($Name) {

        unset($this->Buttons[$Name]);
    }

    public function AddHidden($Name, $Value) {

        $this->Hidden[$Name]= $Value;
    }

    public function DeleteHidden($Name) {

        unset($this->Hidden[$Name]);
    }



    //-----------------------------------------------------------------
    //
    //                    Managing form's data
    //
    //-----------------------------------------------------------------


    /**
     * Send values to controls, in its $Value storage.
     * Values will not be sanitized and transformations will be applied.
     *
     * @param array $Values
     */
    public function SetValues($Values) {

        $this->Values= $Values;
        $this->BuildControls();
        foreach($this->Controls as $Control) {
            if ($Control->IsValidScenario()) {
                $Control->ImportValueFromData();
            }
        }
    }


    /**
     * Send values to controls, in its $InitValue storage.
     * Values will not be sanitized and transformations will be applied.
     *
     * @param array $Values
     */
    public function SetInitValues($Values) {

        $this->Values= $Values;
        $this->BuildControls();
        foreach($this->Controls as $Control) {
            if ($Control->IsValidScenario()) {
                $Control->ImportInitValue();
            }
        }
    }


    /**
     * Send values from HTTP to controls, in its $Value storage.
     * Value will not be transformated and sanitization will be applied.
     * If parameter is null method will try to get values from Request service.
     *
     * @param array $Values
     */
    public function SetHttpValues($Values=null) {

        if ($Values === null) {
            $Values= $this->GetRequestContext()->POST;
            if (empty($Values)) {
                $Values= $this->GetRequestContext()->GET;
            }
        }
        $this->HttpValues= $Values;

        //var_dump($this->HttpValues);

        $this->BuildControls();
        foreach($this->Controls as $Control) {
            if ($Control->IsValidScenario()) {
                $Control->ImportValueFromHttp();
            }
        }
    }


    /**
     * Ask all controls to export theirs values to parent form,
     * and return resulting buffer.
     *
     * @return array
     */
    public function GetValues() {

        $this->Values= array();
        // buffer is cleared to detect "successfull" controls.
        foreach($this->Controls as $Control) {
            if ($Control->IsValidScenario()) {
                $Control->ExportValue();
            }
        }
        return $this->Values;
    }



    //-----------------------------------------------------------------
    //
    //                  Control's getters and setters
    //
    //-----------------------------------------------------------------


    /**
     * Control's setter function for values.
     * Controls will call this method to export theirs data in this buffer.
     *
     * @param string $Key
     * @param mixed $Value
     */
    public function SetValue($Key, $Value) {

        $this->Values[$Key]= $Value;
    }


    /**
     * Control's getter function for values.
     * Controls will call this method to fetch theirs data from this buffer.
     *
     * @param string $Key
     * @return mixed
     */
    public function GetValue($Key) {

        return isset($this->Values[$Key])
            ? $this->Values[$Key]
            : null;
    }


    /**
     * Control's getter function for values from HTTP submision.
     * Controls will call this method to fetch theirs data from this buffer.
     *
     * @param string $Key
     * @return mixed
     */
    public function GetHttpValue($Key) {

        // try direct indexing
        if (isset($this->HttpValues[$Key])) {
            return $this->HttpValues[$Key];
        }

        // maybe deeper in array?
        $pos= strpos($Key,'[');
        if ($pos !== false) {
            $SubKey= substr($Key, $pos+1, -1);
            $Key= substr($Key, 0, $pos);
            if (isset($this->HttpValues[$Key]) && isset($this->HttpValues[$Key][$SubKey])) {
                return $this->HttpValues[$Key][$SubKey];
            }
        }

        // not found
        return false;
        // if component finds false in its $Value it must assume that HTTP submission WAS
        // happen but WITHOUT control's field, so it must NOT return $InitValue on GetValue
    }


    /**
     * Collect validation rules for client-side (javascript) validation.
     * It is called from each control's RenderElement
     *
     * @param string $FieldName
     * @param string $Validation
     */
    public function AddJsValidation($FieldName, $Validation) {

        $this->JsValidations[$FieldName]= $Validation;
    }


    //-----------------------------------------------------------------
    //
    //                  Validation of form submision
    //
    //-----------------------------------------------------------------


    /**
     * Check does values submitted via HTTP belongs to this form or not.
     * It will internaly call SetHttpValues() if not called before.
     *
     * @return boolean
     */
    public function IsSubmitted() {

        if ($this->HttpValues === null) {
            // HTTP submision's data are not imported? import them now from Request service.
            $this->SetHttpValues();
        }
        return isset($this->HttpValues['_FormName'])
            && $this->HttpValues['_FormName'] === $this->Name;
    }



    /**
     * Call each control's validation and return true if all of them return true.
     *
     * After calling this method list of errors are available by GetErrors().
     *
     * It will internaly call SetHttpValues() if not called before.
     *
     * Note that this will not check owner of submitted data, to confirm ownership
     * call IsSubmitted() before this method.
     *
     * @return boolean
     */
    public function Validate() {

        if ($this->HttpValues === null) {
            // HTTP submision's data are not imported? import them now!
            $this->SetHttpValues();
        }
        $Context=
            array('_Form'=> $this) // allow validators to access other form controls
            + $this->CustomValidationValues
            + $this->HttpValues;
        $Valid= true;
        foreach($this->Controls as $Control) {
            if ($Control->IsValidScenario()) {
                $Valid= $Valid && $Control->Validate($Context);
            }
        }
        return $Valid;
    }


    /**
     * Use CustomValidationValues to inject values which are not part of current form
     * but needed for comparation in validator.
     * For example "SkipIf" rule can access external values this way.
     *
     * @param array $Values
     */
    public function SetCustomValidationValues($Values) {

        $this->CustomValidationValues= (array)$Values;
    }


    /**
     * Control's setter function.
     * This method is called by controls to output validation errors.
     * Developer should render that messages above whole form manualy,
     * using $Form->GetErrors() to fetch them.
     *
     * @param string $Message
     */
    public function AddError($Message) {

        $this->ValidationErrors[]= $Message;
    }


    /**
     * Return array of validation messages.
     * Array is not indexed (keyed), it is just informative messages.
     * To get errors for particular control use: $Form->GetControl('Country')->GetErrors()
     *
     * @return array
     */
    public function GetErrors() {

        return $this->ValidationErrors;
    }



    //-----------------------------------------------------------------
    //
    //                      Diff utility
    //
    //-----------------------------------------------------------------


    /**
     * Compare all values in controls and make list of changed items, for log.
     * It can return nice formated and labeled output string or array of discrete values.
     *
     * @return array|string
     */
    public function Diff($Labeled=true) {

        $DiffResults= array();
        foreach($this->Controls as $Name=>$Control) {
            if (!$Control->IsValidScenario()) {
                continue;
            }
            $ControlDiff= $Control->Diff($Labeled);
            if ($ControlDiff === false) {
                continue;
            }
            $DiffResults[$Name]= $ControlDiff;
        }
        return $Labeled
           ? implode(', ', $DiffResults)
           : $DiffResults;
    }




}



?>