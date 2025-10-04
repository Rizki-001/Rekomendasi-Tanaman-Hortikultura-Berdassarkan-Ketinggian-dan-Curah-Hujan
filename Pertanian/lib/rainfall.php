<?php
function getRainfall($lat, $lng) {
    $url = "https://power.larc.nasa.gov/api/temporal/climatology/point?parameters=PRECTOTCORR&community=SB&longitude=$lng&latitude=$lat&format=JSON";

    $options = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: PHP\r\n"
        ]
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === false) return 0;

    $data = json_decode($response, true);
    $monthly = $data['properties']['parameter']['PRECTOTCORR'] ?? [];

    $total = 0;
    foreach ($monthly as $bulan => $nilaiPerHari) {
        if ($bulan !== 'ANN') {
            $total += $nilaiPerHari * 30;
        }
    }

    return round($total, 2); // mm/tahun
}

