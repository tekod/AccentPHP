<?php namespace Accent\AccentCore\Test;

use Accent\Test\AccentTestCase;
use Accent\AccentCore\Debug\Debug;


/**
 * Testing localization package
 */

class Test__Debug extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Debug / Debug test';

    // title of testing group
    const TEST_GROUP= 'AccentCore';


    protected function Build($NewOptions=array()) {

        return new Debug($NewOptions + array(
        ));
    }


    // TESTS:

    public function TestShowStackPoint() {

        $D= $this->Build();
        $Result= $D->ShowStackPoint(0);
        $Exploded= explode('/', str_replace('\\','/',$Result));
        $Expected= 'Test__Debug.php on line (33)';
        $this->assertEqual(end($Exploded), $Expected, 'Difference: '.end($Exploded).' - '.$Expected);
    }


    public function TestShowStack() {

        $D= $this->Build();
        $Result= $D->ShowStack(false);
        $Expected= 'SimpleInvoker->invoke()';
        $this->assertEqual(trim($Result[2][1]), $Expected);
    }


    public function TestIncludedFiles() {

        $D= $this->Build();
        $List= $D->GetIncludedFiles();
        // look for current filename
        $BN= basename(__FILE__);
        foreach($List as $Item) {
            if (basename($Item['Path']) === $BN) {
                $this->assertTrue(true); // confirmed matching
                $this->assertTrue(filesize($Item['Path']) > 1000); // normal file
                return;
            }
        }
        $this->assertTrue(false); // matching not found
    }


    public function TestVarDump() {

        $D= $this->Build();

        // complex array
        $TestValue= array(
            array(),
            'Mario',
            3.141,
            1234567890,
            false,
            'SubArray'=> array(
                'SubSubArray'=> array(4,5,6),
                'Name'=> 'Ana',
            ),
            'SomeObject'=> $D, // object
        );
        $Dump= $D->VarDump($TestValue);

        // extract visible content after 'StdReportFileHeader' occurenceexplode('ProfilerFile',$Dump)
        $Result= html_entity_decode(strip_tags(substr($Dump, strrpos($Dump, 'ProfilerFile')+12)));
        $Expect= ' => boolean FALSE';
        $this->assertEqual(substr($Result,0,strlen($Expect)), $Expect);
    }


    public function TestLogging() {

        $D= $this->Build();
        $D->ProfilerStart(__DIR__.'/tmp/demo.log');
        $D->Mark('First');
        $MemoryOccupation= str_repeat(str_repeat('x',500),500); // 0.25Mb string
        $D->Mark('Second');
        $D->Mark("multiline:\nb\nc");
        $D->Mark('wrapping long line:'.str_repeat(' 1234567890',8));

        // load log file
        $Lines= $D->GetProfilerData(true);
        $Line= explode("\n", $Lines);
        $Item= explode("|", $Line[13]); // line with entry 'Second' should be on line 14
        $this->assertEqual(trim($Item[3]), 'Second');

        // load log buffer
        $LogLines= $D->GetProfilerData(false);
        $this->assertEqual($LogLines[1][3], 'Second');
    }


}


