<?php
// === admin/load_pengguna.php ===
session_start();
require_once '../lib/db.php'; // Pastikan path ini benar, jika db.php ada di direktori yang sama, cukup 'db.php'

// Cek apakah admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin = $_SESSION['user']; // Get admin info for sidebar display

// Fungsi helper untuk redirect dengan pesan
function redirectWithUrlMessage($message, $type) {
    $encodedMessage = urlencode($message);
    header("Location: load_pengguna.php?message={$encodedMessage}&type={$type}");
    exit();
}

// --- Tambah user ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);

    if (empty($username) || empty($password) || empty($role)) {
        error_log("Attempt to add user with empty fields.");
        redirectWithUrlMessage("Username, Password, dan Peran tidak boleh kosong.", "error");
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $conn->prepare("INSERT INTO user (username, password, role, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$username, $hashedPassword, $role]);
        redirectWithUrlMessage("Pengguna berhasil ditambahkan.", "success");
    } catch (PDOException $e) {
        error_log("Error adding user: " . $e->getMessage());
        if ($e->getCode() === '23000') { // Duplicate entry error
            redirectWithUrlMessage("Gagal menambahkan pengguna. Username sudah ada.", "error");
        } else {
            redirectWithUrlMessage("Gagal menambahkan pengguna. Terjadi kesalahan database: " . $e->getMessage(), "error");
        }
    }
}

// --- Update user ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $id_user = trim($_POST['edit_id_user']);
    $username = trim($_POST['edit_username']);
    $role = trim($_POST['edit_role']);
    $password = trim($_POST['edit_password']);

    if (empty($id_user) || empty($username) || empty($role)) {
        redirectWithUrlMessage("Username dan Peran tidak boleh kosong saat mengedit.", "error");
    }

    try {
        $sql = "UPDATE user SET username = ?, role = ?";
        $params = [$username, $role];

        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $sql .= ", password = ?";
            $params[] = $hashedPassword;
        }

        $sql .= " WHERE id_user = ?";
        $params[] = $id_user;

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        redirectWithUrlMessage("Pengguna berhasil diperbarui.", "success");
    } catch (PDOException $e) {
        error_log("Error updating user: " . $e->getMessage());
        if ($e->getCode() === '23000') { // Duplicate entry error
            redirectWithUrlMessage("Gagal memperbarui pengguna. Username sudah digunakan oleh pengguna lain.", "error");
        } else {
            redirectWithUrlMessage("Gagal memperbarui pengguna. Terjadi kesalahan database: " . $e->getMessage(), "error");
        }
    }
}

// --- Hapus user ---
if (isset($_GET['delete'])) {
    $userIdToDelete = $_GET['delete'];
    if ($userIdToDelete == $admin['id_user']) {
        redirectWithUrlMessage("Anda tidak bisa menghapus akun Anda sendiri.", "error");
    }

    try {
        $stmtRiwayat = $conn->prepare("DELETE FROM riwayat WHERE id_user = ?");
        $stmtRiwayat->execute([$userIdToDelete]);

        // Step 2: Now delete the user from 'user' table
        $stmt = $conn->prepare("DELETE FROM user WHERE id_user = ?");
        $stmt->execute([$userIdToDelete]);
        redirectWithUrlMessage("Pengguna berhasil dihapus.", "success");
    } catch (PDOException $e) {
        // You might want to check the SQLSTATE error code for more specific errors
        // For example, if there's still a foreign key constraint violation.
        error_log("Error deleting user: " . $e->getMessage());
        redirectWithUrlMessage("Gagal menghapus pengguna. Pastikan tidak ada riwayat terkait atau coba lagi. Detail: " . $e->getMessage(), "error");
    }
}

// --- Ambil semua user dengan filter ---
$filterRole = isset($_GET['filter_role']) ? $_GET['filter_role'] : '';
$searchUsername = isset($_GET['search_username']) ? trim($_GET['search_username']) : '';

$sql = "SELECT * FROM user WHERE 1=1"; // Start with a true condition
$params = [];

if (!empty($searchUsername)) {
    $sql .= " AND username LIKE ?";
    $params[] = '%' . $searchUsername . '%';
}

if (!empty($filterRole) && $filterRole !== 'all') { // 'all' means no role filter
    $sql .= " AND role = ?";
    $params[] = $filterRole;
}

$sql .= " ORDER BY id_user DESC"; // Optional: order by ID or username
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize message variables for JavaScript
$js_message = '';
$js_message_type = '';
if (isset($_GET['message'])) {
    $js_message = urldecode($_GET['message']);
    $js_message_type = $_GET['type'];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kelola Pengguna - GoAgriculture</title>
    <link rel="manifest" href="/manifest.json">
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

        /* --- Floating Message Styling --- */
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
            flex-grow: 1;
            /* Allow filter group to take available space */
        }

        .action-bar .filter-group label {
            margin-bottom: 0;
            white-space: nowrap;
            /* Prevent label from wrapping */
            font-size: 0.95em;
        }

        .action-bar .filter-group input[type="text"],
        .action-bar .filter-group select {
            margin-bottom: 0;
            padding: 8px 12px;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--border-light);
            flex-grow: 1;
            /* Allow inputs to grow */
            max-width: 200px;
            /* Limit max width for inputs */
        }

        .action-bar .btn-filter,
        .action-bar .btn-reset,
        .action-bar .btn-add {
            padding: 8px 18px;
            font-size: 0.95em;
            width: auto;
            /* Override btn default 100% width */
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
            background-color: rgba(0, 0, 0, 0.4);
            justify-content: center;
            align-items: center;
            padding: 20px;
            /* Add padding for small screens */
        }

        .modal-content {
            background-color: var(--text-on-dark);
            margin: auto;
            /* Centers the modal vertically and horizontally */
            padding: 30px;
            border-radius: var(--border-radius-main);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 500px;
            animation-name: animatetop;
            animation-duration: 0.4s;
            position: relative;
        }

        @keyframes animatetop {
            from {
                top: -300px;
                opacity: 0
            }

            to {
                top: 0;
                opacity: 1
            }
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

            /* ... (existing styles) ... */
            .action-bar {
                flex-direction: column;
                align-items: stretch;
                padding: 15px;
            }

            .action-bar .filter-group {
                flex-direction: column;
                /* Stack filter inputs */
                align-items: flex-start;
                width: 100%;
            }

            .action-bar .filter-group input[type="text"],
            .action-bar .filter-group select {
                max-width: 100%;
                /* Full width for inputs in stacked layout */
            }

            .action-bar .btn-filter,
            .action-bar .btn-reset,
            .action-bar .btn-add {
                width: 100%;
                /* Full width for action buttons */
                margin-top: 10px;
                /* Space between stacked buttons */
            }

            .action-bar .filter-group label {
                margin-bottom: 5px;
                /* Add some space below labels */
            }
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
        @media (max-width: 576px) {

            /* ... (existing styles) ... */
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
                    <li><a href="load_aturan.php"><i class="fas fa-book-open"></i> <span>Aturan dan Tanaman</span></a></li>
                    <li><a href="load_pengguna.php" class="active"><i class="fas fa-users"></i> <span>Pengguna</span></a></li>
                    <li><a href="load_profil.php"><i class="fas fa-user-circle"></i> <span>Profil Admin</span></a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="../auth/logout.php" class="sidebar-logout-btn"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </aside>

        <main class="main-content">
            <div class="content-header">
                <h1>Manajemen Pengguna</h1>
            </div>

            <div class="action-bar">
                <form method="GET" action="load_pengguna.php" class="filter-group">
                    <label for="search_username">Cari:</label>
                    <input type="text" name="search_username" id="search_username" placeholder="Username" value="<?= htmlspecialchars($searchUsername) ?>">

                    <label for="filter_role">Peran:</label>
                    <select name="filter_role" id="filter_role">
                        <option value="all" <?= ($filterRole === 'all' || empty($filterRole)) ? 'selected' : '' ?>>Semua</option>
                        <option value="user" <?= ($filterRole === 'user') ? 'selected' : '' ?>>User</option>
                        <option value="admin" <?= ($filterRole === 'admin') ? 'selected' : '' ?>>Admin</option>
                    </select>
                    <button type="submit" class="btn btn-filter"><i class="fas fa-search"></i> Cari</button>
                    <button type="button" class="btn btn-filter" onclick="window.location.href='load_pengguna.php'"><i class="fas fa-redo"></i> Reset</button>
                </form>
                <button type="button" class="btn btn-filter" onclick="openAddModal()"><i class="fas fa-plus"></i> Tambah Pengguna</button>
            </div>


            <div class="table-container">
                <h2>Daftar Pengguna</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?= htmlspecialchars($u['id_user']) ?></td>
                                    <td><?= htmlspecialchars($u['username']) ?></td>
                                    <td><?= htmlspecialchars($u['role']) ?></td>
                                    <td>
                                        <?php if ($u['id_user'] != $admin['id_user']): // Prevent admin from editing/deleting themselves 
                                        ?>
                                            <a href="#" class="edit-btn" onclick="openEditModal(<?= htmlspecialchars($u['id_user']) ?>, '<?= htmlspecialchars($u['username']) ?>', '<?= htmlspecialchars($u['role']) ?>'); return false;"><i class="fas fa-edit"></i> Edit</a>
                                            <a href="?delete=<?= htmlspecialchars($u['id_user']) ?>" class="delete-btn" onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna <?= htmlspecialchars($u['username']) ?>?');"><i class="fas fa-trash-alt"></i> Hapus</a>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted); font-size:0.9em;">(Anda)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 20px; color: var(--text-muted);">Belum ada pengguna.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeAddModal()">&times;</span>
            <h2>Tambah Pengguna Baru</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                <label for="add-username">Username</label>
                <input type="text" name="username" id="add-username" placeholder="Masukkan Username" required>

                <label for="add-password">Password</label>
                <input type="password" name="password" id="add-password" placeholder="Masukkan Password" required>

                <label for="add-role">Peran (Role)</label>
                <select name="role" id="add-role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="submit" class="btn">Tambah Pengguna</button>
            </form>
        </div>
    </div>

    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeEditModal()">&times;</span>
            <h2>Edit Pengguna</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="edit_id_user" id="edit_id_user">

                <label for="edit_username">Username</label>
                <input type="text" name="edit_username" id="edit_username" required>

                <label for="edit_password">Password (Biarkan kosong jika tidak ingin mengubah)</label>
                <input type="password" name="edit_password" id="edit_password" placeholder="Isi untuk mengubah password">

                <label for="edit_role">Peran (Role)</label>
                <select name="edit_role" id="edit_role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="submit" class="btn">Simpan Perubahan</button>
            </form>
        </div>
    </div>

    <script>
        // PHP variables for message handling
        const jsMessage = "<?= addslashes($js_message) ?>";
        const jsMessageType = "<?= addslashes($js_message_type) ?>";

        // Get the modals
        var addUserModal = document.getElementById('addUserModal');
        var editUserModal = document.getElementById('editUserModal');

        // Function to handle messages
        window.onload = function() {
            if (jsMessage && jsMessageType) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `message ${jsMessageType}`;
                alertDiv.innerHTML = `<i class="fas fa-${jsMessageType === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${jsMessage}`;
                
                // Append directly to body for floating messages
                document.body.appendChild(alertDiv);

                setTimeout(() => {
                    alertDiv.style.opacity = '0';
                    alertDiv.addEventListener('transitionend', () => alertDiv.remove());
                }, 5000); // Pesan akan hilang setelah 5 detik
            }
        };

        // Functions to open/close Add User Modal
        function openAddModal() {
            addUserModal.style.display = 'flex'; // Use flex to center the modal
        }

        function closeAddModal() {
            addUserModal.style.display = 'none';
        }

        // Functions to open/close Edit User Modal
        function openEditModal(id, username, role) {
            document.getElementById('edit_id_user').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_password').value = ''; // Clear password field on open
            editUserModal.style.display = 'flex'; // Use flex to center the modal
        }

        function closeEditModal() {
            editUserModal.style.display = 'none';
        }

        // Close the modals if clicked outside of them
        window.onclick = function(event) {
            if (event.target == addUserModal) {
                addUserModal.style.display = "none";
            }
            if (event.target == editUserModal) {
                editUserModal.style.display = "none";
            }
        }

        // Ensure modals are hidden on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
            if (addUserModal) addUserModal.style.display = 'none';
            if (editUserModal) editUserModal.style.display = 'none';

            // Set active sidebar link
            const currentPath = window.location.pathname.split('/').pop();
            document.querySelectorAll('.sidebar nav ul li a').forEach(link => {
                const linkPath = link.getAttribute('href').split('/').pop();
                if (linkPath === currentPath) {
                    link.classList.add('active');
                }
            });
        });
    </script>

</body>

</html>
