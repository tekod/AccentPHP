<?php namespace Accent\AccentCore;

/**
 * RequestContext is data-object, containing values from HTTP request (super-globals).
 * It does not interpret that data, but some normalization will be performed.
 *
 * Components should access to super-globals through this object in order to make testing possible,
 * also this is solution for serving modified values to components within sub-requests.
 */


use \Accent\AccentCore\Component;


class RequestContext extends Component {


    // configuration
    protected static $DefaultOptions= array(
        'Services'=> array(
            'Sanitizer'=> 'Sanitizer',    // for sanitization within Input method
        ),
    );

    // data, publicly accessible properties
    public $GET;
    public $POST;
    public $FILES;
    public $SERVER;
    public $COOKIE;
    public $ENV;

    // -- internal properties --------------------------------------------------

    // enumerate all context keys
    protected $ContextKeys= array('GET', 'POST', 'FILES', 'SERVER', 'COOKIE', 'ENV');


    // -- methods --------------------------------------------------------------


    /**
     * Populate this data-object with values from super-globals like $_SERVER, $_ENV, $_POST,...
     *
     * @return self
     */
    public function FromGlobals() {

        // retrieve values from super-global variables
        // cannot use variable variables in loop because of PHP bug #65223
        $this->GET   = $_GET;
        $this->POST  = $_POST;
        $this->FILES = $_FILES;
        $this->SERVER= $_SERVER;
        $this->COOKIE= $_COOKIE;
        $this->ENV   = $_ENV;

        // normalize values
        $this->Normalize();

        // chaining
        return $this;
    }


    /**
     * Populate this data-object with values from specified Accent\Request\Request service.
     * Service can be specified as string (name of service) or as object.
     * If parameter is omitted then service "Request" will be used.
     *
     * @param null|string|Accent\Request\Request $Service
     * @return self
     */
    public function FromRequestService($Service=null) {

        // resolve param
        if (!is_object($Service)) {
            $Service= $this->GetService($Service === null ? 'Request' : $Service);
        }

        // fetch values from service
        $Values= $Service->GetContext();
        foreach ($this->ContextKeys as $Key) {
            $this->$Key= $Values[$Key];
        }

        // normalize values
        $this->Normalize();

        // chaining
        return $this;
    }


    /**
     * Populate this data-object with values from supplied array.
     * Possible keys in array are ['GET', 'POST', 'FILES', 'SERVER', 'COOKIE', 'ENV'].
     * Missing keys will be treated as empty array().
     *
     * @param array $Values
     * @return self
     */
    public function FromArray($Values) {

        // import values, but only registered keys
        foreach ($this->ContextKeys as $Key) {
            $this->$Key= isset($Values[$Key]) ? $Values[$Key] : array();
        }

        // normalize values
        $this->Normalize();

        // chaining
        return $this;
    }



    /**
     * This is recommended way to fetch values from GET/POST/COOKIE buffers
     * instead of direct accessing to public properties (...= $RequestContext->GET['Action'];)
     * because it:
     *  - will prevent issuing PHP notice about missing key in input buffer,
     *  - apply sanitization to fetched value,
     *  - if value is array sanitization will be perform recursively to all members,
     *  - pass default value if required key not exist,
     *  - and all that in single line.
     *
     * Examples:
     *   $Id= $RequestContext->Input('id', 'G|I');              // get from GET, as integer
     *   $Name= $RequestContext->Input('name', 'P|T|Len:8');    // get from POST, trimmed, max.length:32
     *   $Checkboxes= $RequestContext->Input('CB', 'A|I');      // get from both GET or POST, as array of integers
     *
     * Source for fetching values can be specified using full names ("GET", "POST", "COOKIE") or shorter ("G", "P", "C").
     * Selecting source must be placed in front of sanitization instructions, as seen in first two examples.
     * Omitting to specify source (see 3rd example) will try to fetch from POST and then fallback to GET buffer.
     * Sanitizer "A" will ensure array type of fetched value.
     * Other sanitizations will be performed by registered "Sanitizer" service ("T", "Len", "I" from previous examples).
     *
     * ENV, FILES and SERVER buffers cannot be accessed using this method.
     *
     * There is shortcut method Component::Input to reach this method.
     *
     * @param string $Key           name of GET/POST value to fetch
     * @param string $Sanitizers    list of sanitizers separated by "|"
     * @param mixed $DefaultValue   return this value if key not found
     * @return mixed
     */
    public function Input($Key, $Sanitizers='', $DefaultValue=null) {

        $Sanitizers= array_map('trim', explode('|', $Sanitizers));
        $Value= null;
        $First= strtoupper($Sanitizers[0]); // get first element
        // get value
        switch ($First) {
            case 'P':   // fetch from POST
            case 'POST':
                $Value= isset($this->POST[$Key]) ? $this->POST[$Key] : $DefaultValue;
                array_shift($Sanitizers);
                break;
            case 'G':   // fetch from GET
            case 'GET':
                $Value= isset($this->GET[$Key]) ? $this->GET[$Key] : $DefaultValue;
                array_shift($Sanitizers);
                break;
            case 'C':   // fetch from COOKIE
            case 'COOKIE':
                $Value= isset($this->COOKIE[$Key]) ? $this->COOKIE[$Key] : $DefaultValue;
                array_shift($Sanitizers);
                break;
            default: // get from both 'P' and 'G' (if source was omitted)
                $Value= isset($this->POST[$Key])
                    ? $this->POST[$Key]
                    : (isset($this->GET[$Key]) ? $this->GET[$Key] : $DefaultValue);
        }
        // force array type?
        $ForceArray= array_search('A', $Sanitizers);
        if ($ForceArray !== false) {
            if (!is_array($Value)) {
                $Value= ($Value === null) ? array() : array($Value);
            }
            unset($Sanitizers[$ForceArray]);
        }
        // support for 'magic_quotes_gpc' is removed in PHP 5.4
        // send rest of sanitizers to Sanitizer service
        if (!empty($Sanitizers)) {
            $Value= $this->GetService('Sanitizer')->Sanitize($Value, implode('|',$Sanitizers));
        }
        return $Value;
    }


    /**
     * Perform normalization/sanitization of stored data
     * to compensate differences between hosting platforms.
     */
    protected function Normalize() {

        // ensure existence of few $_SERVER fields
        $this->SERVER += array(
            'REQUEST_METHOD'=> 'GET',
            'HTTP_HOST'=> '',
            'SERVER_PORT'=> 80,
            'SCRIPT_NAME'=> '',
        );

        // ensure REQUEST_URI existence and syntax
        $this->SERVER['REQUEST_URI']= '/'.trim($this->RebuildRequestUri(), '/');

        // ensure HTTP_HOST information about host
        if (!$this->SERVER['HTTP_HOST']) {
            $this->SERVER['HTTP_HOST']= isset($this->SERVER['SERVER_NAME'])
                ? $this->SERVER['SERVER_NAME']
                : 'localhost';
        }

        // remove trailing port from HTTP_HOST
        $Parts= explode(':', $this->SERVER['HTTP_HOST']);
        $this->SERVER['HTTP_HOST']= $Parts[0];
        // set trailing port to ist field if specified
        if (isset($Parts[1])) {
            $this->SERVER['SERVER_PORT']= $Parts[1];
        }

        // validate host because it can be injected (spoofed)
        if (strlen($this->SERVER['HTTP_HOST']) > 80
                || preg_replace('/(?:^\[)?[a-zA-Z0-9-:\]_]+\.?/', '', $this->SERVER['HTTP_HOST']) !== '') {
            $this->FatalError('Invalid host "'.$this->SERVER['HTTP_HOST'].'".');
        }

        // normalization of DOCUMENT_ROOT ?
    }


    /**
     * The following method is based on code of the Zend Framework (1.10dev - 2010-01-24)
     * Copyright (c) 2005-2010 Zend Technologies USA Inc. (new BSD license)
     */
    protected function RebuildRequestUri() {

        $Srv= $this->SERVER;
        if (isset($Srv['HTTP_X_ORIGINAL_URL']) && false !== stripos(PHP_OS, 'WIN')) {
            // IIS with Microsoft Rewrite Module
            return $Srv['HTTP_X_ORIGINAL_URL'];
        }
        if (isset($Srv['HTTP_X_REWRITE_URL']) && false !== stripos(PHP_OS, 'WIN')) {
            // IIS with ISAPI_Rewrite
            return $Srv['HTTP_X_REWRITE_URL'];
        }
        if (isset($Srv['IIS_WasUrlRewritten']) && $Srv['IIS_WasUrlRewritten'] === '1'
                && isset($Srv['UNENCODED_URL']) && $Srv['UNENCODED_URL'] !== '') {
            // IIS7 with URL Rewrite: make sure we get the unencoded url (double slash problem)
            return $Srv['UNENCODED_URL'];
        }
        if (isset($Srv['REQUEST_URI'])) {
            $RequestUri= $Srv['REQUEST_URI'];
            // HTTP proxy reqs setup request uri with scheme and host [and port] + the url path, only use url path
            $DoubleSlashPos= strpos($RequestUri, '://');
            if ($DoubleSlashPos !== false) {
                $RequestUri= substr($RequestUri, $DoubleSlashPos+3);
                $RequestUri= substr($RequestUri, 0, strpos($RequestUri, '/'));
            }
            return $RequestUri;
        }
        if (isset($Srv['ORIG_PATH_INFO'])) {
            // IIS 5.0, PHP as CGI
            $RequestUri= $Srv['ORIG_PATH_INFO'];
            return ($Srv['QUERY_STRING'])
                ? $RequestUri.'?'.$Srv['QUERY_STRING']
                : $RequestUri;
        }
        if (isset($Srv['argv'])) {
            return $Srv['SCRIPT_NAME'].'?'.$Srv['argv'][0];
        }
        if (isset($Srv['QUERY_STRING'])) {
            return $Srv['SCRIPT_NAME'].'?'.$Srv['QUERY_STRING'];
        }
        return $Srv['SCRIPT_NAME'];
    }


}


