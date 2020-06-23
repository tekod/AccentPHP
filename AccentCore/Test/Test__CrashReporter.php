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
    }


    protected function InvokeCrashCall($Name) {

        // call crashing script in isolated instance
        $URL= $this->BuildTestURL(array(
            'Act'=> 'Forward',
            'Target'=> 'AccentCore.Test.Test__CrashReporter::Crash'.$Name,
        ));
        $Result= file_get_contents($URL);  // actual result is not important, we need log file
        //d($Result, "InvokeCrashCall: $URL");
    }



    // TESTS:

    public function TestShowStackPoint() {

        // clear previous tests
        @unlink($this->LogFile);

        // verify that logfile is cleared
        $this->assertFalse(is_file($this->LogFile));

        // call crashing script "CrashSimple"
        $this->InvokeCrashCall('Simple');

        // verify that logfile now exist
        $this->assertTrue(is_file($this->LogFile));

        // verify content of logfile
        $Dump= file_get_contents($this->LogFile);
        // if PHP change quoting errors in future versions this should make it irrelevant
        $Dump= str_replace(array('"',"'"), ' ', $Dump);
        // "-Simple-" is quite unique word
        $this->assertTrue(strpos($Dump, ' -Simple- ') !== false);
    }


    public function ForwardTest_CrashSimple() {

        // start CrashReporter monitoring
        $CR= new CrashReporter(array(
            'Writer'=> 'LogFile:'.$this->LogFile,
        ));

        // cause fatal error
        require_once '-Simple-';
    }


    public function TestBuiltInWriters() {

        @unlink($this->LogFile);

        // call crashing script "CrashBuiltInWriters"
        $this->InvokeCrashCall('BuiltInWriters');

        // get log and find unique words
        $Dump= str_replace(array('"',"'"), ' ', file_get_contents($this->LogFile));

        // content from WriteToEmail
        $this->assertTrue(strpos($Dump, "address@my.server") !== false);
        $this->assertTrue(strpos($Dump, "crashreporter@localhost") !== false);

        // content from WriteToURL
        $this->assertTrue(strpos($Dump, "::::URL-Receiver::::") !== false);
    }


    public function ForwardTest_CrashBuiltInWriters() {

        // URL for WriteToURL writer
        $URL= $this->BuildTestURL(array('Act'=>'Forward',  'Target'=>'AccentCore.Test.Test__CrashReporter::CrashUrlReceiver'));

        // start CrashReporter monitoring
        $CR= new CrashReporter(array(
            'Writer'=> array(
                'Email:address@my.server',
                'URL:'.$URL,
                'LogFile:'.$this->LogFile,
            ),
            'Testing'=> $this->LogFile,
        ));

        // cause fatal error
        require_once '-=123=-';
    }


    public function ForwardTest_CrashUrlReceiver() {

        // store marker into logfile as confirmation that URL is receive crash-repoprt
        file_put_contents($this->LogFile, '::::URL-Receiver::::', FILE_APPEND);
    }


    public function TestAddCustomWriter() {

        $Log3= __DIR__.'/tmp/crash3.log.php';
        @unlink($this->LogFile);
        @unlink($Log3);

        // call crashing script "CrashCustomWriters"
        $this->InvokeCrashCall('CustomWriters');

        // get log and find unique words
        $Dump= str_replace(array('"',"'"), ' ', file_get_contents($this->LogFile));
        $this->assertTrue(strpos($Dump, '-=CustomWriters=-') !== false);
        $this->assertTrue(strpos($Dump, '+-+-+-+-+-+-+-+-+-+-') !== false);
        // check another log file
        $Dump= str_replace(array('"',"'"), ' ', file_get_contents($Log3));
        $this->assertTrue(strpos($Dump, '-=CustomWriters=-') !== false);
        $this->assertTrue(strpos($Dump, '____________________') !== false);
    }


    public function ForwardTest_CrashCustomWriters() {

        // start CrashReporter monitoring
        $CR= new CrashReporter();

        // add simple custom writer
        $CR->AddWriter(array(__NAMESPACE__.'\MyCustomWriter','WriteMyLog'));

        // add writer with configuration
        $Log3= __DIR__.'/tmp/crash3.log.php';
        $Conf= array(
            'Path'=> $Log3,
            'Separator'=> '_____________________________',
            'CrashReporter'=> $CR,
        );
        $CR->AddWriter(array(__NAMESPACE__.'\MyCustomWriter', 'WriteMyLog3', $Conf));

        // cause fatal error
        require_once '-=CustomWriters=-';
    }


    public function TestCollectorCallback() {

        @unlink($this->LogFile);

        // call crashing script "CrashCollectorCallback"
        $this->InvokeCrashCall('CollectorCallback');

        // get log and find unique words
        $Dump= str_replace(array('"',"'"), ' ', file_get_contents($this->LogFile));
        $this->assertTrue(strpos($Dump, "\n\n[MyCustomKey] MyCustomValue") !== false);
    }


    public function ForwardTest_CrashCollectorCallback() {

        // start CrashReporter monitoring
        $CR= new CrashReporter(array(
            'CollectorCallback'=> array($this, 'MyCollectorCallback'),
            'Writer'=> 'LogFile:'.$this->LogFile,
        ));

        // cause fatal error
        require_once '-=ABC=-';
    }


    public function MyCollectorCallback($Opts) {

        return array('MyCustomKey'=> 'MyCustomValue');
    }


    public function TestEvents() {

        @unlink($this->LogFile);

        // call crashing script "CrashWithEvents"
        $this->InvokeCrashCall('WithEvents');

        // get log and find unique words
        $Dump= str_replace(array('"',"'"), ' ', file_get_contents($this->LogFile));

        // content from event collector
        $this->assertTrue(strpos($Dump, "[InfoFromEvent] Value") !== false);
        // content from event writer
        $this->assertTrue(strpos($Dump, "MyEventWriter!!!!") !== false);
    }


    public function ForwardTest_CrashWithEvents() {

        $EventService= new \Accent\AccentCore\Event\EventService();

        $EventService->AttachListener('CrashReporterCollector', array($this, 'EventCollector'));
        $EventService->AttachListener('CrashReporterWriter', array($this, 'EventWriter'));

        // start CrashReporter monitoring
        $CR= new CrashReporter(array(
            'Services'=> array(
                'Event'=> $EventService,
            ),
        ));  // instantiated without any writer

        // cause fatal error
        require_once '-=()=-';
    }


    public function EventCollector($Event) {
        // append new key to event data
        $Event->InfoFromEvent= 'Value';
    }


    public function EventWriter($Event) {
        // get values
        $Data= $Event->CollectedData;
        // use helper to format dump
        $Dump= $Event->CrashReporter->RenderPlainData($Data);
        // just write it with marker to logfile
        file_put_contents($this->LogFile, "MyEventWriter!!!!!:\n$Dump", FILE_APPEND);
    }
}



/**
 * Custom writers in separate call....
 */
class MyCustomWriter {


    public static function WriteMyLog($Data) {
        // do similar job like WriteToLogFile but with customized separator
        $LogFile= __DIR__.'/tmp/crash.log.php';
        $Dump= "\n\n-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-";
        $Dump .= "\n\r".var_export($Data, true);
        file_put_contents($LogFile, $Dump, FILE_APPEND);
    }


    public static function WriteMyLog3($Conf, $Data) {
        // use configuration to shape log entry
        $Dump= "\n\n".$Conf['Separator'];
        // reuse formatter from caller
        $Dump .= "\n\r".$Conf['CrashReporter']->RenderPlainData($Data);
        // use configuration to make storage location dynamic
        file_put_contents($Conf['Path'], $Dump, FILE_APPEND);
    }
}

