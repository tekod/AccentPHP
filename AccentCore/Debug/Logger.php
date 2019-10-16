<?php namespace Accent\AccentCore\Debug;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Simple logger system,
 * portable, independent of other AccentPHP classes, evan AccentCore package.
 */


class Logger {

    // internal properties
    protected $Enabled;
    protected $LoggerFile;
    protected $SeparatorLine;
    protected $Timestamps;


    /**
     * Constructor.
     *
     * @param string|bool $LogFile  full path to output logging file
     * @param string $Caption  main title of report file
     * @param bool $Enabled  permition
     * @param bool $Overwrite  whether to overwrite log file on start ot not
     * @param string $SeparatorLine  string to insert after each log item
     * @param bool $Timestamps  whether to prepend current timestamp to each log item or not
     */
    public function __construct($LogFile, $Caption= '', $Enabled=true, $Overwrite=true, $SeparatorLine="\n----", $Timestamps=true) {

        $this->Enabled= (bool)$Enabled;
        $this->LoggerFile= $LogFile;
        $this->SeparatorLine= $SeparatorLine;
        $this->Timestamps= $Timestamps;
        if (!$this->Enabled || !$this->LoggerFile) {
            return;
        }
        // ensure existance of directory
        $Dir= dirname($this->LoggerFile);
        if (!is_dir($Dir)) {
            mkdir($Dir, 0777, true);
        }
        if ($Overwrite) {
            $Dump= "$Caption\n(timestamp: ".date('r').")".$this->SeparatorLine;
            file_put_contents($this->LoggerFile, $Dump);
        } else {
            touch($this->LoggerFile);   // appending require that file exist
        }
    }


    /**
     * Store new entry into log.
     *
     * @param string $Message  arbitrary text to log
     */
    public function Log($Message) {

        if (!$this->Enabled || !$this->LoggerFile) {
            return;
        }

        // prepare timestamp
        $Timestamp= $this->Timestamps ? date('Y-m-d H:i:s').' ' : '';

        // add to storage
        $Dump= "\n".$Timestamp.$Message.$this->SeparatorLine;
        file_put_contents($this->LoggerFile, $Dump, FILE_APPEND);
    }


    /**
     * Enable or disable logging.
     *
     * @param bool $Enabled
     */
    public function Enable($Enabled) {

        $this->Enabled= (bool)$Enabled;
    }

}

?>