<?php
require_once '../lib/db.php';

// Initialize error variable
$error = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    // Role is now automatically 'user'
    $role = 'user'; 

    // Validasi input
    if (empty($username) || empty(trim($_POST['password']))) {
        $error = "Username dan Password tidak boleh kosong.";
    } else if (strlen(trim($_POST['password'])) < 6) { // Example: password minimum length
        $error = "Password minimal 6 karakter.";
    } else if (!preg_match("/^[a-zA-Z0-9_]+$/", $username)) { // Example: username alphanumeric
        $error = "Username hanya boleh mengandung huruf, angka, dan underscore.";
    }
    // No need to validate role as it's hardcoded
    else {
        // Cek apakah username sudah digunakan
        try {
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM user WHERE username = ?");
            $stmt_check->execute([$username]);
            $user_exists = $stmt_check->fetchColumn();

            if ($user_exists > 0) {
                $error = "Username sudah digunakan. Silakan pilih username lain.";
            } else {
                // Simpan ke database dengan role 'user'
                $stmt = $conn->prepare("INSERT INTO user (username, password, role, created_at) VALUES (?, ?, ?, NOW())");
                if ($stmt->execute([$username, $password, $role])) {
                    header("Location: login.php?registered=true");
                    exit();
                } else {
                    $error = "Terjadi kesalahan saat mendaftar.";
                }
            }
        } catch (PDOException $e) {
            error_log("Error during registration: " . $e->getMessage()); // Log the error
            $error = "Terjadi kesalahan database. Silakan coba lagi nanti.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - GoAgriculture</title>
    <link rel="manifest" href="/manifest.json">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Define your color variables (consistent with login.php) */
        :root {
            --dark-prime: #3D724D; /* Main dark green for header/accents */
            --light-bg: #F5F5F5; /* Very light grey/off-white for body background */
            --main-accent-green: #66BB6A; /* Primary accent green */
            --secondary-accent-green: #4CAF50; /* A slightly darker shade of accent green for hover */
            --text-on-dark: #FFFFFF; /* White text on dark backgrounds */
            --text-on-light: #333333; /* Dark grey text on light backgrounds */
            --text-muted: #666666; /* Muted grey for secondary text */
            --accent-blue: #2196F3; /* Standard blue for links */
            --accent-red: #D32F2F; /* Deep red for errors */
            --shadow-light: rgba(0, 0, 0, 0.1);
            --border-radius-main: 8px;
            --border-radius-sm: 4px;
        }

        /* Global Reset & Box Sizing */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            line-height: 1.6;
            background: linear-gradient(135deg, var(--light-bg), #e0e6e4);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-on-light);
            overflow: hidden;
            font-size: 15px;
        }

        .register-container {
            background-color: var(--text-on-dark);
            padding: 40px;
            border-radius: var(--border-radius-main);
            box-shadow: 0 10px 30px var(--shadow-light);
            width: 100%;
            max-width: 420px;
            text-align: center;
            animation: fadeIn 0.8s ease-out;
            position: relative;
            overflow: hidden;
        }

        .register-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 10px;
            background-color: var(--dark-prime);
            border-top-left-radius: var(--border-radius-main);
            border-top-right-radius: var(--border-radius-main);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h2 {
            margin-top: 20px;
            margin-bottom: 30px;
            color: var(--dark-prime);
            font-size: 2.4em;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        h2 .fas {
            color: var(--main-accent-green);
        }

        .input-group {
            margin-bottom: 20px;
            position: relative;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-on-light);
            font-weight: 500;
            font-size: 0.95em;
            font-family: 'Roboto', sans-serif;
        }

        .input-group input {
            width: 100%;
            padding: 12px 15px;
            padding-left: 50px; /* Increased space for icon */
            border: 1px solid #ddd;
            border-radius: var(--border-radius-sm);
            font-size: 1em;
            color: var(--text-on-light);
            background-color: var(--light-bg);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            font-family: 'Roboto', sans-serif;
        }
        
        /* Removed select specific styling as the select input is removed */

        .input-group input:focus {
            border-color: var(--main-accent-green);
            box-shadow: 0 0 0 3px rgba(102, 187, 106, 0.2);
            outline: none;
        }

        .input-group .icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(50%); /* Adjust for padding-top of input/select */
            color: var(--dark-prime);
            font-size: 1.2em;
            pointer-events: none; /* Make icon unclickable */
        }
        /* Specific top adjustments for icons inside .input-group */
        .input-group:nth-of-type(1) .icon { /* Username */
            transform: translateY(50%);
        }
        .input-group:nth-of-type(2) .icon { /* Password */
            transform: translateY(50%);
        }
        /* Removed .input-group:nth-of-type(3) .icon as role select is removed */

        .btn-register {
            width: 100%;
            padding: 14px 20px;
            background-color: var(--main-accent-green);
            color: var(--text-on-dark);
            border: none;
            border-radius: var(--border-radius-sm);
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            margin-top: 10px;
            letter-spacing: 0.5px;
        }

        .btn-register:hover {
            background-color: var(--secondary-accent-green);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .error-message, .success-message {
            padding: 10px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 20px;
            font-size: 0.9em;
            animation: slideDown 0.4s ease-out;
            font-family: 'Roboto', sans-serif;
            font-weight: 500;
            text-align: center;
        }

        .error-message {
            background-color: rgba(211, 47, 47, 0.1);
            color: var(--accent-red);
            border: 1px solid var(--accent-red);
        }

        .success-message {
            background-color: rgba(102, 187, 106, 0.1);
            color: var(--main-accent-green);
            border: 1px solid var(--main-accent-green);
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-link {
            margin-top: 25px;
            font-size: 0.95em;
            color: var(--text-muted);
            font-family: 'Roboto', sans-serif;
        }

        .login-link a {
            color: var(--accent-blue);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: #64B5F6;
            text-decoration: underline;
        }

        /* Responsive adjustments */
        @media (max-width: 500px) {
            .register-container {
                margin: 20px;
                padding: 30px;
            }
            .register-container::before {
                height: 8px;
            }
            h2 {
                font-size: 2em;
                margin-top: 15px;
            }
            .input-group input {
                padding: 10px 15px;
                padding-left: 45px;
            }
            .input-group .icon {
                font-size: 1.1em;
            }
            .btn-register {
                font-size: 1em;
                padding: 12px 18px;
            }
        }
    </style>
</head>

<body>
    <div class="register-container">
        <h2><i class="fas fa-leaf"></i> Daftar Akun</h2>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['registered']) && $_GET['registered'] == 'true'): ?>
            <div class="success-message">Pendaftaran berhasil! Silakan login.</div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <i class="fas fa-user icon"></i>
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Masukkan username Anda" required autocomplete="new-username">
            </div>

            <div class="input-group">
                <i class="fas fa-lock icon"></i>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Buat password" required autocomplete="new-password">
            </div>

            <!-- Removed the role selection input -->
            <!--
            <div class="input-group">
                <i class="fas fa-user-tag icon"></i>
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="" disabled selected>Pilih Role</option>
                    <option value="user">User (Petani)</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            -->

            <button type="submit" class="btn-register">Daftar</button>
        </form>

        <p class="login-link">Sudah punya akun? <a href="login.php">Login di sini</a></p>
    </div>
</body>
</html>
