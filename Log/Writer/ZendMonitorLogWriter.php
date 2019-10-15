<?php namespace Accent\Log\Writer;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Forwarding logs to Zend's function zend_monitor_custom_event()
 */

use Accent\Log\Writer\BaseLogWriter;
use Accent\Log\Log;


class ZendMonitorLogWriter extends BaseLogWriter  {

    protected static $DefaultOptions= array(
        // mandatory options
        'Buffered'=> false,
        'MinLevel'=> Log::INFO, // integer from LOG class
        // writter specific options
        'Header'=> '',          // do not send header
        'Formatter'=> 'Line',   // short name or FQCN or initialized object
    );

    protected $ZendLevels = array(  // translating levels to Zend standard
        Log::DEBUG    => 1,
        Log::INFO     => 2,
        Log::NOTICE   => 3,
        Log::WARNING  => 4,
        Log::ERROR    => 5,
        Log::CRITICAL => 6,
        Log::ALERT    => 7,
        Log::EMERGENCY=> 0,
    );



    public function __construct($Options = array()) {

        parent::__construct($Options);

        if (!function_exists('zend_monitor_custom_event')) {
            $this->Initied= false;
            $this->Error('Log/ZendMonitorLogWriter: Zend server not installed.', 3);
        }
    }


    protected function ProcessWrite($Message, $Level, $Data) {

        // StringifyMessage can reduce size od $Data array
        $this->StringifyMessage($Message, $Data);
        // format text line
        $Dump= $this->FormatFileLine($Message, $Level, $Data);
        // save
        $this->Store($Dump, $Level, $Data);
    }


    protected function Flush() {

        // messages cannot be joined
        // just send them in loop
        $Dump= array();
        foreach($this->Buffer as $Item) {
            list($Message, $Level, $Data, $Timestamp)= $Item;
            $this->StringifyMessage($Message, $Data);
            $Dump= $this->FormatFileLine($Message, $Level, $Data, $Timestamp);
            $this->Store($Message, $Level, $Data);
        }
    }


    /**
     * Perform storing content to Zend server
     */
    protected function Store($Message, $Level) {

        zend_monitor_custom_event($this->ZendLevels[$Level], $Message, $Data);
    }


}

?>