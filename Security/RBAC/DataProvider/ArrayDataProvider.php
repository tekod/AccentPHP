<?php namespace Accent\Security\RBAC\DataProvider;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Array-based RBAC data provider.
 */


use \Accent\Security\RBAC\DataProvider\AbstractDataProvider;


class ArrayDataProvider extends AbstractDataProvider {


    // default cofiguration
    protected static $DefaultOptions= array(

        //
        'Roles'=> array(),

        //
        'Permition-Role'=> array(),

        //
        'RoleInheritances'=> array(),

        //
        'User-Role'=> array(),

    );

    // internal properties
    protected $Data;


    /**
     * Constructor.
     */
    public function __construct($Options) {

        // call ancestor
        parent::__construct($Options);

        // expost configuration into local property
        $this->Data= [
            'Roles'=>  $this->GetOption('Roles'),
            'Permition-Role'=>  $this->GetOption('Permition-Role'),
            'RoleInheritances'=>  $this->GetOption('RoleInheritances'),
            'User-Role'=>  $this->GetOption('User-Role'),
        ];

    }


    public function IsAllowed($UserId, $Permition, $Action) {

        if (!isset($this->Data['Permition-Role'][$Permition])) {
            return false;
        }
        $Assignments= array_intersect_key(
            $this->Data['Permition-Role'][$Permition],
            array_flip($this->GetUserRoles($UserId))
        );
        foreach($Assignments as $ActionList) {
            if ($ActionList === '*' ||
                $ActionList === '' ||
                strpos($ActionList, $Action) !== false
            ) {
                return true;
            }
        }
        return false;
    }




    /*****************************************************
     *         Methods for role managements              *
     *****************************************************/


    /**
     * Return ID of role specified by name.
     *
     * @param string $RoleName
     * @return mixed|false
     */
    public function GetRoleIdByName($RoleName) {

        // in ArrayDataProvider name of role is used as ID in storage,
        // others drivers will probably return integer
        return isset($this->Data['Roles'][$RoleName])
            ? $RoleName
            : false;
    }


    /**
     * Return informations about specified role.
     *
     * @param string $RoleId
     * @return array|false
     */
    public function GetRole($RoleId) {

        return isset($this->Data['Roles'][$RoleId])
            ? array(
                'Name'=> $RoleId,
                'Description'=> $this->Data['Roles'][$RoleId],
            )
            : false;
    }




    /**
     * Return list of names of all roles as "role id" => "name" array.
     *
     * @return array
     */
    public function GetAllRoles() {

        $Names= array_keys($this->Data['Roles']);
        return array_combine($Names, $Names);
    }


    /**
     * Create new role.
     *
     * @param string $RoleName
     * @param array $Properties
     * @param array $Inherits
     * @return mixed  Id of new role
     */
    public function CreateRole($RoleName, $Properties=[], $Inherits=[]) {

        if (isset($this->Data['Roles'][$RoleName])) {
            return false;
        }
        $this->Data['Roles'][$RoleName]= $Properties;
        if (!empty($Inherits)) {
            $this->Data['RoleInheritances'][$RoleName]= $Inherits;
        }
        return $RoleName;
    }


    protected function LoopDetection($Id) {

        static $Used;
        if ($Id === null) {
            $Used= array();
            return;
        }
        if (isset($Used[$Id])) {
            return true;
        }
        $Used[$Id]= true;
        return false;
    }



    /**
     * Modify role.
     *
     * @param mixed $RoleId
     * @param array $Values  key=>value data
     * @return bool  Success
     */
    public function UpdateRole($RoleId, array $Values) {

        if (!isset($this->Data['Roles'][$RoleId])) {
            return false;
        }
        $NewName= isset($Values['Name']) ? $Values['Name'] : $RoleId;
        $NewDesc= isset($Values['Description']) ? $Values['Description'] : $this->Data['Roles'][$RoleId];
        // update description
        if ($this->Data['Roles'][$RoleId] <> $NewDesc) {
            $this->Data['Roles'][$RoleId]= $NewDesc;
        }
        // update name
        if ($RoleId <> $NewName) {
            $this->ReplaceRoleName($RoleId, $NewName);
        }
        return true;
    }


    protected function ReplaceRoleName($Name, $NewName) {

        // update Roles section
        if ($NewName !== null) {
            $this->Data['Roles'][$NewName]= $this->Data['Roles'][$Name];
        }
        unset($this->Data['Roles'][$Name]);
        // update RoleInhertances section
        if (isset($this->Data['RoleInheritances'][$Name])) {
            if ($NewName !== null) {
                $this->Data['RoleInheritances'][$NewName]= $this->Data['RoleInheritances'][$Name];
            }
            unset($this->Data['RoleInheritances'][$Name]);
        }
        foreach($this->Data['RoleInheritances'] as $k=>$v) {
            $Found= array_search($Name, $v);
            if ($Found === false) {
                continue;
            }
            if ($NewName === null) {
                unset($this->Data['RoleInheritances'][$k][$Found]);
            } else {
                $this->Data['RoleInheritances'][$k][$Found]= $NewName;
            }
        }
        // update Permition-Role
        foreach(array_keys($this->Data['Permition-Role']) as $Perm) {
            foreach(array_keys($this->Data['Permition-Role'][$Perm]) as $Role) {
                if ($Role !== $Name) {
                    continue;
                }
                if ($NewName !== null && isset($this->Data['Permition-Role'][$Perm][$Name])) {
                    $this->Data['Permition-Role'][$Perm][$NewName]= $this->Data['Permition-Role'][$Perm][$Name];
                }
                unset($this->Data['Permition-Role'][$Perm][$Name]);
            }
        }
        // update User-Role
        foreach($this->Data['User-Role'] as $k=>$v) {
            $Found= array_search($Name, $v);
            if ($Found === false) {
                continue;
            }
            if ($NewName === null) {
                unset($this->Data['User-Role'][$k][$Found]);
            } else {
                $this->Data['User-Role'][$k][$Found]= $NewName;
            }
        }
    }


    /**
     * Remove role from storage.
     *
     * @param mixed $RoleId
     * @return bool  success
     */
    public function DeleteRole($RoleId) {

        if (!isset($this->Data['Roles'][$RoleId])) {
            return false;
        }
        $this->ReplaceRoleName($RoleId, null);
        return true;
    }


    /**
     * Remove all roles and permitions from storage.
     *
     * @return bool  success
     */
    public function DeleteAllRoles() {

        $this->Data= array(
            'Permitions'=> array(),
            'Roles'=> array(),
            'RoleInheritances'=> array(),
            'Permition-Role'=> array(),
            'User-Role'=> array(),
        );
        return true;
    }



    /*****************************************************
     *     Methods for bridging roles with user          *
     *****************************************************/

    /**
     * Return array with role names assigned to specified user.
     *
     * @param mixed $UserId
     * @param bool $WithInherited
     * @return array
     */
    public function GetUserRoles($UserId, $WithInherited=true) {

        if (!isset($this->Data['User-Role'][$UserId])) {
            return array();
        }
        $List= $this->Data['User-Role'][$UserId];
        if (!$WithInherited) {
            return $List;
        }
        foreach($this->Data['User-Role'][$UserId] as $Role) {
            $List= array_merge($List, $this->GetRoleInheritances($Role));
        }
        return array_unique($List);
    }


    /**
     * Grant role to specified user.
     *
     * @param mixed $UserId
     * @param array $Roles
     * @return boolean  success
     */
    public function AssignRoles($UserId, $Roles) {

        if (!isset($this->Data['User-Role'][$UserId])) {
            // we have no means to verify that user really exist so simply add it
            $this->Data['User-Role'][$UserId]= array();
        }
        if (!is_array($Roles)) {
            $Roles= array($Roles);
        }
        $this->Data['User-Role'][$UserId]= array_unique(array_merge(
                $this->Data['User-Role'][$UserId],
                $Roles
        ));
        return true;
    }


    /**
     * Remove assigned role(s) from specified user.
     *
     * @param mixed $UserId
     * @param array $Roles
     * @return boolean  success
     */
    public function RevokeRoles($UserId, $Roles) {

        // we have no means to verify that user really exist so dont throw error
        if (!isset($this->Data['User-Role'][$UserId])) {
            return true;
        }
        if (!is_array($Roles)) {
            $Roles= array($Roles);
        }
        $this->Data['User-Role'][$UserId]= array_filter(
            $this->Data['User-Role'][$UserId],
            function($Item) use (&$Roles) {return !in_array($Item, $Roles);}
        );
        return true;
    }



    /*****************************************************
     *      Methods for dealing with permitions          *
     *****************************************************/


    /**
     * Return array with all permitions (as key) and its posible actions (as value).
     *
     * @return array
     */
    public function GetAllPermitions() {

        return $this->Data['Permitions'];
    }


    /**
     * Return specified permition.
     *
     * @param mixed $PermId
     * @return array|false
     */
    public function GetPermition($PermId) {

        return isset($this->Data['Permitions'][$PermId])
            ? array(
                'Name'=> $PermId,
                'AvailableActions'=> $this->Data['Permitions'][$PermId],
            )
            : false;
    }


    /**
     * Return Permitions ID with specified name.
     *
     * @param string $Name
     * @return mixed|false
     */
    public function GetPermitionIdByName($Name) {

        return isset($this->Data['Permitions'][$Name])
            ? $Name // Array provider store permitions using name as id
            : false;
    }


    /**
     * Add specfied permition to registry.
     *
     * @param string $Name
     * @param string $AvailableActions
     * @return mixed|false  ID of new record
     */
    public function RegisterPermition($Name, $AvailableActions='') {

        if (isset($this->Data['Permitions'][$Name])) {
            return false;
        }
        $this->Data['Permitions'][$Name]= $AvailableActions;
        return $Name;
    }


    /**
     * Update existing permition.
     *
     * @param mixed $PermId
     * @param array $Values  posible keys:['Name','AvaliableActions']
     * @return boolean  success
     */
    public function UpdatePermition($PermId, array $Values) {

        if (!isset($this->Data['Permitions'][$PermId])) {
            return false;
        }
        $NewName= isset($Values['Name'])
            ? $Values['Name']
            : $PermId;
        $NewAvailActions= isset($Values['AvailableActions'])
            ? $Values['AvailableActions']
            : $this->Data['Permitions'][$PermId];
        // update actions
        if ($this->Data['Permitions'][$PermId] <> $NewAvailActions) {
            $this->Data['Permitions'][$PermId]= $NewAvailActions;
        }
        // update name
        if ($PermId <> $NewName) {
            $this->ReplacePermitionName($PermId, $NewName);
        }
        return true;
    }


    protected function ReplacePermitionName($Name, $NewName) {

        // update Permitions section
        if ($NewName !== null) {
            $this->Data['Permitions'][$NewName]= $this->Data['Permitions'][$Name];
        }
        unset($this->Data['Permitions'][$Name]);
        // update Permition-Role section
        if ($NewName !== null) {
            $this->Data['Permition-Role'][$NewName]= $this->Data['Permition-Role'][$Name];
        }
        unset($this->Data['Permition-Role'][$Name]);
    }


    /**
     * Remove specified permition from registry.
     *
     * @param mixed $PermId
     * @return boolean  success
     */
    public function RemovePermition($PermId) {

        if (!isset($this->Data['Permitions'][$PermId])) {
            return false;
        }
        $this->ReplacePermitionName($PermId, null);
        return true;
    }



    /********************************************************
     *     Methods for bridging permitions with roles       *
     ********************************************************/


    /**
     * Return array of all permitions (as key) and granted actions (as value)
     * for specified role, with or without permitions from inherited roles.
     *
     * @param mixed $RoleId
     * @param boolean $WithInherited
     * @return array|false
     */
    public function GetRolePermitions($RoleId, $WithInherited=true) {

        if (!isset($this->Data['Roles'][$RoleId])) {
            return false;
        }
        $Roles= array($RoleId);
        if ($WithInherited) {
            $Roles= array_merge($Roles, $this->GetRoleInheritances($RoleId));
        }
        $Permitions= array();
        foreach($this->Data['Permition-Role'] as $Perm=>$Assigns) {
            foreach($Assigns as $Role=>$Actions) {
                if (!in_array($Role, $Roles)) {
                    continue;
                }
                $Permitions[$Perm]= isset($Permitions[$Perm])
                    ? $this->MergeActions($Actions, $Permitions[$Perm])
                    : $Actions;
            }
        }
        return $Permitions;
    }


    // return sum of both strings but without duplicating chars
    protected function MergeActions($Actions1, $Actions2) {

        if ($Actions1==='*' || $Actions2==='*') {
            return '*';
        }
        // count_chars($str,3) cannot be used becouse we need to preserve order of chars
        foreach(str_split($Actions2) as $Char) {
            if (strpos($Char, $Actions1) === false) {
                $Actions1 .= $Char;
            }
        }
        return $Actions1;
    }


    /**
     * Assign permition to role.
     * Note that previous assigned actions will be overriden, not merged.
     * Will fail if role not exist.
     * Checking for permition existance is removed to allow defered permition registrations.
     *
     * @param mixed $RoleId
     * @param mixed $PermId
     * @param string $Actions
     * @return boolean  success
     */
    public function GrantRolePermition($RoleId, $PermId, $Actions='*') {

        if (!isset($this->Data['Roles'][$RoleId])) {
            return false;
        }
        if (!isset($this->Data['Permition-Role'][$PermId])) {
            $this->Data['Permition-Role'][$PermId]= array();
        }
        $this->Data['Permition-Role'][$PermId][$RoleId]= $Actions;
        return true;
    }


    /**
     * Withdraw assigned permition from specified role.
     * Will fail if role or permition not exist.
     *
     * @param mixed $RoleId
     * @param mixed $PermId
     * @return boolean  success
     */
    public function RevokeRolePermition($RoleId, $PermId) {

        if (!isset($this->Data['Roles'][$RoleId]) || !isset($this->Data['Permitions'][$PermId])) {
            return false;
        }
        if (isset($this->Data['Permition-Role'][$PermId])) {
            unset($this->Data['Permition-Role'][$PermId][$RoleId]);
        }
        return true;
    }


    /**
     * Clear old and set new set of permitions to specified role.
     * $Perms should be array with permition name as index and action as value.
     * Will fail if role not exist.
     *
     * @param mixed $RoleId
     * @param array $Perms
     */
    public function UpdateRolePermitions($RoleId, $Perms) {

        if (!isset($this->Data['Roles'][$RoleId])) {
            return false;
        }
        $this->Data['Permition-Role']= array();
        foreach($Perms as $PermId=>$Actions) {
            $this->Data['Permition-Role'][$PermId][$RoleId]= $Actions;
        }
        return true;
    }



    /*****************************************************
     *      Methods for dealing with permitions          *
     *****************************************************/


    /**
     * Return array with all inherited roles (IDs), recursive.
     *
     * @param mixed $RoleId
     * @return array|false
     */
    public function GetRoleInheritances($RoleId) {

        if (!isset($this->Data['Roles'][$RoleId])) {
            return false;
        }
        if (!isset($this->Data['RoleInheritances'][$RoleId])) {
            return array();
        }
        $this->LoopDetection(null);
        return $this->GetRoleInheritances_Recursive($this->Data['RoleInheritances'][$RoleId]);
    }


    protected function GetRoleInheritances_Recursive($SubRoles) {

        $List= array();
        foreach($SubRoles as $SubRole) {
            if (!isset($this->Data['Roles'][$SubRole]) || $this->LoopDetection($SubRole)) {
                continue;
            }
            $List[]= $SubRole;
            if (!isset($this->Data['RoleInheritances'][$SubRole])) {
                continue;
            }
            $SubSubRoles= $this->GetRoleInheritances_Recursive($this->Data['RoleInheritances'][$SubRole]);
            if ($SubSubRoles === false) {
                return false;
            }
            $List= array_merge($List, $SubSubRoles);
        }
        return $List;
    }


    /**
     * Declare that $RoleId role will inherit all permitions from $InheritFrom role.
     *
     * @param mixed $RoleId
     * @param mixed $InheritFrom
     * @return boolean  success
     */
    public function AddRoleInheritance($RoleId, $InheritFrom) {

        if (!isset($this->Data['Roles'][$RoleId]) || !isset($this->Data['Roles'][$InheritFrom])) {
            return false;
        }
        if (!isset($this->Data['RoleInheritances'][$RoleId])) {
            $this->Data['RoleInheritances'][$RoleId]= array();
        }
        $this->Data['RoleInheritances'][$RoleId]= array_unique(array_merge(
                $this->Data['RoleInheritances'][$RoleId],
                array($InheritFrom)
        ));
        return true;
    }


    /**
     * Remove inheritance relation between $RoleId role and $InheritFrom role.
     *
     * @param mixed $RoleId
     * @param mixed $InheritFrom
     * @return boolean  success
     */
    public function RemoveRoleInheritance($RoleId, $InheritFrom) {

        if (!isset($this->Data['Roles'][$RoleId]) || !isset($this->Data['Roles'][$InheritFrom])) {
            return false;
        }
        if (!isset($this->Data['RoleInheritances'][$RoleId])) {
            return true;
        }
        $Found= array_search($InheritFrom, $this->Data['RoleInheritances'][$RoleId]);
        if ($Found !== false) {
            unset($this->Data['RoleInheritances'][$RoleId][$Found]);
        }
        return true;
    }


    /**
     * Remove all inheritance relations for $RoleId role.
     *
     * @param mixed $RoleId
     * @return boolean
     */
    public function RemoveAllRoleInheritances($RoleId) {

        if (!isset($this->Data['Roles'][$RoleId])) {
            return false;
        }
        unset($this->Data['RoleInheritances'][$RoleId]);
        return true;
    }


}

?>