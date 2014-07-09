<?php

namespace unreal4u\exceptions;

/**
 * This class will throw this type of exceptions
 *
 * @package dbmysqli
 * @author Camilo Sperberg - http://unreal4u.com/
 */
class database extends \ErrorException {
    public function __construct($errstr, $errline=0, $errfile='') {
        parent::__construct($errstr, 0, 0, $errfile, $errline);
    }
}
