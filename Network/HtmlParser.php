<?php namespace Accent\Network;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/* HTML parser encapsulate utilities for common tasks in parsing HTML content.

 * Interface of utilities is designed to be used in complex tasks, such as website crawling.
 * For simple tasks it is easier to use primitive PHP functions.
 * Typically parser methods should be arranged in cascade as following:
 *   ...
 *   function ParseDump($Data, &$Output, $Parser) {
 *       // all parsing logic is wrapped here to allow simple "return false" in case of invalid dump
 *       if (!$Parser->ExtractDelimitedString($Data, '<table id="main">', '</table>', 'Error: main table not found')) {
 *           return false;  // terminate further parsing, error message is issued
 *       } // $Data is cropped string now
 *       if (!$Parser->ExtractDelimitedStrings($Data, '<td>', '</td>', 'Error: table must have 6 cells', 6)) {
 *          return false;  // terminate further parsing, error message is issued
 *       } // $Data becomes array
 *       foreach($Data as $Cell) {
 *          if (!$Parser->ExtractAttr($Cell, 'href', 'Error: missing href')) {
 *              return false; // terminate further parsing, error message is issued
 *          }
 *          $Output[]= $Cell; // $Cell becomes href value
 *       }
 *       return true;  // mark parsing as successful
 *   }
 *   function ParseURL($URL) {
 *      $Dump= file_get_content($URL);
 *      $Parser= new HtmlParser;
 *      $Output= array();
 *      if (ParseDump($Dump, $Output, $Parser)) {  // try to parse that $Dump
 *          // send $Output to database
 *      } else {
 *          // cascade is prematurely terminated, there must be some errors, show them
 *          echo '<div class="error">'.implode('<br>',$Parser->GetParsingErrors()).'</div>'; // or send it via email
 *      }
 *   }
 */

use Accent\AccentCore\Component;


class HtmlParser extends Component {


    // buffer for messages about parsing errors
    protected $ParsingErrors= array();


    /**
     * Build DOMDocument object of supplied HTML.
     * Example: echo all links in page content:
     *   $Nodes= $this->CreateDomFromHtml($HTML)->getElementsByTagName('a');
     *   foreach ($Nodes as $Node) {echo $dom->saveHtml($Node);}
     *
     * @param string $HTML
     * @param string $Charset
     * @return DOMDocument|false
     */
    public function CreateDomFromHtml($HTML, $Charset='UTF-8') {

        if (!$HTML) {
            return false;
        }
        $DOM= new \DOMDocument('1.0', $Charset);
        $DOM->loadHTML($this->RemoveBOM($HTML));
        return $DOM;
    }


    /**
     * Build XPath object of supplied HTML.
     * Example: echo all URLs in page content:
     *   $Nodes= $this->CreateXpathFromHtml($HTML)->query('//a/@href');
     *   foreach ($Nodes as $href) {echo $href->nodeValue;}
     *
     * @param string $HTML
     * @param string $Charset
     * @return DOMXPath|false
     */
    public function CreateXPathFromHtml($HTML, $Charset='UTF-8') {

        $DOM= $this->CreateDomFromHtml($HTML, $Charset);
        if (!$DOM) {
            return false;
        }
        return new \DOMXPath($DOM);
        // example 1: for everything with an id: $Elements= $XPath->query("//*[@id]");
        // example 2: for node data in a selected id: $Elements= $XPath->query("/html/body/div[@id='yourTagIdHere']");
        // example 3: same as above with wildcard: $Elements= $XPath->query("*/div[@id='yourTagIdHere']");
    }


    /**
     * Extract part of string between $FromDelimiter and $ToDelimiter.
     * Method will return true if both delimiters are found in source string.
     * Result will be stored in first parameter of method.
     *
     * @param string &$Input  source content
     * @param string $FromDelimiter  left marker
     * @param string $ToDelimiter  right marker
     * @param string $ErrorMsg  issue parsing error msg on missing delimiter (optional)
     * @return bool
     */
    public function ExtractDelimitedString(&$Input, $FromDelimiter, $ToDelimiter, $ErrorMsg=null) {

        $this->ClearParsingErrors();
        // find first delimiter
        $Exp= explode($FromDelimiter, $Input, 2);
        if (count($Exp) < 2) {
            $this->AddParsingError($ErrorMsg, $Input);
            return false;
        }
        // find second delimiter
        $Exp= explode($ToDelimiter, $Exp[1], 2);
        if (count($Exp) < 2) {
            $this->AddParsingError($ErrorMsg, $Input);
            return false;
        }
        // modify original and return true
        $Input= $Exp[0];
        return true;
    }


    /**
     * Search for all sub-strings that are between $FromDelimiter and ToDelimiter.
     * Note that delimiters must not be equal or substring of another.
     * Method will return true on finding at least one result, or if $Count are specified if it matches count of results.
     * Result will be stored in first parameter of method.
     *
     * @param string $Input  source content
     * @param string $FromDelimiter  left marker
     * @param string $ToDelimiter  right marker
     * @param string|null $ErrorMsg  text of parsing error (optional)
     * @param int|null $Count  issue parsing error if not matching count of results (optional)
     * @return bool
     */
    public function ExtractDelimitedStrings(&$Input, $FromDelimiter, $ToDelimiter, $ErrorMsg=null, $Count=null) {

        $this->ClearParsingErrors();
        // explode by left delimiter and remove first result
        $Parts= explode($FromDelimiter, $Input);
        array_shift($Parts);
        // remove contents after right delimiter
        foreach ($Parts as &$Part) {
            $Delimited= explode($ToDelimiter, $Part);
            $Part= reset($Delimited);
        }
        // store result in first parameter
        $Input= $Parts;
        // if required exact count of results
        if ($Count !== null && count($Parts) !== intval($Count)) {
            $this->AddParsingError($ErrorMsg, $Input);
            return false;
        }
        // return true if anything found
        return !empty($Parts);
    }


    /**
     * Search for all sub-strings that are surrounded by single-quote or double-quote.
     * Method will return true if both delimiters are found in source string.
     * Result will be stored in first parameter of method.
     *
     * @param string $Input  source content
     * @return bool
     */
    public function ExtractQuotedStrings(&$Input) {

        $this->ClearParsingErrors();

        // perform regex search
        preg_match_all("/(?<![\\\\])(?:\\\\\\\\)*(['\"])((?:[^\\\\]|\\\\.)*)\\1/U", $Input, $Matches);

        // store result in first parameter
        $Input= $Matches[2];

        // return true if anything found
        return !empty($Input);
    }


    /**
     * Search for attribute in input string and return its value.
     * Value can be surrounded by single-quote or double-quote.
     * Searching will stop on first occurrence of attribute.
     *
     * @param string $Input  source content
     * @param string $Attr  tag attribute
     * @return bool
     */
    public function ExtractAttrs(&$Input, $Attr) {

        // purify attribute name
        $Attr=  preg_replace('/[^a-z0-9_\\-.:]/i', '', $Attr);

        // perform regex search
        preg_match_all("/[^a-z0-9_\\-.:]$Attr\\s*=\\s*(?<![\\\\])(?:\\\\\\\\)*(['\"])((?:[^\\\\]|\\\\.)*)\\1/Ui", $Input, $Matches);

        // store result in first parameter
        $Input= $Matches[2];

        // return true if anything found
        return !empty($Input);
    }



    /**
     * Contract all sequences of multiple space characters into single space character.
     *
     * @param string $String  source content
     * @param bool $IncludingNewLines  compressing will consume newline chars
     * @return string
     */
    public function CompressSpaces($String, $IncludingNewLines=false) {

        if (!is_scalar($String)) {  // prevent "array to string conversion" warning
            $String= var_export($String, true);
        }
        $Pattern= $IncludingNewLines ? '#[\s]+#' : '#[ \t]+#';
        return preg_replace($Pattern, ' ', $String);
    }


    /****************************************************************
     |                            Helpers
     ***************************************************************/


    /**
     * Append message error to buffer.
     * Null messages will be ignored.
     *
     * @param string $Msg  error-message
     * @param string $Input  (optional) if supplied will be append to message
     */
    public function AddParsingError($Msg, $Input=null) {

        if (!is_string($Msg)) {
            return;
        }
        if ($Input !== null) {
              if ($Input === true) {$Input= 'TRUE';}
              else if ($Input === false) {$Input= 'FALSE';}
              else if (is_array($Input)) {$Input= 'ARRAY:'.json_encode($Input);}
              else $Input= '"'.$Input.'"';
            $Msg .= '; Input='.$Input;
        }
        $this->ParsingErrors[]= $Msg;
    }

    public function GetParsingErrors() {

        return $this->ParsingErrors;
    }

    public function ClearParsingErrors() {

        $this->ParsingErrors= array();
    }


    /**
     * Remove annoying Byte-Order-Mark from string.
     *
     * @param string $String
     * @return string
     */
    protected function RemoveBOM($String) {

        return str_replace("\xEF\xBB\xBF", '', $String);
    }


}