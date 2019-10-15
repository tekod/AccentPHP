<?php namespace Accent\Mailer\Driver;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

use \Accent\Mailer\Driver\BaseDriver;
use \Accent\Mailer\Message;


class NoneDriver extends BaseDriver {


    /**
     * Silent dummy function.
     *
     * @param Message $Message  object of \Accent\Mailer\Message as single message
     * @return array  list of undelivered addresses
     */
    public function Send(Message $Message) {

        return array();
    }


    protected function DebugLog(array $Params) {

        $Path= $this->GetOption('LogFile');
        if (!$Path) {
            return;
        }

        $Dump= 'Log [NoneDriver] on '.date('r')."\n".str_repeat('=',60);
        $Dump .= "\n\nFields:\n".serialize($this->Fields);
        file_put_contents($Path, $Dump);
    }

}



?>