<?php namespace Accent\Router\Event;

use Accent\AccentCore\Event\BaseEvent;


class EventRuleEvent extends BaseEvent {


    // default options
    protected static $DefaultOptions= [

        // compiled route (as array structure)
        'Route'   => null,

        // object of RequestContext
        'Context' => null,
    ];

    // internal storage
    protected $RouteHandler= null;



    /*
     * Setter.
     *
     * @param mixed $Handler
     */
    public function SetRouteHandler($Handler) {

        // store content
        $this->RouteHandler= $Handler;

        // raise flag
        $this->SetHandled();
    }


    /**
     * Getter.
     *
     * @return mixed
     */
    public function GetRouteHandler() {

        return $this->RouteHandler;
    }

}

?>