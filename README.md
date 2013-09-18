db_mysqli.class.php
======

Credits
--------

This class is made by unreal4u (Camilo Sperberg). [http://unreal4u.com/](unreal4u.com). However, the initial idea isn't 
mine, so I would like to thank Mertol Kasanan, this class is based on his work.
See [http://www.phpclasses.org/browse/package/5191.html](http://www.phpclasses.org/browse/package/5191.html) for details.

About this class
--------

* It receives parametrized and simple SQL queries.
* It creates 3 arrays: one containing all data, and another one that contains some statistics. Optionally, it logs all errors into another array and also into an valid XML file.
* The DB connection made is singleton, that means only one connection is made for all your queries, even if you have more than 1 instance. The connection is established on demand, not when you initialize the class.

Detailed description
---------

This package implements a MySQL database access wrapper using the MySQLi extension.

There is class that manages MySQL database access connections so only one connection is established during the same PHP script execution.

Another class implements other database access functions like executing queries with prepared queries, measuring the time the queries take to execute and the memory usage, retrieving query results into arrays, the number of result rows, last inserted record identifier and log executed queries to a valid XML log file or directly into your page.

If the query takes just too long, you can cache the query result into an XML file, and you can also handle errors.

This package has been extensivily tested with xDebug, APC and Suhosin so that no errors are present.

Basic usage
----------

<pre>include('config.php'); // Please see below for explanation
include('db_mysqli.class.php');
$dbLink = new db_mysqli();
$id_user = 23;
$username = 'unreal4u';
$aResult = $dbLink->query('SELECT id,username FROM users WHERE id = ? AND username = ?',$id_user,$username);</pre>

* Congratulations! `$aResult` haves the result of your query!
* Now you can do anything you want with the array, one of the easiest methods to go trough it is a foreach:
<pre>foreach($aResult AS $a) {
  echo 'The id of the user named '.$a['username'].' is: '.$a['id']."\n";
}</pre>
* In case of large queries, don't forget to unset the results in order to save PHP's memory for later: `unset($aResult);`
* **Please see index.php for more options and advanced usage**

Pending
---------
* Multiquery support.
* Register multiple connections

Version History
----------

* 2.0.0 : 
    * Original file with some changes, now using constants instead of variables to make the connection
    * Fixed some minor bugs in case of no results of query, time calculation and variable initialization

* 2.0.1 : 
    * Added live statistics, it displays:
        * Memory usage
        * Time
        * Queries executed

* 2.1.0b: Some mayor changes: (that never became final release)
    * Now supporting XML log with SimpleXML.
    * Added data array size: displays total data size in bytes.
    * XML now logs also:
        * Number of results
        * Data array size
        * Number of queries
    * Live statistics now displays data array size and the query with its binding.

* 2.1.1 : 
    * Fixed some bugs with live statistics
    * Fixed some minor bugs related with the array data size

* 2.1.2 :
    * Error handling is now little better, however it will be improved further.
    * Data array size is now optional: it is just TOO slow. I'll see if I can improve it later.
    * Fixed minor bug with XML logging related to number of results

* 2.1.5 - RC: 
    * Now the class supports caching.
    * Proper error handling is now available.
    * Improved logging system a little more. It is a lot clearer now, it has it's own function.

* 2.1.5:
    * Fixed all sort of bugs.
    * Improved cache: it was faster to read the XML file with SimpleXML and structure it with PHP.
    * Added some more error exceptions. (Couldn't connect to DB, couldn't create cache file, etc)

* 2.1.6:
    * Improved error handling.
    * Added some more error exceptions. (Mainly possible file problem issues)
    * Added a new constant: DBPORT, in order to connect to a specific port different than the default.
    * Fixed a little bug in DB_Connect with variable name. (Was private static, should have been only private)
    * Special functions __call, __get, etc are now private.
    * DB_SHOW_ERRORS now working properly.
    * Class is now valid for PHP &gt;= 5.1.6.

* 2.1.7:
    * Fixed little inconsistency when webserver's charset was different from mysql's charset.

* 2.2.0:
    * Better error management: The class will no longer fail when a certain table or field doesn't exist.
    * <code>$dbLiveStats</code> now shows you the error.
    * Compatible (in strict mode) with PHP 5.3. All magic methods are now public.
    * Changed file name from <code>db.class.php</code> to <code>mysql.class.php</code>.
    * Changed basic class name from <code>DB()</code> to <code>DB_mysql()</code>.
    * Better XML validation. ([http://www.phpclasses.org/discuss/package/5812/thread/4/](Thanks Valentino Lauciani)!)
    * Fixed some minor errors on this README xD

* 3.0.0:
    * Runs on PHP5.3 on Windows and Linux without problems. [http://www.phpclasses.org/discuss/package/5812/thread/5/](Thanks to Hugo Simon).
    * The class is now compatible with PHPDocumentor, so technical reference should be easier.
    * <code>$dbLiveStats</code> and <code>$dbErrors</code> are now public variables, so no more global variables

* 3.1.0:
    * Support for transactions!
    * Connection is now made on demand, that means that if the class is instanciated but not used, no connection to MySQL will be made.
    * Better documentation
    * Full revision of methods, added some new ones, optimized some old ones.
    * This file is under SVN control now :)

* 4.0.0:
    * No longer is XML-based cache used, cacheManager class (see my other classes) is now in charge of doing all that job!
    * Better exception handling

* 4.0.1:
    * Support for multi-connections
    * Better documentation
    * Code cleanup and some minor improvements

Contact the author
-------

* Twitter: [@unreal4u](http://twitter.com/unreal4u)
* Website: [http://unreal4u.com/](http://unreal4u.com/)
* Github:  [http://www.github.com/unreal4u](http://www.github.com/unreal4u)
