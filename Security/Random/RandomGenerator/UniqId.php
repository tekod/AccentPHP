<?php namespace Accent\Security\Random\RandomGenerator;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * This is weak generator, it uses uniqid() function which internaly utilisied
 * microtime() to generate output.
 */

use \Accent\Security\Random\Random;
use \Accent\Security\Random\RandomGenerator\BaseGenerator;


class UniqId extends BaseGenerator {


    protected $Strength= Random::WEAK;


    public function Generate($Length) {

        $Result= '';
        while (strlen($Result) < $Length) {
            $ID= uniqid('', true);
            $Int= intval(substr($ID, strpos($ID, '.')+1));
            $Result .= chr($Int % 256) . chr(($Int >> 8) % 256) . chr(($Int >> 16) % 256);
        }
        return substr($Result, 0, $Length);
    }
}

?>