<?php namespace Accent\Session\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Array Session driver is NOT persistent storage.
 *
 * This driver can be used as null-driver or for testing purposes.
 */



class ArrayDriver extends AbstractDriver {


    // storage space
    protected $Registry= array();



    public function Read($Id, $OldId=null) {

        if (isset($this->Registry[$Id])) {
            // retrieve record
            $Record= $this->Registry[$Id];
        } else if (isset($this->Registry[$OldId])) {
            // retrieve record with alternative id
            $Record= $this->Registry[$Id];
        } else {
            // session not found
            return false;
        }
        // re-load if session is rotated
        if ($Record['RotatedTo']<>'' && isset($this->Registry[$Record['RotatedTo']])) {
            $Record= $this->Registry[$Record['RotatedTo']];
        }
        // result
        return $Record;
    }


    public function Write($Id, $OldId, $TimeCreated, $TimeRotated, $Data, $DataOnce) {

        $Record= array(
            'Id'=> $Id,
            'OldId'=> $OldId,
            'Timestamp'=> time(),
            'TimeCreated'=> $TimeCreated,
            'TimeRotated'=> $TimeRotated,
            'RotatedTo'=> '',
            'Data'=> $Data,
            'DataOnce'=> $DataOnce,
        );
        $this->Registry[$Id]= $Record;
        // update old session to point to new one
        if ($OldId<>'' && isset($this->Registry[$OldId])) {
            $this->Registry[$OldId]['RotatedTo']= $Id;
        }
//echo '<br>'.var_dump($this->Storage).'<br>';
    }


    public function Delete($Id) {

        unset($this->Registry[$Id]);
    }


    public function GarbageCollection() {

        // nothing to do
    }

}

?>