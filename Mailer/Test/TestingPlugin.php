<?php namespace Accent\Mailer\Test;

use \Accent\AccentCore\Component;

/*
 * This is test plugin.
 * Its function is to intercept and append "footer" content at bottom of each mail.
 */

class TestingPlugin extends Component {


    protected static $DefaultOptions= array(

        // text to be append to message body
        'Footer'=> '',
    );


    function __construct(array $Options) {

        // call parent and gather descendant options
        parent::__construct($Options);
    }

    /*
    public function __call($A, $Params) {
        var_dump($A);
    }*/

    public function OnMailSendEnvelopeBefore($Params) {
        // params: array('Message'=>object)

        $Footer= $this->GetOption('Footer');
        $Message= $Params['Message'];

        // append footer to BodyHTML
        $BodyHTML= $Message->GetField('BodyHTML');
        if ($BodyHTML !== null) {
            $Message->BodyHTML($BodyHTML.'<br><br>'.$Footer);
        }

        // append footer to BodyPlain
        $BodyPlain= $Message->GetField('BodyPlain');
        if ($BodyPlain !== null) {
            $Message->BodyPlain($BodyPlain."\n\n".$Footer);
        }

        // this event runs in "interrupt" mode, return true to allow other listeners to execute
        return true;
    }



}