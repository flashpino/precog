import "influxdata/influxdb/schema"

schema.tagValues(
    bucket: "precog",
    tag: "device_id",
    start: -24h
)
