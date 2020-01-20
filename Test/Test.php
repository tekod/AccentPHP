<?php

/* Application for testing Accent components.
 *
 * Call this script directly from browser with full path because
 * it is not reachable via mod_rewrite.
 *
 * Note that autoloader and many other services are not used here
 * to maximize isolation from unnecessary program code.
 *
 * Security hint: tests can take up a lot of CPU and execution time,
 * so remember to block access to this script in production server.
 */

// initial settings
error_reporting(E_ALL & ~E_STRICT);
ini_set('display_errors', 'On');
date_default_timezone_set('UTC');
define('NOW', time());

// load debug functions
require __DIR__.'/../AccentCore/Debug/Functions.php';
d_initialize(ACCENTTEST_PASSWORD, 'AccentTestPass');

// load model
require __DIR__.'/TestModel.php';
$Model= new \AccentTestModel();

// resolve entry filename
if (!defined('ACCENTTEST_ENTRY_FILENAME')) {
    define('ACCENTTEST_ENTRY_FILENAME', $Model->GetEntryFileName());
}

// validate access
$Model->Firewall();

// handle 'Run' action
if (isset($_GET['Act']) && $_GET['Act'] === 'Run') {

    // check is this sub-request made by browser's cached test-page
    if (!isset($_POST['SingleTest'])) {
        die();                  // just return empty iframe
    }

    // get array of requested tests
    $Tests= $_POST['SingleTest'] <> ''
        ? array($_POST['SingleTest'])
        : (isset($_POST['Tests']) ? $_POST['Tests'] : array());

    // execute model
    $Model->Handle($Tests);
    die();
}

// handle 'Forward' action
if (isset($_GET['Act']) && $_GET['Act'] === 'Forward') {

    $Model->Forward($_GET['Target']);
    die();
}


// create list of tests
$Groups= array();
foreach($Model->GetAllTests() as $TestFile) {
    // analyze grouping info
    $Record= array_map('trim', explode(':', $TestFile[2]));
    $Group= $Record[0];
    $Order= isset($Record[1]) ? $Record[1] : null;
    // intitialize new group
    if (!isset($Groups[$Group])) {
        $Groups[$Group]= array();
    }
    // prevent accidentally overwrite existing test item
    if (isset($Groups[$Group][$Order])) {
        $Order= null;  // remove index, just append to list
    }
    // add to group
    if ($Order) {
        $Groups[$Group][$Order]= $TestFile;
    } else {
        $Groups[$Group][]= $TestFile;
    }
}



// echo page
?><!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>AccentPHP testing enviroment</title>
  <meta name="generator" content="Accent - www.accentphp.com" />
  <link rel="icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAADkSURBVHjaYvz//z8DPiB+JR6vgpc6CxnxyTPisgBm8NsbTxiIAX9C9jISZQGpBhOyCMUCkOHkGozLEiZqGw4CLGuc/2NYQCvARG3Xo/uCKj747r8Dtw8odT3McGyWsCSp/2eilss5N3rgjgNaGY7XApABuMKWWMOJ8gG6JaQYjtcCZM3YIpIYwwn6AJslpBhOVBChG0aK4WALCJXnyIaSZPi2Jwx/5t1kJDqZkupylCDCVVmQDaCup19pSlVfILkea5WJXFmQajDYoUiG4630ibYIh8EELUAucvHWvzgMhgGAAAMA4aiLWKBQQ80AAAAASUVORK5CYII=">
  <style type="text/css">
	body {background-color:#1a1a1c; color:#ccc; font:12px sans-serif; height:100%; margin:0; overflow:hidden;}
	.fail {background-color:inherit; color:red; }
	.pass {background-color:inherit; color:green; }
	pre { background-color:#000; color:#aef; padding:1em 2em; } /* lightgray; inherit; */
    a {color:#567; text-decoration:none;}
    a:hover {color:yellow;}
    form {display:block; text-align:left; background-color:#161618; margin:0 0 2em 0;}
    #TestHeader {color:#444; font:italic bold 24px sans-serif; left:1em; position:absolute; text-shadow:2px 2px 2px #000; top:0.2em;}
    #TestMenu {background-color:#111; height:calc(100% - 4em); left:1em; overflow:auto; position:absolute; top:3em; width:30em;}
    #TestFrame {background-color:#000; border:none; bottom:1em; height:calc(100% - 4em); position:absolute; right:1em; top:3em; width:calc(100% - 33em);}
    #TestMenuCaption {background-color:#222; padding:0.5em 1em; font-size:120%;}
    #TestMenuFooter {position:relative; padding:3em;}
    #TestMenuFooter div {position:absolute; right:5em; top:2em;}
    /*#ResultFooter {letter-spacing:0.1em;}*/
    /*.TestPanel {border:1px solid #333; padding:1em 3em; background-color:#111;}*/
    /*.Warning {margin:1em 0; padding:0.5em 2em; background-color:#323; color:#D8D;}*/

    .TestGroup {background-color:#272728; border-top:1px solid #383838; border-bottom:2px solid #000; color:#ddd; padding:0.5em;}
    .TestGroupTitle {position:relative;}
    .TestGroupTitle span {font-size:120%; font-weight:bold; cursor:pointer; color:#888;}
    .TestGroupTitle span:hover {color:yellow;}
    .TestGroupTitle input {vertical-align:middle; margin:0 0.5em}
    .TestArrow {position:absolute; right:1em; top:0; font-size:140%; cursor:pointer; color:#888;}
    .TestArrow:hover {color:yellow;}
    .TestGroup ul {display:none; width:100%; list-style:none; margin:1em 0 0 0; padding:0;}
    .TestGroup li {background-color:#222; border-top:2px solid #000; border-bottom:1px solid #383838; color:#ddd; padding:0.5em 0 0.5em 2em; position:relative}
    .TestGroup li input {margin: 0 0.6em; vertical-align: middle;}
  </style>
  <script>
    function TestGroupToggle(ref) {
          var ul= ref.parentNode.parentNode.getElementsByTagName('ul')[0];
          ul.style.display= ul.style.display === 'block' ? 'none' : 'block';
    }
    function TestGroupSelect(ref) {
        var cbs= ref.parentNode.parentNode.getElementsByTagName('input');
        for(var x=0,xmax=cbs.length;x<xmax;x++){cbs[x].checked= ref.checked;}
    }
    function TestRun(ref) {
        TestRunSpinner();
        var test= ref.parentNode.getElementsByTagName('input')[0].value;
        var input= document.getElementById('TestInputSingleTest');
        input.value= test;
        document.getElementsByTagName('form')[0].submit();
        input.value= '';
    }
    function TestRunSpinner() {
        var Spinner= document.getElementById('TestFrame').contentDocument.getElementById('Spinner');
        if (Spinner) {Spinner.style.display='block'; setTimeout(function(){Spinner.style.opacity='0.7';}, 50);}
    }
  </script>
</head>

<body>

    <div id="TestHeader">AccentPHP testing enviroment</div>

    <div id="TestMenu">
        <div id="TestMenuCaption">List of tests</div>
        <form action="<?=ACCENTTEST_ENTRY_FILENAME?>?Act=Run" method="post" target="TestFrame">
        <?php
            foreach($Groups as $GroupName=>$GroupItems) {
        ?>
            <div class="TestGroup" data-testgroup="<?php echo $GroupName; ?>">
                <div class="TestGroupTitle">
                    <span onclick="TestGroupToggle(this);">+</span>
                    <input type="checkbox" class="checkbox" name="TestGroup" value="<?php echo $GroupName; ?>" onclick="TestGroupSelect(this);" />
                    <?php echo htmlentities($GroupName); ?>
                </div>
                <ul>
                <?php
                    ksort($GroupItems);
                    foreach ($GroupItems as $ItemName=>$ItemStruct) {
                ?>
                    <li>
                        <input type="checkbox" class="checkbox" name="Tests[]" value="<?php echo $ItemStruct[0]; ?>" />
                        <?php echo htmlentities($ItemStruct[1]); ?>
                        <div class="TestArrow" onclick="TestRun(this);">&#10140;</div>
                   </li>
                <?php
                    }
                ?>
                </ul>
            </div>
        <?php
            }
        ?>
        <div id="TestMenuFooter">
            <input type="submit" value="Run selected tests" onclick="TestRunSpinner();" />
            <div>
                <a href="javascript:void(0)" onclick="var C=this.parentNode.parentNode.parentNode.getElementsByTagName('input');for(var x=0,xmax=C.length;x<xmax;x++)C[x].checked=true;">Select all</a>
                <br><br>
                <a href="javascript:void(0)" onclick="var C=this.parentNode.parentNode.parentNode.getElementsByTagName('input');for(var x=0,xmax=C.length;x<xmax;x++)C[x].checked=false;">Clear</a>
            </div>
        </div>
        <input type="hidden" name="SingleTest" id="TestInputSingleTest" value="" />
    </form>
  </div>

  <iframe id="TestFrame" name="TestFrame" src="" onload="this.contentDocument.children[0].style.color='#a98';"></iframe>

</body>
</html>