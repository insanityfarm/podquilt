<?php

class Error {

    public function show($exception)
    {
        echo $exception;
        die;
    }

}