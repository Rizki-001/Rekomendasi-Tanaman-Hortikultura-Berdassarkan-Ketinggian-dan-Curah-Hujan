<?php
session_start();
require_once __DIR__ . '/../lib/db.php'; // Sesuaikan path ke db.php

// Pastikan user sudah login
if (!isset($_SESSION['user'])) {
    header('Location: ../auth/login.php');
    exit;
}

$id_user = $_SESSION['user']['id_user'];
$username = $_SESSION['user']['username'] ?? 'Pengguna';

// Ambil data riwayat dari database
$riwayat = [];
try {
    $stmt = $conn->prepare("SELECT * FROM riwayat WHERE id_user = ? ORDER BY created_at DESC");
    $stmt->execute([$id_user]);
    $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching history for PDF export: " . $e->getMessage());
    // Fallback or display error message on the page itself
    $riwayat = []; // Ensure $riwayat is an empty array on error
}

// Set header untuk HTML yang dapat dicetak
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: inline; filename="riwayat_rekomendasi_' . date('Ymd_His') . '.pdf"'); // Suggest PDF filename for print dialog
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Rekomendasi - GoAgriculture</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 20px;
            color: #333;
            line-height: 1.6;
        }
        h1, h2 {
            color: #2A5234;
            text-align: center;
            margin-bottom: 10px;
        }
        h1 {
            font-size: 24px;
            margin-bottom: 20px;
        }
        h2 {
            font-size: 18px;
            margin-top: 20px;
        }
        .user-info {
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 12px;
            vertical-align: top;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .no-records {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #777;
        }
        .recommendation-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .recommendation-list li {
            margin-bottom: 3px;
        }
        @media print {
            body {
                margin: 0;
                font-size: 10pt;
            }
            table {
                page-break-inside: auto;
            }
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            thead {
                display: table-header-group;
            }
            tfoot {
                display: table-footer-group;
            }
        }
    </style>
</head>
<body>
    <h1>Riwayat Rekomendasi Tanaman</h1>
    <div class="user-info">
        Data untuk Pengguna: <strong><?= htmlspecialchars($username) ?></strong><br>
        Tanggal Ekspor: <?= date('d F Y H:i:s') ?>
    </div>

    <?php if (!empty($riwayat)) : ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tanggal</th>
                    <th>Lokasi</th>
                    <th>Lat</th>
                    <th>Lng</th>
                    <th>Curah Hujan (mm)</th>
                    <th>Ketinggian (m)</th>
                    <th>Hasil Rekomendasi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($riwayat as $row) : ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id_riwayat']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                        <td><?= htmlspecialchars($row['lokasi']) ?></td>
                        <td><?= htmlspecialchars($row['latitude']) ?></td>
                        <td><?= htmlspecialchars($row['longitude']) ?></td>
                        <td><?= htmlspecialchars($row['curah_hujan']) ?></td>
                        <td><?= htmlspecialchars($row['ketinggian']) ?></td>
                        <td>
                            <?php
                            $rekomendasi = json_decode($row['hasil_rekomendasi'], true);
                            if (is_array($rekomendasi) && !empty($rekomendasi)) {
                                echo '<ul class="recommendation-list">';
                                foreach ($rekomendasi as $r) {
                                    echo '<li>' . htmlspecialchars($r['tanaman']) . ' (' . htmlspecialchars($r['skor']) . '%)</li>';
                                }
                                echo '</ul>';
                            } else {
                                echo 'Tidak ada rekomendasi';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p class="no-records">Tidak ada riwayat rekomendasi yang tersedia untuk diekspor.</p>
    <?php endif; ?>

    <script>
        // Trigger print dialog automatically when page loads
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
