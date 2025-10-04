<?php
session_start();
require_once __DIR__ . '/../lib/db.php';
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit();
}
$username = $_SESSION['user']['username'] ?? 'user';
$user_role = $_SESSION['user']['role'] ?? 'user';

try {
    $stmt = $conn->query("
        SELECT 
            tanaman.id_tanaman, 
            tanaman.nama_tanaman, 
            tanaman.kategori, 
            aturan.min_ketinggian, 
            aturan.max_ketinggian, 
            aturan.min_curah_hujan, 
            aturan.max_curah_hujan
        FROM 
            tanaman
        LEFT JOIN 
            aturan ON tanaman.id_tanaman = aturan.id_tanaman
        ORDER BY 
            tanaman.nama_tanaman ASC
    ");
    $tanamanList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Gagal mengambil data tanaman: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Tanaman - GoAgriculture</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="/manifest.json">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --dark-prime: #2A5234;
            --light-bg: #F5F5F5;
            --main-accent-green: #70C174;
            --main-accent-green-rgb: 112, 193, 116;
            --secondary-accent-green: #4CAF50;
            --text-on-dark: #FFFFFF;
            --text-on-light: #333333;
            --text-muted: #666666;
            --accent-blue: #2196F3;
            --accent-yellow: #FFC107;
            --shadow-light: rgba(0, 0, 0, 0.1);
            --border-radius-main: 8px;
            --border-radius-sm: 4px;
            --sidebar-width-open: 280px;
            --sidebar-width-closed: 80px;
            --navbar-height: 70px;
        }
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
            min-height: 100vh;
        }
        .navbar {
            background-color: var(--dark-prime);
            color: var(--text-on-dark);
            height: var(--navbar-height);
            padding: 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
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
            color: var(--text-on-dark);
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .navbar .logo-area i {
            font-size: 1.2em;
            color: var(--main-accent-green);
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
        }
        .navbar .user-info-navbar .user-name {
            font-family: 'Montserrat', sans-serif;
            font-size: 1em;
            font-weight: 500;
        }
        .navbar .user-info-navbar .user-role {
            font-size: 0.8em;
            color: #b0c2b6;
        }
        .main-layout {
            display: flex;
            flex-grow: 1;
            width: 100%;
            height: calc(100vh - var(--navbar-height));
        }
        .sidebar {
            width: var(--sidebar-width-closed);
            background-color: var(--dark-prime);
            color: var(--text-on-dark);
            padding: 20px 0;
            box-shadow: 3px 0 8px var(--shadow-light);
            display: flex;
            flex-direction: column;
            align-items: center;
            position: sticky;
            top: var(--navbar-height);
            height: 100%;
            overflow-y: auto;
            transition: width 0.3s ease;
            flex-shrink: 0;
        }
        .sidebar:hover {
            width: var(--sidebar-width-open);
        }
        .sidebar .user-info {
            display: none;
        }
        .sidebar nav ul li a span {
            white-space: nowrap;
            overflow: hidden;
            text-indent: -9999px;
            transition: text-indent 0.3s ease;
            display: inline-block;
            max-width: 0;
        }
        .sidebar:hover nav ul li a span {
            text-indent: 0;
            max-width: 200px;
            transition: text-indent 0.3s ease, max-width 0.3s ease;
        }
        .sidebar nav {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            width: 100%;
        }
        .sidebar nav ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 0;
            padding-bottom: 0;
        }
        .sidebar nav ul li {
            margin-bottom: 10px;
            width: 100%;
        }
        .sidebar nav ul li a {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            color: var(--text-on-dark);
            text-decoration: none;
            padding: 12px 0px;
            border-radius: var(--border-radius-sm);
            transition: background-color 0.3s ease, color 0.3s ease, justify-content 0.3s ease, padding 0.3s ease;
            font-weight: 500;
            font-family: 'Roboto', sans-serif;
            width: 100%;
            background-color: transparent;
        }
        .sidebar nav ul li a.active {
            background-color: var(--main-accent-green);
            color: var(--dark-prime);
            font-weight: 500;
            box-shadow: none;
        }
        .sidebar nav ul li a.active:hover {
            background-color: rgba(var(--main-accent-green-rgb), 0.9);
            color: var(--dark-prime);
        }
        .sidebar nav ul li a:not(.active):hover {
            color: var(--text-on-dark);
        }
        .sidebar:hover nav ul li a {
            justify-content: flex-start;
            padding: 12px 20px;
            background-color: transparent;
        }
        .sidebar:hover nav ul li a:not(.active):hover {
            background-color: var(--main-accent-green);
            color: var(--dark-prime);
        }
        .sidebar:hover nav ul li a.active {
            background-color: var(--main-accent-green);
            color: var(--dark-prime);
        }
        .sidebar nav ul li a i {
            font-size: 1.1em;
            width: 20px;
            text-align: center;
            flex-shrink: 0;
            color: inherit;
        }
        .sidebar nav ul li a.active i {
            color: var(--dark-prime);
        }
        .sidebar nav ul li a:not(.active):hover i {
            color: var(--text-on-dark);
        }

        .sidebar:hover nav ul li a:not(.active):hover i {
            color: var(--dark-prime);
        }
        .sidebar .logout-button-container {
            width: 100%;
            margin-top: auto;
            padding: 0 15px 15px;
            display: flex;
            justify-content: center;
        }

        .sidebar .logout-button-container a {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: transparent;
            color: var(--text-on-dark);
            text-decoration: none;
            border: 2px solid var(--text-on-dark);
            border-radius: 50%;
            transition: all 0.3s ease;
            font-weight: 500;
            font-family: 'Roboto', sans-serif;
            width: 50px;
            height: 50px;
            flex-shrink: 0;
            overflow: hidden;
            position: relative;
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
            display: none;
        }
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

        /* Container for table and filters */
        .container {
            max-width: 100%;
            flex-grow: 1;
            /* Allow container to grow and take available space */
            /* margin: 25px; removed as padding is on main-content */
            background-color: var(--text-on-dark);
            /* White background */
            padding: 25px;
            border-radius: var(--border-radius-main);
            box-shadow: 0 4px 10px var(--shadow-light);
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            /* Prevent horizontal scrolling within container */
        }

        h2 {
            font-family: 'Montserrat', sans-serif;
            color: var(--dark-prime);
            text-align: center;
            margin-bottom: 25px;
            font-size: 2em;
            font-weight: 600;
        }

        .filter-section {
            display: flex;
            justify-content: flex-end;
            /* Align to the right */
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
        }

        .filter-section label {
            font-weight: 600;
            color: var(--text-muted);
        }

        .filter-section select {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: var(--border-radius-sm);
            background-color: #fefefe;
            font-size: 0.95em;
            cursor: pointer;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            color: var(--text-on-light);
        }

        .filter-section select:focus {
            border-color: var(--main-accent-green);
            box-shadow: 0 0 0 2px rgba(var(--main-accent-green-rgb), 0.2);
            outline: none;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
            box-shadow: 0 2px 5px var(--shadow-light);
            border-radius: var(--border-radius-main);
            overflow: hidden;
            /* Helps with border-radius on table */
        }

        th,
        td {
            padding: 12px 18px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            color: var(--text-on-light);
        }

        th {
            background-color: var(--dark-prime);
            /* Green header for consistency */
            color: var(--text-on-dark);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
            cursor: pointer;
            position: relative;
        }

        th.sortable:after {
            content: '\f0dc';
            /* FontAwesome sort icon */
            font-family: 'Font Awesome 6 Free';
            /* Use correct font family for FA6 */
            font-weight: 900;
            /* Solid icon weight */
            margin-left: 8px;
            opacity: 0.4;
            transition: opacity 0.2s ease;
        }

        th.sortable.asc:after {
            content: '\f0de';
            /* Arrow up */
            opacity: 1;
        }

        th.sortable.desc:after {
            content: '\f0dd';
            /* Arrow down */
            opacity: 1;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:hover {
            background-color: #f1f1f1;
        }

        .no-result {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border: 1px solid #ffeeba;
            border-radius: var(--border-radius-sm);
            text-align: center;
            font-family: 'Roboto', sans-serif;
            font-weight: 400;
            margin-top: 20px;
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

            .filter-section {
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-section select {
                width: 100%;
                margin-top: 5px;
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

            /* Table responsiveness */
            table,
            thead,
            tbody,
            th,
            td,
            tr {
                display: block;
            }

            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }

            tr {
                border: 1px solid #ccc;
                margin-bottom: 10px;
                border-radius: var(--border-radius-main);
            }
            td {
                border: none;
                border-bottom: 1px solid #eee;
                position: relative;
                padding-left: 50%;
                text-align: right;
            }
            td:last-child {
                border-bottom: 0;
            }
            td:before {
                position: absolute;
                top: 12px;
                left: 6px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                text-align: left;
                font-weight: bold;
                content: attr(data-label);
                color: var(--dark-prime);
            }
        }
        @media screen and (max-width: 480px) {
            .navbar .logo-area {
                font-size: 1.2em;
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
            h2 {
                font-size: 1.6em;
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
                    <li><a href="tanaman.php" class="active"><i class="fas fa-seedling"></i> <span>Daftar Tanaman</span></a></li>
                    <li><a href="riwayat.php"><i class="fas fa-history"></i> <span>Riwayat</span></a></li>
                    <li><a href="profil.php"><i class="fas fa-user-circle"></i> <span>Profil</span></a></li>
                    <?php if ($user_role === 'admin'): ?>
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
                <h1>Daftar Tanaman</h1>
                <p class="description">Lihat detail tanaman hortikultura yang terdaftar dalam sistem, beserta kondisi ideal untuk pertumbuhannya.</p>
            </div>
            <div class="container">
                <h2>Daftar Tanaman Hortikultura</h2>
                <?php if (empty($tanamanList)) : ?>
                    <div class="no-result">
                        <p><i class="fas fa-exclamation-triangle"></i> Tidak ada data tanaman tersedia.</p>
                    </div>
                <?php else : ?>
                    <div class="filter-section">
                        <label for="sortOrder">Urutkan berdasarkan:</label>
                        <select id="sortOrder" onchange="sortTable()">
                            <option value="nama_asc">Nama Tanaman (A-Z)</option>
                            <option value="nama_desc">Nama Tanaman (Z-A)</option>
                            <option value="kategori_asc">Kategori (A-Z)</option>
                            <option value="kategori_desc">Kategori (Z-A)</option>
                            <option value="elevasi_min_asc">Elevasi Min (Terendah ke Tertinggi)</option>
                            <option value="elevasi_min_desc">Elevasi Min (Tertinggi ke Terendah)</option>
                            <option value="curah_hujan_min_asc">Curah Hujan Min (Terendah ke Tertinggi)</option>
                            <option value="curah_hujan_min_desc">Curah Hujan Min (Tertinggi ke Terendah)</option>
                        </select>
                    </div>
                    <table id="tanamanTable">
                        <thead>
                            <tr>
                                <th class="sortable" data-column="0">No</th>
                                <th class="sortable" data-column="1">Nama Tanaman</th>
                                <th class="sortable" data-column="2">Kategori</th>
                                <th class="sortable" data-column="3">Elevasi (min - max)</th>
                                <th class="sortable" data-column="4">Curah Hujan (min - max)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tanamanList as $i => $tanaman): ?>
                                <tr>
                                    <td data-label="No"><?= $i + 1 ?></td>
                                    <td data-label="Nama Tanaman"><?= htmlspecialchars($tanaman['nama_tanaman']) ?></td>
                                    <td data-label="Kategori"><?= htmlspecialchars($tanaman['kategori']) ?></td>
                                    <td data-label="Elevasi (min - max)">
                                        <?= (isset($tanaman['min_ketinggian']) && $tanaman['min_ketinggian'] !== null) ? htmlspecialchars($tanaman['min_ketinggian']) . ' - ' . htmlspecialchars($tanaman['max_ketinggian']) . ' m' : 'N/A' ?>
                                    </td>
                                    <td data-label="Curah Hujan (min - max)">
                                        <?= (isset($tanaman['min_curah_hujan']) && $tanaman['min_curah_hujan'] !== null) ? htmlspecialchars($tanaman['min_curah_hujan']) . ' - ' . htmlspecialchars($tanaman['max_curah_hujan']) . ' mm' : 'N/A' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>


    <script>
        // Script untuk menandai menu aktif
        // --- AWAL PERBAIKAN: Mempertahankan lat/lng di link navigasi ---
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

        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname.split('/').pop();
            document.querySelectorAll('.sidebar nav ul li a').forEach(link => {
                const linkPath = link.getAttribute('href').split('/').pop();

                // Spesial handling untuk link Logout agar tidak ditandai sebagai 'active'
                if (link.closest('.logout-button-container')) {
                    return; // Skip logout button
                }

                if (linkPath === currentPath) {
                    link.classList.add('active');
                }
            });

            // Initial sort when page loads (by default, Nama Tanaman A-Z)
            sortTable();
        });

        function sortTable() {
            const table = document.getElementById("tanamanTable");
            if (!table) return; // Exit if table not found

            const tbody = table.querySelector("tbody");
            if (!tbody) return; // Exit if tbody not found

            const rows = Array.from(tbody.rows);
            const sortOrder = document.getElementById("sortOrder").value;

            let columnIndex;
            let isAscending;
            let isNumeric = false;

            switch (sortOrder) {
                case 'nama_asc':
                    columnIndex = 1; // Nama Tanaman
                    isAscending = true;
                    break;
                case 'nama_desc':
                    columnIndex = 1;
                    isAscending = false;
                    break;
                case 'kategori_asc':
                    columnIndex = 2; // Kategori
                    isAscending = true;
                    break;
                case 'kategori_desc':
                    columnIndex = 2;
                    isAscending = false;
                    break;
                case 'elevasi_min_asc':
                    columnIndex = 3; // Elevasi (min - max)
                    isAscending = true;
                    isNumeric = true;
                    break;
                case 'elevasi_min_desc':
                    columnIndex = 3;
                    isAscending = false;
                    isNumeric = true;
                    break;
                case 'curah_hujan_min_asc':
                    columnIndex = 4; // Curah Hujan (min - max)
                    isAscending = true;
                    isNumeric = true;
                    break;
                case 'curah_hujan_min_desc':
                    columnIndex = 4;
                    isAscending = false;
                    isNumeric = true;
                    break;
                default:
                    columnIndex = 1; // Default to Nama Tanaman
                    isAscending = true;
            }

            rows.sort((rowA, rowB) => {
                let cellA = rowA.cells[columnIndex].textContent.trim();
                let cellB = rowB.cells[columnIndex].textContent.trim();
                if (isNumeric) {
                    const parseNumericValue = (text) => {
                        const match = text.match(/^(-?\d+(\.\d+)?)/); // Matches optional negative sign and numbers
                        return match ? parseFloat(match[1]) : (text === 'N/A' ? (isAscending ? Infinity : -Infinity) : parseFloat(text) || 0);
                    };
                    cellA = parseNumericValue(cellA);
                    cellB = parseNumericValue(cellB);
                }

                let comparison = 0;
                if (isNumeric) {
                    comparison = cellA - cellB;
                } else {
                    comparison = cellA.localeCompare(cellB);
                }

                return isAscending ? comparison : -comparison;
            });
            rows.forEach(row => tbody.appendChild(row));
            updateRowNumbers(tbody);
        }
        function updateRowNumbers(tbody) {
            const rows = Array.from(tbody.rows);
            rows.forEach((row, index) => {
                row.cells[0].textContent = index + 1;
            });
        }
    </script>
</body>
</html>