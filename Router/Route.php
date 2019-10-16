<?php namespace Accent\Router;

/**
 * Route is data-object containing information about specified route.
 * Its fields can be set from structure form, imported and exported.
 * All fields are optional.
 */

class Route {

    // -- following fields describe this route --------------------------------------------------------------

    // unique name of route (identifier), using for selecting route for some operations
    public $Name;

    // info that need to be returned as result of routing, typically controller classname or callable (mandatory for routable routes)
    public $Handler;

    // boolean - set to true to automaticaly append detected HTTP method name to resulting Handler string
    public $REST;

    // boolean - set to false to prevent routing that route, it is only for URL generation
    public $Routable;


    // -- following fields are rules --------------------------------------------------------------------------

    // pattern which will be compared againt current URL to determinate route matching (mandatory for basic type of route)
    public $Path;

    // regular expression which will determinate route matching (mandatory for regex type of route)
    public $RegEx;

    // name of event to trigger that should return true on positive matching (mandatory for event type of route)
    public $Event;

    // select required HTTP-method to match, glue multiple methods with '|'
    public $Method;

    // set value to require matching with HTTP_HOST or SERVER_NAME
    public $Host;

    // boolean - set to true to require secure connection (https) for positive matching
    public $Secure;

    // array of validation rules for variables from Path or Regex, keyed with variable name
    public $Validate;

    // boolean - set to true to allow specified Path having arbitrary appendix
    public $Wildcard;


    // -- calculated properties, do not set them manually ------------------------------------------------------

    // result of matching: value of path from request
    public $MatchedPath;

    // result of matching: values of named params in path
    public $MatchedVars;

    // result of matching: value of HTTP method ($_SERVER['REQUEST_METHOD'])
    public $MatchedMethod;

    // object of parent RouteGroup
    public $Owner;

}


?>