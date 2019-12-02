<?php namespace Accent\Localization\Loader;

/**
 * Part of the AccentPHP project.
 *
 * Localization loader class
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

use \Accent\AccentCore\Component;


class BaseLoader extends Component {

    // default constructor options
    protected static $DefaultOptions= array(

        // list of full paths where to search for books
        'Directories'=> array(),

        // list of full paths to book files
        'Files'=> array(),

        // extension of resource file names, null for automatic
        'FileExtension'=> null,

        // are translations grouped by language
        'AllLanguages'=> false,

        // are translations grouped by book
        'AllBooks'=> false,
    );

    protected $FileExtension= '.php';

    // internal properties
    protected $Directories;


    /**
     * Constructor.
     */
    function __construct($Options) {

        // call ancestor
        parent::__construct($Options);

        // export option and strip out trailing slashes
        $this->Directories= (array)$this->GetOption('Directories');
        foreach($this->Directories as &$Dir) {
            $Dir= rtrim($Dir,'/\\');
        }
        // override file extension
        if ($this->GetOption('FileExtension', null) !== null) {
            $this->FileExtension= '.'.trim($this->GetOption('FileExtension'),' .');
        }
    }


    protected function GetPaths($Lang, $Book) {

        $Wildcards= array(
            '@Lang'=> $Lang,
            '@Book'=> $Book,
            '@Dir' => rtrim($this->GetOption('LangFilesRootDir'),'/'),
        );
        $Paths= array();                    // get list of files to load
        foreach((array)$this->GetOption('Files') as $File) {
            $Paths[]= str_replace(array_keys($Wildcards), array_values($Wildcards), $File);
        }
        foreach($this->Directories as $Dir) {     // add files from dirs
            $File= strpos($Dir,'@Lang') === false && strpos($Dir,'@Book') === false
                ? "$Dir/$Lang/$Book".$this->FileExtension
                : $Dir.$this->FileExtension;
            $Paths[]= str_replace(array_keys($Wildcards), array_values($Wildcards), $File);
        }
        return $Paths;
    }


    public function Load($Lang, $Book) {

        $Paths= $this->GetPaths($Lang, $Book);
        $Table= array();
        foreach($Paths as $Path) {
            if (!is_file($Path)) {
                continue;   // file not found, continue silently
            }
            $Array= $this->LoadFile($Path, $Lang, $Book);
            if ($Array===false) {
                continue;   // something went wrong
            }
            // merge with other translations
            $Array= $this->RecombineTable($Array, $Lang, $Book);
            $Table= $this->MergeArrays(array($Table, $Array));
        }
        return $Table;
    }


    protected function LoadFile($Path, $Lang=null, $Book=null) {

        // load $Path file
        $Dump= file_get_contents($Path);
        // use standard service ArrayUtils
        $Array= $this->GetService('ArrayUtils')->DecodeJSON($Dump);
        return $Array;
    }


    protected function RecombineTable($Return, $Lang, $Book) {

        $AllBooks= $this->GetOption('AllBooks', false);
        $AllLanguages= $this->GetOption('AllLanguages', false);

        // if items are not grouped in any way - group them within this book and lang
        if (!$AllBooks && !$AllLanguages) {
            //$Return= array($Book=>$Return);
            return array($Lang=>array($Book=>$Return));
        }
        // if items are not grouped by languages - group them within this language
        if (!$AllLanguages) {
            return array($Lang=>$Return);
        }
        // if items are not grouped by books - inject book segmentation
        if (!$AllBooks) {
            $Tmp= array();
            foreach($Return as $k=>$v) {
                $Tmp[$k]= array($Book=>$v);
            }
            return $Tmp;
        }
        // source has both grouping levels, return it unmodifed
        return $Return;
    }


}

?>