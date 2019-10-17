<?php namespace Accent\Session\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * File session driver stores data in separate files on hosting disk space.
 *
 * This driver should be default choice for most applications.
 *
 * File-locking feature ensures that concurent threads will not overwrite session data during parallel execution
 * (race condition). PHP's built-in session management works in same way.
 * However it can be time-expensive because concurent scripts must wait until lock owner finnish execution.
 * Fortunately locking is on per-user level so it will typicaly affect pages with many ajax calls.
 * Solution for pages with many ajax calls is to call StoreSession method as soon as possible - it will flush
 * all data into files and release locks for concurent scripts. When next Get() method occurs it will lock file again.
 * Method StoreSession will certainly be called on service shutdown.
 *
 * Service "File" must be specified in parent's $DefaultOptions configuration.
 */



class FileDriver extends AbstractDriver {


    protected static $DefaultOptions= array(
        'Dir'       => __DIR__, // storage directory, must not be shared with other services
                                // must be full path or "resolvable"
        'Spread'    => 2,       // how many sub-dirs to create [0..8]
        'Ext'       => '.php',  // file extension
        'Services'  => array(
            'File' => 'File',  // 'File' service
        ),
    );

    // internal properties

    // directory for storage
    protected $Dir;


    /**
     * Constructor.
     */
    public function __construct($Options) {

        // call parent
        parent::__construct($Options);

        // prepare storage
        $this->Dir= rtrim($this->ResolvePath($this->GetOption('Dir')), '/');
    }


    public function Read($Id, $OldId=null) {

        // get full filename
        $FilePath= $this->GetFileName($Id);
        if (!is_file($FilePath)) {
            // try with old id
            $FilePath2= $this->GetFileName($OldId);
            if ($OldId && is_file($FilePath)) {
                $FilePath= $FilePath2;
            } else {
                $this->TraceInfo('Session/FileDriver/Read: file not found: '.$FilePath);
                return false;
            }
        }
        // load file, lock aware
        $Record= $this->LoadFile($FilePath);
        if (!is_array($Record)) {
            return false;
        }
        // re-load if session is rotated
        if ($Record['RotatedTo'] <> '') {
            $RotFilePath= $this->GetFileName($Record['RotatedTo']);
            if (is_file($RotFilePath)) {
                $RotRecord= $this->LoadFile($RotFilePath);
                if (is_array($RotRecord) && isset($RotRecord['Id'])) {
                    $Record= $RotRecord;
                }
            } else {
                // wierd, ok, continue with old record
            }
        }
        //d($Record, 'Read: loaded record');
        // return it
        return $Record;
    }


    protected function LoadFile($Path) {

        $Dump= file_get_contents($Path);

        $Record= is_string($Dump)
            ? @json_decode($Dump, true)
            : false;

        return is_array($Record)
            ? $Record
            : false;
    }


    public function Write($Id, $OldId, $TimeCreated, $TimeRotated, $Data, $DataOnce) {

        // save data
        $this->WriteSingle($Id, $OldId, time(), $TimeCreated, $TimeRotated, '', $Data, $DataOnce, 1);

        // update old session to point to new one
        if ($OldId !== '') {
            $Old= $this->GetService('File')->LoadFile($this->GetFileName($OldId), 'i');
            if ($Old) {
                $this->WriteSingle($OldId, $Old['OldId'], $Old['Timestamp'], $Old['TimeCreated'], $Old['TimeRotated'], $Id, array(), array(), 2);
            }
        }
    }


    protected function WriteSingle($Id, $OldId, $Timestamp, $TimeCreated, $TimeRotated, $RotatedTo, $Data, $DataOnce, $LockN) {

        $FilePath= $this->GetFileName($Id);

        $Dump= json_encode(array(
            'Id'=> $Id,
            'OldId'=> $OldId,
            'Timestamp'=> $Timestamp,
            'TimeCreated'=> $TimeCreated,
            'TimeRotated'=> $TimeRotated,
            'RotatedTo'=> $RotatedTo,
            'Data'=> $Data,
            'DataOnce'=> $DataOnce,
        ), JSON_UNESCAPED_UNICODE);

        // save in file, lock aware
        $Dir= dirname($FilePath);
        if (!is_dir($Dir)) {
            mkdir($Dir, 0777, true);
        }
        file_put_contents($FilePath, $Dump);
    }



    public function Delete($Id) {

        $FilePath= $this->GetFileName($Id);
    d($FilePath, 'delete');
        @unlink($FilePath);  // silently
        // try to remove directory if empty
        if (dirname($FilePath) <> $this->Dir) { // but dont remove root dir
            @rmdir(dirname($FilePath));     // silently
        }
    }


    public function GarbageCollection() {

        $Expire= time() - $this->GetOption('Cookie.Expire') +1;
        // get dir list
		$Files= $this->GetService('File')->ReadDirectoryRecursive($this->Dir);
		if (!is_array($Files)) {
            return;
        }
		foreach($Files as $File) {
			$Path= "$this->Dir/$File";
            if (!is_readable($Path)) {
                continue;
            }
            if (filemtime($Path) <= $Expire) {
                @unlink($Path);
                if (strpos($Path,'/') !== false) {
                    @rmdir(dirname($Path)); // try to remove directory if empty
                }
            }
		}
    }


    public function Close() {

//        // release file locks
//        foreach($this->FileLocks as $Key=>$HND) {
//            $this->GetService('File')->UnlockFile($HND);
//            unset($this->FileLocks[$Key]);
//        }
    }


    private function GetFileName($Key)	{
        // calculate FileName for this Key
        $Dir= $this->Dir;
        for($i=0, $imax=$this->GetOption('Spread'); $i<$imax; ++$i) {
            if (($prefix= substr($Key,$i<<1,2)) !== false) {
                $Dir .= "/$prefix";
            }
        }
        return "$Dir/$Key".$this->GetOption('Ext');
    }

}

?>