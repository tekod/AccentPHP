<?php namespace Accent\Localization\Loader;

/**
 * Part of the AccentPHP project.
 *
 * Localization package brings:
 *  - translation service
 *  - localized number presentation service
 *  - handling "multilanguage strings" service
 *  - routine for detecting user's language from browser user-agent
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


use \Accent\Localization\Loader\BaseLoader;


class IniLoader extends BaseLoader {


    protected $FileExtension= '.ini';


    protected function LoadFile($Path, $Lang=null, $Book=null) {

        $Dump= file_get_contents($Path);
        //return parse_ini_string($Dump, true);
        // use standard service ArrayUtils
        return (array)$this->GetService('ArrayUtils')->IniToArray($Dump);
    }

}

?>