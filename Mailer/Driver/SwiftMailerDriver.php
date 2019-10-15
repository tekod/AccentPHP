<?php namespace Accent\Mailer\Driver;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Using SwiftMailer project for sending emails.
 */

//use \Accent\Mailer\Driver\BaseDriver;
use \Accent\Mailer\Message;


class SwiftMailerDriver extends BaseDriver {


    protected static $DefaultOptions= array(

        // configure access to SMTP server
        'SMTP'=> array(
            'Server'=> 'smtp.goole.com',
            'Port'=> 25,
            'Username'=> 'MyUsername',
            'Password'=> 'MyPassword',
        ),
    );


    protected $SwiftMailer;
    protected $SwiftLogger;

    /**
     * Initialize SwiftMailer.
     */
    protected function InitSwiftMailer() {

        if (is_object($this->SwiftMailer)) {
            return; // already loaded and initialized
        }

        require_once __DIR__.'/../Lib/SwiftMailer/lib/swift_required.php';

        $Transport = \Swift_SmtpTransport::newInstance($this->GetOption('SMTP.Server'), $this->GetOption('SMTP.Port'))
                                         ->setUsername($this->GetOption('SMTP.Username'))
                                         ->setPassword($this->GetOption('SMTP.Password'));
        //$Transport= \Swift_NullTransport::newInstance();

        // create the Mailer using your created Transport
        $this->SwiftMailer = \Swift_Mailer::newInstance($Transport);

        // register logger plugin
        $this->SwiftLogger = new \Swift_Plugins_Loggers_ArrayLogger();
        $this->SwiftMailer->registerPlugin(new \Swift_Plugins_LoggerPlugin($this->SwiftLogger));

        // return success
        return true;
    }


    public function Send(Message $Message) {

        // initialize SwiftMailer
        $this->InitSwiftMailer();

        // clear logger for each envelope
        $this->SwiftLogger->clear();

        // get 'From', prepare it in swift-array-address form
        $FromParsed= $this->ParseEmailAddress($Message->GetField('From'));
        $From= $FromParsed['Name'] === ''
            ? array($FromParsed['Address'])
            : array($FromParsed['Address'] => $FromParsed['Name']);

        // get 'ReturnPath', swift expect string (pure address) here
        $ReturnPathParsed= ($Message->GetField('ReturnPath'))
            ? $this->ParseEmailAddress($Message->GetField('ReturnPath'))
            : $FromParsed;
        $ReturnPath= $ReturnPathParsed['Address'];

        // get 'To', 'CC', 'BCC', prepare them in swift-array-address form
        $PackedTo= $this->PackSwiftAddresses(reset($Message->GetField('To')));
        $PackedCC= $this->PackSwiftAddresses($this->PackAddresses('CC'));
        $PackedBCC= $this->PackSwiftAddresses($this->PackAddresses('BCC'));

        // get 'Subject'
        $Subject= $Message->GetField('Subject');

        // create a message
        $SwiftMessage= \Swift_Message::newInstance($Subject)
            ->setFrom($From)
            ->setReturnPath($ReturnPath)
            ->setTo($PackedTo);
        if (!empty($PackedCC)) {
            $SwiftMessage->setCc($PackedCC);
        }
        if (!empty($PackedBCC)) {
            $SwiftMessage->setBcc($PackedBCC);
        }

        // set bodies
        $BodyHTML= $Message->GetField('BodyHTML');
        $BodyPlain= $Message->GetField('BodyPlain');
        $BodySet= false;
        if ($BodyHTML === null && $BodyPlain === null) {
            $BodyPlain= ''; // ensure at least plain body exist
        }
        if ($BodyHTML !== null) {
            $BodySet= true;
            $SwiftMessage->setBody($BodyHTML, 'text/html');
        }
        if ($BodyPlain !== null) {
            $Method = $BodySet
                ? 'addPart'
                : 'setBody';
            $SwiftMessage->$Method($BodyPlain, 'text/plain');
        }

        // add attachments
        foreach($this->Message->GetField('Attachments') as $Path => $BaseName) {
            $MimeType= $this->GetMimeType($Path);
            $Attachment= \Swift_Attachment::fromPath($Path, $MimeType)
                        ->setFilename($BaseName);
            $SwiftMessage->attach($Attachment);
        }
        foreach($this->Message->GetField('AttachData') as $BaseName => $Buffer) {
            $Attachment= \Swift_Attachment::newInstance($Buffer, $BaseName);
            $SwiftMessage->attach($Attachment);
        }

        // everything is ready for launching, create log
        $this->DebugLog(array($From, $PackedTo, $PackedCC, $PackedBCC, $Subject, $BodySet?$BodyHTML:$BodyPlain));

        // avoid launching email if debug mode detected
        if ($this->GetOption('DebugMode')) {
            return array();
        }

        // send the message
        $Fails= array(); // this will be passed via reference
        try {
            // wrap launcher in try-catch coz SwiftMailer can trigger exception
            $this->SwiftMailer->send($SwiftMessage, $Fails);
        } catch (\Exception $E) {
            // send exception message to logger
            $Msg= 'Swift exception: "'.$E->GetMessage().'"';
            $this->Log2($Msg);
            $this->Error($Msg);
            $Fails= $PackedTo;
        }
        $this->AddFail($Fails);

        // append SwiftMailer's plugin log
        $this->Log2($this->SwiftLogger->dump());

        // return success
        return empty($Fails);
    }


    /**
     * Pack array of addresses in way which SwiftMailer like.
     *
     * @param array
     * @return array
     */
    protected function PackSwiftAddresses($List) {

        //echo '<pre>';var_dump($List);

        if ($List === '') {
            //return array();
        }
        //if (is_array($List)) {echo'<pre>';var_dump($List);var_dump(debug_print_backtrace(0));die();}
        if (is_string($List)) {
            $List= explode(',', $List);
        }
        $Recipients= array();
        foreach(array_filter($List) as $Recipient) {
            $Parsed= $this->ParseEmailAddress($Recipient);
            if ($Parsed['Name'] === '') {
                $Recipients[]= $Parsed['Address'];
            } else {
                $Recipients[$Parsed['Address']]= $Parsed['Name'];
            }
        }

        //var_dump($Recipients);
        return $Recipients;
    }


    /**
     * Store debug data.
     *
     * @param array $Params
     */
    protected function DebugLog(array $Params) {

        $Path= $this->GetOption('LogFile');
        if (!$Path) {
            return;
        }
        if (!is_dir(dirname($Path))) {
            mkdir(dirname($Path), 0777, true);
        }
        list($From, $To, $CC, $BCC, $Subject, $Body)= $Params;
        $Dump= 'Log [SwiftMailerDriver] on '.date('r')."\n".str_repeat('=',60);
        $Dump .= "\n\nFrom:\n'".var_export($From, true)."'";
        $Dump .= "\n\nTo:\n'".var_export($To, true)."'";
        $Dump .= "\n\nCC:\n'".var_export($CC, true)."'";
        $Dump .= "\n\nBCC:\n'".var_export($BCC,true)."'";
        $Dump .= "\n\nSubject:\n'$Subject'";
        $Dump .= "\n\nBody:\n'$Body'";
        file_put_contents($Path, $Dump);
    }


    /**
     * Append additional debug data to log file.
     *
     * @param $Dump
     */
    protected function Log2($Dump) {

        $Path= $this->GetOption('LogFile');
        if (!$Path) {
            return;
        }
        $Heading= "\n\n======================================\nSWIFTMAILER LOG:\n";
        file_put_contents($Path, $Heading.$Dump, FILE_APPEND);
    }

}



?>