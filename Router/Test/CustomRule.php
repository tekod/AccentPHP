<?php namespace Accent\Router\Test;


class CustomRule extends \Accent\Router\Rule\AbstractRule {


    public function Compile($CompiledRoute) {

        // noting to modify
        return $CompiledRoute;
    }


    public function Check($CompiledRoute) {

        if (!isset($CompiledRoute['Auth'])) {
            // there is no job for me, try with next rule
            return self::RESULT_CONTINUE;
        }

        if ($CompiledRoute['Auth'] !== $this->Context->Env['Membership']) {
            // wrong level, reject this route
            return self::RESULT_REJECT_ROUTE;
        }

        // its Ok
        return self::RESULT_CONTINUE;
    }

}


?>