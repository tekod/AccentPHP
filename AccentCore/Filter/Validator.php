<?php namespace Accent\AccentCore\Filter;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Service Validator provides tool for checking contents of provided variables.
 *
 * Validating functions accept three parameters:
 *   1. value which need to be validated
 *   2. optional parameter to influence comparison process
 *   3. instance of validator service to be accessed from external validators
 *
 * Context is buffer with all values needed to participate in validation process,
 * using context validator can compare its testing value (first param) against any
 * other input value within complex task,
 * for example to check are two password inputs are same in registration form.
 */

use \Accent\AccentCore\Component;


class Validator extends Component {


    // list of registered validators
    protected $Validators= array();

    // default options
    protected static $DefaultOptions= array(
        'Services'=> array(
            'UTF'=> 'UTF',
            'Localization'=> 'Localization',
        ),
    );

    // buffer for ValidateAll result
    protected $Failures= array();

    // buffer for ValidateAll context
    protected $Context= array();


    /**
     * Constructor
     */
    public function __construct($Options=array()) {

        parent::__construct($Options);

        // enumerate built-in validators
        foreach(get_class_methods(__CLASS__) as $Method) {
            if (substr($Method, 0, 10) <> 'Validator_') {
                continue;
            }
            $this->Add(substr($Method,10), array($this, $Method));
        }
    }

    /**
     * Registration of new validator.
     * Example:$Validator->Add('UniqueId', 'valid_unique_id');
     *
     * @param string $ValidatorName  case-sensitive name of validator
     * @param string|array|closure $Callable  function which return boolean as result
     * @return self
     */
    public function Add($ValidatorName, $Callable) {

        $this->Validators[$ValidatorName]= $Callable;
        return $this;
    }


    /**
     * Return list of all installed validators.
     */
    public function GetValidatorsList() {

        return array_keys($this->Validators);
    }


    /**
     * External validators can reach "context" by this method.
     *
     * @param string $Key
	 * @return mixed
     */
    public function GetContextValue($Key) {

        return isset($this->Context[$Key])
            ? $this->Context[$Key]
            : false;
    }


    /**
     * Perform validation check using specified validator.
     * Placing prefix "!" in front of validator name will invert result of checking.
     *
     * @param mixed $TestVar  input variable which need to be validated
     * @param string $Name  case-sensitive name of validator
     * @param mixed $Param  optional
     * @return mixed
     */
    public function Validate(&$TestVar, $Name, $Param=null) {

        // extract optional prefix
        $Prefix= ($Name) ? substr($Name,0,1) : '';
        if (in_array($Prefix, array('!'))) {
            $Name= substr($Name, 1);
        }
        if (!isset($this->Validators[$Name])) {
            $this->Error('Validator: unknown validator "'.$Name.'"');
            return false; // for security reasons return false
        }
        $Callable= $this->Validators[$Name];
        $Result= call_user_func_array($Callable, array($TestVar, $Param, $this));

        return ($Prefix === '!')
            ? !$Result
            : $Result;
    }


    /**
     * Perform validation using multiple-in-line validation rules.
     *
     * @param mixed $Var  input variable which need sanitization
     * @param string $Validators  '|'-separated list of rules
     * @param mixed $Context  optional, inputs of whole form, for referencing purposes
     * @return array  list of validators that are not passed
     */
    public function ValidateAll($Var, $Validators, $Context=array()) {

        $this->Failures= array();
        $this->Context= $Context;
        // separate line by '|'
        foreach(explode('|',$Validators) as $ValidatorEntry) {
            // each validator consist of name and optional parameter separated by ':'
            $ValidatorParts= explode(':', $ValidatorEntry, 2) + array('','');
            $Name= $ValidatorParts[0];
            if ($Name === '') {
                continue;    // no name ?
            }
            //echo"<br>Entry:$FilterEntry, Name:$Name, Param:$Param,";
            $Result= $this->Validate($Var, $Name, $ValidatorParts[1]);
            // should skip rest of validators?
            if ($Result === null) {
                break;
            }
            // append failure to list
            if ($Result === false) {
                $this->Failures[]= ltrim($Name, '!');
            }
        }
        return $this->Failures;
    }



    //  ----------------------------
    //      built-in validators
    //  ----------------------------


    /**
     * Compare value with provided constant value
     */
	public function Validator_Equal($Value1, $Value2) {

		return ($Value1 === $Value2);
	}


	/**
	 * Validate email address
	 */
	public function Validator_Email($Email) {

		// '\\w+([-+.]\\w+)*@\\w+([-.]\\w+)*\\.\\w+([-.]\\w+)*'
		// '[[:alnum:]][a-z0-9_.-]*@[a-z0-9.-]+.[a-z]{2,6}'
		$Pattern= '[-_a-z0-9\'+*$^&%=~!?{}]++(?:\.[-_a-z0-9\'+*$^&%=~!?{}]+)*+@'
          .'(?:(?![-.])[-a-z0-9.]+(?<![-.])\.[a-z]{2,6}|\d{1,3}(?:\.\d{1,3}){3})(?::\d++)?';
        if (strlen($Email) > 256) {return false;} // prevent DoS
		return preg_match("/^$Pattern$/iD", $Email) === 1;
	}


	/**
	 * Checks is string valid named email adress like 'My name<myname@myhost.com>'
	 */
	public function Validator_EmailNamed($Email) {

		$Pattern= '[-_a-z0-9\'+*$^&%=~!?{}]++(?:\.[-_a-z0-9\'+*$^&%=~!?{}]+)*+@'
          .'(?:(?![-.])[-a-z0-9.]+(?<![-.])\.[a-z]{2,6}|\d{1,3}(?:\.\d{1,3}){3})(?::\d++)?';
        if (strlen($Email) > 256) {return false;} // prevent DoS
		return preg_match("/^[^@]*<$Pattern>$/iD", $Email) === 1;
	}


    /**
     * Validates that content is valid UTF-8 string.
     */
	public function Validator_UTF8($text) {

		return ($text=='') ? true : (preg_match('/^./us', $text) === 1);
	}


    /**
     * Validate that value is IN range of values, supplied in form "min..max".
     * Works both for numbers and strings.
     */
	public function Validator_InRange($Value, $Limits) {

	    list($Min, $Max)= explode('..', $Limits);
	    if ((is_numeric($Min) and (is_numeric($Max)))) {
            // cast in numeric comparation
		    $Min= floatval($Min);
		    $Max= floatval($Max);
		    $Value= floatval($Value);
		}
		return (($Value >= $Min) and ($Value <= $Max));
	}

    /**
     * Check is value "less then or equal" specified value.
     * Works both for numbers and strings.
     */
	public function Validator_Max($Value, $Num) {

        return $Value <= $Num;
	}

    /**
     * Check is value "greater then or equal" specified value.
     * Works both for numbers and strings.
     */
	public function Validator_Min($Value, $Num) {

        return $Value >= $Num;
	}


    /**
     * Check is value in set of allowed comma-separated values.
     */
	public function Validator_In($Value, $Values) {

        if (!is_array($Values)) {
            $Values= explode(',', $Values);
        }
		return in_array($Value, $Values);
	}


    /**
     * Compare length of string with supplied value.
     * UTF8 aware method.
     */
	public function Validator_Len($Value, $Length) {

        $UTF= $this->GetService('UTF');
	    return $UTF->strlen($Value) === intval($Length);
	}


    /**
     * Check is length of string equal or less then specified value.
     * UTF8 aware method.
     */
	public function Validator_LenMax($Value, $Num) {

        $UTF= $this->GetService('UTF');
        return $UTF->strlen($Value) <= $Num;
	}

    /**
     * Check is length of string equal or greater then specified value.
     * UTF8 aware method.
     */
	public function Validator_LenMin($Value, $Num) {

        $UTF= $this->GetService('UTF');
        return $UTF->strlen($Value) >= $Num;
	}


    /**
     * Check is length of string in allowed range.
     * UTF8 aware method.
     */
	public function Validator_LenRange($Value, $Limits) {

        $UTF= $this->GetService('UTF');
	    $Range= array_map('intval', explode('..', $Limits));
	    $Length= $UTF->strlen($Value);
	    if (count($Range) == 1) {
	        $Range[1]= $Range[0];
	    }
		return (($Range[0] <= $Length) and ($Length <= $Range[1]));
	}


    /**
     * Validate value against regular expresion.
     * Note: this method is raw regex and therefore not UTF8 aware.
     * Note: ensure limiting length of $Value to prevent DoS.
     */
	public function Validator_RegEx($Value, $Pattern) {

		return preg_match($Pattern, $Value) === 1;
	}


	/**
	 * Checks if a string contains only safe characters for URLs, file names or directories.
     * This is not applicable for path validation.
	 */
	public function Validator_FileName($Value) {
        // '&', '=', ' ' may produce problems when linking them via URL
        // should I allow ':' ?
        if (strlen($Value) > 256 || in_array($Value, array('', '.', '..'))) {
            return false;
        }
        $Purified= preg_replace('/[^A-Za-z0-9~_.!\|-]/', '', $Value);
		return $Purified === $Value;
	}


	/**
	 * Checks if a string contains valid URL structure
	 */
	public function Validator_URL($Value) {
        // based on: http://flanders.co.nz/2009/11/08/a-good-url-regular-expression-repost/
	    $UrlPattern='`^(?#Protocol)(?:(?:ht|f)tp(?:s?)\:\/\/|~\/|\/)?(?#Username:Password)(?:\w+:\w+@)?(?#Subdomains)(?:(?:[-\w\d]+\.)+(?#TopLevel Domains)(?:com|org|net|gov|mil|biz|info|mobi|name|aero|jobs|museum|travel|[a-z]{2}))(?#Port)(?::[\d]{1,5})?(?#Directories)(?:(?:(?:\/(?:[-\w~!$+|.,=]|%[a-f\d]{2})+)+|\/)+|\?|#)?(?#Query)(?:(?:\?(?:[-\w~!$+|.,*:]|%[a-f\d{2}])+=?(?:[-\w~!$+|.,*:=]|%[a-f\d]{2})*)(?:&(?:[-\w~!$+|.,*:]|%[a-f\d{2}])+=?(?:[-\w~!$+|.,*:=]|%[a-f\d]{2})*)*)*(?#Anchor)(?:#(?:[-\w~!$+|/.,*:=]|%[a-f\d]{2})*)?$`';
        if (strlen($Value) > 1200) {return false;} // prevent DoS
		return preg_match($UrlPattern, $Value) === 1;
	}


    /**
     * Check can value be interpreted as valid date.
     */
	public function Validator_Date($Value, $Format) {

        if (!$Value) {
            return false;
        }
        if (is_array($Value)) {
            return checkdate($Value[1], $Value[2], $Value[0]);
        }
	    if (!$Format) {
            return (strtotime($Value) !== FALSE);
        }
        if (!function_exists('date_parse_from_format')) {
            return false;
        }
        $Arr= date_parse_from_format($Format, $Value);
        return $Arr['error_count'] == 0;
	    //TODO: use Localization to compare $Value with $Format,
	}


    /**
     * Validate that variable cannot be empty string or empty array.
     */
	public function Validator_Required($Value) {

		return (is_array($Value)) ? !empty($Value) : $Value<>'';
	}


    /**
     * Confirm that value represent valid IPv4 only address.
     */
	public function Validator_IPv4($Value) {

        if ($Value === 'localhost') {
            return true;
        }
	    $Arr= array_map('intval', explode('.', $Value));
	    if (count($Arr) <> 4) {
            return false;
        }
		foreach($Arr as $int) {
            if ($int < 0 || $int > 255) {return false;}
        }
		return (implode('.',$Arr) === $Value);
	}

    /**
     * Confirm that value represent valid IPv4 or IPv6 address.
     */
	public function Validator_IP($Value) {

        if ($Value === 'localhost') {
            return true;
        }
        if (strlen($Value) > 45) {
            return false;
        }
        return filter_var($Value, FILTER_VALIDATE_IP/*, FILTER_FLAG_IPV6*/);
	}


	/**
	 * Checks whether a string consists of alphabetical characters only.
	 */
	public function Validator_Alpha($Value) {

		return ($Value === '')
            ? true
            : preg_match('/^\pL++$/uD', $Value) === 1;
	}

	/**
	 * Checks whether a string consists of alphabetical characters and numbers only.
	 */
	public function Validator_Alnum($Value) {

		return ($Value === '')
            ? true
            : preg_match('/^[\pL\pN]++$/uD', $Value) === 1;
		// with dash: '/^[-\pL\pN_]++$/uD'
	}


	/**
	 * Checks whether a string is a valid integer.
	 * Untrimed string is allowed.
	 */
	public function Validator_Integer($Value) {

		return ctype_digit(trim($Value));
	}


	/**
	 * Checks whether a string is a valid float
	 * Untrimed string is allowed.
	 */
	public function Validator_Float($Value) {

        return preg_match('/^-?(?:\d+|\d*\.\d+)$/', trim($Value)) === 1;
	}



	/**
	 * Validates a credit card number using the Luhn (mod10) formula.
	 * @see http://en.wikipedia.org/wiki/Luhn_algorithm
	 *
	 * @param integer       credit card number
	 * @param string        card type
	 * @return bool
	 */
	public function Validator_CreditCard($Number, $Type=null) {
	    // in $Debug mode it returns empty string on valid CC number or message with error text
	    $Types= array (  //        length,               regex prefix,               use luhn
	       'default'   => array('13,14,15,16,17,18,19',  '',                         true),
	       'AmExpress' => array('15',                    '3[47]',                    true),
	       'Diners'    => array('14,16',                 '36|55|30[0-5]',            true),
	       'Discover'  => array('16',                    '6(?:5|011)',               true),
	       'JCB'       => array('15,16',                 '3|1800|2131',              true),
	       'Maestro'   => array('16,18',                 '50(?:20|38)|6(?:304|759)', true),
	       'Mastercard'=> array('16',                    '5[1-5]',                   true),
	       'Visa'      => array('13,16',                 '4',                        true),
	    );

	    if (!$Type) { // Use the default type
			$Type= 'default';
		}
        if (!isset($Types[$Type])) {return false;}

		// Get params
		list($CardLengths, $CardPrefix, $CardLuhn)= $Types[$Type];

		// Remove all non-digit characters from the number
		if (($Number= preg_replace('/\D+/', '', $Number)) === '') {
            return false;
        }

		// Validate the card length by the card type
		$Length = strlen($Number);
		if (!in_array(strval($Length), explode(',', $CardLengths))) {
            return false;
        }

		// Check card number prefix
		if (preg_match("/^$CardPrefix/", $Number) !== 1) {
            return false;
        }

		// No Luhn check required
		if ($CardLuhn == false)	{
            return true;
        }

		// Checksum of the card number
		$Checksum= 0;
		for ($i=$Length-1; $i>=0; $i-=2) { // Add up every 2nd digit, starting from the right
			$Checksum += $Number[$i];
		}
		for ($i=$Length-2; $i>=0; $i-=2) { // Add up every 2nd digit doubled, starting from the right
			$Double= $Number[$i] * 2;
			// Subtract 9 from the double where value is greater than 10
			$Checksum += ($Double>=10) ? $Double-9 : $Double;
		}
		// If the checksum is a multiple of 10, the number is valid
        $Valid= ($Checksum % 10 === 0);
		return $Valid;
	}


	/**
	 * Checks if a string is a proper decimal format.
	 * The format to specify 4 digits and 2 decimal places should be '4.2'
	 * Note: mysql limit for formatting is '65.30'
	 *
	 * @param string   input string
	 * @param string   decimal format: '6.2'
	 * @return bool
	 */
	public function Validator_Decimal($Value, $Params) {

	    if (preg_match('/^[0-9]+(\.)?[0-9]*$/', strval($Value)) !== 1) {
	       return false;
        }
		list($Len1,$Len2)= explode('.', "$Params."); // ensure atleast one '.'
		list($M, $D)= explode('.', "$Value.");
		$M= ($M) ? strlen(strval(intval($M))) : 0;
		$D= ($D) ? strlen(strval(intval($D))) : 0;    //echo "<br>$Value:$M,$D ($Len1,$Len2)<br>";
		return ($M+$D <= intval($Len1)) and ($D <= intval($Len2));
	}


	/**
	 * Checks if a string contains only numbers,
	 * it is similary as Integer method but for huge numbers and much slower
	 */
	public function Validator_Digits($Value) {

		return preg_match('/^[+-]?\d+$/', $Value) === 1;
	}


	/**
	 * Call external function to determine validity
	 * to call static method use something like 'Validators::Alpha'
	 */
	public function Validator_Func($Value, $FuncName) {

	    $Func= explode('::',$FuncName);
		if ((!isset($Func[1]))and(is_callable($Func[0]))) {
		  return $Func[0]($Value);
		} else if ((isset($Func[1])and(is_callable($Func))))  {
		  return call_user_func_array($Func, array($Value));
		}
		return false;
	}


    /**
     * Skip validation chain depending on value of another form's field.
     * Example:
     * Form has fields: UserType, Name, Ages, ParentName
     * Validation for ParentName: SkipIf:UserType:Equal:firm|SkipIf:Ages:Min:18|Required
     * ParentName will not be checked for "Required" if UserType=="firm" OR Ages>=18
     */
    public function Validator_SkipIf($Value, $Rule) {

        list($ReferenceName, $ValidatorName, $ValidatorOpts)=
            explode(':', $Rule, 3) + array('','','');

        $ReferenceValue= isset($this->Context[$ReferenceName])
            ? $this->Context[$ReferenceName]
            : ''; // control has not value (unsuccessfull control)

         if ($ValidatorName === '') {
            return false; // unknown validator, signal error
        }

        $Result= $this->Validate($ReferenceValue, $ValidatorName, $ValidatorOpts);
        $Value= null;  // silence IDE

        return $Result === true
            ? null  // skip rest of validators
            : true; // silently go to next validator in chain
    }


    /**
     * This will terminate ValidateAll's chain of validations if failures occurs.
     * EOF states for ExitOnFailure.
     */
    public function Validator_EOF() {

        return empty($this->Failures)
            ? true
            : null; // returning null will skip rest of validation checks
    }


    /**
     * Compare value with value of another form element.
     * This validator is expected to be used in form processing (class Form).
     * 3rd parameter will automatically be added by Form::Validate as element object.
     * Comparison is made with '===' to avoid: '' == '0'.
     *
     * @param string  input string
     * @param string  name of form element which value will be compared with $Value
	 * @return bool
     */
    public function Validator_SameInput($Value, $ReferenceName) {

        if (!isset($this->Context[$ReferenceName])) {
            return false;
        }
        return $Value === $this->Context[$ReferenceName];
    }


} // End validator class
