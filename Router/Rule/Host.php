<?php namespace Accent\Router\Rule;

/**
 * Routing rule "Host".
 * It's job is to check is current route valid for specified host.
 * This is secondary rules so it must return IGNORE_RULE or REJECT_ROUTE on checking.
 */

use Accent\Router\Rule\AbstractRule;


class Host extends AbstractRule {


    public function Compile($CompiledRoute) {

        // nothing to modify
        return $CompiledRoute;
    }


    public function Check($CompiledRoute) {

        if (!isset($CompiledRoute['Host'])) {
            // there is no job for me, try with next rule
            return self::RESULT_CONTINUE;
        }

        // fetch host from context
        $RequestHost= $this->Context->SERVER['HTTP_HOST'];

        // reject this route if host not matching
        return $RequestHost === $CompiledRoute['Host']
            ? self::RESULT_CONTINUE
            : self::RESULT_REJECT_ROUTE;
    }

}


?>