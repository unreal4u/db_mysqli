<?php

/*
 * This example file has been temporarily disabled because it relies on the fact that we have some more classes loaded,
 * which must be transformed to the new way of implementing dependencies
 */

/*
include('../config.php');
include('../db_mysqli.php');

include('../../stable-versions.php');

$u4uLoader = new \u4u\autoLoader();
$bench     = new \u4u\benchmark('databaseCalls');
$db        = new \u4u\db_mysqli();

echo '<pre>';

$bench->beginCounter('newTable');
try {
    $db->begin_transaction();
    $db->query('DROP TABLE IF EXISTS t');
    $db->query('CREATE TABLE t(i int(11) PRIMARY KEY) engine=InnoDB');
    $db->query('INSERT INTO t VALUES (?)',1);
    $db->query('INSERT INTO t VALUES (?)',2);
    $db->end_transaction();
} catch (\u4u\queryException $e) {
    printf($e->getMessage().'<br />');
}
$bench->endCounter('newTable');
printf('<em>First transaction ended, starting with second (which will be the one to fail)</em><br />');

$bench->beginCounter('databaseException');
try {
    $db->begin_transaction();
    $db->query('INSERT INTO t VALUES (?)',3);
    $db->query('INSERT INTO t VALUES (?)',4);
    $db->query('INSERT INTO t VALUES (?)',2);
    $db->query('INSERT INTO t VALUES (?)',5);
    $db->end_transaction();
} catch (\u4u\queryException $e) {
    printf('Transaction failed! The message delivered by the database is: '.$e->getMessage().'<br />');
}
$bench->endCounter('databaseException');
printf('<em>Second transaction ended, error check:</em><br />');

print_r($db->dbErrors);

print('<br /><em>Database version:</em><br />');
$bench->beginCounter('databaseVersion');
try {
    echo $db->version();
    print('<br />');
} catch (\Exception $e) {
    print_r($e->getMessage().'<br />');
}
$bench->endCounter('databaseVersion');
$bench->endCounter('databaseCalls');

printf('End of execution<br />');
printf('Total time: %f<br />', $bench->getDiff('databaseCalls', 'time'));
printf('New table creation: %f<br />', $bench->getDiff('newTable', 'time'));
printf('Exception time: %f<br />', $bench->getDiff('databaseException', 'time'));
printf('Version time: %f<br />', $bench->getDiff('databaseVersion', 'time'));
echo '</pre>';
*/