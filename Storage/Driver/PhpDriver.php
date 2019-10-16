<?php namespace Accent\Storage\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Driver for storing data in file as PHP array instruction.
 * Content of files are valid PHP array structure.
 */


class PhpDriver extends FileDriver {


    // predefined options
    protected static $DefaultOptions= array(
        'Ext' => '.php',  // file extension of file
    );

    // PHP format supports arrays
    protected $CapabilityMultiValue= true;


    /*
     * Override loading file to implement "include" mechanisam.
     */
    protected function LoadFile($FilePath) {

        // load file
        $Entry= @include $FilePath;
        // return entry
        return $Entry;
    }


    /*
     * Because LoadFile already returned array type there is nothing to unpacking.
     */
    protected function UnpackEntry($Dump) {

        return $Dump;
    }


    /*
     * Prepare valid content of PHP file.
     */
    protected function PackEntry($Entry) {

        // performing shallow beautifuling by manualy formatting root or array
        // DO NOT STORE PHP OBJECTS becouse var_export has problem with circular references
        if ($this->UseMeta) {
            return "<?php return array("
              ."\n'Timestamp'=> '$Entry[Timestamp]',"
              ."\n'Tags'=> ".var_export($Entry['Tags'], true).","
              ."\n'Value'=> ".var_export($Entry['Value'], true).",\n); ?>";
        } else {
            return "<?php return ".var_export($Entry, true)."; ?>";
        }
    }


    /*
     * Because LoadFile already returned array type there is nothing to unpacking.
     */
    protected function UnpackCompactStruct($Dump) {

        return $Dump;
    }


    /*
     * Prepare valid PHP file.
     */
    protected function PackCompactStruct($Struct) {

        return "<?php return ".var_export($Struct, true)."; ?>";
    }

}

?>