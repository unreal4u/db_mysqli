<?php

include('../src/unreal4u/config.php');
include('../src/unreal4u/dbmysqli.php');

try {
    $db1 = new unreal4u\dbmysqli();
    print('Version connection 1: '.$db1->version());
} catch (\Exception $e) {
    print('::1:: '.$e->getMessage());
}

print('<br />');

try {
    $db2 = new unreal4u\dbmysqli();
    $db2->registerConnection('db_mysqli_v401');
    print('Version connection 2: '.$db2->version());
} catch (\Exception $e) {
    print('::2:: '.$e->getMessage());
}

print('<br />');

try {
    $db3 = new unreal4u\dbmysqli();
    $db3->registerConnection('mysql', 'localhost', 'root');
    print('Version connection 3: '.$db3->version());
} catch (\Exception $e) {
    print('::3:: '.$e->getMessage());
} catch (\ErrorException $e) {
    print('::4:: '.$e->getMessage());
}

$res1 = $db1->query('SHOW TABLES');
$res2 = $db2->query('SHOW TABLES');

var_dump($res1);
var_dump($res2);

$res1 = $db1->query('SHOW DATABASES');
$res2 = $db3->query('select * from user');

var_dump($res1);
var_dump($res2);
