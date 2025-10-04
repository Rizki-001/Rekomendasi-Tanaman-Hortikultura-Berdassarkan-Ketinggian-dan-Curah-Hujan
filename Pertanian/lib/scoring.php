<?php
function getRekomendasi($elevasi, $curah_hujan, $conn)
{
    $query = "SELECT a.*, t.nama_tanaman, t.kategori FROM aturan a JOIN tanaman t ON a.id_tanaman = t.id_tanaman";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $aturanList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rekomendasi = [];
    $w1 = 0.3; // Bobot ketinggian
    $w2 = 0.7; // Bobot curah hujan
    //Rule-Based
    foreach ($aturanList as $aturan) {
        $inRangeElevasi = $elevasi >= $aturan['min_ketinggian'] && $elevasi <= $aturan['max_ketinggian'];
        $inRangeHujan = $curah_hujan >= $aturan['min_curah_hujan'] && $curah_hujan <= $aturan['max_curah_hujan'];

        if ($inRangeElevasi && $inRangeHujan) {
            // Ideal value = titik tengah dari range
            $idealElevasi = ($aturan['min_ketinggian'] + $aturan['max_ketinggian']) / 2;
            $rangeElevasi = $aturan['max_ketinggian'] - $aturan['min_ketinggian'];

            $idealHujan = ($aturan['min_curah_hujan'] + $aturan['max_curah_hujan']) / 2;
            $rangeHujan = $aturan['max_curah_hujan'] - $aturan['min_curah_hujan'];

            if ($rangeElevasi == 0 || $rangeHujan == 0) continue;
            // Hitung skor proximity masing-masing
            $skorElevasi = 1 - (abs($elevasi - $idealElevasi) / ($rangeElevasi / 2));
            $skorHujan = 1 - (abs($curah_hujan - $idealHujan) / ($rangeHujan / 2));
            // Batasi skor ke rentang 0–1
            $skorElevasi = max(0, min(1, $skorElevasi));
            $skorHujan = max(0, min(1, $skorHujan));
            // Hitung skor total berdasarkan bobot
            $skorTotal = round(($skorElevasi * $w1 + $skorHujan * $w2) * 100, 2);
            // Simpan jika skor cukup baik
            if ($skorTotal >= 50) {
                $rekomendasi[] = [
                    'tanaman' => $aturan['nama_tanaman'],
                    'kategori' => $aturan['kategori'],
                    'skor' => $skorTotal
                ];
            }
        }
    }
    // Urutkan rekomendasi dari skor tertinggi ke terendah
    usort($rekomendasi, fn($a, $b) => $b['skor'] <=> $a['skor']);
    return $rekomendasi;
}
