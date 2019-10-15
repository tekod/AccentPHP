<?php namespace Accent\Router\Rule;

/**
 * Routing rule "Method".
 * It's job is to compare HTTP method from request with specified method(s).
 * This is secondary rules so it must return IGNORE_RULE or REJECT_ROUTE on checking.
 */

use Accent\Router\Rule\AbstractRule;


class Method extends AbstractRule {


    public function Compile($CompiledRoute) {

        if (isset($CompiledRoute['Method'])) {
            // 'Method' field becames an array
            $CompiledRoute['Method']= array_filter(explode('|', strtoupper($CompiledRoute['Method'])));
        }
        return $CompiledRoute;
    }


    public function Check($CompiledRoute) {

        if (!isset($CompiledRoute['Method'])) {
            // there is no job for me, try with next rule
            return self::RESULT_CONTINUE;
        }

        // fetch method from context
        $RequestMethod= $this->Context->SERVER['REQUEST_METHOD'];

        // reject this route if method is not in list
        return in_array($RequestMethod, $CompiledRoute['Method'])
            ? self::RESULT_CONTINUE
            : self::RESULT_REJECT_ROUTE;
    }

}


?>