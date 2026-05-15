from(bucket: "precog")
    |> range(start: -1h)
    |> group()
    |> distinct(column: "device_id")
