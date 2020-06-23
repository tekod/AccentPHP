<?php namespace Accent\Network;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


use Accent\AccentCore\Component;
use Accent\AccentCore\ArrayUtils\Collection;

/**
 * Network/HttpClient is component for creating HTTP requests and fetching reponse
 * in much more robust way than simply "file('http://www.example.com')" can do.
 *
 * Capability to accept and send back cookies allows this component to successfully
 * simulate typical web browser.
 * It supports:
 *  - basic user/pass authentication
 *  - setting user_agent, referer, cookies and header content
 *  - following browser redirects, and controlled depth of redirects
 *  - submit form data and retrieve the results
 *  - uploading files
 *  - connection through proxy server
 * Ofcourse this component cannot solve captcha, execute javascript, interact with flash,...
 *
 * Examples:
 *   $Client= new HttpClient();
 *   $Client->Get('www.example.com/index.php?a=1');
 *   echo $Content->GetReceivedBody();
 *    or
 *   $Success= Client->Post('www.example.com/newpost', array(
 *          'DataPOST'=> array(
 *              'Comment'=> 'My new comment',
 *              'Action'=> 'Send',
 *          )));
 *   echo $Success ? $Client->GetReceivedBody() : 'Error: '.$Client->GetError();
 *
 * Methods "Get", "Post", "Put", "Delete", "Patch", "Option", "Head" correspondents to
 * HTTP methods of same name to support creating all REST calls.
 * Beside them there is generic method "Send" where first parameters specifies target method.
 *
 * All options from component constructor can be overriden for each request by specifying
 * them in theirs own "Options" parameter.
 *
 * See:
 *  - d:\Sites\demo\october\library-master-Build389\src\Network\HTTP.php
 *  - d:\Sites\demo\Snoopy-2.0.0\SnoopyClass.php
 *  - d:\Sites\demo\kraken-0.3.3\src\Network\Http\
 *  - d:\Sites\demo\kraken-0.3.3\src\Ipc\Socket\Socket.php
 *  - d:\Sites\demo\guzzle
 *  - d:\Sites\demo\cakephp-3.4.5\src\Network\Socket.php
 *  - d:\Sites\demo\omeka-2.0.3\application\libraries\Zend\Http\Client\Adapter\Socket.php
 *  - d:\Sites\demo\yii-2.0.11-advanced\vendor\swiftmailer\swiftmailer\lib\classes\Swift\Transport\StreamBuffer.php
 *  - d:\Sites\demo\drupal-7.15\includes\common.inc
 *  - d:\Sites\demo\pimcore\pimcore\lib\HTTP\Request2\Adapter\Socket.php
 *
 *
 *
 * Za IP pogledaj:
 *  - d:\Sites\demo\symfony-3.2\src\Symfony\Component\HttpFoundation\IpUtils.php
 *
 * Za URL:
 *  - https://github.com/jeremykendall/php-domain-parser
 *  - https://github.com/jwage/purl
 *  - https://github.com/fruux/sabre-uri
 *  - https://github.com/thephpleague/uri
 *
 *
 */

/*
 * @TODO: gzip transport
 * @TODO: async sending
 * @TODO: WWW-Authenticate
 */

class HttpClient extends Component {


    protected static $DefaultOptions= array(

        // maximum allowed consecutive HTTP redirections to follow
        'MaxRedirects'=> 5,

        // specify timeout in seconds
        'Timeout'=> 10,

        // specify HTTP standard
        'HttpVersion'=> 'HTTP/1.0',

        // allowing to accept and storing cookies from responses
        'AcceptCookies'=> true,

        // allowing to use gzip compression
        'UseGzip'=> true,

        // location of certificate for SSL
        'CA'=> array(
            'File'=> '',
            'Path'=> '',
        ),

        // HTTP headers to send
        'Headers'=> array(
            'Accept'=> '', // "image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, */*";
            'Referer'=> '',
            'User-Agent'=> 'AccentPHP/Network/HttpClient',
            //'Connection'=> 'Close',
            //'Content-Type'=> 'multipart/form-data'
        ),

        // instructions for basic-auth authenication
        'BasicAuth'=> array(
            'User'=> '',
            'Pass'=> '',
        ),

        // instructions for proxy connection
        'Proxy'=> array(
            'Host'=> '',
            'Port'=> '',
            'User'=> '',
            'Pass'=> '',
        ),

        // POST parameters to send with request, as key=>value
        'DataPOST'=> array(),

        // files to upload with request, as key=>path
        'DataFiles'=> array(),

        // content od body to send with request, as string
        'DataBody'=> '',

        // specify path to store result in file instead of memory
        'ResponseToFile'=> '',

        // convert repeated headers in response into CSV value
        'CompactRepeatedHeaders'=> true,

        // error messages
        'ErrorMessages'=> array(
            'MalformedURL'      => 'Malformed URL.',
            'InvalidProtocol'   => 'Invalid protocol "%s".',
            'OpenSslRequired'   => 'PHP extension "open_ssl" is required to make "https" requests.',
            'SockedFail'        => 'Socket creation failed.',
            'DnsLookupFail'     => 'DNS lookup failure.',
            'ConnectionTimeout' => 'Connection refused or timed out.',
            'ConnectionFail'    => 'Connection failed: "%s".',
            'HttpProxyNA'       => 'HTTPS connections over proxy are currently not supported',
        ),

        // version of Accent/Network package
        'Version'=> '1.0.0',
    );

    protected $RepeatableHeaders= array('accept', 'accept-charset', 'accept-encoding', 'accept-language',
        'accept-ranges', 'allow', 'cache-control', 'connection', 'content-encoding', 'content-language',
        'expect', 'if-match', 'if-none-match', 'pragma', 'proxy-authenticate', 'set-cookie', 'te',
        'trailer', 'transfer-encoding', 'upgrade', 'via', 'warning', 'www-authenticate');


    // internal variables

    protected $CookieCollection;
    protected $InitialOptions;
    protected $ConnectionHandle;
    protected $UsingProxy;
    protected $UsingSSL;
    protected $RequestedURL;
    protected $Method;
    protected $RedirectToURL;
    protected $RedirectHistory;
    protected $RedirectCount;
    //protected $RequestBody;
    protected $ContentType;
    protected $Boundary;

    protected $SentHeaders;
    protected $ReceivedHeaders;
    protected $ReceivedBody;
    protected $Errors;

    protected $Scheme;
    protected $Host;
    protected $Port;
    protected $User;
    protected $Pass;
    protected $Path;
    protected $Query;     // after the question mark ?
    protected $Fragment;  // after "#" character



    /**
     * Constructor
     */
    public function __construct($Options) {

        // call parent
        parent::__construct($Options);

        // make safe copy of options
        $this->InitialOptions= $this->Options;

        // build array collection for cookies
        $this->CookieCollection= new Collection();
    }



    public function Get($URL, $Options=array()) {
        return $this->Request('GET', $URL, $Options);
    }
    public function Post($URL, $Options=array()) {
        return $this->Request('POST', $URL, $Options);
    }
    public function Put($URL, $Options=array()) {
        return $this->Request('PUT', $URL, $Options);
    }
    public function Delete($URL, $Options=array()) {
        return $this->Request('DELETE', $URL, $Options);
    }
    public function Patch($URL, $Options=array()) {
        return $this->Request('PATCH', $URL, $Options);
    }
    public function Options($URL, $Options=array()) {
        return $this->Request('OPTIONS', $URL, $Options);
    }
    public function Head($URL, $Options=array()) {
        return $this->Request('HEAD', $URL, $Options);
    }


    /**
     * Main method for sending HTTP requests.
     *
     * @param type $Method
     * @param type $URL
     * @param array $Options
     * @return boolean  success of establishing connection to host
     */
    public function Request($Method, $URL, $Options=array()) {

        // pack options
        $this->Options= $this->MergeArrays(array($this->InitialOptions, $Options));

        // publish selected http-method to other parts of class
        $this->Method= strtoupper($Method);

        // debug info
        $this->TraceInfo('Network/HttpClient: request ['.$this->Method.'] '.$URL);

        // clear redirection history
        $this->RedirectHistory= array();

        // clear error messages
        $this->Errors= array();

        // launch request
        return $this->PerformHttpRequest($URL);
    }



    /**
     * Actual request sender.
     * It is separated from method Request to allow calling by redirecting loop.
     *
     * @param string $URL
     * @return boolean
     */
    protected function PerformHttpRequest($URL) {

        // parse URL
        if (!$this->ParseURL($URL)) {
            $this->TraceInfo("Network/HttpClient: unable to parse URL: ".$this->GetError());
            return false;
        }

        // try to establish connection to target
        if (!$this->Connect()) {
            $this->TraceInfo("Network/HttpClient: connection failed:\n  ".serialize($this->GetAllErrors()));
            return false;
        }

        // write to stream
        $this->SendRequest();

        // fetch response
        $this->FetchResponse();

        // close connection
        $this->Disconnect();

        // loop into recursion?
        $this->HandleRedirection();

        // successful return
        $this->TraceDebug('Network/HttpClient: success.');
        return true;
    }


    protected function HandleRedirection() {

        // is this redirection?
        if (!$this->RedirectToURL) {
            return;
        }

        // is limit reached?
        if (count($this->RedirectHistory) > $this->GetOption('MaxRedirects')) {
            return;
        }

        // log this redirection
        $this->RedirectHistory[]= $this->RedirectToURL;
        $this->TraceInfo('Network/HttpClient: redirecting to "'.$this->RedirectToURL.'".');

        // follow the redirection (recursive call)
        $this->PerformHttpRequest($this->RedirectToURL);
    }


    protected function ParseURL($URL) {

        // store for later use
        $this->RequestedURL= $URL;

        // parse URL into its components
        $ParsedURL= parse_url($URL);

        // return early if URL is seriously malformed
        if ($ParsedURL === false) {
            $this->SoftError('MaformedURL');
            return false;
        }

        // prepare protocol
        $this->Scheme= isset($ParsedURL['scheme'])
            ? strtolower($ParsedURL['scheme'])
            : 'http';
        if (!in_array($this->Scheme, array('http','https','tls','ssl'))) {
            $this->SoftError('InvalidProtocol', $this->Scheme);
            return false;
        }
        $this->UsingSSL= in_array($this->Scheme, array('https','ssl','tls'));
        if ($this->UsingSSL && !extension_loaded('openssl')) {
            $this->SoftError('OpenSslRequired');
            return false;
        }

        // prepare host
        $this->Host= isset($ParsedURL['host']) ? $ParsedURL['host'] : '';

        // prepare port
        $this->Port= isset($ParsedURL['port'])
            ? $ParsedURL['port']
            : ($this->Scheme === 'https' ? '443' : '');

        // prepare auth
        $this->User= isset($ParsedURL['user']) ? $ParsedURL['user'] : '';
        $this->Pass= isset($ParsedURL['pass']) ? $ParsedURL['pass'] : '';

        // prepare path
        $this->Path= isset($ParsedURL['path']) ? $ParsedURL['path'] : '';

        // prepare query
        $this->Query= isset($ParsedURL['query']) ? $ParsedURL['query'] : '';

        // prepare fragment
        $this->Fragment= isset($ParsedURL['fragment']) ? $ParsedURL['fragment'] : '';

        // successfull parsing
        return true;
    }


    protected function Connect() {

        // prepare host and port
        $Proxy= $this->GetOption('Proxy');
        $this->UsingProxy= $Proxy['Host'] !== '' && $Proxy['Port'] !== '';
        if ($this->UsingProxy && $this->UsingSSL) {
            $this->SoftError('HttpProxyNA');
            return false;
        }
        $Host= $this->UsingProxy ? $Proxy['Host'] : $this->Host;
        $Port= $this->UsingProxy ? $Proxy['Port'] : $this->Port;

        // create context for stream
        $ContextOptions= array();
        $CA= $this->GetOption('CA');
        if ($this->UsingSSL && ($CA['File'] !== '' || $CA['Path'] !== '')) {
            // enable certificate verification (including name checks)
            $ContextOptions['ssl']= array(
                'verify_peer'       => true,
                'CN_match'           => $this->Host,
                'disable_compression' => true,
            );
            if ($CA['File'] !== '') {
                $ContextOptions['ssl']['cafile']= $CA['File'];
            }
            if ($CA['Path'] !== '') {
                $ContextOptions['ssl']['capath']= $CA['Path'];
            }
        }
        $Context= stream_context_create($ContextOptions);

        // try to establish connection
        $Location= ($this->UsingSSL ? 'ssl' : 'tcp')
            .'://'.$Host
            .($Port === '' ? ':80' : ":$Port");

        $this->ConnectionHandle= @stream_socket_client( // prevent raising exception
            $Location,
            $ErrNo,
            $ErrMsg,
            $this->GetOption('Timeout'),
            STREAM_CLIENT_CONNECT,
            $Context
        );

        // return success
        if ($this->ConnectionHandle) {
            $this->TraceDebug('Network/HttpClient: connected.');
            return true;
        }

        // dispatch connection error message
        switch ($ErrNo) {
            case -3: $this->SoftError('SocketFail', $ErrMsg); return false;
            case -4: $this->SoftError('DnsLookupFail', $ErrMsg); return false;
            case -5: $this->SoftError('ConnectionTimeout', $ErrMsg); return false;
            default: $this->SoftError('ConnectionFail', "($ErrNo): $ErrMsg");
        }
        return false;
    }


    protected function SendRequest() {

        $this->SentHeaders= array();
        $ContentLength= 0;
        $ConfHeaders= $this->GetOption('Headers');
        $PostData= $this->GetOption('DataPOST');
        $PostFiles= $this->GetOption('DataFiles');
        $PostBody= $this->GetOption('DataBody');

        // location
        $Path= $this->UsingProxy
            ? $this->RequestedURL   // send whole URL if using proxy
            : $this->Path.($this->Query ? '?'.$this->Query : '');
        if (!$Path) {
            $Path= '/';
        }
        if ($this->Method === 'GET' && !empty($PostData)) {
            $this->Method= 'POST';
        }
        $this->SentHeaders[]= $this->Method.' '.$Path.' '.$this->GetOption('HttpVersion');

        // host
        $Host= isset($ConfHeaders['Host'])
            ? $ConfHeaders['Host']
            : $this->Host;
        $Port= in_array($this->Port, array('', '80'))
            ? ''
            : ':'.$this->Port;
        if ($Host) {
            $this->SentHeaders[]= "Host: $Host".$Port;
        }
        unset($ConfHeaders['Host']);

        // encoding
        if ($this->GetOption('UseGzip') && function_exists('gzinflate')) {
            // if UseGzip is true but PHP was not built with zlib support just
            // silently skip this option, it is not critical
            $Encoding= isset($ConfHeaders['Accept-Encoding'])
                ? $ConfHeaders['Accept-Encoding'].', gzip'
                : 'gzip';
            $this->SentHeaders[]= 'Accept-Encoding: '.$Encoding;
            unset($ConfHeaders['Accept-Encoding']);
        }

        // content-type
        $this->Boundary= "-------".substr(md5(rand(0, 32000)), 0, 10);
        $this->ContentType= isset($ConfHeaders['Content-Type'])
            ? $ConfHeaders['Content-Type']
            : (empty($PostData) && empty($PostFiles) ? 'text/html' : 'multipart/form-data');
        if ($this->ContentType === "multipart/form-data") {
            $this->ContentType .= '; boundary='.$this->Boundary;
        }
        $this->SentHeaders[]= "Content-Type: $this->ContentType";
        unset($ConfHeaders['Content-Type']);

        // cookies
        if (!$this->CookieCollection->IsEmpty()) {
            $Items= array();
            foreach ($this->CookieCollection->ToArray() as $Key=>$Value) {
                $Items[]= $Key.'='.urlencode($Value);
            }
            $this->SentHeaders[]= 'Cookie: '.implode('; ', $Items);
        }

        // append all unused headers
        foreach ($ConfHeaders as $Key=>$Value) {
            if ($Value) {$this->SentHeaders[]= $Key.': '.$Value;}
        }

        // add Content-length
        if ($PostBody) {
            //$this->SentHeaders[]= 'Content-Length: '.strlen($PostBody);
            $ContentLength += strlen($PostBody);
        }

        // add proxy authorization
        $Proxy= $this->GetOption('Proxy');
        if ($Proxy['User']) {
            $this->SentHeaders[]= 'Proxy-Authorization: Basic '.base64_encode("$Proxy[User]:$Proxy[Pass]");
        }

        // prepare POST multipart data
        $DataBlock= '';
        if (!empty($PostData)) {
            //$Headers[]= 'Content-Type: multipart/form-data, boundary='.$Boundary;
            $DataBlock= "\r\n\r\n";
            foreach($PostData as $index => $value) {
                $DataBlock .="--$this->Boundary\r\n";
                $DataBlock .= "Content-Disposition: form-data; name=\"".$index."\"\r\n";
                $DataBlock .= "\r\n".$value."\r\n";
            }
            $DataBlock .= "--$this->Boundary--\r\n";
            $ContentLength += strlen($DataBlock);
        }

        // prepare attachment info,
        // do not load attachment into memory because it can produce out-of-memory error,
        // instead just stream it to target (wrapped with boundaries) at end of request body
        foreach ($PostFiles as $Key=>$Path) {
            $Boundary1= "--$this->Boundary\r\n"
                ."Content-Disposition: form-data; name=\"$Key\"; filename=\"".basename($Path)."\"\r\n"
                ."Content-Type: application/octet-stream\r\n"
                ."Content-Transfer-Encoding: binary\r\n\r\n";
            $Boundary2= "\r\n--$this->Boundary--\r\n";
            $FileSize= filesize($Path);
            $PostFiles[$Key]= array(
                'Path'=> $Path,
                'Size'=> $FileSize,
                'B1'=> $Boundary1,
                'B2'=> $Boundary2,
            );
            $ContentLength += strlen($Boundary1) + $FileSize + strlen($Boundary2);
        }

        // send content
        if ($ContentLength) {
            $this->SentHeaders[]= 'Content-Length: '.$ContentLength;
        }
        $Content= implode("\r\n", $this->SentHeaders).$DataBlock."\r\n\r\n".$PostBody;
        fwrite($this->ConnectionHandle, $Content, strlen($Content));

        // debug info
        $this->TraceDebug("Network/HttpClient: sent HTTP request headers & body (without att.):\n".trim(str_replace("\r",'',$Content)));

        // stream attachments
        foreach ($PostFiles as $Attachment) {
            fwrite($this->ConnectionHandle, $Attachment['B1'], strlen($Attachment['B1']));
            $ah= fopen($Attachment['Path'], 'rb');
            stream_copy_to_stream($ah, $this->ConnectionHandle, $Attachment['Size']);
            fclose($ah);
            fwrite($this->ConnectionHandle, $Attachment['B2'], strlen($Attachment['B2']));
        }

    }



    protected function FetchResponse() {

        /*$IsGZiped = false;*/
        $this->ReceivedHeaders= array();
        $this->ReceivedBody= '';
        $this->RedirectToURL= null;

        // fetch response headers
        do {
            // fetch line from connection, only single line, maximum 64kb
            $Dump= fgets($this->ConnectionHandle, 65536);

            // terminate loop if double-newline reached
            if ($Dump === "\r\n") {
                break;
            }

            // analyze received line and store it in $this->ResponseHeaders
            $this->ParseHeader($Dump);

        } while($Dump);

        /*if ($IsGZiped) {
            stream_filter_append($this->ConnectionHandle, 'zlib.inflate', STREAM_FILTER_READ);
        }*/

        // debug info
        $Report= "Network/HttpClient: received headers:";
        foreach($this->ReceivedHeaders as $k=>$v) {
            $Report .= "\n  $k: $v";
        }
        $this->TraceDebug($Report);

        // don't fetch body if response status is something like 301, it causes 60 seconds waiting on Cloudflare
        if ($this->ReceivedHeaders['Status'] >= 300 && $this->ReceivedHeaders['Status'] <= 399) {
            return $this;
        }

        // prepare target file if it is specified
        $ToFile= $this->GetOption('ResponseToFile');
        if ($ToFile) {
            $FileHandle= @fopen($this->ResolvePath($ToFile), 'wb');
        }


        if ($this->ReceivedHeaders['Status'] > 300 && $this->ReceivedHeaders['Status'] < 333) {
            return $this;   // do not fetch body for status 301
        }

        // fetch data from connection
        do {
            // read portion of response body
            $Dump= fread($this->ConnectionHandle, 65536);

            // terminate loop
            if (strlen($Dump) === 0) {
                break;
            }

            // store portion
            if ($ToFile) {
                @fwrite($FileHandle, $Dump);
            } else {
                $this->ReceivedBody .= $Dump;
            }
        } while (true);

        // close target file
        if ($ToFile) {
            @fclose($FileHandle);
            $this->ReceivedBody= true;
        }

        // gunzip
//        if ($IsGZiped) {
//            // per http://www.php.net/manual/en/function.gzencode.php
//            $this->ResponseContent= gzinflate(substr($this->ResponseContent, 10));
//        }

        // debug info
        $this->TraceDebug("Network/HttpClient: response body:\n".($ToFile
                ? 'Stored in file: '.$this->GetOption('ResponseToFile')
                : substr($this->ReceivedBody, 0, 2000)));

        // chaining
        return $this;
    }


    /**
     * Analyze received line of response and store it in $this->ReceivedHeaders.
     */
    protected function ParseHeader($Line) {

        // split line on name:value parts
        $Parts= explode(':', $Line, 2);
        $HdrName= trim($Parts[0]);
        $HdrValue= trim(end($Parts));
        $HdrLowerName= strtolower($HdrName);

        // is it something like: HTTP/1.1 200 Ok
        if (substr($HdrName, 0, 5) === 'HTTP/') {
            $this->ReceivedHeaders['Status']= intval(substr($HdrValue, strpos($HdrValue, ' ')+1));
            return;
        }

        // if a header begins with Location: or URI:, set the redirect
        if (in_array($HdrLowerName, array('location','uri'))) {
            $this->RedirectToURL= strpos($HdrValue, '://') === false
                ? "$this->Scheme://$this->Host:$this->Port/".ltrim($HdrValue, '/')
                : $HdrValue;
        }

        /*// examine Content-Encoding
        if ($HdrLowerName === 'content-encoding' && $HdrValue === 'gzip') {
            $IsGZiped= true;
        }*/

        // special handling of "Set-Cookie:" header
        if ($HdrLowerName === 'set-cookie' && $this->GetOption('AcceptCookies')) {
            $this->ImportCookie($HdrValue);
            //return;
        }

        // store header line in buffer, header names are stored lowercased in order to properly recognize repeated headers
        if (in_array($HdrLowerName, $this->RepeatableHeaders) && isset($this->ReceivedHeaders[$HdrLowerName])) {
            if ($this->GetOption('CompactRepeatedHeaders')) {
                $this->ReceivedHeaders[$HdrLowerName] .= ', '.$HdrValue;
            } else {
                if (!is_array($this->ReceivedHeaders[$HdrLowerName])) {
                    $this->ReceivedHeaders[$HdrLowerName]= array($this->ReceivedHeaders[$HdrLowerName]);
                }
                $this->ReceivedHeaders[$HdrLowerName][]= $HdrValue;
            }
        } else {
            $this->ReceivedHeaders[$HdrLowerName]= $HdrValue;
        }
    }


    protected function Disconnect() {

        fclose($this->ConnectionHandle);
        $this->ConnectionHandle= null;
    }


    public function SendAsync($Method, $URL, $Options) {

    }


    /**
     * Returns HTTP headers sent on last request.
     */
    public function GetSentHeaders() {

        return $this->SentHeaders;
    }


    /**
     * Returns HTTP status of last HTTP response.
     */
    public function GetReceivedStatus() {

        return $this->ReceivedHeaders['Status'];
    }
    /**
     * Returns all headers of last HTTP response.
     */
    public function GetReceivedHeaders() {

        return $this->ReceivedHeaders;
    }


    public function GetReceivedBody() {

        return $this->ReceivedBody;
    }


    public function GetCookieCollection() {

        return $this->CookieCollection;
    }


    /**
     * Returns list of redirections (typicaly with status 301, 302) made in last request.
     *
     * @return array
     */
    public function GetRedirectHistory() {

        return $this->RedirectHistory;
    }


    /**
     * Returns location of content returned by last request.
     * If there was redirections this will return last URL in chain.
     *
     * @return string
     */
    public function GetRequestedURL() {

        return $this->RequestedURL;
    }


    /**
     * Update local cookies with supplied values.
     */
    protected function ImportCookie($HdrValue) {

        $Parts= array_map('trim', explode(';', $HdrValue));
        $FoldedCookie= '';
        $Secure= null;
        $Expire= null;
        $Found= array();
        // gather all attributes before
        foreach ($Parts as $PartKey => $Part) {
            $ExplodedPart= explode('=', $Part, 2);
            $CookieName= reset($ExplodedPart);
            $CookieValue= end($ExplodedPart);
            // analyze known attributes
            switch (strtolower($CookieName)) {
                case 'secure': $Secure= true; continue 2;
                case 'httponly': $Secure= false; continue 2;
                case 'expires': $Expire= $this->ParseHttpTime($CookieValue); continue 2;
                case 'max-age': $Expire= time()+intval($CookieValue); continue 2;
                case 'domain':
                case 'path':
                case 'samesite': continue 2;
            }
            // not known? if it is single-word just ignore it
            if (count($ExplodedPart) === 1) {
                continue;
            }
            // it is some kind of "name=value" content...
            // reject cookies without name
            if ($CookieName === '') {
                continue;
            }
            // if name starting with comma it represents begining of new cookie (folded) with its own set of attributes
            // altrough RFC6265 discourage such construction we must handle it
            if ($CookieName{0} === ',') {
                $RestOfHeaderValue= implode(';', array_slice($Parts, $PartKey));
                $FoldedCookie= ltrim($RestOfHeaderValue, ', '); // remove comma and space
                break;    // terminate loop, there is no more useful attributes for current cookie
            }
            // according to PHP manual for function 'http_parse_cookie' all unrecognized parts should
            // be treated as separate cookie but with common attributes
            $Found[$CookieName]= urldecode($CookieValue);
        }
        // now append/remove these cookies to collection
        foreach ($Found as $Key=>$Value) {
            // TODO: make "secure" aware
            if ($Expire === null || $Expire > time()) {
                $this->CookieCollection->Set($Key, $Value);
            } else {
                $this->CookieCollection->Remove($Key);
            }
        }
        // separate analyzing of folded cookie(s)
        if ($FoldedCookie) {
             $this->ImportCookie($FoldedCookie);             // recursion
        }
    }


    protected function ParseHttpTime($String) {

        $String= strtr($String, array(
            '"' => '',
            "'" => '',
            '-' => ' ',
            ',' => '',
        ));
        $TryDateFormats= array(
            'D d M y H:i:s T',
            'D d M Y H:i:s T',
            'D d m y H:i:s T',
            'D d m Y H:i:s T',
        );
        $TZ= new \DateTimeZone('GMT');
        // try few common formats
        foreach ($TryDateFormats as $Format) {
            $DateTime= \DateTime::CreateFromFormat($Format, $String, $TZ);
            if ($DateTime) {
                return $DateTime->Format('U');
            }
        }
        // bad luck, let PHP to decide
        $DateTime= date_create($String, $TZ);
        return $DateTime === false
            ? 0
            : $DateTime->Format('U');
    }


    protected function SoftError($ErrorType, $Param='') {

        $ErrorText= $this->GetOption("ErrorMessages.$ErrorType");
        $this->Errors[]= array(
            $ErrorType,                     // error type
            sprintf($ErrorText, $Param),    // message
            $Param                          // additional info
        );
    }


    /**
     * Return error message as textual content.
     *
     * @return string|false
     */
    public function GetError() {

        return empty($this->Errors)
            ? false
            : $this->Errors[0][1];
    }


    /**
     * More powerful variant of GetError(),
     * it returns array of all errors instead of only first one
     * and each of them contains original error type to allow custom translation.
     *
     * @return array
     */
    public function GetAllErrors() {

        return $this->Errors;
    }

}

?>