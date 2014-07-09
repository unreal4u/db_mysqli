<?php

namespace unreal4u;

/**
 * Singleton class that holds the connection to MySQL. Do not manually call this class!
 *
 * @author Mertol Kasanan
 * @author Camilo Sperberg - http://unreal4u.com/
 * @package db_mysqli
 */
class mysql_connect {
    /**
     * Array of database instances
     * @var array
     */
    private static $_instance = array();

    /**
     * Internal connection flag
     * @var boolean
     */
    private $_isConnected = false;

    /**
     * Get a singleton instance
     */
    public static function getInstance($host, $username, $passwd, $database, $port) {
        $identifier = md5($host.$username.$passwd.$database.$port);
        if (!isset(self::$_instance[$identifier])) {
            $c = __CLASS__;
            self::$_instance[$identifier] = new $c($host, $username, $passwd, $database, $port);
        }

        return self::$_instance[$identifier];
    }

    /**
     * Don't allow cloning
     *
     * @throws Exception If trying to clone
     */
    public function __clone() {
        $this->_throwException('We can only declare this class once! Do not try to clone it', __LINE__);
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
                $this->_throwException(mysqli_connect_error(), __LINE__);
            }
        } catch (\Exception $e) {
            $this->_throwException(mysqli_connect_error(), __LINE__);
        }

        $this->_isConnected = true;
        $this->db->set_charset(DB_MYSQLI_CHAR);
    }

    /**
     * Throws an exception if these are enabled
     *
     * @param string $msg The string to print within the exception
     * @throws databaseException
     */
    private function _throwException($msg, $line=0) {
        throw new exceptions\database('Check database server is running. MySQL error: '.$msg, $line, __FILE__);
    }

    /**
     * Gracefully closes the connection (if there is an open one)
     */
    public function __destruct() {
        if ($this->_isConnected === true) {
            $this->db->close();
            $this->_isConnected = false;
        }
    }
}
