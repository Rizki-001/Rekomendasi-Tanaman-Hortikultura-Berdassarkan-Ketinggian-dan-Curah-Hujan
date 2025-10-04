<?php
function getElevation($lat, $lng) {
    $url = "https://api.opentopodata.org/v1/srtm90m?locations=$lat,$lng";

    $options = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: PHP\r\n"
        ]
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === false) return null;

    $data = json_decode($response, true);
    return $data['results'][0]['elevation'] ?? null;
}

