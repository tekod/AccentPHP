<?php namespace Accent\Router\Test;

use Accent\Test\AccentTestCase;
use Accent\Router\Router;
use Accent\AccentCore\RequestContext;


// These tests are more integration tests then unit tests
// because class Router is basicly wrapper around Matcher class with few preparation methods


class Test__Router extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Router service test';

    // title of testing group
    const TEST_GROUP= 'Router';


    // internal property
    protected $EventService;


    protected function BuildRouter($NewOptions=array()) {

        $this->EventService= new \Accent\AccentCore\Event\EventService();

        $NewOptions += array('Services'=> array());
        $NewOptions['Services']['Event']= $this->EventService;
        return new Router($NewOptions);
    }


    protected $DemoRoutesDefinition= array(
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

    public $EventExecutionCount= 0;


    public function TestGetAndSetRoutes() {

        // build router service and event service
        $Router= $this->BuildRouter();

        // manually trigger collecting mechanisam
        $Router->LoadRoutes($this->DemoRoutesDefinition);

        // fetch collection object, there must be 3 nodes in it
        $Routes= $Router->GetRoutes();
        $this->assertEqual($Routes->Count(), 3);

        // test setting via event,
        // rebuild router service and event service
        $Router= $this->BuildRouter();

        // attach event listener
        $this->EventService->AttachListener('Router.LoadRoutes', array($this, 'On_TestGetAndSetRoutes_LoadRoutesEventListener'));

        // manually trigger collecting mechanisam
        $Router->LoadRoutes();

        // fetch collection object, there must be 3 nodes in it
        $Routes= $Router->GetRoutes();
        $this->assertEqual($Routes->Count(), 3);
    }



    public function On_TestGetAndSetRoutes_LoadRoutesEventListener($Event) {

        $Collection= $Event->GetOption('Routes');
        $Collection->AddRoutes($this->DemoRoutesDefinition);
    }


    public function TestCaching() {

        // build cache service
        $Cache= new \Accent\Cache\Cache(array('Storage'=>'array'));

        // build router service
        $Router= $this->BuildRouter(array(
            'CacheKey'=> 'Router-CompiledRoutes',
            'Services'=> array(
                'Cache'=> $Cache
            ),
        ));

        // build context
        $Context= (new RequestContext)->FromArray(array('SERVER'=>array('REQUEST_URI'=>'/about')));

        // attach event listener
        $this->EventService->AttachListener('Router.LoadRoutes', array($this, 'On_TestCaching_LoadRoutesEventListener'));

        // reset counter
        $this->EventExecutionCount= 0;

        // call matcher, it will internally trigger collecting, compiling all and storing in cache
        $Result= $Router->MatchRequest($Context);

        // route must be found
        $this->assertEqual(is_object($Result), true);
        $this->assertEqual($Result->Name, 'R2');
        $this->assertEqual($this->EventExecutionCount, 1);

        // now rebuild router service and re-attach event listener
         $Router= $this->BuildRouter(array(
            'CacheKey'=> 'Router-CompiledRoutes',
            'Services'=> array(
                'Cache'=> $Cache
            ),
        ));
        $this->EventService->AttachListener('Router.LoadRoutes', array($this, 'On_TestCaching_LoadRoutesEventListener'));

        // call matcher again
        $Result= $Router->MatchRequest($Context);

        // route must be found but this time event counter must remain unchanged
        $this->assertEqual(is_object($Result), true);
        $this->assertEqual($Result->Name, 'R2');
        $this->assertEqual($this->EventExecutionCount, 1);
    }


    public function On_TestCaching_LoadRoutesEventListener($Event) {

        // increment counter
        $this->EventExecutionCount++;

        // insert routes
        $Collection= $Event->GetOption('Routes');
        $Collection->AddRoutes($this->DemoRoutesDefinition);
    }


    public function TestCustomRules() {

        // prepare router and context
        $Router= $this->BuildRouter();
        $Context= (new RequestContext())->FromArray(array('SERVER'=>array('REQUEST_URI'=>'/dashboard')));

        // listen to rule-collecting event
        $this->EventService->AttachListener('Router.CollectRules', array($this, 'On_TestCaching_CollectRulesEventListener'));

        // load routes
        $Definition= $this->DemoRoutesDefinition;
        $Definition[]= array(
            'Name'=> 'R-Custom',
            'Path'=> '/dashboard',
            'Auth'=> 'Admin',           // new rule, require visitor to be admin
            'Handler'=> 'Controller',
        );
        $Router->LoadRoutes($Definition);

        // simple solution to notify rule about current membership level
        $Context->Env['Membership']= 'Admin';

        // call matcher
        $Result= $Router->MatchRequest($Context);

        // route must be found
        $this->assertEqual(is_object($Result), true);
        $this->assertEqual($Result->Name, 'R-Custom');

        // revoke level and try again
        $Context->Env['Membership']= 'Editor';
        $Result= $Router->MatchRequest($Context);

        // route must not be found
        $this->assertEqual(is_object($Result), false);
    }


    public function On_TestCaching_CollectRulesEventListener($Event) {

        // add new rule to collection
        $Event->GetOption('Rules')->Set('Auth', __NAMESPACE__.'\\CustomRule');

        // note that actual name of class does not need to match rule name
    }


    public function TestCustomMatcher() {

        // build router service
        $Router= $this->BuildRouter(array(
            // CustomMatcher implements "Disable" property of route
            'MatcherClass' => 'Accent\\Router\\Test\\CustomMatcher',
        ));

        // prepare routes
        $Routes= $this->DemoRoutesDefinition;
        $Routes[1]['Disabled']= true;           // disabling "/about" path
        $Router->LoadRoutes($Routes);

        // matching "/about", route must not be found
        $Context= (new RequestContext())->FromArray(array('SERVER'=>array('REQUEST_URI'=>'/about')));
        $Result= $Router->MatchRequest($Context);
        $this->assertEqual(is_object($Result), false);

        // test that matching other routes works
        $Context= (new RequestContext())->FromArray(array('SERVER'=>array('REQUEST_URI'=>'/contact')));
        $Result= $Router->MatchRequest($Context);
        $this->assertEqual(is_object($Result), true);
    }

}


?>