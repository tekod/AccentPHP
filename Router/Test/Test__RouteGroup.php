<?php namespace Accent\Router\Test;

use Accent\Test\AccentTestCase;
use Accent\Router\RouteGroup;


class Test__RouteGroup extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'RouteGroup test';

    // title of testing group
    const TEST_GROUP= 'Router';


    public function TestGroupRulesHandling() {

        $Group= new RouteGroup;
        // read all grouprules
        $GroupRules= $Group->GetGroupRules();
        $this->assertTrue(count($GroupRules) > 3, 'Not enough grouprules present');
        // all values must be null
        foreach($GroupRules as $Name=>$Rule) {
            $this->assertNull($Rule, "Rule '$Name' is not null!");
        }
        // set single rule
        $Keys= array_keys($GroupRules);
        $Key= reset($Keys);
        $Self= $Group->SetGroupRule($Key, 'Ennio Morricone');
        // setter must return self
        $this->assertEqual($Group, $Self);
        // get single rule
        $A= $Group->GetGroupRules($Key);
        $this->assertEqual($A, 'Ennio Morricone');
        // set all rules at once
        $GroupRules= array('a'=>1, 'b'=>2, 'c'=>3);
        $Group->SetGroupRules($GroupRules);
        $B= $Group->GetGroupRules('b');
        $this->assertEqual($B, 2);
        $Fetched= array_intersect_key($Group->GetGroupRules(), $GroupRules);
        $this->assertEqual($GroupRules, $Fetched);
    }


    public function TestMergingGroupRules() {

        $ParentGroupRules= array(
            'String'          => 'a',
            'Numeric'         => 4,
            'Null-Null'       => null,
            'Str-Null'        => 'w',
            'Null-Str'        => null,
            'Boolean'         => false,
            'Append(str-str)' => 'k',
            'Append(null-str)'=> null,
            'Append(str-null)'=> 'u',
            'Append(str-"")'  => 's',
            'ParentOnly'      => 'Parent',
        );
        $GroupRules= array(
            'String'          => 'b',
            'Numeric'         => 5,
            'Null-Null'       => null,
            'Str-Null'        => null,
            'Null-Str'        => 'h',
            'Boolean'         => true,
            'Append(str-str)' => 'b',
            'Append(null-str)'=> 'f',
            'Append(str-null)'=> null,
            'Append(str-"")'  => '',
            'GroupOnly'       => 'a',
            'AppendGroupOnly' => 'a',
        );
        $Expect= array(
            'String'          => 'b',
            'Numeric'         => 5,
            'Null-Null'       => null,
            'Str-Null'        => 'w',  // because null will not override parent value
            'Null-Str'        => 'h',
            'Boolean'         => true,
            'Append(str-str)' => 'kb',
            'Append(null-str)'=> 'f',
            'Append(str-null)'=> 'u',
            'Append(str-"")'  => 's',
            'ParentOnly'      => 'Parent',
            'GroupOnly'       => 'a',
            'AppendGroupOnly' => 'a',
        );
        $Group= new RouteGroup;
        $Group->SetGroupRules($GroupRules);
        $Group->ConcatingRules= array('Append(str-str)','Append(null-str)','Append(str-null)','Append(str-"")','AppendGroupOnly');
        $Result= $Group->GetMergedGroupRules($ParentGroupRules);
        // ensure we dont get extra fields in result
        $this->assertEqual(count($Result), count($Expect), 'count not equal: '.count($Result).':'.count($Expect));
        // assert each manualy because exact order of rules does not matter
        foreach($Expect as $k=>$v) {
            $this->assertEqual($Result[$k], $Expect[$k],
                'key "'.$k.'": '.var_export($Result[$k], true).' not equal '. var_export($Expect[$k], true));
        }
    }


    public function TestRouteManagement() {

        $Group= new RouteGroup;
        // add route from structure
        $Group->AddRoute(array(
            'Name'=> 'Test1',
            'Path'=> 'Path1',
            'Handle'=> 'Controller1',
        ));
        // add route object
        $Route= $Group->BuildRoute(array(
            'Name'=> 'Test2',
            'Path'=> 'Path2',
            'Handle'=> 'Controller2',
        ));
        $Group->AddRoute($Route);
        // add multiple
        $Group->AddRoutes(array(
            array(
                'Name'=> 'TestM1',
                'Path'=> 'PathM1',
                'Handle'=> 'ControllerM1',
            ),
            array(
                'Name'=> 'TestM2',
                'Path'=> 'PathM2',
                'Handle'=> 'ControllerM2',
            ),
        ));
        // add group
        $SubGroup= $Group->AddGroup(array('Auth'=>'Admin'));
        $SubGroup->AddRoute(array(
            'Name'=> 'TestSG1',
            'Path'=> 'PathSG1',
            'Handle'=> 'ControllerSG1',
        ));
        // fetch list
        $Routes= $Group->GetAllKeys();
        $this->assertEqual(count($Routes), 5);
        // get by name
        $R1= $Group->GetRouteByName('TestM2');
        $this->assertEqual($R1->Handle, 'ControllerM2');
        $R1= $Group->GetRouteByName('TestSG1');
        $this->assertEqual($R1->Handle, 'ControllerSG1');
    }


    public function TestImportRouteTree() {

        $Routes= array(
            array('Name'=> 'TestM1',  'Path'=> '/PathM1',  'Handle'=> 'ControllerM1'),
            array('Name'=> 'TestM2',  'Path'=> '/PathM2',  'Handle'=> 'ControllerM2'),
            array(  // start "blog" group
                'GroupRules'=> array(
                    'PathPrefix'=> '/blog',
                ),
                'GroupRoutes'=> array(
                    array('Name'=> 'Blog.Contact',  'Path'=> '/contact',  'Handle'=> 'ControllerBC'),
                    array('Name'=> 'Blog.Search',   'Path'=> '/search',   'Handle'=> 'ControllerBS'),
                ),
            ),
            array(  // start "shop" group
                'GroupRules'=> array(
                    'PathPrefix'=> '/shop',
                ),
                'GroupRoutes'=> array(
                    array(   // start "cart" sub-group
                        'GroupRules'=> array(
                            'PathPrefix'=> '/cart',
                        ),
                        'GroupRoutes'=> array(
                            array('Name'=> 'Shop.Cart.Browser',   'Path'=> '/',          'Handle'=> 'ControllerSCB'),
                            array('Name'=> 'Shop.Cart.Checkout',  'Path'=> '/checkout',  'Handle'=> 'ControllerSCC'),
                        ),
                    ),
                    array('Name'=> 'Shop.Product',   'Path'=> '/product/{id}',  'Handle'=> 'ControllerSP'),
                    array('Name'=> 'Shop.Category',  'Path'=> '/cat/{id}',      'Handle'=> 'ControllerSC'),
                ),
            ),
        );

        $Group= new RouteGroup;
        $Group->AddRoutes($Routes);
        // grab route from 1st level
        $R1= $Group->GetRouteByName('TestM2');
        $this->assertEqual($R1->Handle, 'ControllerM2');
        // grab route from 2nd level
        $R2= $Group->GetRouteByName('Blog.Search');
        $this->assertEqual($R2->Handle, 'ControllerBS');
        // grab route from 3rd level
        $R3= $Group->GetRouteByName('Shop.Cart.Browser');
        $this->assertEqual($R3->Handle, 'ControllerSCB');
        // confirm that 3rd route is in its own group
        $PP= $R3->Owner->Owner->GetGroupRules('PathPrefix');
        $this->assertEqual($PP, '/shop');
    }

}


?>