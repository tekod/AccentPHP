<?php namespace Accent\Router;

/**
 * RouteDispatcher is example how dispatcher can be implemented.
 * Developers are encouraged to build its own to align with application specific needs.
 *
 * Typical usage:
 * # $Route= $this->GetService('Router')->LoadRoutes($RouteList)->MatchRequest();
 * # $Dispatcher= $this->BuildComponent('Accent\\Router\\RouteDispatcher');
 * # // or $Dispatcher= new RouteDispatcher($this->GetCommonOprions());
 * # $Dispatcher->Dispatch($Route);
 *
 * RouteDispatcher recognizes following route "handlers":
 *  - 'ClassName::Method'    - it will staticaly call class and specified method
 *  - 'ClassName'            - it will instantiate class and call 'Run' method
 *  - 'ClassName->Method'    - it will instantiate class and call specified method
 *  - '@ServiceName->Method' - it will retrieve service with specified name and call its method
 * All classnames must be FQCN.
 * All instantiation of classes will get $this->GetCommonOptions() as constructor param.
 * All methods will get $Route object as method param.
 * If specified class cannot be found it will try to redirect to handler of 501 error page.
 */

use \Accent\AccentCore\Component;


class RouteDispatcher extends Component {


    protected static $DefaultOptions= array(

        // names of routes for error pages, routes should be registered in router service
        'ErrorHandlers'=> array(
            'Error403'=> '',               // 403 - Forbidden
            'Error404'=> '',               // 404 - Not found
            'Error501'=> '',               // 501 - Not implemented
        ),

        // services
        'Services'=> array(
            'Router'=> 'Router',
        ),
    );


    /**
     * Execute callable target specified in $Route array.
     *
     * @param null|false|Accent\Router\Route $Route  result from Router's MatchRequest() method
     * @param array $ConstructorOptions  custom options which should be passed to target
     */
    public function Dispatch($Route, $ConstructorOptions=array()) {

        // prepare options
        $ConstructorOptions['Route']= $Route;
        $Options= $this->MergeArrays(array($ConstructorOptions, $this->GetCommonOptions()));

        // get route handler and execute it
        $this->Execute($Options);
    }


    /**
     * Main route executioner.
     * It is isolated into separate method to allow recursion calls.
     *
     * @param string  constructor options with injected 'Route' object
     */
    protected function Execute($Options) {

        // is route found?
        if ($Options['Route'] === null) {
            $this->Execute404($Options);
            return;
        }

        // is route rejected?
        if ($Options['Route'] === false) {
            $this->Execute403($Options);
            return;
        }

        // is it string representing service name and method?
        if (substr($Options['Route']->Handler, 0, 1) === '@') {
            $this->ExecuteService($Options);
            return;
        }

        // is it string representing static class and method?
        if (count(explode('::', $Options['Route']->Handler)) > 1) {
            $this->ExecuteStatic($Options);
            return;
        }

        // it is dynamic
        $this->ExecuteDynamic($Options);
    }


    /**
     * Instantiate class and call its method.
     */
    protected function ExecuteDynamic($Options) {

        $Ex= explode('->', $Options['Route']->Handler);
        $Class= $Ex[0];
        $ClassMethod= isset($Ex[1]) ? $Ex[1] : 'Run';
        if (!class_exists($Class)) {
            $this->Execute501($Class, $Options);
            return;
        }
        $Obj= new $Class($Options);
        $Obj->$ClassMethod($Options);
    }


    /**
     * Call handler as static callable (as array(class,method)).
     */
    protected function ExecuteStatic($Options) {

        $Callable= explode('::', $Options['Route']->Handler);
        if (!class_exists($Callable[0])) {
            $this->Execute501($Callable, $Options);
            return;
        }
        call_user_func($Callable, $Options);
    }


    /**
     * Call handler as named service and its method (as $this->GetService(Name)->Method();).
     */
    protected function ExecuteService($Options) {

        $ExService= explode('->', substr($Options['Route']->Handler, 1), 2);
        $Service= $this->GetService($ExService[0]);
        $Method= count($ExService) === 1 ? 'Run' : $ExService[1];
        if (!$Service) {
             $this->Execute501($Options['Route']->Handler, $Options);
        }
        $Service->$Method($Options);
    }


    /**
     * Handle 501 situation, execute dedicated route for that or execute internal handler if route not found.
     */
    protected function Execute501($ClassName, $Options) {

        $RouteName= $this->GetOption('ErrorHandlers.Error501');
        $Route= $this->GetService('Router')->GetRoutes()->GetRouteByName($RouteName);
        if (!$Route) {
            $this->TraceInfo('Router: class not found ('.$ClassName.'), also handler for 501 error page not found, showing default error page.');
            $this->InternalErrorPage(501);
            return;
        }
        $this->TraceInfo('Router: Class not found ('.$ClassName.'), redirecting with status 501 to route "'.$RouteName.'".');
        $Options['Route']= $Route;
        $this->Execute($Options);   // recursion
    }


    /**
     * Handle 404 situation, execute dedicated route for that or execute internal handler if route not found.
     */
    protected function Execute404($Options) {

        $RouteName= $this->GetOption('ErrorHandlers.Error404');
        $Route= $this->GetService('Router')->GetRoutes()->GetRouteByName($RouteName);
        if (!$Route) {
            $this->TraceInfo('Router: route not found, also handler for 404 error page not found, showing default error page.');
            $this->InternalErrorPage(404);
            return;
        }
        $this->TraceInfo('Router: route not found, redirecting with status 404 to route "'.$RouteName.'".');
        $Options['Route']= $Route;
        $this->Execute($Options);   // recursion
    }


    /**
     * Handle 403 situation, execute dedicated route for that or execute internal handler if route not found.
     */
    protected function Execute403($Options) {
        // get name of rejected route
        $Router= $this->GetService('Router');
        $RejectedRoute= $Router->GetRejectedRoute();
        $NameOfRejectedRoute= is_object($RejectedRoute)
            ? ($RejectedRoute->Name <> '' ? $RejectedRoute->Name : serialize($RejectedRoute))
            : 'null';
        // continue
        $RouteName= $this->GetOption('ErrorHandlers.Error403');
        $Route= $Router->GetRoutes()->GetRouteByName($RouteName);
        if (!$Route) {
            $this->TraceInfo('Router: route rejected ('.$NameOfRejectedRoute.'), also handler for 403 error page not found, showing default error page.');
            $this->InternalErrorPage(403, $RejectedRoute);
            return;
        }
        $this->TraceInfo('Router: route rejected ('.$NameOfRejectedRoute.'), redirecting with status 403 to route "'.$RouteName.'".');
        $Options['Route']= $Route;
        $this->Execute($Options);   // recursion
    }


    /**
     * Internal error page presenation.
     *
     * @param int $Status  numeric error code
     * @param null|Route $Route  rejected route object in case of 403 error
     */
    protected function InternalErrorPage($Status, $Route=null) {

        switch ($Status) {
            case 403: $Header= 'HTTP/1.0 403 Forbidden';
                      $Content= 'Page not allowed (error code 403).';
                      break;
            case 404: $Header= 'HTTP/1.0 404 Not Found';
                      $Content= 'Page not found (error code 404).';
                      break;
            case 501: $Header= 'HTTP/1.0 501 Not Implemented';
                      $Content= 'Class not found (error code 501).';
                      break;
            default:  $Header= 'HTTP/1.0 501 Not Implemented';
                      $Content= 'Unsupported internal error page status (error code '.$Status.').';
        }
        header($Header);
        echo $Content;
        // intentionaly do not die or terminate
    }



}


?>