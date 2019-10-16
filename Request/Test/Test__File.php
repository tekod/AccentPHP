<?php namespace Accent\Request\Test;

/**
 * Testing Accent\Request\File
 *
 * Tag: [TestModelForward] // allowing test-forward calls
 */

use Accent\Test\AccentTestCase;
use Accent\Request\Request;
use Accent\AccentCore\RequestContext;
use Accent\AccentCore\Event\Event;
use Accent\Test\PhpStream;


class Test__File extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Request / File component test';

    // title of testing group
    const TEST_GROUP= 'Request';


    /**
     * Builder.
     * @return Accent\Request\Request
     */
    protected function Build($NewOptions=array(), $Context=array()) {

        $Options= array(
            'RequestContext'=> (new RequestContext)->FromArray($Context),
            'Services'=> array(
            ),
        );
        return new Request($NewOptions + $Options);
    }



    // TESTS:



    public function TestGetFiles() {

        $R= $this->Build(array(), array('FILES'=> array(
            'BigIcon'=> array('name'=>'pic.jpg', 'tmp_name'=>'...', 'size'=>5000, 'error'=>UPLOAD_ERR_OK),
            'SmallIcon'=> array('name'=>'pic2.jpg', 'tmp_name'=>'...', 'size'=>3000, 'error'=>UPLOAD_ERR_OK),
        )));
        // get all files
        $Files= $R->GetFiles();
        $this->assertEqual(is_array($Files), true);
        $this->assertEqual(count($Files), 2);
        // get unexisted file
        $File= $R->GetFile('cc');
        $this->assertEqual($File, false);
        // get existed file
        $File= $R->GetFile('BigIcon');
        $this->assertEqual(is_object($File), $File);
        $this->assertEqual($File->GetName(), 'pic.jpg');
        $File= $R->GetFile('SmallIcon');
        $this->assertEqual(is_object($File), $File);
        $this->assertEqual($File->GetName(), 'pic2.jpg');
    }


    public function TestFile() {

        // prepare temp directory
        @mkdir(__DIR__.'/tmp');
        $FS= new \Accent\AccentCore\File\File();
        $FS->DirectoryClear(__DIR__.'/tmp');    // clear playground

        // call forged request
        $Result= $this->DispatchUploadRequest(array(), array(
            // files for testing distinct methods
            'FileTxt'=> 'abcd',
            'FileGif'=> base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw=='),  // spacer.gif
            'FileErr'=> 'xy',
            'FileNoF'=> null,       // not uploaded file
            // files for testing Process() feature
            'ProcTxt'=> 'abcd',
            'ProcGif'=> base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw=='),  // spacer.gif
            'ProcErr'=> 'xy',
            'ProcNoF'=>  null,      // not uploaded file
        ));

        // result can be "(*)" or error message
        if ($Result !== '(*)') {
            $this->fail($Result);
        }
    }


    public static function ForwardTest_DoUpload() {

        // simulate uploading error
        $_FILES['FileErr']['error']= UPLOAD_ERR_INI_SIZE;
        $_FILES['ProcErr']['error']= UPLOAD_ERR_INI_SIZE;

        // build Request object, use superglobals to initialize its context
        $Request= new Request(array(
            'RequestContext'=> (new RequestContext)->FromGlobals(),
        ));
        /** @var $FileTxt Accent\Request\File */
        $FileTxt= $Request->GetFile('FileTxt');
        /** @var $FileGif Accent\Request\File */
        $FileGif= $Request->GetFile('FileGif');
        /** @var $FileError Accent\Request\File */
        $FileErr= $Request->GetFile('FileErr');
        /** @var $FileError Accent\Request\File */
        $FileNoF= $Request->GetFile('FileNoF');

        // test IsValid()
        if (!$FileTxt->IsValid()) {die('FileTxt is not valid in '.__FILE__.'['.__LINE__.']');}
        if (!$FileGif->IsValid()) {die('FileGif is not valid in '.__FILE__.'['.__LINE__.']');}
        if ($FileErr->IsValid()) {die('FileErr must not be valid in '.__FILE__.'['.__LINE__.']');}
        if ($FileNoF->IsValid()) {die('FileNoF must not be valid in '.__FILE__.'['.__LINE__.']');}

        // test GetExtension()
        if ($FileTxt->GetExtension() <> 'txt') {die('FileTxt has extension "'.$FileTxt->GetExtension().'" in '.__FILE__.'['.__LINE__.']');}
        if ($FileGif->GetExtension() <> 'gif') {die('FileGif has extension "'.$FileGif->GetExtension().'" in '.__FILE__.'['.__LINE__.']');}
        if ($FileErr->GetExtension() <> 'err') {die('FileErr has extension "'.$FileErr->GetExtension().'" in '.__FILE__.'['.__LINE__.']');}

        // test GetSize()
        if ($FileTxt->GetSize() <> 4) {die('FileTxt has size of '.$FileTxt->GetSize().' bytes in '.__FILE__.'['.__LINE__.']');}
        if ($FileGif->GetSize() <> 43) {die('FileGif has size of '.$FileGif->GetSize().' bytes in '.__FILE__.'['.__LINE__.']');}
        if ($FileErr->GetSize() <> 2) {die('FileErr has size of '.$FileErr->GetSize().' bytes in '.__FILE__.'['.__LINE__.']');}

        // test GetError() / HasError() (missing file is not an error)
        if ($FileTxt->HasError() || $FileTxt->GetError() !== UPLOAD_ERR_OK) {die('FileTxt has error '.$FileTxt->GetError().' in '.__FILE__.'['.__LINE__.']');}
        if ($FileGif->HasError() || $FileGif->GetError() !== UPLOAD_ERR_OK) {die('FileGif has error '.$FileTxt->GetError().' in '.__FILE__.'['.__LINE__.']');}
        if (!$FileErr->HasError() || $FileErr->GetError() !== UPLOAD_ERR_INI_SIZE) {die('FileErr has error '.$FileErr->GetError().' in '.__FILE__.'['.__LINE__.']');}
        if ($FileNoF->HasError() || $FileNoF->GetError() !== UPLOAD_ERR_NO_FILE) {die('FileNoF has error '.$FileNoF->GetError().' in '.__FILE__.'['.__LINE__.']');}

        // test GetErrorMsg()
        if ($FileTxt->GetErrorMsg() <> '') {die('FileTxt has error message "'.$FileTxt->GetErrorMsg().'" in '.__FILE__.'['.__LINE__.']');}
        if ($FileGif->GetErrorMsg() <> '') {die('FileGif has error message "'.$FileGif->GetErrorMsg().'" in '.__FILE__.'['.__LINE__.']');}
        if (strpos($FileErr->GetErrorMsg(), 'exceeds allowed') === false) {die('FileErr has error message "'.$FileErr->GetErrorMsg().'" in '.__FILE__.'['.__LINE__.']');}
        if ($FileNoF->GetErrorMsg() <> '') {die('FileNoF has error message "'.$FileNoF->GetErrorMsg().'" in '.__FILE__.'['.__LINE__.']');}

        // test IsImage()
        if ($FileTxt->IsImage()) {die('FileTxt is image by extension in '.__FILE__.'['.__LINE__.']');}
        if (!$FileGif->IsImage()) {die('FileGif is NOT image by extension in '.__FILE__.'['.__LINE__.']');}
        if ($FileErr->IsImage()) {die('FileErr is image by extension in '.__FILE__.'['.__LINE__.']');}
        if ($FileTxt->IsImage(true)) {die('FileTxt is image by mimetype in '.__FILE__.'['.__LINE__.']');}
        if (!$FileGif->IsImage(true)) {die('FileGif is NOT image by mimetype in '.__FILE__.'['.__LINE__.']');}
        if ($FileErr->IsImage(true)) {die('FileErr is image by mimetype in '.__FILE__.'['.__LINE__.']');}

        // test GetImageSize()
        $ImageSize= $FileTxt->GetImageSize();
        if (is_array($ImageSize)) {die('FileTxt has imagesize ['.$ImageSize[0].','.$ImageSize[1].'] in '.__FILE__.'['.__LINE__.']');}
        $ImageSize= $FileGif->GetImageSize();
        if ($ImageSize[0] <> 1 || $ImageSize[1] <> 1) {die('FileGif has imagesize ['.$ImageSize[0].','.$ImageSize[1].'] in '.__FILE__.'['.__LINE__.']');}

        // test GetMimeType()
        $MimeType= $FileTxt->GetMimeType();
        if ($MimeType <> 'text/plain') {die('FileTxt has mimetype "'.$MimeType.'" in '.__FILE__.'['.__LINE__.']');}
        $MimeType= $FileGif->GetMimeType();
        if ($MimeType <> 'image/gif') {die('FileGif has mimetype "'.$MimeType.'" in '.__FILE__.'['.__LINE__.']');}

        // test Rename()
        $FileTxt->Rename('F.txt');
        if ($FileTxt->GetName() <> 'F.txt') {die('FileTxt has name "'.$FileTxt->GetName().'" in '.__FILE__.'['.__LINE__.']');}
        $FileErr->Rename('F.err');
        if ($FileErr->GetName() <> 'F.err') {die('FileErr has name "'.$FileErr->GetName().'" in '.__FILE__.'['.__LINE__.']');}

        // test RenameBasename()
        $FileTxt->RenameBasename('File');
        if ($FileTxt->GetName() <> 'File.txt') {die('FileTxt has name "'.$FileTxt->GetName().'" in '.__FILE__.'['.__LINE__.']');}
        $FileErr->RenameBasename('');   // empty basename
        if ($FileErr->GetName() <> '.err') {die('FileErr has name "'.$FileErr->GetName().'" in '.__FILE__.'['.__LINE__.']');}
        $FileTxt->Rename('FileTxt.txt'); // restore
        $FileErr->Rename('FileErr.err'); // restore

        // test Move()
        $Success= $FileTxt->Move(__DIR__.'/tmp');
        if ($Success !== true) {die('FileTxt returns '.var_export($Success,true).' on Move() in '.__FILE__.'['.__LINE__.']');}
        $Success= $FileGif->Move(__DIR__.'/tmp');
        if ($Success !== true) {die('FileGif returns '.var_export($Success,true).' on Move() in '.__FILE__.'['.__LINE__.']');}
        $Success= $FileErr->Move(__DIR__.'/tmp');
        if ($Success !== false) {die('FileErr returns '.var_export($Success,true).' on Move() in '.__FILE__.'['.__LINE__.']');}
        $Success= $FileNoF->Move(__DIR__.'/tmp');
        if ($Success !== null) {die('FileNoF returns '.var_export($Success,true).' on Move() in '.__FILE__.'['.__LINE__.']');}

        // validate result files
        if (!is_file(__DIR__.'/tmp/FileTxt.txt') || file_get_contents(__DIR__.'/tmp/FileTxt.txt') <> 'abcd') {die('FileTxt.txt not found, in  '.__FILE__.'['.__LINE__.']');}
        if (!is_file(__DIR__.'/tmp/FileGif.gif') || filesize(__DIR__.'/tmp/FileGif.gif') <> 43) {die('FileGif.gif not found, in  '.__FILE__.'['.__LINE__.']');}

        // test Process() feature

        /** @var $ProcTxt Accent\Request\File */
        $ProcTxt= $Request->GetFile('ProcTxt');
        /** @var $FileGif Accent\Request\File */
        $ProcGif= $Request->GetFile('ProcGif');
        /** @var $FileError Accent\Request\File */
        $ProcErr= $Request->GetFile('ProcErr');
        /** @var $FileError Accent\Request\File */
        $ProcNoF= $Request->GetFile('ProcNoF');

        // test processing "no file"
        $Success= $ProcNoF->Process(array(
            'Dir'=> __DIR__.'/tmp',
            'TriggerError'=> false,
        ));
        if ($Success !== null) {die('ProcNoF returns '.var_export($Success,true).' on Process() in '.__FILE__.'['.__LINE__.']');}

        // test processing file with error
        $Success= $ProcErr->Process(array(
            'Dir'=> __DIR__.'/tmp',
            'TriggerError'=> false,
        ));
        if ($Success !== false) {die('ProcErr returns '.var_export($Success,true).' on Process() in '.__FILE__.'['.__LINE__.']');}

        // test processing txt file with renaming and list of allowed extensions, must issue error about extension and fail to process
        $Success= $ProcTxt->Process(array(
            'Dir'=> __DIR__.'/tmp',
            'Rename'=> 'TextData.dump',
            'Extensions'=> 'txt,text,dat',
            'TriggerError'=> false,
        ));
        if (strpos($ProcTxt->GetErrorMsg(), 'extension') === false) {die('ProcText has ErrorMsg "'.var_export($ProcTxt->GetErrorMsg(),true).'" on Process() in '.__FILE__.'['.__LINE__.']');}
        if ($Success !== false) {die('ProcText returns '.var_export($Success,true).' on Process() in '.__FILE__.'['.__LINE__.']');}

        // test processing image file with renamebasename and maxsize and isimage, must pass
        $Success= $ProcGif->Process(array(
            'Dir'=> __DIR__.'/tmp',
            'RenameBasename'=> 'Pic',
            'IsImage'=> true,
            'MaxSize'=> 9999,
            'TriggerError'=> false,
        ));
        if ($Success !== true) {die('ProcGif returns '.var_export($Success,true).' on Process() in '.__FILE__.'['.__LINE__.']');}
        if ($ProcGif->GetError() !== UPLOAD_ERR_OK) {die('ProcGif has ErrorMsg "'.var_export($ProcGif->GetErrorMsg(),true).'" on Process() in '.__FILE__.'['.__LINE__.']');}

        // validate result files
        if (!is_file(__DIR__.'/tmp/Pic.gif') || filesize(__DIR__.'/tmp/Pic.gif') <> 43) {die('Pic.gif not found, in  '.__FILE__.'['.__LINE__.']');}

        // success of all tests
        die('(*)');
}


    /**
     * Helper, send upload request (multipart content-type) to Test.php and return answer.
     */
    protected function DispatchUploadRequest($PostData, $Files) {

        $Boundary= '----------------------'.microtime(true);
        $Body= '';
        foreach($Files as $FileKey=>$FileContent) {
            $Filename= $FileKey.'.'.strtolower(substr($FileKey, -3));
            if ($FileContent === null) {
                $Filename= '';
            }
            $Body .=  "--".$Boundary."\r\n".
                "Content-Disposition: form-data; name=\"$FileKey\"; filename=\"$Filename\"\r\n".
                "Content-Type: application/octet-stream\r\n\r\n".
                $FileContent."\r\n";
        }
        foreach($PostData as $PostKey=>$PostValue) {
            $Body .= "--".$Boundary."\r\n".
                "Content-Disposition: form-data; name=\"$PostKey\"\r\n\r\n".
                "$PostValue\r\n";
        }
        $Body .= "--".$Boundary."--\r\n";
        // build streamcontext
        $Context= stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-Type: multipart/form-data; boundary='.$Boundary,
                'content' => $Body,
            )
        ));
        // build URL to testing facility with instruction to call RetUpload method
        $URL= $this->BuildTestURL(array(
            'Act'=> 'Forward',
            'Target'=> 'Request.Test.Test__File::DoUpload',
        ));
        // fetch and return
        return file_get_contents($URL, false, $Context);
    }



}


?>