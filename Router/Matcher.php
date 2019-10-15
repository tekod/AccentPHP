<?php namespace Accent\Router;

use Accent\AccentCore\ArrayUtils\Collection;
use Accent\AccentCore\Component;
use Accent\Router\Route;
use Accent\Router\Rule\AbstractRule as Rule;


class Matcher extends Component {

    // internal properties
    protected $Context;

    protected $Compiled;

    protected $Routes;

    protected $RejectedRoute;

    /** @var Accent\AccentCore\ArrayUtils\Collection $Rules  */
    protected $Rules;


    /**
     * Contructor.
     *
     * @param array $Options
     */
    function __construct($Options) {

        // call parent
        parent::__construct($Options);

        // gather rule resolvers
        $this->CollectRules();
    }


    /**
     * Create collection of rule classes.
     * It will populate it with standard rules and then trigger event to allow
     * listeners to add own or modify any standard rule.
     */
    protected function CollectRules() {

        // instantiate temporary collection of standard rules
        $TmpRules= new Collection(array(
            'Path'   => 'Accent\\Router\\Rule\\Path',
            'RegEx'  => 'Accent\\Router\\Rule\\RegEx',
            'Event'  => 'Accent\\Router\\Rule\\Event',
            'Host'   => 'Accent\\Router\\Rule\\Host',
            'Method' => 'Accent\\Router\\Rule\\Method',
            'Secure' => 'Accent\\Router\\Rule\\Secure',
        ));

        // call listeners to modify collection
        $this->EventDispatch('Router.CollectRules', ['Rules'=>$TmpRules]);

        // build rule objects and put them in main collection
        // primary rules goes to the beginning of collection
        $Options= $this->GetAllOptions();
        $this->Rules= new Collection();
        foreach($TmpRules as $Rule) {
            $RuleObject= $this->BuildComponent($Rule, $Options);
            if ($RuleObject->IsPrimaryRule) {
                $this->Rules->UnShift($RuleObject);
            } else {
                $this->Rules->Append($RuleObject);
            }
        }
    }


    /**
     * Main method,
     *
     * @param array|Accent\Router\RouteCollection $Routes
     * @param Accent\AccentCore\RequestContext $Context
     * @param bool $CompileAll
     * @return false|null|Accent\Router\Route
     */
    public function Match($Routes, $Context, $CompileAll) {

        $this->RejectedRoute= null;

        // prepare routes
        if (is_array($Routes) && !empty($Routes)) {
            $this->Compiled= $Routes;            // it is array of "compiled routes"
        }
        if (is_object($Routes)) {
            $this->Routes= $Routes;              // it is RouteGroup object
        }

        // prepare context and distribute it to rules
        $this->Context= $Context;
        foreach($this->Rules as $Rule) {
            $Rule->SetContext($Context);
        }

        // compile all routes if specified but skip if compiled list is already supplied
        if ($CompileAll && !(is_array($Routes) && !empty($Routes))) {
            $this->CompileAllRoutes();
        }

        // run checking loop
        $Result= is_array($this->Compiled)
            ? $this->SearchInCompiledRoutes()
            : $this->SearchInRouteCollection();

        // return result
        return $this->SanitizeResult($Result);
    }


    /**
     * Returns list of compiled routes.
     * Typically used for debuging or caching mechanism.
     *
     * @return array
     */
    public function GetCompiledRoutes() {

        return $this->Compiled;
    }


    /**
     * Perform compilation of all routes.
     */
    protected function CompileAllRoutes() {

        $this->Compiled= array();
        $this->CompileAllRoutes_Loop($this->Routes, $this->Routes->GetGroupRules());
    }

    /**
     * Internal method for CompileAllRoutes, recursion loop.
     */
    private function CompileAllRoutes_Loop($Group, $GroupRules) {

        foreach($Group as $Route) {
            if (is_a($Route, 'Accent\\Router\\RouteGroup')) {
                $this->CompileAllRoutes_Loop($Route, $Route->GetMergedGroupRules($GroupRules));
            } else {
                $this->Compiled[]= $this->CompileRoute($Route, $GroupRules);
            }
        }
    }


    /**
     * Perform compilation of route object into its fast-comparing version.
     *
     * @param Accent\Router\Route $Route
     * @return array
     */
    protected function CompileRoute($Route, $GroupRules) {

        // fetch all values from data-object and remove null fields and empty arrays
        $CompiledRoute= array_filter(get_object_vars($Route), function($x){return $x !== null && $x !== array();});

        // append group rules (not overwrite, array merging)
        foreach ($GroupRules as $Key=>$Value) {
            if (is_array($Value)) {
                $CompiledRoute[$Key]= isset($CompiledRoute[$Key]) ? $CompiledRoute[$Key]+$Value : $Value;
            } else if (!isset($CompiledRoute[$Key])){
                $CompiledRoute[$Key]= $Value;
            }
        }

        // apply all compilers
        foreach($this->Rules as $Rule) {
            $CompiledRoute= $Rule->Compile($CompiledRoute);
        }

        // return resulting array
        unset($CompiledRoute['Owner']);
        return $CompiledRoute;
    }


    /**
     * Perform sanitization of resulted route.
     *
     * @param Route|int $Route
     * @return Route|null|false
     */
    protected function SanitizeResult($Route) {

        if (!is_object($Route)) {
            return $Route === Rule::RESULT_REJECT_ROUTE
                ? false     // rejected route (403 error)
                : null;     // route not found (404 error)
        }

        // inform dispatcher and target controller about used HTTP method
        $Route->MatchedMethod= $this->Context->SERVER['REQUEST_METHOD'];

        // concat method on handler string if configured to do that
        if ($Route->REST) {
            $Route->Handler .= $Route->MatchedMethod;
        }

        // clear eventualy rejected route
        $this->RejectedRoute= null;

        // return final route object
        return $Route;
    }


    /**
     * Run checking loop on compiled list.
     *
     * @return null|Accent\Router\Route
     */
    protected function SearchInCompiledRoutes() {

        $LoopResult= Rule::RESULT_SKIP_ROUTE;

        foreach ($this->Compiled as $Item) {
            // skip non-routable routes, assume it is routable for omitted field
            if (isset($Item['Routable']) && !$Item['Routable']) {
                continue;
            }
            // evaluate
            $Result= $this->MatchRoute($Item);
            // test result
            if (is_object($Result)) {
                return $Result;                 // return result on positive matching
            } else if ($Result === Rule::RESULT_REJECT_ROUTE) {
                $LoopResult= $Result;           // set marker that we detect rejecting
            }
        }
        // exit
        return $LoopResult;
    }


    /**
     * Run checking loop in collection of route objects (root RouteGroup).
     *
     * @return null|Accent\Router\Route
     */
    protected function SearchInRouteCollection() {

        // because route collection is tree-like structure we using recursion
        // start recurion loop with values from root
        return $this->SearchInRouteCollection_Loop($this->Routes, $this->Routes->GetGroupRules());
    }


    /**
     * Internal method for SearchInRouteCollection method, recursion loop.
     */
    private function SearchInRouteCollection_Loop($Group, $GroupRules) {

        $LoopResult= Rule::RESULT_SKIP_ROUTE;

        foreach($Group as $Route) {

            if (is_a($Route, 'Accent\\Router\\RouteGroup')) {
                // this is sub-group, call recursion
                $Result= $this->SearchInRouteCollection_Loop($Route, $Route->GetMergedGroupRules($GroupRules));
            } else {
                // this is route object
                // skip non-routable items
                if ($Route->Routable === false) {
                    continue;
                }
                // compile it
                $Item= $this->CompileRoute($Route, $GroupRules);
                // evaluate
                $Result= $this->MatchRoute($Item);
            }
            // test result
            if (is_object($Result)) {
                return $Result;                 // return result on positive matching
            } else if ($Result === Rule::RESULT_REJECT_ROUTE) {
                $LoopResult= $Result;           // set marker that we detect rejecting
            }
        }

        return $LoopResult;
    }

    /**
     * Send specified route to serie of validations (rules).
     *
     * @param array $CompiledRoute
     * @return int|Accent\Router\Route
     */
    protected function MatchRoute($CompiledRoute) {

        $Matched= Rule::RESULT_SKIP_ROUTE;

        // apply all checkers to specified route
        foreach($this->Rules as $Rule) {

            // call rule's checker
            $Result= $Rule->Check($CompiledRoute);

            // in case of success save this object in temp variable and let other rules to perform their validations
            if (is_object($Result)) {
                $Matched= $Result;
                continue;
            }

            // in case of major fail reject this route
            if ($Result === Rule::RESULT_REJECT_ROUTE) {
                $this->RejectedRoute= $Matched;         // save rejected route
                return $Result;
            }

            // in case of "SKIP_ROUTE" skip all other checkings and exit
            if ($Result === Rule::RESULT_SKIP_ROUTE) {
                return $Result;
            }

            // in case of "IGNORE_RULE" simply continue checkings loop
            if ($Result === Rule::RESULT_CONTINUE) {
                continue;
            }

            // report invalid response from rule
            $Class= get_class($Rule);
            $this->Error('Unknown rule response from class "'.substr($Class,strrpos($Class,'\\')+1),'"');
        }

        // return finding
        return $Matched;
    }


    /**
     * Return route that was rejected in last matching.
     * This can be used for more user-friendly version of classic "Error 403 - Forbidden access" page.
     *
     * @return Route
     */
    public function GetRejectedRoute() {

        return $this->RejectedRoute;
    }

}



?>