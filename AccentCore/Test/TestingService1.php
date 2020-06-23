<?php namespace Accent\AccentCore\Test;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


/**
 * Simple test service for string decorations
 */


class TestingService1 {


    protected $Opts;


    protected $DefaultOpts= array(
        'Decor'=> '-',  // single line
    );

    protected $Id;


    public function __construct(array $Options) {

        $this->Opts= $Options + $this->DefaultOpts;
        $this->Id= rand(0, 999999999);
    }


    public function GetId() {

        return $this->Id;
    }


    public function Decorate($Txt) {
        // wrap with one char
        return $this->Opts['Decor'] . $Txt . $this->Opts['Decor'];
    }


}

