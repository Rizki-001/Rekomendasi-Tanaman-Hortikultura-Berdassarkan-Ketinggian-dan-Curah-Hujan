<?php
session_start();
require_once __DIR__ . '/../lib/db.php';

// Check for user session, relying on the 'user' array which should contain 'id_user' and 'username'
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id_user'])) {
    header("Location: ../auth/login.php");
    exit();
}

$userId = $_SESSION['user']['id_user'];
$username = $_SESSION['user']['username'] ?? 'Pengguna'; // Default if username somehow not set
$user_role = $_SESSION['user']['role'] ?? 'user'; // Dapatkan role user, asumsi ini ada di session

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUsername = trim($_POST['username'] ?? ''); // Trim whitespace
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';

    // Basic validation for new username
    if (empty($newUsername)) {
        $error = "Username tidak boleh kosong.";
    } else {
        try {
            // Ambil data user dari DB
            $stmt = $conn->prepare("SELECT * FROM user WHERE id_user = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // If oldPassword is provided, validate it first
                if (!empty($oldPassword)) {
                    if (!password_verify($oldPassword, $user['password'])) {
                        $error = "Password lama salah.";
                    }
                } else if (!empty($newPassword)) {
                    // If new password is provided but old password is not,
                    // inform user that old password is required to change password
                    $error = "Untuk mengubah password, Anda harus memasukkan Password Lama.";
                }

                // Proceed with update only if no error occurred so far
                if (empty($error)) {
                    $query = "UPDATE user SET username = ?";
                    $params = [$newUsername];

                    if (!empty($newPassword)) {
                        // Validate new password complexity if needed (e.g., minimum length)
                        if (strlen($newPassword) < 6) { // Example: minimum 6 characters
                            $error = "Password baru minimal 6 karakter.";
                        } else {
                            $query .= ", password = ?";
                            $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
                        }
                    }

                    if (empty($error)) { // Only execute if no new error from password validation
                        $query .= " WHERE id_user = ?";
                        $params[] = $userId;

                        $update = $conn->prepare($query);
                        $update->execute($params);

                        // Update session username if it was changed
                        $_SESSION['user']['username'] = $newUsername;
                        $success = "Profil berhasil diperbarui.";
                        $username = $newUsername; // Update the variable for current display
                    }
                }
            } else {
                $error = "User tidak ditemukan."; // Should ideally not happen if session is valid
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan: " . $e->getMessage();
            // In a production environment, log $e->getMessage() but show a generic error to the user
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Profil Pengguna - GoAgriculture</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="/manifest.json">
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

        /* Hide user info from sidebar (original HTML had it, but navbar has it now) */
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
            transition: margin-left 0.3s ease;
            /* Hanya transisi margin-left */
            /* Lebar default adalah sisa dari viewport dikurangi sidebar ciut */
            width: calc(100% - var(--sidebar-width-closed));
        }

        /* Saat sidebar di-hover, geser main-content */
        .sidebar:hover + .main-content {
            margin-left: var(--sidebar-width-open);
            /* Lebar disesuaikan saat sidebar terbuka */
            width: calc(100% - var(--sidebar-width-open));
        }

        /* Header Bar */
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

        /* Form Container */
        .container {
            max-width: 600px;
            margin: 0 auto 25px auto; /* Centered, with margin-bottom for footer */
            background-color: var(--text-on-dark);
            padding: 30px;
            border-radius: var(--border-radius-main);
            box-shadow: 0 5px 15px var(--shadow-light);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .container h2 {
            font-family: 'Montserrat', sans-serif;
            color: var(--dark-prime);
            text-align: center;
            margin-bottom: 25px;
            font-size: 2em;
            font-weight: 600;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        label {
            font-weight: 600;
            color: var(--dark-prime);
            margin-bottom: 5px;
            display: block;
            font-family: 'Roboto', sans-serif;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px 15px;
            border-radius: var(--border-radius-sm);
            border: 1px solid #ddd;
            font-size: 1em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            font-family: 'Roboto', sans-serif;
            color: var(--text-on-light);
            background-color: #fcfcfc;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: var(--main-accent-green);
            box-shadow: 0 0 0 3px rgba(var(--main-accent-green-rgb), 0.2);
            outline: none;
        }

        button[type="submit"] {
            background-color: var(--main-accent-green);
            color: var(--text-on-dark);
            padding: 12px 25px;
            border: none;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1em;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.2s ease;
            margin-top: 10px;
            font-family: 'Montserrat', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        button[type="submit"]:hover {
            background-color: var(--secondary-accent-green);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        /* Alert Messages */
        .alert {
            padding: 12px 20px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 20px;
            font-weight: 500;
            text-align: center;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: 'Roboto', sans-serif;
            opacity: 1;
            transition: opacity 0.5s ease-out;
        }

        .alert.fade-out {
            opacity: 0;
        }

        .success {
            background-color: #e8f5e9; /* Light green background */
            color: #2A5234; /* Dark green text */
            border-color: #a5d6a7; /* Muted green border */
        }

        .error {
            background-color: #ffebee; /* Light red background */
            color: #c62828; /* Dark red text */
            border-color: #ef9a9a; /* Muted red border */
        }

        .alert i {
            font-size: 1.2em;
            flex-shrink: 0;
        }

        /* Footer */
        footer {
            margin-top: auto; /* Push to the bottom */
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

            .navbar {
                padding: 10px 20px;
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

            .header-bar {
                padding: 15px 20px;
            }

            .header-bar h1 {
                font-size: 1.8em;
            }

            .container {
                padding: 20px;
                margin: 0 auto 20px auto;
            }

            button[type="submit"] {
                padding: 10px 20px;
                font-size: 1em;
            }
        }

        @media screen and (max-width: 768px) {
            .navbar {
                flex-direction: column;
                height: auto;
                position: relative; /* Kembali ke relative agar tidak menutupi konten */
                padding: 10px 20px;
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

            .container {
                margin: 15px;
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
                    <li><a href="riwayat.php"><i class="fas fa-history"></i> <span>Riwayat</span></a></li>
                    <li><a href="profil.php" class="active"><i class="fas fa-user-circle"></i> <span>Profil</span></a></li>
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
                <h1>Profil Pengguna</h1>
                <p class="description">Kelola informasi akun Anda dan ubah kata sandi.</p>
            </div>

            <div class="container">
                <h2>Pengaturan Profil</h2>

                <?php if ($success): ?>
                    <div id="successMessage" class="alert success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
                <?php elseif ($error): ?>
                    <div id="errorMessage" class="alert error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
                <?php endif; ?>

                <form method="post">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" required value="<?= htmlspecialchars($username) ?>">

                    <label for="old_password">Password Lama (isi jika ingin mengubah password)</label>
                    <input type="password" name="old_password" id="old_password" placeholder="Kosongkan jika tidak ingin mengubah password">

                    <label for="new_password">Password Baru (isi jika ingin mengubah password)</label>
                    <input type="password" name="new_password" id="new_password" placeholder="Kosongkan jika tidak ingin mengubah password">

                    <button type="submit"><i class="fas fa-save"></i> Simpan Perubahan</button>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Fungsi untuk menyembunyikan pesan
        function hideMessage(elementId) {
            const messageElement = document.getElementById(elementId);
            if (messageElement) {
                messageElement.classList.add('fade-out');
                messageElement.addEventListener('transitionend', function() {
                    if (messageElement.classList.contains('fade-out')) {
                        messageElement.style.display = 'none';
                    }
                }, { once: true });
            }
        }

        // Panggil fungsi hideMessage setelah 3 detik (3000 milidetik) untuk pesan sukses
        const successMsg = document.getElementById('successMessage');
        if (successMsg) {
            setTimeout(function() {
                hideMessage('successMessage');
            }, 3000);
        }

        // Panggil fungsi hideMessage setelah 5 detik untuk pesan error
        const errorMsg = document.getElementById('errorMessage');
        if (errorMsg) {
            setTimeout(function() {
                hideMessage('errorMessage');
            }, 5000);
        }

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

        // Script untuk menandai menu aktif
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname.split('/').pop();
            document.querySelectorAll('.sidebar nav ul li a').forEach(link => {
                const linkPath = link.getAttribute('href').split('/').pop();

                // Spesial handling untuk link Logout agar tidak ditandai sebagai 'active'
                if (link.closest('.logout-button-container')) {
                    return; // Skip logout button
                }

                // Cek apakah ada parameter detail_id, jika iya, tetap tandai riwayat.php sebagai aktif
                if (currentPath.startsWith('profil.php') && linkPath === 'profil.php') {
                    link.classList.add('active');
                } else if (linkPath === currentPath) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>