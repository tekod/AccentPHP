<?php namespace Accent\Network\Test;

/**
 * Testing Accent\Network\IpRange
 */

use Accent\Test\AccentTestCase;
use Accent\Network\IpRange;


class Test__IpRange extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Network / IpRange test';

    // title of testing group
    const TEST_GROUP= 'Network';


    /**
     * Instantiate IpRange object.
     *
     * @param array $List
     * @return \Accent\Network\IpRange
     */
    protected function BuildComponent($List=array()) {

        return new IpRange($List);
    }


    public function TestEmpty() {

        $IPR= $this->BuildComponent();
        // check normal IP
        $this->assertFalse($IPR->InRange('10.10.9.0'));
        // check invalid IP
        $this->assertFalse($IPR->InRange('xyz:0'));
    }


    public function TestWildcard() {

        $IPR= $this->BuildComponent();
        // IPv4
        $IPR->Append('10.10.10.*');
        $this->assertFalse($IPR->InRange('10.9.0.0'));  // bellow range
        $this->assertFalse($IPR->InRange('10.10.9.0'));
        $this->assertTrue($IPR->InRange('10.10.10.0'));  // in range
        $this->assertTrue($IPR->InRange('10.10.10.255'));
        $this->assertFalse($IPR->InRange('10.10.11.0'));  // above range
        $this->assertFalse($IPR->InRange('10101010'));  // test invalid IP
        // additional rules
        $IPR->Append('10.11.*');
        $IPR->Append('12.*');
        $this->assertTrue($IPR->InRange('10.11.0.0'));
        $this->assertTrue($IPR->InRange('10.11.255.255'));
        $this->assertTrue($IPR->InRange('10.10.10.1'));  // first rule is still in place
        $this->assertTrue($IPR->InRange('12.0.0.0'));
        $this->assertTrue($IPR->InRange('12.255.255.255'));
        $this->assertFalse($IPR->InRange('13.0.0.0'));  // above all ranges
        // IPv6
        $IPR->Append('fe80:1:2:3:4:*:*:*');
        $this->assertFalse($IPR->InRange('fe80:1:2:3:3::'));  // bellow range
        $this->assertTrue($IPR->InRange('fe80:1:2:3:4::'));
        $this->assertFalse($IPR->InRange('fe80:1:2:3:5::'));  // above range
        $this->assertFalse($IPR->InRange('ffffff:ffffff::'));  // test invalid IP
    }


    public function TestRanges() {

        $IPR= $this->BuildComponent();
        // IPv4
        $IPR->Append('10.10.10.0-10.10.10.5');
        $this->assertFalse($IPR->InRange('10.10.9.0'));  // bellow range
        $this->assertFalse($IPR->InRange('10.10.9.255'));  // bellow range
        $this->assertTrue($IPR->InRange('10.10.10.0'));
        $this->assertTrue($IPR->InRange('10.10.10.5'));
        $this->assertFalse($IPR->InRange('10.10.10.6')); // above range
        $this->assertFalse($IPR->InRange('10.10.11.0')); // above range
        // additional rules
        $IPR->Append('11.11.11.0 - 11.11.11.15');           // intentionaly spaces
        $IPR->Append('11.11.11.10-11.11.11.50');            // intentionaly overlapping
        $this->assertFalse($IPR->InRange('10.10.9.0'));     // bellow all ranges
        $this->assertFalse($IPR->InRange('11.11.10.255'));  // bellow range
        $this->assertTrue($IPR->InRange('11.11.11.0'));
        $this->assertTrue($IPR->InRange('11.11.11.11'));    // found in both rules
        $this->assertTrue($IPR->InRange('11.11.11.46'));    // found in last rule
        $this->assertFalse($IPR->InRange('11.11.11.51'));    // above all ranges
        // IPv6
        $IPR->Append('fe80:1:2:3::0-fe80:1:2:10::ffff');
        $this->assertFalse($IPR->InRange('fe80:1:2:2::'));  // bellow range
        $this->assertTrue($IPR->InRange('fe80:1:2:3::0'));
        $this->assertTrue($IPR->InRange('fe80:1:2:10::ffff'));
        $this->assertFalse($IPR->InRange('fe80:1:2:11::')); // above range
    }


    public function TestMask() {

        $IPR= $this->BuildComponent();
        // IPv4
        $IPR->Append('10.10.10.0/24');
        $this->assertFalse($IPR->InRange('10.10.9.255'));
        $this->assertTrue($IPR->InRange('10.10.10.0'));
        $this->assertTrue($IPR->InRange('10.10.10.255'));
        $this->assertFalse($IPR->InRange('10.10.11.0'));
        // IPv6
        $IPR->Append('fe80:1:2:3::0/64');
        $this->assertFalse($IPR->InRange('fe80:1:2:2::0'));
        $this->assertTrue($IPR->InRange('fe80:1:2:3::0'));
        $this->assertTrue($IPR->InRange('fe80:1:2:3:ffff:ffff:ffff:ffff'));
        $this->assertFalse($IPR->InRange('fe80:1:2:4::0'));
    }


    public function TestExactIP() {

        $IPR= $this->BuildComponent();
        // IPv4
        $IPR->Append('192.168.0.1');
        $IPR->Append('192.168.1.1');
        $this->assertFalse($IPR->InRange('192.168.0.0'));
        $this->assertTrue($IPR->InRange('192.168.0.1'));
        $this->assertTrue($IPR->InRange('192.168.1.1'));
        $this->assertFalse($IPR->InRange('192.168.1.2'));
        // IPv6
        $IPR->Append('fE80:1:2:3::0');                      // intentionaly capitalised "E", must be case insensitve
        $this->assertTrue($IPR->InRange('Fe80:1:2:3::0'));
        $this->assertTrue($IPR->InRange('fe80:1:2:3:0:0:0:0'));
        $this->assertTrue($IPR->InRange('fe80:1:2:3:0:0:0000:0'));
        $this->assertFalse($IPR->InRange('fe80:1:2:4::0'));
    }


    public function TestCompileAllRules() {

        $IPR = $this->BuildComponent();
        // test validation
        $IPR->Append('192.168.0.1');        // valid
        $IPR->Append('192.168.a1.1');       // error: non-digit
        $IPR->Append('-1.1.1.1');           // error: missing first part of range
        $IPR->Append('1.1.1.1-');           // error: missing second part of range
        $IPR->Append('1.1.1.1/');           // error: missing mask subnet
        $IPR->Append('/24');                // error: missing mask base IP
        $IPR->Append('::1');                // valid
        $IPR->Append('ffff::1');            // valid
        $IPR->Append('f:1:2:3:4');          // error: missing segments
        $Errors = $IPR->CompileAllRules();
        $this->assertEqual($Errors, array(1, 2, 3, 4, 5, 8));
    }


    public function TestExportImport() {

        // shortcut, inject rules into constructor, it is Collection
        $IPR1= new IpRange(array(
            '192.168.*.*',
            '::1'
        ));
        $IPR1->CompileAllRules();
        // build second object
        $IPR2= new IpRange();
        $IPR2->Import($IPR1->ToArray());
        // validate rules existance
        $this->assertTrue($IPR2->InRange('::1'));
    }

}


?>