<?php namespace Accent\Router\Test;

use Accent\Test\AccentTestCase;
use Accent\AccentCore\RequestContext;
use Accent\Router\Router;
use Accent\Router\RouteGroup;
use Accent\Router\Rule\AbstractRule as Rule;


class Test__RouterRules extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Rules test';

    // title of testing group
    const TEST_GROUP= 'Router';


    protected function BuildRule($Name, $NewOptions=array()) {

        $NewOptions += array(
            'Services'=> array(
                //'Event'=> new \Accent\AccentCore\Event\Event,
                'Validator'=> new \Accent\AccentCore\Filter\Validator,
            ),
        );
        $Class= 'Accent\\Router\\Rule\\'.$Name;
        return new $Class($NewOptions);
    }


    protected function SetRuleContext($Rule, $Path, $Other=array()) {

        $Context= new RequestContext();
        $Context->FromArray($Other);
        $Context->SERVER['REQUEST_URI']= $Path;

        $Rule->SetContext($Context);
    }


    public function TestPath() {

        $Rule= $this->BuildRule('Path');

        // test simple path matching
        $Route= $Rule->Compile( array(
            'Path'=> '/about',
        ), array());

        // testing with maching path
        // must return object with route
        $this->SetRuleContext($Rule, '/about');
        $this->assertTrue(is_object($Rule->Check($Route)));

        // testing wrong path
        // must return false in order to terminate checkings loop
        $this->SetRuleContext($Rule, '/some/dummy/path');
        $this->assertEqual($Rule->Check($Route), Rule::RESULT_SKIP_ROUTE);

        // find route with placeholder
        $Route= $Rule->Compile( array(
            'Path'=> '/shop/product/{product}',
        ), array());
        $this->SetRuleContext($Rule, '/shop/product/14');
        $Result= $Rule->Check($Route);
        $this->assertTrue(is_object($Result));
        $this->assertEqual($Result->MatchedVars, array('product'=>'14'));

        // find route with 2 placeholders
        $Route= $Rule->Compile( array(
            'Path'=> '/shop/product/{product}/{photo}',
        ), array());
        $this->SetRuleContext($Rule, '/shop/product/11/2');
        $Result= $Rule->Check($Route);
        $this->assertTrue(is_object($Result));
        $this->assertEqual($Result->MatchedVars, array('product'=>'11','photo'=>'2'));

        // route with 2 placeholders must not match path with only one part
        // must return false in order to terminate checkings loop
        $Route= $Rule->Compile( array(
            'Path'=> '/shop/product/{product}/{photo}',
        ), array());
        $this->SetRuleContext($Rule, '/shop/product/11');
        $this->assertEqual($Rule->Check($Route), Rule::RESULT_SKIP_ROUTE);

        // test optional parts
        $Route= $Rule->Compile( array(
            'Path'=> '/shop/product/{product}[/{photo}]',
        ), array());
        $this->SetRuleContext($Rule, '/shop/product/72/3');  // including last part
        $Result= $Rule->Check($Route);
        $this->assertTrue(is_object($Result));
        $this->assertEqual($Result->MatchedVars, array('product'=>'72','photo'=>'3'));
        $this->SetRuleContext($Rule, '/shop/product/56');   // last part omitted
        $Result= $Rule->Check($Route);
        $this->assertTrue(is_object($Result));
        $this->assertEqual($Result->MatchedVars, array('product'=>'56','photo'=>''));

        // test nested optional parts
        $Route= $Rule->Compile( array(
            'Path'=> '/blog/{year}[/{month}[/{day}]]',
        ), array());
        $this->SetRuleContext($Rule, '/blog');              // must be false because {year} is mandatory
        $this->assertEqual($Rule->Check($Route), Rule::RESULT_SKIP_ROUTE);
        $this->SetRuleContext($Rule, '/blog/2014');         // without any optional part
        $Result= $Rule->Check($Route);
        $this->assertEqual($Result->MatchedVars, array('year'=>'2014','month'=>'','day'=>''));
        $this->SetRuleContext($Rule, '/blog/2014/05');      // without one optional part
        $Result= $Rule->Check($Route);
        $this->assertEqual($Result->MatchedVars, array('year'=>'2014','month'=>'05','day'=>''));
        $this->SetRuleContext($Rule, '/blog/2014/05/24');   // with both optional parts
        $Result= $Rule->Check($Route);
        $this->assertEqual($Result->MatchedVars, array('year'=>'2014','month'=>'05','day'=>'24'));

        // test wildcard option
        $Route= $Rule->Compile( array(
            'Path'=> '/shop/product',
            'Wildcard'=> true,
        ), array());
        $this->SetRuleContext($Rule, '/shop/product');                  // same as path
        $this->assertEqual(is_object($Rule->Check($Route)), true);
        $this->SetRuleContext($Rule, '/shop/product/piano');            // with additional part
        $this->assertEqual(is_object($Rule->Check($Route)), true);
        $this->SetRuleContext($Rule, '/shop/product/piano/mini');       // with two additional parts
        $this->assertEqual(is_object($Rule->Check($Route)), true);
        $this->SetRuleContext($Rule, '/shop/products');                 // must fail, not same path
        $this->assertEqual($Rule->Check($Route), Rule::RESULT_SKIP_ROUTE);
        $this->SetRuleContext($Rule, '/shop/produc');                   // must fail, not same path
        $this->assertEqual($Rule->Check($Route), Rule::RESULT_SKIP_ROUTE);

        // test wildcard option on path with params (internaly it uses another execution logic)
        $Route= $Rule->Compile( array(
            'Path'=> '/shop/product/{product}',
            'Wildcard'=> true,
        ), array());
        $this->SetRuleContext($Rule, '/shop/product');                  // must fail, missing param
        $this->assertEqual($Rule->Check($Route), Rule::RESULT_SKIP_ROUTE);
        $this->SetRuleContext($Rule, '/shop/product/piano');            // same as path
        $this->assertEqual(is_object($Rule->Check($Route)), true);
        $this->SetRuleContext($Rule, '/shop/product/piano/R14/Black');  // with additional parts
        $this->assertEqual(is_object($Rule->Check($Route)), true);
        $this->assertEqual($Rule->Check($Route)->MatchedVars['Wildcard'], '/R14/Black');

        // test validation
        $Route= $Rule->Compile( array(
            'Path'=> '/blog/{year}[/{month}[/{day}]]',
            'Validate'=> array(
                'year'=> 'Integer|InRange:2010..2099',
                'month'=> 'Integer|InRange:1..12',
                'day'=> 'Integer|InRange:1..31',
            ),
        ), array());
        $this->SetRuleContext($Rule, '/blog/april');        // should fail, year is string
        $this->assertEqual($Rule->Check($Route), Rule::RESULT_SKIP_ROUTE);
        $this->SetRuleContext($Rule, '/blog/2000');         // should fail, out of range
        $this->assertEqual($Rule->Check($Route), Rule::RESULT_SKIP_ROUTE);
        $this->SetRuleContext($Rule, '/blog/2013');         // should success
        $this->assertEqual(is_object($Rule->Check($Route)), true);
        $this->SetRuleContext($Rule, '/blog/2013/xy');      // should fail, month is string
        $this->assertEqual($Rule->Check($Route), Rule::RESULT_SKIP_ROUTE);
        $this->SetRuleContext($Rule, '/blog/2013/17');      // should fail, out of range
        $this->assertEqual($Rule->Check($Route), Rule::RESULT_SKIP_ROUTE);
        $this->SetRuleContext($Rule, '/blog/2013/01');      // should success
        $this->assertTrue(is_object($Rule->Check($Route)));
    }


    public function TestRegEx() {

        $Rule= $this->BuildRule('RegEx');

        // test simple regex matching
        $Route= $Rule->Compile( array(
            'RegEx'=> '/about',
        ), array());

        // testing with maching path
        // must return object with route
        $this->SetRuleContext($Rule, '/about');
        $this->assertTrue(is_object($Rule->Check($Route)));

        // testing wrong path
        // must return false in order to terminate checkings loop
        $this->SetRuleContext($Rule, '/some/dummy/path');
        $this->assertEqual($Rule->Check($Route), Rule::RESULT_SKIP_ROUTE);

        // test complex pattern
        $Route= $Rule->Compile( array(
            'RegEx'=> '/shop/product/(\\d{1,9})/(\\d{1,9})',
        ), array());
        $this->SetRuleContext($Rule, '/shop/product/214/3');
        $this->assertTrue(is_object($Rule->Check($Route)));
        $this->SetRuleContext($Rule, '/shop/product/photo/2');
        $this->assertEqual($Rule->Check($Route), Rule::RESULT_SKIP_ROUTE);

        // test named params
        $Route= $Rule->Compile( array(
            'RegEx'=> '/author/(?P<username>[^/]+?)/icon',
        ), array());
        $this->SetRuleContext($Rule, '/author/nikola/icon');
        $Result= $Rule->Check($Route);
        $this->assertTrue(is_object($Result));
        $this->assertTrue($Result->MatchedVars['username'], 'nikola');
    }


    public function TestEvent() {

        $EventService= new \Accent\AccentCore\Event\EventService();
        $Rule= $this->BuildRule('Event', array(
            'Services'=> array(
                'Event'=> $EventService,
            )));
        $Context= (new RequestContext())->FromArray(array('SERVER'=>array()));
        $Rule->SetContext($Context);

        $Route= $Rule->Compile( array(
            'Event'=> 'TestingEventRule',
        ), array());

        // set event listener that will return null on every odd execution
        $EventService->AttachListener('TestingEventRule', function($Ev){
            static $Count= 0;
            $Count++;
            if ($Count % 2 === 0) {
                $Ev->SetRouteHandler('H7');
            }
        });
        // first execution, no listeners should claim route
        $Result= $Rule->Check($Route);
        $this->assertEqual($Result, Rule::RESULT_SKIP_ROUTE);

        // second execution, one of listeners should return handler string
        $Result= $Rule->Check($Route);
        $this->assertTrue($Result->Handler, 'H7');

    }


    public function TestMethod() {

        $Rule= $this->BuildRule('Method');

        $Route= $Rule->Compile( array(
            'Path'=> '/about',      //  must contain any primary rule
            'Method'=> 'GET',
        ), array());

        // positive matching path and method
        $this->SetRuleContext($Rule, '/about', array('SERVER'=>array('REQUEST_METHOD'=>'GET')));
        $Result= $Rule->Check($Route);
        $this->assertEqual($Result, Rule::RESULT_CONTINUE);

        // wrong method
        $this->SetRuleContext($Rule, '/about', array('SERVER'=>array('REQUEST_METHOD'=>'POST')));
        $Result= $Rule->Check($Route);
        $this->assertEqual($Result, Rule::RESULT_REJECT_ROUTE);

        // multiple methods and lowercase
        $Route= $Rule->Compile( array(
            'Path'=> '/about',      //  must contain any primary rule
            'Method'=> 'get|post',
        ), array());
        $this->SetRuleContext($Rule, '/about', array('SERVER'=>array('REQUEST_METHOD'=>'POST')));
        $Result= $Rule->Check($Route);
        $this->assertEqual($Result, Rule::RESULT_CONTINUE);
    }


    public function TestHost() {

        $Rule= $this->BuildRule('Host');

        $Route= $Rule->Compile( array(
            'Path'=> '/about',      //  must contain any primary rule
            'Host'=> 'www.site.com',
        ), array());

        // positive matching path and host
        $this->SetRuleContext($Rule, '/about', array('SERVER'=>array('HTTP_HOST'=>'www.site.com')));
        $Result= $Rule->Check($Route);
        $this->assertEqual($Result, Rule::RESULT_CONTINUE);

        // wrong host
        $this->SetRuleContext($Rule, '/about', array('SERVER'=>array('HTTP_HOST'=>'www.wrongsite.com')));
        $Result= $Rule->Check($Route);
        $this->assertEqual($Result, Rule::RESULT_REJECT_ROUTE);
    }


    public function TestSecure() {

        $Rule= $this->BuildRule('Secure');

        $Route= $Rule->Compile( array(
            'Path'=> '/about',      //  must contain any primary rule
            'Secure'=> true,
        ), array());

        // positive matching path and secure status
        $this->SetRuleContext($Rule, '/about', array('SERVER'=>array('HTTPS'=>'on')));
        $Result= $Rule->Check($Route);
        $this->assertEqual($Result, Rule::RESULT_CONTINUE);

        // wrong secure status
        $this->SetRuleContext($Rule, '/about');
        $Result= $Rule->Check($Route);
        $this->assertEqual($Result, Rule::RESULT_REJECT_ROUTE);
    }


    public function TestRoutable() {
        // testing "Routable" is part of Matcher testing
        // because it is not routing rule, it has no its own class
        // it is router feature
    }


    public function TestREST() {
        // testing "REST" is part of Matcher testing
        // because it is not routing rule, it has not its own class,
        // it is router feature
    }
}


?>