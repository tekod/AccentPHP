<?php namespace Accent\Localization\Event;



use Accent\AccentCore\Event\BaseEvent;


class DetectLanguageEvent extends BaseEvent {


    protected static $DefaultOptions= [

        // list of allowed languages, like ['it','fr','en']
        'AllowedLanguages' => [],

        // content of $_SERVER from current HTTP request
        'ServerVars'     => [],
    ];

    // internal storage
    protected $Lang= '';


    /*
     * Setter.
     * Provide lowercased 2-char identifier of language.
     *
     * @param string $Lang
     */
    public function SetLanguage($Lang) {

        // set value
        $this->Lang= $Lang;

        // raise flag
        $this->SetHandled();
    }


    /**
     * Getter.
     *
     * @return string
     */
    public function GetLanguage() {

        return $this->Lang;
    }
}

?>