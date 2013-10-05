<?php

include('../src/unreal4u/config.php');
include('../src/unreal4u/db_mysqli.php');

$db = new unreal4u\db_mysqli();
$db->supressErrors = true;
$db->keepLiveLog = true;
echo $db->version();

$db->query('SELECT * FROM a');

echo '<pre>';
print_r($db->dbLiveStats);
echo '</pre>';