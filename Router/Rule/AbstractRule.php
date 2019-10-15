<?php namespace Accent\Router\Rule;

/**
 * This is base class for all "rules".
 *
 * Rule object must be stateless, do not store values to be preserved between checks.
 */

use Accent\AccentCore\Component;
use Accent\AccentCore\RequestContext;
use Accent\Router\Route;


abstract class AbstractRule extends Component {

    // result from Check method: tell matcher to completly ignore this route and jump to the next one
    const RESULT_SKIP_ROUTE   = 0;

    // result from Check method: tell matcher to reject this route this will produce 403 error if no matching found
    const RESULT_REJECT_ROUTE = 1;

    // result from Chack method: tell matcher to simply go to the next rule
    const RESULT_CONTINUE     = 2;

    // inform matcher whether this rule is primary
    public $IsPrimaryRule= false;

    // -- internal properties --------------------------------------------------

    // object of request context
    /** @var Accent\AccentCore\RequestContext $Context */
    protected $Context;


    /**
     * Setter for $Context property.
     *
     * @param RequestContext $Context
     */
    public function SetContext($Context) {

        $this->Context= $Context;
    }


    /**
     * Transform route object into form easier to store in cache
     * and prepare some fields for faster evaluation later in checking methods.
     *
     * @param type $CompiledRoute
     */
    abstract public function Compile($CompiledRoute);


    /**
     * Validate suppiled route against current rule.
     * Should return:
     *  - if rule is primary rule and route is successfuly matched it should return Accent\Router\Route object, with additional matching information
     *  - if rule is primary rule and it fails matching it should return RESULT_SKIP_ROUTE so matcher will silently skip all other rules and jump to next route
     *  - if rule is secondary rule and route is successfuly matched it should return RESULT_IGNORE_RULE to allow further checkings
     *  - if rule is secondary rule and it fails matching it should return RESULT_REJECT_ROUTE which will terminate further checkings of current route
     * Remember: route will be rejected if any rule returns false or all rules return null.
     *
     * @param array $CompiledRoute
     * @return int|Accent\Router\Route
     */
    abstract public function Check($CompiledRoute);


    /**
     * Helper method, re-create route object from supplied compiled version of route.
     *
     * @param array $CompiledRoute
     * @return \Accent\Router\Rule\Route
     */
    protected function RebuildMatchedRoute($CompiledRoute) {

        $Route= new Route;
        array_walk($CompiledRoute, function($v,$k,$c){$c->$k=$v;}, $Route);
        return $Route;
    }

}


?>