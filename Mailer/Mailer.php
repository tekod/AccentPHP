<?php namespace Accent\Mailer;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/* *
 * Usage:
 * $EM= $this->GetService('Mail')->Create();  // create new email message
 * $EM->To('marco.polo@gmail.com')
 *    ->From('me@gmail.com')
 *    ->Subject('Welcome')
 *    ->Body('Hi customer, welcome to our site')
 *    ->Send();
 *  ...or via constructor...
 *  $EM= $this->GetService('Mail')->Create(array('Fields'=>array(
 *     'To'=> 'marco.polo@gmail.com',
 *     'From'=> 'me@gmail.com',
 *     'Subject'=> 'Welcome',
 *     'BodyPlain'=> 'Hi customer, welcome to our site',
 *   )))->Send();
 */

use \Accent\AccentCore\Component;
use \Accent\Mailer\Message;


class Mailer extends Component {


    // default options
    protected static $DefaultOptions= array(

        // name of driver or FQCN of class
        'Driver'     => 'Swift',

        // instruct to not actually send email
        'DebugMode'=> false,

        // path for storing composition of last message, for debug purposes
        'LogFile'=> '',

        // strings wrapping replacing keywords
        'ReplacerWrapper'=> array('{{', '}}'),

        // set of rules for preventing flooding mail server
        'Throttlers'=> array(),

        // version of Accent/Mailer package
        'Version'=> '1.1.0',
    );


    /**
     * Constructor
     */
    public function __construct($Options) {

        parent::__construct($Options);

    }


    public function Create($Fields = array()) {

        $Options= $this->MergeArrays(array(
            array('Fields' => $this->MergeArrays(array(
                array(
                    'Headers' => array(
                        'User-Agent' => 'AccentPHP / Mail service (ver.'.$this->GetVersion().')',
                    ),
                ),
                $Fields,
            ))),
            $this->Options,
        ));
        $Message= new Message($Options);

        // return message if properly initiated, otherwise fallback to NoneDriver
        return $Message->IsInitied()
            ? $Message
            : new Message(array('Driver'=>'None') + $Options);
    }

}

?>