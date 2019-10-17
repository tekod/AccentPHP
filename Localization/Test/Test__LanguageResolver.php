<?php namespace Accent\Localization\Test;

/**
 * Testing localization service.
 */

use Accent\Test\AccentTestCase;
use Accent\Localization\LanguageResolver;


class Test__LanguageResolver extends AccentTestCase {


    // title describing this test
    const TEST_CAPTION= 'LanguageResolver test';

    // title of testing group
    const TEST_GROUP= 'Localization';


    protected function BuildOptions($Options=array()) {

        $DefaultOptions= array(
            'AllowedLanguages'=> array('en','fr','it','es'),
            'ServerVars'=> array(
                'REQUEST_URI'=> '/p1/p2/p3',
                'HTTP_HOST'=> 'www.mysite.com',
                'HTTP_ACCEPT_LANGUAGE'=> 'de-CH,de;q=0.5',
            ),
            'Rules'=> array(
                //'Domain'=> array('site.com'=>'en', 'site.fr'=>'fr'),
                //'Path'=> 'Prefix',
                //'Browser'=> null,
                //'Session'=> 'Lang',
                //'User'=> 'Lang',
                //'Event'=> 'MyApp.LangResolver',
                //'Default'=> 'en',
            ),
            // services
            'Services'=> array(
                'Event'=> new \Accent\AccentCore\Event\EventService,
                'Session'=> new \Accent\Session\Session(array('Driver'=>'Array')),
                'Auth' => new \Accent\Security\Auth\Auth(array('TryToAutoLogin'=>false, 'Services'=>array(
                    'Session'=>new \Accent\Session\Session(array('Driver'=>'Array')),
                    'UserService'=>new \Accent\User\UserService(array('UserStorages'=>array('Demo'=>array('Class'=>'Array'))))))),
            ),
        );
        return $Options + $DefaultOptions;
    }



    // TESTS:

    public function TestBuild() {

        $Options= $this->BuildOptions();
        $LR= new LanguageResolver($Options);
        $this->assertTrue($LR->IsInitied());
    }



    public function TestResolveByDomain() {

        $Context= (new \Accent\AccentCore\RequestContext())->FromArray(array());
        $Options= $this->BuildOptions(array(
            'Rules'=> array(
                'Domain'=> array('site.fr'=>'fr', 'site.it'=>'it', 'site.us'=>'en')
            ),
            'RequestContext'=> $Context,
        ));
        // test known domain
        $LR= new LanguageResolver($Options);
        $Context->SERVER['HTTP_HOST']= 'mysite.it';
        $this->assertEqual($LR->Resolve(), 'it');
        // test undefined domain
        $Context->SERVER['HTTP_HOST']= 'www.mysite.de';
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), null);
    }


    public function TestResolveByPath() {

        $Context= (new \Accent\AccentCore\RequestContext())->FromArray(array());
        $Options= $this->BuildOptions(array(
            'Rules'=> array('Path'=>'Prefix'),
            'RequestContext'=> $Context,
        ));
        // test allowed language
        $Context->SERVER['REQUEST_URI']= '/es/contact';
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), 'es');
        // test unknown language
        $Context->SERVER['REQUEST_URI']= '/de/contact';
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), null);
        // test suffix
        $Options['Rules']= array('Path'=>'Suffix');
        $Context->SERVER['REQUEST_URI']= '/contact/fr';
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), 'fr');
    }


    public function TestResolveByBrowser() {

        $Context= (new \Accent\AccentCore\RequestContext())->FromArray(array());
        $Options= $this->BuildOptions(array(
            'Rules'=> array('Browser'=>null),
            'RequestContext'=> $Context,
        ));
        // test allowed language
        $Context->SERVER['HTTP_ACCEPT_LANGUAGE']= 'en,en-US,en-AU;q=0.8,fr;q=0.6,en-GB;q=0.4';
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), 'en');
        // test alternate language
        $Context->SERVER['HTTP_ACCEPT_LANGUAGE']= 'pt-br,pt;q=0.8,en-us;q=0.5,en,en-uk;q=0.3';
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), 'en');
        // test unknown language
        $Context->SERVER['HTTP_ACCEPT_LANGUAGE']= 'de-CH,de;q=0.5';
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), null);
    }


    public function TestResolveBySession() {

        $Options= $this->BuildOptions(array(
            'Rules'=> array('Session'=>'Lang'),
        ));
        // test allowed language
        $Options['Services']['Session']->Set('Lang','fr');
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), 'fr');
        // test unknown language
        $Options['Services']['Session']->Set('Lang','gr');
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), null);
    }


    public function TestResolveByUser() {

        $Options= $this->BuildOptions(array(
            'Rules'=> array('User'=>'Lang'),
        ));
        // test allowed language
        $Options['Services']['Auth']->GetUser()->SetData('Lang','it');
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), 'it');
        // test unknown language
        $Options['Services']['Auth']->GetUser()->SetData('Lang','ro');
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), null);
    }




    public function TestResolveByEvent() {

        $Context= (new \Accent\AccentCore\RequestContext())->FromArray(array());
        $Options= $this->BuildOptions(array(
            'Rules'=> array('Event'=>'MyEventName'),
            'RequestContext'=> $Context,
        ));
        $Options['Services']['Event']->AttachListener('MyEventName', function($Event){
            $Segments= explode('/', trim($Event->GetOption('ServerVars.REQUEST_URI'), '/'));
            switch (reset($Segments)) {
                case 'asia': $Event->SetLanguage('zn'); break;
                case 'northamerica': $Event->SetLanguage('en'); break;
                case 'southamerica': $Event->SetLanguage('es'); break;
                case 'europe': $Event->SetLanguage('en'); break;
            }
        });
        // test allowed language
        $Context->SERVER['REQUEST_URI']= '/asia/contact';
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), 'zn');
        // test unknown language
        $Context->SERVER['REQUEST_URI']= '/africa/contact';
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), null);
    }



    public function TestResolveByDefault() {

        $Options= $this->BuildOptions(array(
            'Rules'=> array('Default'=>'gr'),
        ));
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), 'gr');
    }


    public function TestComplexResolving() {

        $Context= (new \Accent\AccentCore\RequestContext())->FromArray(array('SERVER'=>array(
            'HTTP_ACCEPT_LANGUAGE'=> 'de-CH,de;q=0.5',
            'REQUEST_URI'=> '/p1/p2/p3',
            'HTTP_HOST'=> 'www.mysite.it',
        )));
        $Options= $this->BuildOptions(array(
            'AllowedLanguages'=> array('en','fr','it'),
            'Rules'=> array(
                'Event'=> 'LangResolverForAdminPages',
                'Domain'=> array('site.fr'=>'fr', 'site.it'=>'it', 'site.us'=>'en'),
                'Session'=> 'Lang',
                'Browser'=> null,
                'Default'=> 'en',
            ),
            'RequestContext'=> $Context,
        ));
        $Options['Services']['Event']->AttachListener('LangResolverForAdminPages', function($Event){
            $Segments= explode('/', trim($Event->GetOption('ServerVars.REQUEST_URI'),'/'));
            if ($Segments[0] === 'admin') {
                $Event->SetLanguage('fr');
            }
        });
        // test: visiting italian domain, ordinary path, should be resolved by 'Domain' resolver
        $Context->SERVER['REQUEST_URI']= '/contact';
        $Context->SERVER['HTTP_HOST']= 'www.site.it';
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), 'it');
        // test: visiting italian domain, administration path, should be resolved by 'Event' resolver
        $Context->SERVER['REQUEST_URI']= '/admin/contact';
        $Context->SERVER['HTTP_HOST']= 'www.site.it';
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), 'fr');
        // test: visiting greek domain, ordinary path, should be resolved by last resolver
        $Context->SERVER['REQUEST_URI']= '/contact';
        $Context->SERVER['HTTP_HOST']= 'www.site.gr';
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), 'en');
        // test: remove 'Default' resolver, now resolving should fail
        unset($Options['Rules']['Default']);
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), null);
        // test: add 'de' as allowed language, should be resolved by 'Browser' resolver
        $Options['AllowedLanguages'][]= 'de';
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), 'de');
        // test: current user has stored language preference, 'Session' should override 'Browser' resolver now
        $Options['Services']['Session']->Set('Lang','fr');
        $LR= new LanguageResolver($Options);
        $this->assertEqual($LR->Resolve(), 'fr');
    }


    public function TestSetters() {

        $Options= $this->BuildOptions(array(
            'AllowedLanguages'=> array('en','fr'),
            'Rules'=> array('Domain'=> array('site.fr'=>'fr', 'site.us'=>'en')),
            'RequestContext'=> (new \Accent\AccentCore\RequestContext())->FromArray(array('SERVER'=>array('HTTP_HOST'=> 'site.es'))),
        ));
        $LR= new LanguageResolver($Options);
        // use setters
        $LR->SetAllowedLanguages(array('it','es'));
        $LR->GetRules()->Set('Domain', array('site.it'=>'it', 'site.es'=>'es'));
        // assert
        $this->assertEqual($LR->Resolve(), 'es');
    }

}


?>