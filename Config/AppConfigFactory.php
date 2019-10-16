<?php namespace Accent\Config;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */



/**
 * AppConfigFactory is factory for typical use case in application with multiple configurations.
 *
 *
 * Configs named with "!" suffix will be created as read-only.
 *
 * // in app...
 * $this->RegisterService('AppConfigFactory', 'Accent\\Config\\AppConfigFactory', array(
 *      'StorageDriver'=> 'yaml',
 *      'StoragePaths'=> array(
 *          '@AppDir/Config/{Name}.yaml',
 *          '@AppDir/Config/{Env}/{Name}.yaml',
 *       ),
 *      'Environment'=> $this->Env,
 * ));
 * ...
 * // calling...
 * $AppConfig= $this->GetService('AppConfigFactory');       // returning service
 * $DatabaseConfig= $AppConfig->GetConfig('database');      // returning database config (StackedConfig instance)
 * $Value= $DatabaseConfig->Get('DSN');                     // returning discrete value
 *
 *
 * Variant with unknown starting environment:
 * // in app...
 * $this->RegisterService('AppConfigFactory', 'Accent\\Config\\AppConfigFactory', array(
 *      'StorageDriver'=> 'yaml',
 *      'StoragePaths'=> array(
 *          '@AppDir/config/{Name}.yaml',
 *          '@AppDir/config/{Env}/{Name}.yaml',
 *       ),
 * ));                                                      // omit defining Environment !!!
 * ...
 * // calling...
 * $AppConfig= $this->GetService('AppConfigFactory');       // return service
 * $MainConfig= $AppConfig->GetConfig('Main');              // return main configuration
 * $Value= $MainConfig->Get('Environments');
 * // ... use $Value to compare various environments and detect current $Environment ...
 * $AppConfig->ResetEnvironment($Environment);     // update factory with environment information
 *                                                 // it will also create secondary storage for existing configs and load them
 * // for example: $Data= $MainConfig->GetAll();  will return values from all storages of 'main' config
 *
 */

use \Accent\AccentCore\Component;


class AppConfigFactory extends Component {


    protected static $DefaultOptions= array(

        // define which storage driver will be used for instantiation of config objects
        'StorageDriver'=> 'php',

        // define locations of main and environment-dependant config files
        'StoragePaths'=> array(
            // '@AppDir/Config/{Name}.yaml',
            // '@AppDir/Config/{Env}/{Name}.yaml',
        ),

        // name of environment, typically 'Dev','Test','Pub',...
        // it can be modified later by SetEnvironment method
        'Environment'=> '',

    );

    // array of StackedConfig objects
    protected $Configs= array();

    // buffer for environment name
    protected $Environment= '';



    /*
     * Constructor.
     */
    public function __construct($Options) {

        // call parent
        parent::__construct($Options);

        // export Environment option
        $this->Environment= $this->GetOption('Environment');
    }


    /**
     * Create new StackedConfig object and add it to collection.
     * Name can be suffixed with "!" character to configure storage as read-only.
     *
     * @param string $Name  name of config file, without extension
     * @param bool $Load  weather to load storages
     * @return \Accent\Config\StackedConfig
     */
    public function AddConfig($Name, $Load=false) {

        $ReadOnly= substr($Name, -1, 1) === '!';
        $Name= trim($Name, '!');

        // prepare list of storages
        $StorageDefinitions= array();
        foreach($this->GetOption('StoragePaths') as $Path) {
            $StorageDefinitions[]= array(
                'Storage'=> $this->GetOption('StorageDriver'),
                'File'=> str_replace(array('{Name}','{Env}'), array($Name,$this->Environment), $Path),
            );
            if (!$this->Environment) {
                break;      // for undefined environment left all configs with only first path defined
            }
        }

        // instantiate config object
        $this->Configs[$Name]= new StackedConfig(array(
            'Storages'=> $StorageDefinitions,
            'ReadOnly'=> $ReadOnly,
            ) + $this->GetAllOptions()        // append all options used for component constructor
        );

        // load from storages
        if ($Load) {
            $this->Configs[$Name]->Load();
        }

        // return created config
        return $this->Configs[$Name];
    }

    /**
     * Removes instance of StackedConfig from manager.
     *
     * @param string $Name  name of instance, same as used in AddConfig
     */
    public function RemoveConfig($Name) {

        unset($this->Configs[$Name]);
    }


    /**
     * Retrieve attached instance of StackedConfig.
     *
     * @param string $Name  name of instance, same as used in AddConfig
     * @param bool $BuildIfMissing  weather to create StackedConfig if missing
     * @return false|\Accent\Config\StackedConfig
     */
    public function GetConfig($Name, $BuildIfMissing=false) {

        if (!isset($this->Configs[$Name]) && $BuildIfMissing) {
            $this->AddConfig($Name, true);  // in this context autoload is prefered
        }
        return isset($this->Configs[$Name])
            ? $this->Configs[$Name]
            : false;
    }


    /**
     * Set name of current environment, it will be used
     * @param $EnvironmentName
     */
    public function ResetEnvironment($EnvironmentName) {

        // store it
        $this->Environment= $EnvironmentName;

        // for each existing config (re)create all appened storages
        $Paths= $this->GetOption('StoragePaths');
        array_shift($Paths);
        foreach($this->Configs as $Name=>$Conf) {
            // first step, remove all storages behind first one
            $StorageKeys= $Conf->GetStorages();
            array_shift($StorageKeys);
            foreach ($StorageKeys as $Key) {
                $Conf->RemoveStorage($Key);
            }
            // second step, create all additional storages
            foreach ($Paths as $Path) {
                $Conf->AddStorage(array(
                    'Storage'=> $this->GetOption('StorageDriver'),
                    'File'=> str_replace(array('{Name}','{Env}'), array($Name,$this->Environment), $Path),
                ));
            }
            // third step, load all unloaded storages
            $Conf->Load();
        }
    }

}

?>