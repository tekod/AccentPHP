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

use \Accent\Localization\Loader\BaseLoader;


class YamlLoader extends BaseLoader {


    protected $FileExtension= '.yml';


    protected function LoadFile($Path, $Lang=null, $Book=null) {


        $Dump= file_get_contents($Path);
        // use standard service ArrayUtils
        return (array)$this->GetService('ArrayUtils')->YamlToArray($Dump);
    }



}

?>