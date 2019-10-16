<?php namespace Accent\Mailer\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Abstract (base) class for all mail drivers.
 * Descendant classes should implement mail-provider specific 'Send' method and additional setters..
 */

use \Accent\AccentCore\Component;
use \Accent\Mailer\Message;


abstract class BaseDriver extends Component {


    protected static $DefaultOptions= array(

        // object of \Accent\Mailer\Message
        'Message'=> null,
    );

    /** @var \Accent\Mailer\Message */
    protected $Message;


    function __construct(array $Options) {

        // call parent and gather descendant options
        parent::__construct($Options);

        // export Message
        $this->Message= $this->GetOption('Message');
    }


    /**
     * Perform actual sending of single email.
     *
     * @param Message $Message  object of \Accent\Mailer\Message as single message
     * @return array  list of undelivered addresses
     */
    abstract protected function Send(Message $Message);


    /**
     * Store prepared email in text file for debugging/analyzing
     * List of parameters is not precisely defined,
     * it is left open because various drivers packs theirs variables in various ways.
     *
     * @param array $Params  arbitrary params needed to compose log entry
     */
    abstract protected function DebugLog(array $Params);





    //####################################################
    //###                                              ###
    //###                Helper methods                ###
    //###                                              ###
    //####################################################


    /**
     * Convert array-typed addresses to CSV string without problematic chars.
     *
     * @param string $FieldName  [To|CC|BCC]
     * @param bool $ReturnAsArray  optionally return list as array
     * @return string|array
     */
    protected function PackAddresses($FieldName, $ReturnAsArray=false) {

        $Recipients= $this->Message->GetField($FieldName);
        if (!is_array($Recipients)) {
            $Recipients= trim($Recipients) === '' ? array() : array($Recipients);
        }
        array_walk($Recipients, function(&$v){$v=str_replace(array("\n","\r","\t",","),'',$v);});

        return $ReturnAsArray
            ? $Recipients
            : implode(', ', $Recipients);
    }


    /**
     * Removed "new lines" from strings to prevent header spoofing.
     */
    protected function SanitizeLine($String) {

        return str_replace(array("\n","\r","\t"), '', $String);
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
     * Find MIME type of specified file.
     * It will be used to properly tag message attachments.
     *
     * @param string  full path to file
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

}



?>