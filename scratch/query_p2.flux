from(bucket: "precog")
    |> range(start: -1h)
    |> filter(fn: (r) => r["device_id"] == "precog_002")
    |> last()
