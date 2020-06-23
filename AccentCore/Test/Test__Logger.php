<?php namespace Accent\AccentCore\Test;

use Accent\Test\AccentTestCase;
use Accent\AccentCore\Debug\Logger;


/**
 * Testing localization package
 */

class Test__Logger extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Debug / Logger test';

    // title of testing group
    const TEST_GROUP= 'AccentCore';


    public function Before($method) {
        parent::Before($method);
        @unlink(__DIR__.'/tmp/logger.txt');
    }

    public function After($method) {
        parent::After($method);
        @unlink(__DIR__.'/tmp/logger.txt');
    }

    // TESTS:

    public function TestNullLogger() {

        $L= new Logger(
            false
        );
        $L->Log('Test message.');
        $this->assertEqual(is_file(__DIR__.'/tmp/logger.txt'), false);
    }


    public function TestSimpleLogger() {

        $L= new Logger(
            __DIR__.'/tmp/logger.txt'
        );
        $L->Log('Test message.');
        $Dump= file_get_contents(__DIR__.'/tmp/logger.txt');
        $this->assertEqual(strpos($Dump, 'Test message') !== false, true);
    }


    public function TestUsingCaption() {

        $L= new Logger(
            __DIR__.'/tmp/logger.txt',
            'Some caption'
        );
        $L->Log('Test message.');
        $Dump= file_get_contents(__DIR__.'/tmp/logger.txt');
        $this->assertEqual(strpos($Dump, 'Some caption') === 0, true);
        $this->assertEqual(strpos($Dump, 'Test message') !== false, true);
    }


    public function TestUsingEnable() {

        $L= new Logger(
            __DIR__.'/tmp/logger.txt',
            '',
            false
        );
        $L->Log('Test message.');
        $this->assertEqual(is_file(__DIR__.'/tmp/logger.txt'), false);

        // now enable
        $L->Enable(true);
        $L->Log('Test message.');
        $Dump= file_get_contents(__DIR__.'/tmp/logger.txt');
        $this->assertEqual(strpos($Dump, 'Test message') !== false, true);
    }


    public function TestUsingOwerwrite() {

        // predefined content
        file_put_contents(__DIR__.'/tmp/logger.txt', 'ABCD');

        // build logger with "not overwrite" conf
        $L= new Logger(
            __DIR__.'/tmp/logger.txt',
            'Some caption',
            true,
            false
        );
        $L->Log('Test message.');
        $Dump= file_get_contents(__DIR__.'/tmp/logger.txt');
        $this->assertEqual(strpos($Dump, 'Some caption') !== false, false); // caption should not be included
        $this->assertEqual(strpos($Dump, 'Test message') !== false, true);
        $this->assertEqual(strpos($Dump, 'ABCD') !== false, true);
    }


    public function TestUsingSeparatorLine() {

        $Separator= "\n\n#####################";
        $L= new Logger(
            __DIR__.'/tmp/logger.txt',
            'Some caption',
            true,
            true,
            $Separator
        );
        $L->Log('Test message.');
        $Dump= file_get_contents(__DIR__.'/tmp/logger.txt');
        $this->assertEqual(strpos($Dump, 'Test message.'.$Separator) !== false, true);
    }


    public function TestExcludingTimestamps() {

        $L= new Logger(
            __DIR__.'/tmp/logger.txt',
            'Some caption',
            true,
            true,
            "\n----",
            false
        );
        $L->Log('Test message.');
        $Dump= file_get_contents(__DIR__.'/tmp/logger.txt');
        $this->assertEqual(strpos($Dump, date('Y-m-')) !== false, false);
    }
}


