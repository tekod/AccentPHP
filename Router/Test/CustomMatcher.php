<?php namespace Accent\Router\Test;


use Accent\Router\Matcher;


class CustomMatcher extends Matcher {


    protected function MatchRoute($CompiledRoute) {

        // skip matching for routes with "Disabled" property
        if (isset($CompiledRoute['Disabled']) && $CompiledRoute['Disabled']) {
            return null;
        }

        // delegate to parent
        return parent::MatchRoute($CompiledRoute);
    }

}



?>