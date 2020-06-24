<?php namespace Accent\AccentCore\UTF;


/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

use \Accent\AccentCore\Component;


class UTF extends Component {


    protected static $DefaultOptions= array(
        'ForceEngine'=> null, // 'mbstring' or 'iconv' or null for autodetect
    );

	protected $Engine;


	public function __construct($Options=array()) {

        parent::__construct($Options);

        // set engine
	    if (!is_null($this->Options['ForceEngine'])) {
	        $this->Engine= $this->Options['ForceEngine'];
	    } else if (function_exists('mb_strlen')) {
	        $this->Engine= 'mbstring';
	        mb_internal_encoding('UTF-8');
	    } else if (function_exists('iconv_strlen')) {
	        $this->Engine= 'iconv';
            if (PHP_VERSION_ID < 50600) {
                iconv_set_encoding('internal_encoding', 'UTF-8');
            } else {
                ini_set('default_charset', 'UTF-8');
            }
	    } else if (@preg_match( '//u', '' ) === false) {
	        $this->Engine= 'pcre';          // not completly implemented
	    }
        // everything else will use slower methods (mathematics, regex,...)
	}


	public function strlen($String) {

		if ($this->Engine=='mbstring') {
			return mb_strlen($String, 'UTF-8');
		}
		if ($this->Engine=='iconv') {
			return iconv_strlen($String, 'UTF-8');
    	}
   		return strlen(preg_replace("/[\x80-\xBF]/", '', $String));
	}



	public function substr($String, $Start=0, $Length=null) {

	    $Start = (integer)$Start;
	    if (!is_null($Length)) $Length= intval($Length);
	    $StringLength= (($Start<0)or(($Length!==null)and($Length<0)))
            ? $this->strlen($String)
            : 0;
	    if ($Start<0) $Start= max(0, $StringLength+$Start);
	    if ((($Length!==null)and($Length<0))) $Length= max(0, $StringLength-$Start+$Length);
	    if ($Length===0) return '';

	    if ($this->Engine=='mbstring') {
	        if ($Length===null) $Length= mb_strlen($String);
	        return mb_substr($String, $Start, $Length, 'UTF-8');
	    }
	    if($this->Engine=='iconv') {
	        if ($Length==null)	{
	            return iconv_substr($String, $Start, $this->strlen($String), 'UTF-8');
	        }
	        return iconv_substr($String, $Start, $Length, 'UTF-8');
	    }
	    // slower method
	    $StrLen = strlen($String);
	    $Bytes = 0;       // Find the starting byte offset.
	    if ($Start > 0) { // Count all the continuation bytes from the start until we have found
	        $Bytes = -1; $Chars = -1; // $start characters or the end of the string.
	        while ($Bytes < $StrLen - 1 && $Chars < $Start) {
	            $Bytes++;
	            $c = ord($String[$Bytes]);
	            if ($c < 0x80 || $c >= 0xC0) {
	                $Chars++;
	            }
	        }
            if ($Chars < $Start) $Bytes++; // reached end of string
	    } elseif ($Start < 0) { // Count all the continuation bytes from the end until we have found abs($start) characters.
	        $Start = abs($Start);
	        $Bytes = $StrLen; $Chars = 0;
	        while ($Bytes > 0 && $Chars < $Start) {
	            $Bytes--;
	            $c = ord($String[$Bytes]);
	            if ($c < 0x80 || $c >= 0xC0) {
	                $Chars++;
	            }
	        }
	    }
	    $iStart = $Bytes;

	    // Find the ending byte offset.
	    if ($Length === NULL) {
	        $iEnd = $StrLen;
	    } elseif ($Length > 0) {
	        // Count all the continuation bytes from the starting index until we have
	        // found $length characters or reached the end of the string, then
	        // backtrace one byte.
	        $iEnd = $iStart - 1; $Chars = -1;
	        while ($iEnd < $StrLen - 1 && $Chars < $Length) {
	            $iEnd++;
	            $c = ord($String[$iEnd]);
	            if ($c < 0x80 || $c >= 0xC0) {
	                $Chars++;
	            }
	        }
	        // Backtrace one byte if the end of the string was not reached.
	        if ($iEnd < $StrLen - 1 || $Chars >= $Length) {     // ADDED my mV: " || $Chars >= $Length"
	            $iEnd--;
	        }
	    }
	    elseif ($Length < 0) {
	        // Count all the continuation bytes from the end until we have found
	        // abs($start) characters, then backtrace one byte.
	        $Length = abs($Length);
	        $iEnd = $StrLen; $Chars = 0;
	        while ($iEnd > 0 && $Chars < $Length) {
	            $iEnd--;
	            $c = ord($String[$iEnd]);
	            if ($c < 0x80 || $c >= 0xC0) {
	                $Chars++;
	            }
	        }
	        // Backtrace one byte if we are not at the beginning of the string.
	        if ($iEnd > 0) {
	            $iEnd--;
	        }
	    }
	    else {
	        // $length == 0, return an empty string.
	        $iEnd = $iStart - 1;
	    }
	    return substr($String, $iStart, max(0, $iEnd - $iStart + 1));
	} // substr



	public function strpos($Haystack, $Needle, $Offset=0)	{

        if ($Needle==='') {
            return false;
        }
        if($this->Engine=='mbstring') {
			return mb_strpos($Haystack, $Needle, $Offset, 'UTF-8');
		}
		if($this->Engine=='iconv') {
			return iconv_strpos($Haystack, $Needle, $Offset, 'UTF-8');
		}
		$ByteOffset= $this->CharToBytePos($Haystack, $Offset);
		if ($ByteOffset === false)	return false; // offset beyond string length
		$BytePos= strpos($Haystack, $Needle, $ByteOffset);
		if ($BytePos === false)	return false; // needle not found
		return $this->ByteToCharPos($Haystack, $BytePos);
	}


	public function strrpos($Haystack, $Needle, $Offset=null) {

        if ($Needle==='') {
            return false;
        }
		if($this->Engine=='mbstring') {
            if ($Offset===null) $Offset= $this->strlen($Haystack);
			return mb_strrpos($Haystack, $Needle, $Offset, 'UTF-8');
		}
		if($this->Engine=='iconv') {
            // solving missing $Offset parametar in 'iconv_strrpos'
            if ($Offset<0) $Haystack= $this->substr($Haystack,0,$Offset);
            $Res= iconv_strrpos($Haystack, $Needle, 'UTF-8');
            return ($Res!==false && $Offset>0 && $Res<$Offset) ? false : $Res;
		}
        if ($Offset<0) $Haystack= $this->substr($Haystack,0,$Offset);
        $ar= explode($Needle, $Haystack);
        if (count($ar)==1) return false;
        array_pop($ar); // pop off the end of the string where the last match found
        $Pos= $this->strlen(implode($Needle,$ar));
        return ($Pos!==false && $Offset>0 && $Pos<$Offset) ? false : $Pos;
  	}


    public function str_split($String, $Length=1) {

        $Result= array();
        if ($this->Engine=='mbstring' || $this->Engine=='iconv') {
            $StrLength= $this->strlen($String);
            for($x=0; $x<$StrLength; $x=$x+$Length) {
                $Result[]= $this->substr($String, $x, $Length);
            }
            return $Result;
        }
        if ($this->Engine=='pcre') {
            if ($Length == 1) {
                return preg_split("//u", $String, -1, PREG_SPLIT_NO_EMPTY);
            }
            $Array= preg_split('/(?<!^)(?!$)/u', $String, -1, PREG_SPLIT_NO_EMPTY);
            $StrLength= count($Array);
            for($x=0; $x<$StrLength; $x=$x+$Length) {
                $Result[]= implode('', array_slice($Array, $x, $Length));
            }
            return $Result;
        }
        $Len= $this->strlen($String);
        for($i=0; $i<$Len; $i+=$Length) {
            $Result[]= $this->substr($String, $i, $Length);
        }
        return $Result;
    }


	public function strtolower($String) {

        // not supported by iconv
	    if ($this->Engine=='mbstring') {
	       return mb_strtolower($String, 'UTF-8');
	    }
	    $UniStr= $this->ToUnicode($String);
    	if ($UniStr === false)
	       return false;
    	for ($i=0, $c=count($UniStr); $i<$c; $i++) {
		  if (isset($this->UTF8_UPPER_TO_LOWER[$UniStr[$i]])) {
			$UniStr[$i]= $this->UTF8_UPPER_TO_LOWER[$UniStr[$i]];
		  }
	    }
	    return $this->FromUnicode($UniStr);
	}


	public function strtoupper($String) {

	    // not supported by iconv
	    if($this->Engine=='mbstring') {
	       return mb_strtoupper($String, 'UTF-8');
	    }
	    $UniStr= $this->ToUnicode($String);
	    if ($UniStr === false) {
		  return false;
        }
    	for ($i=0, $c=count($UniStr); $i<$c; $i++) {
		  if (isset($this->UTF8_LOWER_TO_UPPER[$UniStr[$i]])) {
			$UniStr[$i]= $this->UTF8_LOWER_TO_UPPER[$UniStr[$i]];
		  }
	    }
        return $this->FromUnicode($UniStr);
	}


    public function str_pad($String, $Length, $PadStr=' ', $Direction=STR_PAD_RIGHT) {

        $PadBefore= $Direction === STR_PAD_BOTH || $Direction === STR_PAD_LEFT;
        $PadAfter= $Direction === STR_PAD_BOTH || $Direction === STR_PAD_RIGHT;
        $Length -= $this->strlen($String);
        $TargetLen= $PadBefore && $PadAfter ? $Length / 2 : $Length;
        $StrToRepeatLen= $this->strlen($PadStr);
        $RepeatTimes= ceil($TargetLen / $StrToRepeatLen);
        $RepeatedString= str_repeat($PadStr, max(0, $RepeatTimes));
        $Before= $PadBefore ? $this->substr($RepeatedString, 0, floor($TargetLen)) : '';
        $After = $PadAfter ? $this->substr($RepeatedString, 0, ceil($TargetLen)) : '';
        return $Before . $String . $After;
    }

	public function ucfirst($String){

		// uppercasing first char in string
		return $this->strtoupper($this->substr($String,0,1)) . $this->substr($String,1);
	}


	public function chr($c) {

		// converts integer (as unicode number) into char as UTF8 string
	    if ($c <= 0x7F) {
    	    return chr($c);
	    } else if ($c <= 0x7FF) {
	        return chr(0xC0 | $c >> 6) . chr(0x80 | $c & 0x3F);
	    } else if ($c <= 0xFFFF) {
	        return chr(0xE0 | $c >> 12) . chr(0x80 | $c >> 6 & 0x3F)
	                                    . chr(0x80 | $c & 0x3F);
	    } else if ($c <= 0x10FFFF) {
	        return chr(0xF0 | $c >> 18) . chr(0x80 | $c >> 12 & 0x3F)
	                                    . chr(0x80 | $c >> 6 & 0x3F)
	                                    . chr(0x80 | $c & 0x3F);
	    } else {
	        return false;
	    }
	}


	protected function CharToBytePos($Str,$Pos)	{
		// Translates a character position into an 'absolute' byte position. str if utf8
		$n= 0;				// number of characters found
		$p= abs($Pos);		// number of characters wanted
		if ($Pos >= 0)	{
			$i= 0;
			$d= 1;
		} else {
			$i= strlen($Str)-1;
			$d= -1;
		}
		for( ; strlen($Str{$i}) && $n<$p; $i+=$d)	{
			$c= (int)ord($Str{$i});
			if (!($c & 0x80)) $n++;	// single-byte (0xxxxxx)
			  elseif (($c & 0xC0) == 0xC0) $n++;	// multi-byte starting byte (11xxxxxx)
		}
		if (!strlen($Str{$i})) return false; // offset beyond string length
		if ($Pos >= 0)	{
				// skip trailing multi-byte data bytes
			while ((ord($Str{$i}) & 0x80) && !(ord($Str{$i}) & 0x40)) { $i++; }
		} else {
			$i++; // correct offset
		}
		return $i;
	}


	protected function ByteToCharPos($Str,$Pos)	{
		// Translates an 'absolute' byte position into a character position, str if utf8
		$n= 0;	// number of characters
		for($i=$Pos; $i>0; $i--)	{
			$c= (int)ord($Str{$i});
			if (!($c & 0x80)) $n++;	// single-byte (0xxxxxx)
			elseif (($c & 0xC0) == 0xC0) $n++;	// multi-byte starting byte (11xxxxxx)
		}
		if (!strlen($Str{$i}))	return false; // offset beyond string length
		return $n;
	}



	public function ConvertToEnt($String, $MaxChar=0x7F, $Entities=true) {

		if(!$String) {
            return '';
        }
		$Returns= "";
		$UTF8len= array(	1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
							1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0,
							0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 2, 2, 2, 2, 2,
							2, 2, 3, 3, 3, 3, 4, 4, 5, 6);
		$Pos= 0;
		$Total= strlen($String);
		do {
			$c= ord($String[$Pos]);
			$Len= $UTF8len[($c >> 2) & 0x3F];
			switch ($Len) {
				case 6: $u = $c & 0x01;	break;
				case 5: $u = $c & 0x03;	break;
				case 4: $u = $c & 0x07;	break;
				case 3: $u = $c & 0x0F;	break;
				case 2: $u = $c & 0x1F; break;
				case 1: $u = $c & 0x7F;	break;
				case 0:	/* unexpected start of a new character */
					$u = $c & 0x3F;
					$Len = 5;
					break;
			}
			while (--$Len && (++$Pos < $Total && $c = ord($String[$Pos]))) {
				if (($c & 0xC0) == 0x80) {
					$u = ($u << 6) | ($c & 0x3F);
				} else {
					/* unexpected start of a new character */
					$Pos--;
					break;
				}
			}
			if ($u <= $MaxChar) $Returns .= chr($u);
			 elseif ($Entities)  $Returns .= '&#'.$u.';';
			 else  $Returns .= '?';
		} while (++$Pos < $Total);
		return $Returns;
	}


	public function UTF8ToISO8859($String, $Entities=true) {

        return self::convert2ent($String, 0xFF, $Entities);
	}



    public function UTF16ToUTF8($UTF16Strign) {

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($UTF16Strign, 'UTF-8', 'UTF-16');
        }
        // slower method then mb_convert_encoding
        $Bytes = (ord($UTF16Strign[0]) << 8) | ord($UTF16Strign[1]);
        switch(true) {
            case ((0x7F & $Bytes) == $Bytes):
                // this case should never be reached, because we are in ASCII range
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr(0x7F & $Bytes);

            case (0x07FF & $Bytes) == $Bytes:
                // return a 2-byte UTF-8 character
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr(0xC0 | (($Bytes >> 6) & 0x1F))
                     . chr(0x80 | ($Bytes & 0x3F));

            case (0xFFFF & $Bytes) == $Bytes:
                // return a 3-byte UTF-8 character
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr(0xE0 | (($Bytes >> 12) & 0x0F))
                     . chr(0x80 | (($Bytes >> 6) & 0x3F))
                     . chr(0x80 | ($Bytes & 0x3F));
        }
        // ignoring UTF-32 for now, sorry
        return '';
    }



    public function UTF8ToUTF16($UTF8String) {

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($UTF8String, 'UTF-16', 'UTF-8');
        }
		// slower method then mb_convert_encoding
        switch(strlen($UTF8String)) {
            case 1:
                // this case should never be reached, because we are in ASCII range
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return $UTF8String;
            case 2:
                // return a UTF-16 character from a 2-byte UTF-8 char
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr(0x07 & (ord($UTF8String[0]) >> 2))
                     . chr((0xC0 & (ord($UTF8String[0]) << 6))
                         | (0x3F & ord($UTF8String[1])));
            case 3:
                // return a UTF-16 character from a 3-byte UTF-8 char
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr((0xF0 & (ord($UTF8String[0]) << 4))
                         | (0x0F & (ord($UTF8String[1]) >> 2)))
                     . chr((0xC0 & (ord($UTF8String[1]) << 6))
                         | (0x7F & ord($UTF8String[2])));
        }
        // ignoring UTF-32 for now, sorry
        return '';
    }


	protected function FromUnicode($Arr) {

	    ob_start();

	    $Keys = array_keys($Arr);

	    foreach ($Keys as $k)
	    {
	        // ASCII range (including control chars)
	        if (($Arr[$k] >= 0) AND ($Arr[$k] <= 0x007f))
	        {
	            echo chr($Arr[$k]);
	        }
	        // 2 byte sequence
	        elseif ($Arr[$k] <= 0x07ff)
	        {
	            echo chr(0xc0 | ($Arr[$k] >> 6));
	            echo chr(0x80 | ($Arr[$k] & 0x003f));
	        }
	        // Byte order mark (skip)
	        elseif ($Arr[$k] == 0xFEFF)
	        {
	            // nop -- zap the BOM
	        }
	        // Test for illegal surrogates
	        elseif ($Arr[$k] >= 0xD800 AND $Arr[$k] <= 0xDFFF)
	        {
	            // Found a surrogate
	            $this->Error("UTF/FromUnicode: Illegal surrogate at index: $k, value: $Arr[$k]");
	            return false;
	        }
	        // 3 byte sequence
	        elseif ($Arr[$k] <= 0xffff)
	        {
	            echo chr(0xe0 | ($Arr[$k] >> 12));
	            echo chr(0x80 | (($Arr[$k] >> 6) & 0x003f));
	            echo chr(0x80 | ($Arr[$k] & 0x003f));
	        }
	        // 4 byte sequence
	        elseif ($Arr[$k] <= 0x10ffff)
	        {
	            echo chr(0xf0 | ($Arr[$k] >> 18));
	            echo chr(0x80 | (($Arr[$k] >> 12) & 0x3f));
	            echo chr(0x80 | (($Arr[$k] >> 6) & 0x3f));
	            echo chr(0x80 | ($Arr[$k] & 0x3f));
	        }
	        // Out of range
	        else
	        {
                $this->Error('UTF/FromUnicode: Codepoint out of Unicode range at index: '.$k.', value: '.$Arr[$k]);
	            return false;
	        }
	    }

	    $Result = ob_get_contents();
	    ob_end_clean();
	    return $Result;
	}


	protected function ToUnicode($String) {

	    $mState= 0; // cached expected number of octets after the current octet until the beginning of the next UTF8 character sequence
	    $mUcs4 = 0; // cached Unicode character
	    $mBytes= 1; // cached expected number of octets in the current sequence

	    $out= array();

	    $len= strlen($String);

	    for ($i = 0; $i < $len; $i++)
	    {
	        $in = ord($String[$i]);

	        if ($mState == 0)
	        {
	            // When mState is zero we expect either a US-ASCII character or a
	            // multi-octet sequence.
	            if (0 == (0x80 & $in))
	            {
	                // US-ASCII, pass straight through.
	                $out[] = $in;
	                $mBytes = 1;
	            }
	            elseif (0xC0 == (0xE0 & $in))
	            {
	                // First octet of 2 octet sequence
	                $mUcs4 = $in;
	                $mUcs4 = ($mUcs4 & 0x1F) << 6;
	                $mState = 1;
	                $mBytes = 2;
	            }
	            elseif (0xE0 == (0xF0 & $in))
	            {
	                // First octet of 3 octet sequence
	                $mUcs4 = $in;
	                $mUcs4 = ($mUcs4 & 0x0F) << 12;
	                $mState = 2;
	                $mBytes = 3;
	            }
	            elseif (0xF0 == (0xF8 & $in))
	            {
	                // First octet of 4 octet sequence
	                $mUcs4 = $in;
	                $mUcs4 = ($mUcs4 & 0x07) << 18;
	                $mState = 3;
	                $mBytes = 4;
	            }
	            elseif (0xF8 == (0xFC & $in))
	            {
	                // First octet of 5 octet sequence.
	                //
	                // This is illegal because the encoded codepoint must be either
	                // (a) not the shortest form or
	                // (b) outside the Unicode range of 0-0x10FFFF.
	                // Rather than trying to resynchronize, we will carry on until the end
	                // of the sequence and let the later error handling code catch it.
	                $mUcs4 = $in;
	                $mUcs4 = ($mUcs4 & 0x03) << 24;
	                $mState = 4;
	                $mBytes = 5;
	            }
	            elseif (0xFC == (0xFE & $in))
	            {
	                // First octet of 6 octet sequence, see comments for 5 octet sequence.
	                $mUcs4 = $in;
	                $mUcs4 = ($mUcs4 & 1) << 30;
	                $mState = 5;
	                $mBytes = 6;
	            }
	            else
	            {
	                // Current octet is neither in the US-ASCII range nor a legal first octet of a multi-octet sequence.
	                $this->Error('ToUnicode: Illegal sequence identifier in UTF-8 at byte '.$i);
	                return false;
	            }
	        }
	        else
	        {
	            // When mState is non-zero, we expect a continuation of the multi-octet sequence
	            if (0x80 == (0xC0 & $in))
	            {
	                // Legal continuation
	                $shift = ($mState - 1) * 6;
	                $tmp = $in;
	                $tmp = ($tmp & 0x0000003F) << $shift;
	                $mUcs4 |= $tmp;

	                // End of the multi-octet sequence. mUcs4 now contains the final Unicode codepoint to be output
	                if (0 == --$mState)
	                {
	                    // Check for illegal sequences and codepoints

	                    // From Unicode 3.1, non-shortest form is illegal
	                    if (((2 == $mBytes) AND ($mUcs4 < 0x0080)) OR
	                    ((3 == $mBytes) AND ($mUcs4 < 0x0800)) OR
	                    ((4 == $mBytes) AND ($mUcs4 < 0x10000)) OR
	                    (4 < $mBytes) OR
	                    // From Unicode 3.2, surrogate characters are illegal
	                    (($mUcs4 & 0xFFFFF800) == 0xD800) OR
	                    // Codepoints outside the Unicode range are illegal
	                    ($mUcs4 > 0x10FFFF))
	                    {
	                        $this->Error('ToUnicode: Illegal sequence or codepoint in UTF-8 at byte '.$i);
	                        return FALSE;
	                    }

	                    if (0xFEFF != $mUcs4)
	                    {
	                        // BOM is legal but we don't want to output it
	                        $out[] = $mUcs4;
	                    }

	                    // Initialize UTF-8 cache
	                    $mState = 0;
	                    $mUcs4  = 0;
	                    $mBytes = 1;
	                }
	            }
	            else
	            {
	                // ((0xC0 & (*in) != 0x80) AND (mState != 0))
	                // Incomplete multi-octet sequence
	                $this->Error('ToUnicode: Incomplete multi-octet sequence in UTF-8 at byte '.$i);
	                return false;
	            }
	        }
	    }

	    return $out;
	} // ToUnicode



	public function SimplifiedDiacritics($Text) {
        // TODO: add more ....
	  $Codes= array(
		// Default translations, can be overriden later
		'À'=>'A',	'Á'=>'A',	'Â'=>'A',	'Ã'=>'A',	'Ä'=>'A',	'Å'=>'A',	'Æ'=>'A',
		'à'=>'a',	'á'=>'a',	'â'=>'a',	'ã'=>'a',	'ä'=>'a',	'å'=>'a',	'æ'=>'a',
		'Ç'=>'C',	'ç'=>'c',
		"Č"=>"C",	"č"=>"c",	'ÄŒ'=>'C',	'Ä'=>'c',
		"Ć"=>"C",	"ć"=>"c",	'Ä†'=>'C',	'Ä‡'=>'c',
		"Đ"=>"Dj",	"Ð"=>"Dj",	"đ"=>"dj",	'Ä?'=>'Dj',	'Ä‘'=>'dj',
		'È'=>'E',	'É'=>'E',	'Ê'=>'E',	'Ë'=>'E',
		'è'=>'e',	'é'=>'e',	'ê'=>'e',	'ë'=>'e',
		'Ì'=>'I',	'Í'=>'I',	'Î'=>'I',	'Ï'=>'I',
		'ì'=>'i',	'í'=>'i',	'î'=>'i',	'ï'=>'i',
		'Ñ'=>'N',	'ñ'=>'n',
		'Ò'=>'O',	'Ó'=>'O',	'Ô'=>'O',	'Õ'=>'O',	'Ö'=>'O',	'Ø'=>'O',	'Œ'=>'O',
		'ò'=>'o',	'ó'=>'o',	'ô'=>'o',	'õ'=>'o',	'ö'=>'o',	'ø'=>'o',	'ð'=>'o',	'œ'=>'o',
		"Š"=>"S",	"š"=>"s",	'Å '=>'S',	'Å¡'=>'s',	'ß'=>'s',
		'Ù'=>'U',	'Ú'=>'U',	'Û'=>'U',	'Ü'=>'U',
		'Ÿ'=>'Y',	'¥'=>'Y',	'Ý'=>'Y',	'ý'=>'y',	'ÿ'=>'y',
		'ù'=>'u',	'ú'=>'u',	'û'=>'u',	'ü'=>'u',	'µ'=>'u',
		"Ž"=>"Z",	"ž"=>"z",	'Å½'=>'Z',	'Å¾'=>'z',
	  );
	  return strtr($Text, $Codes);
	}


	public function StripBOM($String) {
	    // remove BOM from begining of string
		$BOM= chr(239).chr(187).chr(191);
		return (substr($String, 0, 3) == $BOM)
		  ? substr($String, 3)
		  : $String;
	}


    protected $UTF8_UPPER_TO_LOWER = array(
			0x0041=>0x0061, 0x03A6=>0x03C6, 0x0162=>0x0163, 0x00C5=>0x00E5, 0x0042=>0x0062,
			0x0139=>0x013A, 0x00C1=>0x00E1, 0x0141=>0x0142, 0x038E=>0x03CD, 0x0100=>0x0101,
			0x0490=>0x0491, 0x0394=>0x03B4, 0x015A=>0x015B, 0x0044=>0x0064, 0x0393=>0x03B3,
			0x00D4=>0x00F4, 0x042A=>0x044A, 0x0419=>0x0439, 0x0112=>0x0113, 0x041C=>0x043C,
			0x015E=>0x015F, 0x0143=>0x0144, 0x00CE=>0x00EE, 0x040E=>0x045E, 0x042F=>0x044F,
			0x039A=>0x03BA, 0x0154=>0x0155, 0x0049=>0x0069, 0x0053=>0x0073, 0x1E1E=>0x1E1F,
			0x0134=>0x0135, 0x0427=>0x0447, 0x03A0=>0x03C0, 0x0418=>0x0438, 0x00D3=>0x00F3,
			0x0420=>0x0440, 0x0404=>0x0454, 0x0415=>0x0435, 0x0429=>0x0449, 0x014A=>0x014B,
			0x0411=>0x0431, 0x0409=>0x0459, 0x1E02=>0x1E03, 0x00D6=>0x00F6, 0x00D9=>0x00F9,
			0x004E=>0x006E, 0x0401=>0x0451, 0x03A4=>0x03C4, 0x0423=>0x0443, 0x015C=>0x015D,
			0x0403=>0x0453, 0x03A8=>0x03C8, 0x0158=>0x0159, 0x0047=>0x0067, 0x00C4=>0x00E4,
			0x0386=>0x03AC, 0x0389=>0x03AE, 0x0166=>0x0167, 0x039E=>0x03BE, 0x0164=>0x0165,
			0x0116=>0x0117, 0x0108=>0x0109, 0x0056=>0x0076, 0x00DE=>0x00FE, 0x0156=>0x0157,
			0x00DA=>0x00FA, 0x1E60=>0x1E61, 0x1E82=>0x1E83, 0x00C2=>0x00E2, 0x0118=>0x0119,
			0x0145=>0x0146, 0x0050=>0x0070, 0x0150=>0x0151, 0x042E=>0x044E, 0x0128=>0x0129,
			0x03A7=>0x03C7, 0x013D=>0x013E, 0x0422=>0x0442, 0x005A=>0x007A, 0x0428=>0x0448,
			0x03A1=>0x03C1, 0x1E80=>0x1E81, 0x016C=>0x016D, 0x00D5=>0x00F5, 0x0055=>0x0075,
			0x0176=>0x0177, 0x00DC=>0x00FC, 0x1E56=>0x1E57, 0x03A3=>0x03C3, 0x041A=>0x043A,
			0x004D=>0x006D, 0x016A=>0x016B, 0x0170=>0x0171, 0x0424=>0x0444, 0x00CC=>0x00EC,
			0x0168=>0x0169, 0x039F=>0x03BF, 0x004B=>0x006B, 0x00D2=>0x00F2, 0x00C0=>0x00E0,
			0x0414=>0x0434, 0x03A9=>0x03C9, 0x1E6A=>0x1E6B, 0x00C3=>0x00E3, 0x042D=>0x044D,
			0x0416=>0x0436, 0x01A0=>0x01A1, 0x010C=>0x010D, 0x011C=>0x011D, 0x00D0=>0x00F0,
			0x013B=>0x013C, 0x040F=>0x045F, 0x040A=>0x045A, 0x00C8=>0x00E8, 0x03A5=>0x03C5,
			0x0046=>0x0066, 0x00DD=>0x00FD, 0x0043=>0x0063, 0x021A=>0x021B, 0x00CA=>0x00EA,
			0x0399=>0x03B9, 0x0179=>0x017A, 0x00CF=>0x00EF, 0x01AF=>0x01B0, 0x0045=>0x0065,
			0x039B=>0x03BB, 0x0398=>0x03B8, 0x039C=>0x03BC, 0x040C=>0x045C, 0x041F=>0x043F,
			0x042C=>0x044C, 0x00DE=>0x00FE, 0x00D0=>0x00F0, 0x1EF2=>0x1EF3, 0x0048=>0x0068,
			0x00CB=>0x00EB, 0x0110=>0x0111, 0x0413=>0x0433, 0x012E=>0x012F, 0x00C6=>0x00E6,
			0x0058=>0x0078, 0x0160=>0x0161, 0x016E=>0x016F, 0x0391=>0x03B1, 0x0407=>0x0457,
			0x0172=>0x0173, 0x0178=>0x00FF, 0x004F=>0x006F, 0x041B=>0x043B, 0x0395=>0x03B5,
			0x0425=>0x0445, 0x0120=>0x0121, 0x017D=>0x017E, 0x017B=>0x017C, 0x0396=>0x03B6,
			0x0392=>0x03B2, 0x0388=>0x03AD, 0x1E84=>0x1E85, 0x0174=>0x0175, 0x0051=>0x0071,
			0x0417=>0x0437, 0x1E0A=>0x1E0B, 0x0147=>0x0148, 0x0104=>0x0105, 0x0408=>0x0458,
			0x014C=>0x014D, 0x00CD=>0x00ED, 0x0059=>0x0079, 0x010A=>0x010B, 0x038F=>0x03CE,
			0x0052=>0x0072, 0x0410=>0x0430, 0x0405=>0x0455, 0x0402=>0x0452, 0x0126=>0x0127,
			0x0136=>0x0137, 0x012A=>0x012B, 0x038A=>0x03AF, 0x042B=>0x044B, 0x004C=>0x006C,
			0x0397=>0x03B7, 0x0124=>0x0125, 0x0218=>0x0219, 0x00DB=>0x00FB, 0x011E=>0x011F,
			0x041E=>0x043E, 0x1E40=>0x1E41, 0x039D=>0x03BD, 0x0106=>0x0107, 0x03AB=>0x03CB,
			0x0426=>0x0446, 0x00DE=>0x00FE, 0x00C7=>0x00E7, 0x03AA=>0x03CA, 0x0421=>0x0441,
			0x0412=>0x0432, 0x010E=>0x010F, 0x00D8=>0x00F8, 0x0057=>0x0077, 0x011A=>0x011B,
			0x0054=>0x0074, 0x004A=>0x006A, 0x040B=>0x045B, 0x0406=>0x0456, 0x0102=>0x0103,
			0x039B=>0x03BB, 0x00D1=>0x00F1, 0x041D=>0x043D, 0x038C=>0x03CC, 0x00C9=>0x00E9,
			0x00D0=>0x00F0, 0x0407=>0x0457, 0x0122=>0x0123,
		);

    protected $UTF8_LOWER_TO_UPPER = array(
			0x0061=>0x0041, 0x03C6=>0x03A6, 0x0163=>0x0162, 0x00E5=>0x00C5, 0x0062=>0x0042,
			0x013A=>0x0139, 0x00E1=>0x00C1, 0x0142=>0x0141, 0x03CD=>0x038E, 0x0101=>0x0100,
			0x0491=>0x0490, 0x03B4=>0x0394, 0x015B=>0x015A, 0x0064=>0x0044, 0x03B3=>0x0393,
			0x00F4=>0x00D4, 0x044A=>0x042A, 0x0439=>0x0419, 0x0113=>0x0112, 0x043C=>0x041C,
			0x015F=>0x015E, 0x0144=>0x0143, 0x00EE=>0x00CE, 0x045E=>0x040E, 0x044F=>0x042F,
			0x03BA=>0x039A, 0x0155=>0x0154, 0x0069=>0x0049, 0x0073=>0x0053, 0x1E1F=>0x1E1E,
			0x0135=>0x0134, 0x0447=>0x0427, 0x03C0=>0x03A0, 0x0438=>0x0418, 0x00F3=>0x00D3,
			0x0440=>0x0420, 0x0454=>0x0404, 0x0435=>0x0415, 0x0449=>0x0429, 0x014B=>0x014A,
			0x0431=>0x0411, 0x0459=>0x0409, 0x1E03=>0x1E02, 0x00F6=>0x00D6, 0x00F9=>0x00D9,
			0x006E=>0x004E, 0x0451=>0x0401, 0x03C4=>0x03A4, 0x0443=>0x0423, 0x015D=>0x015C,
			0x0453=>0x0403, 0x03C8=>0x03A8, 0x0159=>0x0158, 0x0067=>0x0047, 0x00E4=>0x00C4,
			0x03AC=>0x0386, 0x03AE=>0x0389, 0x0167=>0x0166, 0x03BE=>0x039E, 0x0165=>0x0164,
			0x0117=>0x0116, 0x0109=>0x0108, 0x0076=>0x0056, 0x00FE=>0x00DE, 0x0157=>0x0156,
			0x00FA=>0x00DA, 0x1E61=>0x1E60, 0x1E83=>0x1E82, 0x00E2=>0x00C2, 0x0119=>0x0118,
			0x0146=>0x0145, 0x0070=>0x0050, 0x0151=>0x0150, 0x044E=>0x042E, 0x0129=>0x0128,
			0x03C7=>0x03A7, 0x013E=>0x013D, 0x0442=>0x0422, 0x007A=>0x005A, 0x0448=>0x0428,
			0x03C1=>0x03A1, 0x1E81=>0x1E80, 0x016D=>0x016C, 0x00F5=>0x00D5, 0x0075=>0x0055,
			0x0177=>0x0176, 0x00FC=>0x00DC, 0x1E57=>0x1E56, 0x03C3=>0x03A3, 0x043A=>0x041A,
			0x006D=>0x004D, 0x016B=>0x016A, 0x0171=>0x0170, 0x0444=>0x0424, 0x00EC=>0x00CC,
			0x0169=>0x0168, 0x03BF=>0x039F, 0x006B=>0x004B, 0x00F2=>0x00D2, 0x00E0=>0x00C0,
			0x0434=>0x0414, 0x03C9=>0x03A9, 0x1E6B=>0x1E6A, 0x00E3=>0x00C3, 0x044D=>0x042D,
			0x0436=>0x0416, 0x01A1=>0x01A0, 0x010D=>0x010C, 0x011D=>0x011C, 0x00F0=>0x00D0,
			0x013C=>0x013B, 0x045F=>0x040F, 0x045A=>0x040A, 0x00E8=>0x00C8, 0x03C5=>0x03A5,
			0x0066=>0x0046, 0x00FD=>0x00DD, 0x0063=>0x0043, 0x021B=>0x021A, 0x00EA=>0x00CA,
			0x03B9=>0x0399, 0x017A=>0x0179, 0x00EF=>0x00CF, 0x01B0=>0x01AF, 0x0065=>0x0045,
			0x03BB=>0x039B, 0x03B8=>0x0398, 0x03BC=>0x039C, 0x045C=>0x040C, 0x043F=>0x041F,
			0x044C=>0x042C, 0x00FE=>0x00DE, 0x00F0=>0x00D0, 0x1EF3=>0x1EF2, 0x0068=>0x0048,
			0x00EB=>0x00CB, 0x0111=>0x0110, 0x0433=>0x0413, 0x012F=>0x012E, 0x00E6=>0x00C6,
			0x0078=>0x0058, 0x0161=>0x0160, 0x016F=>0x016E, 0x03B1=>0x0391, 0x0457=>0x0407,
			0x0173=>0x0172, 0x00FF=>0x0178, 0x006F=>0x004F, 0x043B=>0x041B, 0x03B5=>0x0395,
			0x0445=>0x0425, 0x0121=>0x0120, 0x017E=>0x017D, 0x017C=>0x017B, 0x03B6=>0x0396,
			0x03B2=>0x0392, 0x03AD=>0x0388, 0x1E85=>0x1E84, 0x0175=>0x0174, 0x0071=>0x0051,
			0x0437=>0x0417, 0x1E0B=>0x1E0A, 0x0148=>0x0147, 0x0105=>0x0104, 0x0458=>0x0408,
			0x014D=>0x014C, 0x00ED=>0x00CD, 0x0079=>0x0059, 0x010B=>0x010A, 0x03CE=>0x038F,
			0x0072=>0x0052, 0x0430=>0x0410, 0x0455=>0x0405, 0x0452=>0x0402, 0x0127=>0x0126,
			0x0137=>0x0136, 0x012B=>0x012A, 0x03AF=>0x038A, 0x044B=>0x042B, 0x006C=>0x004C,
			0x03B7=>0x0397, 0x0125=>0x0124, 0x0219=>0x0218, 0x00FB=>0x00DB, 0x011F=>0x011E,
			0x043E=>0x041E, 0x1E41=>0x1E40, 0x03BD=>0x039D, 0x0107=>0x0106, 0x03CB=>0x03AB,
			0x0446=>0x0426, 0x00FE=>0x00DE, 0x00E7=>0x00C7, 0x03CA=>0x03AA, 0x0441=>0x0421,
			0x0432=>0x0412, 0x010F=>0x010E, 0x00F8=>0x00D8, 0x0077=>0x0057, 0x011B=>0x011A,
			0x0074=>0x0054, 0x006A=>0x004A, 0x045B=>0x040B, 0x0456=>0x0406, 0x0103=>0x0102,
			0x03BB=>0x039B, 0x00F1=>0x00D1, 0x043D=>0x041D, 0x03CC=>0x038C, 0x00E9=>0x00C9,
			0x00F0=>0x00D0, 0x0457=>0x0407, 0x0123=>0x0122,
    );

} // end of class

