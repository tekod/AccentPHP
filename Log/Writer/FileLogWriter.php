<?php namespace Accent\Log\Writer;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Classic file storage writer, similar to Apache's access log.
 *
 * Bahivor of 'ClearOnStart' options:
 *  - false: - new records will be append to existing file
 *           - header will be repeated to divide previous and current HTTP requests
 *  - true: - file will be rewrited with header (only) on beginning of each request
 */

use Accent\Log\Writer\BaseLogWriter;
use Accent\Log\Log;


class FileLogWriter extends BaseLogWriter  {

    protected static $DefaultOptions= array(
        // mandatory options
        'Buffered'=> false,
        'MinLevel'=> Log::INFO, // integer from LOG class
        'ClearOnStart'=> false,
        // writter specific options
        'Path'=> '',            // path to storage file, will be resolved
        'FilePermition'=> 0664, // access permition for log file
        'Formatter'=> 'Line',   // short name or FQCN or initialized object
        'SeparationLine'=> '',  // "\n-------------------------------------"
    );

    protected $Path;            // resolved path


    public function __construct($Options = array()) {

        parent::__construct($Options);

        $this->Path= $this->ResolvePath($this->GetOption('Path'));

        if ($this->GetOption('ClearOnStart') == true) {
            // rewrite file with header only
            $this->SaveFile($this->GetHeader());
        }
    }


    protected function ProcessWrite($Message, $Level, $Data) {

        // StringifyMessage can reduce size od $Data array
        $this->StringifyMessage($Message, $Data);
        // format text line
        $Dump= $this->FormatFileLine($Message, $Level, $Data);
        // directory exist?
        if (!is_dir(dirname($this->Path))) {
            mkdir(dirname($this->Path), 0777, true);
        }
        // append to file
        $this->SaveFile($Dump, FILE_APPEND);
    }


    protected function Flush() {

        $Dump= array();
        foreach($this->Buffer as $Item) {
            list($Message, $Level, $Data, $Timestamp)= $Item;
            $this->StringifyMessage($Message, $Data);
            $Dump[]= $this->FormatFileLine($Message, $Level, $Data, $Timestamp);
        }
        // append to file
        $this->SaveFile(implode('',$Dump), FILE_APPEND);
    }


    /**
     * Perform storing content to file
     */
    protected function SaveFile($Dump, $Mode=0) {

        file_put_contents($this->Path, $Dump, $Mode);
        @chmod($this->Path, $this->GetOption('FilePermition'));
    }


}

?>