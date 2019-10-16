<?php namespace Accent\Test;


class PhpStream {


    protected $Pos= 0;
    static public $Body= '';


    public function stream_open($path, $mode, $options, &$opened_path) {

        return isset(self::$Body);
    }


    public function stream_read($count) {

        $Result= substr(self::$Body, $this->Pos, $count);
        $this->Pos += strlen($Result);
        return $Result;
    }


    public function stream_eof() {

        return strlen(self::$Body) === 0;
    }


    public function stream_stat() {

        return array();
    }

}

?>