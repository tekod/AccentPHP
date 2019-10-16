<?php namespace Accent\Router\Rule;

/**
 * Routing rule "Secure".
 * It's job is to ensure that current route can be valid only on secure commection (with https://..).
 * This is secondary rules so it must return IGNORE_RULE or REJECT_ROUTE on checking.
 */

use Accent\Router\Rule\AbstractRule;


class Secure extends AbstractRule {


    public function Compile($CompiledRoute) {

        // nothing to modify
        return $CompiledRoute;
    }


    public function Check($CompiledRoute) {

        if (!isset($CompiledRoute['Secure'])) {
            // there is no job for me, try with next rule
            return self::RESULT_CONTINUE;
        }

        // fetch security level from context
        $RequestSecure= (isset($this->Context->SERVER['HTTPS']) && $this->Context->SERVER['HTTPS'] === 'on')
            || (isset($this->Context->SERVER['SERVER_PORT']) && $this->Context->SERVER['SERVER_PORT'] === '443');

        // reject this route if requirements are not matched
        return $RequestSecure === $CompiledRoute['Secure']
            ? self::RESULT_CONTINUE
            : self::RESULT_REJECT_ROUTE;
    }

}


?>