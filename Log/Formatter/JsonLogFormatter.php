<?php namespace Accent\Log\Formatter;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


use Accent\AccentCore\Component;
use Accent\Log\Log;


class JsonLogFormatter extends Component {

    protected static $DefaultOptions= array(
        'SeparationLine'=> '',  // "\n-------------------------------------"
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

        $Array= array(
            'Time'=> date('Y-m-d H:i:s', $Timestamp===null ? time() : $Timestamp),
            'Logger'=> $this->GetOption('LoggerOptions.LoggerName'),
            'Level'=> LOG::GetLevelName($Level),
            'Msg'=> $Message,
            'Data'=> $Data,
        );

        $Line= version_compare(PHP_VERSION, '5.4.0', '>=')
            ? json_encode($Array, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : json_encode($Array);

        return $Line . $this->GetOption('SeparationLine');
    }


}

?>