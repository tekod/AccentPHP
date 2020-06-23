<?php namespace Accent\AccentCore\Test;

use Accent\Test\AccentTestCase;
use Accent\AccentCore\AutoLoader\AutoLoader;

/**
 * Testing AutoLoader component
 */

class Test__Accent_AutoLoader extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Autoloader component test';

    // title of testing group
    const TEST_GROUP= 'AccentCore';

    // AutoLoader instance
    protected $AL;


    public function TestLoadingNamespace() {

        $AL= new AutoLoader;
        $AL->AddRule('Namespace', 'Namespaced', __DIR__.'/TestClasses/Namespaced');
        $Tests= array('Namespaced\\Alpha', 'Namespaced\\Deeper\\Beta');
        foreach($Tests as $Key=>$Class) {
            $Result= $AL->Load($Class);
            $this->assertEqual($Result, true, $Class);
            if ($Result === null) {
                continue;
            }
            // check class_exists (without autoloading option)
            $this->assertEqual(class_exists($Class,false), true, $Class);
            // test using these classes
            $this->assertEqual(call_user_func(array($Class,'Something')), $Key+1, $Class);
        }
    }


    public function TestLoadingPrefix() {

        $AL= new AutoLoader;
        $AL->AddRule('Prefix', 'Prefixed', __DIR__.'/TestClasses/Prefixed');
        $AL->AddRule('Prefix', 'Prefixed_Deeper', __DIR__.'/TestClasses/Prefixed/Deeper');
        $Tests= array('Prefixed_Alpha', 'Prefixed_Deeper_Beta');
        foreach($Tests as $Key=>$Class) {
            $Result= $AL->Load($Class);
            $this->assertEqual($Result, true, $Class);
            if ($Result === null) {
                continue;
            }
            // check class_exists (without autoloading option)
            $this->assertEqual(class_exists($Class,false), true, $Class);
            // test using these classes
            $this->assertEqual(call_user_func(array($Class,'Something')), $Key+1, $Class);
        }
    }


    public function TestLoadingUnderscore() {

        $AL= new AutoLoader;
        $AL->AddRule('Underscore', 'Underscore', __DIR__.'/TestClasses/Underscored');
        $Tests= array('Underscored_Alpha', 'Underscored_Deeper_Beta');
        foreach($Tests as $Key=>$Class) {
            $Result= $AL->Load($Class);
            $this->assertEqual($Result, true, $Class);
            if ($Result === null) {
                continue;
            }
            // check class_exists (without autoloading option)
            $this->assertEqual(class_exists($Class,false), true, $Class);
            // test using these classes
            $this->assertEqual(call_user_func(array($Class,'Something')), $Key+1, $Class);
        }
    }


    public function TestLoadingCamelCase() {

        $AL= new AutoLoader;
        $AL->AddRule('CamelCase', 'Camel', __DIR__.'/TestClasses/CamelCased');
        $Tests= array('CamelAlpha', 'CamelDeeperBeta');
        foreach($Tests as $Key=>$Class) {
            $Result= $AL->Load($Class);
            $this->assertEqual($Result, true, $Class);
            if ($Result === null) {
                continue;
            }
            // check class_exists (without autoloading option)
            $this->assertEqual(class_exists($Class,false), true, $Class);
            // test using these classes
            $this->assertEqual(call_user_func(array($Class,'Something')), $Key+1, $Class);
        }
    }


    public function TestFileCaching() {

        $CachePath= __DIR__.'/CachedRegistry.php';
        $AL= new AutoLoader(array('Cache'=>array('Method'=>'File','Path'=>$CachePath)));
        $AL->AddRule('Namespace', 'Cached1', __DIR__.'/TestClasses/Cached1');
        $AL->Load('Cached1\\Deeper\\Beta');
        $AL->Shutdown();   // trick to execute storing registry
        $T= include $CachePath;
        $this->assertEqual(isset($T['Cached1\\Deeper\\Beta']), true);
        // test clear cache
        $AL->ClearCache(false);
        $T= include $CachePath;
        $this->assertEqual(isset($T['Cached1\\Deeper\\Beta']), false);
    }


    public function TestApcCaching() {

        if (!extension_loaded('apc')) {
            return;
        }
        $ApcKey= 'Accent/Autoload/Test';
        $AL= new AutoLoader(array('Cache'=>array('Method'=>'APC','Path'=>$ApcKey)));
        $AL->AddRule('Namespace', 'Cached2', __DIR__.'/TestClasses/Cached2');
        $AL->Load('Cached2\\Deeper\\Beta');
        $AL->Shutdown();
        $T= apc_fetch($ApcKey);
        $this->assertEqual(isset($T['Cached2\\Deeper\\Beta']), true);
        // test clear cache
        $AL->ClearCache(false);
        $T= apc_fetch($ApcKey);
        $this->assertEqual(isset($T['Cached1\\Deeper\\Beta']), false);
    }


    public function TestCustomProcessor() {

        $AL= new AutoLoader;
        $AL->RegisterProcessor('MyProcessor', array(__CLASS__,'MyCustomProcessor'));
        $AL->AddRule('MyProcessor', 'Custom', __DIR__.'/TestClasses/Custom');
        $Tests= array('Custom\\Alpha', 'Custom\\Deeper\\Beta');
        foreach($Tests as $Class) {
            $Result= $AL->Load($Class);
            $this->assertEqual($Result, true, $Class);
        }
    }


    public static function MyCustomProcessor($ClassName, &$Rules) {
        // modified version of 'Namespace' processor, adding '.class.' at end of file name
        $Parts= explode('\\', $ClassName);
        $RelativePath= '.class.php';
        while ($Parts) {
            $RelativePath= DIRECTORY_SEPARATOR.array_pop($Parts).$RelativePath;
            $nsPrefix= implode('\\', $Parts);
            if (!isset($Rules[$nsPrefix])) {
                continue;
            }
            foreach ($Rules[$nsPrefix] as $Dir) {
                $FilePath= $Dir.$RelativePath;
                if (is_file($FilePath)) {
                    return $FilePath;
                }
            }
        }
        return false;
    }

}

