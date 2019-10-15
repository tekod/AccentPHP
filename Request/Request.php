<?php namespace Accent\Request;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Component Request extends RequestContext with additional logics.
 * It encapsulate common tasks for dealing with HTTP request:
 *  - analyzing URL structure
 *  - analyzing received HTTP headers
 *  - analyzing body received with request
 *  - handling file uploads
 *  - resolving info about IP address of visitor
 *  - support overriding http-method for REST calls
 *  - trying to detect is visitor bot machine
 *  - trying to detect does visitor came from mobile device
 *
 */

use Accent\AccentCore\Component;
use Accent\AccentCore\ArrayUtils\Collection;


class Request extends Component {


    protected static $DefaultOptions= array(

        // is this primary request
        'Primary'=> false,              // true only for main instance

        // list of allowed hosts to prevent spoofing HTTP_HOST
        // OVO TREBA DA BUDE DEO KERNELA ILI CMSa
        'AllowedHosts'=> array(
            // 'www.site.com',   // subdomain+domain+path, ex. www.site.com or www.site.com/en
            // 'localhost',
            // or leave empty array to accept all hosts
        ),

        // allow GetMethod() to return 'AJAX' if detect XMLHttpRequest request
        'AcceptAjaxAsMethod'=> true,

        // allow specified POST field to override detected reqest method
        'OverrideMethodField'=> '_Method',

        // list of IP addresses of proxies with reliable forwarded visitor address
        'TrustedProxies'=> array(
            // '111.111.111.111', '222.222.222.222'
        ),

        // list of HTTP body parsers as content-type => callable
        'BodyDecoders'=> array(
            'application/json'                  => '@/BodyDecoderJSON',
            'application/x-www-form-urlencoded' => '@/BodyDecoderParseStr',
        ),

        // class
        'MobileDetectorClass'=> 'Accent\\Request\\MobileDetection',

        // class
        'BotDetectorClass'=> 'Accent\\Request\\BotDetection',

        // services
        'Services'=> array(
            'Event'=> 'Event',
            //'Sanitizer'=> 'Sanitizer',    // not sure is it still required
        ),
    );


    // -- internal properties --------------------------------------------------

    protected $URL= '';     // URL of request
    protected $Self= '';    // URL of current page
    protected $UrlParts;    // array of URL components
    protected $Host= '';    // subdomain+domain of request
    protected $Headers;
    protected $Files;
    protected $Body;
    protected $BodyDecoders;
    protected $PathPrefixSegments= 0;
    protected $MobileDetector;
    protected $BotDetector;


    /**
     * Constructor
     */
    public function __construct($Options=array()) {

        // build RequestContext if missing, it is mandatory for this component
        if (!isset($Options['RequestContext']) || !$Options['RequestContext']) {
            $Options['RequestContext']= $this->BuildComponent('Accent\\AccentCore\\RequestContext')->FromGlobals();
        }

        // call parent
        parent::__construct($Options);

        // initialization
        $this->Init();
    }


    public function Init() {

        // detect host
        $this->ResolveHost();

        // detect URL and separate its parts
        $this->ResolveURL();

        // extract HTTP request headers (razbija accept-e)
        $this->ResolveHeaders();

        // dispatch event for collection body decoders
        $this->InitBodyDecoders();
    }


     /**
      * Detect host information from context variables.
      * Result is stored in $this->Host.
      */
    protected function ResolveHost() {

        $Server= $this->GetOption('RequestContext')->SERVER;
        $DetectedHost= $Server['HTTP_HOST'];
        $this->Host= $DetectedHost;
    }


    /**
     * Detect URL from context variables.
     * Result is stored in $this->URL.
     * Additionaly URL will be exploded into components and stored in $this->UrlParts.
     */
    protected function ResolveURL() {


        $Server= $this->GetOption('RequestContext')->SERVER;
        $URL= $Server['REQUEST_URI'];

        // expand relative URL
        if (strpos($URL, '://') === false) {
            $SSL= $this->IsSecure();
            $Scheme= $SSL ? 'https' : 'http';
            $Host= $this->GetHost();
            $Port= $this->GetPort();
            $Port= (!$SSL && $Port === 80) || ($SSL && $Port === 443)
                ? ''
                : ':'.$Port;
            $Relative= trim($URL, '/');
            $Relative= ($Relative) ? '/'.$Relative : '';
            $URL= $Scheme.'://'.$Host.$Port.$Relative;
        }

        // store URL
        $this->URL= $URL;

        // get URL components
        $Parts= parse_url($this->URL);
        if (!is_array($Parts)) {
            $this->Error('Request/ResolveURL: Bad URL ('.$this->URL.').');
            return;
        }
        $Parts += array(
            'scheme' => 'http',
            'host'   => 'localhost',
            'port'   => $this->IsSecure() ? '443' : '80',   // default ports
            'path'   =>'/',
            'query'  => '',    // after the question mark (?)
            'fragment'=> '', //  after the hashmark (#)
            'user'   => '',
            'pass'   => '',
        );
        $this->UrlParts= $Parts;
    }


    /**
     * Extract HTTP headers from context variables.
     * Header names are uppercased.
     * Result is stored in $this->Headers.
     */
    protected function ResolveHeaders() {

        $this->Headers= array();
        foreach($this->GetOption('RequestContext')->SERVER as $Key => $Value) {
            if (strncmp($Key, 'HTTP_', 5) === 0) {
                $Key= substr($Key, 5);
            } elseif (strncmp($Key, 'CONTENT_', 8) !== 0) {
                continue;
            }
            $Key= str_replace('_', '-', $Key);
            $this->Headers[$Key]= $Value;
        }
    }


    /**
     * Prepare body decoders collection and trigger event.
     */
    protected function InitBodyDecoders() {

        $this->BodyDecoders= new Collection($this->GetOption('BodyDecoders'));
        $this->EventDispatch('Request.InitBodyDecoders', ['Collection'=> $this->BodyDecoders]);
    }


    /**
     * Is this primary Request object or it is sub-request?
     *
     * @return bool
     */
    public function IsPrimary() {

        return $this->GetOption('Primary');
    }



    /**
     * Return IP address of current visitor.
     *
     * @return string
     */
    public function GetIP() {

        $Srv= $this->GetRequestContext()->SERVER;
        $TrustedProxies= $this->GetOption('TrustedProxies');

        // if visitor coming from trusted proxie get forwarded IP address
        if (isset($Srv['HTTP_X_FORWARDED_FOR']) && isset($Srv['REMOTE_ADDR'])
            && in_array($Srv['REMOTE_ADDR'], $TrustedProxies)) {
            // format: "X-Forwarded-For: client1, proxy1, proxy2"
            $Clients= explode(',', $Srv['HTTP_X_FORWARDED_FOR']);
            return trim(reset($Clients));
        }
        // HTTP_CLIENT_IP also can be set by certain proxies
        if (isset($Srv['HTTP_CLIENT_IP']) && isset($Srv['REMOTE_ADDR'])
            && in_array($Srv['REMOTE_ADDR'], $TrustedProxies)) {
            $Clients= explode(',', $Srv['HTTP_CLIENT_IP']);
            return trim(reset($Clients));
        }
        // ok, back to good old REMOTE_ADDR field, all other server fields are not reliable
        if (isset($Srv['REMOTE_ADDR'])) {
            return $Srv['REMOTE_ADDR'];
        }
        // not found
        return null;
    }


    /**
     * Return scheme (protocol) from HTTP request.
     *
     * @return string
     */
    public function GetScheme() {

        return $this->UrlParts['scheme'];
    }


    /**
     * Return resolved host (domain) information of this request.
     *
     * @return string
     */
    public function GetHost() {

        return $this->Host;
    }


    /**
     * Return HTTP port number.
     *
     * @return int
     */
    public function GetPort() {

        return intval($this->GetRequestContext()->SERVER['SERVER_PORT']);
    }


    /**
     * Return path from HTTP request.
     *
     * @param bool $RemovePrefix
     * @return string
     */
    public function GetPath($RemovePrefix=true) {

        if (!$RemovePrefix) {
            return $this->UrlParts['path'];
        }
        $Segments= explode('/', ltrim($this->UrlParts['path'], '/'));
        return '/'.implode('/', array_slice($Segments, $this->PathPrefixSegments));
    }


    /**
     * Return query part from HTTP request.
     *
     * @param bool $Parsed
     * @return string|array
     */
    public function GetQuery($Parsed=false) {

        if (!$Parsed) {
            return $this->UrlParts['query'];
        }
        $AsArray= null; // silence IDE
        parse_str($this->UrlParts['query'], $AsArray);
        return $AsArray;
    }


    /**
     * Return fragment part from HTTP request.
     *
     * @return string
     */
    public function GetFragment() {

        return $this->UrlParts['fragment'];
    }


    /**
     * Set number of segments at beginning of Path to be considered as prefix.
     *
     * @param int $Count
     * @return self
     */
    public function SetPathPrefixSegments($Count) {

        $this->PathPrefixSegments= max(0, $Count);
        return $this;
    }


    /**
     * Return HTTP method of this request ('GET','POST',..) or 'AJAX' if detected.
     *
     * @return atring
     */
    public function GetMethod() {

        $Server= $this->GetRequestContext()->SERVER;
        $Method= $Server['REQUEST_METHOD'];    // it is always present

        // for XMLHttpRequest request return 'AJAX'
        if (isset($this->Headers['X-REQUESTED-WITH']) && $this->Headers['X-REQUESTED-WITH'] === 'XMLHttpRequest'
            && $this->GetOption('AcceptAjaxAsMethod')) {
            return 'AJAX';
        }

        // honor 'X-HTTP-METHOD-OVERRIDE' header
        if ($Method === 'POST' && isset($this->Headers['X-HTTP-METHOD-OVERRIDE'])) {
            return strtoupper($this->Headers['X-HTTP-METHOD-OVERRIDE']);
        }

        // override with custom method
        $Key= $this->GetOption('OverrideMethodField');
        $Post= $this->GetRequestContext()->POST;
        if ($Method === 'POST' && isset($Post[$Key])) {
            return strtoupper($Post[$Key]);
        }

        // return original value
        return $Method;
    }


    /**
     * Return URL of request.
     *
     * @return string
     */
    public function GetURL() {

        return $this->URL;
    }


    /**
     * Get part of URL, following components are available:
     *  'scheme','host','port','path','query','fragment','user','pass'.
     *
     * @return string
     */
    public function GetUrlComponent($Component) {

        return $this->UrlParts[$Component];
    }


    /**
     * Get all URL components.
     *
     * @return array
     */
    public function GetUrlComponents() {

        return $this->UrlParts;
    }


    /**
     * Get URL of current page. Usefull for <form action=""> attribute.
     * Result always begining with slash.
     *
     * @return string
     */
    public function GetSelf($WithQuery=false) {

        $Path= '/'.ltrim($this->UrlParts['path'], '/');
        if ($WithQuery && $this->UrlParts['query'] !== '') {
            return $Path.'?'.$this->UrlParts['query'];
        }
        return $Path;
    }


    /**
     * Returns array of segments where each 2nd key contains 2nd+1 value.
     * Example: for URL '/a/b/c/d/e/f' returns array('a'=>'b', 'c'=>'d', 'e'=>'f')
     * This method will skip prefix-segments before joining.
     *
     * @return array
     */
    public function GetCombinedSegments() {

        $Out= array();
        $Segments= explode('/', ltrim($this->UrlParts['path'], '/'));
        $Segments= array_slice($Segments, $this->PathPrefixSegments);
        $xMax= count($Segments);
        for($x= 0; $x < $xMax; $x= $x+2) {
            $Out[$Segments[$x]]= isset($Segments[$x+1]) ? $Segments[$x+1] : null;
        }
        return $Out;
    }


    /**
     * Return list of HTTP headers from request.
     *
     * @return array
     */
    public function GetHeaders() {

        return $this->Headers;
    }


    /**
     * Return specified header.
     *
     * @param string $Name
     * @param string $Default
     * @return string|null
     */
    public function GetHeader($Name, $Default=null) {

        return isset($this->Headers[$Name])
            ? $this->Headers[$Name]
            : $Default;
    }


    /**
     * Get browser's UserAgent.
     * Result is limited to 150 chars in order to prevent DoS because this value is often
     * used in regex matching but as part of HTTP header can be easily spoofed by sender.
     *
     * @return string
     */
    public function GetUserAgent() {

        $uaHeaders= array(
            'USER-AGENT',
            'X-OPERAMINI-PHONE-UA',
            'X-DEVICE-USER-AGENT', // vodafone
            'X-ORIGINAL-USER-AGENT',
            'X-SKYFIRE-PHONE',
            'X-BOLT-PHONE-UA',
            'DEVICE-STOCK-UA',
            'X-UCBROWSER-DEVICE-UA',
            'FROM',    // googlebot
            'X-SCANNER',
        );
        foreach($uaHeaders as $Key) {
            if (isset($this->Headers[$Key])) {
                return substr($this->Headers[$Key], 0, 150);
            }
        }
        return '';
    }



    /**
     * Get content type from HTTP header.
     * Typically "text/html" or empty string.
     *
     * @return string
     */
    public function GetBodyType() {

        return isset($this->Headers['CONTENT-TYPE'])
            ? trim(explode(';', $this->Headers['CONTENT-TYPE'])[0])
            : '';             // maybe 'application/octet-stream' as default?
    }


    /**
     * Returns body (content) of request.
     * If specified it will parse and decode body with dedicated decoder.
     *
     * @param bool $Decoded  Whether to return decoded content
     * @return false|mixed  String for raw body or mixed for decoded or false on missing decoder
     */
    public function GetBody($Decoded=true) {

        if ($this->Body === null) {
        $this->Body= file_get_contents('php://input');
        }
        if ($Decoded) {
            $BodyType= $this->GetBodyType();
            $Decoder= $this->BodyDecoders->Get($BodyType);
            $Callable= $this->ResolveCallable($Decoder);
            return $Callable
                ? call_user_func($Callable, $this->Body)
                : false;
        }
        return $this->Body;
    }


    /**
     * Built-in body decoder: JSON decoder.
     */
    public function BodyDecoderJSON($Content) {

        return json_decode($Content, true);
    }


    /**
     * Built-in body decoder for application/x-www-form-urlencoded content-type.
     */
    public function BodyDecoderParseStr($Content) {

        $Result= null;
        parse_str($Content, $Result);
        return $Result;
    }


    /**
     * Is this CLI enviroment ?
     *
     * @return bool
     */
    public function IsCLI() {

        $Server= $this->GetRequestContext()->SERVER;
        return !isset($Server['SERVER_SOFTWARE'])
            && (strncmp(PHP_SAPI, 'cli', 3) === 0 || (is_numeric($Server['argc']) && $Server['argc'] > 0));
    }


    /**
     * Is request made using secured connection ?
     *
     * @return bool
     */
    public function IsSecure() {

        $Srv= $this->GetRequestContext()->SERVER;

        return $this->UrlParts['scheme'] === 'https'
         || (isset($Srv['HTTPS']) && (in_array($Srv['HTTPS'], array('1','on')) || intval($Srv['SERVER_PORT']) === 443));
    }


    /**
     * Examine $_SERVER["HTTP_ACCEPT_LANGUAGE"] and
     * return single language which best match with list of allowed languages.
     *
     * @param array $AvailableLanguages list of 2-char language marks
     * @param string|null $Header overriding $_SERVER["HTTP_ACCEPT_LANGUAGE"]
     * @return string 2-char language mark
     */
    public function DetectLanguage($AvailableLanguages) {
        // based on: http://stackoverflow.com/questions/6038236/http-accept-language
        // standard  for HTTP_ACCEPT_LANGUAGE is defined under
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.4
        // pattern to find is therefore something like this:
        //    1#( language-range [ ";" "q" "=" qvalue ] )
        // where:
        //    language-range  = ( ( 1*8ALPHA *( "-" 1*8ALPHA ) ) | "*" )
        //    qvalue         = ( "0" [ "." 0*3DIGIT ] )
        //            | ( "1" [ "." 0*3("0") ] )
        if (!isset($this->Headers['ACCEPT-LANGUAGE'])) {
            return null;
        }
        $Hits= 0;
        preg_match_all("/([[:alpha:]]{1,8})(-([[:alpha:]|-]{1,8}))?" .
                       "(\s*;\s*q\s*=\s*(1\.0{0,3}|0\.\d{0,3}))?\s*(,|$)/i",
                       $this->Headers['ACCEPT-LANGUAGE'], $Hits, PREG_SET_ORDER);
        // default language (in case of no hits) is the first in the array
        $BestLang= $AvailableLanguages[0];
        $BestQ= 0;
        foreach($Hits as $Arr) {
            // read data from the array of this hit
            $LangPrefix= strtolower($Arr[1]);
            $Language= isset($Arr[3]) ? $LangPrefix : $LangPrefix."-".strtolower($Arr[3]);
            $QValue= isset($Arr[5]) ? floatval($Arr[5]) : 1.0;
            // find q-maximal language
            if (in_array($Language,$AvailableLanguages) && ($QValue > $BestQ)) {
                $BestLang= $Language;
                $BestQ= $QValue;
            }
            // if no direct hit, try the prefix only but decrease q-value by 10% (as http_negotiate_language does)
            else if (in_array($LangPrefix,$AvailableLanguages) && (($QValue*0.9) > $BestQ)) {
                $BestLang= $LangPrefix;
                $BestQ= $QValue*0.9;
            }
        }
        return $BestLang;
    }


    /**
     * Parsing header specified with $Name according to RFC-7231 and returns array of options sorted by their weight.
     *
     * @param atring $Name
     * @return array
     */
    public function ParseAcceptHeader($Name) {

        if (!isset($this->Headers[$Name])) {
            return array('*'=>1);       // means: all types are acceptable
        }
        $Items= explode(',', $this->Headers[$Name]);
        $Items= array_filter(array_map('trim', $Items));
        $Result= array();
        foreach($Items as $Item) {
            $Parts= array_map('trim', explode(';', $Item));
            $Token= array_shift($Parts);
            $Wildcards= substr_count($Token, '*');
            $Weight= 1;
            foreach($Parts as $PartKey => $PartValue) {
                if (strncmp($PartValue, 'q=', 2) === 0) {
                    $Weight= floatval(substr($PartValue, 2));
                    unset($Parts[$PartKey]);
                }
            }
            array_unshift($Parts, $Token);
            $Token= implode(';', $Parts);
            $Result[$Token]= array($Weight, -$Wildcards);
        }
        arsort($Result);
        array_walk($Result, function(&$v){$v= $v[0];});
        return $Result;
    }


    /**
     * Returns array of tags received in "if-none-match" header.
     *
     * @return array
     */
    public function GetETags() {

        return isset($this->Headers['IF-NONE-MATCH'])
            ? array_filter(array_map(function($v){return trim($v,' "');}, explode(',', $this->Headers['IF-NONE-MATCH'])))
            : array();
    }


    /**
     * Returns values received in "Cache-Control" header.
     *
     * @return array
     */
    public function GetCacheControl() {

        $Parts= array();
        if (isset($this->Headers['CACHE-CONTROL'])) {
            foreach(explode(',', $this->Headers['CACHE-CONTROL']) as $Part) {
                $T= explode('=', trim($Part));
                if ($T[0] === '') {
                    continue;
                }
                $Parts[$T[0]]= isset($T[1]) ? $T[1] : true;
            }
        }
        return $Parts;
    }


    /**
     * Return array of file objects. File objects are built from $_FILES input buffer.
     *
     * @return array
     */
    public function GetFiles() {

        if ($this->Files === null) {
            $this->Files= array();
            foreach($this->GetRequestContext()->FILES as $Key=>$Value) {
                if (is_array($Value['tmp_name'])) {
                    $this->Error('Request/File: multiple values for files are not supported for key "'.$Key.'".');
                    continue;
                }
                $this->Files[$Key]= $this->BuildComponent('Accent\\Request\\File', array('OrigInfo'=> $Value));
            }
        }
        return $this->Files;
    }


    /**
     * Return specified file object built from $_FILES buffer.
     *
     * @param string $Key
     * @return Accent\Request\File
     */
    public function GetFile($Key) {

        // build $this->Files if necesarry
        $this->GetFiles();

        // return specified item
        return isset($this->Files[$Key])
            ? $this->Files[$Key]
            : false;
    }



    public function IsMobile() {

        if (!$this->MobileDetector) {
            $Class= $this->GetOption('MobileDetectorClass');
            $this->MobileDetector= $this->BuildComponent($Class);
        }
        return $this->MobileDetector->IsMobile();
    }


    public function IsBot() {

        if (!$this->BotDetector) {
            $Class= $this->GetOption('BotDetectorClass');
            $this->BotDetector= $this->BuildComponent($Class);
        }
        return $this->BotDetector->IsBot();
    }


}

?>