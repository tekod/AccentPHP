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


class PhpLoader extends BaseLoader {


    protected $FileExtension= '.php';


    protected function LoadFile($Path, $Lang=null, $Book=null) {

        $Array= include $Path;
        return $Array;
    }

}

?>