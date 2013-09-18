<?php

include('../config.php');
include('../db_mysqli.class.php');

try {
    $db = new \u4u\db_mysqli();
    $db->throwQueryExceptions = true;
} catch (\u4u\databaseException $e) {
    exit($e->getMessage());
}


try {
    $insertId = $db->insert_id('INSERT INTO a (string_valued, int_valued, bool_valued, string_null_valued, int_null_valued, bool_null_valued, float_valued, float_null_valued, datetime_valued,       datetime_null_valued, timestamp_valued, timestamp_null_valued) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),?)',
                                               'd',           0,          false,       null,               null,            null,             11.22,        null,              '2001-02-03 00:00:00', null,                                   null
    );

    var_dump('The insertId is: '.$insertId);
} catch (\u4u\databaseException $e) {
    var_dump($e->getMessage());
}

try {
    $db->query();
} catch (\u4u\databaseException $e) {
    var_dump('Database exception: '.$e->getMessage());
} catch (\u4u\queryException $e) {
    var_dump('Query exception: '.$e->getMessage());
}

try {
    $db->query('INSERT INTO b (nonexistant) VALUES (?)', 22);
} catch (\u4u\databaseException $e) {
    var_dump('Database exception: '.$e->getMessage());
} catch (\u4u\queryException $e) {
    var_dump('Query exception: '.$e->getMessage());
}

var_dump($db->dbErrors);
