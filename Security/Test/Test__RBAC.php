<?php namespace Accent\Security\RBAC\Test;

use Accent\Test\AccentTestCase;
use Accent\Security\RBAC\RBAC;


class Test__RBAC extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'RBAC service test';

    // title of testing group
    const TEST_GROUP= 'Security';


    protected function Build($Options=array()) {

        $DefOptions= array(
            'DataProvider'=> 'File',
            'FilePath'=> __DIR__.'/rbac-data/data.php',
            'Services'=> array(
                //
            ),
        );
        return new RBAC($DefOptions + $Options);
    }


    // TESTS:

    public function TestGetAllRoles() {

        $R= $this->Build();
        $Roles= $R->GetAllRoles();
        $this->assertTrue(is_array($Roles));
        $this->assertTrue(in_array('Editor', $Roles));
    }


    public function TestGetRoleIdByName() {

        $R= $this->Build();
        $ID= $R->GetRoleIdByName('Editor');
        $AllRoles= $R->GetAllRoles();
        $this->assertTrue(isset($AllRoles[$ID]));
        $this->assertEqual($AllRoles[$ID], 'Editor');
        // query non existing role
        $Wrong= $R->GetRoleIdByName('oOoOoOoOo');
        $this->assertFalse($Wrong);
    }


    public function TestCreateRole() {

        $R= $this->Build();
        $rId= $R->CreateRole('Developer', 'Programming staff.');
        $Info= $R->GetRole($rId);
        $Expected= array(
            'Name'=> 'Developer',
            'Description'=> 'Programming staff.',
        );
        $this->assertEqual($Info, $Expected);
    }


    public function TestUpdateRole() {

        $R= $this->Build();
        $rId= $R->GetRoleIdByName('Editor');
        $Success= $R->UpdateRole($rId, array(
            'Name'=> 'Subscriber',
            'Description'=> 'Receivers of newsletters',
        ));
        $this->assertTrue($Success);
        // re-read RoleId becouse Array provider will change Id
        $rId= $R->GetRoleIdByName('Subscriber');
        // check name in Roles section
        $this->assertTrue(in_array('Subscriber', $R->GetAllRoles()));
        // check name in Inheritances
        $AdmRole= $R->GetRoleIdByName('Administrator');
        $List= $R->GetRoleInheritances($AdmRole);
        $this->assertTrue(is_array($List));
        $this->assertTrue(in_array($rId, $List));
        $this->assertEqual(count($List), 2);
        // check in permition assignment section
        $Perms= $R->GetRolePermitions($rId);
        $this->assertTrue(isset($Perms['Article']));
        // check in user assignment section
        $AssignedRoles= $R->GetUserRoles(2);
        $this->assertEqual($AssignedRoles, array($rId));
        // check description
        $Info= $R->GetRole($rId);
        $this->assertEqual($Info['Description'], 'Receivers of newsletters');
    }


    public function TestDeleteRole() {

        $R= $this->Build();
        $rId= $R->GetRoleIdByName('Editor');
        $Success= $R->DeleteRole($rId);
        $this->assertTrue($Success);
        // check name in Roles section
        $this->assertFalse(in_array('Editor', $R->GetAllRoles()));
        // check name in Inheritances
        $AdmRole= $R->GetRoleIdByName('Administrator');
        $List= $R->GetRoleInheritances($AdmRole);
        $this->assertTrue(is_array($List));
        $this->assertEqual(count($List), 1);
        // check in user assignment section
        $AssignedRoles= $R->GetUserRoles(2);
        $this->assertEqual($AssignedRoles, array());
        // check description
        $Info= $R->GetRole($rId);
        $this->assertTrue($Info === false);
    }


    public function TestDeleteAllRoles() {

        $R= $this->Build();
        $R->DeleteAllRoles();
        $Roles= $R->GetAllRoles();
        $this->assertTrue(empty($Roles));
        $Perms= $R->GetAllPermitions();
        $this->assertTrue(empty($Perms));
    }


    public function TestGetUserRoles() {

        $R= $this->Build();
        $Adm= $R->GetRoleIdByName('Administrator');
        $Ed=  $R->GetRoleIdByName('Editor');
        $CoM= $R->GetRoleIdByName('CommunityMgr');
        // list with inheritance
        $Roles= $R->GetUserRoles(1);
        $this->assertTrue(is_array($Roles));
        $this->assertEqual($Roles, array($Adm,$Ed,$CoM));
        // list without inheritance
        $Roles= $R->GetUserRoles(1, false);
        $this->assertEqual($Roles, array($Adm));
        // more tests
        $Roles= $R->GetUserRoles(4);
        $this->assertEqual($Roles, array());
        $Roles= $R->GetUserRoles(4, false);
        $this->assertEqual($Roles, array());
        $Roles= $R->GetUserRoles(5);
        $this->assertEqual($Roles, array($Ed,$CoM));
    }


    public function TestAssignRoles() {

        $R= $this->Build();
        $rId= $R->GetRoleIdByName('Editor');
        $R->AssignRoles(4, $rId);
        $Roles= $R->GetUserRoles(4);
        $this->assertEqual($Roles, array($rId));
    }


    public function TestRevokeRoles() {

        $R= $this->Build();
        $Ed= $R->GetRoleIdByName('Editor');
        $CoM= $R->GetRoleIdByName('CommunityMgr');
        $R->RevokeRoles(5, $Ed);
        $Roles= $R->GetUserRoles(5);
        $this->assertEqual($Roles, array($CoM));
    }


    public function TestGetAllPermitions() {

        $R= $this->Build();
        $Perms= $R->GetAllPermitions();
        $this->assertTrue(isset($Perms['Uploads']));
        $this->assertEqual($Perms['Uploads'], 'VAD');
    }


    public function TestGetPermition() {

        $R= $this->Build();
        $Perm= $R->GetPermition('Comments');
        $this->assertEqual($Perm['AvailableActions'], 'VMDP');
    }


    public function TestGetPermitionIdByName() {

        $R= $this->Build();
        $Perms= $R->GetAllPermitions();
        $pId= $R->GetPermitionIdByName('Comments');
        $this->assertEqual($Perms[$pId], 'VMDP');
    }


    public function TestRegisterPermition() {

        $R= $this->Build();
        $pId= $R->RegisterPermition('Logs', 'V');
        $Perms= $R->GetAllPermitions();
        $this->assertEqual($Perms[$pId], 'V');
    }


    public function TestUpdatePermition() {

        $R= $this->Build();
        $pId= $R->GetPermitionIdByName('Uploads');
        $Success= $R->UpdatePermition($pId, array(
            'Name'=> 'Downloads',
            'AvailableActions'=> 'VAMD',
        ));
        $this->assertTrue($Success);
        $pId= $R->GetPermitionIdByName('Downloads'); // re-read
        $AllPerms= $R->GetAllPermitions();
        $this->assertTrue($AllPerms[$pId], 'VAMD');
        // check in permition assignment section
        $rId= $R->GetRoleIdByName('Editor');
        $EditorPerms= $R->GetRolePermitions($rId);
        $this->assertTrue(isset($EditorPerms['Downloads']));
        $this->assertFalse(isset($EditorPerms['Uploads']));
    }


    public function TestRemovePermition() {

        $R= $this->Build();
        $pId= $R->GetPermitionIdByName('Uploads');
        $Success= $R->RemovePermition($pId);
        $this->assertTrue($Success);
        $AllPerms= $R->GetAllPermitions();
        $this->assertFalse(isset($AllPerms[$pId]));
        // check in permition assignment section
        $rId= $R->GetRoleIdByName('Editor');
        $EditorPerms= $R->GetRolePermitions($rId);
        $this->assertFalse(isset($EditorPerms['Uploads']));
    }


    public function TestGetRolePermitions() {

        $R= $this->Build();
        // test role without inheritance
        $rId= $R->GetRoleIdByName('Editor');
        $EditorPerms= $R->GetRolePermitions($rId);
        $Expected= array(
            'Article'=> 'VAM',
            'Uploads'=> 'VA',
        );
        $this->assertEqual($EditorPerms, $Expected);
        // test role with inheritance
        $rId= $R->GetRoleIdByName('Administrator');
        $AdmPerms= $R->GetRolePermitions($rId);
        $Expected= array(
            'Article'=> '*',
            'Uploads'=> '*',
            'Comments'=> '*',
            'ClearCache'=> '*',
        );
        $this->assertEqual($AdmPerms, $Expected);
    }


    public function TestGrantPermition() {

        $R= $this->Build();
        // test role without inheritance
        $rId= $R->GetRoleIdByName('Editor');
        $pId= $R->GetPermitionIdByName('Comments');
        $Success= $R->GrantRolePermition($rId, $pId, 'V');
        $this->assertTrue($Success);
        $EditorPerms= $R->GetRolePermitions($rId);
        $Expected= array(
            'Article'=> 'VAM',
            'Uploads'=> 'VA',
            'Comments'=> 'V',
        );
        $this->assertEqual($EditorPerms, $Expected);
    }


    public function TestRevokePermition() {

        $R= $this->Build();
        $rId= $R->GetRoleIdByName('Editor');
        // remove Uploads
        $pId= $R->GetPermitionIdByName('Uploads');
        $Success= $R->RevokeRolePermition($rId, $pId);
        $this->assertTrue($Success);
        $EditorPerms= $R->GetRolePermitions($rId);
        $Expected= array(
            'Article'=> 'VAM',
        );
        $this->assertEqual($EditorPerms, $Expected);
        // go further
        $pId= $R->GetPermitionIdByName('Article');
        $Success= $R->RevokeRolePermition($rId, $pId);
        $this->assertTrue($Success);
        $EditorPerms= $R->GetRolePermitions($rId);
        $Expected= array();
        $this->assertEqual($EditorPerms, $Expected);
        // removing non exising permition
        $pId= $R->GetPermitionIdByName('xyz');
        $Success= $R->RevokeRolePermition($rId, $pId);
        $this->assertFalse($Success);
    }


    public function TestGetRoleInheritances() {

        $R= $this->Build();
        $AdmRole= $R->GetRoleIdByName('Administrator');
        $EdRole= $R->GetRoleIdByName('Editor');
        // check administrator
        $List= $R->GetRoleInheritances($AdmRole);
        $this->assertTrue(is_array($List));
        $this->assertTrue(in_array($EdRole, $List));
        $this->assertEqual(count($List), 2);
        // check editor
        $List= $R->GetRoleInheritances($EdRole);
        $this->assertTrue(is_array($List));
        $this->assertTrue(empty($List));
    }


    public function TestAddRoleInheritance() {

        $R= $this->Build();
        $AdmRole= $R->GetRoleIdByName('Administrator');
        $EdRole= $R->GetRoleIdByName('Editor');
        $DevRole= $R->CreateRole('Developer', 'Programming staff.');
        $Success= $R->AddRoleInheritance($DevRole, $AdmRole);
        $this->assertTrue($Success);
        $List= $R->GetRoleInheritances($DevRole);
        $this->assertTrue(is_array($List));
        $this->assertEqual(count($List), 3);
    }


    public function TestRemoveRoleInheritance() {

        $R= $this->Build();
        $AdmRole= $R->GetRoleIdByName('Administrator');
        $EdRole= $R->GetRoleIdByName('Editor');
        $Success= $R->RemoveRoleInheritance($AdmRole, $EdRole);
        $this->assertTrue($Success);
        $List= $R->GetRoleInheritances($AdmRole);
        $this->assertTrue(is_array($List));
        $this->assertEqual(count($List), 1);
    }


    public function TestRemoveAllRoleInheritances() {

        $R= $this->Build();
        $AdmRole= $R->GetRoleIdByName('Administrator');
        $Success= $R->RemoveAllRoleInheritances($AdmRole);
        $this->assertTrue($Success);
        $List= $R->GetRoleInheritances($AdmRole);
        $this->assertEqual(count($List), 0);
    }


    public function TestIsAllowed() {

        $Tests= array(
            array('1','Article.V',true), // simple test
            array('1','Article.*',true), // all
            array('1','Article.?',true),  // unknown action
            array('1','Article',true),     // ommited action
            array('2','Uploads.V',true),    // other role
            array('2','Uploads.M',false),    // other role - false
            array('1','Comments.*',true), // inherited permition
            array('oOoOo','Uploads.V',false),  // unknown role
        );
        $R= $this->Build();
        foreach($Tests as $TestNo=>$Test) {
            $IsAllowed= $R->IsAllowed($Test[0], $Test[1]);
            $this->assertTrue($IsAllowed === $Test[2], '(TestNo:'.($TestNo+1).')');
        }
    }

}


?>