<?php namespace Accent\Security\Random\RandomGenerator;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * This generator is very weak.
 */

use \Accent\Security\Random\Random;
use \Accent\Security\Random\RandomGenerator\BaseGenerator;


class Rand extends BaseGenerator {


    protected $Strength= Random::WEAK;


    public function Generate($Length) {

        $Out= '';
        $OldRand= mt_rand(0, 255);

        for($x=$Length; $x>0; $x--) {
            $Rand= mt_rand(0, 255);
            $Out .= chr(($Rand + $OldRand + $x) % 256);
            $OldRand= $Rand;
        }
        return $Out;
    }
}
