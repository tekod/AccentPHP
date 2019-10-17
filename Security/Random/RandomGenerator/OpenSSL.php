<?php namespace Accent\Security\Random\RandomGenerator;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * This generator can be used for most tasks.
 */

use \Accent\Security\Random\Random;
use \Accent\Security\Random\RandomGenerator\BaseGenerator;


class OpenSSL extends BaseGenerator {


    protected $Strength= Random::STRONG;


    public function Generate($Length) {

        if ((PHP_VERSION_ID >= 50400 || DIRECTORY_SEPARATOR !== '\\')
                && function_exists('openssl_random_pseudo_bytes')) {
            return openssl_random_pseudo_bytes($Length);
            // not needed to test second parameters of this function becouse result
            // will be mixed from several sources so that will not influence so much.
        } else {
            return false;
        }
    }
}

?>