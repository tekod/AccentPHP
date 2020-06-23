<?php namespace Accent\AccentCore\ArrayUtils;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

use \Accent\AccentCore\Component;


class ArrayUtils extends Component {


    /**
     * Smarter variant of array_merge function, overwriting nodes only if necessary.
     *
     * @param array  first input array
     * @param array  second input array
     * @param array  third input array
     * @param array  fourth input array
     * @param array  fifth input array
     * @return array  merged all arrays
     */
    public function SmartMerge($Array1, $Array2 /*,$Array3,$Array4,..*/) {

        $Merged= array();
        foreach(func_get_args() as $ArgumentKey => $Array) {
            if (!is_array($Array)) {
                $this->FatalError('ArrayUtils:SmartMerge: argument['.$ArgumentKey.'] is not an array.');
                continue;
            }
            if (empty($Array)) continue;
            foreach ($Array as $Key => $Value) {
                if (!is_string($Key)) {   // numerical indexes are just appended
                    $Merged[]= $Value;
                    continue;
                }
                $Merged[$Key]= (is_array($Value) && !empty($Value) && isset($Merged[$Key]) && is_array($Merged[$Key]))
                    ? $this->SmartMerge($Merged[$Key], $Value) // recursion
                    : $Value;
            }
        }
        return $Merged;
    }


    /**
     * Returns array of values from specified column in each row in $Arr
     *
     * @param array of arrays
     * @param mixed, key of specified column, or ommited for first column
     * @param boolean, apply array_unique on result
     * @return array
     */
    function GetColumn($Arr, $Column=null, $MakeUnique=true) {

         $Res= array();
         if (!is_numeric(key($Arr))) {
            foreach($Arr as $a) {
                $Res[]= $Column===null
                    ? reset($a)
                    : (isset($a[$Column]) ? $a[$Column] : null);
            }
         } else {
            for( $x=0, $xmax=count($Arr); $x<$xmax; $x++ ) {
                $Res[]= $Column===null
                    ? reset($Arr[$x])
                    : (isset($Arr[$x][$Column]) ? $Arr[$x][$Column] : null);
            }
         }
         return ($MakeUnique) ? array_unique($Res) : $Res;
    }


    /**
     * Returns array of key-value pairs (map) using values from supplied array.
     *
     * @param array $Array  input multidimensional array
     * @param string|int $ColumnKey  column to use for keys
     * @param string|int $ColumnValue  column to use for values
     * @return array  one-dimensional array, indexed
     */
    public function GetMap($Array, $ColumnKey, $ColumnValue) {

        $Result= array();
        foreach ($Array as $Row) {
            $Result[$Row[$ColumnKey]]= $Row[$ColumnValue];
        }
        return $Result;
    }


    /**
	 * Search for first lower value within array.
	 *
	 * @param array $Array  the array of values
	 * @param mixed $Value  searching value to use as reference
	 * @param bool $ReturnKey  return founded value or its key in array
	 * @return  mixed  founded value in the array, null if there is no previous value or empty array
	 */
	public function FindPreviousByValue($Array, $Value, $ReturnKey=false) {

		if (!is_array($Array)) {
			$this->FatalError('Argument must be an array.');
		}

        // ensure uniqueness and value existace to allow searching for nearby element
        $Array[]= $Value;
        $Array= array_unique($Array);

        // sort with preserving keys
        natcasesort($Array);

        // perform search
        $ArrayValues= array_values($Array);
        $SearchPos= array_search($Value, $ArrayValues);
        if ($SearchPos === 0) {
            // there is no previous value becouse it is lowest value or array is empty
            return null;
        }

        // return previous value or key of previous value
        if ($ReturnKey) {
            $ArrayKeys= array_keys($Array);         //   var_dump($ArrayKeys);die();
            return $ArrayKeys[$SearchPos-1];
        } else {
            return $ArrayValues[$SearchPos-1];
        }
	}


    /**
	 * Search for first higher value within array.
	 *
	 * @param array $Array  the array of values
	 * @param mixed $Value  searching value to use as reference
	 * @param bool $ReturnKey  return founded value or its key in array
	 * @return  mixed  founded value in the array, null if there is no next value or empty array
	 */
	public function FindNextByValue($Array, $Value, $ReturnKey=false) {

		if (!is_array($Array)) {
			$this->FatalError('Argument must be an array.');
		}

        // ensure uniqueness and value existence to allow searching for nearby element
        $Array[]= $Value;
        $Array= array_unique($Array);

        // sort with preserving keys
        natcasesort($Array);

        // perform search
        $ArrayValues= array_values($Array);
        $SearchPos= array_search($Value, $ArrayValues);
        if ($SearchPos === count($Array)-1) {
            // there is no next value because it is higher value or array is empty
            return null;
        }

        // return previous value or key of previous value
        if ($ReturnKey) {
            $ArrayKeys= array_keys($Array);
            return $ArrayKeys[$SearchPos+1];
        } else {
            return $ArrayValues[$SearchPos+1];
        }
	}


    /**
     * Enclosing array keys like "Username" into "Form[Username]".
     * Note that UnEncloseKeys will return ONLY these values with valid prefix.
     *
     * @param string $Prefix
     * @param array $Values
     * @return array
     */
    public function EncloseKeys($Prefix, $Values) {

        if (!is_array($Values)) {
            return array();
        }
        $Out= array();
        foreach($Values as $k=>$v) {
            $Out[$Prefix.'['.$k.']']= $v;
        }
        return $Out;
    }

    /**
     * Restoring back from EncloseKeys-
     *
     * @param array $Values
     * @param string $Prefix
     * @return array
     */
    public function UnEncloseKeys($Values, $Prefix) {

        $Out= array();
        $Prefix .= '[';
        $Pos= strlen($Prefix);
        foreach($Values as $k=>$v) {
            if (substr($k,0,$Pos) === $Prefix) {
                $Out[substr($k,$Pos,-1)]= $v;
            }
        }
        return $Out;
    }


    /*******************************************************
     *
     *                JSON format converters
     *
     *******************************************************/

    /**
     * Serialize array into JSON string.
     *
     * @param array $Array
     * @return string
     */
    public function ArrayToJson($Array) {

        // using native json_encode
        return json_encode($Array, JSON_UNESCAPED_UNICODE);
    }


    /**
     * Unserialize string into array.
     *
     * @param string $Dump
     * @return bool|mixed
     */
    public function JsonToArray($Dump) {

        $Array= json_decode($Dump, true, 128, JSON_BIGINT_AS_STRING);
        if (!$Array) {
            switch(json_last_error()) {
                case JSON_ERROR_DEPTH: $ErrMsg= 'maximum stack depth exceeded';break;
                case JSON_ERROR_CTRL_CHAR: $ErrMsg= 'control character error, wrong encoding?';break;
                case JSON_ERROR_SYNTAX: $ErrMsg= 'syntax error';break;
                case JSON_ERROR_STATE_MISMATCH: $ErrMsg= 'invalid or malformed JSON';break;
                case JSON_ERROR_UTF8: $ErrMsg= 'malformed UTF-8, wrong encoding?';break;
                default: $ErrMsg= 'unknown error.'; d($Dump); d($Array);
            }
            $this->FatalError("JSON: $ErrMsg");
            return false;
        }
        return $Array;
    }


    /*************************************************************
     *
     *                  YAML format converters
     *
     *************************************************************/

    protected function IncludeOnceSpyc() {

        // Spyc class is 3rd party class and it doesn't use namespace.
        // Because there are chances that Spyc can be used by other 3rd party libs
        // it is wise to leave it in namespace where they expect to find it.
        // To relax autoloader we will include class manually because we know its exact location.
        $Class= 'Spyc';
        if (!class_exists($Class, false)) {
            include(__DIR__."/$Class/$Class.php");
        }
    }

    /**
     * Unserialize YAML string to array.
     *
     * @param string $String
     * @return array
     */
    public function YamlToArray($String) {

        $this->IncludeOnceSpyc();
        return \Spyc::YAMLLoadString($String);
    }

    /**
     * Serialize array to YAML string.
     *
     * @param array $Array
     * @return string
     */
    public function ArrayToYaml($Array) {

        $this->IncludeOnceSpyc();
        return \Spyc::YAMLDump($Array, $Indent=false, $WordWrap=false);
    }


    /*************************************************************
     *
     *                   XML format converters
     *
     *************************************************************/


    /**
     * Unserialize XML string into array.
     *
     * @param string $Dump
     * @return bool|mixed
     */
    public function XmlToArray($Dump) {

        // SimpleXML can be disabled using compiling options!
        if (!function_exists('simplexml_load_file')) {
            $this->FatalError('SimpleXML extension not found.');
            return false;
        }
        // create SimpleXML object
        $XML= @simplexml_load_string($Dump);
        if ($XML === false) {
            $this->FatalError('ArrayUtils: Invalid XML structure.');
            $Log= "ArrayUtils.XmltoArray: Failed to parse XML:";
            foreach(libxml_get_errors() as $e) { $Log .= $e->message."; ";}
            $this->TraceError($Log);
            libxml_clear_errors();
            return false;
        }
        // export object into JSON
        $JSON= json_encode($XML);
        // convert JSON in array
        return (version_compare(phpversion(), '5.4.0', '>='))
            ? json_decode($JSON, true, 128, JSON_BIGINT_AS_STRING)
            : json_decode($JSON, true, 128);
    }


    /**
     * Serialize array in XML string.
     *
     * @param $Array
     * @param string $RootTag
     * @return bool|mixed
     */
    public function ArrayToXml($Array, $RootTag='root') {
        // based on: http://stackoverflow.com/questions/1397036/how-to-convert-array-to-simplexml

        // SimpleXML can be disabled using compiling options!
        if (!function_exists('simplexml_load_file')) {
            $this->FatalError('SimpleXML extension not found.');
            return false;
        }
        $XML= new \SimpleXMLElement("<?xml version=\"1.0\"?><$RootTag></$RootTag>");
        // append children using recursion
        $this->ArrayToXml_AddChilds((array)$Array,$XML);
        // convert to XML string and return it
        return $XML->asXML();
    }

    protected function ArrayToXml_AddChilds($Array, &$Xml) {

        foreach($Array as $key=>$value) {
            $NormKey= is_numeric($key) ? "Item$key" : "$key";
            if (is_array($value)) {
                $SubNode= $Xml->addChild($NormKey);
                $this->ArrayToXml_AddChilds($value, $SubNode);
            } else {
                $Xml->addChild(htmlspecialchars($NormKey), htmlspecialchars("$value"));
            }
        }
    }


    /*************************************************************
     *
     *                   INI format converters
     *
     *************************************************************/

    /**
     * Serialize array to INI-file-format.
     *
     * @param array $Array
     * @param array $Parent  internal, do not use it
     * @return string
     */
    public function ArrayToIni($Array, $Parent=array()) {

        $Indent= empty($Parent) ? '' : '  ';
        // we must reorder values because INI format cannot close opened section,
        // so all scalar values must go before arrays
        $Scalars= array();
        $Sections= array();
        foreach($Array as $Key=>$Value) {
            if (is_array($Value)) {
                // echo section header & go into recursion
                $SectionName= array_merge((array)$Parent, (array)$Key);
                if (empty($Parent)) {
                    $Sections[]= '['.$Key.']';
                }
                $Sections[]= $this->ArrayToIni($Value, $SectionName);
            } else {
                $EscapedValue= (is_numeric($Value))
                    ? $Value
                    : '"'.str_replace('"','\\"',$Value).'"';
                $KeyName= count($Parent) < 2
                    ? $Key
                    : $Parent[1].'['.implode('][',array_merge(array_slice($Parent,2),array($Key))).']';
                $Scalars[]= $Indent.$KeyName.'='.$EscapedValue;
            }
        }
        return implode(PHP_EOL, array_merge($Scalars, $Sections));
    }

    /**
     * Unserialize INI-file-format string to array.
     *
     * @param string $Dump
     * @return array
     */
    public function IniToArray($Dump) {

        return parse_ini_string($Dump, true);
    }

}

