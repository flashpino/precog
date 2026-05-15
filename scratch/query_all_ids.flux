from(bucket: "precog")
    |> range(start: -15m)
    |> group()
    |> distinct(column: "device_id")
