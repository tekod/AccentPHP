<?php namespace Accent\Network;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * IpRange component is util for easy matching arbitrary IP address against list of IP ranges.
 * Range must be defined as string, in any of following formats:
 *   - using wildcards like "192.168.*.*"
 *   - using IP-IP like: "127.0.0.1-127.0.0.4"
 *   - using IP mask like: "192.168.1.1/24"
 *   - using exact IP like: "192.168.0.40"
 * Both IPv4 and IPv6 are supported, but due to their incompatibility they are not comparable, do not mix them.
 * All spaces and EOL characters are ignored to make configuration more comfortable.
 * Component is ancestor of Collection class in order to make rules management more powerfull.
 *
 * Requirements:
 *   - server must have 64-bit OS,
 *   - PHP is was NOT compiled with --disable-ipv6 option
 *
 * Usage:
 *    $IPR= new IpRange();
 *    $IPR->Append('10.*.*.*');
 *    $IPR->Append('192.168.0.0-192.168.1.255);
 *    if (!$IPR->InRange($VisitorIP)) die('Forbidden access.');
 *
 * Instead of series of Append() whole array can be loaded at once: $IPR->Import(file($Path));
 *
 * Todo:
 *   - find some way to specify country to append all its ranges in list
 *   - callback as rule?
 */

use Accent\AccentCore\ArrayUtils\Collection;


class IpRange extends Collection {


    /**
     * Search for supplied IP within collection items.
     *
     * @param $IP
     * @return bool
     */
    public function InRange($IP) {

        // convert IP to comapring-friendy format
        $Version= $this->GetIpVersion($IP);
        $IP= $this->NormalizeIP($IP);

        // compare it against each rule
        foreach ($this->Buffer as $Key => &$Value) {
            // compile rule
            if (!is_array($Value)) {
                $Value= $this->CompileRule($Value);
            }
            // evaluate matching
            if ($this->CheckIP($IP, $Value, $Version)) {
                return true;
            }
        }

        // this IP is not matched with any rule
        return false;
    }


    /**
     * Convert IP address in binary format suitable for fast comparation operations.
     * IPv4 addresses will be converted into 32-bit unsigned integer,
     * IPv6 addresses will be converted into 16-char binary string.
     *
     * @param string $IP
     * @param int|null $Version
     * @param char|null $WildcardContext
     * @return false|int|string
     */
    protected function NormalizeIP($IP, $Version=null, $WildcardContext=null) {

        $IP= trim($IP);
        if (!$IP) {
            return false;
        }
        // check version if not suppllied
        if (!$Version) {
            $Version= $this->GetIpVersion($IP);
        }
        // convert IPv4 to 32-bit integer, don't worry about signed/unsigned because all servers are 64-bit now
        if ($Version === 4) {
            // append missing segments
            $Count= substr_count($IP, '.');
            if ($Count < 3) {
                $IP .= str_repeat($WildcardContext === ']' ? '.255' : '.0', 3-$Count);
            }
            // return as longint
            return ip2long($IP);
        }
        // convert IPv6 to string of 16 bytes (not printing safe)
        if ($Version === 6) {
            // inet_pton will expand '::' internally
            return @inet_pton($IP);
        }
        // unknown format, cannot normalize
        return false;
    }


    protected function CompileRule($String) {

        $Version= $this->GetIpVersion($String);
        $Compiled= false;
        if ($this->CompileRule_Wildcard($Compiled, $String, $Version)
            || $this->CompileRule_Range($Compiled, $String, $Version)
            || $this->CompileRule_Mask($Compiled, $String, $Version)
            || $this->CompileRule_Exact($Compiled, $String, $Version)
        ) {
            return $Compiled;
        }
    }


    protected function CompileRule_Wildcard(&$Compiled, $String, $Version) {

        // try to parse as "192.168.*.*"
        if (strpos($String, '*') === false) {
            return false;
        }
        $Compiled= array(
            '><',
            $this->NormalizeIP(str_replace('*', '0', $String), $Version, '['),
            $this->NormalizeIP(str_replace('*', $Version === 4 ? '255' : 'ffff', $String), $Version, ']'),
            $Version
        );
        return true;
    }


    protected function CompileRule_Range(&$Compiled, $String, $Version) {

        // try to parse as "IP-IP", like "192.168.0.1-192.168.0.12"
        $Range= explode('-', $String, 2);
        if (count($Range) === 1) {
            return false;
        }
        $Compiled= array(
            '><',
            $this->NormalizeIP($Range[0], $Version, '['),
            $this->NormalizeIP($Range[1], $Version, ']'),
            $Version
        );
        return true;
    }


    protected function CompileRule_Mask(&$Compiled, $String, $Version) {

        $Parts= explode('/', $String);
        if (count($Parts) !== 2) {
            return false;
        }
        $Subnet= 0;
        $Mask= intval($Parts[1]);
        $BaseIP= false;
        $LastIP= false;
        // separated calculation for IPv4 and IPv6
        if ($Version === 4) {
            $Subnet= min($Mask, 32);
            $Mask= 0xffffffff << (32-$Subnet);
            $BaseIP= $this->NormalizeIP($Parts[0], $Version) & $Mask;
            $LastIP= $BaseIP | (~$Mask & 0xffffffff);
        }
        if ($Version === 6) {
            $Subnet= min($Mask, 128);
            $Mask= str_repeat(chr(255), $Subnet >> 3);
            $Mask .= chr(0xff << (8 - ($Subnet & 7)));
            $Mask= str_pad($Mask, 16, chr(0));
            $BaseIP= $this->NormalizeIP($Parts[0], $Version) & $Mask;
            $LastIP= $BaseIP | ~$Mask;
        }
        if ($BaseIP === false || $Subnet < 1) {
            return false;       // bad IP or subnet, probably empty string
        }
        $Compiled= array(
            '><',
            $BaseIP,
            $LastIP,
            $Version
        );
        return true;
    }


    protected function CompileRule_Exact(&$Compiled, $String, $Version) {

        // treat it as is
        $Compiled= array(
            '=',
            $this->NormalizeIP($String, $Version),
            null,
            $Version
        );
        // it is always true as last step in chain
        return true;
    }


    /**
     * Detect whether given IP address is in IPv4 or IPv6 format.
     *
     * @param string $IP  IP address in free form
     * @return int  [4,6,0]
     */
    public function GetIpVersion($IP) {

        // we cannot use recomended way: filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        // because it will fail on presence of wildcards
        if (strpos($IP, ':') !== false) {
            return 6;
        }
        if (strpos($IP, '.') !== false) {
            return 4;
        }
        return 0;
    }


    /**
     * Compare given IP address against rule.
     *
     * @param string $IP
     * @param array $Rule
     * @return bool
     */
    protected function CheckIP($IP, $Rule, $Version) {     //d(long2ip($Rule[1]).'-'.long2ip($Rule[2]), 'range');

        // skip incompatibile comparations
        if ($Version <> $Rule[3]) {
            return false;
        }
        // compare
        switch ($Rule[0]) {
            case '=': return $IP === $Rule[1];
            case '><': return $IP >= $Rule[1] && $IP <= $Rule[2];
        }
        // unknown comparation rule
        return false;
    }


    /**
     * Force compiling all rules.
     * This is unnecessary for normal operations because InRange will handle it internally,
     * but can be used for implementation of rules cache, see IpFirewall::ExportCompiledRules.
     * Also can be used for validation of rules, if returns empty array then all rules are correctly formated.
     *
     * @return array  list of keys where compilation fails
     */
    public function CompileAllRules() {

        $CompilationErrors= array();

        foreach ($this->Buffer as $Key => &$Value) {
            if (!is_array($Value)) {
                $Value= $this->CompileRule($Value);
            }
            if ($Value[1] === false || $Value[2] === false) {
                $CompilationErrors[]= $Key;
            }
        }

        return $CompilationErrors;
    }


}

?>