<?php namespace Accent\AccentCore\Test;

use Accent\Test\AccentTestCase;
use Accent\AccentCore\ArrayUtils\ArrayUtils;


/**
 * Testing Accent\AccentCore\ArrayUtils\ArrayUtils
 */

class Test__ArrayUtils extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'ArrayUtils / ArrayUtils test';

    // title of testing group
    const TEST_GROUP= 'AccentCore';



    // TESTS:

    public function TestSmartMerge() {

        $Arr1= array(
            'one'=> '#',
            'two'=> array(
                'two-1'=> array(
                    'two-1-a'=> '#',
                    'two-1-b'=> '#',
                ),
                'two-2'=> '#',
            ),
        );
        $Arr2= array(
            'two'=> array(
                'two-1'=> array(
                    'two-1-a'=> '?', // should overwrite two/two-1/two-1-a
                    'two-1-c'=> '?', // should add new child to deep subnode
                ),
                'two-2'=> '?', // should overwrite subnode
            ),
            'three'=> array( // should add new node
                'three-1'=> array(
                    'three-1-a'=> '?',
                ),
            ),
            'one'=> '?', // should overwrite node, must be immune to wrong order
        );
        $Arr3= array(
            'two'=> array(
                'two-1'=> array(
                    'two-1-a'=> '-', // again overwrite two/two-1/two-1-a
                ),
             ),
            'three'=> array(), // overwrite and delete all subnodes
        );
        $Expected= array(
            'one'=> '?',
            'two'=> array(
                'two-1'=> array(
                    'two-1-a'=> '-',
                    'two-1-b'=> '#',
                    'two-1-c'=> '?',
                ),
                'two-2'=> '?',
            ),
            'three'=> array(),
        );
        $AU= new ArrayUtils();
        $Result= $AU->SmartMerge($Arr1, $Arr2, $Arr3);
        $this->assertEqual($Result, $Expected);
    }


    protected $ConvertedArray= array(
        'Slashes'=> '/cache/',
        "'Quoted'"=> "'Quotes'",
        'MultiLine'=> "First\nSecond",
        'UTF8'=> 'Košta 1€',
        'Array'=> array(
            'one'=>'a',
            'two'=>'b',
        ),
    );


    protected function CompressString($String) {

        return str_replace(array(' ',"\n","\r","\t"), '', $String);
    }


    public function TestJsonConvertor() {

        $Json= '{
    "Slashes": "/cache/",
    "\'Quoted\'": "\'Quotes\'",
    "MultiLine": "First\nSecond",
    "UTF8": "Košta 1€",
    "Array": {
        "one":"a",
        "two":"b"
    }}';
        $AU= new ArrayUtils();
        $Result= $AU->JsonToArray($Json);
        $this->assertEqual($Result, $this->ConvertedArray);

        $Result= $AU->JsonToArray($AU->ArrayToJson($this->ConvertedArray));
        $this->assertEqual($this->ConvertedArray, $Result);
    }


    public function TestIniConvertor() {

        $Ini= '
Slashes= "/cache/"
\'Quoted\'= "\'Quotes\'"
MultiLine= "First'."\n".'Second"
UTF8= "Košta 1€"
[Array]
one= a
two= b
';
        $AU= new ArrayUtils();
        $Result= $AU->IniToArray($Ini);
        $this->assertEqual($Result, $this->ConvertedArray);

        $Result= $AU->IniToArray($AU->ArrayToIni($this->ConvertedArray));
        $this->assertEqual($this->ConvertedArray, $Result);
    }


    public function TestFindPreviousAndNextByValue() {

        $Cases= array(
            'Array'=> array('d'=>'1', 'a'=>2, 'b'=>'6', 'c'=>'24', 'f'=>'xy', 'h'=>'m'),
            'TestPrev'=> array(
                'RetVal'=> array(3=>2, 1=>null, 24=>6, 23=>6, 'ppp'=>'m', 'xz'=>'xy'),
                'RetKey'=> array(3=>'a', 1=>null, 24=>'b', 23=>'b', 'ppp'=>'h', 'xz'=>'f'),
            ),
            'TestNext'=> array(
                'RetVal'=> array(3=>6, '1'=>2, -32=>1, 5=>6, 'ppp'=>'xy', 'xz'=>null),
                'RetKey'=> array(3=>'b', 1=>'a', -32=>'d', 5=>'b', 'ppp'=>'f', 'xz'=>null),
            ),
        );
        $AU= new ArrayUtils();
        foreach($Cases['TestPrev'] as $Type=>$Questions) {
            foreach($Questions as $Question=>$Expected) {
                $Result= $AU->FindPreviousByValue($Cases['Array'], $Question, $Type=='RetKey');
                $Description= "Prev/$Type/".var_export($Question,true).': expected:'.var_export($Expected,true).', returned:'.var_export($Result,true);
                $this->assertEqual($Result, $Expected, $Description);
            }
        }
        foreach($Cases['TestNext'] as $Type=>$Questions) {
            foreach($Questions as $Question=>$Expected) {
                $Result= $AU->FindNextByValue($Cases['Array'], $Question, $Type=='RetKey');
                $Description= "Next/$Type/".var_export($Question,true).': expected:'.var_export($Expected,true).', returned:'.var_export($Result,true);
                $this->assertEqual($Result, $Expected, $Description);
            }
        }
    }


    public function TestGetColumn() {

        $AU= new ArrayUtils();
        $Arr= array(array('1','2','3','4'),array('a','b','c','d'),array('A','B','C','D'));
        $this->assertEqual($AU->GetColumn($Arr,null), array('1','a','A'));
        $this->assertEqual($AU->GetColumn($Arr,2), array('3','c','C'));
        // test wrong column key and without removing duplicates
        $this->assertEqual($AU->GetColumn($Arr,6,false), array(null,null,null));
        // test associative keys
        $AssocArr= array(array('a'=>1,'b'=>2),array('a'=>3,'b'=>4),array('a'=>5,'b'=>6));
        $this->assertEqual($AU->GetColumn($AssocArr,'b'), array(2,4,6));
    }

}

