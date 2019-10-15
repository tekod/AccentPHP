<?php namespace Accent\Log\Test;

/**
 * Testing Log service
 *
 * @TODO: test 'db' driver
 */

use Accent\Test\AccentTestCase;
use Accent\Log\Log;


class Test__Log extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Log service test';

    // title of testing group
    const TEST_GROUP= 'Log';

    // internal
    protected $LogFile;


    public function __construct() {

        // parent
        parent::__construct();

        $this->LogFile= __DIR__.'/tmp/test_1.log';

        date_default_timezone_set(ini_get('date.timezone'));
    }



    protected function Build($Options) {

        $DefOptions= array(
            'LoggerName'=> 'My logger',
            'Acquisitors'=> array(
                // name=> array(),
            ),
            'Writers'=> array(
                // name=> array(),
            ),
            'Services'=> array(
                'UTF'=> new \Accent\AccentCore\UTF\UTF,
            ),
        );
        return new Log($Options + $DefOptions);
    }


    // TESTS:


    public function TestFileWriter() {

        $L= $this->Build(array('Writers'=>array('File'=>array(
            'Path'=> $this->LogFile
        ))));
        $L->Log('Demo');
        $Dump= file_get_contents($this->LogFile);
        $this->assertTrue(strpos($Dump,'My logger.INFO: Demo []') !== false);
    }


    public function TestCleared() {
        // write to same file but with ClearOnStart option
        $L= $this->Build(array('Writers'=>array('File'=>array(
            'Path'=> $this->LogFile,
            'ClearOnStart'=> true,
        ))));
        $L->Log('XYZ');
        $Dump= file_get_contents($this->LogFile);
        $this->assertTrue(strpos($Dump,'My logger.INFO: Demo []') === false);
    }


    public function TestMinLevel() {

        unlink($this->LogFile);
        $L= $this->Build(array('Writers'=>array('File'=>array(
            'Path'=> $this->LogFile,
            'MinLevel'=> Log::WARNING,
        ))));
        $L->Log('AAA', Log::DEBUG);
        $L->Log('BBB', Log::NOTICE);
        $L->Log('CCC', Log::WARNING);
        $L->Log('DDD', Log::ERROR);
        $L->Log('EEE', Log::EMERGENCY);
        $Dump= file_get_contents($this->LogFile);
        $this->assertTrue(strpos($Dump,'AAA') === false);
        $this->assertTrue(strpos($Dump,'BBB') === false);
        $this->assertTrue(strpos($Dump,'CCC') !== false);
        $this->assertTrue(strpos($Dump,'DDD') !== false);
        $this->assertTrue(strpos($Dump,'EEE') !== false);
    }


    public function TestBuffered() {

        unlink($this->LogFile);
        $L= $this->Build(array('Writers'=>array('File'=>array(
            'Path'=> $this->LogFile,
            'Buffered'=> true,
        ))));
        $L->Log('-=|=-');
        $L->Log('-=|=-');
        $L->Log('-=|=-');
        $L->Log('-=|=-');
        $Dump= @file_get_contents($this->LogFile); // probably not created yet
        $this->assertTrue(count(explode('=|=',$Dump)) === 1);
        $L->Close();
        $Dump= file_get_contents($this->LogFile);
        $this->assertTrue(count(explode('=|=', $Dump)) === 5);

    }


    public function TestReplacements() {

        $L= $this->Build(array('Writers'=>array('File'=>array(
            'Path'=> $this->LogFile,
            'ClearOnStart'=> true,
        ))));
        $L->LogInfo('Visitor from IP:{IpAddr} sent query from "{PageName}" page.', array(
            'IpAddr'=> '1.2.3.4',
            'PageName'=> 'contact',
        ));
        $Dump= file_get_contents($this->LogFile);
        // all used Data keys must be removed from array, so log line must end with "[]"
        $this->assertTrue(strpos($Dump,'Visitor from IP:1.2.3.4 sent query from "contact" page. []') !== false);
    }


    //
    // test formatters
    //

    public function TestFormatterLine() {

        $L= $this->Build(array('Writers'=>array('File'=>array(
            'Path'=> $this->LogFile,
            'ClearOnStart'=> true,
            'Formatter'=> 'Line', // not changed
            'FormatTemplate'=> '{Time} [{Level}] {Msg} {Data}',
            'SeparationLine'=> "\n-------------------------------------",
        ))));
        $L->Log('XYZ');
        $L->Log('ABC');
        $Dump= file_get_contents($this->LogFile);
        $this->assertTrue(strpos($Dump,' [INFO] XYZ []') !== false);
    }


    public function TestFormatterJson() {

        $L= $this->Build(array('Writers'=>array('File'=>array(
            'Path'=> $this->LogFile,
            'ClearOnStart'=> true,
            'Formatter'=> 'Json',
        ))));
        $L->LogWarning('XYZ', array('a1','a2'));
        $L->LogInfo('ABC');
        $Dump= file_get_contents($this->LogFile);
        $this->assertTrue(strpos($Dump,',"Level":"INFO","Msg":"ABC",') !== false);
    }


    public function TestFormatterHtmlTable() {

        $L= $this->Build(array('Writers'=>array('File'=>array(
            'Path'=> $this->LogFile.'.html',
            'ClearOnStart'=> true,
            'Formatter'=> 'HtmlTable',
        ))));
        $HTML1= '<table border="1"><tr><th>Description:</th><td>"My CV"</td></tr>'
               .'<tr><th>File:</th><td>cv.pdf</td></tr>'
               .'<tr><th>Size:</th><td>42 kb</td></tr></table>';
        $L->LogInfo($HTML1, array('a1','a2'));
        $Dump= file_get_contents($this->LogFile);
        $this->assertTrue(strpos($Dump,',"Level":"INFO","Msg":"ABC",') !== false);
        unlink($this->LogFile.'.html'); // clear
    }


    public function TestFormatterFileTable() {

        $L= $this->Build(array('Writers'=>array('File'=>array(
            'Path'=> $this->LogFile,
            'ClearOnStart'=> true,
            'Formatter'=> 'FileTable',
            'Columns'=>array(
                array('   Time   ',STR_PAD_BOTH),
                array('     Level     ',STR_PAD_BOTH),
                'Message                           ',
                'CustomColumn',
                array('Other Data         ',STR_PAD_LEFT),
            ),
        ))));
        $L->LogWarning('XYZ', array('a1','a2'));
        $L->LogInfo('ABC');
        $Dump= file_get_contents($this->LogFile);
        $this->assertTrue(strpos($Dump,'|   My logger   |ABC ') !== false);
    }


    //
    // test Acquisitors
    //

    public function TestAcquisitorBacktrace() {

        $L= $this->Build(array(
            'Writers'=> array('File'=>array('Path'=> $this->LogFile,'ClearOnStart'=> true,)),
            'Acquisitors'=> array('BackTrace'=>array()),
        ));
        $L->Log('First');
        $L->LogError('Second');
        $Dump= file_get_contents($this->LogFile);
        $Dump= str_replace('\\','/',$Dump); // covert backslash to unix slash
        $Dump= str_replace('//','/',$Dump); // resolve double-slashes
        $this->assertTrue(strpos($Dump,'Second {"BackTrace":["@/Accent/Log/Log.php') !== false,
                'Found: '.$Dump);
    }


    public function TestAcquisitorMemory() {

        $L= $this->Build(array(
            'Writers'=> array('File'=>array('Path'=> $this->LogFile,'ClearOnStart'=> true)),
            'Acquisitors'=> array('Memory'=>array('RealUsage'=>false)),
        ));
        $L->Log('First');
        $L->LogError('Second');
        $Dump= file_get_contents($this->LogFile);
        $this->assertTrue(strpos($Dump,'ERROR: Second {"MemoryUsage":') !== false);
    }


    private function BuildRequestMockObject() {
        $RMO= new \Accent\Test\MockObject(array($this,'ErrorMessage'));
        $RMO->Mock_SetMethod('GetURL', '/test.php');
        $RMO->Mock_SetMethod('GetIP', '1.1.1.1');
        $RMO->Mock_SetMethod('GetMethod', 'GET');
        $RMO->Mock_SetMethod('GetContext', 'www.site.com');
        return $RMO;
    }


    public function TestAcquisitorRequest() {

        $L= $this->Build(array(
            'Request'=> $this->BuildRequestMockObject(), // thic will normaly inject by kernel
            'Writers'=> array('File'=>array('Path'=> $this->LogFile,'ClearOnStart'=> true)),
            'Acquisitors'=> array('Request'=>array()),
        ));
        $L->Log('First');
        $L->LogError('Second');
        $Dump= file_get_contents($this->LogFile);
        $this->assertTrue(strpos($Dump,'Second {"URL":"/test.php","IP":') !== false);
    }


    //
    // test other writers
    //

    public function TestWriterDb() {

        $UseMock= true;
        $DbService= $UseMock
            ? $this->BuildDbMockService()
            : $this->BuildDbRealService();
        $L= $this->Build(array(
            'Writers'=> array('DB'=>array()),
            'Services'=> array(
                'DB'=> $DbService,
            ),
        ));
        $L->Log('First');
        $L->LogError('Second');
        $R= $DbService->Query('log')->FetchAll();
        // adjust expectations: no way to predict exact creation time so just copy it
        $this->WriterDbExpectedValues[0]['created']= $R[0]['created'];
        $this->WriterDbExpectedValues[1]['created']= $R[1]['created'];
        // adjust expectations: Log class is unaccessible in compile time
        $this->WriterDbExpectedValues[0]['level']= Log::INFO;
        $this->WriterDbExpectedValues[1]['level']= Log::ERROR;
        // test
        $this->assertEqual($R, $this->WriterDbExpectedValues);
    }

    private $WriterDbExpectedValues= array(
        array('id'=>1,'level'=>70,'logger'=>'My logger','message'=>'First','created'=>0,'data'=>'a:0:{}'),
        array('id'=>2,'level'=>40,'logger'=>'My logger','message'=>'Second','created'=>0,'data'=>'a:0:{}'),
    );

    private function BuildDbMockService() {

        $DB= new \Accent\Test\MockObject(array($this,'ErrorMessage'));
        $DB->Mock_SetMethod('DateToSqlDatetime', '2015-05-24');
        $DB->Mock_SetMethod('Insert', $DB);
        $DB->Mock_SetMethod('Values', $DB);
        $DB->Mock_SetMethod('Execute', 1);
        $DB->Mock_SetMethod('Query', $DB);
        $DB->Mock_SetMethod('FetchAll', $this->WriterDbExpectedValues);
        return $DB;
    }

    private function BuildDbRealService() {

        $TableDefinition= array (
            'Columns'=> array(
                'id'=> 'serial',
                'level'=> 'int(11)',
                'logger'=> 'varchar(30)',
                'message'=> 'varchar(2000)',
                'created'=> 'datetime',
                'data'=> 'varchar(2000)',
            ),
            'Primary' => array('id'),
            'Engine' => 'MyISAM',
            'Charset' => 'utf8',
        );
        require_once('../DB/DB.php');
        require_once('../DB/Driver/AbstractDriver.php');
        require_once('../DB/Driver/MysqlDriver.php');
        $DB= new \Accent\DB\DB(array(
            'ConnectionParams'=> array(
                'DSN'=> 'mysql:host=localhost&port=3306&dbname=test',
                'Username'=> 'root',
                'Password'=> '',
            ),
            'Services'=> array(
                'Cache'=> false, // false = disable internal cache
            ),
        ));
        $DB->DropTable('log');
		$DB->CreateTable('log', $TableDefinition);
        return $DB;
    }


    public function TestWriterMail() {
        // how to test it?
        $L= $this->Build(array('Writers'=>array('Mail'=>array(
            'Buffered'=> true,
            'EmailTo'=> 'some_address@domain1234567890.com',
        ))));
        //$L->Log('First');
        //$L->Log('Second');
        $L->Close();
        // this writer cannot be asserted
    }



    public function TestWriterFileRotated() {

        // clear logs from old tests
        foreach(glob(__DIR__."/tmp/test_1-*") as $File) unlink($File);

        // make
        $L= $this->Build(array('Writers'=>array('FileRotated'=>array(
            'Path'=> $this->LogFile,
            'ClearOnStart'=> true,
            'Rotate'=> array(
                'MaxAge'=> 0,   // do not check age, test must be completed fast
                'MaxSize'=> 85,  // low value, to triger often rotation
                'MaxFiles'=> 3, // keep only 3 rotated files
        )))));
        // populate
        $L->Log('111');
        $L->Log('222');
        $L->Log('333');
        $L->Log('444');
        $L->Log('555');
        $L->Log('666');
        $YMD= date('Ymd');
        // old rotated file, must be deleted
        $F1= __DIR__."/tmp/test_1-{$YMD}.log";
        $this->assertFalse(is_file($F1));
        // old rotated file, must be deleted
        $F2= __DIR__."/tmp/test_1-{$YMD}_1.log";
        $this->assertFalse(is_file($F2));
        // must contains 333
        $F3= __DIR__."/tmp/test_1-{$YMD}_2.log";
        $this->assertTrue(strpos(file_get_contents($F3),'333') !== false);
        // must contains 444
        $F4= __DIR__."/tmp/test_1-{$YMD}_3.log";
        $this->assertTrue(strpos(file_get_contents($F4),'444') !== false);
        // newest rotated file, must contains 555
        $F5= __DIR__."/tmp/test_1-{$YMD}_4.log";
        $this->assertTrue(strpos(file_get_contents($F5),'555') !== false);
        // actual log file, must contains latest log record
        $this->assertTrue(strpos(file_get_contents($this->LogFile),'666') !== false);
        // clear logs
        foreach(glob(__DIR__."/tmp/test_1-*") as $File) unlink($File);
    }




    //
    // test multiple writers
    //

    public function TestMultipleWriters() {

        $LogFile2= substr($this->LogFile,0,-5).'2.log';
        $L= $this->Build(array('Writers'=>array(
            'One'=> array(
                'Class'=> 'File',
                'Path'=> $this->LogFile,
                'MinLevel'=> Log::INFO,
                'ClearOnStart'=> true
             ),
            'Two'=> array(
                'Class'=> 'File',
                'Path'=> $LogFile2,
                'MinLevel'=> Log::ALERT,
                'ClearOnStart'=> true
            )
        )));
        $Tests= array( // Message, Level, AppearInOne, AppearInTwo
            array('AAA', Log::DEBUG,    false,false),
            array('BBB', Log::NOTICE,   true, false),
            array('CCC', Log::WARNING,  true, false),
            array('DDD', Log::ERROR,    true, false),
            array('EEE', Log::EMERGENCY,true, true),
        );
        foreach ($Tests as $Test) { // call Log() for all tests
            $L->Log($Test[0], $Test[1]);
        }
        $Dump1= file_get_contents($this->LogFile);
        $Dump2= file_get_contents($LogFile2);
        foreach ($Tests as $Test) { // call Log() for all tests
            $this->assertEqual(strpos($Dump1,$Test[0]) !== false, $Test[2], "One($Test[0])");
            $this->assertEqual(strpos($Dump2,$Test[0]) !== false, $Test[3], "Two($Test[0])");
        }
        unlink($LogFile2);
    }


}


?>