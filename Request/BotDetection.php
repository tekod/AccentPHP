<?php namespace Accent\Request;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * This class is adapter for /Request/Lib/CrawlerDetect/CrawlerDetect.php
 * from: https://github.com/JayBizzle/Crawler-Detect
 */

use Accent\AccentCore\Component;
use Jaybizzle\CrawlerDetect\CrawlerDetect;


class BotDetection extends Component {


    // cached value of detection
    protected $Detected;


    /**
     * Main method, return true if current request comes from robot.
     *
     * @return bool
     */
    public function IsBot() {

        if ($this->Detected === null) {
            $this->Detect();
        }
        return $this->Detected;
    }


    /**
     * Perform detection.
     * Store result into $this->Detected.
     */
    protected function Detect() {

        // get $_SERVER array from context
        $Server= $this->GetRequestContext()->SERVER;

        // autoloader
        $this->GetService('Autoloader')->AddRule('Namespace', 'Jaybizzle', __DIR__.'/Lib');
        
        // instantiate library class
        include_once __DIR__.'/Lib/CrawlerDetect/CrawlerDetect.php';
        $Detector= new CrawlerDetect($Server);

        // execute detection
        $this->Detected= $Detector->isCrawler();
    }

}


?>