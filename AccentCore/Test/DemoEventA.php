<?php namespace Accent\AccentCore\Test;

/**
 * Demo Event A
 *
 * This event contains one data-property (with its getter and setter).
 */

use Accent\AccentCore\Event\BaseEvent;


class DemoEventA extends BaseEvent {


    protected $Subject= '';


    public function SetSubject($String) {
        $this->Subject= $String;
    }


    public function GetSubject() {
        return $this->Subject;
    }
}

