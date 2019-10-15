<?php namespace Accent\Log\Writer;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Writing logs to system's log files using error_log() function.
 *
 * More:
 * - http://php.net/manual/en/function.error-log.php
 * - http://php.net/manual/en/errorfunc.configuration.php#ini.error-log
 */

use Accent\Log\Writer\BaseLogWriter;
use Accent\Log\Log;


class ErrorlogLogWriter extends BaseLogWriter  {

    protected static $DefaultOptions= array(
        // mandatory options
        'Buffered'=> false,
        'MinLevel'=> Log::INFO, // integer from LOG class
        'ClearOnStart'=> false,
        // writter specific options
        'ToSAPI'=> false,       // sent directly to the SAPI logging handler
    );



    protected function ProcessWrite($Message, $Level, $Data) {

        // StringifyMessage can reduce size od $Data array
        $this->StringifyMessage($Message, $Data);
        $Message= $this->FormatFileLine($Message, $Level, $Data);
        // select where message will be stored
        $MsgType= $this->GetOption('ToSAPI') ? 4 : 0;
        // system call
        error_log($Message, $MsgType);
    }


    protected function Flush() {

        $MsgType= $this->GetOption('ToSAPI') ? 4 : 0;
        foreach($this->Buffer as $Item) {
            list($Message, $Level, $Data, $Timestamp)= $Item;
            $Message= $this->FormatFileLine($Message, $Level, $Data, $Timestamp);
            error_log($Message, $MsgType);
        }
    }

}

?>