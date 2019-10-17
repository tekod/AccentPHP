<?php namespace Accent\Session\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Base class for all Session drivers
 */

use \Accent\AccentCore\Component;


abstract class AbstractDriver extends Component {


    /**
     * Retrieve session from storage.
     * Returned Id will be altered if stored session has rotated to newer value.
     *
     * @return array|false  session record of false if session not found
     */
    abstract public function Read($Id, $OldId=null);
    //abstract public function Read(&$Id, &$OldId, &$Timestamp, &$TimeCreated, &$TimeRotated, &$Data, &$DataOnce);


    /**
     * Save session to storage.
     */
    abstract public function Write($Id, $OldId, $TimeCreated, $TimeRotated, $Data, $DataOnce);


    /**
     * Remove session data from storage.
     */
    abstract public function Delete($Id);


    /**
     * Perform some maintenance tasks to remove expired sessions and free hosting resources.
     */
    abstract public function GarbageCollection();


    /**
     * Perform cleaning tasks on closing session.
     */
    public function Close() {
        // nothing to do
    }


    /**
     * Execute Close on destructing of driver.
     */
    public function __destruct() {
        $this->Close();
    }

}

?>