from(bucket: "precog")
    |> range(start: -24h)
    |> limit(n: 1)
