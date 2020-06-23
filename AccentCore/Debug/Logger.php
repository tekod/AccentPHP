<?php namespace Accent\AccentCore\Debug;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Simple but powerful logger system,
 * portable and independent of other AccentPHP classes, evan AccentCore package.
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
     * @param bool $Enabled  permission
     * @param bool $Overwrite  whether to overwrite log file on start ot not
     * @param string $SeparatorLine  string to insert after each log item
     * @param bool $Timestamps  whether to prepend current timestamp to each log item or not
     * @param int|false $SizeLimit  delete oldest entries to maintain size of log file (in bytes), false for no-limit, default 1 Mb
     */
    public function __construct($LogFile, $Caption= '', $Enabled=true, $Overwrite=true, $SeparatorLine="\n----", $Timestamps=true, $SizeLimit=1048576) {

        $this->Enabled= (bool)$Enabled;
        $this->LoggerFile= $LogFile;
        $this->SeparatorLine= $SeparatorLine;
        $this->Timestamps= $Timestamps;
        if (!$this->Enabled || !$this->LoggerFile) {
            return;
        }

        // ensure existence of directory
        $Dir= dirname($this->LoggerFile);
        if (!is_dir($Dir)) {
            mkdir($Dir, 0777, true);
        }

        // prepare log file
        if ($Overwrite) {
            $Dump= "$Caption\n(timestamp: ".date('r').")".$this->SeparatorLine;
            file_put_contents($this->LoggerFile, $Dump);
        } else {
            touch($this->LoggerFile);   // appending require that file exist
        }

        // resize log file
        if ($SizeLimit !== false) {
            $this->ResizeFile($SizeLimit);
        }
    }


    /**
     * Store new entry into log.
     *
     * @param string $Message  arbitrary text to log
	 * @param bool $ShowStack  append call stack
     */
    public function Log($Message, $ShowStack=false) {

        if (!$this->Enabled || !$this->LoggerFile) {
            return;
        }

        // prepare heading
        $Heading= [];
        if ($this->Timestamps) {
            $Heading[]= date('Y-m-d H:i:s');
        }
        if ($ShowStack) {
            $Heading[]=  '['.$_SERVER['REQUEST_URI'].'] >> '.\Accent\AccentCore\Debug\Debug::ShowShortStack(' > ');
        }
        $Heading= empty($Heading)
            ? ''
            : implode(' ', $Heading)."\n";

        // add to storage
        $Dump= "\n".$Heading.$Message.$this->SeparatorLine;
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


    /**
     * Empties log file.
     */
    public function Clear() {

        file_put_contents($this->LoggerFile, '');
    }


    /**
     * Trim log file to maintain it size.
     *
     * @param int $SizeLimit  maximum size in bytes
     */
    protected function ResizeFile($SizeLimit) {

        $FileSize = filesize($this->LoggerFile);
        if ($FileSize > $SizeLimit) {
            $Dump = $FileSize > 8 * 1024 * 1024
                ? ''            // 8 Mb is too big to fit in memory, just empty file
                : '  .  .  .  . . . ......' . substr(file_get_contents($this->LoggerFile), -($SizeLimit / 2));
            file_put_contents($this->LoggerFile, $Dump);
        }
    }

}
