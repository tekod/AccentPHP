<?php namespace Accent\Router\Rule;

/**
 * Routing rule "Event".
 * It's job is to delegate checking to event listeners.
 * This is one of primary rules so it must return object of Route on positive matching in Check method.
 */

use Accent\Router\Rule\AbstractRule;
use Accent\AccentCore\Event\EventService;
use Accent\Router\Event\EventRuleEvent;


class Event extends AbstractRule {


    public function Compile($CompiledRoute) {

        // nothing to modify
        return $CompiledRoute;
    }


    public function Check($CompiledRoute) {

        // is there job for me?
        if (!isset($CompiledRoute['Event'])) {
            return self::RESULT_CONTINUE;
        }

        // trigger event
        $Event= new EventRuleEvent([
            'Route'=> $CompiledRoute,
            'Context'=> $this->Context,
        ]);
        $this->EventDispatch($CompiledRoute['Event'], $Event);

        // skip this route if no listener has claim this route
        if (!$Event->IsHandled()) {
            return self::RESULT_SKIP_ROUTE;
        }

        // inject returned value in compiled route record
        $CompiledRoute['Handler']= $Event->GetRouteHandler();

        // re-create Route object from compiled version and populate with matching information
        $Route= $this->RebuildMatchedRoute($CompiledRoute + array(
            'MatchedPath'=> array(),
            'MatchedVars'=> array(),
        ));

        // return object
        return $Route;
    }

}


?>