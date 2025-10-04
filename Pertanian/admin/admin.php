<?php
// === admin/index.php ===
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}
$username = $_SESSION['user']['username'];
require_once '../lib/db.php';

$recent_recommendations = [];
$error_message = '';

$limit_per_page = 5;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * $limit_per_page;

$total_riwayat_rekomendasi = 0;
try {
    // Hitung total riwayat rekomendasi untuk paginasi
    $stmt_count_riwayat = $conn->query("SELECT COUNT(*) FROM riwayat");
    $total_riwayat_rekomendasi = $stmt_count_riwayat->fetchColumn();
} catch (PDOException $e) {
    // Tangani error jika gagal menghitung total
    error_log("Error counting riwayat for pagination: " . $e->getMessage());
}

$total_pages = ceil($total_riwayat_rekomendasi / $limit_per_page);
// Pastikan current_page tidak melebihi total_pages jika total_pages > 0
if ($total_pages > 0 && $current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $limit_per_page; // Sesuaikan offset
}
if ($total_pages == 0) { // Jika tidak ada data sama sekali
    $current_page = 1;
    $offset = 0;
}

try {
    $stmt = $conn->prepare("SELECT
                                r.id_riwayat,
                                r.created_at,
                                u.username,
                                r.lokasi,
                                r.latitude,
                                r.longitude,
                                r.ketinggian,
                                r.curah_hujan,
                                r.hasil_rekomendasi
                            FROM
                                riwayat r
                            JOIN
                                user u ON r.id_user = u.id_user
                            ORDER BY
                                r.created_at DESC
                            LIMIT :limit OFFSET :offset;
    ");
    $stmt->bindParam(':limit', $limit_per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $recent_recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Gagal mengambil data riwayat rekomendasi: " . $e->getMessage();
    error_log("Error fetching recent recommendations with pagination: " . $e->getMessage());
}

// Mengambil data statistik untuk Cards
$total_tanaman = 0;
$total_aturan = 0;
$total_pengguna = 0;
$total_rekomendasi = $total_riwayat_rekomendasi;

try {
    $stmt_tanaman = $conn->query("SELECT COUNT(*) FROM tanaman");
    $total_tanaman = $stmt_tanaman->fetchColumn();

    $stmt_aturan = $conn->query("SELECT COUNT(*) FROM aturan");
    $total_aturan = $stmt_aturan->fetchColumn();

    $stmt_pengguna = $conn->query("SELECT COUNT(*) FROM user");
    $total_pengguna = $stmt_pengguna->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - GoAgriculture</title>
    <link rel="manifest" href="/manifest.json">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        :root {
            /* Warna Dasar & Latar Belakang */
            --dark-prime: #3D724D;
            /* Main dark green for navbar, header */
            --light-bg: #F5F5F5;
            /* Very light grey/off-white for body background, cards */
            --sidebar-bg: #315F40;
            /* Slightly deeper green for sidebar */
            --main-accent-green: #66BB6A;
            /* Primary accent green (e.g., active links, card icons, numbers) */
            --secondary-accent-green: #4CAF50;
            /* A slightly darker shade of accent green for hover/contrast */
            --accent-blue: #2196F3;
            /* Keeping blue for general links */
            --accent-red: #D32F2F;
            /* Deep red for danger */
            --text-on-dark: #FFFFFF;
            /* White text on dark backgrounds */
            --text-on-light: #333333;
            --text-muted: #666666;
            --shadow-light: rgba(0, 0, 0, 0.1);
            --border-light: rgba(0, 0, 0, 0.1);
            --border-dark: rgba(255, 255, 255, 0.1);

            --transition-speed: 0.3s;
            --border-radius-main: 8px;
            --border-radius-sm: 4px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            line-height: 1.6;
            color: var(--text-on-light);
            background-color: var(--light-bg);
            overflow-x: hidden;
            font-size: 15px;
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

        a {
            text-decoration: none;
            color: var(--accent-blue);
            transition: color var(--transition-speed) ease;
        }

        a:hover {
            color: #64B5F6;
        }

        ul {
            list-style: none;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            color: var(--text-on-light);
            font-weight: 600;
        }

        /* --- Navbar (Bilah Navigasi Atas) --- */
        .navbar {
            background-color: var(--dark-prime);
            color: var(--text-on-dark);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 15px var(--shadow-light);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }

        .navbar .app-brand {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8em;
            font-weight: 700;
            color: var(--text-on-dark);
            display: flex;
            align-items: center;
        }

        .navbar .app-brand i {
            margin-right: 10px;
            color: var(--main-accent-green);
            font-size: 1.1em;
        }

        .navbar .user-info {
            display: flex;
            align-items: center;
            font-size: 1em;
        }

        .navbar .user-info span {
            margin-right: 15px;
            color: var(--text-on-dark);
        }

        .navbar .user-info span i {
            margin-right: 5px;
            color: var(--main-accent-green);
        }

        /* Tombol Toggle Sidebar (default: tersembunyi di desktop) */
        .sidebar-toggle-btn {
            display: none;
            font-size: 1.5em;
            color: var(--text-on-dark);
            cursor: pointer;
        }

        /* --- Wrapper (Flex Container Utama untuk Sidebar + Konten) --- */
        .wrapper {
            display: flex;
            padding-top: 70px;
            /* Sesuaikan dengan tinggi navbar */
            min-height: 100vh;
        }

        /* --- Sidebar Styling for Desktop --- */
        .sidebar {
            width: 80px;
            /* Lebar default saat tersembunyi */
            background-color: var(--sidebar-bg);
            color: var(--text-on-dark);
            padding: 20px 0;
            box-shadow: 2px 0 15px var(--shadow-light);
            position: sticky;
            top: 70px;
            height: calc(100vh - 70px);
            overflow: hidden;
            /* Sembunyikan teks yang melampaui batas */
            z-index: 999;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: width var(--transition-speed) ease;
            /* Transisi untuk perubahan lebar */
        }

        .sidebar:hover {
            width: 250px;
            /* Lebar saat di-hover */
        }
        
        /* Sidebar header (untuk desktop) */
        .sidebar-header {
            text-align: center;
            margin-bottom: 0;
            padding: 20px 0;
            border-bottom: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: all var(--transition-speed) ease;
        }

        .sidebar:hover .sidebar-header {
            margin-bottom: 30px;
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--border-dark);
        }

        .sidebar-header h3,
        .sidebar-header p {
            opacity: 0;
            height: 0;
            overflow: hidden;
            transition: opacity var(--transition-speed) ease, height var(--transition-speed) ease, margin var(--transition-speed) ease;
            white-space: nowrap;
            pointer-events: none;
        }

        .sidebar:hover .sidebar-header h3,
        .sidebar:hover .sidebar-header p {
            opacity: 1;
            height: auto;
            pointer-events: auto;
        }

        .sidebar:hover .sidebar-header h3 {
            margin-top: 10px;
        }

        .sidebar:hover .sidebar-header p {
            margin-top: 0;
        }
        
        /* Mobile user info (tersembunyi di desktop) */
        .mobile-user-info {
            display: none;
        }
        
        /* Sidebar Navigasi (untuk desktop) */
        .sidebar-nav ul {
            flex-grow: 1;
        }

        .sidebar-nav ul li {
            margin-bottom: 5px;
        }

        .sidebar-nav ul li a {
            display: flex;
            align-items: center;
            padding: 12px 0;
            justify-content: center;
            color: var(--text-on-dark);
            border-left: 4px solid transparent;
            transition: all var(--transition-speed) ease;
            font-weight: 500;
        }

        .sidebar:hover .sidebar-nav ul li a {
            padding: 12px 25px;
            justify-content: flex-start;
        }

        .sidebar-nav ul li a i {
            margin-right: 0;
            font-size: 1.1em;
            color: var(--text-on-dark);
            transition: margin-right var(--transition-speed) ease, color var(--transition-speed) ease;
        }

        .sidebar:hover .sidebar-nav ul li a i {
            margin-right: 12px;
        }

        .sidebar-nav ul li a span {
            opacity: 0;
            width: 0;
            overflow: hidden;
            white-space: nowrap;
            transition: opacity var(--transition-speed) ease, width var(--transition-speed) ease;
            display: inline-block;
            margin-left: -5px;
        }

        .sidebar:hover .sidebar-nav ul li a span {
            opacity: 1;
            width: auto;
            margin-left: 0;
        }

        .sidebar-nav ul li a:hover {
            background-color: var(--secondary-accent-green);
            color: var(--text-on-dark);
            border-left-color: var(--main-accent-green);
        }

        .sidebar-nav ul li a:hover i {
            color: var(--text-on-dark);
        }

        .sidebar-nav ul li a.active {
            background-color: var(--dark-prime);
            color: var(--main-accent-green);
            border-left-color: var(--main-accent-green);
        }

        .sidebar-nav ul li a.active i {
            color: var(--main-accent-green);
        }
        
        /* Sidebar footer (untuk desktop) */
        .sidebar-footer {
            padding: 20px 0;
            border-top: 1px solid var(--border-dark);
            text-align: center;
            transition: padding var(--transition-speed) ease;
        }

        .sidebar:hover .sidebar-footer {
            padding: 20px 25px;
        }

        .sidebar-logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 2px solid var(--border-light);
            background-color: transparent;
            color: var(--text-muted);
            font-size: 1.3em;
            transition: all var(--transition-speed) ease;
            margin: 0 auto;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .sidebar-logout-btn i {
            color: white;
        }

        .sidebar-logout-btn:hover {
            background-color: var(--main-accent-green);
            color: var(--text-on-dark);
            border-color: var(--main-accent-green);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        /* --- Main Content Styling --- */
        .main-content {
            flex-grow: 1;
            padding: 30px;
            background-color: var(--light-bg);
        }

        .content-header {
            background-color: var(--dark-prime);
            padding: 25px 30px;
            border-radius: var(--border-radius-main);
            box-shadow: 0 4px 20px var(--shadow-light);
            margin-bottom: 30px;
            text-align: left;
        }

        .content-header h1 {
            color: var(--text-on-dark);
            font-size: 2.2em;
            margin-bottom: 10px;
        }

        .welcome-message {
            font-size: 1.1em;
            color: var(--text-on-dark);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .card {
            background-color: var(--text-on-dark);
            border-radius: var(--border-radius-main);
            padding: 25px;
            text-align: left;
            box-shadow: 0 4px 15px var(--shadow-light);
            transition: transform var(--transition-speed) ease, box-shadow var(--transition-speed) ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            padding-top: 60px;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, transparent 50%, rgba(0, 0, 0, 0.03) 50%);
            opacity: 0;
            transition: opacity var(--transition-speed) ease;
            z-index: 0;
        }

        .card:hover::before {
            opacity: 1;
        }


        .card .card-icon {
            position: absolute;
            top: 20px;
            left: 25px;
            font-size: 2.5em;
            color: var(--main-accent-green);
            opacity: 0.8;
            transition: transform var(--transition-speed) ease, color var(--transition-speed) ease;
            z-index: 1;
        }

        .card:hover .card-icon {
            transform: translateY(-5px);
            color: var(--secondary-accent-green);
        }

        .card h3 {
            font-size: 1.2em;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-on-light);
            position: relative;
            z-index: 1;
        }

        .card p {
            font-size: 2.5em;
            font-weight: 700;
            color: var(--main-accent-green);
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }

        .card-link {
            display: inline-flex;
            align-items: center;
            margin-top: auto;
            color: var(--accent-blue);
            font-weight: 500;
            font-size: 0.95em;
            position: relative;
            z-index: 1;
        }

        .card-link i {
            margin-left: 8px;
            font-size: 0.9em;
            transition: transform var(--transition-speed) ease;
        }

        .card-link:hover i {
            transform: translateX(5px);
        }


        /* --- Aktivitas Terbaru (List Style) --- */
        .activity-section {
            background-color: var(--text-on-dark);
            padding: 30px;
            border-radius: var(--border-radius-main);
            box-shadow: 0 4px 20px var(--shadow-light);
            margin-top: 40px;
        }

        .activity-section h3 {
            color: var(--text-on-light);
            margin-bottom: 25px;
            font-size: 1.8em;
            text-align: left;
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 25px;
            background-color: var(--light-bg);
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--border-light);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
            transition: transform var(--transition-speed) ease, box-shadow var(--transition-speed) ease;
        }

        .activity-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .activity-item .activity-details {
            display: flex;
            align-items: flex-start;
            flex-grow: 1;
            margin-right: 20px;
        }

        .activity-item .activity-details i {
            font-size: 1.3em;
            color: var(--main-accent-green);
            margin-right: 15px;
            min-width: 30px;
            text-align: center;
        }

        .activity-item .activity-details .text-content {
            flex-grow: 1;
        }

        .activity-item .activity-details .text-content strong {
            display: block;
            font-size: 1.05em;
            color: var(--text-on-light);
            margin-bottom: 4px;
            font-weight: 600;
        }

        .activity-item .activity-details .text-content span {
            font-size: 0.9em;
            color: var(--text-muted);
            display: block;
            margin-bottom: 2px;
        }

        .activity-item .activity-details .text-content span:last-child {
            margin-bottom: 0;
        }


        .activity-item .activity-date {
            font-size: 0.85em;
            color: var(--text-muted);
            white-space: nowrap;
            text-align: right;
            min-width: 90px;
        }

        /* --- Pagination --- */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            gap: 8px;
        }

        .pagination a {
            background-color: var(--light-bg);
            color: var(--text-on-light);
            padding: 10px 18px;
            text-decoration: none;
            border: 1px solid var(--border-light);
            border-radius: var(--border-radius-sm);
            transition: background-color var(--transition-speed) ease, color var(--transition-speed) ease;
            font-weight: 500;
            font-size: 0.9em;
        }

        .pagination a.active {
            background-color: var(--main-accent-green);
            color: var(--text-on-dark);
            border-color: var(--main-accent-green);
        }

        .pagination a:hover:not(.active) {
            background-color: var(--secondary-accent-green);
            color: var(--text-on-dark);
            border-color: var(--secondary-accent-green);
        }

        /* --- Responsiveness (Media Queries) --- */
        @media (max-width: 992px) {
            .navbar {
                padding: 12px 20px;
            }

            .navbar .app-brand {
                font-size: 1.6em;
            }

            .navbar .user-info span {
                font-size: 0.9em;
                margin-right: 10px;
            }

            .main-content {
                padding: 20px;
            }

            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 20px;
            }

            .card .card-icon {
                font-size: 2.2em;
                top: 15px;
                left: 20px;
            }

            .card h3 {
                font-size: 1.1em;
            }

            .card p {
                font-size: 2.2em;
            }

            .activity-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                padding: 15px 20px;
            }

            .activity-item .activity-details {
                margin-right: 0;
                width: 100%;
            }

            .activity-item .activity-date {
                margin-top: 5px;
                align-self: flex-end;
            }
        }

        /* Perubahan untuk mode ponsel (lebar max 768px) */
        @media (max-width: 768px) {
            .navbar {
                padding: 10px 20px;
            }

            /* Tampilkan tombol toggle dan sembunyikan info user di navbar */
            .sidebar-toggle-btn {
                display: block;
            }
            .navbar .user-info span {
                display: none;
            }

            /* Atur ulang posisi tombol toggle ke kanan */
            .navbar .user-info {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                flex-grow: 1;
            }

            .wrapper {
                flex-direction: column;
            }

            /* Sidebar untuk mode mobile */
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                width: 0;
                height: 100vh;
                padding: 0;
                overflow-x: hidden;
                box-shadow: none;
                z-index: 1001;
                transform: translateX(-100%);
                transition: transform var(--transition-speed) ease, width var(--transition-speed) ease;
            }
            
            .sidebar.active {
                width: 250px;
                padding: 20px 0;
                transform: translateX(0);
                box-shadow: 2px 0 15px var(--shadow-light);
            }
            
            /* Nonaktifkan efek hover pada sidebar di mode mobile */
            .sidebar:hover {
                width: 0;
            }

            /* Sembunyikan header sidebar desktop di tampilan mobile */
            .sidebar-header {
                display: none;
            }

            /* Tampilkan info user di sidebar mobile */
            .mobile-user-info {
                display: flex;
                align-items: center;
                gap: 15px;
                padding: 10px 25px;
                margin-bottom: 20px;
                color: var(--text-on-dark);
                font-weight: 500;
                border-bottom: 1px solid var(--border-dark);
            }

            .mobile-user-info i {
                font-size: 2em;
                color: var(--main-accent-green);
            }
            .mobile-user-info span {
                font-size: 1.1em;
                font-weight: 600;
            }
            .mobile-user-info small {
                font-size: 0.9em;
                color: var(--text-muted);
            }
            
            /* Atur ulang gaya konten sidebar untuk mobile */
            .sidebar-nav ul li a {
                padding: 12px 25px;
                justify-content: flex-start;
            }
            .sidebar-nav ul li a i {
                margin-right: 12px;
            }
            .sidebar-nav ul li a span {
                opacity: 1;
                width: auto;
                margin-left: 0;
            }
            
            .sidebar-footer {
                padding: 20px 25px;
            }
            .sidebar-logout-btn {
                margin: 0;
            }

            .main-content {
                width: 100%;
                margin-left: 0;
                padding: 20px;
                min-height: calc(100vh - 70px);
            }
        }
    </style>
</head>

<body>
    <div class="navbar">
        <div class="app-brand">
            <i class="fas fa-leaf"></i> GoAgriculture
        </div>
        <div class="user-info">
            <!-- Teks user dan role yang hanya terlihat di desktop -->
            <span><i class="fas fa-user-shield"></i> <?= htmlspecialchars($username) ?> (Admin)</span>
            <!-- Tombol Toggle Sidebar untuk ponsel -->
            <div class="sidebar-toggle-btn">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </div>

    <div class="wrapper">
        <aside class="sidebar">
            <!-- Info user di sidebar (hanya muncul di mode ponsel) -->
            <div class="mobile-user-info">
                <i class="fas fa-user-circle"></i>
                <div class="text-content">
                    <span><?= htmlspecialchars($username) ?></span>
                    <small style="color: #FFFFFF;">Admin</small>
                </div>
            </div>
            <!-- Header sidebar yang asli, berfungsi di desktop -->
            <div class="sidebar-header">
                <h3><?= htmlspecialchars($username) ?></h3>
                <p>Administrator</p>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="admin.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="load_aturan.php"><i class="fas fa-book-open"></i> <span>Aturan dan Tanaman</span></a></li>
                    <li><a href="load_pengguna.php"><i class="fas fa-users"></i> <span>Pengguna</span></a></li>
                    <li><a href="load_profil.php"><i class="fas fa-user-circle"></i> <span>Profil Admin</span></a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="../auth/logout.php" class="sidebar-logout-btn"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </aside>

        <main class="main-content">
            <div class="content-header">
                <h1>Dashboard Administrator</h1>
                <p class="welcome-message">Selamat datang di panel administrasi GoAgriculture. Kelola data dan pengguna dengan mudah.</p>
            </div>

            <section class="dashboard-grid">
                <div class="card">
                    <i class="fas fa-seedling card-icon"></i>
                    <h3>Total Tanaman</h3>
                    <p><?= $total_tanaman ?></p>
                </div>
                <div class="card">
                    <i class="fas fa-book-open card-icon"></i>
                    <h3>Aturan Rekomendasi</h3>
                    <p><?= $total_aturan ?></p>
                </div>
                <div class="card">
                    <i class="fas fa-users card-icon"></i>
                    <h3>Pengguna Terdaftar</h3>
                    <p><?= $total_pengguna ?></p>
                </div>
                <div class="card">
                    <i class="fas fa-chart-line card-icon"></i>
                    <h3>Total Rekomendasi</h3>
                    <p><?= $total_rekomendasi ?></p>
                </div>
            </section>

            <section class="activity-section">
                <h3>Aktivitas Rekomendasi Terbaru</h3>
                <?php if (!empty($error_message)): ?>
                    <p style="color: var(--accent-red); text-align: center; padding: 15px; border: 1px solid var(--accent-red); border-radius: var(--border-radius-sm); background-color: rgba(211,47,47,0.1); margin-bottom: 20px;">
                        <?= $error_message ?>
                    </p>
                <?php endif; ?>

                <div class="activity-list">
                    <?php if (empty($recent_recommendations)): ?>
                        <div style="text-align: center; padding: 25px; color: var(--text-muted); background-color: var(--light-bg); border-radius: var(--border-radius-sm);">
                            Tidak ada aktivitas rekomendasi terbaru yang tersedia.
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_recommendations as $rec):
                            $hasil_rekomendasi_parsed = [];
                            $decoded_json = json_decode($rec['hasil_rekomendasi'], true);

                            if (is_array($decoded_json)) {
                                foreach ($decoded_json as $item) {
                                    if (isset($item['tanaman'])) {
                                        $hasil_rekomendasi_parsed[] = $item['tanaman'];
                                    }
                                }
                            }

                            // Ambil maksimal 3 tanaman saja untuk ditampilkan
                            $tanaman_sample = array_slice($hasil_rekomendasi_parsed, 0, 3);
                            $display_rekomendasi = implode(', ', $tanaman_sample);
                            if (count($hasil_rekomendasi_parsed) > 3) {
                                $display_rekomendasi .= ', ...';
                            }

                        ?>
                            <div class="activity-item">
                                <div class="activity-details">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <div class="text-content">
                                        <strong>Pengguna <?= htmlspecialchars($rec['username']) ?> melakukan rekomendasi</strong>
                                        <span>Lokasi: <?= htmlspecialchars($rec['lokasi'] ?? 'N/A') ?> (Lat: <?= htmlspecialchars($rec['latitude'] ?? 'N/A') ?>, Long: <?= htmlspecialchars($rec['longitude'] ?? 'N/A') ?>)</span>
                                        <span>Ketinggian: <?= htmlspecialchars($rec['ketinggian'] ?? 'N/A') ?>m, Curah Hujan: <?= htmlspecialchars($rec['curah_hujan'] ?? 'N/A') ?>mm</span>
                                        <span>Rekomendasi: <span style="color: var(--main-accent-green); font-weight: 500;"><?= htmlspecialchars($display_rekomendasi) ?></span></span>
                                    </div>
                                </div>
                                <div class="activity-date">
                                    <?= htmlspecialchars(date('d F Y H:i', strtotime($rec['created_at']))) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>


                <div class="pagination">
                    <?php if ($total_pages > 1): ?>
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?= $current_page - 1 ?>">&laquo; Previous</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i ?>" class="<?= ($i == $current_page) ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>">Next &raquo;</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggleBtn = document.querySelector('.sidebar-toggle-btn');
            
            // Fungsi untuk mengaktifkan/menonaktifkan sidebar di mode mobile
            const toggleSidebar = () => {
                // Hanya jalankan di layar kecil
                if (window.innerWidth <= 768) {
                    sidebar.classList.toggle('active');
                }
            };

            // Tambahkan event listener ke tombol toggle
            if (sidebarToggleBtn) {
                sidebarToggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation(); // Mencegah klik menyebar ke body
                    toggleSidebar();
                });
            }

            // Menutup sidebar ketika mengklik di luar area sidebar di mode mobile
            document.body.addEventListener('click', (e) => {
                if (window.innerWidth <= 768 && sidebar.classList.contains('active') && !sidebar.contains(e.target) && !sidebarToggleBtn.contains(e.target)) {
                    toggleSidebar();
                }
            });

            // Handle resize event
            window.addEventListener('resize', () => {
                // Jika kembali ke ukuran desktop, pastikan kelas active dihapus
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('active');
                }
            });
        });
    </script>
</body>

</html>
