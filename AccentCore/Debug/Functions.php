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
    $Hdr= '';
    foreach($CallStack as $i=>$Trace) {
        $Where= (isset($Trace['file'])) ? basename($Trace['file']) : "unknown file";
        $Where .= (isset($Trace['line'])) ? "[$Trace[line]]" : "[?]";
        $Hdr= $Where.($i > 0 ? ' &rarr; ' : '').$Hdr;
    }

    // pack HTML and echo it
    echo '
      <pre style="display:block; position:relative; overflow:hidden; background-color:#002840; color:#bcd; margin:6px 0; padding:1.5em 1em .6em 2em; font:normal 10px sans-serif">'
        .'<div style="position:absolute; top:1px; right:0; min-width:100%; font:normal 11px sans-serif; color:#aaa; white-space:nowrap;">'.$Hdr.'</div>'
        .($Caption === '' ? '' : '<b>'.$Caption.' : </b>')
        .$Dump.'</pre>';

    // success, return string
    return gettype($Value);
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