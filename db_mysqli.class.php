<?php

namespace u4u;

include(dirname(__FILE__).'/auxiliar_classes.php');

/**
 * Extended MySQLi Parametrized DB Class
 *
 * db_mysqli.class.php, a MySQLi database access wrapper
 * Original idea from Mertol Kasanan, http://www.phpclasses.org/browse/package/5191.html
 * Optimized, tuned and fixed by unreal4u (Camilo Sperberg)
 *
 * @package db_mysqli
 * @version 4.0.1
 * @author Camilo Sperberg, http://unreal4u.com/
 * @author Mertol Kasanan
 * @license BSD License
 * @copyright 2009 - 2013 Camilo Sperberg
 *
 * @method int num_rows() num_rows() Returns the number of results from the query
 * @method mixed[] insert_id() insert_id($query, $args) Returns the insert id of the query
 * @method mixed[] query() query($query, $args) Returns false if query could not be executed, resultset otherwise
 */
class db_mysqli {
    /**
     * The version of this class
     * @var string
     */
    private $classVersion = '4.0.1';

    /**
     * Contains the actual DB connection instance
     * @var object
     */
    private $db = null;

    /**
     * Contains the prepared statement
     * @var object
     */
    private $stmt = null;

    /**
     * Internal indicator indicating whether we are connected to the database or not. Defaults to false
     * @var boolean
     */
    private $isConnected = false;

    /**
     * Internal statistics collector
     * @var array
     */
    private $stats = array();

    /**
     * Saves the last known error. Can be boolean false or string with error otherwise. Defaults to false
     * @var mixed[]
     */
    private $error = false;

    /**
     * Internal indicator to know whether we are in a transaction or not. Defaults to false
     * @var boolean
     */
    private $inTransaction = false;

    /**
     * Internal indicator to know whether we should rollback the current transaction or not. Defaults to false
     * @var boolean
     */
    private $rollback = false;

    /**
     * Counter of failed connections to the database. Defaults to 0
     * @var int
     */
    private $failedConnectionsCount = 0;

    /**
     * Provides a flag for knowing if we are in our own custom handler or not. Defaults to false
     * @var boolean
     */
    private $isWithinCustomErrorHandler = false;

    /**
     * Keep an informational array with all executed queries. Defaults to false
     * @var boolean
     */
    public $keepLiveLog = false;

    /**
     * Maintains statistics of the executed queries, but only if $this->keepLiveLog is set to true
     *
     * @see $this->keepLiveLog
     * @var array
     */
    public $dbLiveStats = array();

    /**
     * Maintains statistics exclusively from the errors in SQL
     * @var array
     */
    public $dbErrors = array();

    /**
     * Whether to disable throwing exceptions. Defaults to false
     * @var boolean
     */
    public $supressErrors = false;

    /**
     * Whether to throw errors on invalid queries. Defaults to false
     * @var boolean
     */
    public $throwQueryExceptions = false;

    /**
     * The number of maximum failed attempts trying to connect to the database. Defaults to 10
     * @var int
     */
    public $failedConnectionsTreshold = 10;

    /**
     * Indicator for number of executed queries. Defaults to 0
     * @var int
     */
    public $executedQueries = 0;

    /**
     * The constructor, optionally (default off) enter immediatly into transaction mode
     *
     * @param boolean $inTransaction Whether to begin a transaction, defaults to false
     */
    public function __construct($inTransaction=false) {
        if (version_compare(PHP_VERSION, '5.1.5', '<')) {
            $this->throwException('Sorry, class only valid for PHP &gt; 5.1.5, please consider upgrading to the latest version', __LINE__);
        }
        if ($inTransaction === true) {
            $this->begin_transaction();
        }
    }

    /**
     * Ends a transaction if needed committing remaining changes
     */
    public function __destruct() {
        if ($this->isConnected === true OR $this->inTransaction === true) {
            $this->end_transaction();
        }
    }

    /**
     * Controls all the calls to the class
     *
     * @param string $method The method to call
     * @param array $arg_array The data, such as the query. Can also by empty
     */
    public function __call($method, array $arg_array) {
        // Sets our own error handler (Defined in auxiliar_classes.php)
        $this->enableCustomErrorHandler();

        $this->error = false;

        // Some custom statistics
        $this->stats = array(
            'time'   => microtime(true),
            'memory' => memory_get_usage(),
        );

        switch ($method) {
            case 'num_rows':
            case 'insert_id':
            case 'query':
                $this->executedQueries++;
                $this->execute_query($arg_array);

                if ($method == 'query') {
                    $result = $this->execute_result_array($arg_array);
                } else {
                    $resultInfo = $this->execute_result_info($arg_array);
                    $result = $resultInfo[$method];
                }
            break;
            default:
                $result = 'Method not supported!';
            break;
        }

        $this->logStatistics($this->stats, $arg_array, $result, $this->error);

        // Restore whatever error handler we had before calling this class
        $this->restoreErrorHandler();

        // Finally, return our result
        return $result;
    }

    /**
     * Magic get method. Will always return the number of rows
     *
     * @param string $v Any identifier supported by @link $this->execute_result_info()
     * @return array Returns an array with the requested index (supported by execute_result_info)
     */
    public function __get($v='') {
        $resultInfo = $this->execute_result_info();

        if (!isset($resultInfo[$v])) {
            $resultInfo[$v] = 'Method not supported!';
        }

        return $resultInfo[$v];
    }

    /**
     * Magic toString method. Will return current version of this class
     *
     * @return string
     */
    public function __toString() {
        return 'db_mysqli.class.php v'.$this->classVersion.' by Camilo Sperberg - http://unreal4u.com/';
    }

    /**
     * Will return MySQL version or client version
     *
     * @param boolean $clientInformation Set to true to return client information. Defaults to false
     * @return string Returns a string with the client version
     */
    public function version($clientInformation=false) {
        $result = false;

        if (empty($clientInformation)) {
            $result = $this->query('SELECT VERSION()');
            if (!empty($result)) {
                $result = $result[0]['VERSION()'];
            }
        } else {
            $this->registerConnection();
            $temp = explode(' ', $this->db->client_info);
            $result = $temp[1];
        }

        return $result;
    }

    /**
     * Begins a transaction, optionally with other credentials
     *
     * Note: This function will set throwQueryExceptions to true because without it we have no way of knowing that the
     * transaction actually succeeded or not.
     *
     * @param string $databaseName The database name
     * @param string $host The host of the MySQL server
     * @param string $username The username
     * @param string $passwd The password
     * @param int $port The port to which MySQL is listening to
     *
     * @return boolean Returns whether we are or not in a transaction
     */
    public function begin_transaction($databaseName='', $host='', $username='', $passwd='', $port=0) {
        if ($this->inTransaction === false) {
            if ($this->registerConnection($databaseName, $host, $username, $passwd, $port)) {
                $this->inTransaction = true;
                $this->throwQueryExceptions = true;
                $this->db->autocommit(false);
            }
        }

        return $this->inTransaction;
    }

    /**
     * Ends a transaction
     *
     * @return boolean Returns whether we are or not in a transaction
     */
    public function end_transaction() {
        if ($this->inTransaction === true) {
            if ($this->rollback === false) {
                $this->db->commit();
            } else {
                $this->db->rollback();
                $this->rollback = false;
                $result = false;
            }
            $this->db->autocommit(true);
            $this->inTransaction = false;
        }

        return $this->inTransaction;
    }

    /**
     * Opens a new connection to a MySQL database
     *
     * If you want to open another connection, use this method and provide the necesary credentials. Provided
     * credentials will overwrite default values. Note that database name is in first place!
     * This function will immediatly establish a connection to the database and won't wait for the first query to be
     * executed.
     *
     * @param string $databaseName The database name
     * @param string $host The host of the MySQL server
     * @param string $username The username
     * @param string $passwd The password
     * @param int $port The port to which MySQL is listening to
     * @return boolean Returns true if connection is established, false otherwise
     */
    public function registerConnection($databaseName='', $host='', $username='', $passwd='', $port=0) {
        $return = false;

        if ($this->isConnected === false) {
            $this->enableCustomErrorHandler();
            if (empty($host)) {
                $host = DB_MYSQLI_HOST;
            }

            if (empty($username)) {
                $username = DB_MYSQLI_USER;
            }

            if (empty($passwd)) {
                $passwd = DB_MYSQLI_PASS;
            }

            if (empty($databaseName)) {
                $databaseName = DB_MYSQLI_NAME;
            }

            if (empty($port)) {
                $port = DB_MYSQLI_PORT;
            }

            $this->connectToDatabase($host, $username, $passwd, $databaseName, $port);
            $this->restoreErrorHandler();
        }

        return $this->isConnected;
    }

    /**
     * This method will open a connection to the database
     *
     * @return boolean Returns value indicating whether we are connected or not
     */
    private function connectToDatabase($host, $username, $passwd, $database, $port) {
        if ($this->isConnected === false) {
            if ($this->failedConnectionsCount < $this->failedConnectionsTreshold) {
                try {
                    // Always capture all errors from the singleton connection
                    $db_connect = mysql_connect::getInstance($host, $username, $passwd, $database, $port);
                    $this->db = $db_connect->db;
                    $this->isConnected = true;
                } catch (databaseException $e) {
                    // Log the error in our internal error collector and re-throw the exception
                    $this->failedConnectionsCount++;
                    $this->logError(null, 0, 'fatal', $e->getMessage());
                    $this->throwException($e->getMessage(), $e->getLine());
                }
            } else {
                $this->throwException('Too many attempts to connect to database, not trying anymore', __LINE__);
            }
        }

        return $this->isConnected;
    }

    /**
     * Function that checks what type is the data we are trying to insert
     *
     * Supported bind types (http://php.net/manual/en/mysqli-stmt.bind-param.php):
     *  i   corresponding variable has type integer
     *  d   corresponding variable has type double
     *  s   corresponding variable has type string
     *  b   corresponding variable is a blob and will be sent in packets
     *
     * @TODO Support for blob type data (will now go through string type)
     *
     * @param array $arg_array All values that the query will be handling
     * @return array Returns an array with a string of types and another one with the corrected values
     */
    protected function castValues(array $arg_array=null) {
        $types = '';
        if (!empty($arg_array)) {
            foreach ($arg_array as $v) {
                switch ($v) {
                    // @TODO Check the following condition very well!
                    // Empty STRING
                    case '':
                        $types .= 's';
                    break;
                    // All "integer" types
                    case is_null($v):
                    case is_bool($v):
                    case is_int($v):
                        $types .= 'i';
                    break;
                    // Save a float type data
                    case is_float($v):
                        $types .= 'd';
                    break;
                    // Save a string typed data
                    case is_string($v):
                    #default: // @FIXME Disabled until good testing of consequences
                        $types .= 's';
                    break;
                }
            }
        }

        $returnArray = array(
            'types' => $types,
            'arg_array' => $arg_array,
        );

        return $returnArray;
    }

    /**
     * Function that prepares and binds the query
     *
     * @param $arg_array array Contains the binded values
     * @return boolean Whether we could execute the query or not
     */
    private function execute_query(array $arg_array=null) {
        $executeQuery = false;

        if ($this->registerConnection()) {
            $sqlQuery = array_shift($arg_array);

            $tempArray = $this->castValues($arg_array);
            $types     = $tempArray['types'];
            $arg_array = $tempArray['arg_array'];
            unset($tempArray);

            if (isset($this->stmt)) {
                unset($this->stmt);
            }

            $this->stmt = $this->db->prepare($sqlQuery);
            if (!is_object($this->stmt)) {
                $this->logError($sqlQuery, $this->db->errno, 'fatal', $this->db->error);
            }

            if (is_array($arg_array)) {
                array_unshift($arg_array, $types);
                if (empty($this->error)) {
                    if (!$executeQuery = @call_user_func_array(array($this->stmt, 'bind_param'), $this->makeValuesReferenced($arg_array))) {
                        $this->logError($sqlQuery, $this->stmt->errno, 'fatal', 'Failed to bind. Do you have equal parameters for all the \'?\'?');
                        $executeQuery = false;
                    }
                }
            } else {
                if (!empty($sqlQuery)) {
                    $executeQuery = true;
                }
            }

            if ($executeQuery AND is_object($this->stmt)) {
                $this->stmt->execute();
                $this->stmt->store_result();
            } elseif (!$this->error) {
                $this->logError($sqlQuery, 0, 'non-fatal', 'General error: Bad query or no query at all');
            }
        }

        return $executeQuery;
    }

    /**
     * Returns data like the number of rows and last insert id
     *
     * @param array $arg_array Contains the binded values
     * @return array Can return affected rows, number of rows or last id inserted.
     */
    private function execute_result_info(array $arg_array=null) {
        $result = array();

        if (!$this->error) {
            if ($this->db->affected_rows > 0)
                $num_rows = $this->db->affected_rows;
            else {
                if (isset($this->db->num_rows)) {
                    $num_rows = $this->db->num_rows;
                } else {
                    $num_rows = 0;
                }
            }
            $result['num_rows'] = $num_rows;
            $result['insert_id'] = $this->db->insert_id;
        }

        return $result;
    }

    /**
     * Establishes the $result array: the data itself
     *
     * @param array $arg_array
     * @return mixed Returns the array with data, false if there was an error present or int with errno if an error at this stage happens
     */
    private function execute_result_array(array $arg_array) {
        $result = false;

        if (!$this->error) {
            $result = array();
            if ($this->stmt->error) {
                $this->logError(null, $this->stmt->errno, 'fatal', $this->stmt->error);
                return false;
            }

            $result_metadata = $this->stmt->result_metadata();
            if (is_object($result_metadata)) {
                $rows = array();
                $fields = $result_metadata->fetch_fields();
                foreach($fields AS $field) {
                    $rows[$field->name] = null;
                    $dataTypes[$field->name] = $field->type;
                    $params[] =& $rows[$field->name];
                }

                call_user_func_array(array(
                    $this->stmt,
                    'bind_result'
                ), $params);

                while ($this->stmt->fetch()) {
                    foreach ($rows as $key => $val) {
                        $c[$key] = $val;
                        // Fix for boolean data types: hard-detect these and set them explicitely as boolean
                        // @TODO Check if date types get interpreted correctly, ideal would be use PHP's DateTime object
                        if ($dataTypes[$key] == 16) {
                            $c[$key] = (bool)$val;
                        }
                    }
                    $result[] = $c;
                }
            } elseif ($this->stmt->errno == 0) {
                $result = true;
            } else {
                $result = $this->stmt->errno;
            }
        }

        return $result;
    }

    /**
     * Enables our own intern error handler
     *
     * @link http://php.net/manual/en/function.set-error-handler.php Documentation on returned values
     * @link http://www.tyrael.hu/2011/06/26/performance-of-error-handling-in-php/ Benchmark on set_error_handler
     *
     * Conclusion: (...) the overhead of having a custom error handler is almost negligible if it isn’t called. (...) if
     * you have an error and a custom error handler which gets executed, that yields for a ~10X performance loss,
     * regardless of using the suppression operator or not.
     *
     * AKA: If your queries do have a lot of errors, then this will slow things down. Otherwise, you can capture them
     * and do whatever you want, such as logging them or mailing the faulty queries to yourself.
     *
     * @return mixed Returns whatever value set_error_handler returns or false if custom error handler is already set
     */
    private function enableCustomErrorHandler() {
        $return = false;
        if ($this->isWithinCustomErrorHandler === false) {
            $this->isWithinCustomErrorHandler = true;
            $return = set_error_handler(array('\\'.__NAMESPACE__.'\\databaseErrorHandler', 'handleError'));
        }

        return $return;
    }

    /**
     * Restores the previous setted error handler
     *
     * @return boolean Returns true if error handler has been restored or false if no custom error handler had been previously set
     */
    private function restoreErrorHandler() {
        $return = false;
        if ($this->isWithinCustomErrorHandler === true) {
            $this->isWithinCustomErrorHandler = false;
            $return = restore_error_handler();
        }

        return $return;
    }

    /**
     * Throws an exception if these are enabled
     *
     * @param string $msg The string to print within the exception
     * @param int $line The line in which the exception ocurred
     * @throws databaseException
     * @return boolean Returns always false (only when supressErrors is active)
     */
    protected function throwException($msg='', $line=0) {
        if (empty($this->supressErrors)) {
            throw new databaseException($msg, $line, __FILE__);
        }

        return false;
    }

    /**
     * Throws exception on query error
     *
     * @param string $query
     * @param string $mysqlErrorString
     * @param int $mysqlErrno
     * @throws queryException
     */
    protected function throwQueryException($query='', $mysqlErrorString='', $mysqlErrno=0) {
        if (!empty($this->throwQueryExceptions)) {
            throw new queryException($query, $mysqlErrorString, $mysqlErrno);
        }

        return false;
    }

    /**
     * Function that logs all errors
     *
     * @param string $query The query to log
     * @param int $errno The error number to log
     * @param string $type Whether the error is fatal or non-fatal
     * @param string $error The error description
     * @return boolean Always returns true.
     */
    private function logError($query, $errno, $type='non-fatal', $error=null) {
        if (empty($error)) {
            $complete_error = '(not specified)';
        } else if ($type == 'non-fatal') {
            $complete_error = '[NOTICE] ' . $error;
        } else {
            $complete_error = '[ERROR] ' . $error;
            $this->rollback = true;
        }

        $this->dbErrors[] = array(
            'query'        => $query,
            'query_number' => $this->executedQueries,
            'errno'        => $errno,
            'type'         => $type,
            'error'        => $complete_error
        );

        if ($type == 'fatal') {
            $this->error = '[' . $errno . '] ' . $error;
            $this->throwQueryException($query, $error, $errno);
        }

        return true;
    }

    /**
     * Function that executes after each query
     *
     * @param array $stats
     * @param array $arg_array
     * @param array $result
     * @param mixed[] $error
     * @return boolean Returns true if logentry could be made, false otherwise
     */
    private function logStatistics(array $stats, array $arg_array, $result, $error) {
        $return = false;
        if ($this->keepLiveLog === true) {
            $stats = array(
                'memory' => memory_get_usage() - $stats['memory'],
                'time'   => number_format(microtime(true) - $stats['time'], 5, ',', '.'),
            );

            if ($error == false) {
                $errorString = 'FALSE';
            } else {
                $errorString = 'TRUE';
            }

            $inTransaction = 'FALSE';
            if ($this->inTransaction === true) {
                $inTransaction = 'TRUE';
            }

            $resultInfo = $this->execute_result_info($arg_array);
            $query      = reset($arg_array);
            if (!isset($resultInfo['num_rows'])) {
                $resultInfo['num_rows'] = 0;
            }

            $this->dbLiveStats[] = array(
                'query'              => $query,
                'number_results'     => $resultInfo['num_rows'],
                'time'               => $stats['time'] . ' (seg)',
                'memory'             => $stats['memory'] . ' (bytes)',
                'error'              => $error,
                'within_transaction' => $inTransaction,
            );

            $return = true;
        }

        return $return;
    }

    /**
     * Creates an referenced representation of an array
     *
     * @author Hugo Simon http://www.phpclasses.org/discuss/package/5812/thread/5/
     * @param array $arr The array that creates a referenced copy
     * @return array A referenced copy of the original array
     */
    private function makeValuesReferenced($arr) {
        $refs = array();
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }
}

