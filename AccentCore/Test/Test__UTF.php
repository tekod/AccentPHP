<?php namespace Accent\AccentCore\Test;

/**
 * Testing UTF helper
 */

use Accent\Test\AccentTestCase;
use Accent\AccentCore\UTF\UTF;


class Test__UTF extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'UTF';

    // title of testing group
    const TEST_GROUP= 'AccentCore';

    // internal
    protected $Engines= array();


    // TESTS:

    public function TestCreating() {

        if (function_exists('mb_strlen')) {
            $this->Engines['mbstring']= new UTF(array('ForceEngine'=>'mbstring'));
        } else {
            $this->WarningMessage('Notice: cannot test "mbstring" - extension not found.');
        }
	    if (function_exists('iconv_strlen')) {
            $this->Engines['iconv']= new UTF(array('ForceEngine'=>'iconv'));
        } else {
            $this->WarningMessage('Notice: cannot test "iconv" - extension not found.');
        }
        $this->Engines['slow']= new UTF(array('ForceEngine'=>'slow'));
    }


    public function TestStrlen() {

        $Tests= array('aĆcd'=>4, 'ćuć'=>3, '€¢'=>2, chr(0)=>1,
            'šđčćž'=>5, 'шђчћжШЂЧЋЖ'=>10, 'пьяный'=>6, 'αβγλΞΩψ'=>7, '你好我是你的朋友'=>8, );
        foreach($this->Engines as $Name=>$Engine) {
            foreach($Tests as $k=>$v) {
                $res= $Engine->strlen($k);
                $this->assertEqual($res, $v, "$Name:strlen($k)($res=$v)");
            }
        }
    }


    public function TestSubstr() {
        $Str= 'ж123456789ж';
        $Tests= array(
            array(0, 1, 'ж'),
            array(0, 2, 'ж1'),
            array(0, null, $Str),
            array(0, -7, 'ж123'),
            array(1, 1, '1'),
            array(1, null, '123456789ж'),
            array(-2, 1, '9'),
            array(-2, null, '9ж'),
            array(-3, -2, '8'),
            array(2, 0, ''),
            array(10, null, 'ж'),
            array(11, null, ''),
        );
        foreach($this->Engines as $Name=>$Engine) {
            foreach($Tests as $Test) {
                $res= $Engine->substr($Str, $Test[0], $Test[1]);
                //$this->WarningMessage($Name.':"'.$res.'":'.strlen($res));
                $this->assertEqual($res, $Test[2], "$Name:substr($Test[0],$Test[1])($res=$Test[2])");
            }
        }
    }


    public function TestStrPos() {
        $Str= 'ж1234ж';
        $Tests= array(
            array('ж', 0, 0),
            array('ж', 5, 1),
            array('ж1', 0, 0),
            array('1', 1, 0),
            array('4ж', 4, 0),
            array($Str, 0, 0),
            array($Str.'_', false, 0),
            array('4ж1', false, 0),
            array('', false, 0),
        );
        foreach($this->Engines as $Name=>$Engine) {
            foreach($Tests as $Test) {
                $res= $Engine->strpos($Str, $Test[0], $Test[2]);
                $this->assertEqual($res, $Test[1], "$Name:strpos($Test[0],$Test[2])($res=$Test[1])");
            }
        }
    }


    public function TestStrRPos() {
        $Str= 'ж1234ж';
        $Tests= array(
            array('ж', 5, 0),
            array('ж', 5, 2),
            array('ж', 0, -2),
            array('ж1', 0, 0),
            array('1', 1, 0),
            array('4ж', 4, 0),
            array($Str, 0, 0),
            array($Str.'_', false, 0),
            array('4ж1', false, 0),
            array('', false, 0),
        );
        foreach($this->Engines as $Name=>$Engine) {
            foreach($Tests as $T) {
                $res= $Engine->strrpos($Str, $T[0], $T[2]);
                //$this->WarningMessage(var_export($T,true));
                $this->assertEqual($res, $T[1], "$Name:strRpos($T[0],$T[2])($res=$T[1])");
            }
        }
    }


    public function TestStrToLower() {

        $Tests= array('aČcd'=>'ačcd', 'ćuĆ'=>'ćuć', '€¢'=>'€¢', chr(0)=>chr(0),
            'šđčćžŠĐČĆŽ'=>'šđčćžšđčćž', 'шђчћжШЂЧЋЖ'=>'шђчћжшђчћж',
            'ПЬЯЫЩъЪ'=>'пьяыщъъ', 'ΑΒΕΖΗΝΞΣΦΧΩ'=>'αβεζηνξσφχω', '你好是你的友'=>'你好是你的友');
        foreach($this->Engines as $Name=>$Engine) {
            foreach($Tests as $k=>$v) {
                $res= $Engine->strtolower($k);
                $this->assertEqual($res, $v, "$Name:strtolower($k)($res=$v)");
            }
        }
    }


    public function TestStrToUpper() {

        $Tests= array('ačcd'=>'AČCD', 'ćuĆ'=>'ĆUĆ', '€¢'=>'€¢', chr(0)=>chr(0),
            'šđčćžŠĐČĆŽ'=>'ŠĐČĆŽŠĐČĆŽ', 'шђчћжШЂЧЋЖ'=>'ШЂЧЋЖШЂЧЋЖ',
            'пьяыщЪъ'=>'ПЬЯЫЩЪЪ', 'αβεζηνξσφχω'=>'ΑΒΕΖΗΝΞΣΦΧΩ', '你好是你的友'=>'你好是你的友');
        foreach($this->Engines as $Name=>$Engine) {
            foreach($Tests as $k=>$v) {
                $res= $Engine->strtoupper($k);
                $this->assertEqual($res, $v, "$Name:strtoupper($k)($res=$v)");
            }
        }
    }


    public function TestStrSplit() {

        $Tests= array(
            array('qwert', 1, array('q','w','e','r','t')),
            array('qwert', 2, array('qw','er','t')),
            array('qwert', 3, array('qwe','rt')),
            array('љњерт', 1, array('љ','њ','е','р','т')),
            array('љњерт', 2, array('љњ','ер','т')),
            array('', 1, array()),
            array('', 2, array()),
            array('-љ-њ-е-р-т', 2, array('-љ','-њ','-е','-р','-т')),
        );
        foreach($this->Engines as $Name=>$Engine) {
            foreach($Tests as $Test) {
                $res= $Engine->str_split($Test[0], $Test[1]);
                $this->assertEqual($res, $Test[2], "$Name:str_split($Test[0],$Test[1])=".json_encode($res).")");
            }
        }
    }


    public function TestUcFirst() {

        $Tests= array('aČcd'=>'AČcd', 'ćuĆ'=>'ĆuĆ', '€¢'=>'€¢', chr(0)=>chr(0),
            'šđčćžŠĐČĆŽ'=>'ŠđčćžŠĐČĆŽ', 'шђчћжШЂЧЋЖ'=>'ШђчћжШЂЧЋЖ',
            'пьяыщЪъ'=>'ПьяыщЪъ', 'αβεζηνξσφχω'=>'Αβεζηνξσφχω', '你好是你的友'=>'你好是你的友');
        foreach($this->Engines as $Name=>$Engine) {
            foreach($Tests as $k=>$v) {
                $res= $Engine->ucfirst($k);
                $this->assertEqual($res, $v, "$Name:ucfirst($k)($res=$v)");
            }
        }
    }


    public function TestChr() {

        $Tests= array(0=>chr(0), 32=>' ', 126=>'~', 0x17D=>'Ž', 0x2591=>'░',
            0x03A3=>'Σ', 0x0419=>'Й', 0x53F6=>'叶', 0xA2=>'¢', 0x20AC=>'€', 0xB5AB=>'떫');
        foreach($this->Engines as $Name=>$Engine) {
            foreach($Tests as $k=>$v) {
                $res= $Engine->chr($k);
                $this->assertEqual($res, $v, "$Name:chr($k)($res=$v)");
            }
        }
    }


    public function TestSimplifiedDiacritics() {

        $Tests= array('a'=>'a', ' '=>' ', '.'=>'.', '?'=>'?', chr(0)=>chr(0),
            'à'=>'a', 'Ç'=>'C', "Ć"=>"C", "đ"=>"dj", 'Ë'=>'E', 'Ñ'=>'N', 'õ'=>'o', 'Ü'=>'U');
        foreach($this->Engines as $Name=>$Engine) {
            foreach($Tests as $k=>$v) {
                $res= $Engine->SimplifiedDiacritics($k);
                $this->assertEqual($res, $v, "$Name:SimplifiedDiacritics($k)($res=$v)");
            }
        }
    }

}


