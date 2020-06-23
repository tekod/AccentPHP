<?php namespace Accent\AccentCore\Filter;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Sanitizer service offers support for common security tasks:
 *  - sanitizing user-supplied-variables to make them safe for further processing
 *  - escaping non-safe characters for unharmful usage in output
 *
 * Typical usage:
 * $Service= $this->GetService('Sanitizer');
 * echo $Service->Sanitize($Input, 'I');
 * echo $Service->Sanitize($Input, 'I|Range:1..4'); // integer in range [1,2,3,4]
 * echo $Service->Sanitize($Input, 'Local|Float');  // delocalized float number (decimal delimiters)
 * echo $Service->Sanitize($Input, 'CU|T|Len:12');  // trimed, upercassed, limited length on 12 chars
 *
 * echo $F->EscapeHTML($Input);               // prevent HTML messing (Anti-XSS protection)
 */

use \Accent\AccentCore\Component;


class Sanitizer extends Component {


    protected static $DefaultOptions= array(
        'Services'=> array(
            'UTF'=> 'UTF',
            'Localization'=> 'Localization',
        ),
    );

    protected $Sanitizers;

    protected $SanitizersAliases= array(
        'T'=>'Trim',        'I'=>'Integer',
        'F'=>'Float',       '@'=>'UrlDecode',
        'CU'=>'CaseUpper',  'CL'=>'CaseLower',
    );

    //protected $Localizator;


    /**
     * Constructor
     */
    public function __construct($Options=array()) {

        parent::__construct($Options);

        // enumerate built-in sanitizers
        foreach(get_class_methods(__CLASS__) as $Method) {
            if (substr($Method, 0, 9) === 'Sanitize_') {
                $this->Sanitizers[strtoupper(substr($Method,9))]= array($this, $Method);
            }
        }
        foreach($this->SanitizersAliases as $Key=>$Value) {
            $this->Sanitizers[$Key]= array($this, 'Sanitize_'.$Value);
        }
    }


    /**
     * Registration of new sanitizer
     * example: $sanitizer->Add('TrimRight', 'Sanitize_trimright');
	 *
	 * @param string $sanitizerName
	 * @param callable $Callable
     */
    public function Add($sanitizerName, $Callable) {

        $this->Sanitizers[strtoupper($sanitizerName)]= $Callable;
    }


    /**
     * Perform sanitization of variable by applying various sanitizers.
     *
     * @param mixed $Var  input variable which need sanitization
     * @param string $Rules  '|'-separated list of sanitizers
     * @return mixed
     */
    public function Sanitize($Var, $Rules) {

        foreach(explode('|',$Rules) as $Rule) {
            // multiple rules are separated by '|'
            $Parts= explode(':', $Rule, 2) + array('','');
            // each rule consist of name and (optional) parameters separated by ':'
            if ($Parts[0]==='') {
                continue;    // no sanitizer, continue without modifications
            }
            $Var= $this->ApplySanitizer(strtoupper($Parts[0]), $Var, $Parts[1]);
        }
        return $Var;
    }


	/**
	 * Perform sanitization on variable.
	 *
	 * @param $Name
	 * @param $Var
	 * @param $Param
	 * @return mixed
	 */
    protected function ApplySanitizer($Name, $Var, $Param) {

        // get callable
        if (!isset($this->Sanitizers[$Name])) {
            $this->Error('Sanitizer: unknown sanitizer "'.$Name.'"');
            return false; // for security reasons destroy variable and return false
        }
        $Callable= $this->Sanitizers[$Name];
        if (!is_array($Var)) {
            // for scalar $Var execute callable and return its result
            $Var= call_user_func_array($Callable, array($Var, $Param));
        } else {
            // for array $Var apply callable on each element
            foreach($Var as $k => &$v) {
                $v= call_user_func_array($Callable, array($v, $Param)); // $v is reference
            }
        }
        return $Var;
    }



    //  ----------------------------
    //      built-in sanitizers
    //  ----------------------------


	/**
	 * Returns integer value.
	 */
	public function Sanitize_Integer($Value) {

		return intval($Value);
	}


	/**
	 * Returns trimmed value.
	 */
	public function Sanitize_Trim($Value) {

		return trim($Value);
	}


	/**
	 * Returns float value.
	 */
	public function Sanitize_Float($Value) {

		return floatval($Value);
	}


    /**
	 * Returns de-localized value.
     * This sanitizer should be specified BEFORE 'Float' or 'Integer' sanitizers.
	 */
	public function Sanitize_Local($Value) {

        $Localizator= $this->GetService('Localization');
        if ($Localizator == null) {
            $this->Error('Sanitizer: Localizaton service not supplied.');
            return $Value;
        }
		return $Localizator->UnformatNumber($Value);
	}


    /**
     * Returns JSON-decoded value.
     */
    public function Sanitize_JSON($Value) {

        return json_decode($Value, true, 128, JSON_BIGINT_AS_STRING);
    }


	/**
	 * Returns url-decoded value.
	 */
	public function Sanitize_UrlDecode($Value) {

		return urldecode($Value);
	}


	/**
	 * Returns uppercased value.
	 */
	public function Sanitize_CaseUpper($Value) {

        $UTF= $this->GetService('UTF');
		return ($UTF == null)
            ? strtoupper($Value)
            : $UTF->strtoupper($Value);
	}


	/**
	 * Returns lowercased value.
	 */
	public function Sanitize_CaseLower($Value) {

        $UTF= $this->GetService('UTF');
		return ($UTF == null)
            ? strtolower($Value)
            : $UTF->strtolower($Value);
	}


	/**
	 * Returns string with limited length.
	 */
	public function Sanitize_Len($Value, $Param) {

		$UTF= $this->GetService('UTF');
        $Limit= intval($Param);
        $Len= ($UTF == null)
            ? strlen($Value)
            : $UTF->strlen($Value);
        if ($Len<=$Limit) {
            return $Value;
        }
        return ($UTF == null)
            ? substr($Value,0,$Limit)
            : $UTF->substr($Value,0,$Limit);
	}


	/**
	 * Returns padded string.
	 */
	public function Sanitize_Pad($Value, $Param) {

	    list($Len, $Addons, $Type)= explode(':', $Param) + array('',' ','L');
	    $Types= array('R'=>STR_PAD_RIGHT, 'L'=>STR_PAD_LEFT, 'B'=>STR_PAD_BOTH);
	    return str_pad($Value, intval($Len), $Addons, $Types[$Type]);
	}


	/**
	 * Force value within range.
	 */
	public function Sanitize_Range($Value, $Param) {

	    list($Min,$Max)= array_map('trim', explode('..', $Param)+array('0','0'));
		if ((is_numeric($Min) and (is_numeric($Max)))) {
		    return max(intval($Min), min(intval($Max), $Value));
		}
		if (strnatcmp($Min,$Value)>0) {
		    return $Min;
		}
		if (strnatcmp($Value,$Max)>0) {
		    return $Max;
		}
		return $Value;
	}



	/**
	 * Returns preg_replaced value.
	 * Define param as 'pattern|replace' string.
	 */
	public function Sanitize_PregReplace($Value, $Param) {

	    list($Pattern,$Replace)= explode(':', $Param, 2);
		return preg_replace($Pattern, $Replace, $Value);
	}


	/**
	 * Removes all non alphabetical characters from string.
	 */
	public function Sanitize_Alpha($Value) {

		return preg_replace('/[^\pL]/u', '', $Value);
	}


	/**
	 * Removes from string everything except alphabetical characters and numbers.
	 */
	public function Sanitize_Alnum($Value) {

		return preg_replace('/[^\pL\d]+/u', '', $Value);
	}


	/**
	 * Removes from string everything except numbers.
	 */
	public function Sanitize_Digits($Value) {

		return preg_replace('/[^\d]/', '', $Value);
	}


	/**
	 * Removes from string everything forbiden for file name.
	 */
	public function Sanitize_FileName($Value) {

		return preg_replace('/[^A-Za-z0-9~_!\|\.\-\+]/', '', $Value);
	}


    /**
	 * Decode Base64-coded string.
	 */
	public function Sanitize_Base64($Value) {

		return base64_decode($Value);
	}


    /**
	 * Call external function to determine validity.
	 */
	public function Sanitize_Func($Value, $FuncName) {

		if (!$FuncName) {
			$this->Error('Missing FuncName in "Func" sanitizer');
            return false;  // for security reasons return false
        }
        $AsArray= explode(':', $FuncName, 2);
		return (count($AsArray) == 1)
		  ? $FuncName($Value)
		  : call_user_func_array($AsArray, array($Value));
	}





    /* -------------------------------------------------------------------------
     *                             Escapers
     *---------------------------------------------------------------------------*/


    /**
     * Escaped javascript string will prevent unexpected closing-quote and newlines.
     * By default it is assumed that JS string using double qoutes for termination,
     * but there is additional parameter to modify that and specify all unsafe chars.
     */
    public function EscapeJsString($Content, $UnsafeChars='"') {

        $From= array("\\","\n","\r",);
        $To= array("\\\\"," ", " ");
        foreach (str_split($UnsafeChars, 1) as $Char) {
            $From[]= $Char;
            $To[]= "\\".$Char;
        }
        return str_replace($From, $To, $Content);
    }


    /**
     * Ensures that specified string will not be recognized as HTML content.
     */
    public function EscapeHTML($Content) {

        return htmlspecialchars($Content, ENT_QUOTES, 'UTF-8');
    }


    /**
     * Ensure that specified string will not break out of enclosed <style> scope.
     */
    public function EscapeCSS($Content) {

        return strip_tags($Content);
    }

}
