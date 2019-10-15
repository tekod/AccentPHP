<?php namespace Accent\Mailer\Driver;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Using native PHP's "mail" function for sending emails.
 */

//use \Accent\Mailer\Driver\BaseDriver;
use \Accent\Mailer\Message;


class NativeMailerDriver extends BaseDriver {


    // local buffer for headers
    protected $HeadersArray= array();


    function __construct(array $Options) {

        // call parent and gather descendant options
        parent::__construct($Options);

    }

    /*
    protected function InitSender() {

        // "CC" must go in headers if specified
        if (!empty($this->Fields['CC'])) {
            $this->Fields['Headers']['CC']= implode(', ', $this->Fields['CC']);
        }

        // "BCC" must go in headers if specified
        if (!empty($this->Fields['BCC'])) {
            $this->Fields['Headers']['BCC']= implode(', ', $this->Fields['BCC']);
        }

        // header "From" goes in header too
        $this->Fields['Headers']['From'] = $this->Fields['From'];

        // header "Return-Path" goes in header too, clone from "From" if not specified
        $this->Fields['Headers']['Return-Path'] = ($this->Fields['ReturnPath'])
            ? $this->Fields['ReturnPath']
            : $this->Fields['From'];

        // header "Reply-To", clone from "From" if not specified
        $this->Fields['Headers']['Reply-To'] = ($this->Fields['ReplyTo'])
            ? $this->Fields['ReplyTo']
            : $this->Fields['From'];

        // prepare "additional params" of "mail()" function
        $this->AdditionalParams = DIRECTORY_SEPARATOR === '/' && $this->Fields['ReturnPath'] !== ''
            ? '-f'.$this->Fields['ReturnPath']
            : null;

        // confirm that driver is successfully initied
        return true;
    }*/


    /**
     * Perform actual sending of single email.
     *
     * @param Message $Message  object of \Accent\Mailer\Message as single message
     * @return array  list of undelivered addresses
     */
    public function Send(Message $Message) {

        // get 'To'
        $To= $Message->GetField('To');
        $To= reset($To);    // this instance is single-email-object for sure, focus on first entry

        // get 'Subject'
        $Subject= $Message->GetField('Subject');

        // copy headers into local buffer, "BuildBody" method will append few more of them
        $this->HeadersArray= $Message->GetField('Headers');

        // "CC" must go in headers if specified
        if (!empty($Message->GetField('CC'))) {
            $this->HeadersArray['CC']= implode(', ', $Message->GetField('CC'));
        }

        // "BCC" must go in headers if specified
        if (!empty($Message->GetField('BCC'))) {
            $this->HeadersArray['BCC']= implode(', ', $Message->GetField('BCC'));
        }

        // header "From" goes in header too
        $this->HeadersArray['From']= $Message->GetField('From');

        // header "Return-Path" goes in header too, clone from "From" if not specified
        $this->HeadersArray['Return-Path']= ($Message->GetField('ReturnPath'))
            ? $Message->GetField('ReturnPath')
            : $this->HeadersArray['From'];

        // header "Reply-To" goes in header too, clone from "From" if not specified
        $this->HeadersArray['Reply-To']= ($Message->GetField('ReplyTo'))
            ? $Message->GetField('ReplyTo')
            : $this->HeadersArray['From'];

        // pack multipart email content
        $Body= $this->BuildBody($Message->GetField('BodyHTML'), $Message->GetField('BodyPlain'));

        // glue all headers
        $Headers= array();
        foreach ($this->HeadersArray as $Name => $Value) {
            $Headers[]= $Name.': '.$this->SanitizeLine($Value);
        }
        $Headers= implode("\r\n", $Headers);   // maybe use only "\n"? some poor MTAs can replace \r with \n causing double newline

        // prepare "additional params" of "mail()" function
        $AdditionalParams= DIRECTORY_SEPARATOR === '/' && $this->Message->GetField('ReturnPath') !== ''
            ? '-f'.$this->Message->GetField('ReturnPath')
            : null;

        // everything is ready for launching, create debug log
        $this->DebugLog(array($To, $Subject, $Body, $Headers, $AdditionalParams));

        // avoid launching email if debug mode detected
        if ($this->GetOption('DebugMode')) {
            return array();
        }

        // send
        $Success= @mail(implode(', ',$To), $Subject, $Body, $Headers, $AdditionalParams);

        // return array of undeliverable addresses
        return $Success ? array() : $To;
    }


    protected function BuildBody($BodyHTML, $BodyPlain) {

        $Hash= substr(md5(time()),0,12);
        $Attachments= $this->Message->GetField('Attachments');
        $AttachData= $this->Message->GetField('AttachData');

        $IsMultipart= !empty($Attachments)
            || !empty($AttachData)
            || ($BodyHTML !== null && $BodyPlain !== null);

        if ($IsMultipart) {
            $IsMultipartMixed = !empty($Attachments) || !empty($AttachData);
            $MultipartTypeText = $IsMultipartMixed
                ? 'mixed'
                : 'alternative';
            $BoundaryMixed = 'Boundary-Mixed-'.$Hash;
            $BoundaryAlt = 'Boundary-Alt-'.$Hash;
            $BoundaryMain = $IsMultipartMixed
                ? $BoundaryMixed
                : $BoundaryAlt;
            $this->HeadersArray['Content-Type']= "multipart/$MultipartTypeText; boundary=\"$BoundaryMain\"";

            // prepare "Body" parameter of "mail()" function
            $Body = "This is a MIME encoded message.";

            if ($IsMultipartMixed) {
                // enclose text block with its own "alternative" boundary
                $Body .= "\r\n\r\n--$BoundaryMixed"
                    ."\r\nContent-Type: multipart/alternative; boundary=\"$BoundaryAlt\"";
            }
            if ($BodyPlain !== null) {
                $Body .= "\r\n\r\n--$BoundaryAlt"
                    ."\r\nContent-type: text/plain;charset=utf-8"
                    ."\r\nContent-Transfer-Encoding: 8bit"
                    ."\r\n\r\n".$BodyPlain;
            }

            if ($BodyHTML !== null) {
                // ensure existence of <html></html> to satisfy antispam filters
                if (strpos($BodyHTML, '<html') === false) {
                    $BodyHTML = '<html>'.$BodyHTML;
                }
                if (strpos($BodyHTML, '</html') === false) {
                    $BodyHTML = $BodyHTML.'</html>';
                }
                $Body .= "\r\n\r\n--$BoundaryAlt"
                    ."\r\nContent-type: text/html;charset=utf-8"
                    ."\r\nContent-Transfer-Encoding: 8bit"
                    ."\r\n\r\n".$BodyHTML;
            }

            if ($IsMultipartMixed) {
                // close inner block
                $Body .= "\r\n\r\n--$BoundaryAlt--";
            }

            // add all attachments
            foreach ($Attachments as $Path => $BaseName) {
                $MimeType= $this->GetMimeType($Path);
                $Body .= "\r\n\r\n--$BoundaryMixed\r\n"
                    ."Content-Type: $MimeType; name=\"$BaseName\"\r\n"
                    ."Content-Transfer-Encoding: base64\r\n"
                    ."Content-Disposition: attachment; filename=\"$BaseName\"\r\n\r\n"
                    .chunk_split(base64_encode(file_get_contents($Path)));
            }
            foreach($AttachData as $BaseName => $Buffer) {
                $Body .= "\r\n\r\n--$BoundaryMixed\r\n"
                    ."Content-Type: application/octet-stream; name=\"$BaseName\"\r\n"
                    ."Content-Transfer-Encoding: base64\r\n"
                    ."Content-Disposition: attachment; filename=\"$BaseName\"\r\n\r\n"
                    .chunk_split(base64_encode($Buffer));
            }

            // close outer block, it is "alternative" if no attachments are present (if $IsMultipartMixed is false)
            // ending with double EOL to prevent injecting
            $Body .= "\r\n\r\n--$BoundaryMain--\r\n\r\n";
            return $Body;
        }

        // it is not multipart message, so return as-is
        $this->HeadersArray['Content-Type']= $BodyPlain !== null ? "text/plain;charset=utf-8" : "text/html;charset=utf-8";
        $this->HeadersArray['Content-Transfer-Encoding']= "8bit";
        return $BodyPlain !== null ? $BodyPlain : $BodyHTML;
    }


    protected function DebugLog(array $Params) {

        $Path= $this->GetOption('LogFile');
        if (!$Path) {
            return;
        }
        if (!is_dir(dirname($Path))) {
            mkdir(dirname($Path), 0777, true);
        }
        list($To, $Subject, $Body, $Headers, $AdditionalParams)= $Params;
        $To= implode(', ', $To);
        $Dump= 'Log [NativeMainDriver] on '.date('r')."\n".str_repeat('=',60);
        $Dump .= "\n\nTo:\n'$To'";
        $Dump .= "\n\nSubject:\n'$Subject'";
        $Dump .= "\n\nBody:\n'$Body'";
        $Dump .= "\n\nHeaders:\n'$Headers'";
        $Dump .= "\n\nAdditionalParams:\n'$AdditionalParams'";
        file_put_contents($Path, $Dump);
    }


}



?>