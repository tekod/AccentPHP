<?php namespace Accent\AccentCore\Debug;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Simple logger system, portable, independent of other AccentPHP classes.
 * This class is not descendant of Component class in order to be operative as early as possible.
 */


class TLogger {

    // permission to work
    protected $Enabled;

    // as string to specify path of log file
    // as boolean true to keep logs in memory
    protected $LoggerFile= false;

    // buffer of logged data
    protected $Data;

    // content of log header,
    // in case of persistent log it will be stored before each session
    protected $FileHeader;

    // width of each column (in chars)
    protected $ColumnWidths;

    // alignment of each column (as STR_PAD_LEFT, STR_PAD_RIGHT, STR_PAD_BOTH)
    protected $ColumnAligns;

    // chosen utf8 solution
    protected $UtfEngine;

    // content of top of log file, not alterable via constructor
    protected $StdReportFileHeader= "<?php __halt_compiler();\n\n";

    // content of divider
    protected $FileSeparatorLine;


    /**
     * Constructor.
     *
     * @param string|bool $LogFile  full path to output logging file, '.php' will be appended or true to store logs in memory
     * @param string $Caption  main title of report file
     * @param array $Columns  titles of columns, '|' char is multiline separator
     * @param bool $Enabled  permission
     * @param bool $Overwrite  whether to overwrite log file on start or not
     */
    public function __construct($LogFile, $Caption= '', $Columns=array(), $Enabled=true, $Overwrite=true) {

        $this->SetupUTF();
        $this->BuildHeader($Caption, $Columns);
        $this->Enabled= $Enabled;
        $this->LogData= array();

        if (is_string($LogFile)) {
            // ensure output file extension
            touch($LogFile.'.php');
            $this->LoggerFile= realpath($LogFile.'.php');
            // clear file
            if ($this->Enabled && $Overwrite) {
                // only for authorized visitor can overwrite existing log file
                file_put_contents($this->LoggerFile, $this->StdReportFileHeader.$this->FileHeader);
            }
        } else {
            // in-memory log
            $this->LoggerFile= $LogFile;
        }
    }


    /**
     * Prepare content of log header.
     *
     * @param array $Caption  labels of table columns
     * @param array $Columns  definitions of table columns
     */
    protected function BuildHeader($Caption, $Columns) {

        $this->ColumnAligns= array();
        $this->ColumnWidths= array();

        // each column explode by '|', length of largest part will be column width
        foreach($Columns as $ColKey=>&$Col) {
            $this->ColumnAligns[$ColKey]= is_array($Col) ? end($Col) : STR_PAD_RIGHT;
            $this->ColumnWidths[$ColKey]= 0;
            $Col= explode('|', is_array($Col) ? reset($Col) : $Col);
            foreach($Col as $Line) {
                $ThisWidth= min(120, $this->UtfStrLen($Line)); // limit on 120 chars
                $this->ColumnWidths[$ColKey]= max($this->ColumnWidths[$ColKey], $ThisWidth);
            }
        }
        $HeaderLines= $this->BuildMultilineRow($Columns);

        // build separator line
        $this->FileSeparatorLine= '';
        foreach($this->ColumnWidths as $Width) {
            $this->FileSeparatorLine .= str_repeat('-', $Width) . '+';
        }
        $SeparatorLen= strlen($this->FileSeparatorLine);
        $this->FileSeparatorLine= $SeparatorLen < 80
            ? substr($this->FileSeparatorLine, 0, -1).str_repeat('-', 81-$SeparatorLen)
            : substr($this->FileSeparatorLine, 0, -1);

        // extend width of last column to 80-char margin
        if ($SeparatorLen < 80) {
            $Keys= array_keys($Columns);
            $this->ColumnWidths[end($Keys)] += 81-$SeparatorLen;
        }

        // pack header
        $this->FileHeader=
             "\n\n".$Caption
            ."\n(timestamp: ".date('r').")"
            ."\n\n".$this->FileSeparatorLine
            ."\n".$HeaderLines
            ."\n".$this->FileSeparatorLine;
    }


    /**
     * Produce multiline textual string for appending to log file.
     *
     * @param array $Data  array of arrays
     * @return string
     */
    protected function BuildMultilineRow($Data) {

        $Rows= array_fill(0, count($Data), array());
        $DataValues= array_values($Data);
        foreach(array_keys($this->ColumnWidths) as $NumKey=>$ColKey) {
            // align all parts by adding spaces around them
            $Rows[$ColKey]= array();
            foreach($DataValues[$NumKey] as $Line) {
                // split too long parts in smaller chunks
                $ChunkedLines= $this->UtfStrLen($Line) > $this->ColumnWidths[$ColKey]
                    ? $this->Split($Line, $this->ColumnWidths[$ColKey])
                    : array($Line);
                foreach($ChunkedLines as $L) {
                    $Rows[$ColKey][]= $this->Padded($L, $this->ColumnWidths[$ColKey], $this->ColumnAligns[$ColKey]);
                }
            }
        }
        $Result= array();
        for($y=0; $y<999; $y++) {
            $RowLine= array();
            $Found= false;
            foreach(array_keys($this->ColumnWidths) as $ColKey) {
                $RowLine[]= isset($Rows[$ColKey][$y])
                    ? $Rows[$ColKey][$y]
                    : str_repeat(' ', $this->ColumnWidths[$ColKey]);
                $Found |= isset($Rows[$ColKey][$y]);
            }
            if (!$Found) {
                break;
            }
            $Result[]= implode('|', $RowLine);
        }
        return implode("\n", $Result);
    }



    /**
     * Store new entry into log.
     *
     * @param array $DataArray
     */
    public function Log($DataArray) {

        if (!$this->Enabled) {
            return;
        }

        // add to storage
        $this->Data[]= $DataArray;

        // append to log file
        if (is_string($this->LoggerFile)) {
            $Dump= "\n".$this->FormatFileLine($DataArray)."\n".$this->FileSeparatorLine;
            file_put_contents($this->LoggerFile, $Dump, FILE_APPEND);
        }
    }


    /**
     * Format supplied data into string as table-row.
     *
     * @param array $Data
     * @return string
     */
    protected function FormatFileLine($Data) {

        foreach($Data as &$Item) {
            // convert $Item to string (integers, floats, arrays to CSV)
            // pad strings according to Col record
            // handle multiline values
            $Item= explode("\n", $Item); // convert to array
        }
        return $this->BuildMultilineRow($Data);
    }


    /**
     * Return log entries.
     *
     * @param bool $FromFile  return content of log file instead of internal log buffer
     * @return mixed
     */
    public function GetData($FromFile=true) {

        if (!$this->Enabled) {
            return false;
        }
        if (!$FromFile) {
            return $this->Data; // export array for both storage variants
        }
        if ($this->LoggerFile === true) {
            return false;  // sorry, in-memory log has no file
        }
        return is_file($this->LoggerFile) // return file content
            ? file_get_contents($this->LoggerFile)
            : '';
    }




//---------------------------------------------------------------------------
//
//                    Helper functions
//
//---------------------------------------------------------------------------


    protected function SetupUTF() {

        if (function_exists('mb_strlen')) {
	        $this->UtfEngine= 'mbstring';
	        mb_internal_encoding('UTF-8');
	    } else if (function_exists('iconv_strlen')) {
	        $this->UtfEngine= 'iconv';
            iconv_set_encoding('internal_encoding','UTF-8');
        } else {
            $this->UtfEngine= 'none';
        }
    }


    protected function UtfStrLen($String) {

		if ($this->UtfEngine === 'mbstring') {
			return mb_strlen($String, 'UTF-8');
		}
		if ($this->UtfEngine === 'iconv') {
			return iconv_strlen($String, 'UTF-8');
    	}
   		return strlen(preg_replace("/[\x80-\xBF]/", '', $String));
    }


    protected function UtfSubStr($String, $Start, $Length=null) {

        static $PCREUTF= null;

        if ($this->UtfEngine === 'mbstring') {
	        if ($Length === null) {
                $Length= mb_strlen($String);
            }
	        return mb_substr($String, $Start, $Length, 'UTF-8');
	    }
	    if ($this->Engine === 'iconv') {
	        if ($Length === null)	{
	            return iconv_substr($String, $Start, $this->strlen($String), 'UTF-8');
	        }
	        return iconv_substr($String, $Start, $Length, 'UTF-8');
	    }
        if ($PCREUTF === null) {
            $PCREUTF= @preg_match( '//u', '' );
        }
        if ($PCREUTF) {
            $Array= preg_split('/(?<!^)(?!$)/u', $String);
            return implode(array_slice($Array, $Start, $Length));
        }
        // no mbstring, no iconv, no PCRE with UTF support? ok, return std substr:
        return substr($String, $Start, $Length);
    }


    protected function Padded($String, $Length, $Direction=STR_PAD_RIGHT) {

        while($this->UtfStrLen($String) < $Length) {
            switch ($Direction) {
                case STR_PAD_BOTH: $String= " $String "; break;
                case STR_PAD_LEFT: $String= " $String"; break;
                default: $String .= ' ';
            }
        }
        return $this->UtfSubStr($String, 0, $Length);   // strip out extra chars
    }


    protected function Split($String, $ChunkLength=1) {

        static $PCREUTF= null;

        $Result= array();
        if ($this->UtfEngine === 'mbstring') {
            $StrLength= mb_strlen($String);
            for($x=0; $x<$StrLength; $x=$x+$ChunkLength) {
                $Result[]= mb_substr($String, $x, $ChunkLength);
            }
            return $Result;
        }
        if ($this->UtfEngine === 'iconv') {
            $StrLength= iconv_strlen($String);
            for($x=0; $x<$StrLength; $x=$x+$ChunkLength) {
                $Result[]= iconv_substr($String, $x, $ChunkLength);
            }
            return $Result;
        }
        if ($PCREUTF === null) {
            $PCREUTF= @preg_match( '//u', '' );
        }
        if ($PCREUTF) {
            $Array= preg_split('/(?<!^)(?!$)/u', $String);
            $StrLength= count($Array);
            for($x=0; $x<$StrLength; $x=$x+$ChunkLength) {
                $Result[]= implode('', array_slice($Array, $x, $ChunkLength));
            }
            return $Result;
        }
        return str_split($String, $ChunkLength);
    }


}
