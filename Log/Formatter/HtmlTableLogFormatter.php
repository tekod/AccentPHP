<?php namespace Accent\Log\Formatter;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * HtmlTable formatter can be used for logging HTML content
 * which will be stored in *.html file and later viewed by web browser.
 *
 * Content is not escaped.
 * Developer should sanitize source before logging it to prevent XSS issues.
 *
 * Becouse it is hard to achive proper sanitization we strongly discourage
 * using this formatter for logging user-generated content.
 */

use Accent\AccentCore\Component;
use Accent\Log\Log;


class HtmlTableLogFormatter extends Component {

    protected static $DefaultOptions= array(
        'SeparationLine'=> '',  // "\n-------------------------------------"
    );


    protected $Colors = array(
        Log::EMERGENCY => '#FF0000',
        Log::ALERT     => '#FF6000',
        Log::CRITICAL  => '#C08020',
        Log::ERROR     => '#808020',
        Log::WARNING   => '#009000',
        Log::NOTICE    => '#008080',
        Log::INFO      => '#0040B0',
        Log::DEBUG     => '#808080',
    );


    /**
     * Builds nice formated text line with from all supplied values.
     *
     * @param string $Message
     * @param int $Level
     * @param array $Data
     * @param int $Timestamp  provided by Flush method only
     */
    public function Format($Message, $Level, $Data, $Timestamp=null) {

        $Color= isset($this->Colors[$Level]) ? $this->Colors[$Level] : '#000000';

        $Time= date('Y-m-d H:i:s', $Timestamp===null ? time() : $Timestamp);
        $Logger= $this->GetOption('LoggerOptions.LoggerName');
        $DataArray= version_compare(PHP_VERSION, '5.4.0', '>=')
            ? json_encode($Data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : json_encode($Data);

        $HTML= '<table style="width:100%;padding-top:2em">'
           .'<tr><th style="background:#f4f4f4;text-align:left;font-weight:normal;width:20%;border:1px solid gray" rowspan="2">'
           .$Time
           .'<div style="color:#808080;padding:0.5em 0 0">'.htmlspecialchars($Logger,ENT_NOQUOTES,'UTF-8').'</div>'
           .'<strong style="color:'.$Color.'">'.LOG::GetLevelName($Level).'</strong>'
           .'</th><td style="text-align:left;background:#fff;padding:5px;border:1px solid gray">'
           .$Message
           .'</td></tr>'
           .'<tr><td style="text-align:left;background:#f4f4f4;border:1px solid gray">'
                .htmlspecialchars($DataArray,ENT_NOQUOTES,'UTF-8')
           .'</td></tr></table>';

        return $HTML . $this->GetOption('SeparationLine');
    }


}

?>