<?php namespace Accent\Router\Rule;

/**
 * Routing rule "Path".
 * It's job is to compare path from HTTP request with specified value.
 * This is one of primary rules so it must return object of Route on positive matching in Check method.
 */

use Accent\Router\Rule\AbstractRule;


class Path extends AbstractRule {


    // it is primary rule
    public $IsPrimaryRule= true;

    // RequestPath is pre-calculated before matching loops,
    // its value is taken from REQUEST_URI, removed everything after "?" and removed "BasePath" prefix
    protected $RequestPath;


    public function Compile($CompiledRoute) {

        if (isset($CompiledRoute['Path'])) {

            // retrieve path
            $Path= !isset($CompiledRoute['PathPrefix']) || !$CompiledRoute['PathPrefix']
                ? $CompiledRoute['Path']
                : $CompiledRoute['PathPrefix'].$CompiledRoute['Path'];
            $Path= rtrim($Path, '/');

            if (strpos($Path, '{') === false && strpos($Path, '[') === false) {
                // this is simple string comparison
                // save string into separate field of compiled route
                $CompiledRoute['_PathString']= $Path;
                // handle Wildcard option
                if (isset($CompiledRoute['Wildcard']) && $CompiledRoute['Wildcard']) {
                    $CompiledRoute['Wildcard']= strlen($Path);          // will be used in comparison
                } else {
                    unset($CompiledRoute['Wildcard']);                  // remove unused
                }
            } else {
                // prepare regularexpression pattern
                $Reg= str_replace(array('[',']'), array('(',')?'), $Path); // handle optional parts of path
                $Reg= preg_replace('/{(\w+?)}/u', '(?P<$1>[^/]+?)', $Reg); // handle named parametar
                //handle Wildcard option
                if (isset($CompiledRoute['Wildcard']) && $CompiledRoute['Wildcard']) {
                    //$Reg .= '(/.*)';
                    $Reg .= '(?P<Wildcard>/.*?)?';
                }
                // save pattern into separate field of compiled route
                $CompiledRoute['_PathPattern']= '`^'.$Reg.'$`u';
            }

            // this is not needed any more
            unset($CompiledRoute['PathPrefix']);
        }

        return $CompiledRoute;
    }


    public function Check($CompiledRoute) {

        if (isset($CompiledRoute['_PathString'])) {
            // fast comparison case
            if (isset($CompiledRoute['Wildcard'])) {
                // perform comparison in part of string
                if ($CompiledRoute['_PathString'] <> rtrim(substr($this->RequestPath, 0, $CompiledRoute['Wildcard']+1), '/')) {
                    // matching failed, skip this route
                    return self::RESULT_SKIP_ROUTE;
                }
            } else {
                // perform simple string comparison
                if ($CompiledRoute['_PathString'] <> $this->RequestPath) {
                    // matching failed, skip this route
                    return self::RESULT_SKIP_ROUTE;
                }
            }
            // matching found
            $MatchedVars= isset($CompiledRoute['Wildcard'])
                ? array('Wildcard'=> substr($this->RequestPath, $CompiledRoute['Wildcard']))
                : array();
            $MatchedPath= $this->RequestPath;
        }
        else if (isset($CompiledRoute['_PathPattern'])) {
            // perform regex check
            $Matches= null;  // remove IDE warning
            if (!preg_match_all($CompiledRoute['_PathPattern'], $this->RequestPath, $Matches)) {
                // matching failed, skip this route
                return self::RESULT_SKIP_ROUTE;
            }
            // matching found, extract values of named variables
            $MatchedPath= array_shift($Matches);
            $MatchedPath= $MatchedPath[0];
            $MatchedVars= $this->SanitizeMatchings($Matches);
        }
        else {
            // there is no job for me, try with next rule
            return self::RESULT_CONTINUE;
        }

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


    /**
     * Check are variables valid, according to supplied rules.
     *
     * @param array $ValidationRules
     * @param array $Vars
     * @return boolean
     */
    protected function IsValidMatchedVars($ValidationRules, $Vars) {

        // assume it is valid if not validation is specified
        if (empty($ValidationRules)) {
            return true;
        }

        // load validator service
        $Validator= $this->GetService('Validator');
        if (!$Validator) {
            $this->Error('Router: Missing Validator service.');
            return false;
        }

        // perform specified validations
        // note: empty variables comes from optional parts of paths, it has sense to validate them only if they are present
        foreach($ValidationRules as $k=>$v) {
            if (!isset($Vars[$k]) || $Vars[$k] === '') {
                return true;
            }
            $Res= $Validator->ValidateAll($Vars[$k], $v, $Vars);
            if (!empty($Res)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Removes duplications in result of preg_match_all().
     */
    protected function SanitizeMatchings($Vars) {

        foreach(array_keys($Vars) as $k) {
            if (is_numeric($k)) {
                unset($Vars[$k]);
            } else {
                $Vars[$k]= $Vars[$k][0];
            }
        }
        return $Vars;
    }


    /**
     * Extend parent method with some logic:
     * prepare RequestPath value.
     */
    public function SetContext($Context) {

        // call parent to populate $this->Context
        parent::SetContext($Context);

        // extract BasePath config options passed from Router, ensure it begins with slash if not empty
        $BasePath= '/'.trim($this->GetOption('BasePath'), '/');
        $BasePath= rtrim($BasePath, '/');

        // remove everything after "?" and trailing slash, unescape and strip off base path
        $Path= rtrim(strtok($this->Context->SERVER['REQUEST_URI'], '?'), '/');
        $Path= urldecode($Path);
        $Path= substr($Path, strlen($BasePath));

        // store it for later comparison
        $this->RequestPath= $Path;
    }


}


?>