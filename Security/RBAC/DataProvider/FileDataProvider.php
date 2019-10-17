<?php namespace Accent\Security\RBAC\DataProvider;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * File-based RBAC data provider.
 */


use \Accent\Security\RBAC\DataProvider\ArrayDataProvider;


class FileDataProvider extends ArrayDataProvider {


    protected static $DefaultOptions= array(
        'FilePath'=> '@AppDir/conf/rbac.php',
    );


    public function __construct($Options) {

        parent::__construct($Options);

        // load array in $this->Data buffer
        $Path= $this->GetOption('FilePath');
        $this->Data= include $this->ResolvePath($Path);
    }

}

?>