from(bucket: "precog")
    |> range(start: -30m)
    |> keep(columns: ["device_id"])
    |> unique(column: "device_id")
