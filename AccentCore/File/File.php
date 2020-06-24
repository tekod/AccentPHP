<?php namespace Accent\AccentCore\File;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 *  File service is set of functions for dealing with files.
 */


class File {

    /**
     * Add "/" at end of path, except for empty string.
     * Additionally normalize slashes ("\" -> "/").
     *
     * @param string $Path
     * @return string
     */
    public function Slashed($Path) {

        $Path= str_replace('\\', '/', $Path);
        $Path= rtrim($Path, '/');
        return ($Path) ? "$Path/" : '';
    }


    /**
     * Remove "/" from end of path.
     * Additionally normalize slashes ("\" -> "/").
     *
     * @param string $Path
     * @return string
     */
    public function Unslashed($Path) {

        $Path= str_replace('\\', '/', $Path);
        return rtrim($Path, '/');
    }


    public function IsValidFileName($FileName) {

        return ($FileName)
            ? preg_match('/^[A-Za-z0-9~_!&= \|\.\-]+$/', $FileName)
            : false;
    }


    /**
     * Insert suffix between "filename" and ".ext" parts.
     *
     * @param string $File
     * @param string $Suffix
     * @return string
     */
    public function AddSuffixToFileName($File, $Suffix) {

        $x= strrpos($File, '.');
        return $x === false
            ? $File.$Suffix
            :  substr($File, 0, $x).$Suffix.substr($File, $x);
    }


    /**
     * Convert 'verylongfilename.ext' into 'verylo...ext'.
     *
     * @param string $Name
     * @param int $Len
     * @param string $Dots
     * @return string
     */
    public function ShortedFileName($Name, $Len, $Dots='...') {

        if (strlen($Name) <= $Len) {
            return $Name;
        }
        $PI= pathinfo($Name);
        $Extension= isset($PI['extension']) ? $PI['extension'] : '';
        $Point= $Len-strlen($Dots)-strlen($Extension)-strlen($Name);
        $ShortedName= substr($Name, 0, $Point).$Dots.$Extension;

        // there is no enough space for nice shortening, forget on preserving extension and shorten whole string
        if (strlen($ShortedName) > $Len) {
            $ShortedName= substr($Name, 0, max(0, $Len-strlen($Dots))).$Dots;
        }
        return $ShortedName;
    }


    /**
     * Rename base name of file preserving extension.
     *
     * @param string $PathName
     * @param string $NewFileName
     * @return string
     */
    public function RenameButPreserveExt($PathName, $NewFileName) {

        $PI= pathinfo($PathName);
        $Ext= isset($PI['extension'])
            ? ($PI['extension'] === '' ? '.' : '.'.$PI['extension'])
            : '';
        $Dir= isset($PI['dirname']) && $PI['dirname'] <> '.'
            ? $PI['dirname'] .= '/'
            : '';
        return $Dir.$NewFileName.$Ext;
    }


    /**
     * Create temporary file and open it for writing.
     * This method will search for random but guaranties unused filename in specified dir.
     * If directory is null searching will be performed in "system temp directory".
     *
     * @param string $TempDir  full path to directory where file will be created
     * @return mixed filehandler or filepath
     */
    public function NewTempFile($TempDir=null) {

        // find unique file
        $Path= $this->NewTempFilename($TempDir);
        // return
        return fopen($Path, 'wb');
    }


    /**
     * Find guarantied unique filename in specified temp directory.
     * If directory is null searching will be performed in "system temp directory".
     *
     * @param string $TempDir  full path to directory where file will be created
     * @return string
     */
    public function NewTempFilename($TempDir=null) {

        if ($TempDir === null) {
            $TempDir= sys_get_temp_dir();
        }
        // loop
        do {
            $Path= $this->Slashed($TempDir).rand(10000000, 99999999).'.tmp';
        } while(is_file($Path));
        // result
        return $Path;
    }


    /**
     * Read file content from disk.
     *
     * @param string $FilePath
     * @return string|boolean
     */
    public function LoadFile($FilePath) {

        if (!is_readable($FilePath)) {
            return false;
        }
        return file_get_contents($FilePath);
    }


    /**
     * Save file content on disk.
     *
     * @param string $FilePath
     * @param string $Content
     * @param string $Mode  "a" for append content, "o" for overwriting existing file
     * @return bool
     */
    public function SaveFile($FilePath, $Content, $Mode='') {

        if (!$FilePath) {
            return false;
        }
        $Mode= strtolower($Mode);
        $this->MkDirRecursive(dirname($FilePath), 0755);
        $Flags= 0;
        if (strstr($Mode, 'a') !== false) {
            $Flags += FILE_APPEND;
        }
        if (strstr($Mode, 'o') === false && strstr($Mode, 'a') === false && is_file($FilePath)) {
            return false;
        }
        return file_put_contents($FilePath, $Content, $Flags) !== false;
    }


    /**
     * Copy/Move file to other location.
     * Missing directories in path will be created.
     *
     * @param string $FromPath
     * @param string $ToPath
     * @param bool $DeleteOriginal
     * @return boolean
     */
    public function CopyFile($FromPath, $ToPath, $DeleteOriginal = false) {

        if (!$FromPath || !$ToPath) {
            return false;
        }
        $this->MkDirRecursive(dirname($ToPath));
        if (!@copy($FromPath, $ToPath)) {
            return false;
        }
        @chmod($ToPath, fileperms($FromPath)); // try to set same perms, not critical
        if ($DeleteOriginal) {
            $this->DeleteFile($FromPath);  // try to delete, not critical
        }
        return true;
    }


    /**
     * Erase file.
     *
     * @param string $Path
     * @return bool
     */
    public function DeleteFile($Path) {
        return @unlink($Path);
    }


    /**
     * Create directory with creating all parent dirs.
     *
     * @param string $Dir
     * @param int $Mode
     * @return boolean
     */
    public function MkDirRecursive($Dir, $Mode=0755) {
        // using mkdir() function with 3rd parametar will produce problems with 'umask',
        // but PHP manual discourage using umask() function,
        // so we will go with step by step mkdir-chmode recursion
        $Dir= $this->Unslashed($Dir);
        if (file_exists($Dir)) {
            return true;  // dir or file with this name already exists
        }
        if ($Dir && !$this->MkDirRecursive(dirname($Dir), $Mode)) {
            return false;
        }
        $Res= @mkdir($Dir);
        if ($Res) {
            @chmod($Dir, $Mode);
        }
        return $Res;
    }


    /**
     * Deleting empty directory.
     *
     * @param string $Path
     * @return boolean
     */
    public function RmDir($Path) {
        // using "@" because rmdir raises Warning on Windows
        return @rmdir($Path);
    }



    /**
     * Deletes all files in target directory including files in subdirectories.
     *
     * @param string $Dir  full path
     * @param int $OnlyOlderThen  timestamp or zero
     * @return boolean
     */
    public function DirectoryClear($Dir, $OnlyOlderThen=0) {

        $Dir= $this->Unslashed($Dir);
        // prevent suicide
        if (strpos($this->Unslashed(__DIR__), $Dir) === 0) {
            return false;  // specified directory is parent of this script
        }
        $F= @dir($Dir);
        if ($F === false) {
            return false;
        }
        $Res= true;
        while(($Entry= $F->read()) !== false) {
            // is it directory?
            if (is_dir("$Dir/$Entry")) {
                if ($Entry === '.' || $Entry === '..') {
                    continue;
                }
                if (!$this->DirectoryClear("$Dir/$Entry", $OnlyOlderThen)) {
                    $Res= false;
                }
                if (!$this->RmDir("$Dir/$Entry")) {
                    $Res= false;
                }
                continue;
            }
            // no, it is file
            $DeleteIt= $OnlyOlderThen > 0
                ? filemtime("$Dir/$Entry") < $OnlyOlderThen
                : true;
            if ($DeleteIt  && !$this->DeleteFile("$Dir/$Entry")) {
                $Res= false;
            }
        }
        $F->close();
        return $Res;
    }


    /**
     * Copy/Move all files from directory to another location.
     * Function is recursive meaning it will affect files in subdirectories too.
     *
     * @param string $FromDir
     * @param string $ToDir
     * @param bool $DeleteOriginal
     * @return boolean
     */
    public function DirectoryCopy($FromDir, $ToDir, $DeleteOriginal=false) {

        $FromDir= $this->Unslashed($FromDir);
        $ToDir= $this->Unslashed($ToDir);
        if ($FromDir == $ToDir) {
            return true;    // same location
        }
        if (strpos("$ToDir/", "$FromDir/") === 0) {
            return false;   // copy to original's subdir? imposible!
        }
        $F= @dir($FromDir);
        if ($F === false) {
            return false;   // source dir probably not exist
        }
        $this->MkDirRecursive($ToDir, fileperms($FromDir) & 0777);
        $Res= true;
        while(($Entry= $F->read()) !== false) {
            if ($Entry === '.' || $Entry === '..') {
                continue;
            }
            if (is_dir("$FromDir/$Entry")) { // this is dir
                if (!$this->DirectoryCopy("$FromDir/$Entry", "$ToDir/$Entry", $DeleteOriginal)) {
                    $Res = false;
                }
            } else { // this is file
                if (!$this->CopyFile("$FromDir/$Entry", "$ToDir/$Entry", $DeleteOriginal)) {
                    $Res = false;
                }
            }
        }
        $F->close();
        if ($DeleteOriginal) {
            $this->RmDir($FromDir);
        }
        return $Res;
    }


    /**
     * Set new $Mode to all files in directory including files in subdirectories.
     *
     * @param string $DirPath
     * @param int $ModeForFiles
     * @param int $ModeForDirs leave null to avoid change mode of dirs
     * @return boolean
     */
    public function ChModAllFiles($DirPath, $ModeForFiles, $ModeForDirs=null) {

        $DirPath= $this->Unslashed($DirPath);
        if (is_file($DirPath)) {
            return @chmod($DirPath, $ModeForFiles); // this is file
        }
        $dh= @opendir($DirPath);
        if (!$dh) {
            return false;
        }
        while(($File= readdir($dh)) !== false) {
            if ($File === '.' || $File === '..') {
                continue;
            }
            $Path= "$DirPath/$File";
            if (is_dir($Path)) {
                // it is subdirectory
                if ($ModeForDirs !== null) {
                    @chmod($Path, $ModeForDirs);
                }
                $Res= $this->ChModAllFiles($Path, $ModeForFiles, $ModeForDirs); // recursion
            } elseif (!is_link($Path)) {
                // it is file
                $Res= @chmod($Path, $ModeForFiles);
            } else {
                // ignore
                $Res= true;
            }
            if (!$Res) {
                // if error occured
                closedir($dh);
                return false;
            }
        }
        @closedir($dh);
        return true;
    }


    /**
     * Check does target directory contains files.
     *
     * @param string $DirPath
     * @return boolean
     */
    public function IsDirectoryEmpty($DirPath) {

        $Hnd= @dir($DirPath);
        if (!$Hnd) {
            return false;
        }
        while(($Entry= $Hnd->read()) !== false) {
            if ($Entry === '.' || $Entry === '..') {
                continue;
            }
            return false;
        }
        return true;
    }


    /**
     * Simply create list of files in target directory.
     * Options is array of following keys:
     *   - 'Mask'=> '*',            // searching mask, like "*.jpg"
     *   - 'AllowFirstDot'=> true,  // this can hide files starting with dot
     *   - 'AllowDirs'=> false,     // allow directories to be listed in result
     *   - 'AllowFiles'=> true,     // allow files to be listed in result
     *   - 'ModifiedOn'=> 0,        // positive integer: list only items with that timestamp and newer
     *                              // negative integer: list only items with that timestamp and older
     *                              // zero (0) to list all items
     *
     * @param string $Dir
     * @param array $Options
     * @return array|boolean
     */
    public function ReadDirectory($Dir, $Options=[]) {

        $Dir= $this->Unslashed($Dir);
        if (!is_dir($Dir)) {
            return false;
        }
        // avoid using "glob()" because of huge memory consumption
        $F= @dir($Dir);
        if ($F === false) {
            return false;
        }
        $Files= array();
        do {
            $Entry= $F->read();
            if ($Entry === false) {
                break;
            }
            if ($this->IsValidReadDirectoryEntry($Entry, $Dir, $Options)) {
                $Files[]= $Entry;
            }
        } while (true);
        $F->close();
        natcasesort($Files);
        return $Files;
    }


    /**
     * Create list of files from directory including files in subdirectories.
     * Target directory will be calculated as $Dir.'/'.$SubPath
     * and $SubPath will be added as prefix to result items.
     *
     * @param string $Dir
	 * @param array $Options
     * @param string $SubPath
     * @return array|boolean
     */
    public function ReadDirectoryRecursive($Dir, $Options=array(), $SubPath= '') {

        $Dir = $this->Unslashed($Dir);
        $SubPath = $this->Unslashed($SubPath);
        $CondSlash = ($SubPath) ? '/' : '';
        $FullPath = $Dir.$CondSlash.$SubPath;
        $F = @dir($FullPath);
        if ($F === false) {
            return false;
        }
        $Files= array();
        do {
            $Entry= $F->read();
            if ($Entry === false) {
                break;
            }
            if ($this->IsValidReadDirectoryEntry($Entry, $FullPath, $Options)) {
                $Files[]= $SubPath.$CondSlash.$Entry;
            }
            if ($Entry !== '.' && $Entry !== '..' && is_dir("$FullPath/$Entry")) {
                $SubList= $this->ReadDirectoryRecursive($Dir, $Options, $SubPath.$CondSlash.$Entry);
                $Files= array_merge($Files, $SubList); // add list from recursion
            }
        } while (true);
        $F->close();
        natcasesort($Files);
        return $Files;
    }


    // internal method for ReadDirectoryRecursive().
    protected function IsValidReadDirectoryEntry($Name, $Dir, $Options) {
        // apply default options
        $Options += array(
            'Mask'=> '*',
            'AllowFirstDot'=> true,
            'AllowDirs'=> false,
            'AllowFiles'=> true,
            'ModifiedOn'=> 0,
        );
        $IsDir= is_dir("$Dir/$Name");
        // step by step, find a reason to reject current entry
        if ($Name === '.' || $Name === '..') {
            return false;
        }
        if (!$Options['AllowFirstDot'] && $Name[0] === '.') {
            return false;
        }
        if (!$Options['AllowDirs'] && $IsDir) {
            return false;
        }
        if (!$Options['AllowFiles'] && !$IsDir) {
            return false;
        }
        if ($Options['ModifiedOn'] > 0 && filemtime("$Dir/$Name") < $Options['ModifiedOn']) {
            return false;
        }
        if ($Options['ModifiedOn'] < 0 && filemtime("$Dir/$Name") > -$Options['ModifiedOn']) {
            return false;
        }
        // checking mask will be performed only on entries allowed to be in result
        if ($Options['Mask'] !== '*' && !$this->TestFileNameMask($Name, $Options['Mask'])) {
            return false;
        }
        return true;
    }


    // internal method for ReadDirectoryRecursive().
    protected function TestFileNameMask($FileName, $Mask) {
        // convert filesystem wildcards into RegEx wildcards
        $RegEx= str_replace(array("\*","\?"), array('.*','.'), preg_quote($Mask));
        // perform RegEx test
        return preg_match('/^'.$RegEx.'$/is', $FileName);
    }


    /**
     * Recursively calculate sum of sizes of all files in $Dir.
     *
     * @param string $Dir
     * @return int
     */
    public function DirectorySize($Dir) {

        $DirStream= @opendir($Dir);
        if (!$DirStream) {
            return false;
        }
        $Size= 0;
        while(false !== ($FileName= readdir($DirStream))) {
            if (($FileName != ".") && ($FileName != "..")) {
                $FullPath = $this->Slashed($Dir).$FileName;
                if (is_file($FullPath)) {
                    $Size += filesize($FullPath);
                }
                if (is_dir($FullPath)) {
                    $Size += $this->DirectorySize($FullPath);
                }
            }
        }
        closedir($DirStream);
        return $Size;
    }


    /**
     * Returning $Path without "//", "/./" and "/../" parts.
     * Scheme part (http://) will be preserved and slashes will be normalized.
     *
     * @param string $Path
     * @return string
     */
    public function ResolveRelativePath($Path) {

        $Parts= explode('://', $Path, 2);
        $Scheme= count($Parts) === 2 ? "$Parts[0]://" : '';
        $Path= str_replace('\\', '/', end($Parts));
        $Path= str_replace('/./', '/', $Path);
        $Count= 0;
        do {
            $Path = preg_replace(array('~//~', '~/(?!\.\.)[^/]+/\.\./~'), '/', $Path, -1, $Count);
        } while($Count > 0);
        return $Scheme.$Path;
    }


    /**
     * Calculates relative path to target file $Path, relative to positition of $RelativeTo.
     * Slashes wil be normalized.
     *
     * @param string $Path
     * @param string $RelativeTo
     * @return string
     */
    public function CalcRelativePath($Path, $RelativeTo) {

        $Path= explode('/', $this->Unslashed($Path));
        $RelativeTo= explode('/', $this->Unslashed($RelativeTo));
        $Remaining= count($RelativeTo);
        $RelPath= $Path;
        foreach($RelativeTo as $Depth => $Dir) {
            if (isset($Path[$Depth]) && $Dir === $Path[$Depth]) {
                array_shift($RelPath); // ignore this directory
            } else {
                if ($Remaining > 0) {  // some back-steps required
                    $PadNum= count($RelPath) + $Remaining;
                    $RelPath= array_pad($RelPath, -$PadNum, '..');
                    return implode('/', $RelPath);
                }
            }
            $Remaining--;
        }
        return implode('/', $RelPath);
    }


    /**
     * Convert 10240 into "10 k", and 10000 into "9,8 k" - usefull for presentation.
     * Result is rounded to closest value.
     * Note that there is space character between number and multiplier.
     * Feature: for very short numbers it will add extra decimal to decrease rounding error.
     *
     * @param int $n
     * @param int $Decimals
     * @param string $DecSep
     * @return string
     */
    public function GMK_NiceValue($n, $Decimals=0, $DecSep='.') {

        if ($n === 0) {
            return '0';
        }
        $Sufix= array('', ' k', ' M', ' G', ' T');
        $Loop= 0;
        while(($n / 1024) >= 1 && $Loop < 5) {
            $Loop++;
            $n= $n / 1024;
        }
        if ($n < 10 && $Decimals === 0) {
            $Decimals++;        // feature
        }
        $Res= number_format($n, $Decimals, $DecSep, '');
        $Res= preg_replace('~(\\'.$DecSep.'0+)$~', '', $Res);   // remove trailing zeroes
        return $Res.$Sufix[$Loop];
    }


    /**
     * Convert 10240 into 10k, and 10000 into 9k - usefull for ini_set().
     * Result is truncated to lower value.
     *
     * @param int $n
     * @return string
     */
    public function GMK_Packed($n) {
        if ($n == 0) {
            return '0';
        }
        $Sufix = array('', 'k', 'M', 'G', 'T');
        $loop = 0;
        while((($n / 1024) >= 1) and ($loop < 5)) {
            $loop++;
            $n = $n / 1024;
        }
        return intval($n).$Sufix[$loop];
    }


    /**
     * Convert '2M' into 2*1024*1024.
     *
     * @param string $Value  input value as string
     * @param bool $HexBase  use hexadecimal or decimal meaning of 'k','M',...
     * @return int
     */
    public function GMK_Integer($Value, $HexBase=true) {

        if (!$Value) {  // for null and empty string
            return 0;
        }
        $Value = str_replace(' ', '', $Value);
        if (strtoupper(substr($Value, -1)) == 'B') {
            $Value = substr($Value, 0, -1);
        }
        // find multiplier
        $Multiplier = strpos(' KMGT', strtoupper(substr($Value, -1))); // space char is safe here
        // apply powered multiplier
        return intval(floatval($Value) * pow($HexBase ? 1024 : 1000, $Multiplier));
    }


    /**
     * Returns uploading filesize limit, in bytes.
     *
     * @return integer
     */
    public function GetMaxUpload() {

        $Num= min(
            ini_get('upload_max_filesize'),
            ini_get('post_max_size')
        );
        return $this->GMK_Integer($Num);
    }


    /**
     * Return mime type based of filename extension.
     *
     * @param type $FileName
     * @return string
     */
    public function MimeTypeByExt($FileName) {
        $CommonMimeTypes = array(
            "exe"=> "application/vnd.microsoft.portable-executable",
            "odt"=> "application/vnd.oasis.opendocument.text",
            "ods"=> "application/vnd.oasis.opendocument.spreadsheet",
            "odp"=> "application/vnd.oasis.opendocument.presentation",
            "so"=>"application/octet-stream",     "dll"=>"application/octet-stream",
            "oda"=>"application/oda",             "hqx"=>"application/mac-binhex40",
            "cpt"=>"application/mac-compactpro",  "doc"=>"application/msword",
            "bin"=>"application/octet-stream",    "dms"=>"application/octet-stream",
            "lha"=>"application/octet-stream",    "lzh"=>"application/octet-stream",
            "pdf"=>"application/pdf",             "ai"=>"application/postscript",
            "eps"=>"application/postscript",      "ps"=>"application/postscript",
            "smi"=>"application/smil",            "smil"=>"application/smil",
            "bcpio"=>"application/x-bcpio",       "wbxml"=>"application/vnd.wap.wbxml",
            "wmlc"=>"application/vnd.wap.wmlc",   "wmlsc"=>"application/vnd.wap.wmlscriptc",
            "vcd"=>"application/x-cdlink",        "pgn"=>"application/x-chess-pgn",
            "cpio"=>"application/x-cpio",         "csh"=>"application/x-csh",
            "dcr"=>"application/x-director",      "dir"=>"application/x-director",
            "dxr"=>"application/x-director",      "dvi"=>"application/x-dvi",
            "spl"=>"application/x-futuresplash",  "gtar"=>"application/x-gtar",
            "hdf"=>"application/x-hdf",           "skp"=>"application/x-koan",
            "skd"=>"application/x-koan",          "js"=>"application/x-javascript",
            "skt"=>"application/x-koan",          "skm"=>"application/x-koan",
            "latex"=>"application/x-latex",       "nc"=>"application/x-netcdf",
            "cdf"=>"application/x-netcdf",        "sh"=>"application/x-sh",
            "shar"=>"application/x-shar",         "swf"=>"application/x-shockwave-flash",
            "sit"=>"application/x-stuffit",       "sv4cpio"=>"application/x-sv4cpio",
            "sv4crc"=>"application/x-sv4crc",     "tar"=>"application/x-tar",
            "tcl"=>"application/x-tcl",           "tex"=>"application/x-tex",
            "t"=>"application/x-troff",           "tr"=>"application/x-troff",
            "roff"=>"application/x-troff",        "man"=>"application/x-troff-man",
            "me"=>"application/x-troff-me",       "ms"=>"application/x-troff-ms",
            "ustar"=>"application/x-ustar",       "src"=>"application/x-wais-source",
            "xhtml"=>"application/xhtml+xml",     "xht"=>"application/xhtml+xml",
            "zip"=>"application/zip",             "au"=>"audio/basic",
            "snd"=>"audio/basic",                 "mid"=>"audio/midi",
            "midi"=>"audio/midi",                 "kar"=>"audio/midi",
            "mpga"=>"audio/mpeg",                 "mp2"=>"audio/mpeg",
            "mp3"=>"audio/mpeg",                  "aif"=>"audio/x-aiff",
            "aiff"=>"audio/x-aiff",               "aifc"=>"audio/x-aiff",
            "m3u"=>"audio/x-mpegurl",             "ram"=>"audio/x-pn-realaudio",
            "rm"=>"audio/x-pn-realaudio",         "rpm"=>"audio/x-pn-realaudio-plugin",
            "ra"=>"audio/x-realaudio",            "wav"=>"audio/x-wav",
            "pdb"=>"chemical/x-pdb",              "xyz"=>"chemical/x-xyz",
            "bmp"=>"image/bmp",                   "gif"=>"image/gif",
            "ief"=>"image/ief",                   "jpeg"=>"image/jpeg",
            "jpg"=>"image/jpeg",                  "jpe"=>"image/jpeg",
            "png"=>"image/png",                   "tiff"=>"image/tiff",
            "tif"=>"image/tif",                   "djvu"=>"image/vnd.djvu",
            "djv"=>"image/vnd.djvu",              "wbmp"=>"image/vnd.wap.wbmp",
            "ras"=>"image/x-cmu-raster",          "pnm"=>"image/x-portable-anymap",
            "pbm"=>"image/x-portable-bitmap",     "pgm"=>"image/x-portable-graymap",
            "ppm"=>"image/x-portable-pixmap",     "rgb"=>"image/x-rgb",
            "xbm"=>"image/x-xbitmap",             "xpm"=>"image/x-xpixmap",
            "xwd"=>"image/x-windowdump",          "igs"=>"model/iges",
            "iges"=>"model/iges",                 "msh"=>"model/mesh",
            "mesh"=>"model/mesh",                 "silo"=>"model/mesh",
            "wrl"=>"model/vrml",                  "vrml"=>"model/vrml",
            "mpeg"=>"video/mpeg",                 "mpg"=>"video/mpeg",
            "mpe"=>"video/mpeg",                  "qt"=>"video/quicktime",
            "mov"=>"video/quicktime",             "mxu"=>"video/vnd.mpegurl",
            "avi"=>"video/x-msvideo",             "movie"=>"video/x-sgi-movie",
            "css"=>"text/css",                    "asc"=>"text/plain",
            "txt"=>"text/plain",                  "rtx"=>"text/richtext",
            "rtf"=>"text/rtf",                    "sgml"=>"text/sgml",
            "sgm"=>"text/sgml",                   "tsv"=>"text/tab-seperated-values",
            "wml"=>"text/vnd.wap.wml",            "wmls"=>"text/vnd.wap.wmlscript",
            "etx"=>"text/x-setext",               "xml"=>"text/xml",
            "xsl"=>"text/xml",                    "htm"=>"text/html",
            "html"=>"text/html",                  "shtml"=>"text/html");
        $Ext = substr($FileName, strrpos($FileName, '.')+1);
        return (isset($CommonMimeTypes[$Ext])) ? $CommonMimeTypes[$Ext] : 'application/octet-stream';
    }

}

