<?php namespace Accent\Queue\Event;

use Accent\AccentCore\Event\BaseEvent;


class JobEvent extends BaseEvent {


    // default options
    protected static $DefaultOptions= [

        // job object
        'Job' => [],
    ];



    /**
     * Getter.
     *
     * @return string
     */
    public function GetJob() {

        return $this->GetOption('Job');
    }

}

?>