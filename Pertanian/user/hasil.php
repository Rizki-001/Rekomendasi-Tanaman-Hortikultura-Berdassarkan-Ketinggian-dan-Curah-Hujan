<?php
session_start();
require_once '../lib/db.php';
require_once '../lib/elevation.php';
require_once '../lib/rainfall.php';
require_once '../lib/scoring.php';

$lat = $_GET['lat'] ?? 0;
$lng = $_GET['lng'] ?? 0;

$elev = getElevation($lat, $lng);
$rain = getRainfall($lat, $lng);
$hasil = getRekomendasi($elev, $rain, $conn);
?>

<h2>Hasil Rekomendasi</h2>
<p>Elevasi: <?= round($elev) ?> m, Curah hujan: <?= round($rain,2) ?> mm/bulan</p>
<ul>
<?php foreach ($hasil as $item): ?>
    <li><?= $item['tanaman'] ?> (<?= $item['kategori'] ?>) - Skor: <?= $item['skor'] ?>%</li>
<?php endforeach; ?>
</ul>
