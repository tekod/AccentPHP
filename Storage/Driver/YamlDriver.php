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
 */



class YamlDriver extends FileDriver {


    // predefined options
    protected static $DefaultOptions= array(
        'Ext' => '.yaml',  // file extension of file
    );

    // YAML format supports arrays
    protected $CapabilityMultiValue= true;


    protected function UnpackEntry($Dump) {

        if (!$Dump) {
            // probably false (file not found), anyway return false
            return false;
        }
        // check is loaded dump represent valid YAML content
        // and return unmodified content if not
        return $this->GetService('ArrayUtils')->YamlToArray($Dump);
    }

    protected function PackEntry($Entry) {

        if (!$Entry) {
            // invalid value, probably false (entry not found), anyway return false
            return false;
        }
        if (!is_array($Entry)) {
            // YAML cannot store simple string (or any scalar) as-is,
            // it must be mapped somehow into array
            $this->Error('Storage/YAML: YAML format can store only arrays.');
            return false;
        }
        return $this->GetService('ArrayUtils')->ArrayToYaml($Entry);
    }


    protected function UnpackCompactStruct($Dump) {

        return !$Dump
            ? $Dump
            : $this->GetService('ArrayUtils')->YamlToArray($Dump);
    }


    protected function PackCompactStruct($Struct) {

        return !$Struct
            ? $Struct
            : $this->GetService('ArrayUtils')->ArrayToYaml($Struct);
    }


}

?>