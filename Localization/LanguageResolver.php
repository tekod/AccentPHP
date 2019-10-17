<?php namespace Accent\Localization;

/**
 * Component that encapsulate most commonly used strategies
 * for calculating which language to use for frontend.
 *
 * By configuration component can decide how and which strategy to use,
 * and if it fails which strategy to use for fallback and third-level fallback and forth...
 *
 * Method dedicated for applying a strategy is calling "resolver" and its job is to
 * return string containing language symbol for success or null for failing.
 * Array "Rules" align resolvers in cascade allowing failed resolvers to delegate execution to next resolver in chain.
 *
 * Main method Resolve() will execute resolvers in order as they are configured in "Rules"
 * and return value of first successfull resolver skipping further resolvers.
 * If all resolvers fails method Resolve() will return null.
 *
 * There are special resolver "Default" which is always succefull,
 * its purpose is to be placed at bottom of Rules so calling Resolve() will never return null.
 */

use Accent\AccentCore\Component;
use Accent\AccentCore\ArrayUtils\Collection;
use Accent\Localization\Event\DetectLanguageEvent;


class LanguageResolver extends Component {


    protected static $DefaultOptions= array(

        // list of posible languages, specified as lowercased 2-char symbols
        // used by Domain, Path, Browser
        'AllowedLanguages'=> array(),

        // list of rules for resolving language
        // keys are names of resolvers and values are their parameters
        'Rules'=> array(

            // compare current domain with parameters to find matching
            //'Domain'=> array('site.com'=>'en', 'site.fr'=>'fr'),

            // check wether path starts or ends with any of allowed languages [Prefix|Suffix]
            //'Path'=> 'Prefix',

            // check wether $_SERVER["HTTP_ACCEPT_LANGUAGE"] contains allowed language, no params needed
            //'Browser'=> null,

            // fetch stored value from Session service, named as parameter
            //'Session'=> 'Lang',

            // fetch stored value from User service, current user data named as parameter
            //'User'=> 'Lang',

            // ask event listeners to resolve language
            //'Event'=> 'MyApp.LangResolver',

            // if reached this point use this as default language, this resolver is always successfull
            //'Default'=> 'en',
        ),

        // services
        'Services'=> array(
            'Event'=> 'Event',      // mandatory
            'Session'=> 'Session',  // can be ommited if not using 'Session' resolver
            'Auth' => 'Auth',       // can be ommited if not using 'User' resolver
        ),
    );

    // internal properties
    protected $AllowedLanguages= array();
    protected $ServerVars= array();
    protected $Rules;

    /*
     * Constructor.
     */
    public function __construct($Options= array()) {

        parent::__construct($Options);

        // prepare internal buffers
        $this->SetAllowedLanguages($this->GetOption('AllowedLanguages'));
        $Rules= $this->GetOption('Rules');
        $this->Rules= is_object($Rules) ? $Rules : new Collection($Rules);
    }


    /**
     * Set list of allowed languages, as lowercased 2-char symbols.
     *
     * @param array $List
     */
    public function SetAllowedLanguages($List) {

        $this->AllowedLanguages= $List;
    }


    /**
     * Returns collection of rules.
     *
     * @return \Accent\AccentCore\ArrayUtils\Collection
     */
    public function GetRules() {

        return $this->Rules;
    }


    /**
     * Main method, examining list of rules and calling resolvers to determine language.
     *
     * @return null|string
     */
    public function Resolve() {

        // get _SERVER values
        $this->ServerVars= $this->GetRequestContext()->SERVER;

        // allow listeners to append/modify rules
        $this->EventDispatch('LanguageResolver.Resolve', [
            'Resolver'        => $this,
            'AllowedLanguages'=> $this->AllowedLanguages,
            'ServerVars'      => $this->ServerVars,
            'Rules'           => $this->Rules,
        ]);


        // loop thru rules
        foreach($this->Rules->ToArray() as $RuleKey=>$RuleParam) {
            $this->TraceDebug('LanguageResolver: trying "'.$RuleKey.'".');
            // call resolver
            $Callable= array($this, 'Resolver_'.$RuleKey);
            $Result= call_user_func($Callable, $RuleParam);
            // return immidiately on first success
            if ($Result !== null) {
                $this->TraceInfo('LanguageResolver: resolved as "'.$Result.'".');
                return $Result;
            }
        }

        // not resolved
        $this->TraceInfo('LanguageResolver: not resolved.');
        return null;
    }


    /**
     * Resolved rule "Domain".
     *
     * @param array $List  list of domains
     * @return null|string
     */
    public function Resolver_Domain($List) {

        if (!isset($this->ServerVars['HTTP_HOST'])) {
            return null;
        }
        $HostParts= explode(':', $this->ServerVars['HTTP_HOST']);  // trim ":80"
        $Host= reset($HostParts);
        foreach ($List as $Domain=>$Lang) {
            if (substr($Host, -strlen($Domain)) === $Domain) {
                return $Lang;
            }
        }
        return null;
    }


    /**
     * Resolver rule "Path".
     *
     * @param string $Position  ["Prefix"|"Suffix"]
     * @return null|string
     */
    public function Resolver_Path($Position) {

        if (!isset($this->ServerVars['REQUEST_URI']) || strlen($this->ServerVars['REQUEST_URI']) < 3) {
            return null;
        }
        $Segments= array_filter(explode('/', $this->ServerVars['REQUEST_URI']));
        $Segment= strtoupper($Position) === 'PREFIX'
            ? reset($Segments)
            : end($Segments);
        $Segment= strtolower($Segment);
        return in_array($Segment, $this->AllowedLanguages)
            ? $Segment
            : null;
    }


    /**
     * Resolver rule "Browser"
     *
     * @return null|string
     */
    public function Resolver_Browser() {
        // based on: http://stackoverflow.com/questions/6038236/http-accept-language
        if (!isset($this->ServerVars['HTTP_ACCEPT_LANGUAGE'])) {
            return null;
        }
        preg_match_all("/([[:alpha:]]{1,8})(-([[:alpha:]|-]{1,8}))?" .
                       "(\s*;\s*q\s*=\s*(1\.0{0,3}|0\.\d{0,3}))?\s*(,|$)/i",
                       $this->ServerVars['HTTP_ACCEPT_LANGUAGE'], $Hits, PREG_SET_ORDER);
        $Found= null;
        $BestQ= 0;
        foreach($Hits as $Arr) {
            // read data from the array of this hit
            $Language= strtolower($Arr[1]);
            $QValue= isset($Arr[5]) ? floatval($Arr[5]) : 1.0;
            // find q-maximal language
            if (in_array($Language, $this->AllowedLanguages) && ($QValue > $BestQ)) {
                $Found= $Language;
                $BestQ= $QValue;
            }
        }
        return $Found;
    }


    /**
     * Resolver rule "Session".
     *
     * @param string $Key
     * @return null|string
     */
    public function Resolver_Session($Key) {

        $Stored= $this->GetService('Session')->Get($Key);
        $Stored= strtolower($Stored);
        return in_array($Stored, $this->AllowedLanguages)
            ? $Stored
            : null;
    }


    /**
     * Resolver rule "User".
     *
     * @param mixed $Key
     * @return null|string
     */
    public function Resolver_User($Key) {

        $Stored= $this->GetService('Auth')->GetUser()->GetData($Key);
        $Stored= strtolower($Stored);
        return in_array($Stored, $this->AllowedLanguages)
            ? $Stored
            : null;
    }


    /**
     * Resolver rule "Event".
     *
     * @param string $Name
     * @return null|string
     */
    public function Resolver_Event($Name) {

        // dispatch event
        $Ev= new DetectLanguageEvent([
            'ServerVars'       => $this->ServerVars,
            'AllowedLanguages' => $this->AllowedLanguages,
        ]);
        $this->EventDispatch($Name, $Ev);

        // return result
        return $Ev->GetLanguage() === ''
            ? null
            : $Ev->GetLanguage();
    }


    /**
     * Resolver rule "Default".
     *
     * @param string $Lang
     * @return string
     */
    public function Resolver_Default($Lang) {

        return $Lang;
    }

}

?>