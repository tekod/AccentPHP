<?php namespace Accent\Mailer;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Message object represent email message with all its fields and methods for managing them.
 * Developer usually does not need to create this object manually, use Mail class (service) to instantiate it.
 */

use Accent\AccentCore\Component;
use Accent\AccentCore\Event\Event;

class Message extends Component {

    protected static $DefaultOptions= array(

        // storage for email informations
        'Fields'=> array(
            'Subject'=> '',
            'From'=> '',
            'To'=> array(),
            'CC'=> array(),
            'BCC'=> array(),
            'ReplyTo'=> '',
            'ReturnPath'=> '',
            'BodyHTML'=> null,
            'BodyPlain'=> null,
            'Attachments'=> array(),
            'AttachData'=> array(),
            'Headers'=> array(),
            'Replacers'=> array('*'=>array()),
        ),

        'ReplacerWrapper'=> array('{{', '}}'),
    );

    protected $Driver;
    protected $Fields= array();
    protected $EmptyFields;
    protected $Fails;
    protected $SeparateSend;


    function __construct(array $Options) {

        // init few default headers, using "self" instead of "static" to target local scope
        self::$DefaultOptions['Fields']['Headers'] += array(
            'MIME-Version'=> '1.0',
            'User-Agent'=> 'AccentPHP / Mail service',
            //'Message-ID'=> '<'.'>',   // this will be overwritten in SendEnvelope
        );

        // call parent and gather descendant options
        parent::__construct($Options);

        // export fields buffer
        $this->Fields= $this->GetOption('Fields');

        // preserve empty values (local scope) for later clearing
        $this->EmptyFields= self::$DefaultOptions['Fields'];

        // convert 'To' to array if specified in constructor as string
        if (is_string($this->Fields['To'])) {
            $this->Fields['To']= array_map('trim', explode(',', $this->Fields['To']));
        }

        // convert 'CC' to array if specified in constructor as string
        if (is_string($this->Fields['CC'])) {
            $this->Fields['CC']= array_map('trim', explode(',', $this->Fields['CC']));
        }

        // convert 'BCC' to array if specified in constructor as string
        if (is_string($this->Fields['BCC'])) {
            $this->Fields['BCC']= array_map('trim', explode(',', $this->Fields['BCC']));
        }

        // dispatch 'Body' to 'BodyHTML' and 'BodyPlain' if passed via constructor
        if (isset($Options['Fields']['Body'])) {
            $this->Body($Options['Fields']['Body']);
        }

        // convert 'Attachments' to array if supplied as string through constructor
        if (is_string($this->Fields['Attachments'])) {
            $this->Fields['Attachments']= array($this->Fields['Attachments'] => basename($this->Fields['Attachments']));
        }

        // if AttachData supplied as string through constructor set its name as 'unnamed'
        if (is_string($this->Fields['AttachData'])) {
            $this->Error('Mail: AttachData must have associated filename, so it cannot be specified as simple string.');
            $this->Fields['AttachData']= array('unnamed' => $this->Fields['AttachData']);
        }

        // instantiate driver
        $this->Driver= $this->CreateDriver();
        $this->Initied= $this->Driver->IsInitied();
    }


    /**
     * Set 'Subject' field of message.
     * Further calling of this method will OVERWRITE previous entry because message cannot have multiple subjects.
     * It can contain 'replacer' tags.
     *
     * @param string $String
     * @return $this
     */
    public function Subject($String) {

        $this->Fields['Subject']= $String;
        return $this;
    }


    /**
     * Set 'From' of message (sender of message).
     * Further calling of this method will OVERWRITE previous entry because message cannot have multiple senders.
     * It can be formatted in user-friendly or simple format like:
     * "Marco Polo <marco.polo@gmail.com>" or "marco.polo@gmail.com".
     *
     * @param string $String
     * @return $this
     */
    public function From($String) {

        $this->Fields['From']= $String;
        return $this;
    }


    /**
     * Set 'To' field of message (recipient of message).
     * Further calling of this method will append more recipients.
     * It can be formatted in user-friendly or simple format like:
     * "Marco Polo <marco.polo@gmail.com>" or "marco.polo@gmail.com".
     *
     * @param string $String  email address with or without "friendly name" part
     * @return $this
     */
    public function To($String) {

        $this->Fields['To'][]= $String;
        return $this;
    }


    /**
     * Set 'CC' field of message (carbon copy recipient of message).
     * Further calling of this method will append more recipients.
     * It can be formatted in user-friendly or simple format like:
     * "Marco Polo <marco.polo@gmail.com>" or "marco.polo@gmail.com".
     *
     * @param string $String
     * @return $this
     */
    public function CC($String) {

        $this->Fields['CC'][]= $String;
        return $this;
    }


    /**
     * Set 'BCC' field of message (blind carbon copy recipient of message).
     * Further calling of this method will append more recipients.
     * It can be formatted in user-friendly or simple format.
     *
     * @param string $String
     * @return $this
     */
    public function BCC($String) {

        $this->Fields['BCC'][]= $String;
        return $this;
    }


    /**
     * Set 'Reply-To' field of message (where to send reply of  message).
     * Further calling of this method will OVERWRITE previous entry because this field can specify only one address.
     * It can be formatted in user-friendly or simple format.
     *
     * @param string $String
     * @return $this
     */
    public function ReplyTo($String) {

        $this->Fields['ReplyTo']= $String;
        return $this;
    }


    /**
     * Set 'Return-Path' field of message (where to send undelivered mail).
     * Further calling of this method will OVERWRITE previous entry because this field can specify only one address.
     * It can be formatted in user-friendly or simple format.
     *
     * @param string $String
     * @return $this
     */
    public function ReturnPath($String) {

        $this->Fields['ReturnPath']= $String;
        return $this;
    }


    /**
     * Set HTML-formatted 'Body' of message (content of message).
     * Further calling of this method will OVERWRITE previous content.
     * It can contain replacer tags.
     *
     * @param string $String
     * @return $this
     */
    public function BodyHTML($String) {

        $this->Fields['BodyHTML']= $String;
        return $this;
    }

    /**
     * Set plain-formatted 'Body' of message (content of message).
     * Further calling of this method will OVERWRITE previous content.
     * It can contain replacer tags.
     *
     * @param string $String
     * @return $this
     */
    public function BodyPlain($String) {

        $this->Fields['BodyPlain']= $String;
        return $this;
    }

    /**
     * Set HTML 'Body' of message AND generate plain version of message from HTML source.
     * Further calling of this method will OVERWRITE previous content.
     * It can contain replacer tags.
     *
     * @param string $String
     * @return $this
     */
    public function Body($String) {

        $this->Fields['BodyHTML']= $String;
        $this->Fields['BodyPlain']= true;  // marker
        return $this;
    }


    /**
     * Add attachment to message.
     * Further calling of this method will append more attachments.
     *
     * @param string $Path  full path to file
     * @param string|null $AsFileName  attach file under different name
     * @return $this
     */
    public function Attachment($Path, $AsFileName=null) {

        $Basename= $AsFileName === null
            ? basename($Path)
            : $AsFileName;
        $this->Fields['Attachments'][$Path]= $Basename;
        return $this;
    }


    /**
     * Add attachment to message similar as method "Attachment()" but on-fly, without making temporary file on disk.
     * Further calling of this method will append more attachments.
     *
     * @param $DataString
     * @param $AsFileName
     * @return $this
     */
    public function AttachData($DataString, $AsFileName) {

        $this->Fields['AttachData'][$AsFileName]= $DataString;
        return $this;
    }


    /**
     * Add custom mail-header to message.
     *
     * @param string $Name
     * @param string $Value
     * @return $this
     */
    public function Header($Name, $Value) {

        $this->Fields['Headers'][$Name]= $Value;
        return $this;
    }


    /**
     * Add replacer (also known as decorator pattern).
     *
     * @param string $Key  identifier, without wrapping with "{{" and "}}", needed to be replaced with value
     * @param string $Value  value for replacing, must be escaped for safe usage in HTML body
     * @param string $ForRecipient  perform replacing for this recipient only, bare address only
     * @return $this
     */
    public function Replacer($Key, $Value, $ForRecipient='*') {

        // remove named part of recipient address
        $Addr= $this->ExtractBareAddress($ForRecipient);

        // add replacer in registry
        if (!isset($this->Fields['Replacers'][$Addr])) {
            $this->Fields['Replacers'][$Addr]= array();
        }
        $this->Fields['Replacers'][$Addr][$Key]= $Value;

        // return this for chaining
        return $this;
    }


    /**
     * Launch email to mail provider.
     *
     * @return boolean  success
     */
    public function Send() {

        // field 'To' is mandatory
        if ($this->Fields['To'] === '' || $this->Fields['To'] === array()) {
            $this->Error('Mail: field "To" cannot be empty.');
            return false;
        }

        // sanitize addresses, remove problematic chars and ensure array type
        $this->Fields['To']= $this->PackAddresses('To');
        $this->Fields['CC']= $this->PackAddresses('CC');
        $this->Fields['BCC']= $this->PackAddresses('BCC');

        // get default value for "From" from php.ini
        if (!$this->Fields['From'] && ini_get('sendmail_from') <> '') {
            $this->Fields['From'] = ini_get('sendmail_from');
        }

        // get default value for "ReturnPath" from "From"
        if (!$this->Fields['ReturnPath']) {
            $this->Fields['ReturnPath']= $this->Fields['From'];
        }

        // get default value for "ReplyTo" from "From"
        if (!$this->Fields['ReplyTo']) {
            $this->Fields['ReplyTo']= $this->Fields['From'];
        }

        // generate plain version from HTML
        if ($this->Fields['BodyPlain'] === true) {
            $this->Fields['BodyPlain']= $this->GeneratePlainFromHTML();
        }

        // notify listeners about sending email,
        // for example to write external log (use "GetField" method to fetch values)
        // or to append recipients (use "BCC" method to append recipient) or modify message body
        $this->EventDispatch('Mail.Send', ['Message'=>$this]);

        // clear error buffer
        $this->Fails= array();

        // group recipients into envelopes and send each message
        foreach($this->GetEnvelopes() as $To) {
            $this->SendEnvelope($To);
        }

        // return success
        return empty($this->Fails);
    }


    /**
     * Send single email message.
     *
     * @param array $To  addresses (as array of strings)
     */
    protected function SendEnvelope($To) {

        // clone message object
        // in cloned message rewrite 'To' and personalize 'Subject', 'BodyHTML' and 'BodyPlain' fields
        // this way original message remains intact for further usage
        $SingleMessage= clone $this;
        $SingleMessage->ClearField('To');
        $SingleMessage->To($To);

        // generate unique message ID for each envelope
        $SingleMessage->GenerateMessageId();

        // extract bare address from 'To', that will be used as identifier within replacement (decorating)
        $BareAddress= $this->ExtractBareAddress(reset($To));

        // personalize and sanitize subject
        $Subject= $this->ApplyReplacers($this->Fields['Subject'], $BareAddress);
        $SingleMessage->Subject(str_replace(array("\n","\r","\t"), '', $Subject));

        // personalize bodies
        $SingleMessage->BodyHTML($this->Fields['BodyHTML'] === null
            ? null
            : $this->ApplyReplacers($this->Fields['BodyHTML'], $BareAddress));
        $SingleMessage->BodyPlain($this->Fields['BodyPlain'] === null
            ? null
            : $this->ApplyReplacers($this->Fields['BodyPlain'], $BareAddress));

        // notify listeners about sending each email
        // for sending separate mail to each recipient this event will trigger for each envelope
        if ($this->EventDispatch('Mail.SendEnvelope.Before', ['Message'=>$SingleMessage])) {
            // listeners can return true to prevent sending email
            return;
        }

        // send email
        $Fails= $this->Driver->Send($SingleMessage);
        $this->AddFail($Fails);

        // notify listeners about successfully sent each email
        if (empty($Fails)) {
            $this->EventDispatch('Mail.SendEnvelope.After', ['Message'=>$SingleMessage]);
        }
        // continue to next message regardless of previous failures
    }


    /**
     * Instantiate and return driver object.
     *
     * @return object
     */
    protected function CreateDriver() {

        $Driver= $this->GetOption('Driver');
        if (is_object($Driver)) {
            // return supplied object, one instance CAN be used to send multiple envelopes
            return $Driver;
        }
        // it is class name, short or FQCN
        $Class= strpos($Driver,'\\') === false
            ? '\\Accent\\Mailer\\Driver\\'.ucfirst($Driver).'Driver'
            : $Driver;
        if (!class_exists($Class)) {
            $this->Error("Mail service: driver class '$Class' not found.");
            $Class= '\\Accent\\Mailer\\Driver\\NoneDriver';
        }
        // instantiate message object and forward parent's options
        $Options= array(
             'Message'=> $this,
        ) + $this->Options;
        return new $Class($Options);
    }


    //####################################################
    //###                                              ###
    //###                Helper methods                ###
    //###                                              ###
    //####################################################


    /**
     * Clear content of specified field.
     *
     * @param string $FieldName
     */
    public function ClearField($FieldName) {

        if ($FieldName === 'Body') {
            $this->Fields['BodyHTML']= $this->Fields['BodyPlain']= null;
        } else {
            $this->Fields[$FieldName] = $this->EmptyFields[$FieldName];
        }
    }


    /**
     * Fetch content of specified field.
     *
     * @param string $FieldName
     * @return mixed
     */
    public function GetField($FieldName) {

        return ($FieldName === 'Body')
            ? array($this->Fields['BodyHTML'], $this->Fields['BodyPlain'])
            : $this->Fields[$FieldName];
    }


    /**
     * Mark recipient(s) of unsuccessfully sent emails.
     * Parameter can be single recipient (named or bare address), array of addresses or CSV-formatted string of addresses.
     *
     * @param string|array $To
     */
    protected function AddFail($To) {

        if (is_array($To)) {
            $To= implode(',', $To);
        }
        $Ex= array_filter(array_map('trim', explode(',', $To)));
        $this->Fails= array_merge($this->Fails, $Ex);
    }


    /**
     * Return recipients of unsuccessfully sent emails.
     *
     * @return array
     */
    public function GetFails() {

        return $this->Fails;
    }


    public function SetSeparateSendMode($Mode=true) {

        $this->SeparateSend= (bool) $Mode;
    }


    /**
     * Return list of recipients of multiple emails.
     * Each entry represent receivers (as single or multiple sub-array entries) of separated email messages.
     *
     * @return array of arrays
     */
    protected function GetEnvelopes() {

        if ($this->SeparateSend === null) {
            // autodetect, assume false for beginning
            $Separate= false;
        } else {
            $Separate= $this->SeparateSend;
        }

        // force separated mode if replacers are set for individual recipients
        if (count($this->Fields['Replacers']) > 1) {
            $Separate= true;
        }

        // sanitize addresses
        $To= $this->PackAddresses('To');

        // return array of recipients as ...
        if ($Separate) {
            // ... each recipient separated in its own entry, this will launch multiple emails
            array_walk($To, function(&$v){$v=array($v);});
            return $To;
        } else {
            // .. all recipients grouped in first entry, this will launch only one email
            return array($To);
        }
    }


    /**
     * Helper, generate plain text representation of HTML body.
     * This method doing it best to create best looking shape of message
     * but premium systems should supply both versions of body separately.
     *
     * @return string
     */
    protected function GeneratePlainFromHTML() {

        // load html2lib class manually, it is old-fashion no-namespace 3rd-party library
        require_once(__DIR__.'/Lib/Html2Text.php');
        $H2T= new \Html2Text\Html2Text($this->Fields['BodyHTML']);

        // insert "SiteURL" option (if supplied) to resolve relative links
        $BaseURL= $this->GetOption('SiteURL');
        if ($BaseURL) {
            if (strpos($BaseURL, '://') === false) {
                $BaseURL = "http://$BaseURL";
            }
            $H2T->SetBaseURL($BaseURL);
        }

        // return plan text
        return $H2T->GetText();
    }


    /**
     * Convert array-typed addresses to CSV string without problematic chars.
     *
     * @param string $FieldName  [To|CC|BCC]
     * @return string|array
     */
    protected function PackAddresses($FieldName) {

        $Recipients= $this->Fields[$FieldName];
        if (!is_array($Recipients)) {
            $Recipients= trim($Recipients) === '' ? array() : array($Recipients);
        }

        // sanitize array
        array_walk($Recipients, function(&$v){$v=str_replace(array("\n","\r","\t",","),'',$v);});

        // return
        return $Recipients;
    }


    /**
     * Return pure address from named or simple address format.
     * Input string MUST NOT contain multiple addresses.
     *
     * @param string $EmailAddress
     * @return string
     */
    protected function ExtractBareAddress($EmailAddress) {

        $Parsed= $this->ParseEmailAddress($EmailAddress);
        return $Parsed['Address'];
    }


    /**
     * Return list of pure addresses from input (as CSV-string or array).
     *
     * @param string|array $EmailAddresses
     * @return array
     */
    protected function ExtractBareAddresses($EmailAddresses) {

        if (is_string($EmailAddresses)) {
            $EmailAddresses=  explode(',', $EmailAddresses);
        }
        $Out= array();
        foreach(array_filter(array_map('trim',$EmailAddresses)) as $Addr) {
            $Parsed= $this->ParseEmailAddress($Addr);
            $Out[]= $Parsed['Address'];
        }
        return $Out;
    }


    /**
     * Parse input string and
     *
     * @param $String
     * @return mixed
     */
    protected function ParseEmailAddress($String) {

        return (preg_match('#(.*)<(.*)>$#', $String, $matches))
            ? array(
                'Name'=> trim($matches[1]),
                'Address'=> trim($matches[2]),
            )
            : array(
                'Name'=> '',
                'Address'=> trim($String),
            );
    }


    /**
     * Perform replacing of source string with values from 'Replacers' field.
     * Keys will be wrapped with substrings from 'ReplaceWrapper' config options before replacing.
     * Keys are case-sensitive.
     *
     * @param $Text
     * @param string $ForRecipient
     * @return string
     */
    protected function ApplyReplacers($Text, $ForRecipient='*') {

        // get replacements for current recipient and append other default replacements (recipient '*')
        $Replacers= (isset($this->Fields['Replacers'][$ForRecipient]) ? $this->Fields['Replacers'][$ForRecipient] : array())
                  + (isset($this->Fields['Replacers']['*']) ? $this->Fields['Replacers']['*'] : array());
        // wrap keys
        $Wrap= $this->GetOption('ReplacerWrapper');
        $TR= array();
        foreach($Replacers as $Key=>$Value) {
            $TR[$Wrap[0].$Key.$Wrap[1]]= $Value;
        }
        // preform replacing, using strtr() to avoid replacing within already replaced text
        return strtr($Text, $TR);
    }


    /**
     * Find MIME type of specified file.
     * It will be used to properly tag message attachments.
     *
     * @param string $Path  full path to file
     * @return string
     */
    protected function GetMimeType($Path) {

        if (function_exists('mime_content_type')) {
            return mime_content_type($Path);
        } elseif (function_exists('finfo_open')) {
            $fInfo= finfo_open(FILEINFO_MIME);
            $MimeType= finfo_file($fInfo, $Path);
            finfo_close($fInfo);
            return $MimeType;
        }
        else {
            return 'application/octet-stream';
        }
    }


    /**
     * Create "Message-ID" header field for this message.
     * Each sent email must have this value unique.
     */
    public function GenerateMessageId() {

        $Domain= preg_replace('#[^\w.-]+#', '', $this->GetRequestContext()->SERVER['HTTP_HOST']);
        $Id= md5(microtime()).'@'.$Domain;
        $this->Fields['Headers']['Message-ID']= "<$Id@$Domain>";
    }

}



?>