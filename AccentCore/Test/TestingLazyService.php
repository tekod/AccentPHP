<?php namespace Accent\AccentCore\Test;

/**
 * Part of the AccentPHP project.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


/**
 * Simple test service with creating "marker" file during initialization.
 */


class TestingLazyService {


    public function __construct(array $Options) {

        file_put_contents(__DIR__.'/LazyFile.dump', '');
    }


    public function DoSomething() {
        //...
    }

}

