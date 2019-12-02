<?php namespace Accent\Security\RBAC\DataProvider;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Base class for all RBAC data providers.
 */


use \Accent\AccentCore\Component;


abstract class AbstractDataProvider extends Component {



    abstract public function IsAllowed($UserId, $Permition, $Action);



    /*****************************************************
     *         Methods for role managements              *
     *****************************************************/

    abstract public function GetRoleIdByName($RoleName);

    abstract public function GetRole($RoleId);

    abstract public function GetAllRoles();

    abstract public function CreateRole($RoleName, $Properties=[], $Inherits=[]);

    abstract public function UpdateRole($RoleId, array $Properties);

    abstract public function DeleteRole($RoleId);

    abstract public function DeleteAllRoles();



    /*****************************************************
     *     Methods for bridging roles with user          *
     *****************************************************/

    abstract public function GetUserRoles($UserId, $WithInherited=true);

    abstract public function AssignRoles($UserId, $Roles);

    abstract public function RevokeRoles($UserId, $Roles);



    /*****************************************************
     *      Methods for dealing with permitions          *
     *****************************************************/

    abstract public function GetAllPermitions();

    abstract public function GetPermition($PermId);

    abstract public function GetPermitionIdByName($Name);

    abstract public function RegisterPermition($Name, $AvailableActions='');

    abstract public function UpdatePermition($PermId, array $Properties);

    abstract public function RemovePermition($PermId);


    /********************************************************
     *     Methods for bridging permitions with roles       *
     ********************************************************/

    abstract public function GetRolePermitions($RoleId, $WithInherited=true);

    abstract public function GrantRolePermition($RoleId, $PermId, $Actions='*');

    abstract public function RevokeRolePermition($RoleId, $PermId);

    abstract public function UpdateRolePermitions($RoleId, $Perms);


    /*****************************************************
     *      Methods for dealing with permitions          *
     *****************************************************/

    abstract public function GetRoleInheritances($RoleId);

    abstract public function AddRoleInheritance($RoleId, $InheritFrom);

    abstract public function RemoveRoleInheritance($RoleId, $InheritFrom);

    abstract public function RemoveAllRoleInheritances($RoleId);



}

?>