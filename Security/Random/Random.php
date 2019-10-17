<?php namespace Accent\Security\Random;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


use \Accent\AccentCore\Component;


class Random extends Component {

    protected static $DefaultOptions= array(

        // list of generators
        'Generators'=> array(
            //'\\Accent\\Security\\Random\\RandomGenerator\\DevRandom', DEPRECATED!!!
            '\\Accent\\Security\\Random\\RandomGenerator\\DevURandom',
            '\\Accent\\Security\\Random\\RandomGenerator\\OpenSSL',
            '\\Accent\\Security\\Random\\RandomGenerator\\Rand',
            '\\Accent\\Security\\Random\\RandomGenerator\\UniqId',
            '\\Accent\\Security\\Random\\RandomGenerator\\RandomBytes',
        ),
    );

    const WEAK   = 0;
    const NORMAL = 1;
    const STRONG = 2;

    protected $Generators= array();


    public function __construct($Options=array()) {

        parent::__construct($Options);
    }


    /**
     * Return binary string with random bytes.
     *
     * @param int $Length
     * @param bool $UseStrong
     * @return string
     */
    public function GetRandomBytes($Length, $UseStrong=false) {

        $Length= intval($Length);
        // limits
        if ($Length < 1  || $Length > 64000) {
            return '';
        }
        if (empty($this->Generators)) {
            foreach($this->GetOption('Generators') as $FQCN) {
                $this->Generators[]= new $FQCN;
            }
        }
        $DesiredStrength= ($UseStrong)
            ? static::STRONG
            : static::NORMAL;
        // prepare buffer
        $Result= str_repeat(chr(0), $Length);
        // loop
        foreach($this->Generators as $Generator) {
            // skip strongest generators to save time
            if ($Generator->GetStrength() > $DesiredStrength) {
                continue;
            }
            // generate
            $Res= $Generator->Generate($Length);
            // don't process false results
            if ($Res) {
                // XOR each result with current buffer
                $Result ^= $Res;
            }
        }
        return $Result;
    }



    /**
     * Return string consist of characters passed in $CharList.
     * Note that this method does NOT support UTF8 characters.
     *
     * @param int $Length
     * @param string $CharList
     * @param bool $UseStrong
     * @return string
     */
    public function GetRandomString($Length, $CharList=null, $UseStrong=false) {

        if ($CharList === null) {
            $CharList= 'qwertzuiopasdfghjklyxcvbnm1234567890';
        }
        // get entropy
        $Random= $this->GetRandomBytes($Length, $UseStrong);
        // shuffle allowed characters
        $CharList= str_shuffle($CharList);
        $CharListLen= strlen($CharList);
        $Ratio= ($CharListLen-1) / 255;
        // loop
        $Result= '';
		for ($i= 0; $i < $Length; $i++) {
            $Pos= intval(round(ord($Random[$i]) * $Ratio));
            $Result .= $CharList{$Pos};
		}
		return $Result;
    }



}
