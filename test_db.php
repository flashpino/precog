<?php
$mysqli = new mysqli('212.1.210.112', 'asfindbr_esp32', 'nminfo*4990', 'asfindbr_precog');
if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}
$res = $mysqli->query('SHOW TABLES');
while($row = $res->fetch_row()) {
    echo $row[0] . PHP_EOL;
}
