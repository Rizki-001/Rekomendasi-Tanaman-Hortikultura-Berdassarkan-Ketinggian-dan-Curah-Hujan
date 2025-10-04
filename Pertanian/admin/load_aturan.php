<?php
session_start();
require_once '../lib/db.php';

// Cek apakah admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
$admin = $_SESSION['user'];

// Fungsi helper untuk redirect dengan pesan
function redirectWithUrlMessage($message, $type)
{
    $encodedMessage = urlencode($message);
    header("Location: load_aturan.php?message={$encodedMessage}&type={$type}");
    exit();
}

// Fetch all tanaman for dropdowns (still needed for edit modal)
try {
    $stmtAllTanaman = $conn->prepare("SELECT id_tanaman, nama_tanaman, kategori FROM tanaman ORDER BY nama_tanaman ASC");
    $stmtAllTanaman->execute();
    $allTanaman = $stmtAllTanaman->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching all tanaman: " . $e->getMessage());
    $allTanaman = []; // Fallback to empty array
}

// Fetch all distinct categories for dropdowns
try {
    $stmtCategories = $conn->prepare("SELECT DISTINCT kategori FROM tanaman ORDER BY kategori ASC");
    $stmtCategories->execute();
    $allCategories = $stmtCategories->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $allCategories = []; // Fallback to empty array
}

// --- Tambah aturan ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_aturan') {
    $nama_tanaman = trim($_POST['nama_tanaman']);
    $kategori = trim($_POST['kategori']); // Menggunakan 'kategori'
    $min_ketinggian = trim($_POST['min_ketinggian']);
    $max_ketinggian = trim($_POST['max_ketinggian']);
    $min_curah_hujan = trim($_POST['min_curah_hujan']);
    $max_curah_hujan = trim($_POST['max_curah_hujan']);

    // Validasi input
    if (empty($nama_tanaman) || empty($kategori)) {
        redirectWithUrlMessage("Nama Tanaman dan Kategori Tanaman harus diisi.", "error");
    }

    if (!is_numeric($min_ketinggian) || !is_numeric($max_ketinggian) || !is_numeric($min_curah_hujan) || !is_numeric($max_curah_hujan)) {
        redirectWithUrlMessage("Semua kolom aturan (ketinggian dan curah hujan) harus diisi dan berupa angka.", "error");
    }

    if ($min_ketinggian > $max_ketinggian || $min_curah_hujan > $max_curah_hujan) {
        redirectWithUrlMessage("Nilai minimum tidak boleh lebih besar dari nilai maksimum untuk ketinggian atau curah hujan.", "error");
    }

    try {
        $conn->beginTransaction(); // Start transaction

        $id_tanaman = null;
        // Check if plant already exists
        $stmtCheckTanaman = $conn->prepare("SELECT id_tanaman FROM tanaman WHERE nama_tanaman = ? AND kategori = ?"); // Menggunakan 'kategori'
        $stmtCheckTanaman->execute([$nama_tanaman, $kategori]);
        $existingTanaman = $stmtCheckTanaman->fetch(PDO::FETCH_ASSOC);

        if ($existingTanaman) {
            $id_tanaman = $existingTanaman['id_tanaman'];
        } else {
            // If not, insert new plant. Removed 'created_at' from column list.
            $stmtInsertTanaman = $conn->prepare("INSERT INTO tanaman (nama_tanaman, kategori) VALUES (?, ?)"); // Menggunakan 'kategori'
            $stmtInsertTanaman->execute([$nama_tanaman, $kategori]);
            $id_tanaman = $conn->lastInsertId();
        }

        // Now insert the rule using the obtained id_tanaman. Removed 'created_at' from column list.
        $stmt = $conn->prepare("INSERT INTO aturan (id_tanaman, min_ketinggian, max_ketinggian, min_curah_hujan, max_curah_hujan) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $id_tanaman,
            $min_ketinggian,
            $max_ketinggian,
            $min_curah_hujan,
            $max_curah_hujan
        ]);
        $conn->commit(); // Commit transaction
        redirectWithUrlMessage("Aturan berhasil ditambahkan.", "success");
    } catch (PDOException $e) {
        $conn->rollBack(); // Rollback on error
        error_log("Error adding aturan: " . $e->getMessage());

        // Check for specific error code for duplicate entry (SQLSTATE 23000)
        if ($e->getCode() === '23000') {
            redirectWithUrlMessage("Gagal menambahkan aturan: Data aturan atau tanaman dengan kriteria yang sama sudah ada. Error: " . $e->getMessage(), "error");
        } else {
            redirectWithUrlMessage("Gagal menambahkan aturan. Terjadi kesalahan database: " . $e->getMessage(), "error");
        }
    }
}

// --- Hapus aturan ---
if (isset($_GET['delete'])) {
    $id_aturan_to_delete = $_GET['delete'];
    try {
        $conn->beginTransaction();

        // 1. Dapatkan id_tanaman dari aturan yang akan dihapus
        $stmtGetTanamanId = $conn->prepare("SELECT id_tanaman FROM aturan WHERE id_aturan = ?");
        $stmtGetTanamanId->execute([$id_aturan_to_delete]);
        $result = $stmtGetTanamanId->fetch(PDO::FETCH_ASSOC);
        $id_tanaman_to_check = $result ? $result['id_tanaman'] : null;

        // 2. Hapus aturan
        $stmtDeleteAturan = $conn->prepare("DELETE FROM aturan WHERE id_aturan = ?");
        $stmtDeleteAturan->execute([$id_aturan_to_delete]);

        // 3. Jika id_tanaman ditemukan, periksa apakah ada aturan lain yang merujuk ke tanaman ini
        if ($id_tanaman_to_check) {
            $stmtCheckReferences = $conn->prepare("SELECT COUNT(*) FROM aturan WHERE id_tanaman = ?");
            $stmtCheckReferences->execute([$id_tanaman_to_check]);
            $remainingReferences = $stmtCheckReferences->fetchColumn();

            // 4. Jika tidak ada aturan lain yang merujuk, hapus juga tanaman
            if ($remainingReferences == 0) {
                $stmtDeleteTanaman = $conn->prepare("DELETE FROM tanaman WHERE id_tanaman = ?");
                $stmtDeleteTanaman->execute([$id_tanaman_to_check]);
            }
        }

        $conn->commit();
        redirectWithUrlMessage("Aturan dan/atau data tanaman berhasil dihapus.", "success");
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error deleting aturan and/or tanaman: " . $e->getMessage());
        redirectWithUrlMessage("Gagal menghapus aturan. Terjadi kesalahan database: " . $e->getMessage(), "error");
    }
}

// --- Edit aturan ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_aturan') {
    $id_aturan = trim($_POST['edit_id_aturan']);
    $new_id_tanaman = trim($_POST['edit_id_tanaman']); // Ini adalah ID tanaman yang baru dipilih dari dropdown
    $new_nama_tanaman = trim($_POST['edit_nama_tanaman']); // Nama tanaman dari input teks
    $new_kategori = trim($_POST['edit_kategori']); // Kategori tanaman dari input teks/select

    $min_ketinggian = trim($_POST['edit_min_ketinggian']);
    $max_ketinggian = trim($_POST['edit_max_ketinggian']);
    $min_curah_hujan = trim($_POST['edit_min_curah_hujan']);
    $max_curah_hujan = trim($_POST['edit_max_curah_hujan']);

    // Validasi input
    if (empty($id_aturan) || !is_numeric($id_aturan) || !filter_var($id_aturan, FILTER_VALIDATE_INT)) {
        redirectWithUrlMessage("ID Aturan tidak valid untuk diedit.", "error");
    }
    if (empty($new_id_tanaman) || !is_numeric($new_id_tanaman) || !filter_var($new_id_tanaman, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        redirectWithUrlMessage("ID Tanaman saat mengedit harus angka positif.", "error");
    }
    // Validasi nama_tanaman dan kategori, walaupun tidak selalu diupdate, tapi penting untuk validasi input form
    if (empty($new_nama_tanaman) || empty($new_kategori)) {
        redirectWithUrlMessage("Nama Tanaman dan Kategori Tanaman harus diisi saat mengedit.", "error");
    }

    if (!is_numeric($min_ketinggian) || !is_numeric($max_ketinggian) || !is_numeric($min_curah_hujan) || !is_numeric($max_curah_hujan)) {
        redirectWithUrlMessage("Semua kolom aturan harus diisi saat mengedit dan harus berupa angka.", "error");
    }

    // Validasi range
    if ($min_ketinggian > $max_ketinggian || $min_curah_hujan > $max_curah_hujan) {
        redirectWithUrlMessage("Nilai minimum tidak boleh lebih besar dari nilai maksimum saat mengedit.", "error");
    }

    try {
        $conn->beginTransaction(); // Start transaction

        // Ambil id_tanaman lama yang terhubung dengan id_aturan ini
        $stmtGetOldTanamanId = $conn->prepare("SELECT id_tanaman FROM aturan WHERE id_aturan = ?");
        $stmtGetOldTanamanId->execute([$id_aturan]);
        $old_tanaman_data = $stmtGetOldTanamanId->fetch(PDO::FETCH_ASSOC);
        $old_id_tanaman = $old_tanaman_data ? $old_tanaman_data['id_tanaman'] : null;

        // Hanya update detail tanaman (nama_tanaman, kategori) JIKA:
        // 1. ID tanaman yang diedit SAMA dengan ID tanaman lama (tidak mengganti ke tanaman lain)
        // 2. Ada perubahan pada nama_tanaman atau kategori (dibandingkan dengan yang ada di DB untuk id_tanaman tersebut)
        if ($old_id_tanaman == $new_id_tanaman) {
            $stmtGetExistingTanamanDetails = $conn->prepare("SELECT nama_tanaman, kategori FROM tanaman WHERE id_tanaman = ?");
            $stmtGetExistingTanamanDetails->execute([$old_id_tanaman]);
            $existing_tanaman_details = $stmtGetExistingTanamanDetails->fetch(PDO::FETCH_ASSOC);

            if ($existing_tanaman_details && ($existing_tanaman_details['nama_tanaman'] !== $new_nama_tanaman || $existing_tanaman_details['kategori'] !== $new_kategori)) {
                $stmtUpdateTanaman = $conn->prepare("UPDATE tanaman SET nama_tanaman = ?, kategori = ? WHERE id_tanaman = ?");
                $stmtUpdateTanaman->execute([$new_nama_tanaman, $new_kategori, $new_id_tanaman]);
            }
        }
        // Jika $old_id_tanaman != $new_id_tanaman, berarti aturan ini sekarang terkait dengan tanaman lain.
        // Kita tidak akan mengubah nama/kategori tanaman yang baru terpilih dari form edit aturan ini,
        // karena itu adalah data tanaman yang sudah ada di tabel 'tanaman' dan harus dikelola di tempat lain.

        // Update aturan details (id_tanaman mungkin berubah, serta data ketinggian dan curah hujan)
        $stmt = $conn->prepare("UPDATE aturan SET id_tanaman = ?, min_ketinggian = ?, max_ketinggian = ?, min_curah_hujan = ?, max_curah_hujan = ? WHERE id_aturan = ?");
        $stmt->execute([
            $new_id_tanaman,
            $min_ketinggian,
            $max_ketinggian,
            $min_curah_hujan,
            $max_curah_hujan,
            $id_aturan
        ]);
        $conn->commit(); // Commit transaction
        redirectWithUrlMessage("Aturan berhasil diperbarui.", "success");
    } catch (PDOException $e) {
        $conn->rollBack(); // Rollback on error
        error_log("Error updating aturan: " . $e->getMessage());
        redirectWithUrlMessage("Gagal memperbarui aturan. Terjadi kesalahan database: " . $e->getMessage(), "error"); // Memberikan pesan error dari database
    }
}

// Fetch data for display
$searchIdAturan = isset($_GET['search_id_aturan']) ? trim($_GET['search_id_aturan']) : '';
$searchNamaTanaman = isset($_GET['search_nama_tanaman']) ? trim($_GET['search_nama_tanaman']) : '';
$searchKategoriTanaman = isset($_GET['search_kategori']) ? trim($_GET['search_kategori']) : ''; // Menggunakan 'search_kategori'
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'id_aturan';
$sortOrder = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';
$allowedSortColumns = [
    'id_aturan',
    'id_tanaman',
    'nama_tanaman',
    'kategori', // Menggunakan 'kategori'
    'min_ketinggian',
    'max_ketinggian',
    'min_curah_hujan',
    'max_curah_hujan'
];
if (!in_array($sortColumn, $allowedSortColumns)) {
    $sortColumn = 'id_aturan';
}
if (!in_array($sortOrder, ['ASC', 'DESC'])) {
    $sortOrder = 'DESC';
}

// Modify SQL query to include kategori and remove other filters
$sql = "SELECT a.*, t.nama_tanaman, t.kategori FROM aturan a JOIN tanaman t ON a.id_tanaman = t.id_tanaman WHERE 1=1"; // Menggunakan 't.kategori'
$params = [];

if (!empty($searchIdAturan) && is_numeric($searchIdAturan)) {
    $sql .= " AND a.id_aturan = ?";
    $params[] = $searchIdAturan;
}
if (!empty($searchNamaTanaman)) {
    $sql .= " AND t.nama_tanaman LIKE ?";
    $params[] = '%' . $searchNamaTanaman . '%';
}
if (!empty($searchKategoriTanaman)) {
    $sql .= " AND t.kategori = ?"; // Menggunakan 't.kategori'
    $params[] = $searchKategoriTanaman;
}

$sql .= " ORDER BY {$sortColumn} {$sortOrder}";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$aturan = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getSortLink($column, $currentSortColumn, $currentSortOrder)
{
    $newOrder = ($currentSortColumn === $column && $currentSortOrder === 'ASC') ? 'DESC' : 'ASC';
    $queryString = http_build_query(array_merge($_GET, ['sort' => $column, 'order' => $newOrder]));
    return '?' . $queryString;
}
function getSortIcon($column, $currentSortColumn, $currentSortOrder)
{
    if ($currentSortColumn === $column) {
        return $currentSortOrder === 'ASC' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>';
    }
    return '<i class="fas fa-sort"></i>'; // Default sort icon
}

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
    <title>Kelola Aturan - GoAgriculture</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="/manifest.json">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --dark-prime: #3D724D;
            --light-bg: #F5F5F5;
            --sidebar-bg: #315F40;
            --main-accent-green: #66BB6A;
            --secondary-accent-green: #4CAF50;
            --accent-blue: #2196F3;
            --accent-red: #D32F2F;
            --text-on-dark: #FFFFFF;
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
            /* Changed to white for brand text */
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

        .wrapper {
            display: flex;
            padding-top: 70px;
            min-height: 100vh;
        }

        .sidebar {
            width: 80px;
            background-color: var(--sidebar-bg);
            color: var(--text-on-dark);
            padding: 20px 0;
            box-shadow: 2px 0 15px var(--shadow-light);
            position: sticky;
            top: 70px;
            height: calc(100vh - 70px);
            overflow: hidden;
            z-index: 999;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: width var(--transition-speed) ease;
        }

        .sidebar:hover {
            width: 250px;
        }

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

        .sidebar-header .logo img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 0;
            border: 3px solid var(--main-accent-green);
            transition: margin-bottom var(--transition-speed) ease;
        }

        .sidebar:hover .sidebar-header .logo img {
            margin-bottom: 10px;
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
            color: inherit;
        }

        .sidebar-logout-btn:hover {
            background-color: var(--main-accent-green);
            color: var(--text-on-dark);
            border-color: var(--main-accent-green);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

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
        input[type="number"],
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

        .message {
            position: fixed;
            /* Make it float above content */
            top: 20px;
            /* Distance from top */
            right: 20px;
            /* Distance from right */
            z-index: 2000;
            /* Ensure it's on top of everything */
            padding: 15px 25px;
            /* More padding for a better look */
            border-radius: var(--border-radius-main);
            /* Rounded corners */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            /* Add shadow */
            font-weight: 600;
            opacity: 1;
            /* Start visible */
            transition: opacity 0.5s ease-out, top 0.5s ease-out;
            /* Smooth fade and slide out */
            max-width: 90%;
            /* Limit width on smaller screens */
            width: auto;
            /* Adjust width to content */
            text-align: left;
            /* Align text within the floating box */
            display: flex;
            /* Use flex for icon and text alignment */
            align-items: center;
            gap: 10px;
            /* Space between icon and text */
        }

        .success {
            background-color: #e8f5e9;
            /* Light green */
            color: #2e7d32;
            /* Dark green */
            border: 1px solid #4caf50;
        }

        .error {
            background-color: #ffebee;
            /* Light red */
            color: #c62828;
            /* Dark red */
            border: 1px solid #f44336;
        }

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

        table th a {
            /* Style for sortable headers */
            color: var(--text-on-dark);
            display: flex;
            align-items: center;
            gap: 5px;
            justify-content: center;
            /* Center content in header */
        }

        table th a:hover {
            color: #d4edda;
            /* Lighter shade on hover */
        }

        table th a .fas {
            font-size: 0.8em;
        }

        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        table tr:hover {
            background-color: #eef2f1;
        }

        table td a {
            display: inline-flex;
            /* Use flex for icon and text */
            align-items: center;
            gap: 5px;
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
            align-items: flex-end;
            /* Aligns items vertically at the bottom */
            flex-wrap: wrap;
            gap: 15px;
            /* Consistent gap between main items */
            margin-bottom: 20px;
            padding: 20px;
            background-color: var(--text-on-dark);
            border-radius: var(--border-radius-main);
            box-shadow: 0 2px 8px var(--shadow-light);
        }

        .action-bar .filter-group {
            display: flex;
            align-items: flex-end;
            /* Aligns items vertically at the bottom within the filter group */
            gap: 10px;
            /* Consistent gap between filter items */
            flex-wrap: wrap;
            flex-grow: 1;
        }

        .action-bar .filter-item {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            /* Label at top of its input */
            gap: 5px;
            /* Gap between label and input/select */
        }

        .action-bar .filter-group label {
            /* margin-bottom: 5px; Removed, now handled by gap in .filter-item */
            white-space: nowrap;
            font-size: 0.95em;
            color: var(--text-on-light);
        }

        .action-bar .filter-group input[type="text"],
        .action-bar .filter-group input[type="number"],
        .action-bar .filter-group select {
            padding: 8px 12px;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--border-light);
            width: 150px;
            /* Default width for filter inputs */
            background-color: #fcfcfc;
            color: var(--text-on-light);
            font-family: 'Roboto', sans-serif;
            font-size: 0.95em;
            margin-bottom: 0;
            /* Ensure no extra margin at the bottom */
        }

        .action-bar .filter-group input[type="text"].long-input {
            width: 200px;
            /* Wider input for name */
        }

        .action-bar .filter-group input[type="number"].small-input {
            width: 100px;
            /* Smaller for min/max */
        }


        .action-bar .btn-filter,
        .action-bar .btn-reset,
        .action-bar .btn-add {
            padding: 8px 18px;
            font-size: 0.95em;
            width: auto;
            /* Override btn default 100% width */
            border-radius: var(--border-radius-sm);
            margin-top: 0;
            /* Ensures buttons align with inputs */
            display: flex;
            align-items: center;
            gap: 5px;
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

        /* Untuk input number dalam modal agar lebarnya konsisten */
        .modal-content input[type="number"],
        .modal-content input[type="text"],
        .modal-content select {
            /* Tambahkan ini untuk input teks dan select di modal */
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

        /* Responsiveness */
        @media (max-width: 992px) {
            .action-bar {
                flex-direction: column;
                /* Stack main action bar items */
                align-items: stretch;
                padding: 15px;
            }

            .action-bar .filter-group {
                flex-direction: column;
                /* Stack filter items */
                align-items: stretch;
                width: 100%;
                gap: 15px;
                /* Increase gap for stacked items */
            }

            .action-bar .filter-item {
                width: 100%;
                /* Full width for each filter item */
            }

            .action-bar .filter-group input[type="text"],
            .action-bar .filter-group input[type="number"],
            .action-bar .filter-group select {
                width: 100%;
                /* All inputs take full width */
                max-width: none;
                /* Remove max-width constraint */
            }

            .action-bar .btn-filter,
            .action-bar .btn-reset,
            .action-bar .btn-add {
                width: 100%;
                /* Full width for buttons */
                margin-top: 10px;
                /* Space between stacked buttons */
            }

            .action-bar .filter-group label {
                margin-bottom: 5px;
                /* Keep margin for label above input */
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
            .modal-content {
                padding: 20px;
            }

            .close-button {
                font-size: 24px;
                top: 5px;
                right: 15px;
            }

            table td a {
                margin-right: 2px;
                padding: 5px 8px;
                font-size: 0.8em;
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
                    <li><a href="load_aturan.php" class="active"><i class="fas fa-book-open"></i> <span>Aturan dan Tanaman</span></a></li>
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
                <h1>Manajemen Aturan Rekomendasi dan Tanaman</h1>
            </div>
            <div class="action-bar">
                <form method="GET" action="load_aturan.php" class="filter-group">
                    <div class="filter-item">
                        <label for="search_id_aturan">ID Aturan</label>
                        <input type="number" name="search_id_aturan" id="search_id_aturan" placeholder="ID Aturan" value="<?= htmlspecialchars($searchIdAturan) ?>" class="small-input">
                    </div>

                    <div class="filter-item">
                        <label for="search_nama_tanaman">Nama Tanaman</label>
                        <input type="text" name="search_nama_tanaman" id="search_nama_tanaman" placeholder="Nama Tanaman" value="<?= htmlspecialchars($searchNamaTanaman) ?>" class="long-input">
                    </div>

                    <div class="filter-item">
                        <label for="search_kategori">Kategori</label>
                        <select name="search_kategori" id="search_kategori">
                            <option value="">Semua Kategori</option>
                            <?php foreach ($allCategories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>" <?= ($searchKategoriTanaman === $category) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-filter"><i class="fas fa-search"></i> Cari</button>
                    <button class="btn btn-filter"><a href="load_aturan.php" style="color: white;"><i class="fas fa-redo"></i> Reset</a></button>
                </form>
                <button type="button" class="btn btn-add" onclick="openAddModal()"><i class="fas fa-plus"></i> Tambah Data</button>
            </div>


            <div class="table-container">
                <h2>Daftar Aturan Rekomendasi dan Tanaman</h2>
                <table>
                    <thead>
                        <tr>
                            <th><a href="<?= getSortLink('id_aturan', $sortColumn, $sortOrder) ?>">ID Aturan <?= getSortIcon('id_aturan', $sortColumn, $sortOrder) ?></a></th>
                            <th><a href="<?= getSortLink('id_tanaman', $sortColumn, $sortOrder) ?>">ID Tanaman <?= getSortIcon('id_tanaman', $sortColumn, $sortOrder) ?></a></th>
                            <th><a href="<?= getSortLink('nama_tanaman', $sortColumn, $sortOrder) ?>">Nama Tanaman <?= getSortIcon('nama_tanaman', $sortColumn, $sortOrder) ?></a></th>
                            <th><a href="<?= getSortLink('kategori', $sortColumn, $sortOrder) ?>">Kategori <?= getSortIcon('kategori', $sortColumn, $sortOrder) ?></a></th>
                            <th><a href="<?= getSortLink('min_ketinggian', $sortColumn, $sortOrder) ?>">Ketinggian (mdpl) <?= getSortIcon('min_ketinggian', $sortColumn, $sortOrder) ?></a></th>
                            <th><a href="<?= getSortLink('min_curah_hujan', $sortColumn, $sortOrder) ?>">Curah Hujan (mm/tahun) <?= getSortIcon('min_curah_hujan', $sortColumn, $sortOrder) ?></a></th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($aturan) > 0): ?>
                            <?php foreach ($aturan as $a): ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['id_aturan']) ?></td>
                                    <td><?= htmlspecialchars($a['id_tanaman']) ?></td>
                                    <td><?= htmlspecialchars($a['nama_tanaman']) ?></td>
                                    <td><?= htmlspecialchars($a['kategori']) ?></td>
                                    <td><?= htmlspecialchars($a['min_ketinggian']) ?> - <?= htmlspecialchars($a['max_ketinggian']) ?></td>
                                    <td><?= htmlspecialchars($a['min_curah_hujan']) ?> - <?= htmlspecialchars($a['max_curah_hujan']) ?></td>
                                    <td>
                                        <a href="#" class="edit-btn" onclick="openEditModal(
                                            <?= htmlspecialchars(json_encode($a)) ?>
                                        ); return false;"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="?delete=<?= htmlspecialchars($a['id_aturan']) ?>" class="delete-btn" onclick="return confirm('Apakah Anda yakin ingin menghapus aturan ini?');"><i class="fas fa-trash-alt"></i> Hapus</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 20px; color: var(--text-muted);">Belum ada data aturan yang sesuai dengan filter.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div id="addAturanModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeAddModal()">&times;</span>
            <h2>Tambah Aturan Baru</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_aturan">

                <label for="add_nama_tanaman">Nama Tanaman</label>
                <input type="text" name="nama_tanaman" id="add_nama_tanaman" placeholder="Masukkan Nama Tanaman" required>

                <label for="add_kategori">Kategori Tanaman</label>
                <select name="kategori" id="add_kategori" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($allCategories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>">
                            <?= htmlspecialchars($category) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="add_min_ketinggian">Min Ketinggian (mdpl)</label>
                <input type="number" name="min_ketinggian" id="add_min_ketinggian" placeholder="Contoh: 0" required min="0">

                <label for="add_max_ketinggian">Max Ketinggian (mdpl)</label>
                <input type="number" name="max_ketinggian" id="add_max_ketinggian" placeholder="Contoh: 1000" required min="0">

                <label for="add_min_curah_hujan">Min Curah Hujan (mm/tahun)</label>
                <input type="number" name="min_curah_hujan" id="add_min_curah_hujan" placeholder="Contoh: 1000" required min="0">

                <label for="add_max_curah_hujan">Max Curah Hujan (mm/tahun)</label>
                <input type="number" name="max_curah_hujan" id="add_max_curah_hujan" placeholder="Contoh: 2500" required min="0">

                <button type="submit" class="btn"><i class="fas fa-plus"></i> Tambah Aturan</button>
            </form>
        </div>
    </div>

    <div id="editAturanModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeEditModal()">&times;</span>
            <h2>Edit Aturan</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_aturan">
                <input type="hidden" name="edit_id_aturan" id="edit_id_aturan">

                <label for="edit_id_tanaman">Pilih Tanaman</label>
                <select name="edit_id_tanaman" id="edit_id_tanaman" required>
                    <option value="">-- Pilih Tanaman --</option>
                    <?php foreach ($allTanaman as $tanaman): ?>
                        <option value="<?= htmlspecialchars($tanaman['id_tanaman']) ?>"
                            data-nama="<?= htmlspecialchars($tanaman['nama_tanaman']) ?>"
                            data-kategori="<?= htmlspecialchars($tanaman['kategori']) ?>">
                            <?= htmlspecialchars($tanaman['nama_tanaman']) ?> (ID: <?= htmlspecialchars($tanaman['id_tanaman']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="edit_nama_tanaman">Nama Tanaman</label>
                <input type="text" name="edit_nama_tanaman" id="edit_nama_tanaman" required>

                <label for="edit_kategori">Kategori Tanaman</label>
                <select name="edit_kategori" id="edit_kategori" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($allCategories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>">
                            <?= htmlspecialchars($category) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="edit_min_ketinggian">Min Ketinggian (mdpl)</label>
                <input type="number" name="edit_min_ketinggian" id="edit_min_ketinggian" required min="0">

                <label for="edit_max_ketinggian">Max Ketinggian (mdpl)</label>
                <input type="number" name="edit_max_ketinggian" id="edit_max_ketinggian" required min="0">

                <label for="edit_min_curah_hujan">Min Curah Hujan (mm/tahun)</label>
                <input type="number" name="edit_min_curah_hujan" id="edit_min_curah_hujan" placeholder="Contoh: 1000" required min="0">

                <label for="edit_max_curah_hujan">Max Curah Hujan (mm/tahun)</label>
                <input type="number" name="edit_max_curah_hujan" id="edit_max_curah_hujan" placeholder="Contoh: 2500" required min="0">

                <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan Perubahan</button>
            </form>
        </div>
    </div>
    <script>
        // PHP variables for message handling
        const jsMessage = "<?= addslashes($js_message) ?>";
        const jsMessageType = "<?= addslashes($js_message_type) ?>";

        var addAturanModal = document.getElementById('addAturanModal');
        var editAturanModal = document.getElementById('editAturanModal');
        // dynamicMessageArea is no longer needed as messages are appended directly to body

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

        document.addEventListener('DOMContentLoaded', function() {
            // Explicitly hide modals on DOMContentLoaded to prevent auto-open
            if (addAturanModal) addAturanModal.style.display = 'none';
            if (editAturanModal) editAturanModal.style.display = 'none';

            const currentPath = window.location.pathname.split('/').pop();
            document.querySelectorAll('.sidebar nav ul li a').forEach(link => {
                const linkPath = link.getAttribute('href').split('/').pop();

                if (linkPath === currentPath) {
                    link.classList.add('active');
                }
            });

            // Add event listener for edit dropdown only
            document.getElementById('edit_id_tanaman').addEventListener('change', function() {
                updateEditTanamanFields();
            });
        });

        function openAddModal() {
            // Reset form fields when opening for adding
            document.getElementById('add_nama_tanaman').value = '';
            document.getElementById('add_kategori').value = '';
            document.getElementById('add_min_ketinggian').value = '';
            document.getElementById('add_max_ketinggian').value = '';
            document.getElementById('add_min_curah_hujan').value = '';
            document.getElementById('add_max_curah_hujan').value = '';
            addAturanModal.style.display = 'flex'; // Use flex to center the modal
        }

        function closeAddModal() {
            addAturanModal.style.display = 'none';
        }

        function openEditModal(aturanData) {
            document.getElementById('edit_id_aturan').value = aturanData.id_aturan;

            // Set the selected option in the dropdown for id_tanaman
            const editIdTanamanSelect = document.getElementById('edit_id_tanaman');
            for (let i = 0; i < editIdTanamanSelect.options.length; i++) {
                if (editIdTanamanSelect.options[i].value == aturanData.id_tanaman) {
                    editIdTanamanSelect.selectedIndex = i;
                    break;
                }
            }

            // Populate other fields based on the selected plant data from the table row
            document.getElementById('edit_nama_tanaman').value = aturanData.nama_tanaman;

            const editKategoriTanamanSelect = document.getElementById('edit_kategori');
            for (let i = 0; i < editKategoriTanamanSelect.options.length; i++) {
                if (editKategoriTanamanSelect.options[i].value === aturanData.kategori) {
                    editKategoriTanamanSelect.selectedIndex = i;
                    break;
                } else {
                    // Fallback if category not found in the options list for some reason
                    editKategoriTanamanSelect.selectedIndex = 0;
                }
            }

            document.getElementById('edit_min_ketinggian').value = aturanData.min_ketinggian;
            document.getElementById('edit_max_ketinggian').value = aturanData.max_ketinggian;
            document.getElementById('edit_min_curah_hujan').value = aturanData.min_curah_hujan;
            document.getElementById('edit_max_curah_hujan').value = aturanData.max_curah_hujan;

            editAturanModal.style.display = 'flex'; // Use flex to center the modal
        }

        // This function updates the nama_tanaman and kategori fields in the edit modal
        // when a different plant ID is selected from the dropdown.
        function updateEditTanamanFields() {
            const selectElement = document.getElementById('edit_id_tanaman');
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const namaInput = document.getElementById('edit_nama_tanaman');
            const kategoriSelect = document.getElementById('edit_kategori');

            if (selectedOption && selectedOption.value) {
                namaInput.value = selectedOption.getAttribute('data-nama');
                const selectedKategori = selectedOption.getAttribute('data-kategori');
                for (let i = 0; i < kategoriSelect.options.length; i++) {
                    if (kategoriSelect.options[i].value === selectedKategori) {
                        kategoriSelect.selectedIndex = i;
                        break;
                    } else {
                        kategoriSelect.selectedIndex = 0; // Fallback if category not found
                    }
                }
            } else {
                namaInput.value = '';
                kategoriSelect.selectedIndex = 0; // Select "-- Pilih Kategori --"
            }
        }


        function closeEditModal() {
            editAturanModal.style.display = 'none';
        }

        // Close the modals if clicked outside of them
        window.onclick = function(event) {
            if (event.target == addAturanModal) {
                addAturanModal.style.display = "none";
            }
            if (event.target == editAturanModal) {
                editAturanModal.style.display = "none";
            }
        }
    </script>
</body>

</html>