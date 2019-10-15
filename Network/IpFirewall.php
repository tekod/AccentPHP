<?php namespace Accent\Network;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * IpFirewall is upgraded version of IpRange, containing two lists (whitelist and blacklist)
 * and featuring caching of compiled rules to maximize performances.
 * Application can utilize both or only one list for it's business logic, note that method Check() will return null
 * if supplied IP address wasn't found in any list, application must decide what to do in such cases.
 *
 * Typical usage of this component is protecting administrative part of website allowing access only from expecting IP
 * locations.
 * Another typical usage can be blocking visitors from certain countries or blocking abusive visitors (bots) detected
 * by honeypots or antiflood systems.
 *
 * Usage:
 *    $FW= new IpFirewall();
 *    $FW->GetWhiteList()->Append('127.0.0.1');
 *    $FW->GetWhiteList()->Append('::1');   // or specify all rules within constructor options or use ImportCompiledRules()
 *    if ($FW->Check($VisitorIP) !== true) die('Foridden access.');
 *
 */

use Accent\AccentCore\Component;


class IpFirewall extends Component {


    protected static $DefaultOptions= array(

        // list of strings that describe ranges or exact addresses of allowed IP addresses
        'WhiteList'=> array(),

        // list of strings that describe ranges or exact addresses of forbiden IP addresses
        'BlackList'=> array(),
    );

    // internal properties
    protected $WhiteList;
    protected $BlackList;


    /**
     * Constructor
     */
    public function __construct($Options=array()) {

        // call parent
        parent::__construct($Options);

        // instantiate collections
        $this->WhiteList= $this->GetOption('WhiteList');
        if (!is_object($this->WhiteList)) {
            $this->WhiteList = new IpRange($this->WhiteList);
        }
        $this->BlackList= $this->GetOption('BlackList');
        if (!is_object($this->BlackList)) {
            $this->BlackList = new IpRange($this->GetOption('BlackList'));
        }
    }


    /**
     * Returns collection object of WhiteList.
     *
     * @return \Accent\Network\IpRange
     */
    public function GetWhiteList() {

        return $this->WhiteList;
    }


    /**
     * Returns collection object of BlackList.
     *
     * @return \Accent\Network\IpRange
     */
    public function GetBlackList() {

        return $this->BlackList;
    }


    /**
     * Search for supplied IP in both lists,
     * return true if it was found in whitelist, false if found in blacklist and null if wasn't found anywhere.
     *
     * @param string $IP
     * @return bool|null
     */
    public function Check($IP) {

        // first check whitelist, return immidiately if it found there
        if ($this->WhiteList->InRange($IP)) {
            return true;
        }

        // now check within blacklist and return false if found there
        if ($this->BlackList->InRange($IP)) {
            return false;
        }

        // this IP has not found anywhere, leave it to application to choose what to do
        return null;
    }


    /**
     * Returns string with binary pack of compiled lists.
     * String can be stored in external file and loaded later by ImportCompiledRules.
     */
    public function ExportCompiledRules() {

        $this->WhiteList->CompileAllRules();
        $this->BlackList->CompileAllRules();

        return json_encode(array(
            'WhiteList'=> $this->WhiteList->ToArray(),
            'BlackList'=> $this->BlackList->ToArray(),
        ), JSON_UNESCAPED_UNICODE);
    }


    /**
     * Loader of pack previously get from ExportCompiledRules method.
     * This method will destroy previous content of both lists.
     *
     * @param string $BinaryPack
     * @return bool  Success
     */
    public function ImportCompiledRules($BinaryPack) {

        $Pack= json_decode($BinaryPack, true, 128, JSON_BIGINT_AS_STRING);

        if (array_keys($Pack) !== array('WhiteList','BlackList')) {
            return false;
        }

        $this->WhiteList->Import($Pack['WhiteList']);
        $this->BlackList->Import($Pack['BlackList']);
        return true;
    }


}

?>