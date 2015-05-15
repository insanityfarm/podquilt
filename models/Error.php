<?php

class Error {

    static function show($exception)
    {
        echo $exception;
        die;
    }

}