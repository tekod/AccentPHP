<?php namespace Accent\Router\Event;

use Accent\AccentCore\Event\BaseEvent;


class LoadRoutesEvent extends BaseEvent {


    /**
     * Getter.
     *
     * @return Accent\Router\RouteGroup
     */
    public function GetRoutesCollection() {

        return $this->Data['Routes'];
    }

}

?>