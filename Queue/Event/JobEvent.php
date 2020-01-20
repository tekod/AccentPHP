<?php namespace Accent\Queue\Event;

use Accent\AccentCore\Event\BaseEvent;


class JobEvent extends BaseEvent {


    /**
     * Return job object.
     *
     * @return Accent\Queue\Job
     */
    public function GetJob() {

        return $this->Data['Job'];
    }


    /**
     * Queue worker getter.
     *
     * @return Accent\Queue\Worker
     */
    public function GetWorker() {

        return $this->Data['Job']->GetWorker();
    }


    /**
     * Job record getter.
     *
     * @param string $Field
     * @return mixed
     */
    public function GetRecord($Field=null) {

        return $this->Data['Job']->GetRecord($Field);
    }

}

?>