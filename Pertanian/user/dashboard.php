<?php
session_start();
error_reporting(E_ALL); // Aktifkan semua laporan error
ini_set('display_errors', 1); // Tampilkan error di browser

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/elevation.php';
require_once __DIR__ . '/../lib/rainfall.php';
require_once __DIR__ . '/../lib/scoring.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit();
}

$username = $_SESSION['user']['username'] ?? 'Pengguna';
$user_role = $_SESSION['user']['role'] ?? 'user'; // Dapatkan role user

$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;

$lokasi = "Tidak ada lokasi";
$elevasi = null;
$curahHujan = null;
$rekomendasi = [];

// === Mulai penambahan untuk caching rekomendasi ===
$cache_lifetime = 300; // Durasi cache dalam detik (300 detik = 5 menit)
$use_cache = false;

// Periksa apakah ada data lokasi yang tersimpan di session dan cocok dengan lokasi saat ini
if (
    isset($_SESSION['last_location_data']) &&
    isset($_SESSION['last_location_data']['lat']) &&
    isset($_SESSION['last_location_data']['lng']) &&
    isset($_SESSION['last_location_data']['timestamp'])
) {
    $cached_lat_rounded = round($_SESSION['last_location_data']['lat'], 7); // 7 desimal cukup akurat
    $cached_lng_rounded = round($_SESSION['last_location_data']['lng'], 7);
    $current_lat_rounded = round($lat, 7);
    $current_lng_rounded = round($lng, 7);

    // Periksa apakah lat dan lng saat ini sama dengan yang di-cache
    // Dan apakah cache masih valid (belum kadaluarsa)
    if (
        $cached_lat_rounded === $current_lat_rounded &&
        $cached_lng_rounded === $current_lng_rounded &&
        (time() - $_SESSION['last_location_data']['timestamp'] < $cache_lifetime)
    ) {

        $use_cache = true;
        // Gunakan data dari session (cache)
        $lokasi = $_SESSION['last_location_data']['lokasi'];
        $elevasi = $_SESSION['last_location_data']['elevasi'];
        $curahHujan = $_SESSION['last_location_data']['curahHujan'];
        $rekomendasi = $_SESSION['last_location_data']['rekomendasi'];
    }
}
// === Akhir penambahan untuk caching rekomendasi ===

// Jika tidak menggunakan cache atau lokasi berubah/kadaluarsa, hitung ulang
if (!$use_cache && $lat && $lng) {
    $lokasi = "$lat, $lng";
    $elevasi = getElevation($lat, $lng);
    $curahHujan = getRainfall($lat, $lng);
    $rekomendasi = getRekomendasi($elevasi, $curahHujan, $conn);

    // Simpan data hasil perhitungan ke session untuk caching
    $_SESSION['last_location_data'] = [
        'lat' => $lat,
        'lng' => $lng,
        'lokasi' => $lokasi,
        'elevasi' => $elevasi,
        'curahHujan' => $curahHujan,
        'rekomendasi' => $rekomendasi,
        'timestamp' => time()
    ];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Dashboard - GoAgriculture</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="/manifest.json">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        /* Define your color variables */
        :root {
            --dark-prime: #2A5234;
            /* Main dark green for header/accents - sedikit lebih gelap */
            --light-bg: #F5F5F5;
            /* Very light grey/off-white for body background */
            --main-accent-green: #70C174;
            /* Primary accent green - sedikit lebih terang */
            --main-accent-green-rgb: 112, 193, 116;
            /* RGB value for transparency */
            --secondary-accent-green: #4CAF50;
            /* A slightly darker shade of accent green for hover */
            --text-on-dark: #FFFFFF;
            /* White text on dark backgrounds */
            --text-on-light: #333333;
            /* Dark grey text on light backgrounds */
            --text-muted: #666666;
            /* Muted grey for secondary text */
            --accent-blue: #2196F3;
            /* Standard blue for links */
            --accent-yellow: #FFC107;
            /* Warning yellow */
            --shadow-light: rgba(0, 0, 0, 0.1);
            --border-radius-main: 8px;
            --border-radius-sm: 4px;
            --sidebar-width-open: 280px;
            --sidebar-width-closed: 80px;
            /* Reduced width when collapsed */
            --navbar-height: 70px;
            /* Tinggi navbar */
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
            flex-direction: column;
            /* Stack navbar and main content area */
            min-height: 100vh;
        }

        /* Navbar Styling */
        .navbar {
            background-color: var(--dark-prime);
            color: var(--text-on-dark);
            height: var(--navbar-height);
            /* Set tinggi navbar */
            padding: 0 30px;
            /* Padding vertikal 0, horisontal 30px */
            display: flex;
            align-items: center;
            justify-content: space-between;
            /* Pisahkan logo dan info user */
            box-shadow: 0 2px 10px var(--shadow-light);
            z-index: 1000;
            position: sticky;
            top: 0;
            left: 0;
            width: 100%;
        }

        .navbar .logo-area {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8em;
            font-weight: 600;
            /* Font weight adjusted */
            color: var(--text-on-dark);
            /* GoAgriculture text color changed to white */
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .navbar .logo-area i {
            font-size: 1.2em;
            color: var(--main-accent-green);
            /* Icon color remains green */
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
            text-align: right;
            /* Nama dan role di kanan */
        }

        .navbar .user-info-navbar .user-name {
            font-family: 'Montserrat', sans-serif;
            font-size: 1em;
            font-weight: 500;
            /* Font weight adjusted */
        }

        .navbar .user-info-navbar .user-role {
            font-size: 0.8em;
            color: #b0c2b6;
        }


        /* Main Layout (sidebar + content) */
        .main-layout {
            display: flex;
            flex-grow: 1;
            /* Allows main layout to take remaining height */
            width: 100%;
            height: calc(100vh - var(--navbar-height));
        }

        /* Sidebar Styling */
        .sidebar {
            width: var(--sidebar-width-closed);
            /* Start collapsed */
            background-color: var(--dark-prime);
            color: var(--text-on-dark);
            padding: 20px 0;
            /* Padding atas bawah 20px, samping 0 */
            box-shadow: 3px 0 8px var(--shadow-light);
            display: flex;
            /* Jadikan flex container */
            flex-direction: column;
            /* Susun item secara kolom */
            align-items: center;
            /* Pusatkan secara horizontal (saat collapsed) */
            position: sticky;
            top: var(--navbar-height);
            height: 100%;
            overflow-y: auto;
            transition: width 0.3s ease;
            flex-shrink: 0;
        }

        .sidebar:hover {
            width: var(--sidebar-width-open);
            /* Expand on hover */
        }

        /* Hide user info from sidebar */
        .sidebar .user-info {
            display: none;
        }

        /* Teks dalam span: Awalnya disembunyikan dengan text-indent negatif */
        .sidebar nav ul li a span {
            white-space: nowrap;
            overflow: hidden;
            text-indent: -9999px;
            /* Sembunyikan teks di luar layar */
            transition: text-indent 0.3s ease;
            display: inline-block;
            /* Agar text-indent bekerja */
            max-width: 0;
            /* Untuk transisi width yang lebih baik saat sidebar terbuka */
        }

        /* Saat sidebar di-hover, tampilkan teks */
        .sidebar:hover nav ul li a span {
            text-indent: 0;
            /* Kembalikan teks ke posisi normal */
            max-width: 200px;
            /* Lebar maksimum saat terlihat */
            transition: text-indent 0.3s ease, max-width 0.3s ease;
            /* Tambahkan max-width ke transisi */
        }


        /* Modifikasi ini untuk memusatkan secara vertikal */
        .sidebar nav {
            flex-grow: 1;
            /* Pastikan nav mengisi ruang yang tersedia */
            display: flex;
            flex-direction: column;
            justify-content: center;
            /* Pusatkan konten nav (ul) secara vertikal */
            width: 100%;
            /* Pastikan nav mengambil lebar penuh */
        }

        .sidebar nav ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
            /* Pastikan tidak ada margin yang mengganggu pemusatan */
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            /* Pusatkan item menu secara horizontal */
            padding-top: 0;
            padding-bottom: 0;
        }

        .sidebar nav ul li {
            margin-bottom: 10px;
            width: 100%;
            /* Ensure li takes full width */
        }

        /* DEFAULT STYLE FOR ALL MENU ITEMS (NOT ACTIVE, NOT HOVERED) */
        .sidebar nav ul li a {
            display: flex;
            align-items: center;
            justify-content: center;
            /* Centered for collapsed state */
            gap: 15px;
            color: var(--text-on-dark);
            /* Default: White text */
            text-decoration: none;
            padding: 12px 0px;
            /* Padding for collapsed state */
            border-radius: var(--border-radius-sm);
            transition: background-color 0.3s ease, color 0.3s ease, justify-content 0.3s ease, padding 0.3s ease;
            font-weight: 500;
            font-family: 'Roboto', sans-serif;
            width: 100%;
            background-color: transparent;
            /* Pastikan transparan secara default */
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
            /* background-color: var(--main-accent-green); <-- DIHAPUS */
            color: var(--text-on-dark);
            /* Tetap putih saat hover di sidebar ciut */
        }

        /* HOVER STYLE WHEN SIDEBAR IS EXPANDED (when sidebar itself is hovered) */
        /* Ini adalah gaya dasar saat sidebar terbuka, tanpa background pada link itu sendiri */
        .sidebar:hover nav ul li a {
            justify-content: flex-start;
            /* Align text to left when expanded */
            padding: 12px 20px;
            /* Add horizontal padding when expanded */
            background-color: transparent;
            /* Pastikan transparan saat sidebar melebar, kecuali dihover */
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
            width: 20px;
            /* Fixed width for icons */
            text-align: center;
            flex-shrink: 0;
            color: inherit;
            /* Penting! Agar warna ikon mengikuti warna teks link */
        }

        /* Force active icon color */
        .sidebar nav ul li a.active i {
            color: var(--dark-prime);
            /* Warna ikon saat aktif */
        }

        /* Ini juga penting untuk memastikan ikon berubah warna saat hover, mengikuti teks */
        .sidebar nav ul li a:not(.active):hover i {
            color: var(--text-on-dark);
            /* Tetap putih saat hover di sidebar ciut */
        }

        .sidebar:hover nav ul li a:not(.active):hover i {
            color: var(--dark-prime);
        }


        /* Logout button at the bottom of sidebar */
        .sidebar .logout-button-container {
            width: 100%;
            margin-top: auto;
            /* Push to the bottom */
            padding: 0 15px 15px;
            /* Padding bottom and horizontal */
            display: flex;
            justify-content: center;
            /* Center horizontally */
        }

        .sidebar .logout-button-container a {
            display: flex;
            align-items: center;
            justify-content: center;
            /* Center icon */
            background-color: transparent;
            /* No background on logout by default */
            color: var(--text-on-dark);
            /* White text */
            text-decoration: none;
            border: 2px solid var(--text-on-dark);
            /* White circular border */
            border-radius: 50%;
            /* Make it circular */
            transition: all 0.3s ease;
            font-weight: 500;
            font-family: 'Roboto', sans-serif;
            width: 50px;
            /* Fixed width/height for circular */
            height: 50px;
            flex-shrink: 0;
            overflow: hidden;
            /* Hide text (span) when circular */
            position: relative;
            /* For the absolute positioning of text */
        }

        .sidebar .logout-button-container a:hover {
            background-color: var(--main-accent-green);
            /* Green background on hover */
            color: var(--dark-prime);
            /* Dark text on hover */
            border-color: var(--main-accent-green);
            /* Green border on hover */
        }

        .sidebar .logout-button-container a i {
            font-size: 1.1em;
            width: 20px;
            /* Fixed width for icon */
            text-align: center;
            color: var(--text-on-dark);
            /* Icon color white */
            transition: color 0.3s ease;
        }

        .sidebar .logout-button-container a:hover i {
            color: var(--dark-prime);
            /* Icon color dark on hover */
        }

        .sidebar .logout-button-container a span {
            /* Hide the text always for this request */
            display: none;
        }


        /* Main Content Area */
        .main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            background-color: var(--light-bg);
            padding: 25px;
            overflow-y: auto;
        }

        /* Header Bar - REVISED STYLES */
        .header-bar {
            background-color: var(--dark-prime);
            /* Dark green background, same as sidebar */
            color: var(--text-on-dark);
            /* White text */
            padding: 20px 30px;
            border-radius: var(--border-radius-main);
            box-shadow: 0 4px 15px var(--shadow-light);
            margin-bottom: 25px;
            display: flex;
            flex-direction: column;
            /* Stack title and description */
            align-items: flex-start;
            /* Align content to the left */
        }

        .header-bar h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.2em;
            font-weight: 600;
            /* Font weight adjusted */
            color: var(--text-on-dark);
            /* White for title */
            margin: 0;
        }

        .header-bar .description {
            font-size: 0.95em;
            color: #b0c2b6;
            /* Muted white for description */
            margin-top: 5px;
            /* Space between title and description */
            line-height: 1.5;
        }


        /* Location Info Bar - REVISED STYLES */
        .location-info-bar {
            background-color: #e8f5e9;
            /* Lighter green background */
            padding: 20px 25px;
            /* Increased padding */
            border-radius: var(--border-radius-main);
            /* Rounded corners for the whole bar */
            border-left: 6px solid var(--main-accent-green);
            /* Thicker, more prominent left border */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            /* Subtle shadow for depth */
            margin-bottom: 25px;
            display: flex;
            align-items: flex-start;
            /* Align icon and text to the top */
            gap: 20px;
            /* Increased gap between icon and text */
            font-family: 'Roboto', sans-serif;
            color: var(--dark-prime);
        }

        .location-info-bar i {
            font-size: 2em;
            /* Larger icon */
            color: var(--secondary-accent-green);
            /* Slightly darker green for icon */
            flex-shrink: 0;
            /* Prevent icon from shrinking */
            margin-top: 2px;
            /* Slight adjustment for visual alignment */
        }

        .location-info-bar div {
            /* Container for the text lines */
            display: flex;
            flex-direction: column;
            gap: 8px;
            /* Spacing between each line of info */
        }

        .location-info-bar p {
            margin: 0;
            font-size: 1.05em;
            line-height: 1.4;
            /* Improve line spacing */
        }

        .location-info-bar strong {
            font-weight: 600;
            /* Font weight adjusted */
            color: var(--dark-prime);
        }

        /* Card Grid */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .card {
            background-color: var(--text-on-dark);
            padding: 25px;
            border-radius: var(--border-radius-main);
            box-shadow: 0 5px 15px var(--shadow-light);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            border-top: 5px solid transparent;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        /* Card specific border colors */
        .card.location-card {
            border-top-color: var(--main-accent-green);
        }

        .card.elevation-card {
            border-top-color: #5DADE2;
        }

        .card.rainfall-card {
            border-top-color: #3498db;
        }

        .card h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5em;
            color: var(--dark-prime);
            margin-bottom: 15px;
            font-weight: 500;
            /* Font weight adjusted */
        }

        .card p {
            font-size: 1.1em;
            color: var(--text-muted);
            margin: 0;
            font-family: 'Roboto', sans-serif;
        }

        .card .value {
            font-size: 2.5em;
            font-weight: 600;
            /* Font weight adjusted */
            color: var(--main-accent-green);
            margin-top: 10px;
            margin-bottom: 10px;
        }

        /* Recommendation Section */
        .rekomendasi-section h3 {
            font-family: 'Montserrat', sans-serif;
            color: var(--dark-prime);
            text-align: center;
            margin-bottom: 25px;
            font-size: 2em;
            font-weight: 600;
            /* Font weight adjusted */
        }

        .rekomendasi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .rekomendasi-card {
            background: var(--text-on-dark);
            border-left: 6px solid var(--main-accent-green);
            border-radius: var(--border-radius-main);
            padding: 20px;
            box-shadow: 0 2px 8px var(--shadow-light);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .rekomendasi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .rekomendasi-card h4 {
            font-family: 'Montserrat', sans-serif;
            margin-top: 0;
            margin-bottom: 10px;
            color: var(--dark-prime);
            font-size: 1.3em;
            font-weight: 500;
            /* Font weight adjusted */
        }

        .rekomendasi-card p {
            margin: 5px 0;
            color: var(--text-muted);
            font-size: 0.95em;
            font-family: 'Roboto', sans-serif;
        }

        .rekomendasi-card .score {
            font-weight: 500;
            /* Font weight adjusted */
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
            font-weight: 400;
            /* Font weight adjusted */
            margin-top: 20px;
        }

        /* Save Form */
        .form-simpan {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px dashed #e0e0e0;
        }

        .form-simpan button {
            padding: 12px 25px;
            background: var(--main-accent-green);
            color: var(--text-on-dark);
            border: none;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-size: 1.05em;
            font-weight: 500;
            /* Font weight adjusted */
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
        }

        .form-simpan button:hover {
            background: var(--secondary-accent-green);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
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
                text-indent: 0;
                /* Tampilkan teks */
                max-width: 150px;
                /* Atur lebar sesuai kebutuhan di mode terbuka */
            }

            .sidebar nav ul li a {
                justify-content: center;
                /* Keep centered */
                padding: 12px 0;
            }

            .sidebar:hover nav ul li a {
                justify-content: flex-start;
                /* Align text to left when expanded on hover */
                padding: 12px 20px;
            }

            /* Logout button responsive for sidebar (collapsed on smaller desktop/tablet) */
            .sidebar .logout-button-container {
                padding: 0 10px 10px;
                /* Smaller padding */
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
            }

            .header-bar {
                padding: 15px 20px;
            }

            .header-bar h1 {
                font-size: 1.8em;
            }

            /* Location Info Bar - Responsive */
            .location-info-bar {
                font-size: 0.95em;
                flex-direction: column;
                /* Stack items vertically */
                align-items: flex-start;
                padding: 15px 20px;
                gap: 10px;
            }

            .location-info-bar i {
                font-size: 1.8em;
                margin-top: 0;
                /* Reset margin top */
            }

            .location-info-bar div {
                gap: 5px;
                /* Smaller gap for stacked text */
            }


            .card-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }

            .card h3 {
                font-size: 1.3em;
            }

            .card .value {
                font-size: 2em;
            }

            .rekomendasi-section h3 {
                font-size: 1.8em;
            }

            .rekomendasi-grid {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 15px;
            }
        }

        @media screen and (max-width: 768px) {
            .navbar {
                padding: 10px 20px;
                flex-direction: column;
                height: auto;
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
            }

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
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
                text-indent: 0;
                /* Tampilkan teks */
                max-width: none;
            }

            .sidebar nav ul {
                flex-wrap: wrap;
                justify-content: center;
                gap: 5px;
                margin-top: 0;
                width: 100%;
                /* Reset vertical centering for small screens if not desired */
                justify-content: flex-start;
                flex-direction: row;
                /* Change back to row for horizontal layout on small screens */
                align-items: center;
                /* Center items vertically in the row */
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
                justify-content: center;
                /* Keep centered when collapsed */
            }

            .sidebar:hover nav ul li a {
                padding: 8px 12px;
                justify-content: center;
                /* Keep centered on hover for small screens */
                background-color: var(--main-accent-green);
                /* Warna hijau cerah saat hover */
                color: var(--dark-prime);
            }

            .sidebar nav ul li a.active {
                background-color: transparent;
                /* Remove background for active on small screens */
                color: var(--main-accent-green);
            }

            .sidebar nav ul li a.active:hover {
                background-color: var(--main-accent-green);
                /* Warna hijau cerah saat hover */
                color: var(--dark-prime);
            }

            /* Logout button responsive for sidebar on smaller screens (always circular, no text) */
            .sidebar .logout-button-container {
                width: auto;
                /* Allow button to shrink */
                padding: 0 5px 5px;
                /* Smaller padding */
                margin-top: 20px;
                /* Give some space */
            }

            .sidebar .logout-button-container a {
                width: 45px;
                /* Fixed smaller size */
                height: 45px;
                padding: 0;
                /* No padding needed as text is hidden */
                border-radius: 50%;
                /* Always circular */
                justify-content: center;
                /* Center icon */
                border: 2px solid var(--text-on-dark);
                /* Keep border */
                background-color: transparent;
                /* No background by default */
                color: var(--text-on-dark);
                /* Keep default text color */
            }

            .sidebar .logout-button-container a i {
                color: var(--text-on-dark);
            }

            .sidebar:hover .logout-button-container a {
                /* Keep hover effects simple for mobile */
                background-color: var(--main-accent-green);
                color: var(--dark-prime);
                border-color: var(--main-accent-green);
            }

            .sidebar:hover .logout-button-container a i {
                color: var(--dark-prime);
            }


            .main-content {
                width: 100%;
                padding: 15px;
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
            .location-info-bar {
                font-size: 0.9em;
                padding: 12px;
                gap: 10px;
                /* Adjust gap for smaller screens */
            }

            .location-info-bar i {
                font-size: 1.6em;
                /* Adjust icon size for smaller screens */
            }


            .card-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .rekomendasi-section h3 {
                font-size: 1.6em;
            }

            .rekomendasi-grid {
                grid-template-columns: 1fr;
                gap: 12px;
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

            .card h3 {
                font-size: 1.1em;
            }

            .card .value {
                font-size: 1.8em;
            }

            .rekomendasi-card h4 {
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
                    <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> <span>Beranda</span></a></li>
                    <li><a href="tanaman.php"><i class="fas fa-seedling"></i> <span>Daftar Tanaman</span></a></li>
                    <li><a href="riwayat.php"><i class="fas fa-history"></i> <span>Riwayat</span></a></li>
                    <li><a href="profil.php"><i class="fas fa-user-circle"></i> <span>Profil</span></a></li>
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
                <h1>Dashboard</h1>
                <p class="description">Selamat datang di Dashboard GoAgriculture! Temukan rekomendasi tanaman terbaik untuk lokasi Anda.</p>
            </div>

            <?php if (!$lat || !$lng): ?>
                <div class="no-result">
                    <p><i class="fas fa-map-marker-alt"></i> Lokasi belum terdeteksi. Silakan berikan izin lokasi atau coba muat ulang halaman.</p>
                    <p>Mengarahkan Anda ke halaman pengambilan lokasi...</p>
                </div>
            <?php else: ?>
                <div class="location-info-bar">
                    <i class="fas fa-compass"></i>
                    <div>
                        <p><strong>Lokasi Anda:</strong> <?= htmlspecialchars($lokasi) ?></p>
                        <p><strong>Ketinggian:</strong> <?= is_numeric($elevasi) ? round($elevasi) : 'N/A' ?> mdpl</p>
                        <p><strong>Curah Hujan Tahunan:</strong> <?= is_numeric($curahHujan) ? round($curahHujan, 2) : 'N/A' ?> mm/tahun</p>
                    </div>
                </div>

                <div class="card-grid">
                    <div class="card location-card">
                        <h3><i class="fas fa-map-marked-alt"></i> Lokasi</h3>
                        <p class="value"><?= htmlspecialchars($lat) ?><br><?= htmlspecialchars($lng) ?></p>
                        <p>Lintang & Bujur</p>
                    </div>
                    <div class="card elevation-card">
                        <h3><i class="fas fa-mountain"></i> Ketinggian</h3>
                        <p class="value"><?= round($elevasi) ?>m</p>
                        <p>Di atas permukaan laut</p>
                    </div>
                    <div class="card rainfall-card">
                        <h3><i class="fas fa-cloud-rain"></i> Curah Hujan</h3>
                        <p class="value"><?= round($curahHujan, 2) ?>mm</p>
                        <p>Rata-rata tahunan</p>
                    </div>
                </div>

                <section class="rekomendasi-section">
                    <h3>Rekomendasi Tanaman untuk Lokasi anda</h3>

                    <?php if (empty($rekomendasi)) : ?>
                        <div class="no-result">
                            <p><i class="fas fa-exclamation-triangle"></i> Tidak ada rekomendasi tanaman yang cocok untuk kondisi lokasi Anda saat ini.</p>
                            <p>Coba cek kembali informasi lokasi atau hubungi administrator.</p>
                        </div>
                    <?php else : ?>
                        <div class="rekomendasi-grid">
                            <?php foreach ($rekomendasi as $r) : ?>
                                <div class="rekomendasi-card">
                                    <h4><?= htmlspecialchars($r['tanaman']) ?></h4>
                                    <p><strong>Kategori:</strong> <?= htmlspecialchars($r['kategori']) ?></p>
                                    <p><strong>Kecocokan:</strong> <span class="score"><?= $r['skor'] ?>%</span></p>
                                    <p>Tingkat kecocokan berdasarkan kriteria lokasi.</p>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <form action="simpan_riwayat.php" method="POST" class="form-simpan">
                            <input type="hidden" name="lat" value="<?= htmlspecialchars($lat) ?>">
                            <input type="hidden" name="lng" value="<?= htmlspecialchars($lng) ?>">
                            <input type="hidden" name="lokasi" value="<?= htmlspecialchars($lokasi) ?>">
                            <input type="hidden" name="curah_hujan" value="<?= htmlspecialchars(round($curahHujan, 2)) ?>">
                            <input type="hidden" name="ketinggian" value="<?= htmlspecialchars(round($elevasi)) ?>">
                            <input type="hidden" name="hasil_rekomendasi" value='<?= htmlspecialchars(json_encode($rekomendasi), ENT_QUOTES, 'UTF-8') ?>'>
                            <button type="submit"><i class="fas fa-save"></i> Simpan ke Riwayat</button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tandai menu aktif
            const currentPath = window.location.pathname;
            document.querySelectorAll('.sidebar nav ul li a').forEach(link => {
                const linkPath = link.getAttribute('href').split('/').pop();
                const currentFileName = currentPath.split('/').pop();

                // Spesial handling untuk link Logout agar tidak ditandai sebagai 'active'
                if (link.closest('.logout-button-container')) {
                    return; // Skip logout button
                }

                if (linkPath === currentFileName) {
                    link.classList.add('active');
                }
            });

            const urlParams = new URLSearchParams(window.location.search);
            const lat = urlParams.get('lat');
            const lng = urlParams.get('lng');

            // Hanya jika lat dan lng tersedia di URL saat ini
            if (lat && lng) {
                const paramsToAdd = `?lat=${lat}&lng=${lng}`;

                // 1. Modifikasi link "Beranda" di sidebar
                const dashboardNavLink = document.querySelector('.sidebar nav ul li a[href="dashboard.php"]');
                if (dashboardNavLink) {
                    dashboardNavLink.setAttribute('href', `dashboard.php${paramsToAdd}`);
                }

                // 2. Modifikasi link "Riwayat" di sidebar
                const riwayatNavLink = document.querySelector('.sidebar nav ul li a[href="riwayat.php"]');
                if (riwayatNavLink) {
                    riwayatNavLink.setAttribute('href', `riwayat.php${paramsToAdd}`);
                }

                // Jika Anda punya link lain seperti "tanaman.php" atau "profil.php" yang juga perlu mempertahankan lat/lng:
                const tanamanNavLink = document.querySelector('.sidebar nav ul li a[href="tanaman.php"]');
                if (tanamanNavLink) {
                    tanamanNavLink.setAttribute('href', `tanaman.php${paramsToAdd}`);
                }

                const profilNavLink = document.querySelector('.sidebar nav ul li a[href="profil.php"]');
                if (profilNavLink) {
                    profilNavLink.setAttribute('href', `profil.php${paramsToAdd}`);
                }
            }
            const currentUrlParams = new URLSearchParams(window.location.search);
            if (!currentUrlParams.has('lat') || !currentUrlParams.has('lng')) {
            }
        });
    </script>
</body>

</html>