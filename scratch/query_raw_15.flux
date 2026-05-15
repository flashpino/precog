from(bucket: "precog")
    |> range(start: -15m)
    |> limit(n: 10)
