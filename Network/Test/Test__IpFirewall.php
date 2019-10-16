<?php namespace Accent\Network\Test;

/**
 * Testing Accent\Network\IpRange
 */

use Accent\Test\AccentTestCase;
use Accent\Network\IpFirewall;


class Test__IpFirewall extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Network / IpFirewall test';

    // title of testing group
    const TEST_GROUP= 'Network';


    /**
     * Instantiate IpRange object.
     *
     * @param array $Options
     * @return \Accent\Network\IpFirewall
     */
    protected function BuildComponent($Options=array()) {

        return new IpFirewall($Options);
    }


    public function TestEmpty() {

        $FW= $this->BuildComponent();
        // check normal IP
        $this->assertNull($FW->Check('10.10.9.0'));   // cannot be found in any list
        // check invalid IP
        $this->assertNull($FW->Check('xyz:0'));  // invalid IPs also cannot be found in any list
    }


    public function TestNormalUsage() {

        $FW= $this->BuildComponent(array(
            'WhiteList'=> array('192.168.1.4'),
            'BlackList'=> array('192.168.*.*'),
        ));
        $this->assertNull($FW->Check('1.1.1.1'));
        $this->assertFalse($FW->Check('192.168.1.1'));
        $this->assertTrue($FW->Check('192.168.1.4'));
        $this->assertFalse($FW->Check('192.168.1.5'));
    }


    public function TestExportImport() {

        $FW= $this->BuildComponent();
        // manualy add rules
        $FW->GetWhiteList()->Append('192.168.0.1');
        $FW->GetBlackList()->Append('192.168.*.*');
        // save rules
        $Cache= $FW->ExportCompiledRules();
        // destroy first and build second object
        $FW= null;
        $FW2= $this->BuildComponent();
        $FW2->ImportCompiledRules($Cache);
        // validate rules existance
        $this->assertTrue($FW2->Check('192.168.0.1'));
    }

}


?>