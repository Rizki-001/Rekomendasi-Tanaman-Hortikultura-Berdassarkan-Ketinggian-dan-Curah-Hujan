<?php
session_start();
require_once __DIR__ . '/../lib/db.php';

if (!isset($_SESSION['user']['id_user'])) {
    header("Location: ../auth/login.php");
    exit();
}

$userId = $_SESSION['user']['id_user'];

// Inisialisasi variabel untuk menyimpan lat dan lng dari POST
$redirect_lat = null;
$redirect_lng = null;
$lat_lng_params = '';

// Ambil lat dan lng dari POST jika tersedia
if (isset($_POST['lat']) && is_numeric($_POST['lat'])) {
    $redirect_lat = floatval($_POST['lat']);
}
if (isset($_POST['lng']) && is_numeric($_POST['lng'])) {
    $redirect_lng = floatval($_POST['lng']);
}

// Buat string parameter lat/lng untuk redirect
if ($redirect_lat !== null && $redirect_lng !== null) {
    $lat_lng_params = '&lat=' . urlencode($redirect_lat) . '&lng=' . urlencode($redirect_lng);
}

// Validasi data POST
if (
    isset($_POST['lat'], $_POST['lng'], $_POST['lokasi'], $_POST['curah_hujan'], $_POST['ketinggian'], $_POST['hasil_rekomendasi'])
    && is_numeric($_POST['lat'])
    && is_numeric($_POST['lng'])
    && is_numeric($_POST['curah_hujan'])
    && is_numeric($_POST['ketinggian'])
) {
    // Ambil dan bersihkan data
    $lat = floatval($_POST['lat']);
    $lng = floatval($_POST['lng']);
    $lokasi = htmlspecialchars(trim($_POST['lokasi']));
    $curahHujan = floatval($_POST['curah_hujan']);
    $elevasi = floatval($_POST['ketinggian']);

    // Decode hasil rekomendasi
    $hasil_rekomendasi = json_decode($_POST['hasil_rekomendasi'], true);
    if (!is_array($hasil_rekomendasi)) {
        // Data rekomendasi tidak valid
        header("Location: dashboard.php?msg=" . urlencode("Data rekomendasi tidak valid") . "&type=error" . $lat_lng_params);
        exit();
    }

    // Encode ulang untuk simpan ke DB
    $hasil_json = json_encode($hasil_rekomendasi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    try {
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

        // Redirect ke halaman riwayat dengan pesan sukses dan pertahankan lat/lng
        header("Location: riwayat.php?msg=" . urlencode("Rekomendasi berhasil disimpan") . "&type=success" . $lat_lng_params);
        exit();
    } catch (PDOException $e) {
        // Gagal simpan, redirect ke dashboard dengan pesan error dan pertahankan lat/lng
        header("Location: dashboard.php?msg=" . urlencode("Gagal menyimpan riwayat: " . $e->getMessage()) . "&type=error" . $lat_lng_params);
        exit();
    }
} else {
    // Data tidak lengkap atau salah, redirect ke dashboard dengan pesan error dan pertahankan lat/lng
    header("Location: dashboard.php?msg=" . urlencode("Data tidak lengkap atau salah") . "&type=error" . $lat_lng_params);
    exit();
}
?>
