from(bucket: "precog")
    |> range(start: -1h)
    |> last()
