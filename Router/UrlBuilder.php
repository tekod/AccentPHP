<?php namespace Accent\Router;

use Accent\AccentCore\Component;
use Accent\Router\RouteGroup;


class UrlBuilder extends Component {


    // default options
    protected static $DefaultOptions= array(

        // instance of RequestContext (optional, required only for absolute URLs)
        'RequestContext'=> null,

        // instance of RouteGroup class
        'Routes'=> null,
    );

    // exceptions for urlencoding links
    protected $UrlencodeExceptions= array(
        '%24' => '$',
        '%40' => '@',
        '%3A' => ':',
        '%3B' => ';',
        '%2C' => ',',
        '%28' => '(',
        '%29' => ')',
        '%3D' => '=',
        '%2B' => '+',
        '%21' => '!',
        '%2A' => '*',
        '%7C' => '|',
    );

    // -- internal properties --------------------------------------------------
    protected $Host;
    protected $Secure;
    protected $Routes;
    protected $BasePath;


    /**
     * Constructor
     */
    public function __construct($Options) {

        parent::__construct($Options);

        // setup internals
        $Context= $this->GetRequestContext();
        $this->Host= $Context->SERVER['HTTP_HOST'];
        $this->Secure= (isset($Context->SERVER['HTTPS']) && $Context->SERVER['HTTPS'] === 'on')
            || (isset($Context->SERVER['SERVER_PORT']) && $Context->SERVER['SERVER_PORT'] === '443');

        $this->Routes= $this->GetOption('Routes');
        if (is_array($this->Routes)) {
            $this->Routes= new RouteGroup;
            $this->Routes->AddRoutes($this->GetOption('Routes'));
        }

        $this->BasePath= $this->FindBasePath();
    }


    /**
     * Return URL on which specified route will respond.
     * By default resulting link will be relative to root of domain.
     *
     * @param string $RouteName  name of named route
     * @param array $Params  values for placeholders in 'Path' setting
     * @return string|false
     */
    public function Build($RouteName, $Params=array(), $AbsouluteURL=false) {

        $Route= $this->Routes->GetRouteByName($RouteName);
        if (!$Route) {
            $this->TraceInfo('UrlBuilder: named route not found: "'.$RouteName.'".');
            return false;
        }

        if ($Route->Path) {
            $Link= $this->BuildPathRoute($Route, $Params);
            return $this->EnhanceLink($Link, $Route, $AbsouluteURL);
        }

        if ($Route->RegEx) {
            $Link= $this->BuildRegExRoute($Route, $Params);
            return $this->EnhanceLink($Link, $Route, $AbsouluteURL);
        }

        $this->TraceError('UrlBuilder: unsupported construction of named route "'.$RouteName.'".');
        return false;
    }


    protected function BuildPathRoute($Route, $Params) {

        // inject ['prod'=>'lamp','photo'=>'a'] into 'shop/product/{prod}/{photo}'
        $Keys= array_map(function($V){return '{'.$V.'}';}, array_keys($Params));
        $Params= array_map(array($this, 'Encode'), $Params);
        $Link= str_replace($Keys, $Params, $Route->Path);

        // remove missing optionals
        if (strpos($Link, '[') !== false) {
            do {   // nested optionals must be removed in loop
              $Count= 0;
              $Link= preg_replace('~\\[[^\\[]*{[^\\[]*\\]~U', '', $Link, -1, $Count);
            } while ($Count > 0);
            $Link= str_replace(array('[',']'), '', $Link);
        }

        // if some params left unreplaced sanitize them and dispatch debug info
        if (strpos($Link,'{') !== false) {
            $Matches= null;
            preg_match_all('~{(\w+?)}~u', $Link, $Matches);
            $Link= str_replace($Matches[0], '$', $Link); // replace all of them with "$"
            $Msg= 'UrlBuilder: unreplaced params %s in building route "%s"';
            $this->TraceError(sprintf($Msg, implode(',',$Matches[0]), $Route->Name));
        }

        // return at least '/'
        return $Link === '' ? '/' : $Link;
    }


    protected function BuildRegExRoute($Route, $Params) {

        // inject ['2013','01'] into 'archive/(\\d{4})-(\\d{2})'
        // params are numericaly indexed so order of params are important
        $Link= preg_replace_callback('/\\(.*\\)/U', function()use($Params){
            static $Cnt=0;
            return isset($Params[$Cnt]) ? $this->Encode($Params[$Cnt++]) : '$';
        }, $Route->RegEx);

        // return at least '/'
        return $Link === '' ? '/' : $Link;
    }


    protected function EnhanceLink($Link, $Route, $AbsouluteURL) {

        // prepend BasePath and PathPrefix (but dont if route already contains absolute URL)
        if (strpos($Link, '://') === false) {
            $Link= $this->BasePath . $Route->PathPrefix . $Link;
            // also make it absolute if configured so
            if ($AbsouluteURL) {
                $Link= ($this->Secure ? 'https://' : 'http://') . $this->Host . $Link;
            }
        }

        // remove trailing slash
        return rtrim($Link, '/');
    }


    /**
     * Perform safe encoding of supplied string for usage in URLs.
     * This method is public to allow external usage of such usefull function.
     *
     * @param string $String
     * @return string
     */
    public function Encode($String) {

        return strtr(rawurlencode($String), $this->UrlencodeExceptions);
    }


    protected function FindBasePath() {

        $BasePath= $this->GetOption('Paths.DomainDir');
        if (!$BasePath) {
            $Path= $this->GetRequestContext()->SERVER['SCRIPT_NAME'];
            $BasePath= substr($Path, 0, strpos($Path, '/index.php'));
        }
        return $BasePath;
    }

}



?>