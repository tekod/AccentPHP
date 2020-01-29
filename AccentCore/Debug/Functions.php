<?php

/**
 * Rich variable dumper.
 * Note: this method will echo content, not return it.
 *
 * @param mixed $Value  arbitrary value that need to be formatted
 * @param sting $Caption  caption/title/lable to be echoed above formatted value
 * @param bool $FormatingValue  internal, whether to format value or just to echo it
 * @return false|string  false if session is not authorized or string with type of value
 */
function d($Value, $Caption='', $FormatingValue=true) {

    // get debugger
    $Debug= \Accent\AccentCore\Debug\Debug::Instance('d-dump', array(
        'AuthKey'=>false,       // forbid this session if instance is just created
    ));
    if (!$Debug->IsAuthorizedSession()) {
        // authorization fail, do not echo anything
        return false;
    }

    // convert value to human friendly format
    if ($FormatingValue) {
        $Dump= trim($Debug->VarDump($Value, 1));
    } else {
        $Dump= strval($Value);
    }

    // prepare call-stack
    $CallStack = debug_backtrace();
    $ShortStackTrace= '';
    foreach($CallStack as $i=>$Trace) {
        $Where= (isset($Trace['file'])) ? basename($Trace['file']) : "unknown file";
        $Where .= (isset($Trace['line'])) ? "[$Trace[line]]" : "[?]";
        $ShortStackTrace= $Where.($i > 0 ? ' &rarr; ' : '').$ShortStackTrace;
    }
    $DetailedStackTrace= \Accent\AccentCore\Debug\Debug::ShowSimplifiedStack([], 1);

    // pack HTML and echo it
    echo '
      <pre style="display:block; position:relative; background-color:#002840; color:#bcd; margin:6px 0; padding:1.5em 1em .6em 2em; font:normal 10px sans-serif; overflow-x:hidden">'
        .'<div style="position:absolute; top:1px; right:3em; min-width:100%; font:normal 11px sans-serif; color:#aaa; white-space:nowrap;">'
            .$ShortStackTrace
            .'<div class="AccDbgVD" style="padding-left:3em; position:relative;">'
                .'<abbr style="position:absolute; right:-2em; top:-1em; cursor:pointer;" onclick="this.className=this.className===\'AccDbgVDO\'?\'\':\'AccDbgVDO\'">[...]</abbr>'
                .'<ul style="background-color:#124; border:1px solid gray; margin:-0.5em 0 0 1em; float:right; text-align:left;"><li>'.str_replace(["\n",'  '], ['</li><li>',' &nbsp; '], $DetailedStackTrace).'</li></ul>'
            .'</div>'
        .'</div>'
        .($Caption === '' ? '' : '<b>'.$Caption.' : </b>')
        .$Dump.'</pre>';

    // success, return string
    return gettype($Value);
}


/**
 * Same as "d();" but also terminate execution if authorized.
 *
 * @param mixed $Value  arbitrary value that need to be formatted
 * @param sting $Caption  caption/title/lable to be echoed above formatted value
 * @param bool $FormatingValue  internal, whether to format value or just to echo it
 */
function d_d($Value, $Caption='', $FormatingValue=true) {

    if (d($Value, $Caption, $FormatingValue)) {
        die();
    }
}


/**
 * Dump string content in hexadecimal table style.
 *
 * @param mixed $Value
 * @param string $Caption
 */
function d_hex($Value, $Caption='') {

    $Tr= array_merge(range(chr(0),chr(31)), range(chr(128),chr(255)));
    $Hex= str_split(strtoupper(bin2hex($Value)), 32);
    $Chars= str_split(strtr($Value, array_fill_keys($Tr,'.')), 16);

    $Col= array(array(), array(), array());
    foreach ($Hex as $i => $Line) {
        $HexLine= str_pad(implode(' ', str_split($Line,2)), 48, ' ');
        if (strlen($Line) > 16) {$HexLine[23]='.';}
        $Col[0][]= sprintf("%'.06d", $i*16);
        $Col[1][]= $HexLine;
        $Col[2][]= htmlspecialchars($Chars[$i], ENT_COMPAT, 'UTF-8');
    }
    $Dump= '<pre><table border="1" style="border-collapse:collapse; font-size:inherit; color:#cba;"><tr>'
            .'<td style="padding:3px 5px; background-color:#112">'.implode('<br>',$Col[0]).'</td>'
            .'<td style="padding:3px 8px; background-color:#001">'.implode('<br>',$Col[1]).'</td>'
            .'<td style="padding:3px 5px; background-color:#112">'.implode('<br>',$Col[2]).'</td>'
            .'</tr></table></pre>';
    d($Dump, $Caption, false);
}


/**
 * Initialize instance of debugger for usage with "d()" function.
 * Its main task is to inject authorization key into debugger.
 *
 * @param string $AuthKey  random alpha-num string, minimum 10 chars long
 * @param string $CookieName  name of cookie (optional)
 */
function d_initialize($AuthKey, $CookieName='AccentDebugKey') {

    // load class, just in case that autoload not working yet
    require_once 'Debug.php';

    // create instance of debugger
    \Accent\AccentCore\Debug\Debug::Instance('d-dump', array(
        'AuthKey'=> $AuthKey,
        'CookieName'=> $CookieName,
    ));
}



?>