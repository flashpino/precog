from(bucket: "precog")
    |> range(start: -10m)
    |> limit(n: 5)
