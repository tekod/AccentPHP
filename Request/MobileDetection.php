<?php namespace Accent\Request;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * This class is adapter for /Request/Lib/MobileDetect/Mobile_Detect.php
 * from: https://github.com/serbanghita/Mobile-Detect
 */

use Accent\AccentCore\Component;


class MobileDetection extends Component {


    // cached value of detection
    protected $Detected;


    /**
     * Main method, return true if current request comes from mobile device.
     *
     * @return bool
     */
    public function IsMobile() {

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

        // instantiate library class
        include_once __DIR__.'/Lib/MobileDetect/Mobile_Detect.php';
        $Detector= new \Mobile_Detect($Server);

        // execute detection
        $this->Detected= $Detector->isMobile();
    }

}


?>