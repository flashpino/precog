from(bucket: "precog")
    |> range(start: -30m)
    |> last()
