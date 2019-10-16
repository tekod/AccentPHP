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
use Accent\AccentCore\Debug\Debug;


class BackTraceLogAcquisitor extends Component {


    protected static $DefaultOptions= array(
        // mandatory
        'MinLevel'=> Log::ERROR,
        // optional
        'FullDetails'=> false,
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

        $Stack= Debug::ShowStack(false);
        unset($Stack[0]);
        if ($this->GetOption('FullDetails',false) === true) {
            // reorder array and return
            $List= array_values($Stack);
        } else {
            $DefaultAccentRootPath= dirname(dirname(dirname(dirname(__FILE__))));
            $AccentRootPath= $this->GetOption('Paths.AccentDir', $DefaultAccentRootPath);
            $AccentRootLen= strlen($AccentRootPath);
            $List= array();
            foreach($Stack as $Point) {
                // try to shorten path
                $Path= (substr($Point[0],0,$AccentRootLen) === $AccentRootPath && $AccentRootLen > 4)
                    ? '@'.substr($Point[0],$AccentRootLen)
                    : $Point[0];
                $List[]= $Path;
            }
        }
        return array(
            'BackTrace'=> $List,
        );
    }


}

?>