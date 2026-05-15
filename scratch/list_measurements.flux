from(bucket: "precog")
    |> range(start: -1h)
    |> group()
    |> distinct(column: "_measurement")
