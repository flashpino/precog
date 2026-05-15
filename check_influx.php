<?php
require_once __DIR__ . '/includes/influxdb.php';
$deviceId = 'precog_003';
$flux = 'from(bucket: "precog")
  |> range(start: -24h)
  |> filter(fn: (r) => r["device_id"] == "' . $deviceId . '")
  |> filter(fn: (r) => r["_field"] == "temperatura")
  |> filter(fn: (r) => r["_value"] < 15.0)
  |> yield(name: "low_temps")';

$data = InfluxDB::query($flux, 'supera', 'GNPpBsSAmqUGK5vd_omgbIkBHHlxyJSx3LxNTFlO1HyWNIp7aIfYls4RqjiH20XCQM9AAbQvI_lpI9tGyr9AAw==');
print_r($data);
