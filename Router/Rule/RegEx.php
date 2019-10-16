<?php namespace Accent\Router\Rule;

/**
 * Routing rule "RegEx".
 * It's job is to compare path from HTTP request with specified regular expression.
 * This is one of primary rules so it must return object of Route on positive matching in Check method.
 */

use Accent\Router\Rule\Path;


class RegEx extends Path {


    public function Compile($CompiledRoute) {

        if (isset($CompiledRoute['RegEx'])) {

            // retrieve regex pattern
            $Pattern= !isset($CompiledRoute['PathPrefix']) || !$CompiledRoute['PathPrefix']
                ? $CompiledRoute['RegEx']
                : $CompiledRoute['PathPrefix'].$CompiledRoute['RegEx'];
            $Pattern= rtrim($Pattern, '/');

            // save pattern into separate field of compiled route
            $CompiledRoute['_RegExPattern']= '`^'.$Pattern.'$`u';

            // this is not needed any more
            unset($CompiledRoute['PathPrefix']);
        }

        return $CompiledRoute;
    }


    public function Check($CompiledRoute) {

        if (!isset($CompiledRoute['_RegExPattern'])) {
            // there is no job for me, try with next rule
            return self::RESULT_CONTINUE;
        }

        // perform regex check
        $Matches= null;  // remove IDE warning
        if (!preg_match_all($CompiledRoute['_RegExPattern'], $this->RequestPath, $Matches)) {
            // sorry, matching failed, skip this route
            return self::RESULT_SKIP_ROUTE;
        }
        // matching found, extract values of named variables
        $MatchedPath= array_shift($Matches);
        $MatchedPath= $MatchedPath[0];
        $MatchedVars= $this->SanitizeMatchings($Matches);

        // check 'Validate' rule
        if (isset($CompiledRoute['Validate']) && !$this->IsValidMatchedVars($CompiledRoute['Validate'], $MatchedVars)) {
            // sorry, validation failed, but do not produce 403 error - this is "path not matched" situation
            return self::RESULT_SKIP_ROUTE;
        }

        // re-create Route object from compiled version and populate with matching information
        $Route= $this->RebuildMatchedRoute($CompiledRoute + array(
            'MatchedPath'=> $MatchedPath,
            'MatchedVars'=> $MatchedVars,
        ));

        // return object
        return $Route;
    }

}


?>