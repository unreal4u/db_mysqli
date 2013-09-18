<?php

include('../config.php');
include('../db_mysqli.class.php');

$db = new \u4u\db_mysqli();
$db->supressErrors = true;
$db->keepLiveLog = true;
echo $db->version();

$db->query('SELECT * FROM a');

echo '<pre>';
print_r($db->dbLiveStats);
echo '</pre>';