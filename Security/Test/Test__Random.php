<?php namespace Accent\Security\Random\Test;

use Accent\Test\AccentTestCase;
use Accent\Security\Random\Random;


class Test__Random extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'Random service test';

    // title of testing group
    const TEST_GROUP= 'Security';


    public function __construct() {

        // parent
        parent::__construct();

        // clear old images
        foreach(glob(__DIR__."/tmp/rand-images/*.png") as $File) {
            unlink($File);
        }
    }



    protected function Build($GeneratorName=null) {

        if ($GeneratorName === null) {
            $Options= array();
        } else {
            $Options= array(
                'Generators'=> array(
                    null=>null, // clear collection
                    '\\Accent\\Security\\Random\\RandomGenerator\\'.$GeneratorName
                )
            );
        }
        return new Random($Options);
    }


    // TESTS:

    public function TestEachGenerator() {

        set_time_limit(10*60);
        $Names= array('DevURandom','OpenSSL','Rand','UniqId', 'RandomBytes');

        if (PHP_VERSION_ID >= 70200 || DIRECTORY_SEPARATOR !== '\\') {
            $this->WarningMessage('Random: DevURandom generator cannot work on Windows OS since PHP 7.2.');
        }
        foreach($Names as $Name) {
            $R= $this->Build($Name);
            $Bytes= $R->GetRandomBytes(8000, true);
            $this->CheckDistribution($Bytes, $Name);
            $this->DrawImage($Bytes, $Name);
        }
    }


    public function TestMixedSources() {

        set_time_limit(10*60);
        $R= $this->Build();
        // test mixed all weak and normal generators
        $Name= 'mixed-normal';
        $Bytes= $R->GetRandomBytes(8000);
        if ($Bytes === false || $Bytes === '') {
            $this->assertTrue(false, 'Random: Generating using '.$Name.' returned false.');
        } else {
            $this->CheckDistribution($Bytes, $Name);
            $this->DrawImage($Bytes, $Name);
        }

        // test mixed all generators
        $Name= 'mixed-strong';
        $Bytes= $R->GetRandomBytes(8000, true);
        if ($Bytes === false || $Bytes === '') {
            $this->assertTrue(false, 'Random: Generating using '.$Name.' returned false.');
        } else {
            $this->CheckDistribution($Bytes, $Name);
            $this->DrawImage($Bytes, $Name);
        }
    }


    // HELPERS:

    protected function DrawImage($Bytes, $Name) {

        $gd= imagecreatetruecolor(256, 256);
        $white= imagecolorallocate($gd, 255, 255, 255);

        for ($i= strlen($Bytes); $i > 1; $i=$i-2) {
            imagesetpixel($gd, ord($Bytes[$i-1]),ord($Bytes[$i-2]), $white);
        }
        if (!is_dir(__DIR__.'/tmp/rand-images')) {
            mkdir(__DIR__.'/tmp/rand-images', 0777, true);
        }
        imagepng($gd, __DIR__.'/tmp/rand-images/'.$Name.'.png');
    }


    protected function CheckDistribution($Bytes, $GeneratorName) {

        $Bytes= unpack('C*',$Bytes);
        $ErrorMsg= 'Random: %s generator has not enough distribution (%s:%s).';
        // avg value from first block
        $Block1= array_slice($Bytes,0,25);
        $Avg= array_sum($Block1) / count($Block1);        //$this->WarningMessage($Middle);
        $Deviation= $Avg-127;
        $this->assertTrue(abs($Deviation) < 50, sprintf($ErrorMsg, $GeneratorName, 'A', $Deviation));
        // avg value from last block
        $Block2= array_slice($Bytes,-25);
        $Avg= array_sum($Block2) / count($Block2);        //$this->WarningMessage($Middle);
        $Deviation= $Avg-127;
        $this->assertTrue(abs($Deviation) < 50, sprintf($ErrorMsg, $GeneratorName, 'B', $Deviation));
        // whole array
        $Avg= array_sum($Bytes) / count($Bytes);        //$this->WarningMessage($Middle);
        $Deviation= $Avg-127;
        $this->assertTrue(abs($Deviation) < 5, sprintf($ErrorMsg, $GeneratorName, 'C', $Deviation));
     }

}


?>