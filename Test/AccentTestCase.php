<?php namespace Accent\Test;


use Accent\AccentCore\AutoLoader\AutoLoader;


class AccentTestCase extends \UnitTestCase {

    // title describing this test
    const TEST_CAPTION= 'Name of test';

    // title of testing group
    const TEST_GROUP= 'Name of group';

    // MUST redeclare this in all descedant classes
    protected $FileConst= __FILE__;

    // list of files that should be loaded for testing
    // '@' will be replaced with component directory
    // example: array('@/ServiceManager.php')
    protected $LoadFiles= array();

    // whether to use autoloader
    protected $EnableAutoloader= true;

    // location of database
    protected $DatabaseInMemory= true;

    // internal
    protected $ErrorCollection= array();
    protected $Autoloader;
    protected $ComponentDir;
    protected $TestCaseDB;
    protected $CoverageCheckers;


    /**
     * Contructor.
     */
    public function __construct() {

        // parent
        parent::__construct(static::TEST_CAPTION);

        // setup some variables
        $Reflection= new \ReflectionClass($this);
        $this->ComponentDir= dirname(dirname($Reflection->getFileName())).'/';
        $this->CoverageCheckers= [];

        // load service function and main class
        foreach($this->LoadFiles as $F) {
            include_once str_replace('@/', $this->ComponentDir, $F);
        }

        // setup autoloader
        if ($this->EnableAutoloader) {
            require_once __DIR__.'/../AccentCore/AutoLoader/AutoLoader.php';
            $this->Autoloader= new AutoLoader;
            $this->Autoloader->AddRule('Namespace', 'Accent', __DIR__.'/../../Accent');
            $this->Autoloader->Register();
        }

        // load some standard classes
        include_once __DIR__.'/MockObject.php';

        // register our shutdown handler
        register_shutdown_function(array($this, 'ShutDownHandler'));
    }


    /*
     * Return name of test.
     */
    public function GetTestCaption() {

        return static::TEST_CAPTION;
    }


    /**
     * This will be executed before each test function call.
     */
    public function Before($method) {
        // call parent
        parent::Before($method);
        // addendums
    }

    /**
     * This will be executed after each test function call.
     */
    public function After($method) {
        // call parent
        parent::After($method);
        // addendum
        if(!empty($this->ErrorCollection)) {
            $this->ShowErrors();
        }
    }


    /**
     * Execute some tasks after testing each class.
     */
    public function End() {

        // analyze coverage checkers
        $this->AnalyzeCoverageCheckers();
    }


    /**
     * Send important message.
     */
    public function WarningMessage($Message) {
        echo '<div class="Warning">'.$Message.'</div>';
    }


    /**
     * Send error message.
     * Errors will be drawn after each test function.
     */
    public function ErrorMessage($Message) {

        $this->ErrorCollection[]= $Message;
    }


    /**
     * Echo error HTML.
     */
    protected function ShowErrors() {

        if (empty($this->GetErrors())) {
            return;
        }
        echo '<div style="color:red">Error: '.implode('<br>Error: ',$this->GetErrors()).'</div>';
        $this->ErrorCollection= array(); // reset buffer
    }

    /**
     * Returns error messages.
     */
    protected function GetErrors() {

        return $this->ErrorCollection;
    }


    /**
     * Echo list of database queries.
     * @param \Accent\DB\DB $DB
     */
    public function ShowDatabaseQueries($DB=null) {

        if ($DB === null) {
            $DB= $this->TestCaseDB;
        }
        $List= array();
        foreach ($DB->GetDebugQueriesList() as $Num => $Query) {
            $SafeQuery= htmlspecialchars($Query[1], ENT_QUOTES, 'UTF-8');
            $List[]= ''.($Num+1).'. '. $SafeQuery;
        }
        echo '<pre style="border:1px solid #036; padding:1em; background-color:#012; color:#68a; font-size:11px; white-space:pre-wrap;">'
            .implode('<br>',$List)
            .'</pre>';
    }

    /**
     * Remove all errors messages.
     */
    protected function ClearErrors() {

        $this->ErrorCollection= array();
    }


    /**
     * Handler for 'ErrorFunc' option of testing components.
     * Specify it as 'ErrorFunc'=>array($this,'ErrorFunc'),
     */
    public function ErrorFunc($Message, $Step=1, $FatalError=false) {

        $Backtrace= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $F= basename($Backtrace[$Step]['file']);
        $N= intval($Backtrace[$Step]['line']);
        $Message .= " [$F ($N)]";

        if ($FatalError) {
            // render message now and terminate execution
            die("<b>Fatal error:</b> $Message");
        } else {
            // simply add in buffer, it will be rendered later
            $this->ErrorMessage($Message);
        }
    }


    /**
     * Internal shutdown handler.
     */
    public function ShutDownHandler() {

        // echo queued error messaged
        $this->ShowErrors();

        //restore_error_handler();
        $Context= \SimpleTest::getContext();
        $Queue= $Context->get('SimpleErrorQueue');
        $Errors= '';
        while (list($Severity, $Message, $File, $Line)= $Queue->extract()) {
            $SeverityTxt= $Queue->getSeverityAsString($Severity);
            $Errors .= "<br><b>$SeverityTxt:</b> $Message in $File [$Line]";
        }
        if ($Errors) {
            echo '<div style="background-color:#a20;color:#fff;line-height:2em;padding:2em;">Error: '.$Errors.'</div>';
        }
    }


    /**
     * Create and return coverage checker utility.
     *
     * @param string $Class  FQCN
     * @param int $Depth  ignore methods from parents further then $Depth
     * @param array $Ignore  list of methods that should not be monitored
     * @return \Accent\Test\CoverageChecker
     */
    protected function RegisterCoverageChecker($Class, $Depth=99, $Ignore=[]) {

        // build object
        $Checker= new CoverageChecker($Class, $Depth, $Ignore);

        // put it in roster
        $this->CoverageCheckers[]= $Checker;

        // return that object
        return $Checker;
    }


    /**
     * For each registered coverage checker calculate differences and dispatch warning if needed.
     */
    protected function AnalyzeCoverageCheckers() {

        foreach($this->CoverageCheckers as $Checker) {

            // get list of untested public methods
            $Unchecked= $Checker->GetUnchecked();
            if (!empty($Unchecked)) {
                $this->WarningMessage(sprintf(
                    'Class "%s" contains untested public methods: %s.',
                    $Checker->GetClass(),
                    '"'.implode('", "', $Unchecked).'"'));
            }

            // get list of unknown methods
            $Misses= $Checker->GetMisses();
            if (!empty($Misses)) {
                $this->WarningMessage(sprintf(
                    'These methods should not be tested in class "%s": %s.',
                    $Checker->GetClass(),
                    '"'.implode('", "', $Misses).'"'));
            }
        }
    }


    /**
     * Prepare URL pointing back to testing application,
     * aware of customized filename and providing access password.
     *
     * @param array $QueryParams
     * @return string
     */
    public function BuildTestURL($QueryParams=array()) {

        // add mandary field
        $QueryParams['AccentTestPass']= ACCENTTEST_PASSWORD;

        // pack fields
        $Arr= array();
        foreach ($QueryParams as $Key => $Value) {
            $Arr[]= $Key.'='.urlencode($Value);
        }

        // pack URL
        return 'http://localhost/'.ACCENTTEST_ENTRY_FILENAME.'?'.implode('&',$Arr);
    }


    /**
     * Helper builder, creating simulated database service,
     * that will speed up testing execution and isolate current test from testing real database service.
     *
     * @param array $ExpectedValues
     * @return \Accent\Test\MockObject
     */
    protected function BuildMockedDatabaseService($ExpectedValues) {

        $DB= new \Accent\Test\MockObject(array($this,'ErrorMessage'));
        $DB->Mock_SetMethod('Insert', $DB);
        $DB->Mock_SetMethod('Values', $DB);
        $DB->Mock_SetMethod('Where', $DB);
        $DB->Mock_SetMethod('Execute', 1);
        $DB->Mock_SetMethod('Query', $DB);
        $DB->Mock_SetMethod('Delete', $DB);
        $DB->Mock_SetMethod('Upsert', $DB);
        $DB->Mock_SetMethod('TransactionStart', $DB);
        $DB->Mock_SetMethod('FetchAll', function() use (&$ExpectedValues) {
            return array_shift($ExpectedValues);
        });
        $DB->Mock_SetMethod('FetchRow', function() use (&$ExpectedValues) {
            return array_shift($ExpectedValues);
        });
        return $DB;
    }


    /**
     * Helper builder, creating database service according to $this->DatabaseInMemory setting.
     *
     * @param array|null $Options  constructing options
     * @return \Accent\DB\DB
     */
    protected function BuildDatabaseService($Options=array()) {

        $DB= $this->DatabaseInMemory
            ? $this->BuildMemoryDatabaseService($Options)
            : $this->BuildRealDatabaseService($Options);
        return $DB;
    }


    /**
     * Helper builder, creating in-memory database service, using Sqlite PDO driver.
     *
     * @return \Accent\DB\DB
     */
    protected function BuildMemoryDatabaseService($Options=array()) {

        $this->TestCaseDB= new \Accent\DB\DB(
            $Options
            + array(
                'ConnectionParams'=> array(
                    'DSN'=> 'sqlite::memory:',
                ),
                'ErrorFunc'=> array($this, 'ErrorFunc'),
                'Services'=> array(
                    'Cache'=> false,
                ),
            )
        );
        return $this->TestCaseDB;
    }


    protected function BuildRealDatabaseService($Options=array()) {

        $this->TestCaseDB= new \Accent\DB\DB(
            $Options
            + array(
                'ConnectionParams'=> array(
                    'DSN'=> 'mysql:host=localhost;port=3306;dbname=test',
                    'Username'=> 'root',
                    'Password'=> '',
                ),
                'ErrorFunc'=> array($this, 'ErrorFunc'),
                'Services'=> array(
                    'Cache'=> false, // false = disable internal cache
                ),
            )
        );
        return $this->TestCaseDB;
    }

}

?>