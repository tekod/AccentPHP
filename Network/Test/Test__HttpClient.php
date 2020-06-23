<?php namespace Accent\Network\Test;

/**
 * Testing Accent\Network\HttpClient
 *
 * Tag: [TestModelForward] // allowing test-forward calls
 */

use Accent\Test\AccentTestCase;
use Accent\Network\HttpClient;


class Test__HttpClient extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Network / HttpClient test';

    // title of testing group
    const TEST_GROUP= 'Network';


    /**
     * Instantiate HtppClient object.
     *
     * @param type $NewOptions
     * @return \Accent\Network\HttpClient
     */
    protected function BuildClient($NewOptions=array()) {

        $Options= $NewOptions + array(
            'TimeOut'=> 4,
            'ErrorFunc'=> array($this, 'ErrorFunc'),
            'Services'=> array(
                //'UTF'=> new \Accent\AccentCore\UTF\UTF,
            ),
        );
        return new HttpClient($Options);
    }


    public function TestFetchLocalhost() {

        $Client= $this->BuildClient();
        // fetch 'localhost', whatever exist there must return HTML content
        $Success= $Client->Request('GET', 'http://localhost');
        $this->assertTrue($Success);
        // validate body
        $Body= $Client->GetReceivedBody();
        $this->assertTrue(strpos($Body, '<body>') !== false);
        // validate HTTP status 200
        $Status= $Client->GetReceivedStatus();
        $this->assertEqual($Status, 200);
        // validate existance of few common HTTP headers
        $Headers= $Client->GetReceivedHeaders();
        $Headers= '|'.strtolower(implode('|',array_keys($Headers))).'|';
        $this->assertTrue(strpos($Headers, '|date|') !== false);
        $this->assertTrue(strpos($Headers, '|content-type|') !== false);
    }


    public function TestFetchMalformedURLs() {

        $Client= $this->BuildClient();
        // reqest invalid host
        $this->assertFalse($Client->GET('http://localst'));
        $Error= $Client->GetError();
        $this->assertTrue(is_string($Error) && strpos($Error, '(0)') !== false); // errno=0
        // request invalid protocol$Client->GET('dg3r7t://po.qw.vc//gd')
        $this->assertFalse($Client->GET('dg3r7t://po.qw.vc//gd'));
        $Error= $Client->GetError();
        $this->assertTrue(is_string($Error));
        // request invalid path
        $this->assertFalse($Client->GET('http://localhost:gd:as'));
        $Error= $Client->GetError();
        $this->assertTrue(is_string($Error));
    }


    public function TestFetchWithTestForwarding() {

        $Client= $this->BuildClient();
        // prepare URL for forwarder
        $URL= $this->BuildTestURL(array(
            'Act'=> 'Forward',
            'Target'=> 'Network.Test.Test__HttpClient::RetA',
        ));
        $Client->Request('GET', $URL);
        $Content= $Client->GetReceivedBody();
        $this->assertEqual($Content, '-A-');
    }


    public function ForwardTest_RetA() {

        echo '-A-';
    }


    public function TestHttpMethods() {

        $Client= $this->BuildClient();
        // prepare URL for forwarder
        $URL= $this->BuildTestURL(array(
            'Act'=> 'Forward',
            'Target'=> 'Network.Test.Test__HttpClient::RetMethod',
        ));
        $Client->Request('GET', $URL);
        $this->assertEqual($Client->GetReceivedBody(), '(GET)');
        $Client->Request('POST', $URL);
        $this->assertEqual($Client->GetReceivedBody(), '(POST)');
        $Client->GET($URL);
        $this->assertEqual($Client->GetReceivedBody(), '(GET)');
        $Client->POST($URL);
        $this->assertEqual($Client->GetReceivedBody(), '(POST)');
        $Client->PUT($URL);
        $this->assertEqual($Client->GetReceivedBody(), '(PUT)');
        $Client->DELETE($URL);
        $this->assertEqual($Client->GetReceivedBody(), '(DELETE)');
        $Client->PATCH($URL);
        $this->assertEqual($Client->GetReceivedBody(), '(PATCH)');
        $Client->OPTIONS($URL);
        $this->assertEqual($Client->GetReceivedBody(), '(OPTIONS)');
        $Client->HEAD($URL);
        $this->assertEqual($Client->GetReceivedBody(), '');   // HEAD should not return anything, only headers
    }


    public function ForwardTest_RetMethod() {

        echo '('.$_SERVER['REQUEST_METHOD'].')';
    }


    public function TestFetchingIntoFile() {

        $Client= $this->BuildClient();
        $URL= $this->BuildTestURL(array(
            'Act'=> 'Forward',
            'Target'=> 'Network.Test.Test__HttpClient::RetBigData',
        ));
        $File= __DIR__.'/tmp/response.###';
        @unlink($File);
        $Success= $Client->GET($URL, array(
            'ResponseToFile'=>$File,
        ));
        $this->assertTrue($Success);
        $Dump= file_get_contents($File);
        $this->assertEqual(strlen($Dump), 10000);
        $this->assertTrue(strpos($Dump, '=^=') !== false);
    }


    public function ForwardTest_RetBigData() {

        echo str_repeat('_.-=^=-._ ', 1000);  // length = 10.000 bytes
    }


    public function TestSendingPostData() {

        $Client= $this->BuildClient();
        $URL= $this->BuildTestURL(array(
            'Act'=> 'Forward',
            'Target'=> 'Network.Test.Test__HttpClient::RetPostData',
        ));
        $Success= $Client->POST($URL, array(
            'DataPOST'=>array('a'=>1,'b'=>'II','c45'=>'"don\'t"'),
        ));
        $this->assertTrue($Success);
        $Result= $Client->GetReceivedBody();
        $this->assertEqual($Result, 'a,b,c45:1,II,"don\'t"');
    }


    public function ForwardTest_RetPostData() {

        // using real $_POST superglobal because real transmition was occured
        echo implode(',',array_keys($_POST)).':'.implode(',',$_POST);
    }


    public function TestCookies() {

        $Client= $this->BuildClient();
        $URL= $this->BuildTestURL(array(
            'Act'=> 'Forward',
            'Target'=> 'Network.Test.Test__HttpClient::RetCookies',
        ));
        // first step: receive two cookies
        $Success= $Client->GET("$URL&Step=1", array(
            'AcceptCookies'=> true,
        ));
        $this->assertTrue($Success);
        $this->assertEqual($Client->GetReceivedBody(), '(OK)');
        $Cookies= $Client->GetCookieCollection();
        $Cookies->Remove('AccentTestPass'); // remove auth cookie if transfered
        $this->assertEqual($Cookies->Count(), 2);
        $this->assertEqual($Cookies->GetAllKeys(), array('AccentHttpClientTest1','AccentHttpClientTest2'));

        // step 2: send new request, this time these two cookies will be part of request
        $Success= $Client->GET("$URL&Step=2", array(
            'AcceptCookies'=> true,
        ));
        $this->assertTrue($Success);
        $this->assertEqual($Client->GetReceivedBody(), '(OK)');
        $Cookies->Remove('AccentTestPass'); // remove auth cookie if transfered
        $this->assertEqual($Cookies->Count(), 1);  // receiver sent instruction to delete one cookie
        $this->assertEqual($Cookies->Get('AccentHttpClientTest2'), 'Boeing');

        // step 3: send new request, receiver must confirm that only one cookie left
        $Success= $Client->GET("$URL&Step=3", array(
            'AcceptCookies'=> true,
        ));
        $this->assertTrue($Success);
        $this->assertEqual($Client->GetReceivedBody(), '(OK)');
    }


    public function ForwardTest_RetCookies() {

        // this code has direct access to superglobals in order to stay isolated from other factors
        $Step= isset($_GET['Step']) ? $_GET['Step'] : '';
        if ($Step === '1') {
            // in first step add two cookies
            setcookie('AccentHttpClientTest1', '"Micro"');
            setcookie('AccentHttpClientTest2', 'Airbus');
            echo '(OK)';
            return;
        }
        if ($Step === '2') {
            // in second step validate are cookies present, delete first and overwrite second
            $Response= isset($_COOKIE['AccentHttpClientTest1']) && $_COOKIE['AccentHttpClientTest1'] === '"Micro"'
                    && isset($_COOKIE['AccentHttpClientTest2']) && $_COOKIE['AccentHttpClientTest2'] === 'Airbus'
                ? '(OK)'
                : '(Not)';
            setcookie('AccentHttpClientTest1', '', 1);
            setcookie('AccentHttpClientTest2', 'Boeing');
            echo $Response;
            return;
        }
        if ($Step === '3') {
            // in third step validate there is only one cookie
            echo !isset($_COOKIE['AccentHttpClientTest1'])
               && isset($_COOKIE['AccentHttpClientTest2']) && $_COOKIE['AccentHttpClientTest2'] === 'Boeing'
                ? '(OK)'
                : '(Not)';
            return;
        }
        echo 'Unknown step "'.var_export($Step,true).'".';
    }


    public function TestRedirections() {

        $Client= $this->BuildClient();
        $URL= $this->BuildTestURL(array(
            'Act'=> 'Forward',
            'Target'=> 'Network.Test.Test__HttpClient::RetRedirections',
            'Step'=> '1',
        ));
        $Success= $Client->GET($URL);
        $this->assertTrue($Success);
        $this->assertEqual($Client->GetReceivedBody(), '{Ok}');
        $this->assertEqual($Client->GetReceivedStatus(), 201);
    }


    public function ForwardTest_RetRedirections() {

        // this code has direct access to superglobals in order to stay isolated from other factors
        $Step= isset($_GET['Step']) ? $_GET['Step'] : '';
        if ($Step === '1') { // forward to second URL
            $URL= $this->BuildTestURL(array(
                'Act'=> 'Forward',
                'Target'=> 'Network.Test.Test__HttpClient::RetRedirections',
                'Step'=> '2',
            ));
            header("Location: $URL", true, 301);
            die();
        }
        if ($Step === '2') {    // forward to third URL
            $URL= $this->BuildTestURL(array(
                'Act'=> 'Forward',
                'Target'=> 'Network.Test.Test__HttpClient::RetRedirections',
                'Step'=> '3',
            ));
            header("Location: $URL", true, 302);
            die();
        }
        if ($Step === '3') {    // success, return status "201"
            header($_SERVER["SERVER_PROTOCOL"]." 201 Created", true, 201);
            echo '{Ok}';
            return;
        }
        echo 'Unknown step "'.var_export($Step,true).'".';
    }


    public function TestUpload() {

        $Client= $this->BuildClient();
        $URL= $this->BuildTestURL(array(
            'Act'=> 'Forward',
            'Target'=> 'Network.Test.Test__HttpClient::RetUpload',
        ));
        // upload file 'response.###' generated in previous tests
        $Success= $Client->POST($URL, array(
            'DataFiles'=>array(
                'UpFile1'=> __DIR__.'/tmp/response.###',
            ),
        ));
        $this->assertTrue($Success);
        $Result= $Client->GetReceivedBody();
        $this->assertEqual($Result, '(Ok)');

        // clear playground
        (new \Accent\AccentCore\File\File)->DirectoryClear( __DIR__.'/tmp');
    }



    public function ForwardTest_RetUpload() {

        // validate $_FILES
        if (!isset($_FILES['UpFile1'])) {echo 'File entry not found.'; return;}
        if ($_FILES['UpFile1']['tmp_name'] === 'none') {echo '"tmp_name" === "none".'; return;}
        if ($_FILES['UpFile1']['tmp_name'] === '') {echo '"tmp_name" === "".'; return;}
        if ($_FILES['UpFile1']['size'] === 0) {echo 'File size === 0.'; return;}
        // validate content of file
        if (!is_uploaded_file($_FILES['UpFile1']['tmp_name'])) {echo 'Error: is_uploaded_file().'; return;}
        $Path2= __DIR__.'/tmp/response.##2';
        if (!move_uploaded_file($_FILES['UpFile1']['tmp_name'], $Path2)) {echo 'Error: move_uploaded_file().'; return;}
        $Buffer= file_get_contents($Path2);
        if (strlen($Buffer) <> 10000) {echo 'Error: received '.strlen($Buffer).' bytes.'; return;}
        if ($Buffer <> str_repeat('_.-=^=-._ ', 1000)) {echo 'Error: unexpected content of file.'; return;}
        // success
        echo '(Ok)';
    }

}

?>