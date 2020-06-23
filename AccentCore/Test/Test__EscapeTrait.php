<?php namespace Accent\AccentCore\Test;

use Accent\Test\AccentTestCase;


/**
 * Testing AccentCore/EscapeTrait
 */

class Test__EscapeTrait extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Filter / EscapeTrait test';

    // title of testing group
    const TEST_GROUP= 'AccentCore';


    /**
     * Contructor.
     */
    public function __construct() {

        // parent
        parent::__construct();

        // dynamic create class with trait because autoloader at compilation time is not available
        eval("
            namespace ".__NAMESPACE__.";
            class TestClassWithEscapeTrait extends \Accent\AccentCore\Component {
                use \Accent\AccentCore\Filter\EscapeTrait;
            }");
    }


    /**
     * This trait extends Component descending classes, not stand alone classes
     * so in order to test it we must attach it to Component class and configure paths and services.
     *
     * @param array $Options
     * @return \Accent\AccentCore\Filter\EscapeTrait
     */
    protected function Build($Options=array()) {

        return new TestClassWithEscapeTrait($Options);
    }


    public function TestEscapeJsString() {

        $T= $this->Build();

        // normal cases
        $this->assertEqual($T->EscapeJsString('abc'),             'abc');
        $this->assertEqual($T->EscapeJsString('a=function(){};'), 'a=function(){};');

        // escape newline
        $this->assertEqual($T->EscapeJsString('ab'."\n".'c'),     'ab c');

        // escape double quote (enabled by default)
        $this->assertEqual($T->EscapeJsString('var a="x";'),      'var a=\\"x\\";');

        // single qouotes are not escaped by default
        $this->assertEqual($T->EscapeJsString("var a='x';"),      "var a='x';");

        // escape single qoute using second parameter
        $this->assertEqual($T->EscapeJsString("var a='x';", "'"), "var a=\\'x\\';");

        // escape specifying multiple custom chars
        $Source= 'var x="y"+\'z\\\';';              // note extra backslash after "z"
        $Expected= 'var x=\\"y\\"+\\\'z\\\\\\\';';
        $UnsafeChars= '\\\'"';                      // escaped 3 chars: (\), ('), (")
        $this->assertEqual($T->EscapeJsString($Source, $UnsafeChars), $Expected);
        // note that backshlash is already defined in method so it must NOT be processed twice
    }


    public function TestEscapeHtml() {

        $T= $this->Build();
        $Source= 'A \'quote\' is <b>bold</b>';
        $Expect= 'A &#039;quote&#039; is &lt;b&gt;bold&lt;/b&gt;';
        $this->assertEqual($T->EscapeHtml($Source), $Expect);
    }



    public function TestEscapeAttribute() {

        $T= $this->Build();

        // without second param - assuming attribute will be enclosed by double qoutes
        $this->assertEqual($T->EscapeAttribute('Some "x", \'y\' attrs'),        'Some &quot;x&quot;, \'y\' attrs');

        // set second param to false for attributes that will be enclosed by single qoute
        $this->assertEqual($T->EscapeAttribute('Some "x", \'y\' attrs', false), 'Some "x", &#x27;y&#x27; attrs');
    }


    public function TestEscapeCSS() {

        $T= $this->Build();
        // try to close [style] tag and execute some javascript
        $Source= 'body {font:90%}</style><scrpit>alert("xss");</script><style>';
        $Expect= 'body {font:90%}alert("xss");';
        $this->assertEqual($T->EscapeCSS($Source), $Expect);
    }


    public function TestEscapeWebPath() {

        $T= $this->Build();

        // simple case
        $this->assertEqual($T->EscapeWebPath('/author/1'),          '/author/1');

        // double quotes
        $this->assertEqual($T->EscapeWebPath('/author/mik"cd"'),    '/author/mik%22cd%22');

        // space char
        $this->assertEqual($T->EscapeWebPath('/about us'), '/about%20us');

        // preserve ":" in "http" section but escape ":" in path
        $this->assertEqual($T->EscapeWebPath('http://site.com/product:45-b'), 'http://site.com/product%3A45-b');
    }

}

