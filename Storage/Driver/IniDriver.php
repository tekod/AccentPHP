<?php namespace Accent\Storage\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Driver for storing data in file using classic INI file format.
 */


class IniDriver extends FileDriver {


    // predefined options
    protected static $DefaultOptions= array(
        'Ext' => '.ini',  // file extension of file
    );

    // JSON format supports arrays
    protected $CapabilityMultiValue= true;


    public function __construct($Options) {

        // call parent
        parent::__construct($Options);
        // INI file format limitation
        if ($this->IsCompactMode && $this->UseMeta) {
            $this->FatalError('AccentCore/Storage: Ini driver cannot store so deep arrays.');
            $this->Initied= false;
        }
    }


    protected function UnpackEntry($Dump) {

        // return entry
        return $this->UseMeta
            ? $this->GetService('ArrayUtils')->IniToArray($Dump)
            : $Dump;
    }

    protected function PackEntry($Entry) {

        // prepare payload
        return $this->UseMeta
            ? $this->GetService('ArrayUtils')->ArrayToIni($Entry)."\r\n"
            : (string)$Entry;
    }


    protected function UnpackCompactStruct($Dump) {

        $Struct= $this->GetService('ArrayUtils')->IniToArray($Dump);
        // for compact mode only "keys" section is actualy stored
        return $this->UseMeta
            ? array('Keys'=>$Struct)
            : $Struct;
    }


    protected function PackCompactStruct($Struct) {

        // for compact mode only "keys" section is actualy stored
        $Array= $this->UseMeta ? $Struct['Keys'] : $Struct;
        return $this->GetService('ArrayUtils')->ArrayToIni($Array)."\r\n";
    }



}

?>