<?php namespace Accent\Log\Writer;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Base class for all log writers.
 */

use Accent\AccentCore\Component;
use Accent\Log\Log;


abstract class BaseLogWriter extends Component {


    protected static $DefaultOptions= array(
        // mandatory
        'Buffered'=> false,
        'MinLevel'=> Log::INFO,
        'ClearOnStart'=> false,
        // optional
        'Header'  => null,
        'Formatter'=> 'Line',
        'Path'=> '',
    );

    protected $LoggerName;

    protected $Buffer= array();

    protected $Header;

    protected $Formatter;




    public function __construct($Options=array()) {

        parent::__construct($Options);

        $this->LoggerName= $this->GetOption('LoggerOptions.LoggerName');
        $this->Header= $this->GetOption('Header');
        $this->Formatter= $this->GetOption('Formatter');
    }


    /**
     * Main writing method.
     *
     * @param string|object $Message
     * @param int $Level
     * @param array $Data
     */
    public function Write($Message, $Level, $Data) {

        // should ignore this message?
        if (!$this->CanHandle($Message, $Level, $Data)) {
            return;
        }

        // is bufferin enabled?
        if ($this->GetOption('Buffered')) {
            $this->Buffer[]= array($Message, $Level, $Data, time());
            return;
        }

        // sent it to writing processor
        $this->ProcessWrite($Message, $Level, $Data);
    }


    /**
     * ProcessWrite does actual writing of message.
     */
    protected function ProcessWrite($Message, $Level, $Data) {

        //$Message= $this->ExtractMessage($Message);

        // open stream
        // append(filename, content)
        // close stream
    }


    /**
     * Decide should writer ignore this message or not.
     * @return bool
     */
    protected function CanHandle($Message, $Level, $Data) {

        return $Level <= $this->GetOption('MinLevel');
    }


    /**
     * Return heading string to be written before first message.
     *
     * @return string
     */
    protected function GetHeader() {

        if ($this->Header === null) {
            // generate header from loggername and timestamp
            $Header= "\n\n".$this->LoggerName
                    ."\n(timestamp: ".date('r').")"
                    .$this->GetOption('SeparationLine','');
        } else {
            $Header= $this->Header;
        }
        // clear it before return
        $this->Header= '';
        // ret
        return $Header;
    }


    /**
     * Write buffered messages.
     * This method will NOT be called if 'Buffered' option is not set.
     */
    protected function Flush() {

        // open stream
        // foreach($this->Buffer as $Item) {
        //     $this->ProcessWrite($Item[0], $Item[1], $Item[2]);
        // }
        // close stream
    }


    /**
     * This method will be triggered on closing of application to allow
     * writers to finalize theirs tasks.
     */
    public function Close() {

        if ($this->GetOption('Buffered') && !empty($this->Buffer)) {
            $this->Flush();
            $this->Buffer= array();
        }
    }


    /**
     * Call associated Formatter to make string representation of whole line.
     *
     * Timestamp is provided by Flash method only.
     *
     * @return string
     */
    protected function FormatFileLine($Message, $Level, $Data, $Timestamp=null) {

        if (is_string($this->Formatter)) {
            // build object, use same options from writer
            $Class= strpos($this->Formatter,'\\') !== false
                ? $this->Formatter
                : '\\Accent\\Log\\Formatter\\'.$this->Formatter.'LogFormatter';
            if (!class_exists($Class)) {
                $this->Error('Log/Writer: Formatter class '.$Class.' not found.');
                $Class= '\\Accent\\Log\\Formatter\\LineLogFormatter'; // fallback
            }
            $this->Formatter= new $Class($this->GetAllOptions());
        }
        $Formatted= $this->Formatter->Format($Message, $Level, $Data, $Timestamp);
        return $this->GetHeader()."\n".$Formatted;
    }


    /**
     * Ensure string reprensation of $Message
     * and perform replacing {key} with values from $Data.
     * Both parameters will be modified via reference.
     *
     * @param mixed $Message
     * @param array $Data
     */
    protected function StringifyMessage(&$Message, &$Data) {

        $Message= $this->ExtractMessage($Message);
        $Message= $this->ReplaceMessage($Message, $Data);
    }


    /**
     * Replace "{key}" occurances in $Message with values in $Data array.
     * @param string $Message
     * @param array $Data
     * @return string
     */
    protected function ReplaceMessage($Message, &$Data) {

        foreach (array_keys($Data) as $key) {
            if (strpos($Message, '{'.$key.'}') === false) {
                continue;
            }
            $Message= str_replace('{'.$key.'}', $Data[$key], $Message);
            unset($Data[$key]);
        }
        return $Message;
    }


    /**
     * Returns the string representation of the message.
     */
    protected function ExtractMessage($Message, $Depth=0) {

        if ($Message === null) {
            return 'null';
        }
        if (is_float($Message)) {
            return number_format($Message);
        }
        if ($Message instanceof \DateTime) {
            return $Message->format('Y-m-d H:i:s');
        }
        if (is_resource($Message)) {
            return 'resource';
        }
        if (is_array($Message)) {
            $List= array();
            if (count($Message) > 200) {
                $Message= array_slice($Message, 0, 200);
                $Message[]= '... Maximum of 200 items reached.';
            }
            foreach($Message as $Item) {
                $Indent= str_repeat('    ',$Depth);
                $List[]= $Indent.'- '.$this->ExtractMessage($Item, $Depth+1);
            }
            return implode("\n", $List);
        }
        if (is_object($Message)) {
            if (method_exists($Message, 'getmessage')) {
                return $Message->GetMessage();
            } else if (method_exists($Message, '__tostring')) {
                return (string)$Message;
            } else {
                return 'object('.get_class($Message).')';
            }
        }
        return $Message;
    }

}

?>