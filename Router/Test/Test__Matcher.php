<?php namespace Accent\Router\Test;

use Accent\Test\AccentTestCase;
use Accent\AccentCore\RequestContext;
use Accent\AccentCore\Filter\Validator;
use Accent\Router\Matcher;
use Accent\Router\RouteGroup;


class Test__RouteMatcher extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Matcher test';

    // title of testing group
    const TEST_GROUP= 'Router';


    protected function BuildMatcher() {

        return new Matcher(array('Services'=>array('Validator'=> new Validator)));
    }

    protected function BuildRequestContext($Path, $Other=array()) {

        $Context= new RequestContext();
        $Context->FromArray($Other);
        $Context->SERVER['REQUEST_URI']= $Path;
        return $Context;
    }


    public function TestMatch() {

        $ArrayOfRouteDefinitions= array(
            array('Name'=> 'R1',     'Path'=> '/contact',        'Handler'=> 'H1'),
            array('Name'=> 'R2',     'Path'=> '/about',          'Handler'=> 'H2'),
            array(// start bloging group
                'GroupRules'=> array(
                    'PathPrefix'=> '/blog',
                ),
                'GroupRoutes'=> array(
                    array('Name'=> 'R3',     'Path'=> '/',          'Handler'=> 'H3'),
                    array('Name'=> 'R4',     'Path'=> '/{year}',    'Handler'=> 'H4',   'Validate'=> array('year'=>'Integer')),
                ),
            ),
        );
        // prepare route collection
        $Group= new RouteGroup;
        $Group->AddRoutes($ArrayOfRouteDefinitions);

        // prepare matcher and context objects
        $Matcher= $this->BuildMatcher();
        $Context= $this->BuildRequestContext('/about');

        // run matcher, it should find route with "H2" handler
        $Result= $Matcher->Match($Group, $Context, false);
        $this->assertEqual(is_object($Result), true);
        $this->assertEqual($Result->Handler, 'H2');

        // test context which must fail for all known routes
        $Context= $this->BuildRequestContext('/abc');
        $Result= $Matcher->Match($Group, $Context, false);
        $this->assertEqual($Result === null, true);

        // test new context, try to find deeper route
        $Context= $this->BuildRequestContext('/blog');
        $Result= $Matcher->Match($Group, $Context, false);
        $this->assertEqual(is_object($Result), true);
        $this->assertEqual($Result->Handler, 'H3');

        // test context with additional rules
        $Context= $this->BuildRequestContext('/blog/2014');
        $Result= $Matcher->Match($Group, $Context, false);
        $this->assertEqual(is_object($Result), true);
        $Context= $this->BuildRequestContext('/blog/author');
        $Result= $Matcher->Match($Group, $Context, false);
        $this->assertEqual($Result === null, true);   // it is null bcs no valid path was found

        // test $CompileAll param
        $Context= $this->BuildRequestContext('/blog/2014');
        $Result= $Matcher->Match($Group, $Context, true);
        $CompiledRoutes= $Matcher->GetCompiledRoutes();
        $this->assertEqual(is_array($CompiledRoutes), true);
        $this->assertEqual(count($CompiledRoutes), 4);   // R1,R2,R3,R4

        // test passing compiled routes to matcher
        $Context= $this->BuildRequestContext('/');
        $Result= $Matcher->Match($Group, $Context, true);
        $CompiledRoutes= $Matcher->GetCompiledRoutes(); // some dummy match, just to compile list
        $Matcher2= $this->BuildMatcher();               // create new matcher & context
        $Context2= $this->BuildRequestContext('/about');
        $Result= $Matcher2->Match($CompiledRoutes, $Context2, true);    // use compiled list
        $this->assertEqual(is_object($Result), true);
        $this->assertEqual($Result->Handler, 'H2');

        // test non-routable routes
        $R2= $Group->GetRouteByName('R2')->Routable= false;   // set non-routable for "/about" path
        $Context= $this->BuildRequestContext('/about');
        $Matcher= $this->BuildMatcher();                    // rebuilding mather to clear its internal cache
        $Result= $Matcher->Match($Group, $Context, false);
        $this->assertEqual($Result === null, true);

        // test REST option
        $R2= $Group->GetRouteByName('R2');
        $R2->Routable= null;   // clear value from previous test
        $R2->REST= true;
        $Context= $this->BuildRequestContext('/about', array('SERVER'=>array('REQUEST_METHOD'=>'POST')));
        $Matcher= $this->BuildMatcher();
        $Result= $Matcher->Match($Group, $Context, false);
        $this->assertEqual(is_object($Result), true);
        $this->assertEqual($Result->Handler, 'H2POST');
    }


    public function TestGetRejectedRoute() {

         $ArrayOfRouteDefinitions= array(
            array('Name'=> 'R1',     'Path'=> '/contact',        'Handler'=> 'H1',      'Method'=> 'GET'),
            array('Name'=> 'R2',     'Path'=> '/about',          'Handler'=> 'H2'),
        );
        // prepare route collection
        $Group= new RouteGroup;
        $Group->AddRoutes($ArrayOfRouteDefinitions);

        // prepare matcher and context objects
        $Matcher= $this->BuildMatcher();
        $Context= $this->BuildRequestContext('/contact', array('SERVER'=>array('REQUEST_METHOD'=>'POST')));

        // run matcher, it should return false
        $Result= $Matcher->Match($Group, $Context, false);
        $this->assertEqual($Result === false, true);

        // name of rejected route must be 'R1'
        $RejectedRoute= $Matcher->GetRejectedRoute();
        $this->assertEqual($RejectedRoute->Name, 'R1');

        // add 3rd route with same path and 'POST' method
        $Group->AddRoute(array('Name'=> 'R3',     'Path'=> '/contact',    'Handler'=> 'H3',    'Method'=> 'POST'));

        // run matcher, it should return 'R3' route and ignore that 'R1' was rejected
        $Result= $Matcher->Match($Group, $Context, false);
        $this->assertEqual(is_object($Result), true);
    }

}


?>