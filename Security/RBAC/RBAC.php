<?php namespace Accent\Security\RBAC;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 *
 */

use Accent\Security\RBAC\DataProvider\AbstractDataProvider;


class RBAC extends AbstractDataProvider {


    protected static $DefaultOptions= array(

        'DataProvider'=> 'Array', // [Array, File, Db, FQCN, object of BaseDataProvider]

        'Services'=> array(
        ),
    );


    protected $DataProvider;


    public function __construct($Options = array()) {

        parent::__construct($Options);

        $this->DataProvider= $this->GetOption('DataProvider');
        if (is_string($this->DataProvider)) {
            $Class= strpos($this->DataProvider, '\\') === false
                ? '\\Accent\\Security\\RBAC\\DataProvider\\'.$this->DataProvider.'DataProvider'
                : $this->DataProvider;
            $this->DataProvider= new $Class($this->GetAllOptions());
        }
    }



    public function IsAllowed($UserId, $Permition, $Action=null) {

        if ($Action === null) {
            $Parts= explode('.', $Permition);
            if (count($Parts) > 1) {
                $Action= array_pop($Parts);
                $Permition= implode('.', $Parts);
            } else {
                $Action= '*';
            }
        }
        return $this->DataProvider->IsAllowed($UserId, $Permition, $Action);
    }



    /*****************************************************
     *         Methods for role managements              *
     *****************************************************/


    /**
     * Return ID of role specified by name.
     *
     * @param string $RoleName
     * @return mixed
     */
    public function GetRoleIdByName($RoleName) {

        return $this->DataProvider->GetRoleIdByName($RoleName);
    }


    /**
     * Return informations about specified role.
     *
     * @param string $RoleId
     * @return array|false
     */
    public function GetRole($RoleId) {

        return $this->DataProvider->GetRole($RoleId);
    }


    /**
     * Return list of names of all roles as "role id" => "name" array.
     *
     * @return array
     */
    public function GetAllRoles() {

        return $this->DataProvider->GetAllRoles();
    }


    /**
     * Create new role.
     *
     * @param string $RoleName
     * @param string $Description
     * @param array $Inherits
     * @return mixed  Id of new role
     */
    public function CreateRole($RoleName, $Description='', $Inherits=array()) {

        return $this->DataProvider->CreateRole($RoleName, $Description, $Inherits);
    }


    /**
     * Modify role.
     *
     * @param mixed $RoleId
     * @param array $Values  key=>value data
     * @return bool  Success
     */
    public function UpdateRole($RoleId, array $Values) {

        return $this->DataProvider->UpdateRole($RoleId, $Values);
    }


    /**
     * Remove role from storage.
     *
     * @param mixed $RoleId
     * @return bool  success
     */
    public function DeleteRole($RoleId) {

        return $this->DataProvider->DeleteRole($RoleId);
    }


    /**
     * Remove all roles and permitions from storage.
     *
     * @return bool  success
     */
    public function DeleteAllRoles() {

        return $this->DataProvider->DeleteAllRoles();
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

        return $this->DataProvider->GetUserRoles($UserId, $WithInherited);
    }


    /**
     * Grant role to specified user.
     *
     * @param mixed $UserId
     * @param array $Roles
     * @return boolean  success
     */
    public function AssignRoles($UserId, $Roles) {

        return $this->DataProvider->AssignRoles($UserId, $Roles);
    }


    /**
     * Remove assigned role(s) from specified user.
     *
     * @param mixed $UserId
     * @param array $Roles
     * @return boolean  success
     */
    public function RevokeRoles($UserId, $Roles) {

        return $this->DataProvider->RevokeRoles($UserId, $Roles);
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

        return $this->DataProvider->GetAllPermitions();
    }


    /**
     * Return specified permition.
     *
     * @param mixed $PermId
     * @return array|false
     */
    public function GetPermition($PermId) {

        return $this->DataProvider->GetPermition($PermId);
    }


    /**
     * Return Permitions ID with specified name.
     *
     * @param string $Name
     * @return mixed|false
     */
    public function GetPermitionIdByName($Name) {

        return $this->DataProvider->GetPermitionIdByName($Name);
    }


    /**
     * Add specfied permition to registry.
     *
     * @param string $Name
     * @param string $AvailableActions
     * @return mixed  id of new record
     */
    public function RegisterPermition($Name, $AvailableActions='') {

        return $this->DataProvider->RegisterPermition($Name, $AvailableActions);
    }


    /**
     * Update existing permition.
     *
     * @param mixed $PermId
     * @param array $Values  posible keys:['Name','AvaliableActions']
     * @return boolean  success
     */
    public function UpdatePermition($PermId, array $Values) {

        return $this->DataProvider->UpdatePermition($PermId, $Values);
    }


    /**
     * Remove specified permition from registry.
     *
     * @param mixed $PermId
     * @return boolean  success
     */
    public function RemovePermition($PermId) {

        return $this->DataProvider->RemovePermition($PermId);
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

        return $this->DataProvider->GetRolePermitions($RoleId, $WithInherited);
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

        return $this->DataProvider->GrantRolePermition($RoleId, $PermId, $Actions);
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

        return $this->DataProvider->RevokeRolePermition($RoleId, $PermId);
    }


    /**
     * Clear old and set new set of permitions to specified role.
     * $Perms can be specified as array with permition name as index and action as value
     * or as string like 'Perm1:VA,Perm2:*,Perm3'.
     * Will fail if role not exist.
     *
     * @param mixed $RoleId
     * @param array $Perms
     */
    public function UpdateRolePermitions($RoleId, $Perms) {

        if (is_string($Perms)) {
            $Ex= array_filter(explode(',', $Perms));
            $Perms= array();
            foreach($Ex as $item) {
                $A= explode(':',$item);
                $Perms[trim($A[0])]= isset($A[1]) ? trim($A[1]) : '*';
            }
        }
        return $this->DataProvider->UpdateRolePermition($RoleId, $Perms);
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

        return $this->DataProvider->GetRoleInheritances($RoleId);
    }


    /**
     * Declare that $RoleId role will inherit all permitions from $InheritFrom role.
     *
     * @param mixed $RoleId
     * @param mixed $InheritFrom
     * @return boolean  success
     */
    public function AddRoleInheritance($RoleId, $InheritFrom) {

        return $this->DataProvider->AddRoleInheritance($RoleId, $InheritFrom);
    }


    /**
     * Remove inheritance relation between $RoleId role and $InheritFrom role.
     *
     * @param mixed $RoleId
     * @param mixed $InheritFrom
     * @return boolean  success
     */
    public function RemoveRoleInheritance($RoleId, $InheritFrom) {

        return $this->DataProvider->RemoveRoleInheritance($RoleId, $InheritFrom);
    }


    /**
     * Remove all inheritance relations for $RoleId role.
     *
     * @param mixed $RoleId
     * @return boolean
     */
    public function RemoveAllRoleInheritances($RoleId) {

        return $this->DataProvider->RemoveAllRoleInheritances($RoleId);
    }

}

?>