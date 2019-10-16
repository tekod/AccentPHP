<?php namespace Accent\Mailer\Plugin;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Simple plugin for making short pause before each email.
 *
 * It respond on "Mail.SendEnvelope.Before" event where taking some time before return execution to caller.
 */

use \Accent\AccentCore\Component;


class SimpleThrottler extends Component {


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