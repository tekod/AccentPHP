<?php namespace Accent\Network;

use Accent\Test\AccentTestCase;


/**
 * Testing Accent\Network\HtmlParser
 */

class Test__HttpClient extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Network / HtmlParser test';

    // title of testing group
    const TEST_GROUP= 'Network';


    protected function Build($NewOptions=array()) {

        $Options= $NewOptions + array(
            'Services'=> array(
                'UTF'=> new \Accent\AccentCore\UTF\UTF,
            ),
        );
        return new HtmlParser($Options);
    }


    public function TestCreateDOM() {

        $Parser= $this->Build();
        // empty HTML cannot be converted into DOM structure
        $this->assertFalse($Parser->CreateDomFromHtml(false));
        $this->assertFalse($Parser->CreateDomFromHtml(''));
        // normal HTML must be successfully converted to DOM
        $HTML= '<html><head><title>Test page</title></head><body><h1>Heading</h1>Paragraph</body></html>';
        $this->assertTrue(is_object($Parser->CreateDomFromHtml($HTML)));
    }


    public function TestCreateXPath() {

        $Parser= $this->Build();
        // empty HTML cannot be converted into XPath structure
        $this->assertFalse($Parser->CreateXPathFromHtml(false));
        $this->assertFalse($Parser->CreateXPathFromHtml(''));
        // normal HTML must be successfully converted to XPath
        $HTML= '<html><head><title>Test page</title></head><body><h1 class="h">Heading</h1>Paragraph</body></html>';
        $XP= $Parser->CreateXPathFromHtml($HTML);
        // search for: class="h", that must be found in "h1" tag
        $Elem= $XP->query("//*[@class='h']");
        $this->assertEqual($Elem->length, 1);
        $this->assertEqual($Elem->item(0)->tagName, 'h1');
    }


    public function TestExtractDelimitedString() {

        $Parser= $this->Build();
        $HTML= '<tr><th>Name</th><td>Value</td></tr>';
        // get part between <td> and </td>
        $Case= $HTML;
        $Success= $Parser->ExtractDelimitedString($Case, '<td>', '</td>');
        $this->assertTrue($Success);
        $this->assertEqual($Case, 'Value');
        // if first delimiter not found return false and get unmodified source string
        $Case= $HTML;
        $Success= $Parser->ExtractDelimitedString($Case, '<t>', '</th>');
        $this->assertFalse($Success);
        $this->assertEqual($Case, $HTML);
        // if end delimiter not found return false and get unmodified source string
        $Case= $HTML;
        $Success= $Parser->ExtractDelimitedString($Case, '<td>', '</div>');
        $this->assertFalse($Success);
        $this->assertEqual($Case, $HTML);
        // if no delimiters found return false and get unmodified source string
        $Case= $HTML;
        $Success= $Parser->ExtractDelimitedString($Case, '<div>', '</div>');
        $this->assertFalse($Success);
        $this->assertEqual($Case, $HTML);
        // test error message
        $Case= $HTML;
        $Parser->ExtractDelimitedString($Case, '<div>', '</div>', 'MyErrorMessage');
        $Errors= $Parser->GetParsingErrors();
        $this->assertEqual(count($Errors), 1);
        $this->assertTrue(strpos($Errors[0], 'MyErrorMessage') === 0);
    }


    public function TestExtractDelimitedStrings() {

        $Parser= $this->Build();
        $HTML= '<tr><th>Color</th><td>Blue</td><td>White</td><td>Red</td></tr>';
        // get all parts between <td> and </td>
        $Case= $HTML;
        $Success= $Parser->ExtractDelimitedStrings($Case, '<td>', '</td>');
        $this->assertTrue($Success);
        $this->assertEqual($Case, array('Blue','White','Red'));
        // get empty result
        $Case= $HTML;
        $Success= $Parser->ExtractDelimitedStrings($Case, '<a ', '</a>');
        $this->assertFalse($Success);
        $this->assertEqual($Case, array());
        // test error message
        $Case= $HTML;
        $Success= $Parser->ExtractDelimitedStrings($Case, '<td>', '</td>', 'Must be two', 2);
        $this->assertFalse($Success);
        $Errors= $Parser->GetParsingErrors();
        $this->assertEqual(count($Errors), 1);
        $this->assertTrue(strpos($Errors[0], 'Must be two') === 0);
        $Case= $HTML;
        $Success= $Parser->ExtractDelimitedStrings($Case, '<td>', '</td>', 'Must be three', 3);
        $this->assertTrue($Success);
        $this->assertEqual($Parser->GetParsingErrors(), array());
    }


    public function TestExtractQuotedStrings() {

        $Parser= $this->Build();
        $HTML= '<p><a href="/a.php">A</a><br>'  // double-quoted
            .'<a href=\'/b.php\'>B</a><br>'     // single-quoted
            .'<a href = " /c.php " >C</a></p>>';  // double-quoted with spaces
        // get simple cases
        $Case= $HTML;
        $Success= $Parser->ExtractQuotedStrings($Case);
        $this->assertTrue($Success);
        $this->assertEqual($Case, array('/a.php','/b.php',' /c.php '));
        // test incomplete quoting
        $HTML= "start'one\"two\\'quoted-one\\\"quoted-two";
        $Success= $Parser->ExtractQuotedStrings($HTML);
        $this->assertFalse($Success);
        $this->assertEqual($HTML, array());
        // test consecutive backslashes, only odd number of backslashes can escape quote
        $BS= '\\';
        //            /----this-is-string-----\                                        /----------------this-is-string------------------\
        //          quote    non-quote     quote          non-quote                 quote                non-quote                    quote
        $HTML= "zero:('),one:($BS'),two:($BS$BS'),three:($BS$BS$BS'),four:($BS$BS$BS$BS'),five:($BS$BS$BS$BS$BS'),six:($BS$BS$BS$BS$BS$BS')";
        $Success= $Parser->ExtractQuotedStrings($HTML);
        $this->assertTrue($Success);
        $this->assertEqual($HTML, array(
            "),one:($BS'),two:($BS$BS",
            "),five:($BS$BS$BS$BS$BS'),six:($BS$BS$BS$BS$BS$BS",
        ));

    }


    public function TestExtractAttr() {

        $Parser= $this->Build();
        $HTML= '<p><a href="/a.php">href</a><br>'  // double-quoted
            .'<a href=\'/b.php\'>href</a><br>'     // single-quoted
            .'<a xhref=\'/x.php\'>href</a><br>'     // prefixed attribute
            .'<a Href = " /c.php " >href</a></p>>';  // double-quoted, with spaces, uppercase
        // get simple cases
        $Case= $HTML;
        $Success= $Parser->ExtractAttrs($Case, 'href');
        $this->assertTrue($Success);
        $this->assertEqual($Case, array('/a.php','/b.php',' /c.php '));
        // test incomplete quoting
        $HTML= "<div id=\"bac'>a</div><p class='ret\">b</p>";
        $Success= $Parser->ExtractAttrs($HTML, 'class');
        $this->assertFalse($Success);
        $this->assertEqual($HTML, array());
        // test consecutive backslashes
        $BS= '\\';
        //            /-------this-is-string-------\                                     /--------------this-is-string-----------------\
        //          quote      non-quote        quote          non-quote              quote              non-quote                  quote
        $HTML= "<p id='*******id=$BS'******id=$BS$BS'*****id=$BS$BS$BS'****id=$BS$BS$BS$BS'***id=$BS$BS$BS$BS$BS'**id=$BS$BS$BS$BS$BS$BS')";
        $Success= $Parser->ExtractAttrs($HTML, 'id');
        $this->assertTrue($Success);
        $this->assertEqual($HTML, array(
            "*******id=$BS'******id=$BS$BS",
            "***id=$BS$BS$BS$BS$BS'**id=$BS$BS$BS$BS$BS$BS",
        ));
    }


    public function TestCompressSpaces() {

        $Parser= $this->Build();
        $this->assertEqual($Parser->CompressSpaces(array()), "array (\n)");
        $this->assertEqual($Parser->CompressSpaces(''), '');
        $this->assertEqual($Parser->CompressSpaces(' '), ' ');          // single space must be preserved, this is not "trim" method
        $this->assertEqual($Parser->CompressSpaces('  '), ' ');         // double-space to single-space
        $this->assertEqual($Parser->CompressSpaces('   '), ' ');        // triple-space to single-space
        $this->assertEqual($Parser->CompressSpaces("\t"), " ");         // convert tab to space, it simplifies further tasks
        $this->assertEqual($Parser->CompressSpaces(" \t"), ' ');        // convert space+tab into single space
        $this->assertEqual($Parser->CompressSpaces('Id:    1        2'), 'Id: 1 2');
        $this->assertEqual($Parser->CompressSpaces('',true), '');       // use "consuming newline chars"
        $this->assertEqual($Parser->CompressSpaces("A\nB\rC \n D",true), 'A B C D');
    }

}


?>