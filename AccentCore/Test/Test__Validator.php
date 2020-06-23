<?php namespace Accent\AccentCore\Test;

use Accent\Test\AccentTestCase;
use Accent\AccentCore\Filter\Validator;


class Test__Validator extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Filter / Validator service test';

    // title of testing group
    const TEST_GROUP= 'AccentCore';


    protected function BuildValidator($NewOptions=array()) {

        $Options= $NewOptions + array(
            'Services'=> array(
                'UTF'=> new \Accent\AccentCore\UTF\UTF,
            ),
        );
        return new Validator($Options);
    }



    // TESTS:

    public function TestEqual() {

        $Tests= array(
            array('', '', true),
            array('', '0', false),
            array('', 0, false),
            array('', false, false),
            array(null, false, false),
            array(null, true, false),
            array(null, null, true),
            array(true, true, true),
            array(false, false, true),
            array(array(), array(), true),
            array(array(array()), array(array()), true),
            array(array(1,2), array(2,1), false),
            array('0', '0.00', false),
        );
        $V= $this->BuildValidator();
        foreach($Tests as $Test) {
            $Res= $V->Validate($Test[0], 'Equal', $Test[1]);
            $this->assertEqual($Res, $Test[2], '['.var_export($Test[0],true).':'.var_export($Test[1],true).' = '.var_export($Res,true).'"]');
        }
    }


    public function TestEmail() {

        $Tests= array(
            '' => false,
        	'me@domain.com' => true,
        	'me@100.99.1.0' => true,
        	'me.me@hac.ke.rs' => true,
        	'domain' => false,
        	'@domain.com' => false,
        	'me.domain.com' => false,
        	'me@domain' => false,
        	'me@domain@com' => false,
            'me@domain.c'=> false,
            'gmail@chucknorris.com' => true,
            'me@me@domain.com'=> false,
            'me:me@domain.com'=> false,
            'me+me@domain.com'=> true,
            'me.me@domain.com'=> true,
        );
        $V= $this->BuildValidator();
        foreach($Tests as $k=>$v) {
            $Res= $V->Validate($k, 'Email');
            $this->assertEqual($Res, $v, '["'.$k.'" = "'.var_export($Res,true).'"]');
        }
    }


    public function TestEmailNamed() {

        $Tests= array(
            '' => false,
        	'<me@domain.com>' => true,
        	'MyName<me@domain.com>' => true,
			'me.m/y+self<me@domain.com>' => true,
			'first last name<me@domain.com>' => true,
			'me@domain<me@domain.com>' => false,
        );
        $V= $this->BuildValidator();
        foreach($Tests as $k=>$v) {
            $Res= $V->Validate($k, 'EmailNamed');
            $this->assertEqual($Res, $v, '["'.$k.'" = "'.var_export($Res,true).'"]');
        }
    }


    public function TestUtf8() {
        $Tests = array(
        	'' => true,
        	'text' => true,
			'ΑβγδÜberГджз' => true,
			chr(220) => false,
			chr(150).chr(70) => false,
        );
        $V= $this->BuildValidator();
        foreach ($Tests as $k => $v) {
            $Res= $V->Validate($k, 'UTF8');
            $this->assertEqual($Res, $v, "['$k'] %s");
        }
    }


    public function TestInRange() {
        $Tests = array(
        	':1:5' => false,
        	'1:1:5' => true,
			'15:1:5' => false,
			'A:1:5' => false,
			'A:A:C' => true,
			'Z:A:C' => false,
			'15:4:45' => true,
        );
        $V= $this->BuildValidator();
        foreach ($Tests as $k => $v) {
        	list($t,$min,$max)= explode(':', $k);
            $Res= $V->Validate($t, "InRange", "$min..$max");
            $this->assertEqual($Res, $v, "['$k'] %s");
        }
    }

    public function TestMax() {
        $Tests = array(
            array('', 1, true),
        	array(4, 10, true),
            array(10, 10, true),
            array(11, 10, false),
            array('10', '10', true),
            array('11', '10', false),
            array('C', 'DEEE', true),
            array('F', 'DEEE', false),
            array(-3, -2, true),
            array(-1, -2, false),
        );
        $V= $this->BuildValidator();
        foreach ($Tests as $Test) {
        	$Res= $V->Validate($Test[0], "Max", $Test[1]);
            $this->assertEqual($Res, $Test[2], "[".var_export($Test[0],true).'='.var_export($Test[1],true).']');
        }
    }

    public function TestMin() {
        $Tests = array(
            array('', 1, false),
        	array(4, 10, false),
            array(10, 10, true),
            array(11, 10, true),
            array('10', '10', true),
            array('11', '10', true),
            array('C', 'DEEE', false),
            array('F', 'DEEE', true),
            array(-3, -2, false),
            array(-1, -2, true),
        );
        $V= $this->BuildValidator();
        foreach ($Tests as $Test) {
        	$Res= $V->Validate($Test[0], "Min", $Test[1]);
            $this->assertEqual($Res, $Test[2], "[".var_export($Test[0],true).'='.var_export($Test[1],true).']');
        }
    }


    public function TestIn() {
        $Tests = array(
            array('', array(1,2,3), false),
            array('', array('',1,2,3), true),
            array(1, array(1,2,3), true),
            array('A', array('a','b','c'), false),
            array('A', array('','A'), true),
            array('15', array('151','152'), false),
            array('15', '151,152', false),
            array('152', '151,152', true),
        );
        $V= $this->BuildValidator();
        foreach ($Tests as $Test) {
        	$Res= $V->Validate($Test[0], "In", $Test[1]);
            $this->assertEqual($Res, $Test[2], "[".var_export($Test[0],true).'='.var_export($Test[1],true).']');
        }
    }


    public function TestLen() {

        $Tests= array(
            '' => 0,
        	'me' => 2,
        	1234 => 4,
        	'0' => 1,
        	'ΑβγδÜberГджз' => 12,
        );
        $V= $this->BuildValidator();
        foreach($Tests as $k=>$v) {
            $Res= $V->Validate($k, 'Len', $v);
            $this->assertEqual($Res, true, '["'.$k.'" = '.$v.'] %s');
        }
    }

    public function TestLenMax() {

        $Tests= array(
            array('', 0, true),
            array('me', 2, true),
            array('me', 1, false),
            array(1234, 4, true),
            array(1234, 2, false),
            array('ΑβγδÜberГджз', 14, true),
            array('ΑβγδÜberГджз', 8, false),
        );
        $V= $this->BuildValidator();
        foreach($Tests as $Test) {
            $Res= $V->Validate($Test[0], 'LenMax', $Test[1]);
            $this->assertEqual($Res, $Test[2], '["'.$Test[0].'" = '.$Test[1].'] %s');
        }
    }

    public function TestLenMin() {

        $Tests= array(
            array('', 0, true),
            array('me', 2, true),
            array('me', 3, false),
            array(1234, 4, true),
            array(1234, 5, false),
            array('ΑβγδÜberГджз', 12, true),
            array('ΑβγδÜberГджз', 15, false),
        );
        $V= $this->BuildValidator();
        foreach($Tests as $Test) {
            $Res= $V->Validate($Test[0], 'LenMin', $Test[1]);
            $this->assertEqual($Res, $Test[2], '["'.$Test[0].'" = '.$Test[1].'] %s');
        }
    }

    public function TestLenRange() {

        $Tests= array(
            array('', '0..2', true),
            array('me', '2..4', true),
            array('me', '3..4', false),
            array(1234, '4..6', true),
            array(1234, '5..6', false),
            array('ΑβγδÜberГджз', '10..12', true),
            array('ΑβγδÜberГджз', '15..88', false),
        );
        $V= $this->BuildValidator();
        foreach($Tests as $Test) {
            $Res= $V->Validate($Test[0], 'LenRange', $Test[1]);
            $this->assertEqual($Res, $Test[2], '["'.$Test[0].'" = '.$Test[1].'] %s');
        }
    }


    public function TestRegEx() {

        $Tests= array(
            array('', '/\s/', false),
        	array('a', '/\w/', true),
			array('a007', '/\d{3}/', true),
        );
        $V= $this->BuildValidator();
        foreach($Tests as $Test) {
            $Res= $V->Validate($Test[0], 'RegEx', $Test[1]);
            $this->assertEqual($Res, $Test[2], '["'.$Test[0].'" :: "'.$Test[1].'" = '.var_export($Test[2],true).'] %s');
        }
    }


    public function TestFileName() {

        $Tests= array(
            '' => false,
        	'abc.' => true,
        	'a:bc' => false,
			'a/b' => false,
        	'δÜд' => false,
        );
        $V= $this->BuildValidator();
        foreach($Tests as $k=>$v) {
            $Res= $V->Validate($k, 'FileName');
            $this->assertEqual($Res, $v, '["'.$k.'" = '.var_export($v,true).'] %s');
        }
    }


    public function TestURL() {

        $Tests= array(
            '' => false,
        	'domain.com' => true,
        	'www.domain.com' => true,
        	'www.domain.com#' => true,
			'http://domain.com' => true,
			'http://www.domain.com' => true,
			'http://www.sub.domain.com' => true,
			'https://domain.com/' => true,
			'www.domain.com:8080' => true,
			'www.domain.com/dir/index.php' => true,
			'domain.com/dir/index.php?' => false,	// ?
			'domain.com/dir/index.php?id=' => true,
			'domain.com/dir/index.php?id=4' => true,
			'domain.com/dir/index.php?id=4#anchor' => true,
        	'domaincom' => false,
        	'www.domaincom' => false,
			'http:domain.com' => false,
			'www.doma in.com' => false,
			'www.doma?in.com' => false,
			'www.doma@in.com' => false,
        );
        $V= $this->BuildValidator();
        foreach($Tests as $k=>$v) {
            $Res= $V->Validate($k, 'URL', $v);
            $this->assertEqual($Res, $v, '["'.$k.'" = '.var_export($v,true).'] %s');
        }
    }


    public function TestDate() {

        $Tests= array(
            '' => false,
        	'24.5.2001' => true,
            '5/24/2001' => true,
            '24-5-2001' => true,
            '1 Jan 2001' => true,
            '2001/5/24' => true,
            '20010524' => true,
            '1 1 2001' => false,
            '1 Jan 2001' => true,
            '0.0.2001' => true,  // wtf ?????
            '32.1.2001' => false,
            '1.1.20001' => false,
        );
        $V= $this->BuildValidator();
        foreach($Tests as $k=>$v) {
            $Res= $V->Validate($k, 'Date');
            $this->assertEqual($Res, $v, '["'.$k.'" = '.var_export($v,true).'] %s');
        }
    }


    public function TestRequired() {

        $Tests= array(
            array(false, ''),
            array(true, ' '),
            array(true, 'A'),
            array(false, array()),
            array(true, array(array())),
        );
        $V= $this->BuildValidator();
        foreach($Tests as $Test) {
            $Res= $V->Validate($Test[1], 'Required');
            $this->assertEqual($Res, $Test[0], '["'.var_export($Test[1],true).'" = '.var_export($Test[0],true).'] %s');
        }
    }


    public function TestIPv4() {

        $Tests= array(
            '' => false,
        	'1.2.3.4' => true,
            '.1.2.3.4' => false,
            'a.2.3.4' => false,
            '1:2:3:4' => false,
            '333.2.3.4' => false,
            '1.2.3' => false,
            '1.2.3.4.5' => false,
        );
        $V= $this->BuildValidator();
        foreach($Tests as $k=>$v) {
            $Res= $V->Validate($k, 'IPv4');
            $this->assertEqual($Res, $v, '["'.$k.'" = '.var_export($v,true).'] %s');
        }
    }

    public function TestIP() {

        $Tests= array(
            '' => false,
        	'1.2.3.4' => true,
            'a.2.3.4' => false,
            '1:2:3:4' => false,
            '333.2.3.4' => false,
            '1.2.3' => false,
            '1.2.3.4.5' => false,
            'fdc6:c46b:bb8f:7d4c:0000:8a2e:0370:7334' => true,
            'fe80:0000:0000:0000:0202:b3ff:fe1e:8329' => true,
            'fe80::150:03cf:caaa:9876'=> true,
            '0:0:0:0:0:0:0:0'=> true,
            '::' => true,
            '1::' => true,
            '::1' => true,
            '1::1' => true,
            '::1.1.1.1'=> true,
            '1:1::1:1:1:1:1:1'=> false,
            'localhost'=> true,
        );
        $V= $this->BuildValidator();
        foreach($Tests as $k=>$v) {
            $Res= $V->Validate($k, 'IP');
            $this->assertEqual($Res, $v, '["'.$k.'" = '.var_export($v,true).'] %s');
        }
    }


    public function TestAlpha() {

        $Tests= array(
            ''     => true,
            '1'      => false,
            'as3dd'    => false,
            'as dd'      => false,
			'ΑβγδÜberГджз' => true,
			"A\n\t4"     => false,
        );
        $V= $this->BuildValidator();
        foreach($Tests as $k=>$v) {
            $Res= $V->Validate($k, 'Alpha');
            $this->assertEqual($Res, $v, '["'.$k.'" = '.var_export($v,true).'] %s');
        }

        //$x='1'; $R= $V->ValidateAll($x, 'Alpha'); echo var_dump($R);

        //$T= preg_match('/^\pL++$/uD', '1'); echo var_dump($T);
    }


    public function TestAlNum() {

        $Tests= array(
            '' => true,
            'as3dd'  => true,
			'Αβγδ4Über7Гджз' => true,
			"A\n\t4" => false,
        );
        $V= $this->BuildValidator();
        foreach($Tests as $k=>$v) {
            $Res= $V->Validate($k, 'Alnum');
            $this->assertEqual($Res, $v, '["'.$k.'" = '.var_export($v,true).'] %s');
        }
    }


    public function TestInteger() {

        $Tests= array(
            '' => false,
            '0' => true,
            '4 ' => true,
			'7д'  => false,
			"\n4" => true,
			"4\n" => true,
			'4,4' => false,
			'4-4' => false,
			'4.4' => false,
			'0x4' => false,
        );
        $V= $this->BuildValidator();
        foreach($Tests as $k=>$v) {
            $Res= $V->Validate($k, 'Integer');
            $this->assertEqual($Res, $v, '["'.$k.'" = '.var_export($v,true).'] %s');
        }
    }


    public function TestFloat() {

        $Tests= array(
            '' => false,
            '0' => true,
            '4 ' => true,
			'7д'  => false,
			"\n4" => true,
			"4\n" => true,
			'4,4' => false,
			'4-4' => false,
			'4.4' => true,
			'0x4' => false,
			'.01' => true,
			'.01.2' => false,
        );
        $V= $this->BuildValidator();
        foreach($Tests as $k=>$v) {
            $Res= $V->Validate($k, 'Float');
            $this->assertEqual($Res, $v, '["'.$k.'" = '.var_export($v,true).'] %s');
        }
    }


    public function TestCreditCard() {

        $Tests= array(
            '123456789012' => false,                  '323456789012341:AmExpress' =>false,
            '12345678901237' => true,                 '343456789012341:AmExpress' =>true,
            '12345678901234567890' => false,          '373456789012344:AmExpress' => true,
                                                      '3234567890123440:AmExpress' => false,
            '12345678901234:Diners' => false,
            '36345678901239:Diners' => true,          '12345678901234:JCB' => false,
            '55345678901235:Diners' => true,          '12345678901234567:JCB' => false,
            '30345678901235:Diners' => true,          '1234567890123456:JCB' => false,
            '303456789012345:Diners' => false,        '3234567890123458:JCB' => true,
            '3034567890123450:Diners' => true,        '1801567890123456:JCB' => false,
                                                      '1800567890123456:JCB' => true,
            '6511567890123455:Discover' => true,      '2130567890123456:JCB' => false,
            '6010567890123456:Discover' => false,     '2131567890123454:JCB' => true,
            '6011567890123450:Discover' => true,
            '60115678901234500:Discover' => false,    '123456789012345:Mastercard' => false,
            '6610567890123456:Discover' => false,     '12345678901234567:Mastercard' => false,
                                                      '1234567890123456:Mastercard' => false,
            '12345678901234567:Maestro' => false,     '5034567890123456:Mastercard' => false,
            '5021567890123456:Maestro' => false,      '5134567890123454:Mastercard' => true,
            '5020567890123451:Maestro' => true,       '60115678901234500:Mastercard' => false,
            '5038567890123451:Maestro' => true,
            '6305567890123456:Maestro' => false,      '12345678901234:Visa' => false,
            '6304567890123456:Maestro' => true,       '1234567890123:Visa' => false,
            '6759567890123456:Maestro' => true,       '4234567890125:Visa' => true,
            '675956789012345675:Maestro' => true,     '4234567890123456:Visa' => true,
        );
        $V= $this->BuildValidator();
        foreach($Tests as $k=>$v) {
            list($num,$type)= explode(':', $k.':');
            $Res= $V->Validate($num, 'CreditCard', $type);
            $this->assertEqual($Res, $v, '["'.$k.'" = '.var_export($v,true).'] %s');
        }
    }


    public function TestDecimal() {

        $Tests= array(
            array('',    '4.2', false),
            array('0',   '4.2', true),
            array('1',   '4.2', true),
			array('a',    '4.2', false),
			array('1234', '4.2', true),
			array('1234',  '4',  true),
			array('12345', '4.2', false),
			array('12.34', '4.2', true),
			array('1.2345','5.4', true),
			array('1.234', '8.2', false),
			array('.1234', '4.2', false),
        );
        $V= $this->BuildValidator();
        foreach($Tests as $Test) {
            $Res= $V->Validate($Test[0], 'Decimal', $Test[1]);
            $this->assertEqual($Res, $Test[2], '["'.$Test[0].'":"'.$Test[1].'" = '.var_export($Test[2],true).'] %s');
        }
    }


    public function TestDigits() {

        $Tests= array(
            '' => false,
            ' ' => false,
            '.' => false,
			'a' => false,
			'0' => true,
			'1234567m890' => false,
			'123456789012345678901234567890123456789012345678901234567890123456789012345678' => true,
			'1234.2' => false,
        );
        $V= $this->BuildValidator();
        foreach($Tests as $k=>$v) {
            $Res= $V->Validate($k, 'Digits');
            $this->assertEqual($Res, $v, '["'.$k.'" = '.var_export($v,true).'] %s');
        }
    }


    public function TestFunc() {

        $Tests = array(
            array(1.2, 'is_float', true),
            array(array(1), 'is_array', true),    // validate array types
            array('', 'is_array', false),
            array('c', 'Accent\\AccentCore\\Filter\\Validator::Validator_Alpha', true)
        );
        $V= $this->BuildValidator();
        foreach ($Tests as $Test) {
            $Res= $V->Validate($Test[0], 'Func', $Test[1]);
        	$this->assertEqual($Res, $Test[2], '['.var_export($Test[0],true)."] %s");
        }
    }


    public function TestSkipIf() {

        $FormValues= array(
            'UserType'=> 'person',
            'Name'=> 'me',
            'Ages'=> '12',
            'ParentName'=> '',
        );
        $Tests = array(
            array('', 'SkipIf:UserType:Equal:firm|SkipIf:Ages:Min:18|Required', array('Required')),
            array('', 'SkipIf:UserType:Equal:person|SkipIf:Ages:Min:18|Required', array()),
            array('', 'SkipIf:UserType:Equal:firm|SkipIf:Ages:Min:12|Required', array()),
            array('Nik', 'SkipIf:UserType:Equal:firm|SkipIf:Ages:Min:18|Required', array()),
        );
        $V= $this->BuildValidator();
        foreach ($Tests as $Test) {
            $FormValues['ParentName']= $Test[0];
            $Res= $V->ValidateAll($FormValues['ParentName'], $Test[1], $FormValues);
        	$this->assertEqual($Res, $Test[2], '["'.$Test[1].'" = '.var_export($Res,true)."] %s");
        }
    }


    public function TestEOF() {

        $Tests = array(
            array('', 'Required|LenMin:4', array('Required','LenMin')),
            array('', 'Required|EOF|LenMin:4', array('Required')),
            array('', 'EOF|Required|LenMin:4', array('Required','LenMin')),
            array('', 'Required|LenMin:4|EOF', array('Required','LenMin')),
            array('', 'Required|LenMin:4|EOF|URL', array('Required','LenMin')),
            array('x', 'Required|LenMin:4|EOF|URL', array('LenMin')),
            array('abcd', 'Required|LenMin:4|EOF|URL', array('URL')),
        );
        $V= $this->BuildValidator();
        foreach ($Tests as $Test) {
            $Res= $V->ValidateAll($Test[0], $Test[1]);
        	$this->assertEqual($Res, $Test[2], '["'.$Test[0].'":"'.$Test[1].'" = '.var_export($Res,true)."] %s");
        }
    }


    public function TestSameInput() {

        $FormValues= array(
            'Username'=> 'me',
            'Password'=> '',
            'PassAgain'=> '',
        );
        $Tests = array(
            array('','', 'Required|SameInput:Password', array('Required')),
            array('x','', 'Required|SameInput:Password', array('Required','SameInput')),
            array('x','x', 'Required|SameInput:Password', array()),
            array('x1','x2', 'Required|SameInput:Password', array('SameInput')),
        );
        $V= $this->BuildValidator();
        foreach ($Tests as $Test) {
            $FormValues['Password']= $Test[0];
            $FormValues['PassAgain']= $Test[1];
            $Res= $V->ValidateAll($FormValues['PassAgain'], $Test[2], $FormValues);
        	$this->assertEqual($Res, $Test[3], '["'.$Test[0].'":"'.$Test[1].'" = '.var_export($Res,true)."] %s");
        }
    }



}


