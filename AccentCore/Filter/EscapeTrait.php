<?php namespace Accent\AccentCore\Filter;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/*
 * Collection of escaping functions.
 *
 * Typical usage:
 * echo $this->EscapeHTML($Message);    // prevent HTML messing (Anti-XSS protection)
 */


trait EscapeTrait {


    /**
     * Escaped javascript string will prevent unexpected closing-quote and newlines.
     * By default it is assumed that JS string using double qoutes for termination,
     * but there is additional parameter to modify that and specify all unsafe chars.
     */
    public function EscapeJsString($Content, $UnsafeChars='"') {

        // define map for escaping
        $From= array("\\","\n","\r",);
        $To= array("\\\\"," ", " ");

        // add to map unsafe chars but skip already defined
        foreach (str_split($UnsafeChars, 1) as $Char) {
            if (!in_array($Char, $From)) {
                $From[]= $Char;
                $To[]= "\\".$Char;
            }
        }

        // escape and return
        return str_replace($From, $To, $Content);
    }


    /**
     * Ensures that specified string will not be recognized as HTML content.
     */
    public function EscapeHTML($Content) {

        return htmlspecialchars($Content, ENT_QUOTES, 'UTF-8');
    }


    /**
     * Escaping chars that are not safe in HTML tag attribute values.
     */
    public function EscapeAttribute($Content, $EnclosedWithQuote=true) {

        return $EnclosedWithQuote
            ? str_replace('"', '&quot;', $Content)
            : str_replace("'", '&#x27;', $Content);
    }


    /**
     * Ensure that specified string will not break out of enclosed <style> scope.
     */
    public function EscapeCSS($Content) {

        return strip_tags($Content);
    }



    /**
     * Escaping path to be safely used as URL,
     * with or without "http:/" prefix.
     *
     * @param string $Path
     * @return string
     */
    public function EscapeWebPath($Path) {

        // basicaly do urlencode preserving "/" chars
        $Parts= explode('://', $Path, 2);
        $Out= implode('/', array_map('rawurlencode', explode('/', end($Parts))));
        return count($Parts) > 1
            ? "$Parts[0]://$Out"
            : $Out;
    }

}

?>