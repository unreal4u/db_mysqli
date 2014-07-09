<?php

namespace unreal4u\exceptions;

/**
 * If there is an error within the query, the class will throw this exception
 *
 * @author Camilo Sperberg - http://unreal4u.com/
 * @package db_mysqli
 */
class query extends \Exception {
    public function __construct($query, $errstr, $errno) {
        // Construct a error message and parent-construct the exception
        $message = $errstr;
        if (!empty($query)) {
            $message .= '; Query: '.$query;
        }

        parent::__construct($message, $errno);
    }
}
