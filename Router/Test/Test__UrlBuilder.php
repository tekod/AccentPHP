<?php namespace Accent\Router\Test;

use Accent\Test\AccentTestCase;
use Accent\Router\UrlBuilder;
use Accent\AccentCore\RequestContext;


class Test__RouterUrlBuilder extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'UrlBuilder test';

    // title of testing group
    const TEST_GROUP= 'Router';


    protected $DemoRoutesDefinition= array(
        array('Name'=> 'About',                 'Path'=> '/about'),
        array(
            'GroupRules'=> array(
                'PathPrefix'=> '/shop',
            ),
            'GroupRoutes'=> array(
                array('Name'=> 'Cart', 'Path'=> '/cart'),
            ),
        ),
        array('Name'=>'RouteWithParams',        'Path'=> '/shop/product/{product}/{photo}'),
        array('Name'=>'RouteWithOptionals',     'Path'=> '/shop/product/[{product}[/{photo}]]'),
        array('Name'=>'RegExRoute',             'RegEx'=> '/shop/product/(\\d{1,9})/(\\d{1,9})'),
    );


    protected function BuildBuilder($ContextArray=array()) {

        $ContextArray += array('SERVER'=> array());
        $ContextArray['SERVER'] += array('SCRIPT_NAME'=>'/sub/app/index.php');  // initialy website is not in root of domain
        $Options= array(
            'Routes'=> $this->DemoRoutesDefinition,
            'RequestContext'=> (new RequestContext)->FromArray($ContextArray),
        );
        return new UrlBuilder($Options);
    }


    public function TestBuildingSimpleRoute() {

        $Builder= $this->BuildBuilder();
        $URL= $Builder->Build('About');
        $this->assertEqual($URL, '/sub/app/about');
    }


    public function TestBuildingRouteFromSubGroup() {

        $Builder= $this->BuildBuilder();
        $URL= $Builder->Build('Cart');
        $this->assertEqual($URL, '/sub/app/shop/cart');
    }


    public function TestBuildingRouteOnRootOfDomain() {

        $Builder= $this->BuildBuilder(array('SERVER'=>array('SCRIPT_NAME'=>'/index.php')));
        $URL= $Builder->Build('About');
        $this->assertEqual($URL, '/about');
    }


    public function TestBuildingMissingRoute() {

        $Builder= $this->BuildBuilder();
        $URL= $Builder->Build('XYZ');
        $this->assertEqual($URL === false, true);
    }


    public function TestBuildingRouteWithParams() {

        $Builder= $this->BuildBuilder();
        $URL= $Builder->Build('RouteWithParams', array('product'=>'412', 'photo'=>'3'));
        $this->assertEqual($URL, '/sub/app/shop/product/412/3');

        // assigning params to routes without params should not produce any error
        $Builder= $this->BuildBuilder();
        $URL= $Builder->Build('About', array('product'=>'412', 'photo'=>'3'));
        $this->assertEqual($URL, '/sub/app/about');

        // omitting params in routes with params should replace them with "$"
        $Builder= $this->BuildBuilder();
        $URL= $Builder->Build('RouteWithParams', array());
        $this->assertEqual($URL, '/sub/app/shop/product/$/$');
    }


    public function TestBuildingRouteWithOptionalParts() {

        $Builder= $this->BuildBuilder();
        // without both optionals
        $URL= $Builder->Build('RouteWithOptionals', array());
        $this->assertEqual($URL, '/sub/app/shop/product');

        // first optional specified
        $URL= $Builder->Build('RouteWithOptionals', array('product'=>'27'));
        $this->assertEqual($URL, '/sub/app/shop/product/27');

        // both optionals specified
        $URL= $Builder->Build('RouteWithOptionals', array('product'=>'31','photo'=>'4'));
        $this->assertEqual($URL, '/sub/app/shop/product/31/4');

        // if specify sub-optional without its parent replace it with "$"
        $URL= $Builder->Build('RouteWithOptionals', array('photo'=>'4'));
        $this->assertEqual($URL, '/sub/app/shop/product/$/4');
    }


    public function TestBuildingAbsoluteURL() {

        $Builder= $this->BuildBuilder(array('SERVER'=>array('HTTP_HOST'=>'www.demo.com')));
        $URL= $Builder->Build('About', array(), true);
        $this->assertEqual($URL, 'http://www.demo.com/sub/app/about');
    }


    public function TestUrlEncoding() {

        $Builder= $this->BuildBuilder();
        $URL= $Builder->Build('RouteWithParams', array('product'=>'Nike(All Star)/Red-47', 'photo'=>'1'));
        $this->assertEqual($URL, '/sub/app/shop/product/Nike(All%20Star)%2FRed-47/1');
    }


    public function TestBuildingRegexRoutes() {

        $Builder= $this->BuildBuilder();

        // replace params
        $URL= $Builder->Build('RegExRoute', array('472', '6'));
        $this->assertEqual($URL, '/sub/app/shop/product/472/6');

        // missing params must be replaced with "$"
        $URL= $Builder->Build('RegExRoute', array());
        $this->assertEqual($URL, '/sub/app/shop/product/$/$');
    }

}


?>