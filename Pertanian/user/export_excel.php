<?php
session_start();
require_once __DIR__ . '/../lib/db.php';

// Pastikan user sudah login
if (!isset($_SESSION['user'])) {
    header('Location: ../auth/login.php');
    exit;
}

$id_user = $_SESSION['user']['id_user'];

// Set header untuk file Excel (CSV)
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="riwayat_rekomendasi_' . date('Ymd_His') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Tulis header kolom ke file CSV
fputcsv($output, [
    'ID Riwayat',
    'Tanggal',
    'Lokasi',
    'Latitude',
    'Longitude',
    'Curah Hujan (mm)',
    'Ketinggian (m)',
    'Hasil Rekomendasi (Tanaman: Skor%)'
]);

try {
    // Ambil data riwayat dari database untuk user yang sedang login
    $stmt = $conn->prepare("SELECT * FROM riwayat WHERE id_user = ? ORDER BY created_at DESC");
    $stmt->execute([$id_user]);
    $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($riwayat as $row) {
        $hasil_rekomendasi_formatted = '';
        $rekomendasi = json_decode($row['hasil_rekomendasi'], true);

        if (is_array($rekomendasi) && !empty($rekomendasi)) {
            $temp_rekomendasi_array = [];
            foreach ($rekomendasi as $r) {
                $temp_rekomendasi_array[] = htmlspecialchars($r['tanaman']) . ': ' . htmlspecialchars($r['skor']) . '%';
            }
            $hasil_rekomendasi_formatted = implode('; ', $temp_rekomendasi_array);
        } else {
            $hasil_rekomendasi_formatted = 'Tidak ada rekomendasi';
        }

        // Tulis baris data ke file CSV
        fputcsv($output, [
            $row['id_riwayat'],
            date('Y-m-d H:i:s', strtotime($row['created_at'])),
            htmlspecialchars($row['lokasi']),
            htmlspecialchars($row['latitude']),
            htmlspecialchars($row['longitude']),
            htmlspecialchars($row['curah_hujan']),
            htmlspecialchars($row['ketinggian']),
            $hasil_rekomendasi_formatted
        ]);
    }
} catch (PDOException $e) {
    // Jika terjadi error, tulis pesan error ke CSV atau log
    fputcsv($output, ['Error: ' . $e->getMessage()]);
    error_log("Error exporting history to Excel: " . $e->getMessage());
} finally {
    fclose($output);
}
?>
