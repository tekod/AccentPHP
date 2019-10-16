<?php namespace Accent\AccentCore\Test;

/**
 * Testing Accent\Debug\CrashReporter
 *
 * Tag: [TestModelForward] // allowing test-forward calls
 */

use Accent\Test\AccentTestCase;
use Accent\AccentCore\Debug\CrashReporter;


class Test__CrashReporter extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Debug / CrashReporter test';

    // title of testing group
    const TEST_GROUP= 'AccentCore';


    protected $LogFile;


    public function __construct() {

        // parent
        parent::__construct();

        $this->LogFile= __DIR__.'/tmp/crash.log.php';
        @unlink($this->LogFile);
    }



    // TESTS:

    public function TestShowStackPoint() {

        // verify that logfile is cleared
        $this->assertFalse(is_file($this->LogFile));

        // call crashing script
        $URL= $this->BuildTestURL(array(
            'Act'=> 'Forward',
            'Target'=> 'AccentCore.Test.Test__CrashReporter::Crash',  // this will call self::ForwardTest_Crash
        ));
        $Result= file_get_contents($URL);  // actual result is not important, we need log file

        // verify that logfile now exist
        $this->assertTrue(is_file($this->LogFile));

        // verify content of logfile
        $Dump= file_get_contents($this->LogFile);
        // if PHP change quoting errors in future versions this should make it irrelevant
        $Dump= str_replace(array('"',"'"), ' ', $Dump);
        // "retwr" is quite unique word
        $this->assertTrue(strpos($Dump, ' retwr ') !== false);
    }


    public function ForwardTest_Crash() {

        $CR= new CrashReporter(array(
            'Writer'=> 'LogFile:'.$this->LogFile,
        ));

        require 'retwr';        // this will cause fatal error
    }

}


?>