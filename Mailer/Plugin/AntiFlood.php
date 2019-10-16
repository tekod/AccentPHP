<?php namespace Accent\Mailer\Plugin;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * AntiFlood plugin counts sent emails and apply short pause after reaching specified number before continuing.
 *
 * It respond on "Mail.SendEnvelope.Before" event.
 */

use \Accent\AccentCore\Component;


class AntiFlood extends Component {


    protected static $DefaultOptions= array(

        // time (in seconds) to sleep before continue execution
        'Sleep'=> 2,
    );


    function __construct(array $Options) {

        // call parent and gather descendant options
        parent::__construct($Options);
    }


    public function OnMailSendEnvelopeBefore($Params) {
        // params: array('Message'=>object)

        sleep($this->GetOption('Sleep'));

        // this event runs in "interrupt" mode, return true to allow other listeners to execute
        return true;
    }



}