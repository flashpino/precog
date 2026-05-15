<?php
require_once __DIR__ . '/includes/influxdb.php';

$flux = 'from(bucket: "precog")
    |> range(start: -24h)
    |> last()';

$data = InfluxDB::query($flux);
print_r($data);
