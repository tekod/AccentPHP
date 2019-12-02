<?php namespace Accent\Router\Event;

use Accent\AccentCore\Event\BaseEvent;


class LoadRoutesEvent extends BaseEvent {


    // default options
    protected static $DefaultOptions= [

        // collection (RouteGroup)
        'Routes'   => null,
    ];


    /**
     * Getter.
     *
     * @return mixed
     */
    public function GetRoutesCollection() {

        return $this->GetOption('Routes');
    }

}

?>