<?php namespace Accent\Security\Password;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Utilities for basic dealing with passwords,
 * in way how modern systems should work.
 *
 * In modern times it become required to store passwords as hashes into database
 * instead of plain form.
 * These methods will create, hash and verify passwords to achieve that standard.
 *
 * @TODO: break into "drivers" for each algorithm
 */

use \Accent\AccentCore\Component;


class Password extends Component {


    protected static $DefaultOptions= array(

        // specify hashing alorithym
        'DefaultHashAlgo'=> '$2y$09',   // "$1$" = md5
                                        // "2a$","$2y$" = blowfish
                                        // "$5$" = sha256
                                        // "$6$" = sha512
                                        // more: http://php.net/manual/en/function.crypt.php

        // services
        'Services'=> array(
            'Random'=> 'Random',
        ),
    );


    public function __construct($Options=array()) {

        parent::__construct($Options);
    }


    /**
     * Create string with random letters and numbers.
     * It is recomanded to strip off ambiguous characters from string to improve UX.
     *
     * @param int $Length (optional) desired length of password
     * @param string $CharList (optional) specify which characters can be contained in password
     * @return string
     */
    public function Create($Length=10, $CharList=null) {

        if ($CharList === null) {
            // use unambiguous characters
            $CharList= 'qwertzupasdfghjkyxcvbnmQWERTZUPASDFGHJKLYXCVNM2345679';
        }
        return $this->GetService('Random')->GetRandomString($Length, $CharList);
    }


    /**
     * Make hash of password.
     *
     * @param string $Password
     * @param array $Options
     * @return string|boolean
     */
    public function Hash($Password, $Options=array()) {

        $Options += array(
            'Algo'=> null,  // DefaultOptions->DefaultHashAlgo will be used
            'Salt'=> null,  // will be generated
        );
        if (strlen($Password) > 512) {
            return false;
        }
        $Algo= ($Options['Algo'] === null)
            ? $this->GetOption('DefaultHashAlgo')
            : $Options['Algo'];
        $Salt= ($Options['Salt'] === null)
            ? $this->GetService('Random')->GetRandomString(22,
                    'qwertzuiopasdfghjklyxcvbnmQWERTZUIOPASDFGHJKLYXCVBNM1234567890./')
            : substr($Options['Salt']);
        if (PHP_VERSION_ID < 50307) {
            // $2y$ is unsupported prior to PHP 5.3.7)
            $Algo= str_replace('$2y$', '$2a$', $Algo);
        }
        $Hash= crypt($Password, $Algo.'$'.$Salt);
        if (strlen($Hash) < 60) {
            return false;
        }
		return $Hash;
    }


    /**
     * Verify user's password against hash loaded from database.
     *
     * @param string $Password
     * @param string $HashFromDB
     * @return boolean
     */
    public function Verify($Password, $HashFromDB) {

        if (strlen($Password) > 512) {
            return false;
        }
        $Test= crypt($Password, $HashFromDB);
        return $this->SafeCompareStrings($Test, $HashFromDB);
    }


    /**
     * Check is hash from database compatibile with default hashing algo.
     *
     * @param string $Hash
     * @return boolean
     */
    public function NeedToRehash($Hash) {

        $Parts= explode('$', $Hash);
        $Algo= '$'.$Parts[1].'$'.$Parts[2];
        return $Algo <> $this->GetOption('DefaultHashAlgo');
    }


    /**
     * Compare two string while preventing time-attack.
     *
     * Execution of this method will always take same time,
     * no matter how different strings are.
     *
     * @param string $Str1
     * @param string $Str2
     * @return boolean
     */
    public function SafeCompareStrings($Str1, $Str2) {

        if (strlen($Str1) <> strlen($Str2)) {
            return false;
        }

        return array_sum(unpack('C*', $Str1 ^ $Str2)) === 0;
    }


}
