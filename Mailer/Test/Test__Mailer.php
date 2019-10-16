<?php namespace Accent\Mailer\Test;

use Accent\Test\AccentTestCase;
use Accent\Mailer\Mailer;


/**
 * Testing Mailer package and its drivers
 */

class Test__Mailer extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Mailer service test';

    // title of testing group
    const TEST_GROUP= 'Mailer';


    // TESTS:

    public function TestNativeMailerDriver() {

        $LogFile = __DIR__.'/tmp/log.txt';

        $Service = new Mailer(array(
            'Driver'    => 'NativeMailer',
            'LogFile'   => $LogFile,
            'DebugMode' => true,
        ));

        $Message = $Service->Create()
                           ->Subject('Testing subject')
                           ->From('me@abc.com')
                           ->To('to1@abc.com')
                           ->To('to2@abc.com')
                           ->CC('cc1@abc.com')
                           ->CC('cc2@abc.com')
                           ->BCC('bcc1@abc.com')
                           ->BCC('bcc2@abc.com')
                           ->ReplyTo('no-reply1@abc.com')
                           ->ReturnPath('no-reply2@abc.com')
                           ->BodyHTML('<h1>Hello</h1> world')
                           ->BodyPlain(true);
        $Success = $Message->Send();

        // check is executed successfully
        $this->assertTrue($Success);

        // get log file (remove CRLF) and compare expectations
        $Dump = str_replace(array("\r", "\n"), '', file_get_contents($LogFile));   
        $this->AssertTrue(strpos($Dump, 'to1@abc.com, to2@abc.com') !== false);
        $this->AssertTrue(strpos($Dump, 'cc1@abc.com, cc2@abc.com') !== false);
        $this->AssertTrue(strpos($Dump, 'bcc1@abc.com, bcc2@abc.com') !== false);
        $this->AssertTrue(strpos($Dump, 'Reply-To: no-reply1@abc.com') !== false);
        $this->AssertTrue(strpos($Dump, 'Return-Path: no-reply2@abc.com') !== false);
        $this->AssertTrue(strpos($Dump, '<h1>Hello</h1> world') !== false);
        $this->AssertTrue(strpos($Dump, 'HELLO world') !== false);
        $this->AssertTrue($Message->GetFails() === array());

        // test attachments, it is not isolated in separate test because it's implementation varies in drivers
        // so it must be tested separately for each driver
        $Message= $Service->Create()
                ->From('me@abc.com')
                ->To('test@xyz.com')
                ->Subject('Invoice')
                ->Body('Your invoice is attached.')
                ->Attachment(__DIR__.'/tmp/Attachment.txt')
                ->AttachData('abcd', 'sample.txt');
        file_put_contents(__DIR__.'/tmp/Attachment.txt', 'qwertz');
        $Success= $Message->Send();
        unlink(__DIR__.'/tmp/Attachment.txt');
        $this->assertTrue($Success);
        $Dump= str_replace(array("\r","\n"), '', file_get_contents($LogFile));
        $this->AssertTrue(strpos($Dump, base64_encode("qwertz")) !== false);   // attachment
        $this->AssertTrue(strpos($Dump, base64_encode("abcd")) !== false);   // attachment
    }


    public function T_stReplacers() {

        $LogFile= __DIR__.'/tmp/log.txt';

        $Service= new Mailer(array(
            'Driver'    => 'NativeMailer',
            'LogFile'   => $LogFile,
            'DebugMode' => true,
        ));

        // test replacers
        $To1= 'nikola.tesla@gmail.com';   // simple address
        $To2= 'Bell corp, CEO<graham.bell@bell.com>'; // named address, with illegal char ","
        $Message= $Service->Create()
                          ->From('me@abc.com')
                          ->To($To1)
                          ->To($To2)
                          ->Subject('Newsletter #{{Num}}')
                          ->Body('Download our newsletter from <a href="{{Link}}">here</a>')
                          ->Replacer('Num', '41')
                          ->Replacer('Link', 'http://www.site.com?dl=1234');
        $Success= $Message->Send();
        $this->assertTrue($Success);
        $Dump= str_replace(array("\r","\n"), '', file_get_contents($LogFile));
        $this->AssertTrue(strpos($Dump, "nikola.tesla@gmail.com, Bell corp CEO<graham.bell@bell.com>") !== false); // illegal "," removed
        $this->AssertTrue(strpos($Dump, "Newsletter #41") !== false);
        $this->AssertTrue(strpos($Dump, "?dl=1234\">here</a>") !== false);

        // test personalized replacers, also test sending separated mails
        $Message= $Service->Create()
                          ->From('me@abc.com')
                          ->To($To1)
                          ->To($To2)
                          ->Subject('Invoice {{Inv}}')
                          ->Body('Hello {{Name}}, your invoice for {{Month}} is attached.')
                          ->Replacer('Month', 'September')
                          ->Replacer('Name', 'Nikola', $To1)
                          ->Replacer('Name', 'mr. Bell', $To2)
                          ->Replacer('Inv', '#2031', $To1)
                          ->Replacer('Inv', '#2032', $To2);
        $Success= $Message->Send();
        $this->assertTrue($Success);
        $Dump= str_replace(array("\r","\n"), '', file_get_contents($LogFile));
        $this->AssertTrue(strpos($Dump, "Invoice #2032") !== false);   // dump contains only last message
        $this->AssertTrue(strpos($Dump, "'Bell corp CEO<graham.bell@bell.com>'") !== false); // ony one recipient
        $this->AssertTrue(strpos($Dump, "Hello mr. Bell, your invoice for September is attached") !== false);
        $this->AssertTrue($Message->GetFails() === array());
    }


    public function T_estSwiftMailerDriver() {

        $LogFile = __DIR__.'/tmp/log.txt';

        $Service = new Mailer(array(
            'Driver'    => 'SwiftMailer',
            'LogFile'   => $LogFile,
            'DebugMode' => true,
        ));

        $Message = $Service->Create()
           ->Subject('Testing subject')
           ->From('me@abc.com')
           ->To('to1@abc.com')
           ->To('to2@abc.com')
           ->CC('cc1@abc.com')
           ->CC('cc2@abc.com')
           ->BCC('bcc1@abc.com')
           ->BCC('bcc2@abc.com')
           ->ReplyTo('no-reply1@abc.com')
           ->ReturnPath('no-reply2@abc.com')
           ->BodyHTML('<h1>Hello</h1> world')
           ->BodyPlain(true);
        $Success = $Message->Send();
        $this->assertTrue($Success);
        $Dump= str_replace(array("\r","\n"," "), '', file_get_contents($LogFile));
        $this->AssertTrue(strpos($Dump, "To:'array(0=>'to1@abc.com',1=>'to2@abc.com',)") !== false);   // To:
        $this->AssertTrue(strpos($Dump, "CC:'array(0=>'cc1@abc.com',1=>'cc2@abc.com',)'"));  // CC:
        $this->AssertTrue(strpos($Dump, "BCC:'array(0=>'bcc1@abc.com',1=>'bcc2@abc.com',)'"));  // BCC:
        $this->AssertTrue(strpos($Dump, "Testingsubject") !== false);   // Subject
        $this->AssertTrue(strpos($Dump, "<h1>Hello</h1>") !== false);   // Body

        // test attachments
        // sorry, Swift construction does not allow to test attachments without truly sending email!
    }


    public function _TestInWild() {

         $LogFile= __DIR__.'/tmp/log.txt';

         $Service= new Mailer(array(
             'Driver'    => 'SwiftMailer',
             'LogFile'   => $LogFile,
             'SMTP'=> array(
                 'Server'=> 'mail.accentphp.com',
                 'Port'=> 25,
                 'Username'=> 'admin@accentphp.com',
                 'Password'=> 'misshp44in',
             ),
             //'DebugMode' => true,
         ));
         $Message= $Service->Create()
             ->From('admin@accentphp.com')
             ->To('mveliki@gmail.com')
             ->CC('miroslav.curcic@spens.rs')
             ->Subject('Testiram slanje 3')
             ->Body('Pregledaj(III)')
             ->Attachment(__DIR__.'/tmp/Attachment.txt')
             ->AttachData('abcd', 'sample.txt');
         file_put_contents(__DIR__.'/tmp/Attachment.txt', 'qwertz');
         $Success = $Message->Send();
         $this->assertTrue($Success);
     }


    public function Te_stPlugins() {

        $LogFile = __DIR__.'/tmp/log.txt';

        $Service = new Mailer(array(
            'Driver'    => 'NativeMailer',
            'LogFile'   => $LogFile,
            'DebugMode' => true,
            'Plugins'=> array(
                array(
                    'Class'=> 'Accent\\Mailer\\Test\\TestingPlugin',
                    'Footer'=> '[ this is mandatory footer (C) ]',
                )
            ),
        ));
        $Message= $Service->Create()
                          ->From('me@abc.com')
                          ->To('r1@gmail.com')
                          ->Subject('Newsletter')
                          ->Body('Download our newsletter.');
        $Success= $Message->Send();
        $this->assertTrue($Success);
        $Dump= str_replace(array("\r","\n"," "), '', file_get_contents($LogFile));
        $this->AssertTrue(strpos($Dump, "(C)") !== false);
    }


}


?>