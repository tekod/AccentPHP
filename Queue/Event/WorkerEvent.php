<?php namespace Accent\Queue\Event;

use Accent\AccentCore\Event\BaseEvent;


class WorkerEvent extends BaseEvent {


    // default options
    protected static $DefaultOptions= [

        // worker object
        'Worker' => null,
    ];



    /**
     * Getter.
     *
     * @return string
     */
    public function GetWorker() {

        return $this->GetOption('Worker');
    }

}

?>