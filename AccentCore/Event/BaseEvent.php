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


    public function IsHandled() {

        return $this->IsHandled;
    }

    public function SetHandled() {

        $this->IsHandled= true;
    }


}
