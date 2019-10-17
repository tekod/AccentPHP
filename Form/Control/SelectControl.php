<?php namespace Accent\Form\Control;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * 'select' input form element.
 *
 * Like: <select name=""> <option value="1">Europe</option> </select>
 */

use \Accent\Form\Control\BaseControl;


class SelectControl extends BaseControl {


    protected static $DefaultOptions= array(
        'Attributes'=> array(
            'multiple'=> null,
            'size'=> 1,
        ),

        // key=>value list of options
        // values will be translated by Localization service
        // if passed as string it will be translated and exploded by pipe (first item has index 1)
        'List'=> array(),

        // prepend something like "- Select -" or "Choose one:" to options list
        // this is usefull when 'List' instruction is passed as string
        // set value as string to insert translated option like Msg(that string)
        // set value as "true" to insert Msg('EmptyOption#Form') option
        // index of that option will be '0' if first option has numeric key, otherwise '-'
        'EmptyOption'=> false,

        // should select first option as 'default option' if nothing selected
        'AutoSelectFirst'=> true,

        // attributes for all <option> items
        'OptionAttributes'=> array(),

        // attributes for selected <option> item(s)
        'SelectedOptionAttributes'=> array(),
    );

    protected $CanBeReadOnly= false;
    protected $CanBeDisabled= true;

    // internals
    protected $CachedList= array(null=>null, '@'=>null);


    /**
     * Render HTML representation of current form element.
     *
     * @return string HTML
     */
    public function RenderElement() {

        // prepare most important attributes
        $Attr= $this->BuildAttributes(array());
        // prepare value
        $Content= $this->RenderOptionsList(
            $this->GetSelectList(),
            $this->GetSelectValues(),
            $this->GetOption('Attributes.multiple'),
            $this->GetOption('MultiLang'),
            $this->GetOption('Escape'),
            $this->GetOption('AutoSelectFirst'),
            $this->GetOption('OptionAttributes'),
            $this->GetOption('SelectedOptionAttributes')
        );
        // render tag
        return $this->Form->RenderTag('select', $Attr, $Content);
    }


    protected function RenderOptionsList($List, $Values, $IsMultiple, $IsMultiLang, $IsEscape, $IsAutoSelectFirst, $OptionAttributes, $SelectedOptionAttributes) {

        $Rendered= array();
		$Found= false;
        $Count= count($List);
        // process in reverse order to be able to add "select" attribute
        // to first <option> if needed
        foreach (array_reverse($List,true) as $key => $aVal) {
            // prepare content of <option> tag
            $OptAttrs= array('value'=>$key);
            if ($IsMultiLang) {
                // @TODO: make it
                $aVal= MultiLang::Get($aVal);
            }
			$Content= ($IsEscape) ? $this->Escape($aVal) : $aVal;
            // prepare "selected" attribute
			if (($IsAutoSelectFirst && !$Found && --$Count == 0)
              || ((in_array($key,$Values) && ($IsMultiple || !$Found)))) {
                $Found= true;
                $OptAttrs += array('selected'=>'selected') + $SelectedOptionAttributes;
            }
            // append $OptionAttributes to list of attributes and render <option> tag
            $OptAttrs += $OptionAttributes;
      		$Rendered[]= "\n	    ".$this->Form->RenderTag('option', $OptAttrs, $Content);
    	}
        // reverse again and concat all of them
        return implode('', array_reverse($Rendered));
    }


    /*
     * Return array of options based on 'List' option,
     * keys are preserved,
     * values are translated by Localization service.
     */
    protected function GetSelectList($Lang=null) {

        if ($this->CachedList[$Lang] === null) {
            $List= $this->TranslateOptions($Lang);
            $this->CachedList[$Lang]= $this->PrependEmptyOption($List, $Lang);
        }
        return $this->CachedList[$Lang];
    }


    /*
     *
     */
    protected function GetSelectValues() {

        if ($this->Value === false) {
            return array();     // unsuccesffull control, stay blank
        }
        $Values= $this->Value === null
            ? $this->InitValue  // submission has not happen
            : $this->Value;
		if (!is_array($Values)) {
            $Values= ($Values === null) ? array() : array($Values);
        }
        return $Values;
    }


    /*
     * SELECT element has not own its ?_ml element becouse this control cannot be multilang.
     * User can only select key of offered <option>.
     */
    protected function GetMultiLangName() {

        return $this->Name;
    }


    /*
     * Return selected item, translated in backend language
     */
    protected function DiffValueForLog() {

        $List= $this->GetSelectList('@');
        return isset($List[$this->Value]) ? $List[$this->Value] : '';
    }


    /*
     * If validation rule 'Required' detected
     * compare $this->Value with first key in List.
     * If they are equal then clear $this->Value to ensure validation fail.
     */
    public function Validate($Context) {

        $OldValue= $this->Value;
        $Validators= explode('|', $this->GetValidators($this->GetScenario()));
        foreach($Validators as $V) {
            if (strtolower($V) !== 'required') {
                continue;
            }
            $List= $this->GetSelectList();  // list will be cached for later usage
//            $FirstKey= is_array($List)
//                ? reset(array_keys($List)).'' // force string type
//                : '0';
            if (is_array($List)) {
                $FirstKey= key($List);
            } else {
                $FirstKey= '0';
            }
            if ($this->Value === $FirstKey) {
                $this->Value= '';
            }
        }
        // perform validation
        $Ret= parent::Validate($Context);
        // put back old value
        $this->Value= $OldValue;
        // return result of validation
        return $Ret;
    }

/*
    protected function GetValidatorsOld($ForScenario) {

        $Validators= explode('|', parent::GetValidators($ForScenario));
        foreach($Validators as $V) {
            if (strtolower($V) === 'required') {
                $List= $this->GetSelectList();  // list will be cached for later usage
                $FirstKey= is_array($List)
                    ? reset(array_keys($List)).'' // force string type
                    : '0';
                if ($this->Value === $FirstKey) {
                    $this->Value= '';
                }
            }
        }
        return implode('|', $Validators);
    }*/


    /*
     * Return translated options.
     */
    protected function TranslateOptions($Lang) {

        $List= $this->GetOption('List');
        if (is_string($List)) {
            $List= $this->MsgDef('', $List, '|', $Lang);
            $List= array_combine(range(1,count($List)), $List); // renumber keys from 1
        } else {
            if (!is_array($List)) { // something went wrong but don't left list empty
               $List= array($List);
            }
            foreach($List as &$Item) {
                $Item= $this->Msg($Item, null, $Lang);
            }
        }
        return $List;
    }


    /*
     * Insert "empty option" at begining of supplied list of options.
     *
     * That first option will have value '0' if numeric list is detected,
     * otherwise it will be '-'.
     */
    protected function PrependEmptyOption($List, $Lang) {

        $EmptyOption= $this->GetOption('EmptyOption');
        if ($EmptyOption === false) {
            return $List;
        }
        if (empty($List)) {
            // list is empty? guess according to InitValue or Value
            $TestValue= $this->InitValue === false ? $this->Value : $this->InitValue;
        } else {
            // check type of first element's index (usually "0")
            $Keys= array_keys($List);
            $TestValue= reset($Keys);
            //$TestValue= reset(array_keys($List));
        }
        // test is it numerical (as integer)
        // note: we must use custom comaparation becouse:
        //   is_numerical() fails becouse it returns true for "0000000"
        //   is_int becouse() fails becouse it returns false for "1" (it expect integer type)
        //   ctype_digit() fails becouse it returns false for "-1" (all negative numbers)
        $Key= strval(intval($TestValue)) === strval($TestValue) ? '0' : '-';
        // find textual reprensentation
        $TextKey= $EmptyOption === true ? 'EmptyOption#Form' : $EmptyOption;
        $Text= $this->Msg($TextKey, null, $Lang);
        // prepend to list
        return array($Key=>$Text) + $List;
    }

}
?>