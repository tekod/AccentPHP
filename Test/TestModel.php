<?php

/**
 *	Test manager
 *
 */

define('SIMPLE_TEST', __DIR__.'/simpletest/');
require_once SIMPLE_TEST.'unit_tester.php';
require_once SIMPLE_TEST.'reporter.php';
require_once __DIR__.'/AccentTestCase.php';


class AccentTestModel {

    protected $TestClasses;
    protected $TestTitles;


    /**
     * Perform testing of specified tests.
     *
     * @param array $Tests
     */
    public function Handle($Tests) {

        // turn-off 'E_DEPRECATED'
		if (defined('E_DEPRECATED')) {
    		error_reporting(E_ALL & ~E_STRICT & ~E_DEPRECATED);
        }

        $Totals= array(0,0,0);
		$this->LoadTests($Tests);

        $HeadHTML= '
        <style>
            html {font-size:90%; font-family:sans-serif}
            .TestResult {margin:0.2em 0 1.2em 0; color:#bbb; font-size:14px;}
            .TestResult th {width:25em; padding:0.5em 0 0.5em 2em; text-align:left;}
            .TestResult td {width:7em; padding:0.5em 0 0.5em 2em; border-left:1px solid #222;}
            .TotalResults {border:1px solid #aaa; margin-bottom:2em; color:#eee}
            .TotalResults th, .TotalResult td {padding:2em 0 2em 2em;}
            .Warning {margin:1em 0; padding:0.3em 2em; background-color:#323; color:#D8D; width:48em;}
            #Spinner {display:none; position:fixed; left:0; top:0; width:100%; height:100%; background-color:#000; opacity:0; transition:opacity 1s;}
            #Spinner span {display:inline-block; border-radius:50%; animation:spin 8s linear infinite;
                border:12px solid #444; border-top:12px solid #393; border-bottom:12px solid #c44;
                width:240px; height:240px; position: absolute; top:50%; left:50%; margin:-120px 0 0 -120px;}
            @keyframes spin { 0% {transform:rotate(0deg);} 100% {transform:rotate(360deg);}}
        </style>';
        $ResultHTML= '';


        // loop
    	foreach($this->TestClasses as $Class) {

            // execute test
            $Result= $this->RunTest($Class);

            // render and echo result
            $ResultHTML .= $this->RenderLineResult($Result, 'TestResult', ["#800","#040"]);

            // sum totals
            $Totals[0] += $Result['Stats'][0];
            $Totals[1] += $Result['Stats'][1];
            $Totals[2] += $Result['Stats'][2];
        }

        if (empty($this->TestClasses)) {
            // show "nothing executed" message
            $HeadHTML .= '
            <h1 style="color:#444; font:bold 18px sans-serif; margin:2em;">Nothing selected.</h1>';
        } else {
            // display totals
            $TotalResultData= array(
                'Caption'=> $Totals[1]+$Totals[2] === 0 ? 'PASS' : 'FAIL',
                'Dump'=> '',
                'Stats'=> $Totals,
            );
            $TotalResultHTML= $this->RenderLineResult($TotalResultData, 'TestResult TotalResults', ["#c00","green"]);
            $HeadHTML .= '
                <h1 style="color:#444; font:italic bold 32px sans-serif; margin:0;">Result:</h1>'
                .$TotalResultHTML.'
                <h2 style="color:#444; font:italic bold 24px sans-serif; margin:0;">Tests:</h1>';
        }

        // echo everything
        echo $HeadHTML . $ResultHTML . '<div id="Spinner"><span></span></div>';
	}


    /**
     * Rendering visual presentation of testing result.
     *
     * @param array $Result
     * @param string $Class
     * @param array $Colors
     * @return string
     */
    protected function RenderLineResult($Result, $Class, $Colors) {

        $HTML= '';

        if ($Result['Dump']) {  // just echo it, probably error messages
            $HTML .= '<div class="TestPanel">'.$Result['Dump'].'</div>';
        }

        $Color= $Result['Stats'][1]+$Result['Stats'][2] > 0
            ? $Colors[0]
            : $Colors[1];
        $HTML .=
            '<table class="'.$Class.'" style="background-color:'.$Color.'" cellspacing="0">
                <th>'.htmlentities($Result['Caption']).'</th>
                <td>'.$Result['Stats'][0].' passes</td>
                <td>'.$Result['Stats'][1].' fails</td>
                <td>'.$Result['Stats'][2].' exceptions</td>
            </table>';

        return $HTML;
    }


    /**
     * Include all specified files, and create list of testing classes.
     * Multiple classes per file are supported.
     *
     * @param array $Tests
     */
	protected function LoadTests($Tests) {

        $Classes= get_declared_classes();
        $SearchRoot= dirname(__DIR__);

		// loop
		foreach($Tests as $Test) {
            //echo "Loading: $SearchRoot/$Test";
            include_once $SearchRoot.DIRECTORY_SEPARATOR.$Test;
    	}

    	// get list of new classes
    	$NewClasses= array_values(array_diff(get_declared_classes(), $Classes));
    	// remove non-test classes
    	for($x=count($NewClasses)-1; $x>=0; $x--) {
            $BaseClassname= substr($NewClasses[$x], strrpos($NewClasses[$x], '\\')+1);
        	if (strtolower(substr($BaseClassname,0,6)) <> 'test__') {
                unset($NewClasses[$x]);
            }
    	}

        // final list of classes for testing
        $this->TestClasses= $NewClasses;
	}


    /**
     * Execute single test case.
     *
     * @param string $Class
     * @return array
     */
    protected function RunTest($Class) {

    	// run simpletest
    	ob_start();
    	$Test= new TestSuite('AccentPHP test');
        $Reporter= new Accent_TestReporter('UTF-8');
        $Obj= new $Class();
        $Test->add($Obj);
    	$Test->run($Reporter);
		// return results
		return array(
            'Caption'=> $Obj->GetTestCaption(),
            'Dump'=> ob_get_clean(),
            'Stats'=> $Reporter->GetStats(),
        );
    }


    /**
     * Scan directory and return list of all test-classes.
     *
     * @return array
     */
    public function GetAllTests() {

        // show list of all tests
        $Tests= array();
        // get list of all test files
        $SearchRoot= dirname(dirname(__FILE__));
        $Files= $this->ReadDirs($SearchRoot);
        foreach($Files as $File) {
            $Dump= file_get_contents($SearchRoot.'/'.$File);
            preg_match('#\s*const\s+TEST_CAPTION\s*=\s*\'(.*)\'\s*;\s#', $Dump, $matches);
            $Caption= isset($matches[1]) ? $matches[1] : '';
            preg_match('#\s*const\s+TEST_GROUP\s*=\s*\'(.*)\'\s*;\s#', $Dump, $matches);
            $Group= isset($matches[1]) ? $matches[1] : '';
            if ($Caption !== '' && $Group !== '') {
                $Tests[]= array($File, $Caption, $Group);
            }
        }
        // return
        return $Tests;
    }


    /**
     * This method handles "sub-request" feature.
     *
     * @param string $Target
     */
    public function Forward($Target) {

        // split target into CLASS::METHOD
        list($Class, $Method)= explode('::', $Target);

        // convert 'Network.HttpClient.Test.Test__Network-HttpClient' classname
        //  to FQCN 'Accent\Network\HttpClient\Test\Test__Network-HttpClient'
        // using dots for escaping slashes prevents missuse with '..' in classname
        $File= 'Accent/'.str_replace('.', '/', $Class); // dots to slashes
        $Path= dirname(dirname(__DIR__)).'/'.$File.'.php';
        $Class= str_replace('/', '\\', $File);  // slashes to backslashes
        $Class= str_replace('-', '_', $Class);   // minus to underscore

        // safety check, search for "tag" in doc-block
        $Dump= file_get_contents($Path);
        $Parts= explode('*/', $Dump, 2);
        if (strpos(reset($Parts), '[TestModelForward]') === false) {
            die('Target does not expect test-forwarding!');
        }

        // call target
        require_once $Path;
        call_user_func(array(new $Class, 'ForwardTest_'.$Method));
    }


    /**
     * Recusively scan directory.
     */
    protected function ReadDirs($Path, $SubPath='') {

       //echo "\n<br>$Path, $SubPath.";
       $Path= rtrim($Path,'/');
       $SubPath= trim($SubPath,'/');
       $CondSlash= ($SubPath) ? '/' : '';
       $FullPath= $Path . $CondSlash . $SubPath;
       $F= @dir($FullPath);
       if ($F === false) {
           return false;
       }
       $Files= array();
       while (false !== ($Entry = $F->read())) {
           if ($Entry{0}=='.') continue; // skip everything beginning with dot
           if(is_dir("$FullPath/$Entry")) {
               $Files= array_merge($Files, $this->ReadDirs($Path, $SubPath.$CondSlash.$Entry));
           } else {
               if (substr($Entry,0,6)=='Test__' && substr($Entry,-4)=='.php') {
                   $Files[]= $SubPath.$CondSlash.$Entry;
               }
           }
       }
       $F->close();
       natcasesort($Files);
       return $Files;
    }


    /**
     * Returns filename of requested PHP file.
     */
    public function GetEntryFileName() {

        $BT= debug_backtrace();
        return basename($BT[1]['file']);
    }


    /**
     * Access controll check.
     */
    public function Firewall() {

        if (isset($_COOKIE['AccentTestPass']) && $_COOKIE['AccentTestPass'] === ACCENTTEST_PASSWORD) {
            return; // access granted by cookie
        }
        if (isset($_GET['AccentTestPass']) && $_GET['AccentTestPass'] === ACCENTTEST_PASSWORD) {
            return; // access granted by parameter, this is used by generated sub-calls
        }
        if (isset($_POST['Pass']) && $_POST['Pass'] === ACCENTTEST_PASSWORD) {
            setcookie('AccentTestPass', ACCENTTEST_PASSWORD, time()+86400*365*5);
            return; // confirmed entered password, set cookie and grant access
        }
        // display password form
        echo '<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>AccentPHP</title>
</head>
<body style="width:30em; margin:15em auto;">'
   .(isset($_POST['Pass'])?'<div style="border:1px solid #c00; color:#c00; padding:5px; margin:3em 0; text-align:center;">Invalid password!</div>':'').'
  <h1 style="color:#ccc; font:italic bold 24px sans-serif; text-shadow:2px 2px 2px #444; margin: 0;">AccentPHP tests</h1>
  <form action="'.ACCENTTEST_ENTRY_FILENAME.'" method="post" style="padding:1em; border:1px solid #ddd; text-align:center; font:16px sans-serif; color:#888;">
  Enter password: &nbsp; <input name="Pass" value="" size="30">
  </form>
</body></html>';
        die();
    }

}


/**
 * Decorater for reporting class of SimpleTest lib.
 */
class Accent_TestReporter extends HtmlReporter {

    protected $Stats;

    public function paintHeader($test_name) {
        // do not echo anything nothing
    }

    public function paintFooter($test_name) {
        // do not echo anything
        $this->Stats= array(
            $this->getPassCount(),
            $this->getFailCount(),
            $this->getExceptionCount(),
        );
    }

    public function GetStats() {

        return $this->Stats;
    }
}

?>