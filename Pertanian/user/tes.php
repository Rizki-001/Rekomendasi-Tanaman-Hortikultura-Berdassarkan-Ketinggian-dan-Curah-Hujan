<?php
session_start();
require_once __DIR__ . '/../lib/db.php';

if (!isset($_SESSION['user']['id_user'])) {
    header("Location: ../auth/login.php");
    exit();
}

$userId = $_SESSION['user']['id_user'];

// Ambil data dari form
$lat = $_POST['lat'];
$lng = $_POST['lng'];
$lokasi = $_POST['lokasi'];
$curahHujan = $_POST['curah_hujan'];
$elevasi = $_POST['ketinggian'];

// Decode dan encode ulang hasil rekomendasi agar aman disimpan
$hasil_rekomendasi = json_decode($_POST['hasil_rekomendasi'], true);
$hasil_json = json_encode($hasil_rekomendasi, JSON_UNESCAPED_UNICODE);

// Simpan ke database
$query = "INSERT INTO riwayat 
    (id_user, lokasi, latitude, longitude, curah_hujan, ketinggian, hasil_rekomendasi, created_at)
    VALUES 
    (:id_user, :lokasi, :latitude, :longitude, :curah_hujan, :ketinggian, :hasil_rekomendasi, NOW())";

$stmt = $conn->prepare($query);
$stmt->execute([
    ':id_user' => $userId,
    ':lokasi' => $lokasi,
    ':latitude' => $lat,
    ':longitude' => $lng,
    ':curah_hujan' => $curahHujan,
    ':ketinggian' => $elevasi,
    ':hasil_rekomendasi' => $hasil_json,
]);

// Redirect ke halaman riwayat
header("Location: riwayat.php");
exit();
?>