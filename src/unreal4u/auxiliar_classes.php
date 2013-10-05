<?php

namespace unreal4u;

/**
 * This class will throw this type of exceptions
 *
 * @package dbmysqli
 * @author Camilo Sperberg - http://unreal4u.com/
 */
class databaseException extends \ErrorException {
    public function __construct($errstr, $errline=0, $errfile='') {
        parent::__construct($errstr, 0, 0, $errfile, $errline);
    }
}

/**
 * If there is an error within the query, the class will throw (optionally) this exception
 *
 * @author Camilo Sperberg - http://unreal4u.com/
 * @package db_mysqli
 */
class queryException extends \Exception {
    public function __construct($query, $errstr, $errno) {
        // Construct a error message and parent-construct the exception
        $message = $errstr;
        if (!empty($query)) {
            $message .= '; Query: '.$query;
        }

        parent::__construct($message, $errno);
    }
}

/**
 * This class will handle all errors for us
 *
 * @author Camilo Sperberg - http://unreal4u.com/
 * @package db_mysqli
 */
class databaseErrorHandler {
    public static function handleError($errno, $errstr, $errfile='', $errline=0, $errcontext=array()) {
        throw new DatabaseException($errstr, $errline, $errfile);
    }
}

/**
 * Singleton class that holds the connection to MySQL. Do not manually call this class!
 *
 * @author Mertol Kasanan
 * @author Camilo Sperberg - http://unreal4u.com/
 * @package db_mysqli
 */
class mysql_connect {
    private static $instance = array();
    private $isConnected = false;
    private $supressErrors = false;

    /**
     * Get a singleton instance
     */
    public static function getInstance($host, $username, $passwd, $database, $port) {
        $identifier = md5($host.$username.$passwd.$database.$port);
        if (!isset(self::$instance[$identifier])) {
            $c = __CLASS__;
            self::$instance[$identifier] = new $c($host, $username, $passwd, $database, $port);
        }

        return self::$instance[$identifier];
    }

    /**
     * Don't allow cloning
     *
     * @throws Exception If trying to clone
     */
    public function __clone() {
        $this->throwException('We can only declare this class once! Do not try to clone it', __LINE__);
    }

    /**
     * Tries to make the connection
     *
     * @throws Exception If any problem with the database
     */
    public function __construct($host, $username, $passwd, $database, $port) {
        try {
            $this->db = new \mysqli($host, $username, $passwd, $database, $port);
            if (mysqli_connect_error()) {
                $this->throwException(mysqli_connect_error(), __LINE__);
            }
        } catch (\Exception $e) {
            $this->throwException(mysqli_connect_error(), __LINE__);
        }

        $this->isConnected = true;
        $this->db->set_charset(DB_MYSQLI_CHAR);
    }

    /**
     * Throws an exception if these are enabled
     *
     * @param string $msg The string to print within the exception
     * @throws databaseException
     * @return boolean Returns always false (only when supressErrors is active)
     */
    private function throwException($msg, $line=0) {
        throw new databaseException('Check database server is running. MySQL error: '.$msg, $line, __FILE__);

        return false;
    }

    /**
     * Gracefully closes the connection (if there is an open one)
     */
    public function __destruct() {
        if ($this->isConnected === true) {
            $this->db->close();
            $this->isConnected = false;
        }
    }
}

