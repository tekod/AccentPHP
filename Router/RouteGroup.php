<?php namespace Accent\Router;

/**
 * RouteGroup is extending Collection class with route-specific features.
 */

use Accent\AccentCore\ArrayUtils\Collection;
use Accent\Router\Route;


class RouteGroup extends Collection {

    // set of group rules
    protected $GroupRules= array(
        'PathPrefix'  => null,
        'Host'        => null,
        'Secure'      => null,
        'Method'      => null,
    );

    // enumerate rules that have to be concated (instead of being overwriten) with deeper routegroup rule
    public $ConcatingRules= array(
        'PathPrefix',
    );

    // instance of parent RouteGroup
    public $Owner;


    ///////////////////////////////////////////////////////////////////
    //       methods for group rules manipulation
    //////////////////////////////////////////////////////////////////

    /**
     * Return specified group-rule or rules set.
     *
     * @param null|string $Key
     * @return mixed
     */
    public function GetGroupRules($Key=null) {

        return $Key === null
            ? $this->GroupRules
            : $this->GroupRules[$Key];
    }


    /**
     * Set multiple group-rules as key->value array.
     * Not overwritten rules will be preserved.
     *
     * @param array $GroupOptions
     * @return self
     */
    public function SetGroupRules($GroupOptions) {

        $this->GroupRules= $GroupOptions + $this->GroupRules;
        return $this;
    }


    /**
     * Set specified group-rule.
     *
     * @param string $Key
     * @param mixed $Value
     * @return self
     */
    public function SetGroupRule($Key, $Value) {

        $this->GroupRules[$Key]= $Value;
        return $this;
    }


    /**
     * Merges local group-rules with rules from parameter.
     *
     * @param array $ParentRules
     * @return array
     */
    public function GetMergedGroupRules($ParentRules) {

        // simply sum two arrays, override parent's values
        $Merged= array_filter($this->GroupRules) + $ParentRules;

        // manualy handle fields that need to be concated
        foreach(array_keys($Merged) as $Key) {
            if (in_array($Key, $this->ConcatingRules)) {
                $Merged[$Key]= (isset($ParentRules[$Key]) ? $ParentRules[$Key] : '')
                    .(isset($this->GroupRules[$Key]) ? $this->GroupRules[$Key] : '');
            }
        }

        // return resulting array
        return $Merged;
    }



    //////////////////////////////////////////////////////////////////
    //      methods for routes manipulation
    //////////////////////////////////////////////////////////////////

    /**
     * Append route to collection (as object or definition).
     *
     * @param array|Route|RouteGroup $Route
     * @return self
     */
    public function AddRoute($Route) {

        // if suppiled route is object of Route
        if (is_object($Route)) {
            $this->Append($Route);
            return $this;
        }

        if (!is_array($Route)) {    // this class is not descendant of Component so Error method is not available,
            return $this;           // just ignore
        }

        // if supplied route is sub-group contruction (as array(GroupRules=>[],GroupRoute=>[])))
        if (isset($Route['GroupRoutes'])) {
            $SubGroup= $this->AddGroup($Route['GroupRules']);
            $SubGroup->AddRoutes($Route['GroupRoutes']);
            return $this;
        }

        // if supplied route is route-structure (as array)
        $Route= $this->BuildRoute($Route);
        $this->Append($Route);
        return $this;
    }


    /**
     * Append multiple routes to collection.
     *
     * @param array $Array
     * @return self
     */
    public function AddRoutes($Array) {

        foreach($Array as $Route) {
            $this->AddRoute($Route);
        }
        return $this;
    }


    /**
     * Converts route definition into route object.
     *
     * @param array $Struct
     * @param object $Owner
     * @return Route
     */
    public function BuildRoute($Struct, $Owner=null) {

        $Route= new Route;
        foreach($Struct as $k=>$v) {
            $Route->$k= $v;
        }
        $Route->Owner= $Owner === null ? $this : $Owner;
        return $Route;
    }


    /**
     * Append sub-group to collection.
     *
     * @param array $GroupRules
     * @return RouteGroup
     */
    public function AddGroup($GroupRules) {

        // create sub-group from same class as current
        $SubGroup= new static;
        $SubGroup->Owner= $this;
        $SubGroup->ConcatingRules= $this->ConcatingRules;

        // import supplied list of rules
        $SubGroup->SetGroupRules($GroupRules);

        // append it to list
        $this->Append($SubGroup);

        // return newly created object
        return $SubGroup;
    }



    ///////////////////////////////////////////////////////////////////////////
    //      utilities
    //////////////////////////////////////////////////////////////////////

    /**
     * Find route by its name.
     * All group rules are included in resulting object.
     *
     * @param string $Name
     * @return null|Route
     */
    public function GetRouteByName_Old($Name) {

        foreach ($this->Buffer as $Item) {
            if (is_a($Item, 'Accent\\Router\\RouteGroup')) {
                $Route= $Item->GetRouteByName($Name);
                if ($Route !== null) {
                    return $Route;
                }
            } else if ($Item->Name === $Name) {
                return $Item;
            }
        }
        return null;
    }


    /**
     * Search for route with specified name.
     *
     * @param string $Name
     * @return false|Route
     */
    public function GetRouteByName($Name) {

        return $this->GetRouteByNameRecursive($Name, $this->Buffer, $this->GroupRules);
    }


    /**
     * Internal loop method.
     */
    protected function GetRouteByNameRecursive($Name, $Collection, $Rules) {

        foreach($Collection as $Route) {
            // is this sub-group?
            if (is_a($Route, 'Accent\\Router\\RouteGroup')) {
                // yes, call recursion
                $Result= $this->GetRouteByNameRecursive($Name, $Route->ToArray(), $Route->GetMergedGroupRules($Rules));
                if ($Result !== null) {
                    return $Result;
                }
                continue;
            }

            // no, this is route object
            if ($Route->Name <> $Name) {
                continue;
            }

            // append group rules (not overwrite, array merging)
            foreach ($Rules as $Key=>$Value) {
                if (is_array($Value)) {
                    $Route->$Key= property_exists($Route, $Key) ? $Route->$Key + $Value : $Value;
                } else if (!property_exists($Route, $Key) || $Route->$Key === null) {
                    $Route->$Key= $Value;
                }
            }

            // return finding
            return $Route;
        }

        // return failure
        return null;
    }


}



?>