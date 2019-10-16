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
 * Content of files are valid JSON structures.
 *
 * JSON encoding can successfully deal with all types of variables,
 * there is no need for any checking and failsafe-translation.
 */



class JsonDriver extends FileDriver {


    // predefined options
    protected static $DefaultOptions= array(
        'Ext' => '.json',  // file extension of file
    );

    // JSON format supports arrays
    protected $CapabilityMultiValue= true;


    protected function UnpackEntry($Dump) {

        return !$Dump
           ? $Dump
           : $this->GetService('ArrayUtils')->JsonToArray($Dump);
    }

    protected function PackEntry($Entry) {

        return !$Entry
            ? $Entry
            : $this->GetService('ArrayUtils')->ArrayToJSON($Entry);
    }


    protected function UnpackCompactStruct($Dump) {

        return !$Dump
            ? $Dump
            : $this->GetService('ArrayUtils')->JsonToArray($Dump);
    }


    protected function PackCompactStruct($Struct) {

        return !$Struct
            ? $Struct
            : $this->GetService('ArrayUtils')->ArrayToJson($Struct);
    }

}

?>