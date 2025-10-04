<?php
// === admin/profil.php ===
session_start();
require_once '../lib/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$admin = $_SESSION['user'];

// Fungsi helper untuk redirect dengan pesan
function redirectWithUrlMessage($message, $type) {
    $encodedMessage = urlencode($message);
    header("Location: load_profil.php?message={$encodedMessage}&type={$type}");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username)) {
        redirectWithUrlMessage("Username tidak boleh kosong.", "error");
    } else {
        try {
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE user SET username = ?, password = ? WHERE id_user = ?");
                $stmt->execute([$username, $hashedPassword, $admin['id_user']]);
            } else {
                $stmt = $conn->prepare("UPDATE user SET username = ? WHERE id_user = ?");
                $stmt->execute([$username, $admin['id_user']]);
            }

            // Update session data
            $_SESSION['user']['username'] = $username;

            redirectWithUrlMessage("Profil berhasil diperbarui.", "success");
        } catch (PDOException $e) {
            error_log("Error updating admin profile: " . $e->getMessage()); // Log the error for debugging
            redirectWithUrlMessage("Gagal memperbarui profil: " . $e->getMessage(), "error");
        }
    }
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
    <title>Profil Admin - GoAgriculture</title>
    <link rel="manifest" href="/manifest.json">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* --- Variabel CSS Global --- */
        :root {
            /* Warna Dasar & Latar Belakang */
            --dark-prime: #3D724D; /* Main dark green for navbar, header */
            --light-bg: #F5F5F5; /* Very light grey/off-white for body background, cards */
            --sidebar-bg: #315F40; /* Slightly deeper green for sidebar */

            /* Warna Aksen */
            --main-accent-green: #66BB6A; /* Primary accent green (e.g., active links, card icons, numbers) */
            --secondary-accent-green: #4CAF50; /* A slightly darker shade of accent green for hover/contrast */
            --accent-blue: #2196F3; /* Keeping blue for general links */
            --accent-red: #D32F2F; /* Deep red for danger */

            /* Warna Teks */
            --text-on-dark: #FFFFFF; /* White text on dark backgrounds */
            --text-on-light: #333333; /* Dark grey text on light backgrounds */
            --text-muted: #666666; /* Muted grey for secondary text on light backgrounds */

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
            color: #64B5F6; /* Lighter shade of blue on hover */
        }

        ul {
            list-style: none;
        }

        h1, h2, h3, h4, h5, h6 {
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
            padding-top: 70px; /* Sesuaikan dengan tinggi navbar */
            min-height: 100vh;
        }

        /* --- Sidebar Styling --- */
        .sidebar {
            width: 80px; /* Lebar default saat tersembunyi */
            background-color: var(--sidebar-bg);
            color: var(--text-on-dark);
            padding: 20px 0;
            box-shadow: 2px 0 15px var(--shadow-light);
            position: sticky;
            top: 70px; /* Jaga agar sidebar di bawah navbar */
            height: calc(100vh - 70px); /* Penuh tinggi viewport dikurangi navbar */
            overflow: hidden; /* Sembunyikan teks yang melampaui batas */
            z-index: 999;
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* Untuk meletakkan logout di bawah */
            transition: width var(--transition-speed) ease; /* Transisi untuk perubahan lebar */
        }

        .sidebar:hover {
            width: 250px; /* Lebar saat di-hover */
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
        /* --- Sidebar Header --- */
        .sidebar-header {
            text-align: center;
            margin-bottom: 0; /* Default: tanpa margin bawah */
            padding: 20px 0; /* Default: padding lebih sedikit */
            border-bottom: none; /* Default: tanpa border bawah */
            display: flex;
            flex-direction: column;
            align-items: center; /* Pusatkan konten saat tersembunyi */
            transition: all var(--transition-speed) ease; /* Transisi untuk semua properti */
        }

        .sidebar:hover .sidebar-header {
            margin-bottom: 30px; /* Kembalikan margin saat hover */
            padding: 0 20px 20px; /* Kembalikan padding saat hover */
            border-bottom: 1px solid var(--border-dark); /* Kembalikan border saat hover */
        }

        .sidebar-header .logo img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 0; /* Default: tanpa margin bawah logo */
            border: 3px solid var(--main-accent-green);
            transition: margin-bottom var(--transition-speed) ease;
        }

        .sidebar:hover .sidebar-header .logo img {
            margin-bottom: 10px; /* Kembalikan margin saat hover */
        }

        .sidebar-header h3,
        .sidebar-header p {
            opacity: 0; /* Sembunyikan teks secara default */
            height: 0; /* Collapse tinggi untuk mencegah mempengaruhi layout */
            overflow: hidden; /* Pastikan teks benar-benar tersembunyi */
            transition: opacity var(--transition-speed) ease, height var(--transition-speed) ease, margin var(--transition-speed) ease;
            white-space: nowrap; /* Mencegah teks terpotong */
            pointer-events: none; /* Mencegah teks diklik saat tersembunyi */
        }

        .sidebar:hover .sidebar-header h3,
        .sidebar:hover .sidebar-header p {
            opacity: 1; /* Tampilkan teks saat hover */
            height: auto; /* Kembalikan tinggi saat hover */
            pointer-events: auto; /* Jadikan bisa diklik kembali */
        }
        .sidebar:hover .sidebar-header h3 { margin-top: 10px; }
        .sidebar:hover .sidebar-header p { margin-top: 0; }

        /* --- Sidebar Navigasi --- */
        .sidebar-nav ul {
            flex-grow: 1; /* Biarkan daftar navigasi mengisi ruang yang tersedia */
        }

        .sidebar-nav ul li {
            margin-bottom: 5px;
        }

        .sidebar-nav ul li a {
            display: flex;
            align-items: center;
            padding: 12px 0; /* Padding horizontal lebih sedikit saat tersembunyi */
            justify-content: center; /* Pusatkan ikon */
            color: var(--text-on-dark);
            border-left: 4px solid transparent;
            transition: all var(--transition-speed) ease;
            font-weight: 500;
        }

        .sidebar:hover .sidebar-nav ul li a {
            padding: 12px 25px; /* Kembalikan padding penuh saat hover */
            justify-content: flex-start; /* Sejajarkan ke kiri saat hover */
        }

        .sidebar-nav ul li a i {
            margin-right: 0; /* Tanpa margin kanan default */
            font-size: 1.1em;
            color: var(--text-on-dark);
            transition: margin-right var(--transition-speed) ease, color var(--transition-speed) ease;
        }

        .sidebar:hover .sidebar-nav ul li a i {
            margin-right: 12px; /* Kembalikan margin kanan saat hover */
        }

        /* Sembunyikan teks di dalam <span> secara default */
        .sidebar-nav ul li a span {
            opacity: 0;
            width: 0; /* Sembunyikan lebar teks */
            overflow: hidden;
            white-space: nowrap;
            transition: opacity var(--transition-speed) ease, width var(--transition-speed) ease;
            display: inline-block; /* Penting untuk transisi lebar */
            margin-left: -5px; /* Sesuaikan untuk menarik teks lebih dekat ke ikon saat tersembunyi */
        }

        /* Tampilkan teks saat sidebar di-hover */
        .sidebar:hover .sidebar-nav ul li a span {
            opacity: 1;
            width: auto; /* Kembalikan lebar natural teks */
            margin-left: 0; /* Reset margin */
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
            padding: 20px 0; /* Padding horizontal lebih sedikit saat tersembunyi */
            border-top: 1px solid var(--border-dark);
            text-align: center;
            transition: padding var(--transition-speed) ease;
        }

        .sidebar:hover .sidebar-footer {
            padding: 20px 25px; /* Kembalikan padding penuh saat hover */
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .sidebar-logout-btn i {
            color: inherit;
        }

        .sidebar-logout-btn:hover {
            background-color: var(--main-accent-green);
            color: var(--text-on-dark);
            border-color: var(--main-accent-green);
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
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

        /* --- Form Container Styling --- */
        .form-container {
            background-color: var(--text-on-dark); /* Menggunakan variabel putih */
            padding: 30px;
            border-radius: var(--border-radius-main); /* Menggunakan variabel radius */
            max-width: 500px;
            margin: auto;
            box-shadow: 0 4px 10px var(--shadow-light); /* Menggunakan variabel shadow */
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: var(--text-on-light); /* Menggunakan variabel teks gelap */
        }

        label {
            font-weight: bold;
            display: block;
            margin-bottom: 6px;
            color: var(--text-on-light); /* Menggunakan variabel teks gelap */
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: var(--border-radius-sm); /* Menggunakan variabel radius */
            border: 1px solid var(--border-light); /* Menggunakan variabel border */
            background-color: var(--light-bg); /* Latar belakang input */
            color: var(--text-on-light); /* Warna teks input */
        }

        .btn {
            background-color: var(--main-accent-green); /* Menggunakan variabel hijau aksen */
            color: var(--text-on-dark); /* Menggunakan variabel teks putih */
            padding: 10px 15px;
            border: none;
            border-radius: var(--border-radius-sm); /* Menggunakan variabel radius */
            cursor: pointer;
            width: 100%;
            font-size: 1em;
            transition: background-color var(--transition-speed) ease; /* Tambah transisi */
        }

        .btn:hover {
            background-color: var(--secondary-accent-green); /* Warna hover hijau aksen */
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
        
        .back-link {
            display: block;
            margin-top: 25px; /* Added margin to separate from button */
            text-align: center;
            font-size: 0.9em;
            color: var(--accent-blue); /* Consistent link color */
            transition: color var(--transition-speed) ease;
        }

        .back-link:hover {
            color: #64B5F6; /* Lighter blue on hover */
        }
        .back-link i {
            margin-right: 5px;
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

            /* Sidebar pada layar kecil akan selalu terbuka/lebar */
            .wrapper {
                flex-direction: column; /* Stack sidebar and main content vertically */
                padding-top: 80px; /* Adjust for potentially taller navbar */
            }

            .sidebar {
                width: 100%; /* Sidebar will take full width */
                height: auto; /* Height adapts to content */
                position: static; /* Remove sticky behavior */
                padding: 15px 0;
                box-shadow: 0 1px 10px var(--shadow-light);
                top: auto;
                flex-direction: column;
                /* Disable hover effects for full-width sidebar */
                transition: none;
            }
            .sidebar:hover { /* Override hover effect for smaller screens */
                width: 100%;
            }

            /* Sidebar Header for smaller screens */
            .sidebar-header {
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid var(--border-dark); /* Ensure border appears */
                transition: none; /* Disable transition */
            }
            .sidebar:hover .sidebar-header { /* Ensure these styles are active when sidebar is full width */
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid var(--border-dark);
            }
            .sidebar-header .logo img {
                margin-bottom: 10px; /* Ensure margin appears */
                transition: none;
            }
            .sidebar:hover .sidebar-header .logo img { /* Ensure these styles are active */
                margin-bottom: 10px;
            }
            .sidebar-header h3,
            .sidebar-header p {
                opacity: 1; /* Show text */
                height: auto; /* Restore height */
                pointer-events: auto; /* Make clickable */
                transition: none; /* Disable transition */
            }
            .sidebar:hover .sidebar-header h3,
            .sidebar:hover .sidebar-header p { /* Ensure these styles are active */
                opacity: 1;
                height: auto;
                pointer-events: auto;
            }

            /* Sidebar Navigation for smaller screens */
            .sidebar-nav ul {
                display: flex;
                flex-wrap: wrap; /* Allow items to wrap */
                justify-content: center; /* Center nav items */
                gap: 5px; /* Small gap between items */
            }

            .sidebar-nav ul li {
                margin-bottom: 0; /* Remove vertical margin */
            }

            .sidebar-nav ul li a {
                padding: 10px 15px;
                font-size: 0.9em;
                justify-content: center; /* Keep centered on mobile */
                transition: none; /* Disable transition */
            }
            .sidebar:hover .sidebar-nav ul li a { /* Ensure these styles are active */
                padding: 10px 15px;
                justify-content: center;
            }

            .sidebar-nav ul li a i {
                margin-right: 8px; /* Show icon margin */
                transition: none;
            }
            .sidebar:hover .sidebar-nav ul li a i { /* Ensure these styles are active */
                margin-right: 8px;
            }

            .sidebar-nav ul li a span {
                opacity: 1; /* Show text */
                width: auto; /* Expand text width */
                margin-left: 0; /* Reset margin */
                transition: none; /* Disable transition */
            }
            .sidebar:hover .sidebar-nav ul li a span { /* Ensure these styles are active */
                opacity: 1;
                width: auto;
                margin-left: 0;
            }

            /* Sidebar footer on medium screens */
            .sidebar-footer {
                margin-top: 15px;
                padding: 15px 20px;
                transition: none;
            }
            .sidebar:hover .sidebar-footer { /* Ensure these styles are active */
                padding: 15px 20px;
            }
            .sidebar-logout-btn {
                width: 45px;
                height: 45px;
                font-size: 1.2em;
            }

            .main-content {
                padding: 20px;
            }

            .form-container {
                padding: 20px;
            }
            h2 {
                font-size: 1.5em;
            }
        }

        @media (max-width: 576px) {
            .navbar {
                flex-direction: column;
                align-items: flex-start;
                padding-bottom: 10px;
                height: auto; /* Allow navbar height to adjust */
            }
            .navbar .user-info {
                margin-top: 10px;
                width: 100%;
                justify-content: space-between;
            }
            .wrapper {
                padding-top: 100px; /* Adjust for potentially taller navbar on small screens */
            }

            /* Sidebar Navigation on very small screens */
            .sidebar-nav ul {
                flex-direction: column; /* Stack nav items vertically */
                align-items: center;
            }
            .sidebar-nav ul li {
                width: 100%;
                text-align: center;
            }
            .sidebar-nav ul li a {
                justify-content: center;
            }
            .sidebar-nav ul li a i {
                margin-right: 8px;
            }

            /* Sidebar footer on small screens */
            .sidebar-footer {
                padding: 15px 15px;
            }
            .sidebar-logout-btn {
                width: 40px;
                height: 40px;
                font-size: 1.1em;
            }

            .content-header h1 {
                font-size: 1.8em;
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
                    <li><a href="load_pengguna.php"><i class="fas fa-users"></i> <span>Pengguna</span></a></li>
                    <li><a href="load_profil.php" class="active"><i class="fas fa-user-circle"></i> <span>Profil Admin</span></a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="../auth/logout.php" class="sidebar-logout-btn"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </aside>

        <main class="main-content">
            <div class="content-header">
                <h1>Profil Administrator</h1>
            </div>

            <div class="form-container">
                <h2>Edit Profil Admin</h2>

                <form method="post" action="">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" value="<?= htmlspecialchars($admin['username']) ?>" required>

                    <label for="password">Password Baru</label>
                    <input type="password" name="password" id="password" placeholder="Kosongkan jika tidak ingin mengubah password">

                    <button type="submit" class="btn">Simpan Perubahan</button>
                </form>
            </div>
        </main>
    </div>

    <script>
        // PHP variables for message handling
        const jsMessage = "<?= addslashes($js_message) ?>";
        const jsMessageType = "<?= addslashes($js_message_type) ?>";

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

        // Set active sidebar link on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
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
