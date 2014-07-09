dbmysqli.php
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

<pre>include('src/unreal4u/config.php'); // Please see below for explanation
include('src/unreal4u/dbmysqli.php');
$dbLink = new unreal4u\dbmysqli();
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

Including with composer
---------

Add this to your composer.json:
<pre>
{
   "require": {
       "unreal4u/dbmysqli": "@stable"
   }
}
</pre>

Now you can instantiate a new dbmysqli class by executing:

<pre>
require('vendor/autoload.php');

$rutverifier = new unreal4u\dbmysqli();
</pre>

Pending ---------
* Multiquery support.
* Better naming convention to include other RDSMs later on
* Convert to PDO (to support :tag type associations)

Version History
----------

* 4.0.0:
    * No longer is XML-based cache used, cacheManager class (see my other classes) is now in charge of doing all that job!
    * Better exception handling

* 4.0.1:
    * Support for multi-connections
    * Better documentation
    * Code cleanup and some minor improvements

* 4.1.0:
    * Made class compatible with composer.phar and PSR-0 autoloader standards
    * Some minor fixes in documentation and code

* 4.1.1:
    * Fixes
    * Better documentation

* 4.1.2:
    * Totally forgot about PSR-0 underscore standard. The class is now refactored to wipe out the usage of underscore in the class's name

* 4.1.3:
    * Better documentation
    * Year change
* 5.0.0:
    * Mayor revision of the code and several fixes, updated to comply with (at least) PSR-2
    * [BC] Result array is now an SplFixedArray, uses less memory and should be a bit faster as well
    * [BC] More types supported: float and DateTime objects are now returned for those type of data's
    * [BC] camelCase'd all methods and variables
    * [BC] Class now throws exceptions in it's proper namespace
        * unreal4u\databaseException is now unreal4u\exceptions\database
        * unreal4u\queryException is now unreal4u\exceptions\query

Contact the author
-------

* Twitter: [@unreal4u](http://twitter.com/unreal4u)
* Website: [http://unreal4u.com/](http://unreal4u.com/)
* Github:  [http://www.github.com/unreal4u](http://www.github.com/unreal4u)
