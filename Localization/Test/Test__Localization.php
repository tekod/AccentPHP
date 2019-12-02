<?php namespace Accent\Localization\Test;

/**
 * Testing localization service.
 */

use Accent\Test\AccentTestCase;
use Accent\Localization\Localization;


class Test__Localization extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Localization service test';

    // title of testing group
    const TEST_GROUP= 'Localization';


    protected function BuildOptions() {

        return array(
            'DefaultBook'=> 'main',  // first translations will be loaded from 'main.php'
            'LoaderConfigs'=> array( // prepare configuration for loader which will be invoked
                'php1'=> array(         // first PHP loader
                    'LoaderClass'=> 'PHP',        // or YAML, INI, JSON, XML, DB
                    'Directories'=> array(   // list of full paths where to search for books
                        __DIR__.'/lang',
                        __DIR__.'/somemodule_lang',
                    ),
                ),
                'php2'=> array(         // second PHP loader, with different settings
                    'LoaderClass'=> 'PHP',
                    'Directories'=> array(), // ...
                ),
                'ini1'=> array(         // INI loader
                    'LoaderClass'=> 'INI',
                    'Directories'=> array(__DIR__.'/lang'),
                ),
                'db1'=> array(          // database loader
                    'LoaderClass'=> 'DB',
                    'Table'=> 'translations_table',     // name of table for SQL query
                    'Fields'=> array('code','lang','book','message'), // name of columns for SQL query
                    'Wheres'=> array(array('published','=','1')), // array of Where definitions
                ),
                'Grouped'=> array(      // for tests of grouped translations
                ),                      // will be overwritten in each testcase
            ),
            'Books'=> array(
                'ini'=> 'INI1',
            ),
            'DefaultLoader'=> 'php1', // which loader to use for books not listed in Books
            'LangAliases'=> array(  // aliases
                null=> 'sr',
                '@'=> 'en',
                'q'=> null,
            ),
            'Services'=> array(
                'ArrayUtils'=> new \Accent\AccentCore\ArrayUtils\ArrayUtils,
                'Event'=> new \Accent\AccentCore\Event\EventService,
                'UTF'=> new \Accent\AccentCore\UTF\UTF,
            ),
        );
    }




    protected $TestingStrings= array(
        'Simple' => 'simple',
        "'Quoted'" => "'Quotes'",
        'MultiLine' => "First\nSecond",
        'UTF8' => 'Košta 1€',
    );



    // TESTS:

    public function TestTranslating() {

        $Options= $this->BuildOptions();
        $L= new Localization($Options);

        // test simple translation in pre-configured frontend language
        // should be found in ./test/lang/sr/main.php
        $Msg= $L->Translate('FirstDemo');
        $this->assertEqual($Msg, 'Ovo je demo.');

        // test translation in pre-configured backend language
        $Msg= $L->Translate('FirstDemo', null, '@');
        $this->assertEqual($Msg, 'This is demo.');

        // test translation in specified language
        $Msg= $L->Translate('FirstDemo', null, 'en');
        $this->assertEqual($Msg, 'This is demo.');

        // test fallback to backend language
        // should be found in ./test/lang/en/main.php
        $Msg= $L->Translate('BackendOnlyString');
        $this->assertEqual($Msg, 'QQ');

        // test unknown translation
        $Msg= $L->Translate('x.y.z');
        $this->assertEqual($Msg, 'x.y.z');

        // test replacing
        $Msg= $L->Translate('ReplacingDemo', null, 'en', array('Nikola'));
        $this->assertEqual($Msg, 'Hi Nikola');

        // test another book
        $Msg= $L->Translate('SecondDemo#book2', null, 'en');
        $this->assertEqual($Msg, 'This is second demo.');

        // test overriding translation from file in external module which target main book
        // 'B' should be loaded from ./test/somemodule_lang/en/main.php
        $Msg= $L->Translate('OverridingString', null, 'en');
        $this->assertEqual($Msg, 'B');

        // test book from external module
        $Msg= $L->Translate('ModuleBookOnlyString#book3');
        $this->assertEqual($Msg, '123');

        // test modifier '|'
        // replacers are not applied, this should be used for lists not for sentencies
        $Msg= $L->Translate('Piped', '|', null, 'Ana');
        $this->assertEqual($Msg[0], 'I am %s');

        // test modifier integer for '|'
        // replacing is applied becouse string-type was requested
        $Msg= $L->Translate('Piped', '2', null, 'Ana');
        $this->assertEqual($Msg, 'You are Ana');
    }


    public function TestGroupedFiles() {

        $LocalOptions= $this->BuildOptions();
        $LocalOptions['LoaderConfigs']['php1']['Directories']= array(); // clear main book
        $LocalOptions['Books'] += array('Months'=>'Grouped', 'DaysOfWeek'=>'Grouped');

        // test AllBooks option
        $LocalOptions['LoaderConfigs']['Grouped']= array(
            'LoaderClass'=> 'PHP',
            'Files'=> array(__DIR__.'/grouping/all-books.php',),
            'AllBooks'=> true,
            'AllLanguages'=> false,
        );
        $L= new Localization($LocalOptions);
        $Msg= $L->Translate('1#Months');
        $this->assertEqual($Msg, 'January');

        // test AllLanguages and AllBooks options together
        $LocalOptions['LoaderConfigs']['Grouped']= array(
            'LoaderClass'=> 'PHP',
            'Files'=> array(__DIR__.'/grouping/all-langs-and-books.php',),
            'AllBooks'=> true,
            'AllLanguages'=> true,
        );
        $L= new Localization($LocalOptions);
        $Msg= $L->Translate('1#DaysOfWeek', null, 'en');
        $this->assertEqual($Msg, 'Monday');

        // test AllLanguages option alone
        // this combination assume that translations will be loaded into default book
        $LocalOptions['LoaderConfigs'][$LocalOptions['DefaultLoader']]= array(
            'LoaderClass'=> 'PHP',
            'Files'=> array(__DIR__.'/grouping/all-langs.php',),
            'AllBooks'=> false,
            'AllLanguages'=> true,
        );
        $L= new Localization($LocalOptions);
        $Msg= $L->Translate('1', null, 'en');
        $this->assertEqual($Msg, 'January');
    }


    public function TestLoaderIni() {

        $Options= $this->BuildOptions();
        $Options['LoaderConfigs'][$Options['DefaultLoader']]= array(
            'LoaderClass'=> 'INI',
            'Directories'=> array(__DIR__.'/lang'),
        );
        $L= new Localization($Options);
        foreach($this->TestingStrings as $k=>$v) {
            $Msg= $L->Translate($k);
            $Msg= str_replace("\r","",$Msg); // compensate EOL variations
            $this->assertEqual($Msg, $v);
        }
    }


    public function TestLoaderYaml() {

        $Options= $this->BuildOptions();
        $Options['LoaderConfigs'][$Options['DefaultLoader']]= array(
            'LoaderClass'=> 'YAML',
            'Directories'=> array(__DIR__.'/lang'),
        );
        $L= new Localization($Options);
        foreach($this->TestingStrings as $k=>$v) {
            $Msg= $L->Translate($k);
            $Msg= str_replace("\r","",$Msg); // compensate EOL variations
            $this->assertEqual($Msg, $v);
        }
        //echo'<br><br>Tables: '.var_export($L->GetTables(),true);
    }


    public function TestLoaderJSON() {

        $Options= $this->BuildOptions();
        $Options['LoaderConfigs'][$Options['DefaultLoader']]= array(
            'LoaderClass'=> 'JSON',
            'Directories'=> array(__DIR__.'/lang'),
        );
        $L= new Localization($Options);
        foreach($this->TestingStrings as $k=>$v) {
            $Msg= $L->Translate($k);
            $this->assertEqual($Msg, $v);
        }
        //echo'<br><br>Tables: '.var_export($L->GetTables(),true);
    }


    public function TestLoaderXML() {

        $Options= $this->BuildOptions();
        $Options['LoaderConfigs'][$Options['DefaultLoader']]= array(
            'LoaderClass'=> 'XML',
            'Directories'=> array(__DIR__.'/lang'),
        );
        $L= new Localization($Options);
        foreach($this->TestingStrings as $k=>$v) {
            if (substr($k,0,1)=="'") continue; // unsupported in XML
            $Msg= $L->Translate($k);
            $this->assertEqual($Msg, $v);
        }
        //echo'<br><br>Tables: '.var_export($L->GetTables(),true);
    }


/*
    function testLoaderDB() {

        $Options= $this->BuildOptions();
        $Options['LoaderConfigs'][$Options['DefaultLoader']]= array(
            'LoaderClass'=> 'DB',
            'Table'=> 'translations_table',     // name of table for SQL query
            'Fields'=> array('code','lang','book','message'), // name of columns for SQL query
            'Wheres'=> array(array('published','=','1')), // array of Where definitions
        );
        $L= new Localization($Options);
        foreach($this->TestingStrings as $k=>$v) {
            $Msg= $L->Translate($k);
            $this->assertEqual($Msg, $v);
        }
        //echo'<br><br>Tables: '.var_export($L->GetTables(),true);
    }
    */


    public function TestMultipleLoaders() {

        $Options= $this->BuildOptions();
        // make existing php1 and ini1 loaders points at same place
        $Options['LoaderConfigs']['php1']['Directories']= '@Dir/multiple_loading';
        $Options['LoaderConfigs']['ini1']['Directories']= '@Dir/multiple_loading';
        $Options['LangFilesRootDir']= __DIR__;   // also testing 'LangFilesRootDir' option
        // specify loader as array, order does matter
        // we could use $Options['DefaultLoader']=array(...) as well
        $Options['Books']['main']= array('php1','ini1');
        // perform tests
        $L= new Localization($Options);
        $this->assertEqual($L->Translate('Demo1'), 'first'); // Demo1 is not overwritten
        $this->assertEqual($L->Translate('Demo2'), '2nd');  // rest is overwritten
        $this->assertEqual($L->Translate('Demo3'), '3rd');
    }


    public function TestSetTranslation() {

        $Options= $this->BuildOptions();
        $L= new Localization($Options);
        $Msg= $L->Translate('FirstDemo');
        $this->assertEqual($Msg, 'Ovo je demo.');
        $NewTranslation= 'ABCD';
        $L->SetTranslation('FirstDemo', 'en', null, $NewTranslation);
        $this->assertEqual($L->Translate('FirstDemo'), 'Ovo je demo.');
        $this->assertEqual($L->Translate('FirstDemo',null,'en'), $NewTranslation);
        $this->assertEqual($L->Translate('ReplacingDemo',null,'en',array('Ann')), 'Hi Ann');
    }


    public function TestRegisterLoader() {

        $Options= $this->BuildOptions();
        $L= new Localization($Options);
        // normal usage
        $this->assertEqual($L->Translate('FirstDemo'), 'Ovo je demo.');
        $Book= 'Months';
        $LoaderName= 'NewLoader';
        $LoaderConfig= array(
            'LoaderClass'=> 'PHP',
            'Files'=> __DIR__.'/grouping/all-langs-and-books.php', //string instead of array
            'AllBooks'=> true,
            'AllLanguages'=> true,
        );
        // configure new loader and specify loader for $Book
        $L->RegisterLoader($LoaderName, $LoaderConfig);
        $L->RegisterBook($Book, $LoaderName);
        $this->assertEqual($L->Translate('2#Months',null,'en'), 'February');
    }


    public function TestSetBookRedirection() {

        $Options= $this->BuildOptions();
        $Options['Books'] += array('Months'=>'Grouped', 'DaysOfWeek'=>'Grouped');
        $Options['LoaderConfigs']['Grouped']= array(
            'LoaderClass'=> 'PHP',
            'Files'=> __DIR__.'/grouping/all-langs-and-books.php',
            'AllBooks'=> true,
            'AllLanguages'=> true,
        );
        $L= new Localization($Options);
        // normal usages
        $this->assertEqual($L->Translate('FirstDemo'), 'Ovo je demo.');
        $this->assertEqual($L->Translate('2#Months',null,'en'), 'February');
        // redirect and test
        $Redirection= $L->SetTemporaryBookRedirection('Months','DaysOfWeek');
        $this->assertEqual($L->Translate('2#Months',null,'en'), 'Tuesday'); // VIOLA
        // clear redirection and test normal translation
        $Redirection=null;
        $this->assertEqual($L->Translate('2#Months',null,'en'), 'February');
    }


    public function TestNumberChoicePattern() {

        $Options= $this->BuildOptions();
        $L= new Localization($Options);
        // test simple integer number
        $L->SetTranslation('Test1','en',null,'[2]AA|[3]BBB|[4]CCCC|[5]DDDDD');
        $this->assertEqual($L->Translate('Test1','=2','en',null), 'AA');
        $this->assertEqual($L->Translate('Test1','=5','en',null), 'DDDDD');
        $this->assertEqual($L->Translate('Test1','=9','en',null), 'DDDDD'); // not found
        // test simple expressions
        $L->SetTranslation('Test1','en',null,'[n==2]AA|[n>3]BBB|[n>8]CCCC|[n>9]DDDDD');
        $this->assertEqual($L->Translate('Test1','=2','en',null), 'AA');
        $this->assertEqual($L->Translate('Test1','=9','en',null), 'BBB'); // first match
        // test complex expressions
        $L->SetTranslation('Test1','en',null,'[n==2]FIRST|[n%10==4&&(n+2)*2==32]YES|[1]NO');
        $this->assertEqual($L->Translate('Test1','= 4','en',null), 'NO');
        $this->assertEqual($L->Translate('Test1','=14','en',null), 'YES');
        $this->assertEqual($L->Translate('Test1','=15','en',null), 'NO');
        $this->assertEqual($L->Translate('Test1','=24','en',null), 'NO');
    }


    public function TestPluralizationChoicePattern() {

        $Options= $this->BuildOptions();
        $L= new Localization($Options);
        $L->SetTranslation('PluralizationRule','en',null,'1');
        $L->SetTranslation('PluralizationRule','sr',null,'7');
        // test simple pluralization rule (english language)
        $L->SetTranslation('Test1','en',null,'now|%s hour ago|%s hours ago');
        $this->assertEqual($L->Translate('Test1','*0','en',0), 'now');
        $this->assertEqual($L->Translate('Test1','*1','en',1), '1 hour ago');
        $this->assertEqual($L->Translate('Test1','*7','en',7), '7 hours ago');
        // test complex pluralization rule (serbian language)
        $L->SetTranslation('Test1','sr',null,'sada|pre %s sat|pre %s sata|pre %s sati');
        $this->assertEqual($L->Translate('Test1','*0','sr',0), 'sada');
        $this->assertEqual($L->Translate('Test1','*1','sr',1), 'pre 1 sat');
        $this->assertEqual($L->Translate('Test1','*2','sr',2), 'pre 2 sata');
        $this->assertEqual($L->Translate('Test1','*4','sr',4), 'pre 4 sata');
        $this->assertEqual($L->Translate('Test1','*5','sr',5), 'pre 5 sati');
        $this->assertEqual($L->Translate('Test1','*11','sr',11), 'pre 11 sati');
        $this->assertEqual($L->Translate('Test1','*14','sr',14), 'pre 14 sati');
        $this->assertEqual($L->Translate('Test1','*21','sr',21), 'pre 21 sat');
        $this->assertEqual($L->Translate('Test1','*22','sr',22), 'pre 22 sata');
        $this->assertEqual($L->Translate('Test1','*24','sr',24), 'pre 24 sata');
        $this->assertEqual($L->Translate('Test1','*25','sr',25), 'pre 25 sati');
        $this->assertEqual($L->Translate('Test1','*31','sr',31), 'pre 31 sat');
        $this->assertEqual($L->Translate('Test1','*32','sr',32), 'pre 32 sata');
        // test incomplete number of translations, should return last supplied translation
        $L->SetTranslation('Test1','sr',null,'sada|pre %s sat|pre %s sata');
        $this->assertEqual($L->Translate('Test1','*57','sr',57), 'pre 57 sata');
        // test zero case without supplied zero-case-translation
        $L->SetTranslation('Test1','sr',null,'|pre %s sat|pre %s sata|pre %s sati');
        $this->assertEqual($L->Translate('Test1','*0','sr',0), 'pre 0 sati');
    }


    public function TestPackedMsgCode() {

        $Options= $this->BuildOptions();
        $L= new Localization($Options);
        // test index number
        $L->SetTranslation('Days','en',null,'Mon|Tue|Wed|Thu|Fri|Sat|Sun');
        $this->assertEqual($L->Translate('Days#main#2'), 'Tue');
        // packed with book
        $L->SetTranslation('Days','en','b2','Mo|Tu|We|Th|Fr|Sa|Su');
        $this->assertEqual($L->Translate('Days#b2#2'), 'Tu');
        // packed with number-choice pattern
        $L->SetTranslation('T1','en',null,'[2]AA|[3]BB|[4]CC|[5]DD');
        $this->assertEqual($L->Translate('T1#main#=3'), 'BB');
    }


    public function TestDates() {

        $Options= $this->BuildOptions();
        $Options['LoaderConfigs']['php1']['Directories']= array(__DIR__.'/formating');
        $L= new Localization($Options);
        $Timestamp= mktime(19, 15, 2, 3, 24, 1999);
        $Datestamp= mktime(0, 0, 0, 3, 24, 1999); // Timestamp without hours,minutes,seconds
        // simple case
        $fDate= $L->FormatDate($Timestamp, Localization::FULL_DATE, 'en');
        $this->assertEqual($fDate, 'Wednesday, March 24, 1999');
        // translated
        $fDate= $L->FormatDate($Timestamp, Localization::FULL_DATE, 'sr');
        $this->assertEqual($fDate, 'Sreda, 24. Mart 1999');
        // shorter format
        $fDate= $L->FormatDate($Timestamp, Localization::MEDIUM_DATE, 'en');
        $this->assertEqual($fDate, 'Mar 24, 1999');
        // shortest format
        $fDate= $L->FormatDate($Timestamp, Localization::SHORT_DATE, 'en');
        $this->assertEqual($fDate, '03/24/99');
        // custom format
        $L->SetDateFormat(Localization::MEDIUM_DATE, 'D, m/d/Y', 'en');
        $fDate= $L->FormatDate($Timestamp, Localization::MEDIUM_DATE, 'en');
        $this->assertEqual($fDate, 'Wed, 03/24/1999');
        // complex format
        $L->SetDateFormat(Localization::MEDIUM_DATE, '\T\o\d\a\y: <\i>D</\i>, m/d/y', 'en');
        $fDate= $L->FormatDate($Timestamp, Localization::MEDIUM_DATE, 'en');
        $this->assertEqual($fDate, 'Today: <i>Wed</i>, 03/24/99');
        // UnFormatDate date
        $Time= $L->UnFormatDate('03/24/99', Localization::SHORT_DATE, 'en');
        $this->assertEqual($Time, $Datestamp);
        // complex format
        $L->SetDateFormat(Localization::MEDIUM_DATE, '\T\o\d\a\y\: \<\i\>D\<\/\i\>\, m/d/Y', 'en');
        $Time= $L->UnFormatDate('Today: <i>Wed</i>, 03/24/1999', Localization::MEDIUM_DATE, 'en');
        $this->assertEqual($Time, $Datestamp);
        // test TimeOffset
        $L->SetDateFormat(Localization::MEDIUM_DATE, 'm/d/y H:i', 'en');
        $fDate= $L->FormatDate($Timestamp, Localization::MEDIUM_DATE, 'en');
        $this->assertEqual($fDate, '03/24/99 19:15');
        $L->SetTimeOffset(8*3600); // time-zone = +8 hours
        $fDate= $L->FormatDate($Timestamp, Localization::MEDIUM_DATE, 'en');
        $this->assertEqual($fDate, '03/25/99 03:15'); // 03:15 on next day
    }



    public function TestTimeAgo() {

        $Options= $this->BuildOptions();
        $Options['LoaderConfigs']['php1']['Directories']= array(__DIR__.'/formating');
        $L= new Localization($Options);
        $Cases= array( // testing in serbian to confirm complex pluralization of time units
            0=> 'pre 0 sekundi',        1=> 'pre 1 sekund',
            2=> 'pre 2 sekunde',        60=> 'pre 1 minut',
            61=> 'pre 1 minut',         120=> 'pre 2 minute',
            183=> 'pre 3 minute',       240=> 'pre 4 minute',
            300=> 'pre 5 minuta',       660=> 'pre 11 minuta',
            720=> 'pre 12 minuta',      1260=> 'pre 21 minut',
            1320=> 'pre 22 minute',     1500=> 'pre 25 minuta',
            3600=> 'pre 1 sat',         7200=> 'pre 2 sata',
            86400=> 'pre 1 dan',        3*86400=> 'pre 3 dana',
            37*86400=> 'pre 1 mesec',   7*30*86400=> 'pre 7 meseci',
            365*86400=> 'pre 1 godinu', 2*365*86400=> 'pre 2 godine',
        );
        foreach($Cases as $k=>$v) {
            $this->assertEqual($L->FormatTimeInterval($k), $v);
        }
    }


    public function TestNumbers() {
        $Options= $this->BuildOptions();
        $Options['LoaderConfigs']['php1']['Directories']= array(__DIR__.'/formating');
        $L= new Localization($Options);

        $this->assertEqual($L->FormatNumber(4,2,'en'), '4.00');
        $this->assertEqual($L->FormatNumber(4,2,'sr'), '4,00');
        $this->assertEqual($L->FormatNumber(4.6,2,'en'), '4.60');
        $this->assertEqual($L->FormatNumber(4.6,2,'sr'), '4,60');
        $this->assertEqual($L->FormatNumber(3000,0,'en'), '3,000');
        $this->assertEqual($L->FormatNumber(3000,0,'sr'), '3.000');
        $this->assertEqual($L->FormatNumber(3000.51,2,'en'), '3,000.51');
        $this->assertEqual($L->FormatNumber(3000.51,2,'sr'), '3.000,51');

        $this->assertEqual($L->UnFormatNumber('4','en'), 4);
        $this->assertEqual($L->UnFormatNumber('4','sr'), 4);
        $this->assertEqual($L->UnFormatNumber('4.7','en'), 4.7);
        $this->assertEqual($L->UnFormatNumber('4,7','sr'), 4.7);
        $this->assertEqual($L->UnFormatNumber('4,7','en'), 47); // becouse ',' is ThoSeparator
        $this->assertEqual($L->UnFormatNumber('4.7','sr'), 47); // becouse '.' is ThoSeparator
        $this->assertEqual($L->UnFormatNumber('4000','en'), 4000);
        $this->assertEqual($L->UnFormatNumber('4000','sr'), 4000);
        $this->assertEqual($L->UnFormatNumber('4,000','en'), 4000);
        $this->assertEqual($L->UnFormatNumber('4.000','sr'), 4000);
        $this->assertEqual($L->UnFormatNumber('4.000','en'), 4);
        $this->assertEqual($L->UnFormatNumber('4,000','sr'), 4);
    }


    public function TestDetectLanguageFromBrowser() {

        $Options= $this->BuildOptions();
        $L= new Localization($Options);
        $Header= 'en-ca,en;q=0.8,en-us;q=0.6,de-de;q=0.4,de;q=0.2';
        $this->assertEqual($L->DetectLanguageFromBrowser(array('en'),$Header),'en');
        $this->assertEqual($L->DetectLanguageFromBrowser(array('xy'),$Header),'xy');
        $this->assertEqual($L->DetectLanguageFromBrowser(array('de'),$Header),'de');
        $this->assertEqual($L->DetectLanguageFromBrowser(array('en'),$Header),'en');
        $this->assertEqual($L->DetectLanguageFromBrowser(array('de','en'),$Header),'en');
        $this->assertEqual($L->DetectLanguageFromBrowser(array('de','xy'),$Header),'de');
    }

}


?>