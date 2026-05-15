<?php
require_once __DIR__ . '/../includes/influxdb.php';
$flux = 'from(bucket: "precog") |> range(start: -1h) |> limit(n: 1)';
$data = InfluxDB::query($flux);
print_r($data);
