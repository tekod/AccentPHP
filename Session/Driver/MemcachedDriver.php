<?php namespace Accent\Session\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Memcached session storage driver based on the Memcached class provided by
 * the PHP memcached extension.
 */



class MemcachedDriver extends AbstractDriver {


    protected static $DefaultOptions= array(
        'Prefix'=> 'ac_',  // namespace for keys stored in memcached service
        'Servers' => array( // array of servers and ports that run the memcached service
			array('host'=> '127.0.0.1', 'port'=> 11211, 'weight'=> 100),
		),
        'Services'  => array(
            'File' => 'File',  // 'File' service
        ),
    );

    protected $MemcachedService= null;


    public function __construct($Options) {
        // call parent
        parent::__construct($Options);
        // validate
        if (!class_exists('Memcached')) {
            $this->Initied= false;
            $this->Error("Memcached extension not available.");
            return;
        }
        // create object
		$this->MemcachedService= new \Memcached();
    	$this->MemcachedService->AddServers($this->Options['Servers']);
        // test connection
        if ($this->MemcachedService->GetVersion() === false) {
            $this->Initied= false;
            $this->Error("Memcached server not responding, check configuration.");
        }
    }


    public function Read($Id, $OldId) {

        $Record= $this->MemcachedService->Get($this->Options['Prefix'].$Id);
        if (!$Record) {
            $Record= $this->MemcachedService->Get($this->Options['Prefix'].$OldId);
        }
        // re-load if session is rotated
        if ($Record['RotatedTo']<>'') {
            $RotRecord= $this->MemcachedService->Get($this->Options['Prefix'].$Record['RotatedTo']);
            if (is_array($RotRecord) && isset($RotRecord['Id'])) {
                $Record= $RotRecord;
            }
        }
        // return it
        return $Record;
    }


    public function Write($Id, $OldId, $TimeCreated, $TimeRotated, $Data, $DataOnce) {

        // save data
        $this->WriteSingle($Id, $OldId, time(), $TimeCreated, $TimeRotated, '', $Data, $DataOnce);

        // update old session to point to new one
        if ($OldId=='') {
            $Old= $this->MemcachedService->Get($this->Options['Prefix'].$OldId);
            if ($Old) {
                $this->WriteSingle($OldId, $Old['OldId'], $Old['Timestamp'], $Old['TimeCreated'], $Old['TimeRotated'], $Id, array(), array());
            }
        }
    }


    protected function WriteSingle($Id, $OldId, $Timestamp, $TimeCreated, $TimeRotated,
            $RotatedTo, $Data, $DataOnce) {

        // generate valid PHP instruction
        $Record= array(
          'Id'=> $Id,
          'OldId'=> $OldId,
          'Timestamp'=> $Timestamp,
          'TimeCreated'=> $TimeCreated,
          'TimeRotated'=> $TimeRotated,
          'RotatedTo'=> $RotatedTo,
          'Data'=> $Data,
          'DataOnce'=> $DataOnce,
        );
        $Succ= $this->MemcachedService->Set(
            $this->Options['Prefix'].$Id,
            $Record,
            time() + $this->Options['Cookie']['Expire']
        );
        if ($Succ === false) {
            $this->Error("Memcached server writing error.");
        }
    }


    public function Delete($Id) {

        $this->MemcachedService->Delete($this->Options['Prefix'].$Id);
    }


    public function GarbageCollection() {

        // nothing to do
    }

}

?>