<?php namespace Accent\Storage\Driver;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


/**
 * Storage driver for storing data external Redis service.
 *
 * TODO: Redis has not good anti-race-condition strategy, find a way to implement some kind of locking mechanism
 */



class RedisDriver extends AbstractDriver {



    // predefined options
    protected static $DefaultOptions= array(

        // short string which must be unique to prevent key collision if Memcached serve multiple applications
        'Prefix'=> '',

        // TTL (time to live), default on 30 days
        'Expire' => 2600000,

        // server host and ports that run the redis service
        'Server'=> array('Host' => '127.0.0.1', 'Port' => 6379),

    );

    // instance of RedisCLI (object)
    protected $Redis;


    protected function GetRedis() {

        // create and init Memcached object on first call
        if ($this->Redis === null) {
            try {
                // create instance of RedisCLI class
                $this->Redis = new RedisCLI();
                // configure all servers
                $Server = $this->GetOption('Server');
                $this->Redis->Connect($Server['Host'], $Server['Port'], true);
                $this->Redis->set_error_function($this->GetOption('ErrorFunc'));
                // test connection
                if ($this->RedisCommand('SELECT', 0) === false) {
                    $this->Redis= false;
                }
            } catch (Exception $e) {
                $this->Error($e->getMessage());
                $this->Redis= false;
            }
        }
        // return object
        return $this->Redis;
    }


    protected function RedisCommand($Cmd) {

        try {
            $CLI = call_user_func_array(array($this->Redis, 'Cmd'), func_get_args());
            $Executor = strpos(strtoupper($Cmd), 'GET') !== false ? 'Get' : 'Set';
            return $CLI->$Executor();
        } catch (Exception $e) {
            $this->Error($e->getMessage());
            return false;
        }
    }



    public function StorageExist() {

        return $this->GetRedis() !== false;
    }


    public function Exist($Key, $Validate=true) {

        // validate connection
        if (!$this->GetRedis()) {
            return false;
        }

        // fetch from Memcached extension
        //$Record= $this->Redis->Cmd('GET', $this->GetKey($Key))->Get();
        $Record= $this->RedisCommand('GET', $this->GetKey($Key));

        // record not found?
        if ($Record === null) {
            return false;
        }

        // return true or result of validation
        $Pack= json_decode($Record, true, 128, JSON_BIGINT_AS_STRING);
        return is_array($Pack) && $Validate
            ? $this->ValidateTags($Pack['Tags'], $Pack['Timestamp'])
            : true;
    }


    public function Read($Key) {

        // validate connection
        if (!$this->GetRedis()) {
            return false;
        }

        // fetch from Memcached extension
        $Record= $this->RedisCommand('GET', $this->GetKey($Key));

        // record not found?
        if ($Record === null) {
            return false;
        }

        // record not valid?
        $Pack= json_decode($Record, true, 128, JSON_BIGINT_AS_STRING);
        if (!is_array($Pack) || $this->ValidateTags($Pack['Tags'], $Pack['Timestamp']) === false)  {
            return false;
        }

        // return stored value
        return $Pack['Data'];
    }


    public function Write($Key, $Value, $Tags) {

        // validate connection
        if (!$this->GetRedis()) {
            return false;
        }

        if (is_string($Tags)) {
            $Tags= array($Tags);
        }
        $Timestamp= $this->GetMicrotime();  // as string
        $Pack= json_encode(array(
            'Timestamp'=> $Timestamp,
            'Tags'=> $Tags,
            'Data'=> $Value,
        ), JSON_UNESCAPED_UNICODE);

        // send record to Redis service
        $this->RedisCommand('SET', $this->GetKey($Key), $Pack, 'EX', $this->GetOption('Expire'));

        // update tags in TagRegistry if needed
        if (!empty($Tags)) {
            $this->UpdateTags($Tags, $Timestamp);
        }
    }


    protected function ValidateTags($Tags, $Timestamp) {

        // check is any of tags has newer timestamp
        if (empty($Tags)) {
            return true;
        }

        // fetch TagRegistry from Redis
        $TagRegistry= json_decode($this->RedisCommand('GET', $this->GetKeyTagRegistry()), true);
        if (!is_array($TagRegistry)) {
            $TagRegistry= array();
        }

        // check timestamp of each tag
        // tag must exist and timestamp from registry must not be newer then timestamp of the record
        foreach(array_unique($Tags) as $Tag) {
            if (!isset($TagRegistry[$Tag]) || $TagRegistry[$Tag] > $Timestamp) {
                return false;
            }
        }

        // otherwise return true
        return true;
    }


    public function Clear($Tags) {

        if (!$this->GetRedis()) {
            return;
        }

        // special case
        if($Tags === '*') {
            // clear registry
            $this->RedisCommand('SET', $this->GetKeyTagRegistry(), ''); // permanent TTL
            return;
        }
        // ensure array type
        if(is_string($Tags)) {
            $Tags= array($Tags);
        }

        // fetch TagRegistry from Redis
        $TagRegistry= json_decode($this->RedisCommand('GET', $this->GetKeyTagRegistry()), true);

        if (!is_array($TagRegistry)) {
            $TagRegistry= array();
        }

        // delete tags instead of updating them, this makes further readings faster and GC unnecessary
        foreach ($Tags as $Tag) {
            unset($TagRegistry[$Tag]);
        }

        // store updated registry
        $this->RedisCommand('SET', $this->GetKeyTagRegistry(), json_encode($TagRegistry, JSON_UNESCAPED_UNICODE)); // permanent TTL
    }


    public function GarbageCollection() {

        //...   nothing to do :)
    }


    protected function GetKey($Key) {

        return $this->GetOption('Prefix').'.'.$Key;
    }


    protected function GetKeyTagRegistry() {

        return $this->GetOption('Prefix').'_TAG-REGISTRY';
    }


    protected function UpdateTags($Tags, $Timestamp) {

        $UpdatedTags= array();
        foreach(array_unique($Tags) as $Tag) {
            $UpdatedTags[$Tag]= $Timestamp;
        }

        // fetch TagRegistry from Redis
        $TagRegistry= json_decode($this->RedisCommand('GET', $this->GetKeyTagRegistry()), true);
        if (!is_array($TagRegistry)) {
            $TagRegistry= array();
        }

        // append (or overwrite if exist) new tags to registry
        $TagRegistry= $UpdatedTags + $TagRegistry;

        // store updated registry
        $this->RedisCommand('SET', $this->GetKeyTagRegistry(), json_encode($TagRegistry, JSON_UNESCAPED_UNICODE)); // permanent TTL
    }


    protected function GetMicrotime($Offset=0) {

        return sprintf("%01.6f", microtime(true) + $Offset);
        //list($usec, $sec) = explode(" ", microtime());
        //return $sec.':'.substr($usec,2,6);
    }


    /**
     * Debug helper.
     */
    public function GetRegistry() {

        return $this->GetRedis() ? json_decode($this->RedisCommand('GET', $this->GetKeyTagRegistry()), true) : false;
    }
}



/**
 * Following Redis communication class it taken from https://github.com/ziogas/PHP-Redis-implementation
 * It should not be used outside of this driver therefore it can be contained in same file.
 */
/**
 * Raw redis wrapper, all the commands are passed as-is.
 * More information and usage examples could be found on https://github.com/ziogas/PHP-Redis-implementation
 * Based on http://redis.io/topics/protocol
 */
class RedisCLI {

    const INTEGER = ':';
    const INLINE = '+';
    const BULK = '$';
    const MULTIBULK = '*';
    const ERROR = '-';
    const NL = "\r\n";
    private $handle = false;
    private $host;
    private $port;
    private $silent_fail;
    private $commands = array();
    //Timeout for stream, 30 seconds
    private $timeout = 30;
    //Timeout for socket connection
    private $connect_timeout = 3;
    //Use this with extreme caution
    private $force_reconnect = false;
    //Error handling, debug info
    private $last_used_command = '';
    //Error handling function, use set_error_function method ()
    private $error_function = null;
    public function __construct($host = false, $port = false, $silent_fail = false, $timeout = 60) {
        if ($host && $port) {
            $this->connect($host, $port, $silent_fail, $timeout);
        }
    }
    //Main method to establish connection
    public function connect($host = '127.0.0.1', $port = 6379, $silent_fail = false, $timeout = 60) {
        $this->host = $host;
        $this->port = $port;
        $this->silent_fail = $silent_fail;
        $this->timeout = $timeout;
        if ($silent_fail) {
            $this->handle = @fsockopen($host, $port, $errno, $errstr, $this->connect_timeout);
            if (!$this->handle) {
                $this->handle = false;
            }
        } else {
            $this->handle = fsockopen($host, $port, $errno, $errstr, $this->connect_timeout);
        }
        if (is_resource($this->handle)) {
            stream_set_timeout($this->handle, $this->timeout);
        }
    }
    public function reconnect() {
        $this->__destruct();
        $this->connect($this->host, $this->port, $this->silent_fail);
    }
    public function __destruct() {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
    //Returns all commands array
    public function commands() {
        return $this->commands;
    }
    //Used to push single command to queue
    public function cmd() {
        if (!$this->handle) {
            return $this;
        }
        $args = func_get_args();
        $rlen = count($args);
        $output = '*'. $rlen . self::NL;
        foreach ($args as $arg) {
            $output .= '$'. strlen($arg) . self::NL . $arg . self::NL;
        }
        $this->commands[] = $output;
        return $this;
    }
    //Used to push many commands at once, almost always for setting something
    public function set() {
        if (!$this->handle) {
            return false;
        }
        //Total size of commands
        $size = $this->exec();
        $response = array();
        for ($i=0; $i<$size; $i++) {
            $response[] = $this->get_response();
        }
        if ($this->force_reconnect) {
            $this->reconnect();
        }
        return $response;
    }
    //Used to get command response
    public function get($line = false) {
        if (!$this->handle) {
            return false;
        }
        $return = false;
        if ($this->exec()) {
            $return = $this->get_response();
            if ($this->force_reconnect) {
                $this->reconnect();
            }
        }
        return $return;
    }
    //Used to get length of the returned array. Most useful with `Keys` command
    public function get_len() {
        if (!$this->handle) {
            return false;
        }
        $return = null;
        if ($this->exec()) {
            $char = fgetc($this->handle);
            if ($char == self::BULK) {
                $return = sizeof($this->bulk_response());
            } elseif ($char == self::MULTIBULK) {
                $return = sizeof($this->multibulk_response());
            }
            if ($this->force_reconnect) {
                $this->reconnect();
            }
        }
        return $return;
    }
    //Forces to reconnect after every get() or set(). Use this with extreme caution
    public function set_force_reconnect($flag) {
        $this->force_reconnect = $flag;
        return $this;
    }
    //Used to parse single command single response
    private function get_response() {
        $return = false;
        $char = fgetc($this->handle);
        switch ($char) {
            case self::INLINE:
                $return = $this->inline_response();
                break;
            case self::INTEGER:
                $return = $this->integer_response();
                break;
            case self::BULK:
                $return = $this->bulk_response();
                break;
            case self::MULTIBULK:
                $return = $this->multibulk_response();
                break;
            case self::ERROR:
                $return = $this->error_response();
                break;
        }
        return $return;
    }
    //For inline responses only
    private function inline_response() {
        return trim(fgets($this->handle));
    }
    //For integer responses only
    private function integer_response() {
        return ( int ) trim(fgets($this->handle));
    }
    //For error responses only
    private function error_response() {
        $error = fgets($this->handle);
        if ($this->error_function) {
            call_user_func($this->error_function, $error .'('. $this->last_used_command .')');
        }
        return false;
    }
    //For bulk responses only
    private function bulk_response() {
        $return = trim(fgets($this->handle));
        if ($return === '-1') {
            $return = null;
        } else {
            $return = $this->read_bulk_response($return);
        }
        return $return;
    }
    //For multibulk responses only
    private function multibulk_response() {
        $size = trim(fgets($this->handle));
        $return = false;
        if ($size === '-1') {
            $return = null;
        } else {
            $return = array();
            for ($i = 0; $i < $size; $i++) {
                $return[] = $this->get_response();
            }
        }
        return $return;
    }
    //Sends command to the redis
    private function exec() {
        $size = sizeof($this->commands);
        if ($size < 1) {
            return null;
        }
        if ($this->error_function) {
            $this->last_used_command = str_replace(self::NL, '\\r\\n', implode(';', $this->commands));
        }
        $command = implode(self::NL, $this->commands) . self::NL;
        fwrite($this->handle, $command);
        $this->commands = array();
        return $size;
    }
    //Bulk response reader
    private function read_bulk_response($tmp) {
        $response = null;
        $read = 0;
        $size = ((strlen($tmp) > 1 && substr($tmp, 0, 1) === self::BULK) ? substr($tmp, 1) : $tmp);
        while ($read < $size) {
            $diff = $size - $read;
            $block_size = $diff > 8192 ? 8192 : $diff;
            $chunk = fread($this->handle, $block_size);
            if ($chunk !== false) {
                $chunkLen = strlen($chunk);
                $read += $chunkLen;
                $response .= $chunk;
            } else {
                fseek($this->handle, $read);
            }
        }
        fgets($this->handle);
        return $response;
    }
    public function set_error_function($func) {
        $this->error_function = $func;
    }
}

?>