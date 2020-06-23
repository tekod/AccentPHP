<?php namespace Accent\AccentCore\Test;

use Accent\Test\AccentTestCase;


/**
 * Testing File component.
 */
use Accent\AccentCore\File\File as FileService;


class Test__File extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'File service test';

    // title of testing group
    const TEST_GROUP= 'AccentCore';


    public function __construct() {

        parent::__construct();

        // create "temp" directory if missing
        if (!is_dir(__DIR__.'/tmp')) {
            mkdir(__DIR__.'/tmp');
        }
    }


    public function TestSlashed() {

        $F= new FileService;
        $this->assertEqual($F->Slashed(null), '');
        $this->assertEqual($F->Slashed(''),   '');
        $this->assertEqual($F->Slashed('/'),  '');
        $this->assertEqual($F->Slashed(' '),  ' /');
        $this->assertEqual($F->Slashed('a'),  'a/');
        $this->assertEqual($F->Slashed('a/'), 'a/');
        $this->assertEqual($F->Slashed('a\\'), 'a/');
        $this->assertEqual($F->Slashed('/a'), '/a/');
        $this->assertEqual($F->Slashed('\\a'), '/a/');
    }


    public function TestUnslashed() {

        $F= new FileService;
        $this->assertEqual($F->Unslashed(null), '');
        $this->assertEqual($F->Unslashed(''),   '');
        $this->assertEqual($F->Unslashed('/'),  '');
        $this->assertEqual($F->Unslashed(' '),  ' ');
        $this->assertEqual($F->Unslashed('a'),  'a');
        $this->assertEqual($F->Unslashed('a/'), 'a');
        $this->assertEqual($F->Unslashed('a//'),'a');
        $this->assertEqual($F->Unslashed('/a'), '/a');
        $this->assertEqual($F->Unslashed('a\\'),'a');
        $this->assertEqual($F->Unslashed('\\a'), '/a');
    }


    public function TestIsValidFileName() {

        $F= new FileService;
        $this->assertEqual($F->IsValidFileName(''), false);         // empty str
        $this->assertEqual($F->IsValidFileName('a'), true);         // single char is valid name
        $this->assertEqual($F->IsValidFileName('a b'), true);       // space char is allowed
        $this->assertEqual($F->IsValidFileName('abc.'), true);      // empty extension is allowed
        $this->assertEqual($F->IsValidFileName('.abc'), true);      // empty basename is allowed
        $this->assertEqual($F->IsValidFileName('a:bc'), false);     // ":" is not allowed
        $this->assertEqual($F->IsValidFileName('a/b'), false);      // slash is not allowed
        $this->assertEqual($F->IsValidFileName('a'."\n".'b'), false);// enter is not allowed
        $this->assertEqual($F->IsValidFileName('δÜд'), false);      // utf-8 encoding is not allowed
    }


    public function TestAddSuffixToFileName() {

        $F= new FileService;
        $this->assertEqual($F->AddSuffixToFileName('abc.txt', '#'), 'abc#.txt');    // normal case
        $this->assertEqual($F->AddSuffixToFileName('abc',     '#'), 'abc#');        // missing extension
        $this->assertEqual($F->AddSuffixToFileName('abc.',    '#'), 'abc#.');       // missing extension
        $this->assertEqual($F->AddSuffixToFileName('.txt',    '#'), '#.txt');       // missing basename
        $this->assertEqual($F->AddSuffixToFileName('a.txt', 'XYZ'), 'aXYZ.txt');    // adding longer sufix
        $this->assertEqual($F->AddSuffixToFileName('',        '#'), '#');           // adding to empty string
        $this->assertEqual($F->AddSuffixToFileName(null,      '#'), '#');           // assume null as empty string
        $this->assertEqual($F->AddSuffixToFileName('abc.txt', ''),  'abc.txt');     // adding nothing
        $this->assertEqual($F->AddSuffixToFileName('abc.',    ''),  'abc.');        // adding nothing
        $this->assertEqual($F->AddSuffixToFileName('.txt',    ''),  '.txt');        // adding nothing
    }


    public function TestShortedFileName() {

        $F= new FileService;
        $this->assertEqual($F->ShortedFileName('qwert.txt', 99), 'qwert.txt');      // no change
        $this->assertEqual($F->ShortedFileName('qwert.txt', 9),  'qwert.txt');      // exact length - do not change
        $this->assertEqual($F->ShortedFileName('qwert.txt', 8),  'qw...txt');       // consume few chars
        $this->assertEqual($F->ShortedFileName('qwert.txt', 6),  '...txt');         // consume whole basename
        $this->assertEqual($F->ShortedFileName('qwert.txt', 5),  'qw...');          // no enough space, trim ending
        $this->assertEqual($F->ShortedFileName('qwert.txt', 1),  '...');            // consume everything
        $this->assertEqual($F->ShortedFileName('qwertzuiop', 8), 'qwert...');       // no extension
        $this->assertEqual($F->ShortedFileName('qwertzuiop.', 8),'qwert...');       // no extension
        $this->assertEqual($F->ShortedFileName('.htaccess', 34), '.htaccess');      // no basename
        $this->assertEqual($F->ShortedFileName('.htaccess', 7),  '.hta...');        // no basename
        $this->assertEqual($F->ShortedFileName('',          20), '');               // empty string
        $this->assertEqual($F->ShortedFileName(null,        20), '');               // assume null as empty string
        $this->assertEqual($F->ShortedFileName('qwertzui.txt', 7, '_'), 'qwe_txt'); // custom dots
    }


    public function TestRenameButPreserveExt() {

        $F= new FileService;
        $this->assertEqual($F->RenameButPreserveExt('qwert.txt', 'abc'), 'abc.txt');// simple case
        $this->assertEqual($F->RenameButPreserveExt('qwert',     'abc'), 'abc');    // no extension
        $this->assertEqual($F->RenameButPreserveExt('qwert.',    'abc'), 'abc.');   // no extension
        $this->assertEqual($F->RenameButPreserveExt('.gif',      'abc'), 'abc.gif');// no basename
        $this->assertEqual($F->RenameButPreserveExt('q.w.e.txt', 'abc'), 'abc.txt');// dots in name
        $this->assertEqual($F->RenameButPreserveExt('qwert.txt',    ''), '.txt');   // change with empty string
        $this->assertEqual($F->RenameButPreserveExt('qwert',        ''), '');       // change with empty string
        $this->assertEqual($F->RenameButPreserveExt('.txt',         ''), '.txt');   // change with empty string
        $this->assertEqual($F->RenameButPreserveExt('',             ''), '');       // change with empty string
        $this->assertEqual($F->RenameButPreserveExt('',          'abc'), 'abc');    // missing file name
        $this->assertEqual($F->RenameButPreserveExt('',             ''), '');       // both empty strings
    }


    public function TestNewTempFile() {

        $F= new FileService;
        $InDir= $F->Slashed(__DIR__).'tmp';

        // just find unique name
        $Name= $F->NewTempFilename();
        $this->assertEqual(is_string($Name), true);                                 // it must be string
        $this->assertEqual(substr($Name, -4), '.tmp');                              // check "tmp" extension

        // find name in specified directory
        $Name= $F->NewTempFilename($InDir);
        $this->assertEqual(is_string($Name), true);                                 // it must be string
        $PI= pathinfo($Name);
        $this->assertEqual($PI['dirname'], $InDir);                                 // check dirname

        // simple case
        $Hnd= $F->NewTempFile();
        $this->assertEqual(is_resource($Hnd), true);                                // it must be file handler
        $this->assertEqual(substr(stream_get_meta_data($Hnd)['uri'], -4), '.tmp');  // check "tmp" extension
        fclose($Hnd);

        // create in custom dir
        $Hnd= $F->NewTempFile($InDir);
        $this->assertEqual(is_resource($Hnd), true);                                // it must be file handler
        $PI= pathinfo(stream_get_meta_data($Hnd)['uri']);
        $this->assertEqual($PI['dirname'], $InDir);
        unlink(stream_get_meta_data($Hnd)['uri']);                                  // remove temp file                             // check dirname
        fclose($Hnd);
    }


    public function TestLoadFile_SaveFile() {

        $F= new FileService;
        $Path= __DIR__.'/tmp/dump.txt';

        // create new file
        $Succ= $F->SaveFile($Path, 'abc');
        $this->assertEqual($Succ, true);
        $this->assertEqual(file_get_contents($Path), 'abc');

        // try with LoadFile()
        $this->assertEqual($F->LoadFile($Path), 'abc');
        $this->assertEqual($F->LoadFile($Path.'.pdf'), false);

        // append some content
        $Succ= $F->SaveFile($Path, 'xy', 'a');
        $this->assertEqual($Succ, true);
        $this->assertEqual(file_get_contents($Path), 'abcxy');

        // rewrite content
        $Succ= $F->SaveFile($Path, 'RT');      // without param "o" it must fail
        $this->assertEqual($Succ, false);
        $this->assertEqual(file_get_contents($Path), 'abcxy');

        // rewrite content
        $Succ= $F->SaveFile($Path, 'RT', 'o');
        $this->assertEqual($Succ, true);
        $this->assertEqual(file_get_contents($Path), 'RT');

        // clean
        unlink($Path);
    }


    public function TestCopyFile() {

        $F= new FileService;
        $From= __DIR__.'/tmp/dump.txt';
        $To= __DIR__.'/tmp/dump2.txt';
        file_put_contents($From, 'xy');

        // both params are required
        $this->assertEqual($F->CopyFile('', $To), false);
        $this->assertEqual($F->CopyFile($From, ''), false);

        // copy
        $Succ= $F->CopyFile($From, $To);

        // test
        $this->assertEqual($Succ, true);
        $this->assertEqual(file_get_contents($To), 'xy');

        // now copy with deleting original file
        $Succ= $F->CopyFile($From, $To, true);

        // test
        $this->assertEqual($Succ, true);
        $this->assertEqual(is_file($From), false);

        // clean
        @unlink($From);
        @unlink($To);
    }


    public function TestDeleteFile() {

        $F= new FileService;
        $Path= __DIR__.'/tmp/dump3.txt';
        file_put_contents($Path, 'abc');

        // delete
        $Succ= $F->DeleteFile($Path);

        // test
        $this->assertEqual($Succ, true);
        $this->assertEqual(is_file($Path), false);
    }


    public function TestMkDirRecursive() {

        $F= new FileService;
        $Path= __DIR__.'/tmp/a/b/c';

        $Succ= $F->MkDirRecursive($Path);
        $this->assertEqual($Succ, true);

        // validation
        $this->assertEqual(is_dir($Path), true);
    }


    public function TestRmDir() {

        $F= new FileService;

        // remove each dirs from previous test
        $this->assertEqual($F->RmDir(__DIR__.'/tmp/a/b/c'), true);
        $this->assertEqual($F->RmDir(__DIR__.'/tmp/a/b'), true);
        $this->assertEqual($F->RmDir(__DIR__.'/tmp/a'), true);
    }


    public function TestDirectoryClear() {

        $F= new FileService;
        $Dir= __DIR__.'/tmp';

        // make 3 files in different depths
        @mkdir("$Dir/a/aa", 0777, true);
        touch("$Dir/file1.txt");
        touch("$Dir/a/file2.txt", time()-86400); // timestamp= -24h
        touch("$Dir/a/aa/file3.txt");

        // validate
        $this->assertEqual(is_file("$Dir/a/aa/file3.txt"), true);

        // test suicide protection
        // must test with dirname(__DIR__) because __DIR__ is NOT protected - it is not parent of File class
        $this->assertEqual($F->DirectoryClear(dirname(__DIR__)), false);

        // clear directory but only files older then 1 hour
        $Succ= $F->DirectoryClear($Dir, time()-3600);
        $this->assertEqual($Succ, false);   // it is normal because some dirs are not empty

        // validate
        $this->assertEqual(is_file("$Dir/file1.txt"), true);
        $this->assertEqual(is_file("$Dir/a/file2.txt"), false);
        $this->assertEqual(is_file("$Dir/a/aa/file3.txt"), true);

        // clear directory, all files
        $Succ= $F->DirectoryClear($Dir);
        $this->assertEqual($Succ, true);

        // validate
        $this->assertEqual(is_dir("$Dir/a"), false);
        $this->assertEqual(is_dir("$Dir/a/aa"), false);
        $this->assertEqual(is_file("$Dir/file1.txt"), false);
        $this->assertEqual(is_file("$Dir/a/file2.txt"), false);
        $this->assertEqual(is_file("$Dir/a/aa/file3.txt"), false);
    }


    public function TestDirectoryCopy() {

        $F= new FileService;
        $From= __DIR__.'/tmp/from';
        $To= __DIR__.'/tmp/to';

        // make 2 files in different depths
        mkdir("$From/aa", 0777, true);
        touch("$From/file1.txt");
        touch("$From/aa/file2.txt");

        // copy directory
        mkdir($To, 0777, true);
        $Succ= $F->DirectoryCopy($From, $To);
        $this->assertEqual($Succ, true);

        // validate
        $this->assertEqual(is_file("$To/file1.txt"), true);
        $this->assertEqual(is_file("$To/aa/file2.txt"), true);

        // now copy and delete original files
        $F->DirectoryClear($To);
        $Succ= $F->DirectoryCopy($From, $To, true);
        $this->assertEqual($Succ, true);

        // validate
        $this->assertEqual(is_file("$From/file1.txt"), false);
        $this->assertEqual(is_file("$From/aa/file2.txt"), false);
        $this->assertEqual(is_file("$To/file1.txt"), true);
        $this->assertEqual(is_file("$To/aa/file2.txt"), true);

        // clean
        $F->DirectoryClear(__DIR__.'/tmp');
    }


    public function TestChModeAllFiles() {

        $F= new FileService;
        $Dir= __DIR__.'/tmp';

        // make 2 files in different depths
        mkdir("$Dir/a/aa", 0777, true);
        touch("$Dir/file1.txt");
        touch("$Dir/a/aa/file2.txt");

        // chmod
        $Succ= $F->ChModAllFiles($Dir, 0444, 0555);
        $this->assertEqual($Succ, true);

        // validate
        $this->assertEqual(substr(sprintf('%o',fileperms("$Dir/file1.txt")),-4), '0444');
        $this->assertEqual(substr(sprintf('%o',fileperms("$Dir/a/aa/file2.txt")),-4), '0444');
        $this->assertEqual(substr(sprintf('%o',fileperms("$Dir/a/aa")),-4), '0555');

        // clean
        chmod("$Dir/a", 0777);
        chmod("$Dir/a/aa", 0777);
        chmod("$Dir/file1.txt", 0666);
        chmod("$Dir/a/aa/file2.txt", 0666);
        $F->DirectoryClear($Dir);
    }


    public function TestIsDirectoryEmpty() {

        $F= new FileService;
        $Dir= __DIR__.'/tmp';

        // it is empty at begining
        $this->assertEqual($F->IsDirectoryEmpty($Dir), true);

        // add simple file
        touch($Dir.'/z.txt');
        $this->assertEqual($F->IsDirectoryEmpty($Dir), false);

        // remove that file, create sub-dir
        mkdir("$Dir/a/aa", 0777, true);
        $this->assertEqual($F->IsDirectoryEmpty($Dir), false);

        // clear
        $F->DirectoryClear($Dir);
        $this->assertEqual($F->IsDirectoryEmpty($Dir), true);
    }


    public function TestReadDirectory() {

        $F= new FileService;
        $Dir= __DIR__.'/tmp';

        // make 2 files in different depths
        mkdir("$Dir/a/aa", 0777, true);
        touch("$Dir/file1.txt");
        touch("$Dir/a/aa/file2.txt");
        touch("$Dir/file7.gif", time()-86400);
        touch("$Dir/.htaccess");

        // fetch list
        $A= $F->ReadDirectory($Dir);
        $this->assertEqual(is_array($A), true);
        $this->assertEqual($A, ['.htaccess','file1.txt','file7.gif']); // not recursive

        // test option "Mask"
        $A= $F->ReadDirectory($Dir, ['Mask'=> 'file?.*']);
        $this->assertEqual($A, ['file1.txt', 'file7.gif']);

        // test option "AllowFirstDot"
        $A= $F->ReadDirectory($Dir, ['AllowFirstDot'=> false]);
        $this->assertEqual($A, ['file1.txt', 'file7.gif']);

        // test option "AllowDirs"
        $A= $F->ReadDirectory($Dir, ['AllowDirs'=> true]);
        $this->assertEqual($A, ['.htaccess','a','file1.txt','file7.gif']); // including "a"

        // test option "AllowFiles"
        $A= $F->ReadDirectory($Dir, ['AllowFiles'=> false, 'AllowDirs'=> true]);
        $this->assertEqual($A, ["a"]);

        // test option "ModifiedOn"
        $A= $F->ReadDirectory($Dir, ['ModifiedOn'=> time()-3600]);
        $this->assertEqual($A, ['.htaccess','file1.txt']);
        $A= $F->ReadDirectory($Dir, ['ModifiedOn'=> -(time()-3600)]);
        $this->assertEqual($A, ['file7.gif']);

        // clear
        $F->DirectoryClear($Dir);
    }


    public function TestReadDirectoryRecursive() {

        $F= new FileService;
        $Dir= __DIR__.'/tmp';

        // make 2 files in different depths
        mkdir("$Dir/a/aa", 0777, true);
        touch("$Dir/file1.txt");
        touch("$Dir/a/aa/file2.txt");

        // fetch list
        $A= $F->ReadDirectoryRecursive($Dir);
        $this->assertEqual(is_array($A), true);
        $this->assertEqual($A, ['a/aa/file2.txt', 'file1.txt']);

        // clear
        $F->DirectoryClear($Dir);
    }


    public function TestDirectorySize() {

        $F= new FileService;
        $Dir= __DIR__.'/tmp';

        // make 2 files in different depths
        mkdir("$Dir/a/aa", 0777, true);
        file_put_contents("$Dir/file1.txt", 'abcd');
        file_put_contents("$Dir/a/aa/file2.txt", 'xyz');

        // calculate
        $A= $F->DirectorySize($Dir);

        // validate
        $this->assertEqual(is_integer($A), true);
        $this->assertEqual($A, 7);

        // clear
        $F->DirectoryClear($Dir);
    }


    public function TestResolveRelativePath() {

        $F= new FileService;

        // nothing to change
        $this->assertEqual($F->ResolveRelativePath('/var/www/index.php'), '/var/www/index.php');

        // resolve double slash
        $this->assertEqual($F->ResolveRelativePath('/var/www//index.php'), '/var/www/index.php');

        // resolve double dots
        $this->assertEqual($F->ResolveRelativePath('/var/www/../etc'),     '/var/etc');
        $this->assertEqual($F->ResolveRelativePath('/var/www/../../etc'),  '/etc');

        // handling backslashes
        $this->assertEqual($F->ResolveRelativePath('c:\\site\\..\\Windows'), 'c:/Windows');

        // preserving scheme
        $this->assertEqual($F->ResolveRelativePath('file://var//www/./inc'), 'file://var/www/inc');
    }


    public function TestCalcRelativePath() {

        $F= new FileService;

        // in same dir
        $this->assertEqual($F->CalcRelativePath('D:\\localhost\marco', 'D:/localhost'), 'marco');

        // directory relative to itself is emtpy string
        $this->assertEqual($F->CalcRelativePath('D:\\localhost', 'D:/localhost'), '');

        // two steps back
        $this->assertEqual($F->CalcRelativePath('/home/tt/www', '/home'), 'tt/www');

        // partially wrong relative
        $this->assertEqual($F->CalcRelativePath('/home/tt/www', '/home/cc'), '../tt/www');

        // completly wrong relative
        $this->assertEqual($F->CalcRelativePath('/home/tt/www', '/var/www'), '../../home/tt/www');
    }


    public function TestGMK_NiceValue() {

        $F= new FileService;
        $this->assertEqual($F->GMK_NiceValue(          1024),   '1 k');
        $this->assertEqual($F->GMK_NiceValue(          1025),   '1 k');
        $this->assertEqual($F->GMK_NiceValue(             1),     '1');
        $this->assertEqual($F->GMK_NiceValue(             0),     '0');
        $this->assertEqual($F->GMK_NiceValue(            -1),    '-1');
        $this->assertEqual($F->GMK_NiceValue(     1024*1024),   '1 M');
        $this->assertEqual($F->GMK_NiceValue(1024*1024*1024),   '1 G');
        $this->assertEqual($F->GMK_NiceValue(          1440), '1.4 k');  // feature
        $this->assertEqual($F->GMK_NiceValue(       4841904), '4.6 M');
        $this->assertEqual($F->GMK_NiceValue(      50931023),  '49 M');  // rounding 48.6M to 49M

        // more decimals
        $this->assertEqual($F->GMK_NiceValue(1440, 1),   '1.4 k');
        $this->assertEqual($F->GMK_NiceValue(1440, 2),  '1.41 k');
        $this->assertEqual($F->GMK_NiceValue(1440, 3), '1.406 k');

        // separator
        $this->assertEqual($F->GMK_NiceValue(1440, 0, ','),  '1,4 k');
        $this->assertEqual($F->GMK_NiceValue(1440, 2, ','), '1,41 k');
    }


    public function TestGMK_Packed() {

        $F= new FileService;
        $this->assertEqual($F->GMK_Packed(          1024),   '1k');
        $this->assertEqual($F->GMK_Packed(          1444),   '1k');
        $this->assertEqual($F->GMK_Packed(             1),    '1');
        $this->assertEqual($F->GMK_Packed(             0),    '0');
        $this->assertEqual($F->GMK_Packed(            -1),   '-1');
        $this->assertEqual($F->GMK_Packed(     1024*1024),   '1M');
        $this->assertEqual($F->GMK_Packed(1024*1024*1024),   '1G');
        $this->assertEqual($F->GMK_Packed(       4841904),   '4M');
        $this->assertEqual($F->GMK_Packed(      50931023),  '48M');     // truncating 48.6M to 48M
    }


    public function TestGMK_Integer() {

        $F= new FileService;
        $this->assertEqual($F->GMK_Integer('1'), 1);
        $this->assertEqual($F->GMK_Integer('0'), 0);
        $this->assertEqual($F->GMK_Integer(''), 0);
        $this->assertEqual($F->GMK_Integer('1k'), 1024);
        $this->assertEqual($F->GMK_Integer('1K'), 1024);
        $this->assertEqual($F->GMK_Integer('1M'), 1024*1024);
        $this->assertEqual($F->GMK_Integer('1m'), 1024*1024);
        $this->assertEqual($F->GMK_Integer('5.17k'), 5294);
    }


    public function TestGetMaxUpload() {

        // because ini_set('upload_max_filesize', '1M') and ini_set('post_max_size', '1M') has no effect
        // this method cannot be extensively tested
        $F= new FileService;
        $UMF= $F->GMK_Integer(ini_get('upload_max_filesize'));
        $PMS= $F->GMK_Integer(ini_get('post_max_size'));
        $this->assertEqual($F->GetMaxUpload(), min($UMF, $PMS));
    }


    public function TestMimeTypeByExt() {

        $F= new FileService;
        $this->assertEqual($F->MimeTypeByExt('readme.txt'), 'text/plain');
        $this->assertEqual($F->MimeTypeByExt('invoice.pdf'), 'application/pdf');
        $this->assertEqual($F->MimeTypeByExt('invoice.odt'), 'application/vnd.oasis.opendocument.text');
        $this->assertEqual($F->MimeTypeByExt('screenshot.jpg'), 'image/jpeg');
        $this->assertEqual($F->MimeTypeByExt('setup.exe'), 'application/vnd.microsoft.portable-executable');
    }


}


