<?php namespace Accent\AccentCore\Test;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */




/**
 * Test service using other services.
 */


class TestingAdvService {


    protected $Opts;

    protected $DefaultOpts= array(
        'SubService'=> 'ATP1',      // object or service name of subservice
        // 'ServiceManager' field is always injected by manager
    );



    public function __construct($Options) {

        $this->Opts= $Options + $this->DefaultOpts;
    }


    public function Initied() {
        // ServiceManager field is injected by Service manager
        return is_object($this->Opts['ServiceManager']);
    }


    /**
     * Get specified service.
     * Calling services trough Service() function allows lazy creation of them.
     *
     * @param string|object $Service Name of service or already instatied service object
     * @return object
     */
    /*protected function SpecService($Service) {
        // subservice can be specified as object or string
        return is_string($Service)
            ? $this->Opts['ServiceManager']->Get($this->Opts[$Service])
            : $Service;
    }
*/

    protected function SubService() {

        $ServiceName= $this->Opts['SubService'];

        return is_object($ServiceName)
            ? $ServiceName
            : $this->Opts['ServiceManager']->Get($ServiceName);
    }


    public function DoSomething($Txt) {
        // use subservice
        return $this->SubService()->Decorate($Txt);
    }




}
