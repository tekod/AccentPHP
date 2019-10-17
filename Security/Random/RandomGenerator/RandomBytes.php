<?php namespace Accent\Security\Random\RandomGenerator;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * This generator can be used for most tasks for PHP7 environment.
 */

use \Accent\Security\Random\Random;
use \Accent\Security\Random\RandomGenerator\BaseGenerator;


class RandomBytes extends BaseGenerator {


    protected $Strength= Random::STRONG;


    public function Generate($Length) {

        if (function_exists('random_bytes')) {
            return random_bytes($Length);
        } else {
            return false;
        }
    }
}

?>