<?php namespace Accent\AccentCore\Event;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

use \Accent\AccentCore\Component;


class BaseEvent extends Component {


    private $IsHandled= false;
    protected $Context= [];


    public function IsHandled() {

        return $this->IsHandled;
    }

    public function SetHandled() {

        $this->IsHandled= true;
    }

    public function SetContext(array $Context) {

        $this->Context= $Context;
    }


    public function GetContext($Name=null) {

         return $Name === null
            ? $this->Context
            : (isset($this->Context[$Name]) ? $this->Context[$Name] : null);
    }

}
