<?php namespace Accent\Session;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


use \Accent\AccentCore\Component;


class Session extends Component {


    // default options
    protected static $DefaultOptions= array(

        // name of storage driver (or FQCN)
        'Driver'=> 'File',

        // when to rotate session id (in seconds), zero for no rotation, 3600 for rotation on hour, ...
        'IdRotationTime'=> 0,

        // chances to trigger garbage-collector (default 1:200)
        'GcProbability'=> 200,

        // cookie settings
        'Cookie'=> array(
            'Name'=> 'sid',
            'Expire'=> 7200,        // lifetime, default on 2 hours (7200 secs), zero for session-only
            'Path'=> '/',
            'Domain'=> 'localhost',
            'CustomGetter'=> '',    // callable which return content of cookie
            'CustomSetter'=> '',    // callable which set new cookie content
        ),

        // version of Accent/Session package
        'Version'=> '1.0.0',

        // required services
        'Services'=> array(
            //'DB'=> 'DB',      // required for "db" driver
            //'File'=> 'File',  // required for "file" driver
        ),
    );

    // identifier of current session (retrieved from cookie or generated)
    protected $SessionId= '';

    // identifier of session before rotation
    protected $SessionOldId= '';

    // timestamp of last visit of current session
    protected $SessionTimestamp;

    // timestamp when last time session Id is modified
    protected $SessionTimeRotated;

    // timestamp when current session is created
    protected $SessionTimeCreated;

    // buffer for session's data
    protected $Data= array();

    // buffer for session's read-once data
    protected $DataOnce= array();

    // storage driver object
    protected $Driver;

    // flag that data are already loaded from storage
    protected $Loaded= false;

    // flag that data are modified and not saved yet
    protected $Stored= false;

    // values of session cookie parametars
    protected $Cookie= array();




    /**
     * Constructor
     */
    public function __construct($Options=array()) {

        // call ancestor
        parent::__construct($Options);

        // unpack Cookie option
        $this->Cookie= $this->GetOption('Cookie');
        // maximum allowed session lifetime is 24 hours
        // remember that session should not be used as means for "remember me" functionality
        $this->Cookie['Expire']= min(86400, $this->Cookie['Expire']);

        // try to get session-id from request
        $this->SetIdFromCookie();

        // register shutdown function to store session data
        register_shutdown_function(array($this, 'OnShutdown'));

        // attach event listener to trigger $this->SendCookie
        $ThisService= $this;
        $this->RegisterEventListener('Output.Send', function() use ($ThisService) {
            $ThisService->SendCookie();
        });
    }


    /**
     * Load data from storage using driver.
     */
    protected function Load() {

        // do not load already loaded session data
        if ($this->Loaded) {
            return;
        }
        $this->Loaded= true;

        // call driver
        $Found= ($this->SessionId)
            ? $this->GetDriver()->Read($this->SessionId, $this->SessionOldId)
            : false;
        if (!$Found) {
            $this->CreateNewSession();   // new session ID will be assigned here if missing
            return;
        }

        // unpack loaded session
        $this->SessionId= $Found['Id'];         // loaded ID can alter SessionId here
        $this->SessionOldId= $Found['OldId'];
        $this->SessionTimestamp= $Found['Timestamp'];
        $this->SessionTimeCreated= $Found['TimeCreated'];
        $this->SessionTimeRotated= $Found['TimeRotated'];
        $this->Data= $Found['Data'];
        $this->DataOnce= $Found['DataOnce'];

        // don't hold 'OldId' info for more then 30 seconds, dont update OldId storage records
        if (time() - $this->SessionTimeRotated > 30) {
            $this->SessionOldId= '';
        }
        // rotator can alter session_id too
        $this->TryToRotateId();
    }


    /**
     * Get info about arbitrary session.
     * @param string $SessId
     * @return array
     */
    public function GetSessionById($SessId) {

        return $this->GetDriver()->Read($SessId, null);
    }


    /**
     * Save data to persistent storage using driver.
     * If there is no data and session is not loaded - storing will be skiped.
     */
    public function StoreSession() {

        if ($this->Stored) {
            return; // already stored and data are unchanged
        }
        if (empty($this->Data) && empty($this->DataOnce) && !$this->Loaded) {
            return; // there is not session loaded and no data to be saved
        }
        $this->GetDriver()->Write(
            $this->SessionId,
            $this->SessionOldId,
            $this->SessionTimeCreated,
            $this->SessionTimeRotated,
            $this->Data,
            $this->DataOnce
        );
        $this->Stored= true;
        $this->Loaded= false;    // this will reload session data on next Get()
        $this->GarbageCollection();
    }


    /**
     * Lazy driver object creator.
     */
    protected function GetDriver() {

        if ($this->Driver) {
            return $this->Driver;
        }

        if (is_object($this->GetOption('Driver'))) {
            // specified as object
            $this->Driver= $this->GetOption('Driver');
        } else if (strpos($this->GetOption('Driver'),'\\')!==false) {
            // specified as classname
            $Class= $this->GetOption('Driver');
            $this->Driver= new $Class($this->GetAllOptions());  // send same options to driver
        } else {
            // specified as name of internal driver
            $Name= ucfirst(strtolower($this->GetOption('Driver')));
            $Class= '\\Accent\\Session\\Driver\\'.$Name.'Driver';
            $this->Driver= new $Class($this->GetAllOptions());  // send same options to driver
        }
        return $this->Driver;
    }


    /**
     * Remove all data from current session and clears session's identifier.
     */
    public function ClearSession() { // should remove cookie too ?

        // clear buffers
        $this->SessionTimestamp= 0;
        $this->SessionTimeCreated= 0;
        $this->SessionTimeRotated= 0;
        $this->Data= array();
        $this->DataOnce= array();

        // safe disconnect from current session and release locks
        if ($this->SessionId) {
            $this->StoreSession();
            $this->CloseSession();
        }

        // clear ids and flags
        $this->SessionId= '';
        $this->SessionOldId= '';
        $this->Loaded= false;
        $this->Stored= true;
    }


    /**
     * Delete specified session from storage.
     * Omit parameter to delete current session.
     */
    public function DeleteSession($SessId=null) {

        if ($SessId === null) {
            // delete both IDs
            if ($this->SessionId) {
                $this->GetDriver()->Delete($this->SessionId);
            }
            if ($this->SessionOldId) {
                $this->GetDriver()->Delete($this->SessionOldId);
            }
            // release locks
            $this->ClearSession();
        } else {
            $this->GetDriver()->Delete($SessId);
        }
    }


    /**
     * Clear all data and prepare new session.
     * Session identifier will be preserved or generated new if missing.
     */
    protected function CreateNewSession() {

        $NewSessId= ($this->SessionId)
            ? $this->SessionId
            : $this->GenerateId();
        $this->ClearSession();
        $this->SessionId= $NewSessId;
        $this->SessionOldId= '';
        $Now= time();
        $this->SessionTimestamp= $Now;
        $this->SessionTimeCreated= $Now;
        $this->SessionTimeRotated= $Now;
        $this->SetCookie();
        $this->Loaded= true;        // prevent Load() to search for this session
    }

    /**
     * Call driver to release all ocupied resources.
     */
    protected function CloseSession() {

        $this->GetDriver()->Close();
    }


    /**
     * Return ID of current session or NULL if no session found.
     * This method will not load session data from storage.
     */
    public function GetId() {

        return $this->SessionId;
    }

    /**
     * Set custom ID for current session.
     * This method will not load session data from storage.
     * @return true  if ID is successfully validated
     */
    public function SetId($Id) {

        // check is new id correctly formated
        if (!$this->IsValidId($Id)) {
            return false;
        }
        // safe disconnect from current session
        if ($this->SessionId) {
            $this->StoreSession();
            $this->CloseSession();
        }
        // set new id
        $this->SessionId= $Id;
        $this->SessionOldId= '';
        return true;
    }


    /**
     * Set session identifier as readed from cookie.
     * @return true  if IDs are successfully validated
     */
    public function SetIdFromCookie() {

        $Content= $this->GetCookie();
        $Ids= explode('|', $Content, 2);

        // validate both IDs in single call
        if (!$this->IsValidId(implode('',$Ids))) {
            return false;
        }

        // safe disconnect from current session
        if ($this->SessionId) {
            $this->StoreSession();
            $this->CloseSession();
        }

        // set new ids
        $this->SessionId= $Ids[0];
        $this->SessionOldId= isset($Ids[1]) ? $Ids[1] : '';
        $this->SetCookie();
        return true;
    }


    /**
     * Get timestamp of current session's last visit.
     * This will load/create session if needed.
     */
    public function GetFirstVisit() {

        $this->Load();
        return $this->SessionTimeCreated;
    }


    /**
     * Get timestamp of current session's last visit.
     * This will load/create session if needed.
     */
    public function GetLastVisit() {

        $this->Load();
        return $this->SessionTimestamp;
    }


    /**
     * Periodically regenerate and assign new ID to current session.
     */
    protected function TryToRotateId() {

        if (!$this->Loaded || $this->GetOption('IdRotationTime')==0) {
            return;
        }
        if ($this->SessionUpdatedId + $this->GetOption('IdRotationTime') > time()) {
            return;
        }
        $this->RotateId();
    }


    /**
     * Assign new ID to current session.
     * Do not call this multiple times during same request, cookies can confuse browser.
     */
    public function RotateId() {

        $this->SessionOldId= $this->SessionId;
        $this->SessionId= $this->GenerateId();
        $this->SessionUpdatedId= time();
        $this->SetCookie();
    }


    /**
     * Creates new random identifier for current session.
     */
    public function GenerateId($Length=32) {
        // this implementation can produce up to 128-characters random string
        $Salt= function_exists('password_hash')
            ? password_hash(uniqid(mt_rand(),true).$Length, PASSWORD_DEFAULT)
            : base64_encode(hash('sha512', uniqid(mt_rand(),true).$Length, true));
        $Str= base64_encode(hash('sha512', $Salt.uniqid(mt_rand(),true).microtime(), true));
        $Limited= substr($Str, 0, $Length);
        return strtr($Limited, '=+/', '-_.'); // ensure filename safe characters
    }


    /**
     * Check session ID for unallowed characters.
     */
    public function IsValidId($Id) {

        $Length= strlen($Id);
        // too large string can take much time in regex
        if ($Length < 16 || $Length > 128) {
            return false;
        }
        $Purified= preg_replace('/[^A-Za-z0-9_.-]/', '', $Id);
		return $Purified === $Id;
    }


    /**
     * Execute garbage collection periodicaly.
     */
    protected function GarbageCollection() {

       if (mt_rand(0, $this->GetOption('GcProbability')) !== 0) {
           return;
       }
       $this->GetDriver()->GarbageCollection();
    }


    /**
     * Tasks to be performed on shutdown of PHP lifecycle.
     */
    public function OnShutdown() {

        if (!$this->Stored) {
            $this->StoreSession();
        }
        $this->CloseSession();
    }


    //--------------------------------------------
    //        GET / HAS / SET / DELETE
    //--------------------------------------------


    /**
     * Retrieve value from session buffer.
     * This method will load session from storage if needed.
     * If NULL is passed as key method will return all data but that will not erase readonce data.
     */
    public function Get($Key) {

        $this->Load();
        if ($Key === null) {
            return $this->DataOnce + $this->Data;
        }
        if (isset($this->DataOnce[$Key])) {
            $Temp= $this->DataOnce[$Key];
            unset($this->DataOnce[$Key]);
            return $Temp;
        }
        if (isset($this->Data[$Key])) {
            return $this->Data[$Key];
        }
        return null;
    }


   /**
     * Checks does session's variable exist in buffer.
     * ReadOnce vars will be preserved.
     * This method will load session from storage if needed.
     */
    public function Has($Key) {

        $this->Load();
        return isset($this->DataOnce[$Key]) || isset($this->Data[$Key]);
    }


    /**
     * Retrieve value from session buffer.
     * This method will load session from storage if needed.
     */
    public function Set($Key, $Value, $ReadOnce=false) {

        $this->Load();
        if ($ReadOnce) {
            $this->DataOnce[$Key]= $Value;
        } else {
            $this->Data[$Key]= $Value;
        }
        $this->Stored= false;
        return $this;
    }




    /**
     * Remove value from session buffer.
     * This method will load session from storage if needed.
     */
    public function Delete($Key, $ReadOnceOnly=false) {

        $this->Load();
        unset($this->DataOnce[$Key]);
        if (!$ReadOnceOnly) {
            unset($this->Data[$Key]);
        }
        $this->Stored= false;
        return $this;
    }


    //----------------------------------------------
    //          COOKIE management methods
    //----------------------------------------------


    /**
     * Set cookie content.
     */
    protected function SetCookie() {

        // called from CreateNewSession and RotateId,
        // both methods cannot be executed in same request
        $Content= ($this->SessionOldId)
            ? $this->SessionId.'|'.$this->SessionOldId
            : $this->SessionId;
        $this->Cookie['Value']= $Content;
    }


    /**
     * Retrieve content of session cookie.
     *
     * @return string
     */
    protected function GetCookie() {

        // try with custom getter
        if ($this->Cookie['CustomGetter']) {
            $Func= $this->Cookie['CustomGetter'];
            return $Func($this->Cookie['Name']);
        }

        // read from cookie
        return $this->Input($this->Cookie['Name'], 'COOKIE');
    }


    /**
     * Allows external access to expiration of session cookie.
     * That can be used to achieve "logout" action (by setting $Expire in past)
     * of "Remember me" action (by setting $Expire several years in future).
     *
     * @param int $Expire  [ 0 = destroy cookie in browser closing,
     *                      >0 = UTC time of expiration]
     */
    public function SetCookieExpiration($Expire) {

        $this->Cookie['Expire']= intval($Expire);
    }


    /**
     * Actual sender of session cookie on HTTP output.
     * Cookie was not set inside of SetCookie() method, to allow furher modifications,
     * so somewhere in application should be call to this method to dispatch it.
     */
    public function SendCookie() {

        // is cookie already sent?
        if ($this->Cookie === null) {
            return;
        }

        // Array driver is not persistant, so it does not require cookie
        if ($this->GetOption('Driver') === 'Array') {
            return;
        }

        // if not prepared do it now
        if (!isset($this->Cookie['Value'])) {
            $this->SetCookie();
        }

        // prepare cookie parameters
        $Name= $this->Cookie['Name'];
        $Value= $this->Cookie['Value'];
        $Expire= $this->Cookie['Expire'] === 0 ? 0 : time() + $this->Cookie['Expire'];
        $Path= $this->Cookie['Path'];
        $Domain= $this->Cookie['Domain'] === 'localhost' ? null : $this->Cookie['Domain'];

        // dispatch cookie
        if ($this->Cookie['CustomSetter']) {
            $Func= $this->Cookie['CustomSetter'];
            $Func($Name, $Value, $Expire, $Path, $Domain, false);
        } else {
            setcookie($Name, $Value, $Expire, $Path, $Domain, false);
        }
        // marker
        $this->Cookie= null;
    }

} // End

?>