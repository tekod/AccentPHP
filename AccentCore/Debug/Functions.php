<?php

/**
 * Rich variable dumper.
 * Note: this method will echo content, not return it.
 *
 * @param mixed $Value  arbitrary value that need to be formatted
 * @param string $Caption  caption/title/label to be echoed above formatted value
 * @param bool|null $FormatingValue  internal, whether to format value or just to echo it,
 *                  null will not format value but will prepend debug's css styles
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

	// override eventual JSON header
	@header('Content-Type: text/html', true);

    // convert value to human friendly format
    if ($FormatingValue === true) {
        $Dump= trim($Debug->VarDump($Value, 1));
    } else {
        $Dump= strval($Value);
    }

    // prepare call-stack
    $ShortStackTrace= \Accent\AccentCore\Debug\Debug::ShowShortStack(' &rarr; ', 1);
    $DetailedStackTrace= \Accent\AccentCore\Debug\Debug::ShowSimplifiedStack([], 1);
    $DetailedStackTrace= preg_replace('~"(.*)"~', '"<b><i>$1</i></b>"', $DetailedStackTrace);

    // should echo debug styles?
    if ($FormatingValue === null) {
        echo $Debug->VarDumpStyles();
    }

    // pack HTML and echo it
    echo '
      <pre class="AccDbgVD" style="display:block; position:relative; background-color:#002840; color:#bcd; margin:6px 0; padding:1px 1em .6em 1em; font:normal 10px sans-serif; overflow-x:hidden; white-space:pre-line; min-width:80em; text-align:left; z-index:999; line-height:0;">'
        .'<div style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; direction:rtl; text-align:left; font:normal 11px sans-serif; color:#aaa; padding-right:2em;">'.$ShortStackTrace.'</div>'
        .'<abbr style="position:absolute; right:1em; top:0.5rem; cursor:pointer;" onclick="this.className=this.className===\'AccDbgVDO\'?\'\':\'AccDbgVDO\'">[...]</abbr>'
        .'<ul style="background-color:#023; border:1px solid #666; border-top:none; font-size:14px; line-height:19px;"><li>'.str_replace(["\n",'  '], ['</li><li>',' &nbsp; '], $DetailedStackTrace).'</li></ul>'
        .($Caption === '' ? '' : '<b style="color:#8cf; font-size:13px; line-height:normal">'.$Caption.' : </b>')
        .$Dump.'</pre>';

    // success, return string
    return gettype($Value);
}


/**
 * Same as "d();" but also terminate execution if authorized.
 *
 * @param mixed $Value  arbitrary value that need to be formatted
 * @param string $Caption  caption/title/label to be echoed above formatted value
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
    $Dump= '<pre style="margin:0; padding:0; border:none">'
			.'<table border="1" style="border-collapse:collapse; margin:0; font-size:15px; color:#cba;"><tr>'
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


/**
 * Return true if current request comes from authorized visitor.
 *
 * @return bool
 */
function d_is_authorized() {

    return \Accent\AccentCore\Debug\Debug::Instance('d-dump', ['AuthKey'=>false])->IsAuthorizedSession();
}