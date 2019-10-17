<?php namespace Accent\Security\Random\RandomGenerator;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Base class for all random generators.
 */

use \Accent\Security\Random\Random;


class BaseGenerator {


    /**
     * Mark strength of this generator
     */
    protected $Strength= Random::NORMAL;


    /**
     * Main worker function.
     *
     * @param int $Length
     * @return string
     */
    public function Generate($Length) {

        return str_repeat('a', $Length);
    }


    /**
     * Return strength of this generator.
     *
     * @return const Random::STRONG|Random::WEAK
     */
    public function GetStrength() {

        return $this->Strength;
    }
}

?>