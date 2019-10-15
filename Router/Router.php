<?php namespace Accent\Router;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Routing can be performed by MatchRequest method.
 * It returns route object of matched route with few additional fields.
 * Matching of routes are performed in order of definition, first match will be returned.
*/

use Accent\AccentCore\Component;
use Accent\AccentCore\RequestContext;


class Router extends Component {


    protected static $DefaultOptions= array(

        // modify this to redirect matching to customized class
        'MatcherClass' => 'Accent\\Router\\Matcher',

        // modify this to redirect builder to custom class
        'UrlBuilderClass'=> 'Accent\\Router\\UrlBuilder',

        // to enable caching of compiled routes specify key as string for Cache-service
        'CacheKey' => null,

        // location of website relatively to domain root directory, must end with slash
        'BasePath'=> '/',

        // services
        'Services'=> array(
            // if any of routes are event-typed
            'Event'=> 'Event',
            // if any of routes has 'Validate' property it will be processed by Validator
            'Validator'=> 'Validator',
            // if caching is enabled
            // 'Cache'=> 'Cache'
        ),

        // version of component
        'Version'=> '0.5.0',
    );


    // buffer for routes
    /* var RouteGroup $Routes */
    protected $Routes;

    // buffer for $_SERVER, closures and event will examine it
    protected $ServerVars;

    // buffers for info about successfull matching process
    protected $MatchingInfo;

    // flag
    protected $RoutesCollected= false;

    // object of requestmatcher class
    protected $Matcher;

    // object of URL builder class
    protected $UrlBuilder;

    // array of compiled routes
    protected $CompiledRoutes;


    /**
     * Contructor.
     *
     * @param array $Options
     */
    function __construct($Options=array()) {

        // call parent
        parent::__construct($Options);

        // prepare collection object
        $this->Routes= new RouteGroup;
    }


    /**
     * Expose root route collection.
     *
     * @return RouteGroup
     */
    public function GetRoutes() {

        return $this->Routes;
    }


    /**
     * Perform collecting route definitions.
     *
     * @param array|null $Struct  tree of route definitions
     * @return self
     */
    public function LoadRoutes($Struct=null) {

        // collect routes only once
        if ($this->RoutesCollected) {
            return $this;
        }

        // try to load compiled routes from cache
        $this->CompiledRoutes= $this->GetCompiledRoutesFromCache();

        // if cached version not found ...
        if ($this->CompiledRoutes === null) {

            // ... import routes from parameter if supplied
            if (is_array($Struct)) {
                $this->Routes->AddRoutes($Struct);
            }

            // ... trigger event to allow listeners to add its routes
            $this->EventDispatch('Router.LoadRoutes', ['Routes'=>$this->Routes]);
        }

        // mark & return
        $this->RoutesCollected= true;
        return $this;
    }


    /**
     * Challenge each route against request context and return first matching route object.
     *
     * @param Accent\AccentCore\RequestContext|null $Context
     * @return Accent\Router\Route|false|null
     */
    public function MatchRequest($Context=null) {

        $this->LoadRoutes();
        $this->BuildMatcher();
        $CompileAll= $this->GetOption('CacheKey') !== null;

        // build context if not specified
        if ($Context === null) {
            $Context= $this->GetRequestContext();
        }

        // call matcher
        $Routes= !$this->CompiledRoutes  // loose comparison: for null or empty array
            ? $this->Routes
            : $this->CompiledRoutes;
        $Result= $this->Matcher->Match($Routes, $Context, $CompileAll);

        // if caching is enabled but cached version was not found - send it to cache
        if ($CompileAll && $this->CompiledRoutes === null) {
            $this->SaveCompiledRoutesToCache($this->Matcher->GetCompiledRoutes());
        }

        // return matching result
        return $Result;
    }


    /**
     * Factory method, instantiate matcher object with all options and store it in $this->Matcher.
     */
    protected function BuildMatcher() {

        if ($this->Matcher !== null) {
            return;
        }

        // send all Router's options to Matcher and Matcher will pass them to rules objects
        $Class= $this->GetOption('MatcherClass');
        $Options= $this->GetAllOptions();
        $this->Matcher= $this->BuildComponent($Class, $Options);
    }


    /**
     * Try to fetch list of compiled routes from cache.
     *
     * @return array|null
     */
    protected function GetCompiledRoutesFromCache() {

        // is key specified?
        $CacheKey= $this->GetOption('CacheKey');
        if ($CacheKey === null) {
            return null;
        }

        // is service available?
        $Service= $this->GetService('Cache');
        if (!$Service) {
            return null;
        }

        // fetch
        $Routes= $Service->Read($CacheKey);

        // validate & return
        return !is_array($Routes) || empty($Routes)
            ? null
            : $Routes;
    }


    /**
     * Send compiled routes to cache.
     *
     * @param array $Array
     */
    protected function SaveCompiledRoutesToCache($Array) {

        $CacheKey= $this->GetOption('CacheKey');
        $Service= $this->GetService('Cache');
        if (is_object($Service) && $CacheKey !== null) {
            $Service->Write($CacheKey, $Array);
        }
    }


    /**
     * Return route that was rejected in last matching.
     * This can be used for more user-friendly version of classic "Error 403 - Forbidden access" page.
     *
     * @return Route
     */
    public function GetRejectedRoute() {

        return $this->Matcher
            ? $this->Matcher->GetRejectedRoute()
            : null;
    }


    /**
     * Helper method, instantiate UrlBuilder and auto-configure it.
     *
     * @return Accent\Router\UrlBuilder
     */
    public function GetUrlBuilder() {

        if (!$this->UrlBuilder) {
            $Class= $this->GetOption('UrlBuilderClass');
            $Options= array(
                'Routes'=> $this->GetRoutes(),
                'Context'=> $this->GetRequestContext(),
            ) + $this->GetCommonOptions();
            $this->UrlBuilder= $this->BuildComponent($Class, $Options);
        }
        return $this->UrlBuilder;
    }

}

?>