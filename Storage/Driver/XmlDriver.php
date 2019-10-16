<?php namespace Accent\Storage\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Driver for storing data in XML formated file.
 *
 * Note that XML format require that entity keys begins only with: [A-Z] | "_" | [a-z]
 */



class XmlDriver extends FileDriver {


    // predefined options
    protected static $DefaultOptions= array(
        'Ext' => '.xml',  // file extension of file
    );

    // XML format supports arrays
    protected $CapabilityMultiValue= true;


    public function __construct($Options) {
        // call parent
        parent::__construct($Options);
        // validate existance of extension
        if (!function_exists('simplexml_load_file')) {
            $this->Initied= false;
            $this->FatalError('SimpleXML extension not found.');
        }
    }


    protected function UnpackEntry($Dump) {

        return !$Dump
           ? $Dump
           : $this->GetService('ArrayUtils')->XmlToArray($Dump);
    }

    protected function PackEntry($Entry) {

        return !$Entry
            ? $Entry
            : $this->GetService('ArrayUtils')->ArrayToXml($Entry);
    }


    protected function UnpackCompactStruct($Dump) {

        return !$Dump
            ? $Dump
            : $this->GetService('ArrayUtils')->XmlToArray($Dump);
    }


    protected function PackCompactStruct($Struct) {

        return !$Struct
            ? $Struct
            : $this->GetService('ArrayUtils')->ArrayToXml($Struct);
    }


}

?>