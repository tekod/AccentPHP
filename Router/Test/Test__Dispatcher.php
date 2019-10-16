<?php namespace Accent\Router\Test;

use Accent\Test\AccentTestCase;
use Accent\Router\Router;
use Accent\Router\Route;
use Accent\Router\RouteDispatcher;


class Test__RouterDispatcher extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Dispatcher test';

    // title of testing group
    const TEST_GROUP= 'Router';


    protected $DemoRoutesDefinition= array(
        array(
            'Name'=> 'R1',
            'Path'=> '/contact',
            'Handler'=> 'H1'),
        array(
            'Name'=> 'DemoStatic',
            'Path'=> '/static',
            'Handler'=> 'Accent\\Router\\Test\\Demo_StaticClass::DemoStaticMethod'),
        array(
            'Name'=> 'DemoDynamic',
            'Path'=> '/dynamic',
            'Handler'=> 'Accent\\Router\\Test\\Demo_DynamicClass->DemoDynMethod'),
        array(
            'Name'=>'Route403',
            'Handler'=> 'Accent\\Router\\Test\\Demo_ErrorPage::Error403',
            'Routable'=>false),
        array(
            'Name'=>'Route404',
            'Handler'=> 'Accent\\Router\\Test\\Demo_ErrorPage::Error404',
            'Routable'=>false),
        array(
            'Name'=>'Route501',
            'Handler'=> 'Accent\\Router\\Test\\Demo_ErrorPage::Error501',
            'Routable'=>false),
    );


    protected function BuildResultingRouteObject($Index) {

        $Route= new Route;
        $Route->Name= $this->DemoRoutesDefinition[$Index]['Name'];
        $Route->Handler= $this->DemoRoutesDefinition[$Index]['Handler'];
        return $Route;
    }


    public function TestDispatch() {

        $Router= new Router();
        $Router->LoadRoutes($this->DemoRoutesDefinition);

        $Dispatcher= new RouteDispatcher(array(
            'ErrorHandlers'=> array(
                'Error403'=> 'Route403',
                'Error404'=> 'Route404',
                'Error501'=> 'Route501',
            ),
            'Services'=> array(
                'Router'=> $Router,
            )
        ));

        // test static class
        $Route= $this->BuildResultingRouteObject(1);
        $Expected= 'SM';        // taken from target class method
        ob_start();
        $Dispatcher->Dispatch($Route);
        $this->assertEqual(ob_get_clean(), $Expected);

        // test dynamic class
        $Route= $this->BuildResultingRouteObject(2);
        $Expected= 'CDM';        // taken from target class constructor + method
        ob_start();
        $Dispatcher->Dispatch($Route);
        $this->assertEqual(ob_get_clean(), $Expected);

        // test error 404
        $Route= null;
        $Expected= '<h1>Not found</h1>';        // taken from Demo_ErrorPage
        ob_start();
        $Dispatcher->Dispatch($Route);
        $this->assertEqual(ob_get_clean(), $Expected);

        // test error 403
        $Route= false;
        $Expected= '<h1>Forbidden</h1>';        // taken from Demo_ErrorPage
        ob_start();
        $Dispatcher->Dispatch($Route);
        $this->assertEqual(ob_get_clean(), $Expected);

        // test error 501
        $Route= $this->BuildResultingRouteObject(0);
        $Expected= '<h1>Not implemented</h1>';        // taken from Demo_ErrorPage
        ob_start();
        $Dispatcher->Dispatch($Route);
        $this->assertEqual(ob_get_clean(), $Expected);
    }


}


?>