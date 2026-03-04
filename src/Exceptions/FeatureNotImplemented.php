<?php
namespace PatrykNamyslak\PatForm\Exceptions;

use Exception;


class FeatureNotImplemented extends Exception{
    public function __construct(){
        $this->message = "This Feature was not implemented yet";
    }
}