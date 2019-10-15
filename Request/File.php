<?php namespace Accent\Request;

/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Accent\Request\File object represents uploaded file received with HTTP request.
 * It encapsulates common tasks for handling uploading.
 *
 * Usage:
 * | $Request= $this->GetService('Request');
 * | foreach($Request->GetFiles() as $File) {
 * |   if ($File->IsValid()) {
 * |       // $File->Rename('myfile.txt');     or     $File->RenameBasename('myfile');
 * |       // if ($File->GetExtension() <> 'jpg')...     or     if ($File->GetSize() > XXX)...
 * |       $File->Move($TargetDir);
 * |   } else if {$File->HasError() {
 * |       echo 'Error uploading file: '.$File->GetName().' ('.$File->GetError().').';
 * |   } else {
 * |       // file has not sent
 * |   }
 * | }
 * or by fetching specified uploaded file
 * | $File= $Request->GetFile('Avatar');
 * | if ($File->IsValid() && $File->IsImage() && $File->GetSize()<XXX) {
 * |    $File->Move($TargetDir);
 * | }
 * or by using Process utility
 * | $File= $Request->GetFile('CV');
 * | $Result= $File->Process(array(
 * |     'Dir'           => $TargetDir,
 * |     'Extensions'    => 'doc,docx,odt,odtx,pdf',
 * |     'MaxSize'       => 1024*1024*2,
 * |     'RenameBasename'=> $UserId,
 * | ));
 * | if ($Result === true) {
 * |     ... // show confirmation
 * | } else if ($Result === false){
 * |     echo 'Error uploading file: '.$File->GetErrorMsg();
 * | }
 *
 * Note that this component does not trigger $this->Error() method for an error occurences,
 * instead developer must check result of Move() and manually trigger: $this->Error($this->GetErrorMsg()).
 * Method Process is an exception to that rule because it encapsulate common workflow.
 */

use Accent\AccentCore\Component;


class File extends Component {

    // configuration
    protected static $DefaultOptions= array(

        // data taken from $_FILE field
        'OrigInfo'=> array(
            'tmp_name' => '',
            'name'     => '',
            'size'     => 0,
            'error'    => 0,
        ),
    );

    // -- internal properties --------------------------------------------------
    protected $Name;
    protected $TmpName;
    protected $Size;
    protected $Error;
    protected $MimeType;


    /**
     * Constructor
     */
    public function __construct($Options) {

        // call parent
        parent::__construct($Options);

        // export $_FILE info pack
        $Info= $this->GetOption('OrigInfo');
        $this->Name   = $this->SanitizeFilename($Info['name']);
        $this->TmpName= $Info['tmp_name'];
        $this->Size   = intval($Info['size']);
        $this->Error  = intval($Info['error']);

        // validate source
        if ($this->Error === UPLOAD_ERR_OK && !is_uploaded_file($this->TmpName)) {
            $this->Error= UPLOAD_ERR_NO_FILE;
        }
     }


    // -- getters --------------------------------------------------------------


    public function GetTmpName() {

        return $this->TmpName;
    }

    public function GetName() {

        return $this->Name;
    }

    public function GetExtension() {

        return pathinfo($this->Name, PATHINFO_EXTENSION);
    }

    public function GetSize() {

        return $this->Size;
    }

    public function GetError() {

        return $this->Error;
    }


    // -- utilities ------------------------------------------------------------


    public function GetErrorMsg() {

        // for string errors
        if (is_string($this->Error)) {
            return $this->Error;
        }
        // for numeric error codes
        switch ($this->Error) {
            case UPLOAD_ERR_OK:
            case UPLOAD_ERR_NO_FILE:
                return '';
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
            case UPLOAD_ERR_PARTIAL:
                return $this->MsgDef('File %s exceeds allowed filesize.', 'Request.FileErr.Size', null, null, array($this->GetName()));
            case UPLOAD_ERR_NO_TMP_DIR:
                return $this->MsgDef('Missing temp upload directory.', 'Request.FileErr.NoTmp', null, null, array($this->GetName()));
        }
        // for all other numeric error codes
        return $this->MsgDef('Unable to upload file %s.', 'Request.FileErr.Unknown', null, null, array($this->GetName()));
    }


    /**
     * Returns true if file is ready to be moved to its destination.
     *
     * @return bool
     */
    public function IsValid() {

        return $this->Error === UPLOAD_ERR_OK;
    }


    /**
     * Returns true if there is some error in transfer.
     * Missing file is not an error.
     *
     * @return bool
     */
    public function HasError() {

        return !in_array($this->Error, array(UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE));
    }


    /**
     * Check is uploaded file image.
     * By default it will use file extension for detection
     * but with $CheckMimeType parameter it will check real mime-type too.
     *
     * @param bool $CheckMimeType
     * @return boolean
     */
    public function IsImage($CheckMimeType=false) {

        $ImageExtensions= array('jpg', 'jpe', 'jpeg', 'gif', 'png');
        if (!in_array(strtolower($this->GetExtension()), $ImageExtensions)) {
            return false;
        }
        if ($CheckMimeType) {
            $MimeTypes= array('image/jpeg', 'image/gif', 'image/png');
            if (!in_array($this->GetMimeType(), $MimeTypes)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Returns array with width and height of uploaded image
     * or false on missing or non-image file.
     *
     * @return array|false
     */
    public function GetImageSize() {

        return $this->IsValid()
            ? @getimagesize($this->TmpName) // @ - prevent warning on reading non-images
            : false;
    }


    /**
     * Returns mime-type of uploaded file or null if hosting does not support checking.
     * This is real mime-type detection, not via file extension.
     *
     * @return null|string
     */
    public function GetMimeType() {

        if ($this->IsValid() && $this->MimeType === null) {
            if (function_exists('mime_content_type')) {
                $this->MimeType= mime_content_type($this->TmpName);
            } elseif (function_exists('finfo_open')) {
                $fInfo= finfo_open(FILEINFO_MIME);
                $this->MimeType= finfo_file($fInfo, $this->TmpName);
                finfo_close($fInfo);
            }
        }
        return $this->MimeType;
    }


    /**
     * Change name of file before moving it to target location.
     *
     * @param string $NewName
     * @return self
     */
    public function Rename($NewName) {

        $this->Name= $this->SanitizeFilename($NewName);
        return $this;
    }


    /**
     * Change name of file (but preserve extension) before moving it to target location.
     *
     * @param string $NewName
     * @return self
     */
    public function RenameBasename($NewName) {

        $Ext= $this->GetExtension();
        $this->Name= $this->SanitizeFilename($NewName).'.'.$Ext;
        return $this;
    }


    /**
     * Move uploaded file to target location.
     *
     * @param string $ToDir  Target directory
     * @return true|false|null  Success
     */
    public function Move($ToDir) {

        // if there is no file return null
        if ($this->Error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        // if any error occured return false
        if ($this->Error !== UPLOAD_ERR_OK) {
            return false;
        }
        // create target directory
        if (!$ToDir) {
            $this->Error= $this->MsgDef('Target directory must be specified.', 'Request.FileErr.NoDir', null, null, array($ToDir));
            return false;
        }
        if (!is_dir($ToDir)) {
            if (!mkdir($ToDir, 0777, true)) {
                $this->Error= $this->MsgDef('Unable to create directory %s.', 'Request.FileErr.CreateDir', null, null, array($ToDir));
                return false;
            }
        }
        // remove existing file (overwrite)
		$ToPath= rtrim($this->ResolvePath($ToDir), '\\/').'/'.$this->Name;
		if (is_file($ToPath)) {
            @unlink($ToPath);       // @ - possible file permition protection
        }
        // move file to target location
        if (!move_uploaded_file($this->TmpName, $ToPath)) {
            $this->Error= error_get_last();
            return false;
        }
        // success
        @chmod($ToPath, 0666);      // @ - maybe no permition to use chmod
        return true;
    }


    //-- uploading processor ---------------------------------------------------


    /**
     * Processor is automated method that encapsulate common workflow for handling uploading files.
     * Options are array of:
     *  - Dir: (string) target directory (can use ResolvePath()) (mandatory)
     *  - TriggerErrors: (bool) set to false to avoid triggering $this->Error() method
     *  - Rename: same as method
     *  - RenameBasename: same as method
     *  - MaxSize: (int) maximum allowed file size in bytes
     *  - Extensions: (string) CSV list of allowed file extensions
     *  - IsImage: (bool) require file to be an image file
     * @param array $Options
     */
    public function Process($Options) {

        $Options += array(
            'Dir' => '',
            'TriggerError'=> true,
        );
        // if there is no file return null
        if ($this->Error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        // if any error occured return false
        if ($this->Error !== UPLOAD_ERR_OK) {
            $this->ProcessError($this->Error, $Options);
            return false;
        }
        // apply options
        if (isset($Options['Rename'])) {
            $this->Rename($Options['Rename']);
        }
        if (isset($Options['RenameBasename'])) {
            $this->RenameBasename($Options['RenameBasename']);
        }
        if (isset($Options['IsImage']) && !$this->IsImage(true)) {
            $Err= $this->MsgDef('File "%s" is not valid image.', 'Request.FileErr.Image', null, null, array($this->GetName()));
            $this->ProcessError($Err, $Options);
            return false;
        }
        // validate file extension
        $Ext= strtolower($this->GetExtension());
        $Extensions= isset($Options['Extensions'])
            ? array_filter(array_map('trim', explode(',', $Options['Extensions'])))
            : array();
        if (!empty($Extensions) && !in_array($Ext, $Extensions)) {
            $Err= $this->MsgDef('File extension "%s" is not allowed.', 'Request.FileErr.Ext', null, null, array($Ext));
            $this->ProcessError($Err, $Options);
            return false;
        }
        // validate file size
        $MaxSize= isset($Options['MaxSize']) ? intval($Options['MaxSize']) : 0;
        if ($MaxSize > 0 && $this->GetSize() > $MaxSize) {
            $this->ProcessError(UPLOAD_ERR_FORM_SIZE, $Options);       // recycle message about exceeded size
            return false;
        }
        // move file
        $Success= $this->Move($Options['Dir']);
        if ($Success === false) {
            $this->ProcessError($this->Error, $Options);
        }
        return $Success;
    }


    // -- helpers --------------------------------------------------------------


    protected function ProcessError($Error, $Options) {

        $this->Error= $Error;
        if ($Options['TriggerError']) {
            $this->Error($this->GetErrorMsg());
        }
    }


    /**
     * Cleaning filename from non-standard chars.
     */
    protected function SanitizeFilename($Name) {

        $Name= preg_replace('/[^A-Za-z0-9~_!\|\.\-\+]/', '', $Name);
        return $Name;
    }

}

?>