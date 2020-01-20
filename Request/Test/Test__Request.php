<?php namespace Accent\Request\Test;

/**
 * Testing Accent\Request\Request
 */

use Accent\Test\AccentTestCase;
use Accent\Request\Request;
use Accent\AccentCore\RequestContext;
use Accent\AccentCore\Event\Event;
use Accent\Test\PhpStream;


class Test__Request extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Request component test';

    // title of testing group
    const TEST_GROUP= 'Request';


    /**
     * Builder.
     * @return Accent\Request\Request
     */
    protected function Build($NewOptions=array(), $Context=array()) {

        $Options= array(
            'RequestContext'=> (new RequestContext)->FromArray($Context),
            'Services'=> array(
            ),
        );
        return new Request($NewOptions + $Options);
    }


    // TESTS:



    public function TestResolvingHostAndPort() {

        // both specified, host and port
        $R= $this->Build(array(), array(
           'SERVER'=> array('HTTP_HOST'=>'www.mysite.com', 'SERVER_PORT'=>8080),
        ));
        $this->assertEqual($R->GetHost(), 'www.mysite.com');
        $this->assertEqual($R->GetPort(), 8080);

        // include port in header
        $R= $this->Build(array(), array(
           'SERVER'=> array('HTTP_HOST'=>'localhost:443'),
        ));
        $this->assertEqual($R->GetHost(), 'localhost');
        $this->assertEqual($R->GetPort(), 443);

        // default port is 80
        $R= $this->Build(array(), array(
           'SERVER'=> array('HTTP_HOST'=>'www.mysite.com'),
        ));
        $this->assertEqual($R->GetPort(), 80);
    }


    public function TestUrlDetection() {

        $Tests= array(
            array('/about.php', 'http://www.mysite.com/about.php'),
            array('/about', 'http://www.mysite.com/about'),
            array('/subdir/about.php', 'http://www.mysite.com/subdir/about.php'),
            array('/', 'http://www.mysite.com'),
        );
        foreach($Tests as $Test) {
            $R= $this->Build(array(), array('SERVER'=> array(
                'REQUEST_URI'=> $Test[0],
                'HTTP_HOST'=> 'www.mysite.com',
            )));
            $Result= $R->GetUrl();
            $this->assertEqual($Result, $Test[1], "Diff: '$Result' <> '$Test[1]'");
        }
    }


    public function TestUrlComponents() {

        $Tests= array(
            array(
                array('REQUEST_URI'=>'/', 'HTTP_HOST'=>'www.mysite.com'),
                array('scheme'=>'http', 'host'=>'www.mysite.com', 'port'=>80, 'path'=>'/', 'query'=>'', 'fragment'=>''),
            ),
            array(
                array('REQUEST_URI'=>'/subdir/about.php?a=1&b=2#top', 'HTTP_HOST'=>'www.mysite.com'),
                array('scheme'=>'http', 'host'=>'www.mysite.com', 'port'=>80, 'path'=>'/subdir/about.php', 'query'=>'a=1&b=2', 'fragment'=>'top'),
            ),
            array(
                array('REQUEST_URI'=>'/about.php', 'HTTP_HOST'=>'1.1.1.1:443', 'HTTPS'=>'1'),
                array('scheme'=>'https', 'host'=>'1.1.1.1', 'port'=>443, 'path'=>'/about.php', 'query'=>'', 'fragment'=>''),
            ),
        );
        foreach($Tests as $Test) {
            $R= $this->Build(array(), array('SERVER'=> $Test[0]));
            $Result= $R->GetUrlComponents();
            foreach($Test[1] as $k=>$v) {
                $this->assertEqual($Result[$k], $v, "Diff: '$Result[$k]' <> '$v'");
            }
        }
        // examine components using getters
        $R= $this->Build(array(), array('SERVER'=>array('REQUEST_URI'=>'/subdir/about.php?a=1&b=2#top', 'HTTP_HOST'=>'localhost')));
        $this->assertEqual($R->GetScheme(), 'http');
        $this->assertEqual($R->GetHost(), 'localhost');
        $this->assertEqual($R->GetPort(), 80);
        $this->assertEqual($R->GetPath(), '/subdir/about.php');
        $this->assertEqual($R->GetQuery(), 'a=1&b=2');
        $this->assertEqual($R->GetQuery(true), array('a'=>1, 'b'=>2));
        $this->assertEqual($R->GetFragment(), 'top');
    }


    public function TestResolveHeaders() {

        $Headers= array('REQUEST_URI'=>'/',                 // non-header keys
            'HTTP_HOST'=>'localhost',                       // classic HTTP header
            'HTTP_ACCEPT_ENCODING'=>'gzip, deflate',        // header with underscore
            'CONTENT_TYPE'=>'application/json',             // one of "CONTENT_" headers
        );
        $R= $this->Build(array(), array('SERVER'=>$Headers));
        // test each
        $this->assertEqual($R->GetHeader('REQUEST_URI'), null);                 // must not be found
        $this->assertEqual($R->GetHeader('REQUEST_URI', 'DEFAULT'), 'DEFAULT'); // test getting default value
        $this->assertEqual($R->GetHeader('HTTP_HOST'), null);                   // must be without HTTP prefix
        $this->assertEqual($R->GetHeader('HOST'), 'localhost');
        $this->assertEqual($R->GetHeader('ACCEPT-ENCODING'), 'gzip, deflate');  // "_" converted to "-"
        $this->assertEqual($R->GetHeader('CONTENT-TYPE'), 'application/json');  // preserve starting with "CONTENT_"
    }


    public function TestGetIp() {

        // testing simply case
        $R= $this->Build(array(), array('SERVER'=> array(
            'REMOTE_ADDR'=> '1.2.3.4'
        )));
        $this->assertEqual($R->GetIP(), '1.2.3.4');
        // testing access via untrusted proxy
        $R= $this->Build(array(), array('SERVER'=> array(
            'REMOTE_ADDR'=> '2.2.2.2',
            'HTTP_X_FORWARDED_FOR'=> '10.10.10.10, 15.15.15.15, 17.17.17.17'
        )));
        $this->assertEqual($R->GetIP(), '2.2.2.2');
        // testing access via trusted proxy
        $R= $this->Build(array(
            'TrustedProxies'=> array('51.52.53.54'),
        ), array('SERVER'=> array(
            'REMOTE_ADDR'=> '51.52.53.54',
            'HTTP_X_FORWARDED_FOR'=> '10.10.10.10, 15.15.15.15, 17.17.17.17',
        )));
        $this->assertEqual($R->GetIP(), '10.10.10.10');
    }


    public function TestMethod() {

        // testing simply case
        $R= $this->Build(array(), array('SERVER'=> array(
            'REQUEST_METHOD'=> 'PUT',
        )));
        $this->assertEqual($R->GetMethod(), 'PUT');
        // testing AJAX
        $R= $this->Build(array(), array('SERVER'=> array(
            'REQUEST_METHOD'=> 'POST',
            'HTTP_X_REQUESTED_WITH'=> 'XMLHttpRequest',
        )));
        $this->assertEqual($R->GetMethod(), 'AJAX');
        // this time forbid AJAX as method
        $R= $this->Build(array(
            'AcceptAjaxAsMethod'=> false,
        ), array('SERVER'=> array(
            'REQUEST_METHOD'=> 'POST',
            'HTTP_X_REQUESTED_WITH'=> 'XMLHttpRequest',
        )));
        $this->assertEqual($R->GetMethod(), 'POST');
        // test 'X-HTTP-METHOD-OVERRIDE'
        $R= $this->Build(array(), array('SERVER'=> array(
            'REQUEST_METHOD'=> 'POST',
            'HTTP_X_HTTP_METHOD_OVERRIDE'=> 'dElEtE',
        )));
        $this->assertEqual($R->GetMethod(), 'DELETE');      // uppercased
        $R= $this->Build(array(), array('SERVER'=> array(
            'REQUEST_METHOD'=> 'GET',
            'HTTP_X_HTTP_METHOD_OVERRIDE'=> 'DELETE',
        )));
        $this->assertEqual($R->GetMethod(), 'GET');         // must be POST to override
        // test 'OverrideMethodField'
        $R= $this->Build(
            array('OverrideMethodField'=> 'm'),
            array(
                'SERVER'=> array('REQUEST_METHOD'=> 'POST'),
                'POST'=> array('m'=>'pUt')
        ));
        $this->assertEqual($R->GetMethod(), 'PUT');         // uppercased
        $R= $this->Build(
            array('OverrideMethodField'=> 'm'),
            array(
                'SERVER'=> array('REQUEST_METHOD'=> 'GET'),  // must be POST to override
                'GET'=> array('m'=>'PUT')
        ));
        $this->assertEqual($R->GetMethod(), 'GET');
    }


    public function TestGetSelf() {

        $Tests= array(
            // REQUEST_URI, $WithQuery, Result
            array('/about', false, '/about'),
            array('/subdir/about.php?a=2', false, '/subdir/about.php'),
            array('', false, '/'),
            array('/', false, '/'),
            array('/?a=1', false, '/'),
            array('/?a=1#fragment', false, '/'),
            array('/subdir', true, '/subdir'),
            array('/subdir/about?a=2', true, '/subdir/about?a=2'),
            array('/subdir/about/?a=3', true, '/subdir/about/?a=3'),
            array('/subdir?a=4#fragment', true, '/subdir?a=4'),
            array('/', true, '/'),
            array('/?a=1', true, '/?a=1'),
            array('/?a=1#fragment', true, '/?a=1'),
        );
        foreach($Tests as $Test) {
            list($URI, $WithQuery, $Expected)= $Test;
            $R= $this->Build(array(), array('SERVER'=>array(
                'REQUEST_URI'=> $URI,
                'HTTP_HOST'=> 'www.mysite.com',
            )));
            $Self= $R->GetSelf($WithQuery);
            $this->assertEqual($Self, $Expected, "Case '$URI'-".($WithQuery?'true':'false')." : '$Self' <> '$Expected'");
        }
    }



    public function TestPathPrefix() {

       $R= $this->Build(array(), array('SERVER'=>array('REQUEST_URI'=> '/en/shop/hat/RedHat')));
       $R->SetPathPrefixSegments(1);
       $this->assertEqual($R->GetPath(), '/shop/hat/RedHat');
       $this->assertEqual($R->GetPath(false), '/en/shop/hat/RedHat');
       $R->SetPathPrefixSegments(2);
       $this->assertEqual($R->GetPath(), '/hat/RedHat');
       $R->SetPathPrefixSegments(0);
       $this->assertEqual($R->GetPath(false), '/en/shop/hat/RedHat');
    }


    public function TestCombinedSegments() {

       $R= $this->Build(array(), array('SERVER'=>array('REQUEST_URI'=> '/brand/Nike/product/AirMax500/OddMember')));
       $this->assertEqual($R->GetCombinedSegments(), array('brand'=>'Nike', 'product'=>'AirMax500', 'OddMember'=>null));
    }


    public function TestGetUserAgent() {

       $R= $this->Build(array(), array('SERVER'=>array('HTTP_USER_AGENT'=> 'Bot')));
       $this->assertEqual($R->GetUserAgent(), 'Bot');
    }


    public function TestBody() {

        // temporary redirect "file_get_contents('php://input')" to read from our class
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', 'Accent\Test\PhpStream');
        // content type: text/html
        PhpStream::$Body= '<html>';
        $R= $this->Build(array(), array('SERVER'=>array('CONTENT_TYPE'=> 'text/html; charset=UTF-8')));
        $this->assertEqual($R->GetBody(), false);           // text/html has not registered decoder
        $this->assertEqual($R->GetBody(false), '<html>');   // returned raw content
        // content type: JSON
        PhpStream::$Body= '{"a":1,"b":"xyz"}';
        $R= $this->Build(array(), array('SERVER'=>array('CONTENT_TYPE'=> 'application/json; charset=UTF-8')));
        $this->assertEqual($R->GetBody(), array('a'=>1, 'b'=>'xyz'));   // array
        $this->assertEqual($R->GetBody(false), '{"a":1,"b":"xyz"}');   // returned raw content
        // content type: application/x-www-form-urlencoded
        PhpStream::$Body= 'a=1&b=xyz';
        $R= $this->Build(array(), array('SERVER'=>array('CONTENT_TYPE'=> 'application/x-www-form-urlencoded; charset=UTF-8')));
        $this->assertEqual($R->GetBody(), array('a'=>1, 'b'=>'xyz'));   // array
        $this->assertEqual($R->GetBody(false), 'a=1&b=xyz');   // returned raw content
        // test custom decoder (register decoder in configration)
        PhpStream::$Body= 'A b C d E f G h';
        $R= $this->Build(array('BodyDecoders'=>array('MyType'=>array($this,'BodyDecoderMy'))), array('SERVER'=>array('CONTENT_TYPE'=> 'MyType; charset=UTF-8')));
        $this->assertEqual($R->GetBody(), 'ABCDEFGH');   // uppercased
        // test custom decoder (replace existing decoder using event listener)
        $EventService= new \Accent\AccentCore\Event\EventService();
        $EventService->AttachListener('Request.InitBodyDecoders', function($E){$E->Collection->Set('application/json', array($this,'BodyDecoderJson2'));});
        PhpStream::$Body= '{"a":1,"b":"xyz"}';
        $R= $this->Build(array('Services'=>array('Event'=>$EventService)), array('SERVER'=>array('CONTENT_TYPE'=> 'application/json; charset=UTF-8')));
        $this->assertEqual($R->GetBody(), array('A'=>1, 'B'=>'XYZ')); // uppercased items
        // restore stream handler
        stream_wrapper_restore('php');
    }


    public function BodyDecoderMy($Content) {return str_replace(' ', '', strtoupper($Content));}
    public function BodyDecoderJson2($Content) {return json_decode(strtoupper($Content), true);}


    public function TestDetectLanguage() {

        $R= $this->Build(array(), array('SERVER'=>array('HTTP_ACCEPT_LANGUAGE'=>'en-ca,en;q=0.8,en-us;q=0.6,de-de;q=0.4,de;q=0.2')));
        $this->assertEqual($R->DetectLanguage(array('en')),'en');   // main hit
        $this->assertEqual($R->DetectLanguage(array('xy')),'xy');   // result must be one of offered languages, so return "xy"
        $this->assertEqual($R->DetectLanguage(array('de')),'de');   // minor hit
        $this->assertEqual($R->DetectLanguage(array('de','en')),'en');  // return better quality if both exist
        $this->assertEqual($R->DetectLanguage(array('en','de')),'en');  // order does not matter
        $this->assertEqual($R->DetectLanguage(array('de','xy')),'de');  // only "de" exist
        $this->assertEqual($R->DetectLanguage(array('xy','de')),'de');  // order does not matter
    }


    public function TestParseAcceptHeader() {

        $R= $this->Build(array(),array('SERVER'=>array(
            'HTTP_ACCEPT'           => 'text/*, text/plain, */*, text/plain;format=flowed',
            'HTTP_ACCEPT_CHARSET'   => 'iso-8859-5, unicode-1-1;q=0.8',
            'HTTP_ACCEPT_ENCODING'  => 'gzip;q=1.0, identity; q=0.5, *;q=0',
            'HTTP_ACCEPT_LANGUAGE'  => 'da, en-gb;q=0.8, en;q=0.7',
        )));
        $this->assertEqual($R->ParseAcceptHeader('ACCEPT'), array('text/plain'=>1, 'text/*'=>1, 'text/plain;format=flowed'=>1, '*/*'=>1));
        $this->assertEqual($R->ParseAcceptHeader('ACCEPT-CHARSET'), array('iso-8859-5'=>1, 'unicode-1-1'=>0.8));
        $this->assertEqual($R->ParseAcceptHeader('ACCEPT-ENCODING'), array('gzip'=>1, 'identity'=>0.5, '*'=>0));
        $this->assertEqual($R->ParseAcceptHeader('ACCEPT-LANGUAGE'), array('da'=>1, 'en-gb'=>0.8, 'en'=>0.7));
    }


    public function TestGetETag() {

        $R= $this->Build(array(), array('SERVER'=>array('HTTP_IF_NONE_MATCH'=> 'abc')));
        $this->assertEqual($R->GetETags(), array('abc'));
        // CSV
        $R= $this->Build(array(), array('SERVER'=>array('HTTP_IF_NONE_MATCH'=> 'abc,xyz')));
        $this->assertEqual($R->GetETags(), array('abc', 'xyz'));
        // ignore spaces and preserve caps
        $R= $this->Build(array(), array('SERVER'=>array('HTTP_IF_NONE_MATCH'=> 'abc , QwE')));
        $this->assertEqual($R->GetETags(), array('abc', 'QwE'));
        // ignore quotes
        $R= $this->Build(array(), array('SERVER'=>array('HTTP_IF_NONE_MATCH'=> '"abc","efg"')));
        $this->assertEqual($R->GetETags(), array('abc', 'efg'));
        // empty string
        $R= $this->Build(array(), array('SERVER'=>array('HTTP_IF_NONE_MATCH'=> '')));
        $this->assertEqual($R->GetETags(), array());
    }


    public function TestGetCacheControl() {

        $R= $this->Build(array(), array('SERVER'=>array('HTTP_CACHE_CONTROL'=> 'no-cache')));
        $this->assertEqual($R->GetCacheControl(), array('no-cache'=>true));
        // with "="
        $R= $this->Build(array(), array('SERVER'=>array('HTTP_CACHE_CONTROL'=> 'max-age=0')));
        $this->assertEqual($R->GetCacheControl(), array('max-age'=>0));
        // multiple values
        $R= $this->Build(array(), array('SERVER'=>array('HTTP_CACHE_CONTROL'=> 'public, max-age=31536000')));
        $this->assertEqual($R->GetCacheControl(), array('public'=>true, 'max-age'=>31536000));
    }


    public function TestMobileDetection() {
        // Nokia browser
        $R= $this->Build(array(), array('SERVER'=>array('HTTP_X_NOKIA_GATEWAY_ID'=> '*BG/3.*/4')));
        $this->assertEqual($R->IsMobile(), true);
        // OperaMini
        $R= $this->Build(array(), array('SERVER'=>array('HTTP_USER_AGENT'=> 'Opera/9.80 (J2ME/MIDP; Opera Mini/5.1.21214/28.2725; U; ru) Presto/2.8.119 Version/11.10')));
        $this->assertEqual($R->IsMobile(), true);
        // regular Mozilla Firefox on PC
        $R= $this->Build(array(), array('SERVER'=>array('HTTP_USER_AGENT'=> 'Mozilla/5.0 (Windows NT x.y; Win64; x64; rv:10.0) Gecko/20100101 Firefox/10.0')));
        $this->assertEqual($R->IsMobile(), false);
        // regular Google Chrome on PC
        $R= $this->Build(array(), array('SERVER'=>array('HTTP_USER_AGENT'=> 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113 Safari/537.36')));
        $this->assertEqual($R->IsMobile(), false);
    }


    public function TestBotDetection() {
        // Google-bot
        $R= $this->Build(
            array('Services'=>array('Autoloader'=>$this->Autoloader)),
            array('SERVER'=>array('HTTP_USER_AGENT'=> 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')));
        $this->assertEqual($R->IsBot(), true);
        // Facebook-bot
        $R= $this->Build(
            array('Services'=>array('Autoloader'=>$this->Autoloader)),
            array('SERVER'=>array('HTTP_USER_AGENT'=> 'facebookexternalhit/1.1')));
        $this->assertEqual($R->IsBot(), true);
        // regular Mozilla Firefox on PC
        $R= $this->Build(
            array('Services'=>array('Autoloader'=>$this->Autoloader)),
            array('SERVER'=>array('HTTP_USER_AGENT'=> 'Mozilla/5.0 (Windows NT x.y; Win64; x64; rv:10.0) Gecko/20100101 Firefox/10.0')));
        $this->assertEqual($R->IsBot(), false);
        // regular Google Chrome on PC
        $R= $this->Build(
            array('Services'=>array('Autoloader'=>$this->Autoloader)),
            array('SERVER'=>array('HTTP_USER_AGENT'=> 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113 Safari/537.36')));
        $this->assertEqual($R->IsBot(), false);
    }


}


?>