<?php namespace Accent\Log\Formatter;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


use Accent\AccentCore\Component;
use Accent\Log\Log;


class FileTableLogFormatter extends Component {


    protected static $DefaultOptions= array(
        'Columns'=> array(),
        'Services'=> array(
            'UTF'=> 'UTF',
        ),
    );

    protected $DefaultColumns= array(
        array('   Time   ',STR_PAD_BOTH),
        'Level          ',
        'Message                           ',
        array('Data         ',STR_PAD_LEFT),
    );

    protected $ColumnWidths;
    protected $ColumnAligns;
    protected $FileSeparatorLine;
    protected $FileTableHeader;


    public function __construct($Options = array()) {

        parent::__construct($Options);

        $Columns= $this->GetOption('Columns');
        if (empty($Columns)) {
            $Columns= $this->DefaultColumns;
        }
        $this->BuildColumns($Columns);
    }

    /**
     * Builds nice formated text line with from all supplied values.
     *
     * @param string $Message
     * @param int $Level
     * @param array $Data
     * @param int $Timestamp  provided by Flush method only
     */
    public function Format($Message, $Level, $Data, $Timestamp=null) {

        $Values= array(
            date("Y-m-d\nH:i:s", $Timestamp===null ? time() : $Timestamp),
            $this->GetOption('LoggerOptions.LoggerName')."\n".LOG::GetLevelName($Level),
            $Message,
        );

        $AdditionalCols= count($this->ColumnWidths)-4;
        if ($AdditionalCols > 0) {
            $Values[]= array_shift($Data);
        }

        $Values[]= version_compare(PHP_VERSION, '5.4.0', '>=')
            ? json_encode($Data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : json_encode($Data);

        foreach($Values as &$Item) {
            if (!is_scalar($Item)) { // stringify value
                $Item= version_compare(PHP_VERSION, '5.4.0', '>=')
                    ? json_encode($Data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : json_encode($Data);
            }
            $Item= explode("\n", $Item); // convert to array
        }

        $Line= $this->BuildMultilineRow($Values);

        return $this->GetTableHeader().$Line."\n".$this->FileSeparatorLine;
    }


    protected function BuildColumns($Columns) {

        $this->ColumnAligns= array();
        $this->ColumnWidths= array();

        // each column explode by '|', length of largest part will be column width
        foreach($Columns as $ColKey=>&$Col) {
            $this->ColumnAligns[$ColKey]= is_array($Col) ? end($Col) : STR_PAD_RIGHT;
            $this->ColumnWidths[$ColKey]= 0;
            $Col= explode('|', is_array($Col) ? reset($Col) : $Col);
            foreach($Col as $Line) {
                $ThisWidth= min(120, $this->GetService('UTF')->StrLen($Line)); // limit on 120 chars
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
        $this->FileTableHeader=
             $this->FileSeparatorLine."\n"
            .$HeaderLines."\n"
            .$this->FileSeparatorLine."\n";
    }


    protected function GetTableHeader() {

        $Header= $this->FileTableHeader;
        // clear it before return
        $this->FileTableHeader= '';
        // ret
        return $Header;
    }


    /**
     * Produce multiline textual string for appending to log file.
     *
     * @param array $Data  array of arrays
     */
    protected function BuildMultilineRow($Data) {

        $Rows= array_fill(0, count($Data), array());
        $DataValues= array_values($Data);
        $UTF= $this->GetService('UTF');
        foreach(array_keys($this->ColumnWidths) as $NumKey=>$ColKey) {
            // align all parts by adding spaces around them
            $Rows[$ColKey]= array();
            foreach($DataValues[$NumKey] as $Line) {
                // split too long parts in smaller chunks
                $ChunkedLines= $UTF->StrLen($Line) > $this->ColumnWidths[$ColKey]
                    ? $UTF->str_split($Line, $this->ColumnWidths[$ColKey])
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


    protected function Padded($String, $Length, $Direction=STR_PAD_RIGHT) {

        $UTF= $this->GetService('UTF');
        while($UTF->StrLen($String) < $Length) {
            switch ($Direction) {
                case STR_PAD_BOTH: $String= " $String "; break;
                case STR_PAD_LEFT: $String= " $String"; break;
                default: $String .= ' ';
            }
        }
        return $UTF->SubStr($String, 0, $Length);   // strip out extra chars
    }

}

?>