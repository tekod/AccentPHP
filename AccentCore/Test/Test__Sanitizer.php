<?php namespace Accent\AccentCore\Test;

use Accent\Test\AccentTestCase;
use Accent\AccentCore\Filter\Sanitizer;


/**
 * Testing sanitizer component.
 */

class Test__Sanitizer extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Filter / Sanitizer service';

    // title of testing group
    const TEST_GROUP= 'AccentCore';


    protected function BuildSanitizer($NewOptions=array()) {

        $Options= $NewOptions + array(
            'Services'=> array(
                'UTF'=> new \Accent\AccentCore\UTF\UTF,
            ),
        );
        return new Sanitizer($Options);
    }



    // TESTS:

    public function TestInteger() {

        $Tests= array('247kCC'=>247, 'kCC'=>0, '0.9'=>0, 0.9=>0, true=>1, false=>0,
            ''=>0, '002'=>2, );
        $F= $this->BuildSanitizer();
        foreach($Tests as $k=>$v) {
            $this->assertEqual($F->Sanitize($k, 'I'), $v);
        }
    }


    public function TestTrim() {

        $Tests= array('22'=>'22', ' 2 '=>'2', ''=>'', 0.9=>'0.9', true=>'1', false=>'0',
            "\n"=>'', "\r\n\t"=>'', "\nK\r"=>'K', "\tE\n"=>'E', );
        $F= $this->BuildSanitizer();
        foreach($Tests as $k=>$v) {
            $res= $F->Sanitize($k, 'T');
            $this->assertEqual($res, $v, '["'.$k.'":"'.$v.'"] = "'.$res.'"');
        }
    }


    public function TestFloat() {

        $Tests= array('22'=>22, ' 2 '=>'2', ''=>'', 0.71=>0.71, true=>1, false=>0,
            "\n"=>0, "\r\n\t"=>0, "\n91\r"=>91, "\t92\n"=>92,
            '0.72'=>0.72, );
        $F= $this->BuildSanitizer();
        foreach($Tests as $k=>$v) {
            $res= $F->Sanitize($k, 'FLOAT');
            $this->assertEqual($res, $v, '["'.$k.'":"'.$v.'"] = "'.$res.'"');
        }
    }


    public function TestLocal() {

        $LocalizationOptions= array(
            'DefaultLang'=> 'sr', // use serbian to test different decimal separator
            'LoaderConfigs'=> array(
                'php1'=> array(
                    'LoaderClass'=> 'PHP',
                    'Directories'=> __DIR__.'/../../Localization/Test/formating',
                ),
            ),
            'Services'=> array(
                'Event'=> new \Accent\AccentCore\Event\EventService,
            ),
        );
        $NewOptions= array(
            'Services'=> array(
                'UTF'=> new \Accent\AccentCore\UTF\UTF,
                'Localization'=> new \Accent\Localization\Localization($LocalizationOptions),
            ),
        );
        $F= $this->BuildSanitizer($NewOptions);
        $Tests= array(
            '0,58'=> '0.58',    // decimal separator
            '1.500.000'=> '1500000',// thousand separator
            '2.048,9'=> '2048.9',   // combined
            ',4'=> '.4',            // missing leading zero
            '-14,2'=> '-14.2',      // negative number
        );
        foreach($Tests as $k=>$v) {
            $res= $F->Sanitize($k, 'Local');
            $this->assertEqual($res, $v, '["'.$k.'":"'.$v.'"] = "'.$res.'"');
        }
    }


    public function TestUrlDecode() {
        // also testing full name of filter ('URLDECODE') instead of alias '@'.
        $Tests= array('22'=>22, ' 2 '=>' 2 ', ''=>'', 0.71=>'0.71', true=>'1', false=>'0',
            '+'=>' ', '%20'=>' ',);
        $F= $this->BuildSanitizer();
        foreach($Tests as $k=>$v) {
            $res= $F->Sanitize($k, 'URLDECODE');
            $this->assertEqual($res, $v, '["'.$k.'":"'.$v.'"] = "'.$res.'"');
        }
    }


    public function TestCaseUpper() {

        $Tests= array('22'=>22, ' 2 '=>' 2 ', ''=>'', 0.71=>0.71, true=>1, false=>0,
            'maja'=>'MAJA', 'Đorđe'=>'ĐORĐE', '_@'=>'_@', );
        $F= $this->BuildSanitizer();
        foreach($Tests as $k=>$v) {
            $res= $F->Sanitize($k, 'CU');
            $this->assertEqual($res, $v, '["'.$k.'":"'.$v.'"] = "'.$res.'"');
        }
    }


    public function TestCaseLower() {

        $Tests= array('22'=>22, ' 2 '=>' 2 ', ''=>'', 0.71=>0.71, true=>1, false=>0,
            'maja'=>'maja', 'Đorđe'=>'đorđe', '_@'=>'_@', );
        $F= $this->BuildSanitizer();
        foreach($Tests as $k=>$v) {
            $res= $F->Sanitize($k, 'CL');
            $this->assertEqual($res, $v, '["'.$k.'":"'.$v.'"] = "'.$res.'"');
        }
    }


    public function TestLen() {

        $Tests= array(
            array('123',3,'123'),
            array('',3,''),
            array('1234',3,'123'),
            array('Ш123',3,'Ш12'),
            array('abc',1,'a'),
            array('abc',0,''),
            array(true,3,'1'),
            array(false,3,'0'),
        );
        $F= $this->BuildSanitizer();
        foreach($Tests as $T) {
            $res= $F->Sanitize($T[0], 'Len:'.$T[1]);
            $this->assertEqual($res, $T[2], "['$T[0]','$T[1]','$T[2]'] = '$res'");
        }
    }


    public function TestPad() {

         $Tests= array(
            array('123','3:_:L','123'),
            array('123','5:_:L','__123'),
            array('123','1:_:L','123'),
            array('123','5:_:R','123__'),
            array('123','5:_:B','_123_'),
            array('','5:_:L','_____'),
        );
        $F= $this->BuildSanitizer();
        foreach($Tests as $T) {
            $res= $F->Sanitize($T[0], 'Pad:'.$T[1]);
            $this->assertEqual($res, $T[2], "['$T[0]','$T[1]','$T[2]'] = '$res'");
        }
    }


    public function TestRange() {

        $Tests= array(
            array(0, '0..3', 0),
            array(0, '1..3', 1),
            array(2, '1..3', 2),
            array(3, '1..3', 3),
            array(4, '1..3', 3),
            array(-8, '1..2', 1),
            array(-7, '-1..1', -1),
            array(-1, '-9..-2', -2),
            array(-5, '-8..-3', -5),
            array(-9, '-6..-4', -6),
            array(5, '-5..-3', -3),
            array('', '4..6', 4),
            array('d', '4..6', 4),
            array('5', '4..6', 5),
            array('4.1', '4..6', 4.1),
        );
        $F= $this->BuildSanitizer();
        foreach($Tests as $T) {
            $res= $F->Sanitize($T[0], 'Range:'.$T[1]);
            $this->assertEqual($res, $T[2], "['$T[0]','$T[1]','$T[2]'] = '$res'");
        }
    }


    public function TestPregReplace() {

        $Tests= array(
            array('a1b2c3', '/[^\d]/:a', 'a1a2a3'),
        );
        $F= $this->BuildSanitizer();
        foreach($Tests as $T) {
            $res= $F->Sanitize($T[0], 'PregReplace:'.$T[1]);
            $this->assertEqual($res, $T[2], "['$T[0]','$T[1]','$T[2]'] = '$res'");
        }
    }


    public function TestAlpha() {

        $Tests= array('22'=>'', ' 2 '=>'', ''=>'', 0.9=>'', true=>'', false=>'',
            "\n"=>'', "\r\n\t"=>'', "\nK\r"=>'K', "\tE\n"=>'E', );
        $F= $this->BuildSanitizer();
        foreach($Tests as $k=>$v) {
            $res= $F->Sanitize($k, 'Alpha');
            $this->assertEqual($res, $v, '["'.$k.'":"'.$v.'"] = "'.$res.'"');
        }
    }


    public function TestAlnum() {

        $Tests= array('22'=>'22', ' 2h '=>'2h', ''=>'', 0.9=>'09', true=>'1', false=>'0',
            "\n"=>'', "\r\n\t"=>'', "\nK\r"=>'K', "\tE\n"=>'E', );
        $F= $this->BuildSanitizer();
        foreach($Tests as $k=>$v) {
            $res= $F->Sanitize($k, 'Alnum');
            $this->assertEqual($res, $v, '["'.$k.'":"'.$v.'"] = "'.$res.'"');
        }
    }


    public function TestDigits() {

        $Tests= array('22'=>'22', ' 2h '=>'2', ''=>'', 0.9=>'09', true=>'1', false=>'0',
            "\n"=>'', "\r\n\t"=>'', "\nK\r"=>'', "\tE\n"=>'', );
        $F= $this->BuildSanitizer();
        foreach($Tests as $k=>$v) {
            $res= $F->Sanitize($k, 'Digits');
            $this->assertEqual($res, $v, '["'.$k.'":"'.$v.'"] = "'.$res.'"');
        }
    }


    public function TestFileName() {

        $Tests= array('mama.txt'=>'mama.txt', ' '=>'', ''=>'', 0.9=>'0.9',
            '/etc/pass'=>'etcpass', '/?*:'=>'', '_-+!~|'=>'_-+!~|', true=>'1', false=>'0',
            "\n"=>'', "\r\n\t"=>'', "\nK\r"=>'K', "\tE\n"=>'E', );
        $F= $this->BuildSanitizer();
        foreach($Tests as $k=>$v) {
            $res= $F->Sanitize($k, 'FileName');
            $this->assertEqual($res, $v, '["'.$k.'":"'.$v.'"] = "'.$res.'"');
        }
    }


    public function TestFunc() {

        $Tests= array('mama'=>'#mama', ' '=>'# ', ''=>'#', 0.9=>'#0.9', false=>'#0',);
        $F= $this->BuildSanitizer();
        foreach($Tests as $k=>$v) {
            $res= $F->Sanitize($k, 'Func:'.get_class($this).':_DemoFunc');
            $this->assertEqual($res, $v, '["'.$k.'":"'.$v.'"] = "'.$res.'"');
        }
    }
    public static function _DemoFunc($Val) {
        return '#'.$Val;
    }


    public function TestAddSanitizer() {

        $F= $this->BuildSanitizer();
        $F->Add('Testing', array($this,'_DemoFunc'));
        $TestValue= 'M';
        $res= $F->Sanitize($TestValue, 'Testing');
        $this->assertEqual($res, '#M', '"#M" = "'.$res.'"');
    }


}


