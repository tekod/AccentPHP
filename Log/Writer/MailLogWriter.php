<?php namespace Accent\Log\Writer;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Sending log messages to email using PHP's native mail() function.
 *
 * Enabling "Buffered" option will collect all messages and send single email.
 * It is recomanding to enable that option to avoid issues with anti-spam systems,
 * but be sure that Log::Close method will be called to trigger actual sending.
 *
 * This writer does not use "Header" option becouse there is not possible to
 * append content in already sent email.
 */

use Accent\Log\Writer\BaseLogWriter;
use Accent\Log\Log;


class MailLogWriter extends BaseLogWriter  {

    protected static $DefaultOptions= array(
        // mandatory options
        'Buffered'=> false,
        'MinLevel'=> Log::INFO, // integer from LOG class
        // writter specific options
        'Formatter'=> 'Line',   // short name or FQCN or initilized object
        'SeparationLine'=> '',  // "\n-------------------------------------"
        'EmailTo'=> 'me@gmail.com', // recipient(s) of email,
                                    // multiple addresses separate by "," or ";",
                                    // use simple form, not "named email address"
        'EmailSubject'=> 'Log event',
        'EmailFrom'=> 'no-reply@mysite.com', // address of sender
        'EmailBody'=> 'Following log message(es) are issued: {Msg}',
        'EmailAddHeaders'=> array(), // additional headers to send (beside From and ContentType)
    );


    public function __construct($Options = array()) {

        parent::__construct($Options);
    }


    protected function ProcessWrite($Message, $Level, $Data) {

        // StringifyMessage can reduce size od $Data array
        $this->StringifyMessage($Message, $Data);
        // format text line
        $Dump= $this->FormatFileLine($Message, $Level, $Data);
        // send
        $this->SendEmail($Dump);
    }


    protected function Flush() {

        $Dump= array();
        foreach($this->Buffer as $Item) {
            list($Message, $Level, $Data, $Timestamp)= $Item;
            $this->StringifyMessage($Message, $Data);
            $Dump[]= $this->FormatFileLine($Message, $Level, $Data, $Timestamp);
        }
        // send
        $this->SendEmail("\n".implode("\n",$Dump));
    }


    protected function SendEmail($Content) {

        $Subject= $this->GetOption('EmailSubject');
        $Body= strtr($this->GetOption('EmailBody'), array('{Msg}'=>"\n".trim($Content)."\n"));
        $Headers= $this->GetHeaders();

        foreach($this->GetAddresses() as $Address) {
            mail($Address, $Subject, $Body, $Headers);
        }
    }


    protected function GetAddresses() {

        $Opt= $this->GetOption('EmailTo');
        $List1= explode(',', $Opt);
        $List2= explode(';', $Opt);
        $List= count($List1)>count($List2) ? $List1 : $List2;
        return array_map('trim', $List);
    }


    protected function GetHeaders() {

        $HeadersArray= $this->GetOption('EmailAddHeaders');
        $HeadersArray += array( // add default headers, if not exist
            'From'=> trim($this->GetOption('EmailFrom', 'no-reply@mysite.com')),
            'Content-type'=>'text/plain; charset=utf-8',
        );
        $HeadersString= '';
        foreach($HeadersArray as $key=>$value) {
            // prevent hdr injection
            $key= str_replace(array("\r","\n"),' ',$key);
            $value= str_replace(array("\r","\n"),' ',$value);
            // pack
            $HeadersString .= trim($key).': '.trim($value)."\r\n";
        }
        return $HeadersString;
    }



    /**
     * Override, append additional enter to header, if exist
     */
    protected function GetHeader() {

        $Header= parent::GetHeader();

        return ($Header === '') ? '' : "$Header\n";
    }

}

?>