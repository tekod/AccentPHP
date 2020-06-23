<?php namespace Accent\AccentCore\ServiceManager;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


/**
 * Lazy service builder.
 * Usage:
 * ... $T= $ServiceManager->GetLazy('DB'); ... $DBO= $T(); ... $DBO->Query('users').....
 */


class LazyLoader {


    protected $ServiceManager;
    protected $ServiceName;
    protected $Resolved;


    public function __construct($Name, $Manager) {

        $this->ServiceManager= $Manager;
        $this->ServiceName= $Name;
    }


    public function __invoke() {

        if (!$this->Resolved) {
            $SM= $this->ServiceManager;
            if ($SM === null) {
                return false;
            }
            $this->Resolved= $SM->Get($this->ServiceName);
        }
        return $this->Resolved;
    }

}
