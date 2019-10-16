<?php namespace Accent\Router\Test;


class Demo_ErrorPage {


    public static function Error403() {

        echo '<h1>Forbidden</h1>';
    }

    public static function Error404() {

        echo '<h1>Not found</h1>';
    }

    public static function Error501() {

        echo '<h1>Not implemented</h1>';
    }

}

?>