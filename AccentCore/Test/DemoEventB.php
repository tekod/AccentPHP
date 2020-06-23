<?php namespace Accent\AccentCore\Test;

/**
 * Demo Event B
 *
 * This event contains:
 *  - counter to trace how many times listeners was pinged it
 *  - contructor option to setup inital value of counter
 */

use Accent\AccentCore\Event\BaseEvent;


class DemoEventB extends BaseEvent {


    protected $Counter;


    public function __construct($Options=[]) {

        parent::__construct($Options);

        // extract property
        $this->Counter= isset($this->Data['Counter']) ? $this->Data['Counter'] : 0;
    }


    public function Ping() {
        $this->Counter++;
    }


    public function GetCounter() {
        return $this->Counter;
    }
}

