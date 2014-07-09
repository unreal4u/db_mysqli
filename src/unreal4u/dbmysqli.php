<?php

namespace unreal4u;

include(dirname(__FILE__).'/auxiliar_classes.php');
include(dirname(__FILE__).'/exceptions/database.php');
include(dirname(__FILE__).'/exceptions/query.php');

/**
 * Extended MySQLi Parametrized DB Class
 *
 * dbmysqli.php, a MySQLi database access wrapper
 * Original idea from Mertol Kasanan, http://www.phpclasses.org/browse/package/5191.html
 * Optimized, tuned and fixed by unreal4u (Camilo Sperberg)
 *
 * @package dbmysqli
 * @version 5.0.0
 * @author Camilo Sperberg, http://unreal4u.com/
 * @author Mertol Kasanan
 * @license BSD License
 * @copyright 2009 - 2014 Camilo Sperberg
 *
 * @method int numRows() numRows() Returns the number of results from the query
 * @method mixed[] insertId() insertId($query, $args) Returns the insert id of the query
 * @method mixed[] query() query($query, $args) Returns false if query could not be executed, resultset otherwise
 */
class dbmysqli {
    /**
     * The version of this class
     * @var string
     */
    private $_classVersion = '5.0.0';

    /**
     * Contains the actual DB connection instance
     * @var object
     */
    private $_db = null;

    /**
     * Contains the prepared statement
     * @var object
     */
    private $_stmt = null;

    /**
     * Internal indicator indicating whether we are connected to the database or not. Defaults to false
     * @var boolean
     */
    private $_isConnected = false;

    /**
     * Internal statistics collector
     * @var array
     */
    private $_stats = array();

    /**
     * Saves the last known error. Can be boolean false or string with error otherwise. Defaults to false
     * @var mixed[]
     */
    private $_error = false;

    /**
     * Internal indicator to know whether we are in a transaction or not. Defaults to false
     * @var boolean
     */
    private $_inTransaction = false;

    /**
     * Internal indicator to know whether we should rollback the current transaction or not. Defaults to false
     * @var boolean
     */
    private $_rollback = false;

    /**
     * Counter of failed connections to the database. Defaults to 0
     * @var int
     */
    protected $_failedConnectionsCount = 0;

    /**
     * The number of maximum failed attempts trying to connect to the database. Defaults to 10
     * @var int
     */
    public $failedConnectionsTreshold = 10;

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
     * Whether to throw errors on invalid queries. Defaults to false
     * @var boolean
     */
    public $throwQueryExceptions = false;

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
        if ($inTransaction === true) {
            $this->beginTransaction();
        }
    }

    /**
     * Ends a transaction if needed committing remaining changes
     */
    public function __destruct() {
        if ($this->_isConnected === true || $this->_inTransaction === true) {
            $this->endTransaction();
        }
    }

    /**
     * Controls all the calls to the class
     *
     * @param string $method The method to call
     * @param array $arg_array The data, such as the query. Can also by empty
     */
    public function __call($method, array $arg_array=null) {
        $this->_error = false;

        // Some custom statistics
        $this->_stats = array(
            'time'   => microtime(true),
            'memory' => memory_get_usage(),
        );

        switch ($method) {
            case 'numRows':
            case 'insertId':
            case 'query':
                $this->executedQueries++;
                $this->_executeQuery($arg_array);

                if ($method == 'query') {
                    $result = $this->_executeResultArray($arg_array);
                } else {
                    $resultInfo = $this->_executeResultInfo($arg_array);
                    $result = $resultInfo[$method];
                }
            break;
            default:
                $result = sprintf('Method not supported!');
            break;
        }

        $this->_logStatistics($arg_array, $result);

        // Finally, return our result
        return $result;
    }

    /**
     * Magic get method. Will always return the number of rows
     *
     * @param string $v Any identifier supported by @link $this->_executeResultInfo()
     * @return array Returns an array with the requested index (supported by _executeResultInfo)
     */
    public function __get($v='') {
        $resultInfo = $this->_executeResultInfo();

        if (!isset($resultInfo[$v])) {
            $resultInfo[$v] = sprintf('Method not supported!');
        }

        return $resultInfo[$v];
    }

    /**
     * Magic toString method. Will return current version of this class
     *
     * @return string
     */
    public function __toString() {
        return basename(__FILE__).' v'.$this->_classVersion.' by Camilo Sperberg - http://unreal4u.com/';
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
    public function beginTransaction($databaseName='', $host='', $username='', $passwd='', $port=0) {
        if ($this->_inTransaction === false) {
            if ($this->registerConnection($databaseName, $host, $username, $passwd, $port)) {
                $this->_inTransaction = true;
                $this->throwQueryExceptions = true;
                $this->_db->autocommit(false);
            }
        }

        return $this->_inTransaction;
    }

    /**
     * Ends a transaction
     *
     * @return boolean Returns whether we are or not in a transaction
     */
    public function endTransaction() {
        if ($this->_inTransaction === true) {
            if ($this->_rollback === false) {
                $this->_db->commit();
            } else {
                $this->_db->rollback();
                $this->_rollback = false;
                $result = false;
            }
            $this->_db->autocommit(true);
            $this->_inTransaction = false;
        }

        return $this->_inTransaction;
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

        if ($this->_isConnected === false) {
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

            $this->_connectToDatabase($host, $username, $passwd, $databaseName, $port);
        }

        return $this->_isConnected;
    }

    /**
     * This method will open a connection to the database
     *
     * @return boolean Returns value indicating whether we are connected or not
     */
    private function _connectToDatabase($host, $username, $passwd, $database, $port) {
        if ($this->_isConnected === false) {
            if ($this->_failedConnectionsCount < $this->failedConnectionsTreshold) {
                try {
                    mysqli_report(MYSQLI_REPORT_STRICT);
                    // Always capture all errors from the singleton connection
                    $db_connect = mysql_connect::getInstance($host, $username, $passwd, $database, $port);
                    $this->_db = $db_connect->db;
                    $this->_isConnected = true;
                } catch (\mysqli_sql_exception $e) {
                    var_dump('inside here.....');
                    // Log the error in our internal error collector and re-throw the exception
                    $this->_failedConnectionsCount++;
                    mysqli_report(MYSQLI_REPORT_OFF);
                    $this->_logError(null, 0, 'fatal', $e->getMessage());
                    $this->_throwException($e->getMessage(), $e->getLine());
                }
                mysqli_report(MYSQLI_REPORT_OFF);
            } else {
                $this->_throwException('Too many attempts to connect to database, not trying anymore', __LINE__);
            }
        }

        return $this->_isConnected;
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
    protected function _castValues(array $arg_array=null) {
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
    private function _executeQuery(array $arg_array=null) {
        $executeQuery = false;

        if ($this->registerConnection()) {
            $sqlQuery = array_shift($arg_array);

            $tempArray = $this->_castValues($arg_array);
            $types     = $tempArray['types'];
            $arg_array = $tempArray['arg_array'];
            unset($tempArray);

            if (isset($this->_stmt)) {
                $this->_stmt = null;
            }

            $this->_stmt = $this->_db->prepare($sqlQuery);
            if (!is_object($this->_stmt)) {
                $this->_logError($sqlQuery, $this->_db->errno, 'fatal', $this->_db->error);
            }

            if (!empty($arg_array)) {
                array_unshift($arg_array, $types);
                if (empty($this->_error)) {
                    if (!$executeQuery = @call_user_func_array(array($this->_stmt, 'bind_param'), $this->_makeValuesReferenced($arg_array))) {
                        $this->_logError($sqlQuery, $this->_stmt->errno, 'fatal', 'Failed to bind. Do you have equal parameters for all the \'?\'?');
                        $executeQuery = false;
                    }
                }
            } else {
                if (!empty($sqlQuery)) {
                    $executeQuery = true;
                }
            }

            if ($executeQuery AND is_object($this->_stmt)) {
                $this->_stmt->execute();
                $this->_stmt->store_result();
            } elseif (!$this->_error) {
                $this->_logError($sqlQuery, 0, 'non-fatal', 'General error: Bad query or no query at all');
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
    private function _executeResultInfo(array $arg_array=null) {
        $result = array();

        if (!$this->_error) {
            if ($this->_db->affected_rows > 0)
                $numRows = $this->_db->affected_rows;
            else {
                if (isset($this->_db->num_rows)) {
                    $numRows = $this->_db->num_rows;
                } else {
                    $numRows = 0;
                }
            }
            $result['numRows'] = $numRows;
            $result['insertId'] = $this->_db->insert_id;
        }

        return $result;
    }

    /**
     * Establishes the $result array: the data itself
     *
     * @param array $arg_array
     * @return mixed Returns the array with data, false if there was an error present or int with errno if an error at this stage happens
     */
    private function _executeResultArray(array $arg_array) {
        $result = false;

        if (!$this->_error) {
            if ($this->_stmt->error) {
                $this->_logError(null, $this->_stmt->errno, 'fatal', $this->_stmt->error);
                return false;
            }

            $result_metadata = $this->_stmt->result_metadata();
            if (is_object($result_metadata)) {
                $rows = array();
                $fields = $result_metadata->fetch_fields();
                foreach($fields AS $field) {
                    $rows[$field->name] = null;
                    $dataTypes[$field->name] = $field->type;
                    $params[] =& $rows[$field->name];
                }
                $result = array();

                call_user_func_array(array(
                    $this->_stmt,
                    'bind_result'
                ), $params);

                while ($this->_stmt->fetch()) {
                    foreach ($rows as $key => $val) {
                        $c[$key] = $val;
                        // Fix for boolean data types: hard-detect these and set them explicitely as boolean
                        // Complete list: http://www.php.net/manual/en/mysqli-result.fetch-fields.php#113949
                        switch ($dataTypes[$key]) {
                            case 7: // timestamp
                            case 10: // date
                            case 11: // time
                            case 12: // datetime
                                if ($val !== null) {
                                    $c[$key] = new \DateTime($val);
                                }
                                break;
                            case 16: // bit
                                $c[$key] = (bool)$val;
                                break;
                            case 4: // float
                            case 5: // double
                            case 246: // decimal
                                $c[$key] = floatval($val);
                                break;
                            // Following are just as quick reference for later (maybe)
                            #case 1: // tinyint
                            #case 2: // smallint
                            #case 3: // int
                            #case 8: // bigint
                            #case 9: // mediumint
                            #case 13: // year
                            #case 252: // Text related field
                            #case 253: // varchar
                            #case 254: // char
                            #default:
                            #    break;
                        }
                    }
                    $result[] = $c;
                }

                $result = \SplFixedArray::fromArray($result);
            } elseif ($this->_stmt->errno == 0) {
                $result = true;
            } else {
                $result = $this->_stmt->errno;
            }
        }

        return $result;
    }

    /**
     * Throws an exception if these are enabled
     *
     * @param string $msg The string to print within the exception
     * @param int $line The line in which the exception ocurred
     * @throws databaseException
     */
    protected function _throwException($msg='', $line=0) {
        throw new exceptions\database($msg, $line, __FILE__);
    }

    /**
     * Throws exception on query error
     *
     * @param string $query
     * @param string $mysqlErrorString
     * @param int $mysqlErrno
     * @throws queryException
     */
    protected function _throwQueryException($query='', $mysqlErrorString='', $mysqlErrno=0) {
        throw new exceptions\query($query, $mysqlErrorString, $mysqlErrno);
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
    private function _logError($query, $errno, $type='non-fatal', $error=null) {
        if (empty($error)) {
            $completeError = '(not specified)';
        } else if ($type == 'non-fatal') {
            $completeError = '[NOTICE] ' . $error;
        } else {
            $completeError = '[ERROR] ' . $error;
            $this->_rollback = true;
        }

        $this->dbErrors[] = array(
            'query'        => $query,
            'query_number' => $this->executedQueries,
            'errno'        => $errno,
            'type'         => $type,
            'error'        => $completeError
        );

        if ($type == 'fatal') {
            $this->_error = '[' . $errno . '] ' . $error;
            $this->_throwQueryException($query, $error, $errno);
        }

        return true;
    }

    /**
     * Function that executes after each query
     *
     * @param array $arg_array
     * @param array $result
     * @return boolean Returns true if logentry could be made, false otherwise
     */
    private function _logStatistics(array $arg_array, $result) {
        $return = false;
        if ($this->keepLiveLog === true) {
            $stats = array(
                'memory' => memory_get_usage() - $this->_stats['memory'],
                'time'   => number_format(microtime(true) - $this->_stats['time'], 5, ',', '.'),
            );

            if ($this->_error == false) {
                $errorString = 'FALSE';
            } else {
                $errorString = 'TRUE';
            }

            $inTransaction = 'FALSE';
            if ($this->_inTransaction === true) {
                $inTransaction = 'TRUE';
            }

            $resultInfo = $this->_executeResultInfo($arg_array);
            $query      = reset($arg_array);
            if (!isset($resultInfo['numRows'])) {
                $resultInfo['numRows'] = 0;
            }

            $this->dbLiveStats[] = array(
                'query'              => $query,
                'number_results'     => $resultInfo['numRows'],
                'time'               => $stats['time'] . ' (seg)',
                'memory'             => $stats['memory'] . ' (bytes)',
                'error'              => $this->_error,
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
    private function _makeValuesReferenced(array $arr=null) {
        $refs = null;
        if (!empty($arr)) {
            $refs = array();
            foreach ($arr as $key => $value) {
                $refs[$key] = &$arr[$key];
            }
        }
        return $refs;
    }
}

