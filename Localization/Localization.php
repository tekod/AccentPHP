<?php namespace Accent\Localization;

/**
 * Part of the AccentPHP project.
 *
 * Localization package brings:
 *  - translation service
 *  - localized number presentation service
 *  - routine for detecting user's language from browser user-agent
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Directories and Files in configuration can be specified as string or array of strings.
 * They can contain wildcards:
 *  @Lang will be replaced with language value, 'en' for example,
 *  @Book will be replaced with book name, 'main' for example
 *   (if none of @Lang and @Book wildcars found in 'Directories' entry they will be
 *    appended like 'ExistingEntry/@Lang/@Book'),
 *  @Dir will be replaced with value from 'LangFilesRootDir' option
 *   (using this wildcard allows shortening paths in loader configs),
 * Book loaders can be specified as array which will execute all of them by order.
 *
 * TODO: reconsider switching from own loaders to AccentCore/Storage loaders.
 */

use \Accent\AccentCore\Component;


class Localization extends Component {


    const FULL_DATE   = 1;
    const LONG_DATE   = 2;
    const MEDIUM_DATE = 3;
    const SHORT_DATE  = 4;

    // default constructor options
    protected static $DefaultOptions= array(

        // language to use as default
        'DefaultLang'=> 'en',

        // first translations will be loaded from 'main.php'
        'DefaultBook'=> 'main',

        // register books and their assigned loaders
        'Books'=> [
            // 'MyNewBook'=> 'db1',  // register "MyNewBook" and assign "db1" loader to it
        ],

        // prepare configuration for loader which will be invoked
        'LoaderConfigs'=> array(
            'php1'=> array(         // first PHP loader
                'LoaderClass'=> 'PHP',    // short name for local [PHP, YML, INI, JSON, XML, Database,..] or FQCN
                'Directories'=> array(),// list of full paths where to search for books
                'Files'=> array(),      // list of full paths to files with translations
            ),
        ),

        // choose which loader to call for specified book
//        'BookLoader'=> array(
//            //'MyNewBook'=> 'db1',  // load 'MyNewBook' from database
//        ),

        // which loader to use for books not listed in BookLoader
        'DefaultLoader'=> 'php1',

        // string which will be used insted '@Dir' wildcard
        'LangFilesRootDir'=> '',

        // aliases for languages that will be resolved within Msg() and Translate()
        // there are two reserved aliases: '@' and null
        'LangAliases'=> array(
            // alias '@' will be used as fallback language if translation not found in requested language,
            // commonly used for backend
            '@'=> 'en',

            // alias [null] will be used for methods called with lang argument ommited,
            // commonly used for frontend,
            // this alias will be auto populated with value from 'DefaultLang' option,
            // you don't have to configure it manualy
            /* null=> 'en', */
        ),

        // adjustment for timestamps in FormatDate and UnformatDate
        'TimeOffset'=> 0,

        // version of Accent/Localization package
        'Version'=> '1.0.0',

        // services
        'Services'=> array(
            'Event'=> 'Event',  // name of event manager service
            'UTF'=> 'UTF',      // name of utf8 service
        ),
    );

    // book registry
    protected $Books= [];

    // buffer for translated strings, grouped by language and then grouped by book
    protected $Tables= [];

    // array of loader objects
    protected $Loaders= [];

    // storage for redirection of books
    protected $BookRedirectings= [];
    protected $BookRedirectingCount= 0;

    // cache for pluralization resolvers
    protected $PluralizationRuleCache= [];

    // some internal constants for delocalization
    protected $EnglishMonths= array('January','February','March','April','May','June','July','August','September','October','November','December');
    protected $EnglishDaysOfWeek= array('Monday','Tuesday','Wednesday','Thurseday','Friday','Saturday','Sunday');

    // adjustment for timestamps in FormatDate and UnformatDate methods
    protected $TimeOffset= 0;

    // specify aliases for languages that will be resolved within Msg() and Translate(), example: array(null=>'en', '@'=>'fr')
    protected $LangAliases= [];


    /**
     * Constructor.
     */
    public function __construct($Options=[]) {

        // call ancestor
        parent::__construct($Options);

        // init book and loader registry
        $this->Books= $this->GetOption('Books');
        $this->Loaders= $this->GetOption('LoaderConfigs');

        // aliases can be changed latter by calling SetLangAliases()
        $this->SetLangAliases($this->GetOption('LangAliases'));
    }


    /**
     * Assign languages for aliases such as "@", "?", "", null,....
     * They will be used within Msg() and Translate() to resolve target language.
     * Note: unmentioned aliases will be preserved.
     *
     * @param array $Aliases
     */
    public function SetLangAliases($Aliases) {

        $this->LangAliases=
            $Aliases
            + [null => $this->GetOption('DefaultLang')]
            + $this->LangAliases;
    }


    /**
     * Returns translation of supplied message code.
     *
     * All nullable parameters can be specified as empty string to achieve same behaviour.
     *
     * @param string $MsgCode Key of translated message
     * @param mixed $Modifier :
     *         '|' - return array exploded by '|' char
     *         1..999 - return specified element of message exploded by '|'
     *         '=999' - execute 'choice pattern' and return part of message
     *         '*999' - execute 'pluralization choice' and return part of message
     * @param mixed $Lang :
     *         string like 'en' for target language,
     *         null for configured frontend language (default)
     *         '@' for configured backend language
     * @param array $Replace array of values to inject in message via sprintf function
     * @return string
     */
    public function Translate($MsgCode, $Modifier=null, $Lang=null, $Replace=null) {

        // resolve packed format
        $Pack= explode('#', $MsgCode);
        $Code= $Pack[0];
        $Book= $this->ResolveBook(isset($Pack[1]) ? $Pack[1] : null);
        $Modifier= isset($Pack[2]) ? $Pack[2] : $Modifier;      // override method argument
        $Lang= isset($Pack[3]) ? $Pack[3] : $Lang;              // override method argrument

        // resolve requested language and book
        $Lang= strtolower($this->ResolveLang($Lang));

        // retrieve message
        $Message= $this->FindMessage($Code, $Book, $Lang);

        // apply modifier
        $Message= $this->ApplyModifier($Message, $Modifier, $Lang);

        // inject replacers
        $Replace= ($Replace === null || $Replace === '') ? [] : (array)$Replace;
        if (!is_array($Message) && !empty($Replace)) {
            $Message= vsprintf($Message, $Replace);
        }
        return $Message;
    }


    /**
     * Perform modifier tansformations to content.
     *
     * @param string $Message  content
     * @param string $Modifier  instructions
     * @param string $Lang  language
     * @return string|array
     */
    protected function ApplyModifier($Message, $Modifier, $Lang) {

        // check '|' modifier
        if ($Modifier === '|') {
            return explode('|', $Message);
        }
        // check integer modifier
        $Index= intval($Modifier);
        if ($Index > 0) {
            $Exploded= explode('|', $Message);
            if (isset($Exploded[$Index-1])) {
                return $Exploded[$Index-1];
            }
        }
        // check 'numeric choice pattern'
        $ModifierChar1= substr($Modifier, 0, 1);
        if ($ModifierChar1 === '=') {
            return $this->ResolveNumericChoice($Message, substr($Modifier, 1));
        }
        // check 'pluralization choice pattern'
        if ($ModifierChar1 === '*') {
            return $this->ResolvePluralizationChoice($Message, substr($Modifier, 1), $Lang);
        }
        return $Message;
    }


    /**
     * Perform search for specified message and use loaders if necessary
     *
     * @param string $Code  identifer of translatable content
     * @param string $Book  name of book
     * @param string $Lang  identifier of language
     */
    protected function FindMessage($Code, $Book, $Lang) {

        // is this new language?
        if (!isset($this->Tables[$Lang])) {
            $this->InitNewLanguageTable($Lang);
        }
        // is requested book loaded
        if (!isset($this->Tables[$Lang][$Book])) {
            $this->LoadBook($Lang, $Book);
        }
        // look in specified book
        if (isset($this->Tables[$Lang][$Book][$Code])) {
            return $this->Tables[$Lang][$Book][$Code];
        }
        // fallback in default book
        $DefaultBook= $this->GetOption('DefaultBook', 'main');
        if (isset($this->Tables[$Lang][$DefaultBook][$Code])) {
            return $this->Tables[$Lang][$DefaultBook][$Code];
        }
        // further fallback in backend language, frontend often hasn't all translations
        if ($Lang <> $this->LangAliases['@']) {
            return $this->FindMessage($Code, $Book, $this->LangAliases['@']);
        }
        return $Code; // return untranslated string
    }


    /**
     * Initialization tasks for adding each new language in table.
     *
     * @param string $Lang  language identifier
     */
    protected function InitNewLanguageTable($Lang) {

        // initialize table and load default book
        $this->Tables[$Lang]= array();
        $this->LoadBook($Lang, $this->GetOption('DefaultBook', 'main'));
    }


    /**
     * Return 2-char value for $Lang by resolving null and '@' cases.
     *
     * @param string $Lang  language identifier
     * @return string
     */
    protected function ResolveLang($Lang) {

        return isset($this->LangAliases[$Lang])
            ? $this->LangAliases[$Lang]
            : (string)$Lang;
    }


    /**
     * Return final book name by resolving null case and book-redirections.
     *
     * @param string $BookName  name of book
     * @return string
     */
    protected function ResolveBook($BookName) {

        $Book= $BookName === null || $BookName === ''
            ? $this->GetOption('DefaultBook', 'main')
            : (string)$BookName;

        // look for redirections and return another book if redirection is found
        foreach(array_reverse($this->BookRedirectings) as $R) {
            if ($R['From'] === $Book) {
                return $R['To'];
            }
        }

        // return result
        return $Book;
    }


    /**
     * Perform loading new translations from its sources.
     *
     * @param string $Lang  language
     * @param string|array $Book  name of book
     */
    protected function LoadBook($Lang, $Book) {

        $Loaders= $this->GetLoadersForBook($Book);

        // for each loader - load data into table
        foreach($Loaders as $Loader) {
            $Lines= $Loader->Load($Lang, $Book);
            $this->Tables= $this->MergeArrays([$this->Tables, $Lines]);
        }

        // dispatching an event allows extensions to override values in this book
        $this->EventDispatch('Localization.LoadBook', ['Localization'=>$this, 'Lang'=>$Lang, 'Book'=>$Book]);
    }


    /**
     * Get list of loaders assigned to specified book.
     *
     * @param string $Book  name of book
     * @return array of loader objects
     */
    protected function GetLoadersForBook($Book) {

        $Loaders= [];

        // get loaders for this book
        $LoaderNames= isset($this->Books[$Book]) ? $this->Books[$Book] : $this->GetOption('DefaultLoader');

        // it is possible to declare multiple loaders for single book, force array type
        if (!is_array($LoaderNames)) {
            $LoaderNames= [$LoaderNames];
        }

        // collect objects
        foreach($LoaderNames as $LoaderName) {
            // ensure that loaders are instantiated
            if (!is_object($this->Loaders[$LoaderName])) {
                $this->CreateLoader($LoaderName);
            }
            $Loaders[]= $this->Loaders[$LoaderName];
        }

        // return list of objects
        return $Loaders;
    }


    /**
     * Loader factory.
     *
     * @param string $LoaderName  name of loader
     */
    protected function CreateLoader($LoaderName) {

        // prepare constructor options
        $Conf= $this->Loaders[$LoaderName] + [
            'LoaderClass'=> '_Unknown_',
            'LangFilesRootDir'=> $this->GetOption('LangFilesRootDir'),
        ];

        // prepare full qualified class name
        $FQCN= strpos($Conf['LoaderClass'], '\\') === false
            ? '\\Accent\\Localization\\Loader\\'.ucfirst(strtolower($Conf['LoaderClass'])).'Loader'
            : $Conf['LoaderClass'];

        // instantiate loader object
        $this->Loaders[$LoaderName]= $this->BuildComponent($FQCN, $Conf);
    }


    /**
     * Put customized translation into system.
     * This method will not
     * Parameters are same as for Translate method.
     *
     * @return \Accent\AccentCore\Localization\Localization for chaining
     */
    public function SetTranslation($Code, $Lang, $Book, $TranslatedMessage) {

        $Code= ltrim($Code, '%');
        $Lang= $this->ResolveLang($Lang);
        $Book= is_null($Book) ? $this->GetOption('DefaultBook', 'main') : (string)$Book;
        // is this new language ?
        if (!isset($this->Tables[$Lang])) {
            $this->InitNewLanguageTable($Lang);
        }
        // is requested book loaded ?
        if (!isset($this->Tables[$Lang][$Book])) {
            $this->LoadBook($Lang, $Book);
        }
        $this->Tables[$Lang][$Book][$Code]= (string)$TranslatedMessage;
        return $this;
    }


    /**
     * Examine all expressions in string and return first matching message.
     * String must be constructed like: '[1]one|[2]two|[n>2&&n<5]few|[n>=5]much'
     *
     * @param string $Messages
     * @param int $Num
     * @return string
     */
    protected function ResolveNumericChoice($Messages, $Num) {

        $Matches= null; // silence IDE
        $Count= preg_match_all('/\[([^\]]*)\]([^\|]*)\|/', $Messages.'|', $Matches);
		if($Count === 0) {
			return $Messages;
        }
		for($i=0; $i<$Count; ++$i) {
			$Expr= $Matches[1][$i];
			$Msg= $Matches[2][$i];
            if (is_numeric($Expr)) {
                if ($Expr == $Num) { // shorthand format - number only
			        return $Msg;
                }
			} else if ($this->EvaluteExpression($Expr, $Num)) {
				return $Msg;
            }
		}
		return $Msg; // return the last entry
    }


    /**
     * Perform evalution of supplied expression.
     * This is used by ResolveNumericChoice method.
     *
     * @param string $Expression
     * @param int $n
     * @return string
     */
    protected function EvaluteExpression($Expression, $n) {

        // check is it safe to send expression to "evil" eval() func
        $ValidChars= array('1','2','3','4','5','6','7','8','9','0',
            ' ','n','=','!','%','(',')','?',':','<','>','&','|','^','+','-','*','/');
        if (str_replace($ValidChars,'',$Expression) <> '') {return false;}
        $PreventFuncCalls= array('n(','1(','2(','3(','4(','5(','6(','7(','8(','9(','0(');
        $ssExpresion= str_replace(array(' ',"\t","\r","\n"),'',$Expression);
        if (str_replace($PreventFuncCalls,'',$ssExpresion) <> $ssExpresion) {return false;}
        // replace 'n' with $n and evalute expression
        return eval('return '.str_replace('n', $n, $Expression).';');
    }


    /**
     * Choose part of message according to pluralization rule for specified language.
     *
     * @param string $Messages
     * @param int $Num
     * @param string $Lang
     * @return string
     */
    protected function ResolvePluralizationChoice($Messages, $Num, $Lang) {

        $Messages= explode('|', $Messages);

        // special case '0' - return first element if it was supplied, otherwise continue
        if ($Num <= 0 && $Messages[0] <> '') {
            return $Messages[0];
        }

        // get resolver function
        if (!isset($this->PluralizationRuleCache[$Lang])) {
            $Rule= intval($this->Translate('PluralizationRule', null, $Lang)); // 0 for unknown
            $this->PluralizationRuleCache[$Lang]= $this->GetPluralizationFunc($Rule);
        }
        $Func= $this->PluralizationRuleCache[$Lang];

        // calculate which pluralization form to use
        $Index= $Func($Num)+1;

        // return $Index element of $Messages (or last one if element not found)
        return isset($Messages[$Index])
            ? $Messages[$Index]
            : end($Messages);
    }


    /**
     * Register additional book (or books if passed as array) in already initialized service.
     *
     * @param string|array $Book
     * @param string $LoaderName
     * @return self
     */
    public function RegisterBook($Book, $LoaderName) {

        // force array type, there can be multiple books registered at once
        $Books= is_array($Book) ? $Book : array($Book);

        // assign loader to each book
        foreach($Books as $B) {
            $this->Books[$B]= $LoaderName;
        }

        // chaining
        return $this;
    }


    /**
     * Register additional loader and specify its configuration (or instantied loader object).
     *
     * @param string $LoaderName
     * @param array $Config
     * @return self
     */
    public function RegisterLoader($LoaderName, $Config) {

        $this->Loaders[$LoaderName]= $Config;
        return $this;
    }


    /**
     * Temporary redirect calls for one book to another.
     * That makes posible to override bunch of messages generated by other systems
     * without effort to overwrite each of them and restore original after usage.
     * Returned value is notificator object which will automaticaly remove
     * redirection on its destruction (ussually you will not destroy it manualy).
     *
     * @param string $FromBook
     * @param string $ToBook
     * @return \Accent\AccentCore\Localization\Localization_BookRedirNotificator
     */
    public function SetTemporaryBookRedirection($FromBook, $ToBook) {

        // store redirection
        $this->BookRedirectings[++$this->BookRedirectingCount]= array(
            'From'=> $FromBook,
            'To'=> $ToBook,
        );
        // build notificator object
        return new Localization_BookRedirNotificator($this, $this->BookRedirectingCount);
    }

    /**
     * Internal method, must be public to allow notificator to work.
     */
    public function _RemoveTemporaryBookRedirection($Id) {

        unset($this->BookRedirectings[$Id]);
    }



    /**
     * Retreive content from database table 'multilang_text'
     * @param string $Key
     * @param string $Lang
     * @return mixed

    public static function GetFromDB($Key, $Lang) {

        // SELECT * FROM `multilang_text` WHERE mltKey
        return DB()
                ->Query('multilang_text', 'mltText')
                ->Where('mltKey', $Key)
                ->OrderBy("mltLang='$Lang' DESC")
                ->OrderBy("mltLang='" . System::$LangDef . "' DESC")
                ->Range(1)
                ->FetchField('mltText');
    }


    /**
     * Store content into database table 'multilang_text'
     * @param string $Key
     * @param string $Lang
     * @param string $Text

    public static function StoreToDB($Key, $Lang, $Text) {

        $Translated = (($Text <> '') or ($Lang == System::$LangDef));
        // store
        $Status = DB()
            ->Update('multilang_text')
            ->Values(array(
                  'mltTranslated' => $Translated ? 'D' : ' ',
                  'mltText' => $Text,))
            ->Where('mltKey', $Key)
            ->Where('mltLang', $Lang)
            ->Execute();
        return $Status;
    }*/




    public function DetectLanguage() {

        /*
    IZVORI JEZIKA, PO VAŽNOSTI:
    URL - Determine the language from the URL (Path prefix or Domain).
    Session - Determine the language from a request/session parameter.
    User - Follow the user's language preference.
    Browser - Determine the language from the browser's language settings.
    Default language - Use the default site language (English).

         */
    }


    /**
     * Examine $_SERVER["HTTP_ACCEPT_LANGUAGE"]  (or value supplied in $Header) and
     * return single language which best match with list of allowed languages.
     *
     * @param array $AvailableLanguages list of 2-char language marks
     * @param string|null $Headers overriding $_SERVER["HTTP_ACCEPT_LANGUAGE"]
     * @return string 2-char language mark
     */
    public function DetectLanguageFromBrowser($AvailableLanguages, $Headers=null) {
        // based on: http://stackoverflow.com/questions/6038236/http-accept-language
        // standard  for HTTP_ACCEPT_LANGUAGE is defined under
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.4
        // pattern to find is therefore something like this:
        //    1#( language-range [ ";" "q" "=" qvalue ] )
        // where:
        //    language-range  = ( ( 1*8ALPHA *( "-" 1*8ALPHA ) ) | "*" )
        //    qvalue         = ( "0" [ "." 0*3DIGIT ] )
        //            | ( "1" [ "." 0*3("0") ] )
        $HttpAcceptLanguage= is_null($Headers) ? $this->GetRequestContext()->SERVER["HTTP_ACCEPT_LANGUAGE"] : $Headers;
        $Hits= null; // silence IDE
        preg_match_all("/([[:alpha:]]{1,8})(-([[:alpha:]|-]{1,8}))?" .
                       "(\s*;\s*q\s*=\s*(1\.0{0,3}|0\.\d{0,3}))?\s*(,|$)/i",
                       $HttpAcceptLanguage, $Hits, PREG_SET_ORDER);
        // default language (in case of no hits) is the first in the array
        $BestLang= $AvailableLanguages[0];
        $BestQ= 0;
        foreach($Hits as $Arr) {
            // read data from the array of this hit
            $LangPrefix= strtolower($Arr[1]);
            $Language= isset($Arr[3]) ? $LangPrefix : $LangPrefix."-".strtolower($Arr[3]);
            $QValue= isset($Arr[5]) ? floatval($Arr[5]) : 1.0;
            // find q-maximal language
            if (in_array($Language,$AvailableLanguages) && ($QValue > $BestQ)) {
                $BestLang= $Language;
                $BestQ= $QValue;
            }
            // if no direct hit, try the prefix only but decrease q-value by 10% (as http_negotiate_language does)
            else if (in_array($LangPrefix,$AvailableLanguages) && (($QValue*0.9) > $BestQ)) {
                $BestLang= $LangPrefix;
                $BestQ= $QValue*0.9;
            }
        }
        return $BestLang;
    }




    /*************************************************************
     *
     *              Localization of dates and time
     *
     *************************************************************/


    protected $FormatDateTypeNames= array('', 'Full', 'Long', 'Medium', 'Short');


    /**
     * Modifies specified formating pattern for date presentation.
     *
     * @param int $FormatType some of: self::FULL_DATE, self::LONG_DATE, ...
     * @param type $FormatString new formating pattern
     * @param type $Lang desired language
     * @return \Accent\AccentCore\Localization\Localization
     */
    public function SetDateFormat($FormatType, $FormatString, $Lang=null) {

        $TranslationKey= 'FormatDate.'.$this->FormatDateTypeNames[$FormatType];
        $this->SetTranslation($TranslationKey, $Lang, null, $FormatString);
        return $this;
    }


    /**
     * Return date using format for specified language and format-type.
     * Use option 'TimeOffset' to specify time-zone shift.
     *
     * @param int $Timestamp  input time, ussually given by time() function
     * @param const $FormatType [self::FULL_DATE, self::LONG_DATE,...]
     * @param mixed $Lang  apply formats for this language
     * @return string
     */
    public function FormatDate($Timestamp, $FormatType=self::SHORT_DATE, $Lang=null) {

        if (is_numeric($FormatType)) {
            $TranslationKey= 'FormatDate.'.$this->FormatDateTypeNames[$FormatType];
            $Format= $this->Translate($TranslationKey, null, $Lang);
        } else {
            $Format= $FormatType; // already supplied as formatting string
        }
        $Timestamp += $this->TimeOffset;
        $LocalizedFormat= $this->LocalizeDateFormat($Timestamp, $Format, $Lang);
        return date($LocalizedFormat, $Timestamp);
    }

    /**
     * Return time using format for specified language.
     * Use option 'TimeOffset' to specify time-zone shift.
     */
    public function FormatTime($Timestamp, $ShowSeconds=false, $Lang=null) {

        $TranslationKey= 'FormatTime.'.($ShowSeconds ? 'Full' : 'Short');
		$Format= $this->Translate($TranslationKey, null, $Lang);
        $Timestamp += $this->TimeOffset;
        return date($Format, $Timestamp);
    }

    /**
     * Return integer representing timestamp by analyzing supplied
     * formated string and format-type for specified language.
     *
     * @param string $FormatedDate  input formated string
     * @param const $FormatType  [::FULL_DATE, ::LONG_DATE, ::MEDIUM_DATE, ::SHORT_DATE]
     * @param mixed $Lang  apply formats for this language
     * @return int
     */
    public function UnFormatDate($FormatedDate, $FormatType=self::SHORT_DATE, $Lang=null) {

        $UTF= $this->GetService('UTF');
        // get translated titles but dont cache them to allow overwritting and book redirecting
		$Months= $this->Translate('FormatDate.Months', null, $Lang);
        $lcMonths= explode('|', $UTF->strtolower($Months));
        $DOWs= $this->Translate('FormatDate.DaysOfWeek', null, $Lang);
        $lcDOWs= explode('|', $UTF->strtolower($DOWs));
        // prepare replacers to english
        $Replacer= array_combine($lcMonths, $this->EnglishMonths)
                 + array_combine($lcDOWs, $this->EnglishDaysOfWeek);
        // add 3-letter titles
        foreach(array_keys($Replacer) as $Key) {
            $Replacer[$UTF->substr($Key,0,3)]= $Replacer[$Key];
        }
   //echo '<br>Replacer: ';var_dump($Replacer);
        // perform translation of formated date into english titles
        $FormatedDate= strtr($UTF->strtolower($FormatedDate), $Replacer);
        // prepare formatting string
        if (is_numeric($FormatType)) {
            // it is some of "medium", "short", ...
            $TranslationKey= 'FormatDate.'.$this->FormatDateTypeNames[$FormatType];
            $Pattern= $this->Translate($TranslationKey, null, $Lang);
        } else {
            // it is already specified as pattern
            $Pattern= $FormatType;
        }
        // parse input
        $Parsed= date_parse_from_format($Pattern, $FormatedDate);
   //echo '<br>Parsed: ';var_dump($Parsed);
        $Timestamp= mktime($Parsed['hour'],$Parsed['minute'],$Parsed['second'],
                $Parsed['month'],$Parsed['day'],$Parsed['year']);
        return $Timestamp - $this->TimeOffset;
    }


    /**
     * Return humanized presentation of time interval ('24 minutes ago', '7 days ago',...)
     * using pluralization rules for target language.
     *
     * @param int $Interval  interval of time in seconds
     * @param mixed $Lang  apply rules for this language
     * @param string|null $PackIn  pack result into translated message with this key
     * @return string|array
     */
    public function FormatTimeInterval($Interval, $Lang=null, $PackIn='FormatDate/TimeAgo') {

        $Interval= abs(floor($Interval)); // convert to absolute integer value

        // find packing level
        if ($Interval >= 86400*365) {       $Vars= array('Year',   86400*365);
        } else if ($Interval >= 86400*30) { $Vars= array('Month',  86400*30);
        } else if ($Interval >= 86400) {    $Vars= array('Day',    86400);
        } else if ($Interval >= 3600) {     $Vars= array('Hour',   3600);
        } else if ($Interval >= 60) {       $Vars= array('Minute', 60);
        } else {                            $Vars= array('Second', 0);
        }

        // normalize interval value
        $n= $Vars[1] === 0 ? $Interval : (string)floor($Interval/$Vars[1]);

        // prepare measurement unit (pluralization mode)
        $Unit= $this->Translate('TimeUnits.'.$Vars[0], '*'.$n, $Lang);

        // inject value and unit into message
        return ($PackIn===null)
            ? array($n, $Unit)
            : $this->Translate('FormatDate.TimeAgo', null, $Lang, array($n,$Unit));
    }


    /*
     * Return translated version of date-formating string.
     */
    protected function LocalizeDateFormat($Timestamp, $FormatString, $Lang) {

        $UTF= $this->GetService('UTF');

        // get numerical values for all items which may be presented as text
        // currently they are name of month and name of day of week

        $LocalizableItems= gmdate('N,n', $Timestamp + $this->TimeOffset);
        list($DayOfWeek,$Month)= explode(',', $LocalizableItems);

        // get translated titles but dont cache them to allow overwritting and book redirecting
		$MonthTitle= $this->Translate('FormatDate.Months', $Month, $Lang);
        $ExpMonth= $UTF->str_split($MonthTitle);
		$DayOfWeekTitle= $this->Translate('FormatDate.DaysOfWeek', $DayOfWeek, $Lang);
        $ExpDayOfWeek= $UTF->str_split($DayOfWeekTitle);

        // prepare replacements
        $Replace= array(
            'l'=> '\\'.implode('\\',$ExpDayOfWeek),
            'D'=> '\\'.implode('\\',array_slice($ExpDayOfWeek,0,3)),
            'F'=> '\\'.implode('\\',$ExpMonth),
            'M'=> '\\'.implode('\\',array_slice($ExpMonth,0,3)),
        );

        // preserve escaped letters (\l \D \F \M) by removing them from format string
        $Preserve= array();
        foreach(array_keys($Replace) as $k=>$v) {
            $Preserve['\\'.$v]= '{{{{{{{{'.$k.'}}}}}}}}';
        }


        $FormatString= strtr($FormatString, $Preserve);

        // perform text replacing
        $TranslatedFormat= strtr($FormatString, $Replace);

        // return preserved letters
        $Output= strtr($TranslatedFormat, array_flip($Preserve));

        // result
        return $Output;
    }


    /**
     * Set new value for TimeOffset.
     *
     * @param int $OffsetInSeconds
     * @return \Accent\AccentCore\Localization\Localization
     */
    public function SetTimeOffset($OffsetInSeconds) {

        $this->TimeOffset= intval($OffsetInSeconds);
        return $this;
    }


    /*************************************************************
     *
     *            Localization of number presentation
     *
     *************************************************************/


    /**
     * Convert real number to printable format with localized decimal and thousand separators.
     *
     * @param real $Number input value
     * @param integer $Decimals how many decimals to roundup
     * @param null|string $Lang 2-chars language mark or null for FrontendLanguage
     * @return string
     */
    public function FormatNumber($Number, $Decimals=0, $Lang=null) {

		$ThoSeparator= $this->Translate('FormatNumber.SepThousand',null,$Lang);
		$DecSeparator= $this->Translate('FormatNumber.SepDecimal',null,$Lang);
        return number_format($Number, $Decimals, $DecSeparator, $ThoSeparator);
	}


    /**
     * Convert number to format suitable for mathematics and storing in DB.
     * Returned type is string in order to preserve accuracy of floated-point value.
     *
     * @param string $Number input string
     * @param null|string $Lang 2-chars language mark or null for FrontendLanguage
     * @return string
     */
	public function UnFormatNumber($Number, $Lang=null) {

		$ThoSeparator= $this->Translate('FormatNumber.SepThousand',null,$Lang);
		$DecSeparator= $this->Translate('FormatNumber.SepDecimal',null,$Lang);
        $Number= str_replace(array($ThoSeparator,'+',' '), '', $Number); // remove thous.
	    $Number= str_replace(array($DecSeparator,','), '.', $Number);  // now add decimal
	    return trim($Number);
	}


    /**
     * Return rule for pluralization choice.
     *
     * @param int $Rule
     * @return anonimous func
     */
    protected function GetPluralizationFunc($Rule) {

        // rules are borrowed from developer.mozilla.org/en-US/docs/Localization_and_Plurals
        switch ($Rule) {
            case 0: // Asian (Chinese,Japanese,Korean,Vietnamese,Persian,Turkish,Thai,Lao)
                return function($n){return 0;};
            case 1: // Germanic (Danish, Dutch, English, Faroese, Frisian, German, Norwegian, Swedish), Finno-Ugric (Estonian, Finnish, Hungarian), Language isolate (Basque), Latin/Greek (Greek), Semitic (Hebrew), Romanic (Italian, Portuguese, Spanish, Catalan) - 2 forms
                return function($n){return $n!=1?1:0;};
            case 2: // Romanic (French, Brazilian Portuguese) - 2 forms
                return function($n){return $n>1?1:0;};
            case 3: // Baltic (Latvian) - 3 forms
                return function($n){return $n%10==1&&$n%100!=11?1:($n!=0?2:0);};
            case 4: // Celtic (Scottish Gaelic) - 4 forms
                return function($n){return $n==1||$n==11?0:($n==2||$n==12?1:($n>0&&$n<20?2:3));};
            case 5: // Romanic (Romanian) - 3 forms
                return function($n){return $n==1?0:($n==0||$n%100>0&&$n%100<20?1:2);};
            case 6: // Baltic (Lithuanian) - 3 forms
                return function($n){return $n%10==1&&$n%100!=11?0:($n%10>=2&&($n%100<10||$n%100>=20)?2:1);};
            case 7: // Slavic (Belarusian, Bosnian, Croatian, Serbian, Russian, Ukrainian) - 3 forms
                return function($n){return $n%10==1&&$n%100!=11?0:($n%10>=2&&$n%10<=4&&($n%100<10||$n%100>=20)?1:2);};
            case 8: // Slavic (Slovak, Czech) - 3 forms
                return function($n){return $n==1?0:($n>=2&&$n<=4?1:2);};
            case 9: // Slavic (Polish) - 3 forms
                return function($n){return $n==1?0:($n%10>=2&&$n%10<=4&&($n%100<10||$n%100>=20)?1:2);};
            case 10: // Slavic (Slovenian, Sorbian) - 4 forms
                return function($n){return $n%100==1?0:($n%100==2?1:($n%100==3||$n%100==4?2:3));};
            case 11: // Celtic (Irish Gaelic) - 5 forms
                return function($n){return $n==1?0:($n==2?1:($n>=3&&$n<=6?2:($n>=7&&$n<=10?3:4)));};
            case 12: // Semitic (Arabic) - 5 forms (but zero case is already handled)
                return function($n){return $n==1?0:($n==2?1:($n%100>=3&&$n%100<=10?2:($n%100>=11&&$n%100<=99?3:4)));};
            case 13: // Semitic (Maltese) - 4 forms
                return function($n){return $n==1?0:($n==0||$n%100>0&&$n%100<=10?1:($n%100>10&&$n%100<20?2:3));};
            case 14: // Slavic (Macedonian) - 3 forms
                return function($n){return $n%10==1?0:($n%10==2?1:2);};
            case 15: // Icelandic - 2 forms
                return function($n){return $n%10==1&&$n%100!=11?0:1;};
            case 16: // Celtic (Breton) - 5 forms
                return function($n){return $n%10==1&&$n%100!=11&&$n%100!=71&&$n%100!=91?0:($n%10==2&&$n%100!=12&&$n%100!=72&&$n%100!=92?1:(($n%10==3||$n%10==4||$n%10==9)&&$n%100!=13&&$n%100!=14&&$n%100!=19&&$n%100!=73&&$n%100!=74&&$n%100!=79&&$n%100!=93&&$n%100!=94&&$n%100!=99?2:($n%1000000==0&&$n!=0?3:4)));};
        }
        return function($n){return 0;}; // return first item for all unknown languages
    }


    /**
     * Return list of commonly used languages in the world with theirs native titles.
     * It is usefull for creation of language selector without need to load all them.
     *
     * This list is based on languages available from localize.drupal.org.
     *
     * The "Left-to-right marker" comments and the enclosed UTF-8 markers are to make
     * otherwise strange looking PHP syntax natural (to not be displayed in right to left).
     */
    public function GetLanguageList() {
        return array(
          'af' => array('Afrikaans', 'Afrikaans'),
          'ar' => array('Arabic', /* Left-to-right marker "‭" */ 'العربية', 'RTL'),
          'az' => array('Azerbaijani', 'Azərbaycanca'),
          'be' => array('Belarusian', 'Беларуская'),
          'bg' => array('Bulgarian', 'Български'),
          'bn' => array('Bengali', 'বাংলা'),
          'bo' => array('Tibetan', 'བོད་སྐད་'),
          'bs' => array('Bosnian', 'Bosanski'),
          'ca' => array('Catalan', 'Català'),
          'cs' => array('Czech', 'Čeština'),
          'cy' => array('Welsh', 'Cymraeg'),
          'da' => array('Danish', 'Dansk'),
          'de' => array('German', 'Deutsch'),
          'el' => array('Greek', 'Ελληνικά'),
          'en' => array('English', 'English'),
          'eo' => array('Esperanto', 'Esperanto'),
          'es' => array('Spanish', 'Español'),
          'et' => array('Estonian', 'Eesti'),
          'eu' => array('Basque', 'Euskera'),
          'fa' => array('Persian, Farsi', /* Left-to-right marker "‭" */ 'فارسی', 'RTL'),
          'fi' => array('Finnish', 'Suomi'),
          'fo' => array('Faeroese', 'Føroyskt'),
          'fr' => array('French', 'Français'),
          'ga' => array('Irish', 'Gaeilge'),
          'he' => array('Hebrew', /* Left-to-right marker "‭" */ 'עברית', 'RTL'),
          'hi' => array('Hindi', 'हिन्दी'),
          'hr' => array('Croatian', 'Hrvatski'),
          'hu' => array('Hungarian', 'Magyar'),
          'hy' => array('Armenian', 'Հայերեն'),
          'id' => array('Indonesian', 'Bahasa Indonesia'),
          'is' => array('Icelandic', 'Íslenska'),
          'it' => array('Italian', 'Italiano'),
          'ja' => array('Japanese', '日本語'),
          'jv' => array('Javanese', 'Basa Java'),
          'ka' => array('Georgian', 'ქართული ენა'),
          'kk' => array('Kazakh', 'Қазақ'),
          'km' => array('Khmer', 'ភាសាខ្មែរ'),
          'ko' => array('Korean', '한국어'),
          'ku' => array('Kurdish', 'Kurdî'),
          'ky' => array('Kyrgyz', 'Кыргызча'),
          'lt' => array('Lithuanian', 'Lietuvių'),
          'lv' => array('Latvian', 'Latviešu'),
          'mk' => array('Macedonian', 'Македонски'),
          'mn' => array('Mongolian', 'монгол'),
          'ms' => array('Bahasa Malaysia', 'بهاس ملايو'),
          'ne' => array('Nepali', 'नेपाली'),
          'nl' => array('Dutch', 'Nederlands'),
          'nb' => array('Norwegian Bokmål', 'Bokmål'),
          'nn' => array('Norwegian Nynorsk', 'Nynorsk'),
          'pa' => array('Punjabi', 'ਪੰਜਾਬੀ'),
          'pl' => array('Polish', 'Polski'),
          'pt' => array('Portuguese', 'Português'),
          'ro' => array('Romanian', 'Română'),
          'ru' => array('Russian', 'Русский'),
          'sk' => array('Slovak', 'Slovenčina'),
          'sl' => array('Slovenian', 'Slovenščina'),
          'sq' => array('Albanian', 'Shqip'),
          'cr' => array('Serbian cyrilic', 'Српски'),
          'sr' => array('Serbian latin', 'Srpski'),
          'sv' => array('Swedish', 'Svenska'),
          'ta' => array('Tamil', 'தமிழ்'),
          'th' => array('Thai', 'ภาษาไทย'),
          'tr' => array('Turkish', 'Türkçe'),
          'uk' => array('Ukrainian', 'Українська'),
          'vi' => array('Vietnamese', 'Tiếng Việt'),
          'zh' => array('Chinese', '简体中文'),
        );
    }


    /**
     * Debug, in development phase
     */
    public function GetTables() {

        return $this->Tables;
    }

} // end of class Localization


/*
 * Internall class, for usage within book redirections.
 */
class Localization_BookRedirNotificator {

    protected $LocalizationObject;
    protected $RedirectorId;

    function __construct($LocalizationObject, $RedirectorId) {
        // save params
        $this->LocalizationObject= $LocalizationObject;
        $this->RedirectorId= $RedirectorId;
    }

    function __destruct() {
        // invoke redirection removing
        $this->LocalizationObject->_RemoveTemporaryBookRedirection($this->RedirectorId);
    }
} // end of class Localization_BookRedirNotificator

?>