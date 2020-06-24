<?php namespace Accent\Cache;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */

/**
 * Cache component implements caching functionalities.
 */


use Accent\AccentCore\Component;
use Accent\Storage\Storage;


class Cache extends Component {


    // default options
    protected static $DefaultOptions= array(

        // name of storage engine or already instantied storage object
        'Storage'=> 'php',

        // data TTL (time to live) in seconds or false for permanent
        'Expire'=> 86400,

        // full path to directory (used only for file-based storage drivers)
        'Path'=> '',

        // version of component
        'Version'=> '1.0.0',

        // there is much more available options, depending on choosen storage engine
        // to find all available options check header of each particular driver
    );

    // instance of Storage object
    protected $Storage;


    /**
     * Constructor
     */
    public function __construct($Options) {

        parent::__construct($Options);

        // create storage object
        $this->CreateStorage();
    }


    protected function CreateStorage() {

        $Storage= $this->GetOption('Storage');
        $Path= $this->GetOption('Path');

        // if passed as string build storage object
        if (is_string($Storage)) {
            // in case of error storage constructor will dispatch error message
            // and initialized itself with "NoneDriver" driver
            $Storage= new Storage(array(
                'Driver'=> $Storage,
                'Path'=> $Path,
                'Mode'=> 'Distributed',   // using compact mode has no sanse for caching
            ) + $this->GetAllOptions(array('Path')));
        }
        $this->Storage= $Storage;

        // status of this object depends on status of storage object
        $this->Initied= $this->Storage->IsInitiated();
    }


    /**
     * Check existance of key in cache storage.
     * This method will NOT remove key from storage if expired value found.
     *
     * @param string $Key
     * @return bool
     */
    public function Exist($Key) {

        return $this->Storage->Exist($Key);
    }


    /**
     * Get value from cache.
     * This method will remove expired value from cache storage.
     * @param string $Key
     * @param array $Tags
     * @return null|mixed
     */
    public function Read($Key) {

        return $this->Storage->Read($Key);
    }


    /**
     * Put value in cache storage.
     *
     * @param string $Key
     * @param mixed $Value
     * @param array $Tags
     */
    public function Write($Key, $Value, $Tags=array()) {

        $this->Storage->Write($Key, $Value, $Tags);
    }


    /**
     * Removed values from cache with specified $Tags setted.
     * Special case: passing "*" as parametar will remove ALL keys.
     * @param '*'|array $Tags
     */
	public function Clear($Tags) {

        $this->Storage->Clear($Tags);
    }


    /**
     * Checks all values in cache storage and removes expired ones.
     */
	public function GarbageCollection() {

        $this->Storage->GarbageCollection();
    }


}

?>