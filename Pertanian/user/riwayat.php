<?php
session_start();
error_reporting(E_ALL); // Aktifkan semua laporan error
ini_set('display_errors', 1); // Tampilkan error di browser

require_once __DIR__ . '/../lib/db.php'; // Pastikan path ini benar relatif terhadap riwayat.php

// Cek apakah user sudah login dan memiliki ID
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id_user'])) {
    header('Location: ../auth/login.php');
    exit;
}

$id_user = intval($_SESSION['user']['id_user']); // Ambil ID pengguna dari session dan pastikan integer
$username = $_SESSION['user']['username'] ?? 'Pengguna';
$user_role = $_SESSION['user']['role'] ?? 'user'; // Dapatkan role user, asumsi ini ada di session

// Ambil lat dan lng dari URL jika ada
$current_lat = isset($_GET['lat']) ? htmlspecialchars($_GET['lat']) : '';
$current_lng = isset($_GET['lng']) ? htmlspecialchars($_GET['lng']) : '';

// Buat string parameter lat/lng jika keduanya ada
$lat_lng_params = '';
if (!empty($current_lat) && !empty($current_lng)) {
    $lat_lng_params = '&lat=' . $current_lat . '&lng=' . $current_lng;
}

// Fungsi helper untuk redirect dengan pesan notifikasi
// Fungsi ini akan menambahkan pesan ke URL dan melakukan redirect
function redirectWithUrlMessage($message, $type) {
    $encodedMessage = urlencode($message);
    
    // Dapatkan komponen URL saat ini
    $current_url_parts = parse_url($_SERVER["REQUEST_URI"]);
    $path = $current_url_parts['path'];
    $query = [];
    if (isset($current_url_parts['query'])) {
        parse_str($current_url_parts['query'], $query);
    }

    // Hapus parameter 'message' dan 'type' lama jika ada, untuk memastikan hanya pesan baru yang muncul
    unset($query['message']);
    unset($query['type']);

    // Tambahkan parameter 'message' dan 'type' baru
    $query['message'] = $encodedMessage;
    $query['type'] = $type;

    // Bangun kembali string query, mempertahankan parameter lain seperti lat/lng
    $new_query_string = http_build_query($query);
    
    header("Location: {$path}?{$new_query_string}");
    exit();
}

// Handle permintaan penghapusan riwayat
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_riwayat_to_delete = intval($_GET['id']); // Pastikan ini integer
    $current_user_id = $id_user; // id_user sudah dipastikan integer di atas

    error_log("Attempting to delete riwayat ID: " . $id_riwayat_to_delete . " for user ID: " . $current_user_id);

    try {
        // Pastikan riwayat yang akan dihapus adalah milik user yang sedang login
        $stmt_check = $conn->prepare("SELECT id_riwayat FROM riwayat WHERE id_riwayat = ? AND id_user = ?");
        if (!$stmt_check) {
            throw new Exception("Failed to prepare check statement: " . $conn->errorInfo()[2]);
        }
        $stmt_check->bindParam(1, $id_riwayat_to_delete, PDO::PARAM_INT);
        $stmt_check->bindParam(2, $current_user_id, PDO::PARAM_INT);
        $stmt_check->execute();

        $row_count = $stmt_check->rowCount();
        error_log("Check query rowCount: " . $row_count);

        if ($row_count > 0) {
            $stmt_delete = $conn->prepare("DELETE FROM riwayat WHERE id_riwayat = ?");
            if (!$stmt_delete) {
                throw new Exception("Failed to prepare delete statement: " . $conn->errorInfo()[2]);
            }
            $stmt_delete->bindParam(1, $id_riwayat_to_delete, PDO::PARAM_INT);
            $stmt_delete->execute();
            error_log("Riwayat ID " . $id_riwayat_to_delete . " deleted successfully.");
            redirectWithUrlMessage('Riwayat berhasil dihapus!', 'success');
        }
    } catch (PDOException $e) {
        error_log("PDOException during delete: " . $e->getMessage());
        redirectWithUrlMessage('Terjadi kesalahan database saat menghapus: ' . $e->getMessage(), 'error');
    } catch (Exception $e) {
        error_log("General Exception during delete: " . $e->getMessage());
        redirectWithUrlMessage('Terjadi kesalahan: ' . $e->getMessage(), 'error');
    }
}

// Ambil data riwayat dari database untuk user yang sedang login
$riwayat = [];
try {
    $stmt = $conn->prepare("SELECT * FROM riwayat WHERE id_user = ? ORDER BY created_at DESC");
    if (!$stmt) {
        throw new Exception("Failed to prepare fetch statement: " . $conn->errorInfo()[2]);
    }
    $stmt->bindParam(1, $id_user, PDO::PARAM_INT); // Bind id_user sebagai integer
    $stmt->execute();
    $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dekode hasil_rekomendasi untuk setiap baris riwayat
    foreach ($riwayat as &$row) { // Gunakan referensi (&) untuk memodifikasi array asli
        $row['hasil_rekomendasi'] = json_decode($row['hasil_rekomendasi'], true);
        // Pastikan hasil_rekomendasi adalah array, jika tidak, set ke array kosong
        if (!is_array($row['hasil_rekomendasi'])) {
            $row['hasil_rekomendasi'] = [];
        }
    }
    unset($row); // Hapus referensi setelah loop selesai
} catch (PDOException $e) {
    error_log("Database Error (Fetch Riwayat): " . $e->getMessage());
    redirectWithUrlMessage('Gagal mengambil data riwayat: ' . $e->getMessage(), 'error');
} catch (Exception $e) {
    error_log("General Exception (Fetch Riwayat): " . $e->getMessage());
    redirectWithUrlMessage('Terjadi kesalahan saat mengambil data riwayat: ' . $e->getMessage(), 'error');
}

// Cek permintaan tampilan detail
$detail_entry = null;
if (isset($_GET['detail_id'])) {
    $detail_id = intval($_GET['detail_id']);
    foreach ($riwayat as $entry) {
        // Pastikan detail yang diminta adalah milik user yang sedang login
        if ($entry['id_riwayat'] === $detail_id && $entry['id_user'] === $id_user) {
            $detail_entry = $entry;
            break;
        }
    }
}

// Inisialisasi variabel pesan untuk JavaScript
$js_message = '';
$js_message_type = '';
if (isset($_GET['message'])) {
    $js_message = urldecode($_GET['message']);
    $js_message_type = $_GET['type'];
    
    // TIDAK ADA LAGI REDIRECT PHP DI SINI.
    // URL akan dibersihkan oleh JavaScript setelah pesan ditampilkan.
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat - GoAgriculture</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
        /* Define your color variables */
        :root {
            --dark-prime: #2A5234; /* Main dark green for header/accents - sedikit lebih gelap */
            --light-bg: #F5F5F5; /* Very light grey/off-white for body background */
            --main-accent-green: #70C174; /* Primary accent green - sedikit lebih terang */
            --main-accent-green-rgb: 112, 193, 116; /* RGB value for transparency */
            --secondary-accent-green: #4CAF50; /* A slightly darker shade of accent green for hover */
            --text-on-dark: #FFFFFF; /* White text on dark backgrounds */
            --text-on-light: #333333; /* Dark grey text on light backgrounds */
            --text-muted: #666666; /* Muted grey for secondary text */
            --accent-blue: #2196F3; /* Standard blue for links */
            --accent-yellow: #FFC107; /* Warning yellow */
            --shadow-light: rgba(0, 0, 0, 0.1);
            --border-radius-main: 8px;
            --border-radius-sm: 4px;
            --sidebar-width-open: 280px;
            --sidebar-width-closed: 80px; /* Reduced width when collapsed */
            --navbar-height: 70px; /* Tinggi navbar */
        }

        /* Base Styles & Typography */
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            background-color: var(--light-bg);
            color: var(--text-on-light);
            overflow-x: hidden;
            display: flex;
            flex-direction: column; /* Stack navbar and main content area */
            min-height: 100vh;
        }

        /* Navbar Styling */
        .navbar {
            background-color: var(--dark-prime);
            color: var(--text-on-dark);
            height: var(--navbar-height); /* Set tinggi navbar */
            padding: 0 30px; /* Padding vertikal 0, horisontal 30px */
            display: flex;
            align-items: center;
            justify-content: space-between; /* Pisahkan logo dan info user */
            box-shadow: 0 2px 10px var(--shadow-light);
            z-index: 1000;
            position: fixed; /* Ubah ke fixed */
            top: 0;
            left: 0;
            width: 100%;
        }

        .navbar .logo-area {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8em;
            font-weight: 600; /* Font weight adjusted */
            color: var(--text-on-dark); /* GoAgriculture text color changed to white */
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .navbar .logo-area i {
            font-size: 1.2em;
            color: var(--main-accent-green); /* Icon color remains green */
        }

        .navbar .user-info-navbar {
            display: flex;
            align-items: center;
            gap: 15px;
            color: var(--text-on-dark);
        }

        .navbar .user-info-navbar .user-details {
            display: flex;
            flex-direction: column;
            text-align: right; /* Nama dan role di kanan */
        }

        .navbar .user-info-navbar .user-name {
            font-family: 'Montserrat', sans-serif;
            font-size: 1em;
            font-weight: 500; /* Font weight adjusted */
        }

        .navbar .user-info-navbar .user-role {
            font-size: 0.8em;
            color: #b0c2b6;
        }


        /* Main Layout (sidebar + content) */
        .main-layout {
            display: flex;
            flex-grow: 1; /* Allows main layout to take remaining height */
            width: 100%;
            padding-top: var(--navbar-height); /* Tambahkan padding atas untuk mengompensasi fixed navbar */
            min-height: 100vh; /* Sesuaikan dengan tinggi viewport */
        }

        /* Sidebar Styling */
        .sidebar {
            width: var(--sidebar-width-closed); /* Start collapsed */
            background-color: var(--dark-prime);
            color: var(--text-on-dark);
            padding: 20px 0; /* Padding atas bawah 20px, samping 0 */
            box-shadow: 3px 0 8px var(--shadow-light);
            display: flex; /* Jadikan flex container */
            flex-direction: column; /* Susun item secara kolom */
            align-items: center; /* Pusatkan secara horizontal (saat collapsed) */
            position: fixed; /* Ubah ke fixed */
            top: var(--navbar-height); /* Mulai di bawah navbar */
            left: 0;
            height: calc(100vh - var(--navbar-height)); /* Tinggi sidebar sesuai sisa viewport */
            overflow-y: auto;
            transition: width 0.3s ease;
            flex-shrink: 0;
            z-index: 999; /* Di bawah navbar, di atas konten utama */
        }

        .sidebar:hover {
            width: var(--sidebar-width-open); /* Expand on hover */
        }

        /* Hide user info from sidebar */
        .sidebar .user-info {
            display: none;
        }

        /* Teks dalam span: Awalnya disembunyikan dengan text-indent negatif */
        .sidebar nav ul li a span {
            white-space: nowrap;
            overflow: hidden;
            text-indent: -9999px; /* Sembunyikan teks di luar layar */
            transition: text-indent 0.3s ease;
            display: inline-block; /* Agar text-indent bekerja */
            max-width: 0; /* Untuk transisi width yang lebih baik saat sidebar terbuka */
        }

        /* Saat sidebar di-hover, tampilkan teks */
        .sidebar:hover nav ul li a span {
            text-indent: 0; /* Kembalikan teks ke posisi normal */
            max-width: 200px; /* Lebar maksimum saat terlihat */
            transition: text-indent 0.3s ease, max-width 0.3s ease; /* Tambahkan max-width ke transisi */
        }


        /* Modifikasi ini untuk memusatkan secara vertikal */
        .sidebar nav {
            flex-grow: 1; /* Pastikan nav mengisi ruang yang tersedia */
            display: flex;
            flex-direction: column;
            justify-content: center; /* Pusatkan konten nav (ul) secara vertikal */
            width: 100%; /* Pastikan nav mengambil lebar penuh */
        }

        .sidebar nav ul {
            list-style-type: none;
            padding: 0;
            margin: 0; /* Pastikan tidak ada margin yang mengganggu pemusatan */
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center; /* Pusatkan item menu secara horizontal */
            padding-top: 0;
            padding-bottom: 0;
        }

        .sidebar nav ul li {
            margin-bottom: 10px;
            width: 100%; /* Ensure li takes full width */
        }

        /* DEFAULT STYLE FOR ALL MENU ITEMS (NOT ACTIVE, NOT HOVERED) */
        .sidebar nav ul li a {
            display: flex;
            align-items: center;
            justify-content: center; /* Centered for collapsed state */
            gap: 15px;
            color: var(--text-on-dark); /* Default: White text */
            text-decoration: none;
            padding: 12px 0px; /* Padding for collapsed state */
            border-radius: var(--border-radius-sm);
            transition: background-color 0.3s ease, color 0.3s ease, justify-content 0.3s ease, padding 0.3s ease;
            font-weight: 500;
            font-family: 'Roboto', sans-serif;
            width: 100%;
            background-color: transparent; /* Pastikan transparan secara default */
        }

        /* STYLE FOR ACTIVE MENU ITEM */
        /* Warna solid saat aktif, teks gelap */
        .sidebar nav ul li a.active {
            background-color: var(--main-accent-green);
            color: var(--dark-prime);
            font-weight: 500;
            box-shadow: none;
        }

        /* Active item's hover state */
        /* Sedikit transparansi saat di hover, teks gelap */
        .sidebar nav ul li a.active:hover {
            background-color: rgba(var(--main-accent-green-rgb), 0.9);
            color: var(--dark-prime);
        }

        /* HOVER STYLE FOR INACTIVE MENU ITEMS (collapsed state) */
        /* TANPA background-color di sini! Warna hanya muncul saat sidebar melebar. */
        .sidebar nav ul li a:not(.active):hover {
            color: var(--text-on-dark); /* Tetap putih saat hover di sidebar ciut */
        }

        /* HOVER STYLE WHEN SIDEBAR IS EXPANDED (when sidebar itself is hovered) */
        /* Ini adalah gaya dasar saat sidebar terbuka, tanpa background pada link itu sendiri */
        .sidebar:hover nav ul li a {
            justify-content: flex-start; /* Align text to left when expanded */
            padding: 12px 20px; /* Add horizontal padding when expanded */
            background-color: transparent; /* Pastikan transparan saat sidebar melebar, kecuali dihover */
        }

        /* Override hover style specifically when sidebar is expanded AND item is hovered AND not active */
        /* Warna hijau cerah saat hover, teks gelap (untuk item tidak aktif saat sidebar melebar) */
        .sidebar:hover nav ul li a:not(.active):hover {
            background-color: var(--main-accent-green);
            color: var(--dark-prime);
        }

        /* Override style for active item when sidebar is expanded */
        /* Pastikan warna latar belakang hijau cerah tetap ada untuk item aktif saat sidebar melebar */
        .sidebar:hover nav ul li a.active {
             background-color: var(--main-accent-green);
             color: var(--dark-prime);
        }


        .sidebar nav ul li a i {
            font-size: 1.1em;
            width: 20px; /* Fixed width for icons */
            text-align: center;
            flex-shrink: 0;
            color: inherit; /* Penting! Agar warna ikon mengikuti warna teks link */
        }

        /* Force active icon color */
        .sidebar nav ul li a.active i {
            color: var(--dark-prime); /* Warna ikon saat aktif */
        }

        /* Ini juga penting untuk memastikan ikon berubah warna saat hover, mengikuti teks */
        .sidebar nav ul li a:not(.active):hover i {
            color: var(--text-on-dark); /* Tetap putih saat hover di sidebar ciut */
        }
        .sidebar:hover nav ul li a:not(.active):hover i {
            color: var(--dark-prime);
        }


        /* Logout button at the bottom of sidebar */
        .sidebar .logout-button-container {
            width: 100%;
            margin-top: auto; /* Push to the bottom */
            padding: 0 15px 15px; /* Padding bottom and horizontal */
            display: flex;
            justify-content: center; /* Center horizontally */
        }

        .sidebar .logout-button-container a {
            display: flex;
            align-items: center;
            justify-content: center; /* Center icon */
            background-color: transparent; /* No background on logout by default */
            color: var(--text-on-dark); /* White text */
            text-decoration: none;
            border: 2px solid var(--text-on-dark); /* White circular border */
            border-radius: 50%; /* Make it circular */
            transition: all 0.3s ease;
            font-weight: 500;
            font-family: 'Roboto', sans-serif;
            width: 50px; /* Fixed width/height for circular */
            height: 50px;
            flex-shrink: 0;
            overflow: hidden; /* Hide text (span) when circular */
            position: relative; /* For the absolute positioning of text */
        }

        .sidebar .logout-button-container a:hover {
            background-color: var(--main-accent-green); /* Green background on hover */
            color: var(--dark-prime); /* Dark text on hover */
            border-color: var(--main-accent-green); /* Green border on hover */
        }

        .sidebar .logout-button-container a i {
            font-size: 1.1em;
            width: 20px; /* Fixed width for icon */
            text-align: center;
            color: var(--text-on-dark); /* Icon color white */
            transition: color 0.3s ease;
        }

        .sidebar .logout-button-container a:hover i {
            color: var(--dark-prime); /* Icon color dark on hover */
        }

        .sidebar .logout-button-container a span {
            /* Hide the text always for this request */
            display: none;
        }


        /* Main Content Area */
        .main-content {
            flex-grow: 1;
            flex-basis: auto;
            display: flex;
            flex-direction: column;
            background-color: var(--light-bg);
            padding: 25px;
            overflow-y: auto;
            /* Atur margin-left sebagai default saat sidebar ciut */
            margin-left: var(--sidebar-width-closed);
            /* Transisi hanya untuk margin-left */
            transition: margin-left 0.3s ease; /* Hanya transisi margin-left */
            /* Lebar default adalah sisa dari viewport dikurangi sidebar ciut */
            width: calc(100% - var(--sidebar-width-closed));
        }

        /* Saat sidebar di-hover, geser main-content */
        /* Gunakan adjacent sibling selector (+) untuk targetkan main-content */
        .sidebar:hover + .main-content { /* <<< PERUBAHAN KRUSIAL DI SINI */
            margin-left: var(--sidebar-width-open);
            /* Lebar disesuaikan saat sidebar terbuka */
            width: calc(100% - var(--sidebar-width-open));
        }


        /* Header Bar - REVISED STYLES */
        .header-bar {
            background-color: var(--dark-prime); /* Dark green background, same as sidebar */
            color: var(--text-on-dark); /* White text */
            padding: 20px 30px;
            border-radius: var(--border-radius-main);
            box-shadow: 0 4px 15px var(--shadow-light);
            margin-bottom: 25px;
            display: flex;
            flex-direction: column; /* Stack title and description */
            align-items: flex-start; /* Align content to the left */
        }

        .header-bar h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.2em;
            font-weight: 600; /* Font weight adjusted */
            color: var(--text-on-dark); /* White for title */
            margin: 0;
        }

        .header-bar .description {
            font-size: 0.95em;
            color: #b0c2b6; /* Muted white for description */
            margin-top: 5px; /* Space between title and description */
            line-height: 1.5;
        }


        /* Location Info Bar - REVISED STYLES (Used for history item details) */
        .history-item-detail {
            background-color: #e8f5e9; /* Lighter green background */
            padding: 20px 25px; /* Increased padding */
            border-radius: var(--border-radius-main); /* Rounded corners for the whole bar */
            border-left: 6px solid var(--main-accent-green); /* Thicker, more prominent left border */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08); /* Subtle shadow for depth */
            margin-bottom: 25px;
            display: flex;
            align-items: flex-start; /* Align icon and text to the top */
            gap: 20px; /* Increased gap between icon and text */
            font-family: 'Roboto', sans-serif;
            color: var(--dark-prime);
        }

        .history-item-detail i {
            font-size: 2em; /* Larger icon */
            color: var(--secondary-accent-green); /* Slightly darker green for icon */
            flex-shrink: 0; /* Prevent icon from shrinking */
            margin-top: 2px; /* Slight adjustment for visual alignment */
        }

        .history-item-detail div { /* Container for the text lines */
            display: flex;
            flex-direction: column;
            gap: 8px; /* Spacing between each line of info */
        }

        .history-item-detail p {
            margin: 0;
            font-size: 1.05em;
            line-height: 1.4; /* Improve line spacing */
        }

        .history-item-detail strong {
            font-weight: 600; /* Font weight adjusted */
            color: var(--dark-prime);
        }

        /* Card Grid (for history recommendations) */
        .history-rekomendasi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .history-rekomendasi-card {
            background: var(--text-on-dark);
            border-left: 6px solid var(--main-accent-green);
            border-radius: var(--border-radius-main);
            padding: 20px;
            box-shadow: 0 2px 8px var(--shadow-light);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .history-rekomendasi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .history-rekomendasi-card h4 {
            font-family: 'Montserrat', sans-serif;
            margin-top: 0;
            margin-bottom: 10px;
            color: var(--dark-prime);
            font-size: 1.3em;
            font-weight: 500; /* Font weight adjusted */
        }

        .history-rekomendasi-card p {
            margin: 5px 0;
            color: var(--text-muted);
            font-size: 0.95em;
            font-family: 'Roboto', sans-serif;
        }

        .history-rekomendasi-card .score {
            font-weight: 500; /* Font weight adjusted */
            color: var(--main-accent-green);
        }

        /* No Result / Info Messages */
        .no-result {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border: 1px solid #ffeeba;
            border-radius: var(--border-radius-sm);
            text-align: center;
            font-family: 'Roboto', sans-serif;
            font-weight: 400; /* Font weight adjusted */
            margin-top: 20px;
        }

        /* Table for history entries */
        .history-table-container {
            background-color: var(--text-on-dark);
            padding: 25px;
            border-radius: var(--border-radius-main);
            box-shadow: 0 5px 15px var(--shadow-light);
            margin-bottom: 25px;
            overflow-x: auto; /* Enable horizontal scrolling for small screens */
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .history-table th,
        .history-table td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            text-align: left;
            font-family: 'Roboto', sans-serif;
            font-size: 0.95em;
        }

        .history-table th {
            background-color: var(--dark-prime);
            color: var(--text-on-dark);
            font-weight: 600;
            white-space: nowrap; /* Prevent wrapping in headers */
            text-align: center; /* Center header text */
        }

        .history-table td {
            text-align: center; /* Center table data for consistency */
        }


        .history-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .history-table tr:hover {
            background-color: #f1f1f1;
        }

        .history-table .view-button,
        .history-table .delete-button {
            padding: 8px 12px;
            border: none;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
            transition: background-color 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin: 2px; /* Small margin between buttons */
        }

        .history-table .view-button {
            background-color: var(--main-accent-green);
            color: var(--text-on-dark);
        }

        .history-table .view-button:hover {
            background-color: var(--secondary-accent-green);
        }

        .history-table .delete-button {
            background-color: #e74c3c; /* Red for delete */
            color: var(--text-on-dark);
        }

        .history-table .delete-button:hover {
            background-color: #c0392b; /* Darker red on hover */
        }


        /* Detail section for a single history entry */
        .detail-section {
            background-color: var(--text-on-dark);
            padding: 25px;
            border-radius: var(--border-radius-main);
            box-shadow: 0 5px 15px var(--shadow-light);
            margin-bottom: 25px;
        }

        .detail-section h3 {
            font-family: 'Montserrat', sans-serif;
            color: var(--dark-prime);
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.8em;
            font-weight: 600;
            text-align: center;
        }

        .detail-section p {
            margin: 8px 0;
            font-size: 1em;
            color: var(--text-on-light);
        }

        .detail-section p strong {
            color: var(--dark-prime);
            font-weight: 600;
        }

        .detail-section .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: var(--dark-prime);
            color: var(--text-on-dark);
            border: none;
            border-radius: var(--border-radius-sm);
            text-decoration: none;
            transition: background-color 0.3s ease;
            font-weight: 500;
        }

        .detail-section .back-button:hover {
            background-color: #1a3a22; /* Slightly lighter dark green */
        }

        /* Export links */
        .export-links {
            text-align: center;
            margin-top: 30px;
            padding: 15px;
            background-color: #e8f5e9; /* Light green background */
            border-radius: var(--border-radius-main);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .export-links a {
            background-color: var(--main-accent-green);
            color: var(--text-on-dark);
            padding: 10px 20px;
            border-radius: var(--border-radius-sm);
            text-decoration: none;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .export-links a:hover {
            background-color: var(--secondary-accent-green);
        }

        /* Floating Message Styling */
        .message {
            position: fixed; /* Make it float above content */
            top: 20px; /* Distance from top */
            right: 20px; /* Distance from right */
            z-index: 2000; /* Ensure it's on top of everything */
            padding: 15px 25px; /* More padding for a better look */
            border-radius: var(--border-radius-main); /* Rounded corners */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); /* Add shadow */
            font-weight: 600;
            opacity: 1; /* Start visible */
            transition: opacity 0.5s ease-out, top 0.5s ease-out; /* Smooth fade and slide out */
            max-width: 90%; /* Limit width on smaller screens */
            width: auto; /* Adjust width to content */
            text-align: left; /* Align text within the floating box */
            display: flex; /* Use flex for icon and text alignment */
            align-items: center;
            gap: 10px; /* Space between icon and text */
        }

        .success {
            background-color: #e8f5e9; /* Light green */
            color: #2e7d32; /* Dark green */
            border: 1px solid #4caf50;
        }

        .error {
            background-color: #ffebee; /* Light red */
            color: #c62828; /* Dark red */
            border: 1px solid #f44336;
        }


        /* Footer */
        footer {
            margin-top: auto;
            padding-top: 25px;
            border-top: 1px solid #e0e0e0;
            color: var(--text-muted);
            font-size: 0.9em;
            text-align: center;
            font-family: 'Roboto', sans-serif;
            width: 100%;
        }

        /* Responsive Adjustments */
        @media screen and (max-width: 992px) {
            .sidebar {
                width: var(--sidebar-width-closed);
                padding: 20px 15px;
            }
            .sidebar:hover {
                width: var(--sidebar-width-open);
            }

            /* Untuk mobile/tablet, span harus selalu terlihat atau diatur berbeda */
            .sidebar nav ul li a span {
                text-indent: 0; /* Tampilkan teks */
                max-width: 150px; /* Atur lebar sesuai kebutuhan di mode terbuka */
            }

            .sidebar nav ul li a {
                justify-content: center; /* Keep centered */
                padding: 12px 0;
            }
            .sidebar:hover nav ul li a {
                justify-content: flex-start; /* Align text to left when expanded on hover */
                padding: 12px 20px;
            }
            /* Logout button responsive for sidebar (collapsed on smaller desktop/tablet) */
            .sidebar .logout-button-container {
                padding: 0 10px 10px; /* Smaller padding */
            }
            .sidebar .logout-button-container a {
                width: 45px;
                height: 45px;
                padding: 10px;
            }
            /* Ensure text is hidden on hover for smaller desktop/tablet when sidebar is narrow */
            .sidebar:hover .logout-button-container a span {
                display: none;
            }


            .main-content {
                padding: 20px;
                margin-left: var(--sidebar-width-closed); /* Tetap menyesuaikan sidebar */
                width: calc(100% - var(--sidebar-width-closed)); /* Sesuaikan juga lebar */
            }
            /* Hanya target main-content saat sidebar di-hover, bukan main-layout */
            .sidebar:hover + .main-content {
                margin-left: var(--sidebar-width-open);
                width: calc(100% - var(--sidebar-width-open)); /* Sesuaikan juga lebar */
            }

            .header-bar {
                padding: 15px 20px;
            }

            .header-bar h1 {
                font-size: 1.8em;
            }

            /* Location Info Bar - Responsive */
            .history-item-detail {
                font-size: 0.95em;
                flex-direction: column; /* Stack items vertically */
                align-items: flex-start;
                padding: 15px 20px;
                gap: 10px;
            }
            .history-item-detail i {
                font-size: 1.8em;
                margin-top: 0; /* Reset margin top */
            }
            .history-item-detail div {
                gap: 5px; /* Smaller gap for stacked text */
            }


            .history-rekomendasi-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }

            .history-rekomendasi-card h4 {
                font-size: 1.3em;
            }

            .history-rekomendasi-card .score {
                font-size: 2em;
            }
        }

        @media screen and (max-width: 768px) {
            .navbar {
                padding: 10px 20px;
                flex-direction: column;
                height: auto;
                position: relative; /* Kembali ke relative agar tidak menutupi konten */
            }
            .navbar .logo-area {
                font-size: 1.5em;
            }
            .navbar .user-info-navbar .user-name {
                font-size: 0.9em;
            }
            .navbar .user-info-navbar .user-role {
                font-size: 0.7em;
            }

            body {
                flex-direction: column;
            }

            .main-layout {
                flex-direction: column;
                height: auto;
                padding-top: 0; /* Tidak perlu padding atas karena navbar tidak fixed */
            }

            .sidebar {
                position: relative; /* Kembali ke relative */
                width: 100%;
                height: auto; /* Biarkan tinggi menyesuaikan konten */
                box-shadow: 0 2px 5px var(--shadow-light);
                padding: 20px;
                overflow-y: visible;
                flex-direction: row;
                justify-content: center;
                flex-wrap: wrap;
                top: 0;
            }

            .sidebar:hover {
                width: 100%;
            }

            /* Always show text on small screens */
            .sidebar nav ul li a span {
                text-indent: 0; /* Tampilkan teks */
                max-width: none;
            }

            .sidebar nav ul {
                flex-wrap: wrap;
                justify-content: center;
                gap: 5px;
                margin-top: 0;
                width: 100%;
                justify-content: flex-start;
                flex-direction: row; /* Change back to row for horizontal layout on small screens */
                align-items: center; /* Center items vertically in the row */
                padding-top: 0;
                padding-bottom: 0;
            }

            .sidebar nav ul li {
                margin-bottom: 0;
            }

            .sidebar nav ul li a {
                padding: 8px 12px;
                font-size: 0.85em;
                gap: 8px;
                justify-content: center; /* Keep centered when collapsed */
            }
            .sidebar:hover nav ul li a {
                padding: 8px 12px;
                justify-content: center; /* Keep centered on hover for small screens */
                background-color: var(--main-accent-green); /* Warna hijau cerah saat hover */
                color: var(--dark-prime);
            }
            .sidebar nav ul li a.active {
                background-color: transparent; /* Remove background for active on small screens */
                color: var(--main-accent-green);
            }
            .sidebar nav ul li a.active:hover {
                background-color: var(--main-accent-green); /* Warna hijau cerah saat hover */
                color: var(--dark-prime);
            }

            /* Logout button responsive for sidebar on smaller screens (always circular, no text) */
            .sidebar .logout-button-container {
                width: auto; /* Allow button to shrink */
                padding: 0 5px 5px; /* Smaller padding */
                margin-top: 20px; /* Give some space */
            }
            .sidebar .logout-button-container a {
                width: 45px; /* Fixed smaller size */
                height: 45px;
                padding: 0; /* No padding needed as text is hidden */
                border-radius: 50%; /* Always circular */
                justify-content: center; /* Center icon */
                border: 2px solid var(--text-on-dark); /* Keep border */
                background-color: transparent; /* No background by default */
                color: var(--text-on-dark); /* Keep default text color */
            }
            .sidebar .logout-button-container a i {
                color: var(--text-on-dark);
            }
            .sidebar:hover .logout-button-container a { /* Keep hover effects simple for mobile */
                background-color: var(--main-accent-green);
                color: var(--dark-prime);
                border-color: var(--main-accent-green);
            }
            .sidebar:hover .logout-button-container a i {
                color: var(--dark-prime);
            }


            .main-content {
                width: 100%; /* Pastikan ini 100% untuk mengisi lebar */
                padding: 15px;
                margin-left: 0; /* Hapus margin-left karena sidebar tidak fixed */
                flex-basis: auto; /* Agar konten tidak mengecil secara tidak wajar */
            }
            .main-layout:hover .main-content {
                margin-left: 0; /* Hapus margin-left hover */
            }

            .header-bar {
                flex-direction: column;
                align-items: flex-start;
                padding: 15px;
                margin-bottom: 20px;
            }

            .header-bar h1 {
                font-size: 1.6em;
            }
            .header-bar .description {
                font-size: 0.85em;
            }

            /* Location Info Bar - Responsive for 768px */
            .history-item-detail {
                font-size: 0.9em;
                padding: 12px;
                gap: 10px; /* Adjust gap for smaller screens */
            }
            .history-item-detail i {
                font-size: 1.6em; /* Adjust icon size for smaller screens */
            }


            .history-rekomendasi-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .history-rekomendasi-card h4 {
                font-size: 1.6em;
            }

            .history-rekomendasi-card .score {
                font-size: 2em;
            }

            /* Table responsiveness for small screens (768px and below) */
            .history-table-container {
                padding: 15px;
            }

            .history-table {
                width: 100%;
                table-layout: auto; /* Use auto for better content fitting */
            }

            .history-table thead {
                display: none;
            }

            .history-table tbody,
            .history-table tr,
            .history-table td {
                display: block;
            }

            .history-table tr {
                border: 1px solid #ccc;
                margin-bottom: 10px;
                border-radius: var(--border-radius-main);
                overflow: hidden; /* Ensure rounded corners are visible */
            }

            .history-table td {
                border: none;
                border-bottom: 1px solid #eee;
                position: relative;
                padding-left: 50%; /* Space for the label */
                text-align: right;
                font-size: 0.9em;
            }

            .history-table td:last-child {
                border-bottom: none; /* No border for the last cell */
            }

            .history-table td:before {
                position: absolute;
                top: 12px;
                left: 15px; /* Adjust left padding for label */
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                text-align: left;
                font-weight: 600; /* Bolder label */
                color: var(--dark-prime); /* Darker label color */
                content: attr(data-label);
            }

            .history-table td.actions {
                text-align: center;
                padding-left: 15px; /* Reset padding for action buttons */
                padding-top: 10px;
                padding-bottom: 10px;
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 10px; /* Space between buttons */
            }

            .history-table td.actions:before {
                content: none;
            }

            /* Adjust action button size for mobile */
            .history-table .view-button,
            .history-table .delete-button {
                padding: 6px 10px;
                font-size: 0.8em;
            }
        }

        @media screen and (max-width: 480px) {
            .navbar .logo-area {
                font-size: 1.2em;
            }
            .navbar .user-info-navbar .user-name {
                font-size: 0.9em;
            }
            .navbar .user-info-navbar .user-role {
                font-size: 0.7em;
            }

            .sidebar nav ul {
                flex-direction: column;
                gap: 10px;
            }

            .sidebar nav ul li a {
                justify-content: center;
            }

            .header-bar h1 {
                font-size: 1.4em;
            }

            .history-rekomendasi-card h4 {
                font-size: 1.1em;
            }
        }
    </style>
</head>

<body>
    <header class="navbar">
        <a href="dashboard.php" class="logo-area">
            <i class="fas fa-leaf"></i> GoAgriculture
        </a>
        <div class="user-info-navbar">
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($username) ?></div>
                <div class="user-role"><?= htmlspecialchars(ucfirst($user_role)) ?></div>
            </div>
        </div>
    </header>

    <div class="main-layout">
        <aside class="sidebar">
            <nav>
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> <span>Beranda</span></a></li>
                    <li><a href="tanaman.php"><i class="fas fa-seedling"></i> <span>Daftar Tanaman</span></a></li>
                    <li><a href="riwayat.php" class="active"><i class="fas fa-history"></i> <span>Riwayat</span></a></li>
                    <li><a href="profil.php"><i class="fas fa-user-circle"></i> <span>Profil</span></a></li>
                    <?php if ($user_role === 'admin'): // Asumsi 'admin' adalah role untuk akses panel admin ?>
                        <li><a href="../admin/index.php"><i class="fas fa-cogs"></i> <span>Admin Panel</span></a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="logout-button-container">
                <a href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </div>
        </aside>

        <main class="main-content">
            <div class="header-bar">
                <h1>Riwayat Pencarian</h1>
                <p class="description">Lihat kembali riwayat pencarian rekomendasi tanaman Anda di sini.</p>
            </div>

            <?php if ($detail_entry) :
                // hasil_rekomendasi sudah didekode di bagian PHP atas
                $rekomendasi_detail = $detail_entry['hasil_rekomendasi'];
            ?>
                <div class="detail-section">
                    <h3>Detail Riwayat Pencarian</h3>
                    <div class="history-item-detail">
                        <i class="fas fa-compass"></i>
                        <div>
                            <p><strong>Tanggal Pencarian:</strong> <?= htmlspecialchars(date('d F Y H:i', strtotime($detail_entry['created_at']))) ?></p>
                            <p><strong>Lokasi:</strong> <?= htmlspecialchars($detail_entry['lokasi']) ?></p>
                            <p><strong>Lintang:</strong> <?= htmlspecialchars($detail_entry['latitude']) ?></p>
                            <p><strong>Bujur:</strong> <?= htmlspecialchars($detail_entry['longitude']) ?></p>
                            <p><strong>Ketinggian:</strong> <?= htmlspecialchars($detail_entry['ketinggian']) ?> m</p>
                            <p><strong>Curah Hujan Tahunan:</b> <?= htmlspecialchars($detail_entry['curah_hujan']) ?> mm</p>
                        </div>
                    </div>

                    <h4>Rekomendasi Tanaman:</h4>
                    <?php if (!empty($rekomendasi_detail)) : ?>
                        <div class="history-rekomendasi-grid">
                            <?php foreach ($rekomendasi_detail as $r) : ?>
                                <div class="history-rekomendasi-card">
                                    <h4><?= htmlspecialchars($r['tanaman']) ?></h4>
                                    <p><strong>Kategori:</strong> <?= htmlspecialchars($r['kategori']) ?></p>
                                    <p><strong>Kecocokan:</strong> <span class="score"><?= htmlspecialchars($r['skor']) ?>%</span></p>
                                    <p>Tingkat kecocokan berdasarkan kriteria lokasi.</p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div class="no-result">
                            <p><i class="fas fa-exclamation-triangle"></i> Tidak ada rekomendasi tanaman yang tersimpan untuk entri ini.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="history-table-container">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Lokasi</th>
                                <th>Lat</th>
                                <th>Lng</th>
                                <th>Curah Hujan</th>
                                <th>Ketinggian</th>
                                <th>Hasil Rekomendasi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($riwayat) > 0) : ?>
                                <?php foreach ($riwayat as $r) : ?>
                                    <tr>
                                        <td data-label="Tanggal"><?= date('d F Y H:i', strtotime($r['created_at'])) ?></td>
                                        <td data-label="Lokasi"><?= htmlspecialchars($r['lokasi']) ?></td>
                                        <td data-label="Lat"><?= htmlspecialchars($r['latitude']) ?></td>
                                        <td data-label="Lng"><?= htmlspecialchars($r['longitude']) ?></td>
                                        <td data-label="Curah Hujan"><?= htmlspecialchars($r['curah_hujan']) ?> mm</td>
                                        <td data-label="Ketinggian"><?= htmlspecialchars($r['ketinggian']) ?> m</td>
                                        <td data-label="Hasil Rekomendasi">
                                            <?php
                                            $hasil = $r['hasil_rekomendasi']; // Sudah didekode di atas
                                            if (is_array($hasil) && !empty($hasil)) {
                                                $display_limit = 2; // Tampilkan hanya 2 rekomendasi teratas untuk ringkasan
                                                $count = 0;
                                                foreach ($hasil as $h) {
                                                    if ($count < $display_limit) {
                                                        echo htmlspecialchars($h['tanaman']) . ' (' . htmlspecialchars($h['skor']) . '%)<br>';
                                                        $count++;
                                                    } else {
                                                        echo '...';
                                                        break;
                                                    }
                                                }
                                            } else {
                                                echo "<i>Tidak ada hasil</i>";
                                            }
                                            ?>
                                        </td>
                                        <td class="actions">
                                            <a href="?detail_id=<?= htmlspecialchars($r['id_riwayat']) ?><?= $lat_lng_params ?>" class="view-button">
                                                <i class="fas fa-eye"></i> Detail
                                            </a>
                                            <a href="riwayat.php?action=delete&id=<?= htmlspecialchars($r['id_riwayat']) ?><?= $lat_lng_params ?>" class="delete-button" onclick="return confirm('Apakah Anda yakin ingin menghapus riwayat ini?');">
                                                <i class="fas fa-trash-alt"></i> Hapus
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 20px;">
                                        <div class="no-result">
                                            <p><i class="fas fa-info-circle"></i> Belum ada riwayat pencarian yang tersimpan.</p>
                                            <p>Mulai cari rekomendasi tanaman di <a href="dashboard.php" style="color: var(--primary-color); font-weight: 500;">Dashboard</a> Anda!</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="export-links">
                    <a href="export_excel.php?user_id=<?= htmlspecialchars($id_user) ?><?= $lat_lng_params ?>" target="_blank"><i class="fas fa-file-excel"></i> Export ke Excel</a>
                    <a href="export_pdf.php?user_id=<?= htmlspecialchars($id_user) ?><?= $lat_lng_params ?>" target="_blank"><i class="fas fa-file-pdf"></i> Export ke PDF</a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // PHP variables for message handling
        const jsMessage = "<?= addslashes($js_message) ?>";
        const jsMessageType = "<?= addslashes($js_message_type) ?>";

        // Fungsi untuk menangani pesan notifikasi mengambang
        window.onload = function() {
            if (jsMessage && jsMessageType) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `message ${jsMessageType}`;
                alertDiv.innerHTML = `<i class="fas fa-${jsMessageType === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${jsMessage}`;
                
                // Tambahkan langsung ke body untuk pesan mengambang
                document.body.appendChild(alertDiv);

                setTimeout(() => {
                    alertDiv.style.opacity = '0';
                    alertDiv.addEventListener('transitionend', () => alertDiv.remove());
                }, 5000);
                const url = new URL(window.location.href);
                url.searchParams.delete('message');
                url.searchParams.delete('type');
                window.history.replaceState({}, document.title, url.toString());
            }

            // Tandai menu aktif di sidebar
            const currentPath = window.location.pathname.split('/').pop();
            document.querySelectorAll('.sidebar nav ul li a').forEach(link => {
                const linkPath = link.getAttribute('href').split('/').pop();

                // Spesial handling untuk link Logout agar tidak ditandai sebagai 'active'
                if (link.closest('.logout-button-container')) {
                    return; // Lewati tombol logout
                }

                // Cek apakah ada parameter detail_id, jika iya, tetap tandai riwayat.php sebagai aktif
                if (currentPath.startsWith('riwayat.php') && linkPath === 'riwayat.php') {
                    link.classList.add('active');
                } else if (linkPath === currentPath) {
                    link.classList.add('active');
                }
            });
            const urlParams = new URLSearchParams(window.location.search);
            const lat = urlParams.get('lat');
            const lng = urlParams.get('lng');

            if (lat && lng) {
                const paramsToAdd = `?lat=${lat}&lng=${lng}`;

                // Modifikasi link "Beranda" di sidebar
                const dashboardNavLink = document.querySelector('.sidebar nav ul li a[href="dashboard.php"]');
                if (dashboardNavLink) {
                    dashboardNavLink.setAttribute('href', `dashboard.php${paramsToAdd}`);
                }

                // Modifikasi link "Tanaman" di sidebar
                const tanamanNavLink = document.querySelector('.sidebar nav ul li a[href="tanaman.php"]');
                if (tanamanNavLink) {
                    tanamanNavLink.setAttribute('href', `tanaman.php${paramsToAdd}`);
                }

                // Modifikasi link "Profil" di sidebar
                const profilNavLink = document.querySelector('.sidebar nav ul li a[href="profil.php"]');
                if (profilNavLink) {
                    profilNavLink.setAttribute('href', `profil.php${paramsToAdd}`);
                }

                const riwayatNavLink = document.querySelector('.sidebar nav ul li a[href="riwayat.php"]');
                if (riwayatNavLink) {
                    riwayatNavLink.setAttribute('href', `riwayat.php${paramsToAdd}`);
                }
                // Link "Riwayat" tidak perlu dimodifikasi karena sudah aktif dan tidak perlu parameter lat/lng
            }
        };
    </script>
</body>
</html>
