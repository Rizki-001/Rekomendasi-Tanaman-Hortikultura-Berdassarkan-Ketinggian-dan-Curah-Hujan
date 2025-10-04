<?php
// === admin/load_tanaman.php ===
session_start();
require_once '../lib/db.php'; // Pastikan path ini benar

// Cek apakah admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin = $_SESSION['user'];

// --- Tambah tanaman ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_tanaman') {
    $nama_tanaman = trim($_POST['nama_tanaman']);
    $kategori = trim($_POST['kategori']);

    if (empty($nama_tanaman) || empty($kategori)) {
        header("Location: load_tanaman.php?error=empty_fields");
        exit();
    }

    try {
        $stmt = $conn->prepare("INSERT INTO tanaman (nama_tanaman, kategori) VALUES (?, ?)");
        $stmt->execute([$nama_tanaman, $kategori]);
        header("Location: load_tanaman.php?success=add");
        exit();
    } catch (PDOException $e) {
        error_log("Error adding tanaman: " . $e->getMessage());
        header("Location: load_tanaman.php?error=add_failed");
        exit();
    }
}

// --- Hapus tanaman ---
if (isset($_GET['delete'])) {
    $id_tanaman_to_delete = $_GET['delete'];
    try {
        $stmtAturan = $conn->prepare("DELETE FROM aturan WHERE id_tanaman = ?");
        $stmtAturan->execute([$id_tanaman_to_delete]);


        $stmt = $conn->prepare("DELETE FROM tanaman WHERE id_tanaman = ?");
        $stmt->execute([$id_tanaman_to_delete]);
        header("Location: load_tanaman.php?success=delete");
        exit();
    } catch (PDOException $e) {
        error_log("Error deleting tanaman: " . $e->getMessage());
        header("Location: load_tanaman.php?error=delete_failed");
        exit();
    }
}

// --- Edit tanaman ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_tanaman') {
    $id_tanaman = trim($_POST['edit_id_tanaman']);
    $nama_tanaman = trim($_POST['edit_nama_tanaman']);
    $kategori = trim($_POST['edit_kategori']);

    if (empty($id_tanaman) || empty($nama_tanaman) || empty($kategori)) {
        header("Location: load_tanaman.php?error=empty_edit_fields");
        exit();
    }

    try {
        $stmt = $conn->prepare("UPDATE tanaman SET nama_tanaman = ?, kategori = ? WHERE id_tanaman = ?");
        $stmt->execute([$nama_tanaman, $kategori, $id_tanaman]);
        header("Location: load_tanaman.php?success=edit");
        exit();
    } catch (PDOException $e) {
        error_log("Error updating tanaman: " . $e->getMessage());
        header("Location: load_tanaman.php?error=edit_failed");
        exit();
    }
}


// --- Ambil semua tanaman dengan filter ---
$searchNamaTanaman = isset($_GET['search_nama_tanaman']) ? trim($_GET['search_nama_tanaman']) : '';
$filterKategori = isset($_GET['filter_kategori']) ? $_GET['filter_kategori'] : '';

$sql = "SELECT * FROM tanaman WHERE 1=1";
$params = [];

if (!empty($searchNamaTanaman)) {
    $sql .= " AND nama_tanaman LIKE ?";
    $params[] = '%' . $searchNamaTanaman . '%';
}

if (!empty($filterKategori) && $filterKategori !== 'all') {
    $sql .= " AND kategori = ?";
    $params[] = $filterKategori;
}

$sql .= " ORDER BY id_tanaman DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$tanaman = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua kategori unik dari database untuk filter dropdown
$stmtKategori = $conn->query("SELECT DISTINCT kategori FROM tanaman ORDER BY kategori ASC");
$allKategori = $stmtKategori->fetchAll(PDO::FETCH_COLUMN);

// Jika ada kategori standar yang ingin selalu ditampilkan meski belum ada di DB
$mergedCategories = array_unique(array_merge($allKategori));
sort($mergedCategories);


// Handle messages for user feedback
$message = '';
$messageType = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'add') {
        $message = 'Tanaman berhasil ditambahkan.';
        $messageType = 'success';
    } elseif ($_GET['success'] === 'delete') {
        $message = 'Tanaman berhasil dihapus.';
        $messageType = 'success';
    } elseif ($_GET['success'] === 'edit') {
        $message = 'Tanaman berhasil diperbarui.';
        $messageType = 'success';
    }
} elseif (isset($_GET['error'])) {
    if ($_GET['error'] === 'empty_fields') {
        $message = 'Nama Tanaman dan Kategori tidak boleh kosong.';
        $messageType = 'error';
    } elseif ($_GET['error'] === 'add_failed') {
        $message = 'Gagal menambahkan tanaman. Mungkin data sudah ada.';
        $messageType = 'error';
    } elseif ($_GET['error'] === 'delete_failed') {
        $message = 'Gagal menghapus tanaman. Pastikan tidak ada data terkait (misal: aturan) atau coba lagi.';
        $messageType = 'error';
    } elseif ($_GET['error'] === 'empty_edit_fields') {
        $message = 'Nama Tanaman dan Kategori tidak boleh kosong saat mengedit.';
        $messageType = 'error';
    } elseif ($_GET['error'] === 'edit_failed') {
        $message = 'Gagal memperbarui tanaman. Mungkin nama tanaman sudah digunakan.';
        $messageType = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kelola Tanaman - GoAgriculture</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        :root {
            --dark-prime: #3D724D;
            /* Main dark green for navbar, header */
            --light-bg: #F5F5F5;
            /* Very light grey/off-white for body background, cards */
            --sidebar-bg: #315F40;
            /* Slightly deeper green for sidebar */

            /* Warna Aksen */
            --main-accent-green: #66BB6A;
            /* Primary accent green (e.g., active links, card icons, numbers) */
            --secondary-accent-green: #4CAF50;
            /* A slightly darker shade of accent green for hover/contrast */
            --accent-blue: #2196F3;
            /* Keeping blue for general links */
            --accent-red: #D32F2F;
            /* Deep red for danger */

            /* Warna Teks */
            --text-on-dark: #FFFFFF;
            /* White text on dark backgrounds */
            --text-on-light: #333333;
            /* Dark grey text on light backgrounds */
            --text-muted: #666666;
            /* Muted grey for secondary text on light backgrounds */

            /* Shadow & Border */
            --shadow-light: rgba(0, 0, 0, 0.1);
            --border-light: rgba(0, 0, 0, 0.1);
            --border-dark: rgba(255, 255, 255, 0.1);

            /* Lain-lain */
            --transition-speed: 0.3s;
            --border-radius-main: 8px;
            --border-radius-sm: 4px;
        }

        /* --- Reset & Base Styles --- */
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

        a {
            text-decoration: none;
            color: var(--accent-blue);
            transition: color var(--transition-speed) ease;
        }

        a:hover {
            color: #64B5F6;
            /* Lighter shade of blue on hover */
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

        /* --- Wrapper (Flex Container Utama untuk Sidebar + Konten) --- */
        .wrapper {
            display: flex;
            padding-top: 70px;
            /* Sesuaikan dengan tinggi navbar */
            min-height: 100vh;
        }

        /* --- Sidebar Styling --- */
        .sidebar {
            width: 80px;
            /* Lebar default saat tersembunyi */
            background-color: var(--sidebar-bg);
            color: var(--text-on-dark);
            padding: 20px 0;
            box-shadow: 2px 0 15px var(--shadow-light);
            position: sticky;
            top: 70px;
            /* Jaga agar sidebar di bawah navbar */
            height: calc(100vh - 70px);
            /* Penuh tinggi viewport dikurangi navbar */
            overflow: hidden;
            /* Sembunyikan teks yang melampaui batas */
            z-index: 999;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            /* Untuk meletakkan logout di bawah */
            transition: width var(--transition-speed) ease;
            /* Transisi untuk perubahan lebar */
        }

        .sidebar:hover {
            width: 250px;
            /* Lebar saat di-hover */
        }

        /* --- Sidebar Header --- */
        .sidebar-header {
            text-align: center;
            margin-bottom: 0;
            /* Default: tanpa margin bawah */
            padding: 20px 0;
            /* Default: padding lebih sedikit */
            border-bottom: none;
            /* Default: tanpa border bawah */
            display: flex;
            flex-direction: column;
            align-items: center;
            /* Pusatkan konten saat tersembunyi */
            transition: all var(--transition-speed) ease;
            /* Transisi untuk semua properti */
        }

        .sidebar:hover .sidebar-header {
            margin-bottom: 30px;
            /* Kembalikan margin saat hover */
            padding: 0 20px 20px;
            /* Kembalikan padding saat hover */
            border-bottom: 1px solid var(--border-dark);
            /* Kembalikan border saat hover */
        }

        .sidebar-header .logo img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 0;
            /* Default: tanpa margin bawah logo */
            border: 3px solid var(--main-accent-green);
            transition: margin-bottom var(--transition-speed) ease;
        }

        .sidebar:hover .sidebar-header .logo img {
            margin-bottom: 10px;
            /* Kembalikan margin saat hover */
        }

        .sidebar-header h3,
        .sidebar-header p {
            opacity: 0;
            /* Sembunyikan teks secara default */
            height: 0;
            /* Collapse tinggi untuk mencegah mempengaruhi layout */
            overflow: hidden;
            /* Pastikan teks benar-benar tersembunyi */
            transition: opacity var(--transition-speed) ease, height var(--transition-speed) ease, margin var(--transition-speed) ease;
            white-space: nowrap;
            /* Mencegah teks terpotong */
            pointer-events: none;
            /* Mencegah teks diklik saat tersembunyi */
        }

        .sidebar:hover .sidebar-header h3,
        .sidebar:hover .sidebar-header p {
            opacity: 1;
            /* Tampilkan teks saat hover */
            height: auto;
            /* Kembalikan tinggi saat hover */
            pointer-events: auto;
            /* Jadikan bisa diklik kembali */
        }

        .sidebar:hover .sidebar-header h3 {
            margin-top: 10px;
        }

        .sidebar:hover .sidebar-header p {
            margin-top: 0;
        }

        /* --- Sidebar Navigasi --- */
        .sidebar-nav ul {
            flex-grow: 1;
            /* Biarkan daftar navigasi mengisi ruang yang tersedia */
        }

        .sidebar-nav ul li {
            margin-bottom: 5px;
        }

        .sidebar-nav ul li a {
            display: flex;
            align-items: center;
            padding: 12px 0;
            /* Padding horizontal lebih sedikit saat tersembunyi */
            justify-content: center;
            /* Pusatkan ikon */
            color: var(--text-on-dark);
            border-left: 4px solid transparent;
            transition: all var(--transition-speed) ease;
            font-weight: 500;
        }

        .sidebar:hover .sidebar-nav ul li a {
            padding: 12px 25px;
            /* Kembalikan padding penuh saat hover */
            justify-content: flex-start;
            /* Sejajarkan ke kiri saat hover */
        }

        .sidebar-nav ul li a i {
            margin-right: 0;
            /* Tanpa margin kanan default */
            font-size: 1.1em;
            color: var(--text-on-dark);
            transition: margin-right var(--transition-speed) ease, color var(--transition-speed) ease;
        }

        .sidebar:hover .sidebar-nav ul li a i {
            margin-right: 12px;
            /* Kembalikan margin kanan saat hover */
        }

        /* Sembunyikan teks di dalam <span> secara default */
        .sidebar-nav ul li a span {
            opacity: 0;
            width: 0;
            /* Sembunyikan lebar teks */
            overflow: hidden;
            white-space: nowrap;
            transition: opacity var(--transition-speed) ease, width var(--transition-speed) ease;
            display: inline-block;
            /* Penting untuk transisi lebar */
            margin-left: -5px;
            /* Sesuaikan untuk menarik teks lebih dekat ke ikon saat tersembunyi */
        }

        /* Tampilkan teks saat sidebar di-hover */
        .sidebar:hover .sidebar-nav ul li a span {
            opacity: 1;
            width: auto;
            /* Kembalikan lebar natural teks */
            margin-left: 0;
            /* Reset margin */
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

        /* --- Sidebar Footer (untuk Tombol Logout) --- */
        .sidebar-footer {
            padding: 20px 0;
            /* Padding horizontal lebih sedikit saat tersembunyi */
            border-top: 1px solid var(--border-dark);
            text-align: center;
            transition: padding var(--transition-speed) ease;
        }

        .sidebar:hover .sidebar-footer {
            padding: 20px 25px;
            /* Kembalikan padding penuh saat hover */
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
            color: inherit;
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
            color: var(--text-on-dark);
        }

        .content-header h1 {
            color: var(--text-on-dark);
            font-size: 2.2em;
            margin-bottom: 10px;
        }

        label {
            font-weight: bold;
            display: block;
            margin-bottom: 6px;
            color: var(--text-on-light);
        }

        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--border-light);
            background-color: var(--light-bg);
            color: var(--text-on-light);
            font-family: 'Roboto', sans-serif;
            font-size: 1em;
        }

        .btn {
            background-color: var(--main-accent-green);
            color: var(--text-on-dark);
            padding: 10px 15px;
            border: none;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            width: 100%;
            font-size: 1em;
            transition: background-color var(--transition-speed) ease, transform 0.2s ease;
        }

        .btn:hover {
            background-color: var(--secondary-accent-green);
            transform: translateY(-1px);
        }

        /* --- Message Styling --- */
        .message {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            border-radius: var(--border-radius-sm);
            font-weight: 500;
        }

        .success {
            background-color: rgba(102, 187, 106, 0.1);
            color: var(--main-accent-green);
            border: 1px solid var(--main-accent-green);
        }

        .error {
            background-color: rgba(211, 47, 47, 0.1);
            color: var(--accent-red);
            border: 1px solid var(--accent-red);
        }


        /* --- Table Styling --- */
        .table-container {
            background-color: var(--text-on-dark);
            padding: 30px;
            border-radius: var(--border-radius-main);
            box-shadow: 0 4px 10px var(--shadow-light);
            overflow-x: auto;
        }

        .table-container h2 {
            text-align: center;
            margin-bottom: 25px;
            color: var(--text-on-light);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th,
        table td {
            padding: 12px 15px;
            text-align: center;
            border-bottom: 1px solid var(--border-light);
        }

        table th {
            background-color: var(--dark-prime);
            color: var(--text-on-dark);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        table tr:hover {
            background-color: #eef2f1;
        }

        table td a {
            display: inline-block;
            padding: 6px 10px;
            border-radius: var(--border-radius-sm);
            font-size: 0.9em;
            text-decoration: none;
            transition: background-color var(--transition-speed) ease, color var(--transition-speed) ease;
            margin-right: 5px;
        }

        table td a.edit-btn {
            background-color: var(--accent-blue);
            color: var(--text-on-dark);
            border: 1px solid var(--accent-blue);
        }

        table td a.edit-btn:hover {
            background-color: #1976D2;
            border-color: #1976D2;
        }

        table td a.delete-btn {
            background-color: var(--accent-red);
            color: var(--text-on-dark);
            border: 1px solid var(--accent-red);
        }

        table td a.delete-btn:hover {
            background-color: #c0392b;
            border-color: #c0392b;
        }

        /* --- Filter & Action Bar (New) --- */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            padding: 20px;
            background-color: var(--text-on-dark);
            border-radius: var(--border-radius-main);
            box-shadow: 0 2px 8px var(--shadow-light);
        }

        .action-bar .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-grow: 1; /* Allow filter group to take available space */
        }

        .action-bar .filter-group label {
            margin-bottom: 0;
            white-space: nowrap; /* Prevent label from wrapping */
            font-size: 0.95em;
        }

        .action-bar .filter-group input[type="text"],
        .action-bar .filter-group select {
            margin-bottom: 0;
            padding: 8px 12px;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--border-light);
            flex-grow: 1; /* Allow inputs to grow */
            max-width: 200px; /* Limit max width for inputs */
        }

        .action-bar .btn-filter,
        .action-bar .btn-reset,
        .action-bar .btn-add {
            padding: 8px 18px;
            font-size: 0.95em;
            width: auto; /* Override btn default 100% width */
            border-radius: var(--border-radius-sm);
        }

        .action-bar .btn-reset {
            background-color: var(--text-muted);
        }
        .action-bar .btn-reset:hover {
            background-color: #555;
        }
        .action-bar .btn-add {
            background-color: var(--main-accent-green);
        }
        .action-bar .btn-add:hover {
            background-color: var(--secondary-accent-green);
        }


        /* --- Modal Styles --- */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            justify-content: center;
            align-items: center;
            padding: 20px; /* Add padding for small screens */
        }

        .modal-content {
            background-color: var(--text-on-dark);
            margin: auto; /* Centers the modal vertically and horizontally */
            padding: 30px;
            border-radius: var(--border-radius-main);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 500px;
            animation-name: animatetop;
            animation-duration: 0.4s;
            position: relative;
        }

        @keyframes animatetop {
            from {top: -300px; opacity: 0}
            to {top: 0; opacity: 1}
        }

        .close-button {
            color: var(--text-muted);
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 20px;
            cursor: pointer;
        }

        .close-button:hover,
        .close-button:focus {
            color: var(--accent-red);
            text-decoration: none;
            cursor: pointer;
        }

        .modal-content h2 {
            text-align: center;
            margin-top: 0;
            margin-bottom: 25px;
            color: var(--text-on-light);
        }

        /* --- Responsiveness (Media Queries) --- */
        @media (max-width: 992px) {
            .action-bar {
                flex-direction: column;
                align-items: stretch;
                padding: 15px;
            }
            .action-bar .filter-group {
                flex-direction: column; /* Stack filter inputs */
                align-items: flex-start;
                width: 100%;
            }
            .action-bar .filter-group input[type="text"],
            .action-bar .filter-group select {
                max-width: 100%; /* Full width for inputs in stacked layout */
            }
            .action-bar .btn-filter,
            .action-bar .btn-reset,
            .action-bar .btn-add {
                width: 100%; /* Full width for action buttons */
                margin-top: 10px; /* Space between stacked buttons */
            }
            .action-bar .filter-group label {
                margin-bottom: 5px; /* Add some space below labels */
            }
        }
        @media (max-width: 576px) {
            .modal-content {
                padding: 20px;
            }
            .close-button {
                font-size: 24px;
                top: 5px;
                right: 15px;
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
            <span><i class="fas fa-user-shield"></i> <?= htmlspecialchars($admin['username']) ?> (Admin)</span>
        </div>
    </div>

    <div class="wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><?= htmlspecialchars($admin['username']) ?></h3>
                <p>Administrator Panel</p>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="admin.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="load_tanaman.php" class="active"><i class="fas fa-seedling"></i> <span>Kelola Tanaman</span></a></li>
                    <li><a href="load_aturan.php"><i class="fas fa-book-open"></i> <span>Kelola Aturan</span></a></li>
                    <li><a href="load_pengguna.php"><i class="fas fa-users"></i> <span>Kelola Pengguna</span></a></li>
                    <li><a href="load_profil.php"><i class="fas fa-user-circle"></i> <span>Profil Admin</span></a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="../auth/logout.php" class="sidebar-logout-btn"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </aside>

        <main class="main-content">
            <div class="content-header">
                <h1>Manajemen Tanaman</h1>
            </div>

            <?php if ($message): ?>
                <div class="message <?= $messageType ?>"><?= $message ?></div>
            <?php endif; ?>

            <div class="action-bar">
                <form method="GET" action="load_tanaman.php" class="filter-group">
                    <label for="search_nama_tanaman">Cari:</label>
                    <input type="text" name="search_nama_tanaman" id="search_nama_tanaman" placeholder="Nama Tanaman" value="<?= htmlspecialchars($searchNamaTanaman) ?>">

                    <label for="filter_kategori">Kategori:</label>
                    <select name="filter_kategori" id="filter_kategori">
                        <option value="all" <?= ($filterKategori === 'all' || empty($filterKategori)) ? 'selected' : '' ?>>Semua</option>
                        <?php foreach ($mergedCategories as $kat): ?>
                            <option value="<?= htmlspecialchars($kat) ?>" <?= ($filterKategori === $kat) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-filter"><i class="fas fa-search"></i> Cari</button>
                    <button class="btn btn-filter"><a href="load_tanaman.php" style="color: white;"><i class="fas fa-redo"></i> Reset</a></button>
                </form>
                <button type="button" class="btn btn-add" onclick="openAddModal()"><i class="fas fa-plus"></i> Tambah Tanaman</button>
            </div>


            <div class="table-container">
                <h2>Daftar Tanaman</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Tanaman</th>
                            <th>Kategori</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tanaman) > 0): ?>
                            <?php foreach ($tanaman as $t): ?>
                                <tr>
                                    <td><?= htmlspecialchars($t['id_tanaman']) ?></td>
                                    <td><?= htmlspecialchars($t['nama_tanaman']) ?></td>
                                    <td><?= htmlspecialchars($t['kategori']) ?></td>
                                    <td>
                                        <a href="#" class="edit-btn" onclick="openEditModal(<?= htmlspecialchars($t['id_tanaman']) ?>, '<?= htmlspecialchars($t['nama_tanaman']) ?>', '<?= htmlspecialchars($t['kategori']) ?>'); return false;"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="?delete=<?= htmlspecialchars($t['id_tanaman']) ?>" class="delete-btn" onclick="return confirm('Apakah Anda yakin ingin menghapus tanaman <?= htmlspecialchars($t['nama_tanaman']) ?>? Ini juga akan menghapus aturan yang terkait!');"><i class="fas fa-trash-alt"></i> Hapus</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 20px; color: var(--text-muted);">Belum ada data tanaman.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <div id="addTanamanModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeAddModal()">&times;</span>
            <h2>Tambah Tanaman Baru</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_tanaman">
                <label for="add-nama_tanaman">Nama Tanaman</label>
                <input type="text" name="nama_tanaman" id="add-nama_tanaman" placeholder="Masukkan Nama Tanaman" required>

                <label for="add-kategori">Kategori</label>
                <select name="kategori" id="add-kategori" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php
                    // Daftar kategori yang tersedia untuk dipilih
                    $availableCategories = ['Sayur', 'Obat', 'Bunga', 'Buah'];
                    foreach ($availableCategories as $cat) {
                        echo '<option value="' . htmlspecialchars($cat) . '">' . htmlspecialchars($cat) . '</option>';
                    }
                    ?>
                </select>
                <button type="submit" class="btn">Tambah Tanaman</button>
            </form>
        </div>
    </div>

    <div id="editTanamanModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeEditModal()">&times;</span>
            <h2>Edit Tanaman</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_tanaman">
                <input type="hidden" name="edit_id_tanaman" id="edit_id_tanaman">

                <label for="edit_nama_tanaman">Nama Tanaman</label>
                <input type="text" name="edit_nama_tanaman" id="edit_nama_tanaman" required>

                <label for="edit_kategori">Kategori</label>
                <select name="edit_kategori" id="edit_kategori" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php
                    // Daftar kategori yang tersedia untuk dipilih
                    $availableCategories = ['Sayur', 'Obat', 'Bunga', 'Buah'];
                    foreach ($availableCategories as $cat) {
                        echo '<option value="' . htmlspecialchars($cat) . '">' . htmlspecialchars($cat) . '</option>';
                    }
                    ?>
                </select>
                <button type="submit" class="btn">Simpan Perubahan</button>
            </form>
        </div>
    </div>

    <script>
        // Get the modals
        var addTanamanModal = document.getElementById('addTanamanModal');
        var editTanamanModal = document.getElementById('editTanamanModal');

        // Functions to open/close Add Tanaman Modal
        function openAddModal() {
            // Reset form fields when opening for adding
            document.getElementById('add-nama_tanaman').value = '';
            document.getElementById('add-kategori').value = ''; // Reset dropdown to default
            addTanamanModal.style.display = 'flex'; // Use flex to center the modal
        }

        function closeAddModal() {
            addTanamanModal.style.display = 'none';
        }

        // Functions to open/close Edit Tanaman Modal
        function openEditModal(id, nama, kategori) {
            document.getElementById('edit_id_tanaman').value = id;
            document.getElementById('edit_nama_tanaman').value = nama;
            document.getElementById('edit_kategori').value = kategori; // Set selected value for dropdown
            editTanamanModal.style.display = 'flex'; // Use flex to center the modal
        }

        function closeEditModal() {
            editTanamanModal.style.display = 'none';
        }

        // Close the modals if clicked outside of them
        window.onclick = function(event) {
            if (event.target == addTanamanModal) {
                addTanamanModal.style.display = "none";
            }
            if (event.target == editTanamanModal) {
                editTanamanModal.style.display = "none";
            }
        }
    </script>

</body>

</html>