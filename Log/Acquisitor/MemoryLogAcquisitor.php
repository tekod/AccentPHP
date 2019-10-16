<?php namespace Accent\Log\Acquisitor;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


use Accent\AccentCore\Component;
use Accent\Log\Log;
//use Accent\AccentCore\Debug\Debug;


class MemoryLogAcquisitor extends Component {


    protected static $DefaultOptions= array(
        // mandatory
        'MinLevel'=> Log::INFO,
        // optional
        'GetPeakUsage'=> false, // use memory_get_usage or memory_get_peak_usage
        'RealUsage'=> true,     // boolean option for both functions
        'AsInteger'=> false,    // return as number or formated like "4.1 Mb"
    );


    /**
     * Returns additional data to log servicer.
     *
     * @param string $Message
     * @param int $Level
     * @param array $Data
     */
    public function GetData($Message, $Level, $Data) {

        if ($Level > $this->GetOption('MinLevel')) {
            return array();
        }

        $RealUsageOption= $this->GetOption('RealUsage') == true;

        if ($this->GetOption('GetPeakUsage')) {
            $Usage= memory_get_peak_usage($RealUsageOption);
        } else {
            $Usage= memory_get_usage($RealUsageOption);
        }

        if (!$this->GetOption('AsInteger')) {
            $Usage= $this->FormatGMK($Usage);
        }

        return array(
            'MemoryUsage'=> $Usage,
        );
    }



    protected function FormatGMK($n) {

        if ($n == 0) {
            return '0 b';
        }
        $Sufix = array('', 'k', 'M', 'G', 'T');
        $loop = 0;
        while((($n / 1024) >= 1) and ($loop < 5)) {
            $loop++;
            $n = $n / 1024;
        }
        $Decimals= $n < 10 ? 2 : 1;
        $Res = number_format($n, $Decimals);
        return "$Res $Sufix[$loop]b";
    }


}

?>