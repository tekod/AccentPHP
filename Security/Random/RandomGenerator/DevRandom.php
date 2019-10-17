<?php namespace Accent\Security\Random\RandomGenerator;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 *********************************************************
 * DEPRECATED!!!
 * Reading from /dev/random can freeze execution!
 **********************************************************
 *
 * This generator can take very long time to generate output.
 *
 * Use it only when really needed.
 */

use \Accent\Security\Random\Random;
use \Accent\Security\Random\RandomGenerator\BaseGenerator;


class DevRandom extends BaseGenerator {


    protected $Strength= Random::STRONG;


    public function Generate($Length) {

        // try to load using mcrypt extension becouse access to /dev/random can be
        // blocked by open_basedir directive
        $Result= $this->LoadWithMCrypt($Length);

        if (!$Result) {
            // load using direct access to file
            $Result= $this->LoadWithFOpen($Length);
        }

        return $Result;
    }


    protected function LoadWithFOpen($Length) {

        $File= '/dev/random';

        if (!is_readable($File)) {
            return false;
        }

        $fp= fopen($File, 'rb');
        if (!$fp) {
            return false;
        }

        if (function_exists('stream_set_read_buffer')) {
            stream_set_read_buffer($fp, 0);
        }

        $Result= fread($fp, $Length);
        fclose($fp);

        return $Result;
    }


    protected function LoadWithMCrypt($Length) {

        if (function_exists('mcrypt_create_iv') && (PHP_VERSION_ID >= 50307 || DIRECTORY_SEPARATOR !== '\\')) { // PHP bug #52523
			return mcrypt_create_iv($Length, \MCRYPT_DEV_RANDOM);
        } else {
            return false;
        }
    }
}
