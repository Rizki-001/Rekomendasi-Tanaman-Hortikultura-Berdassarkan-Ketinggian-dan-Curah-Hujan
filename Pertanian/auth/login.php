<?php
session_start();

require_once '../lib/db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM user WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Simpan session login
        $_SESSION['user'] = [
            'id_user' => $user['id_user'],
            'username' => $user['username'],
            'role' => $user['role']
        ];

        // Redirect berdasarkan role
        if ($user['role'] === 'admin') {
            header("Location: ../admin/admin.php"); // Mengarahkan ke admin/index.php
        } else {
            header("Location: ../index.php"); // Mengarahkan ke index.php untuk user biasa
        }
        exit;
    } else {
        $error = "Username atau password salah.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GoAgriculture</title>
    <link rel="manifest" href="/manifest.json">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Define your color variables */
        :root {
            --dark-prime: #3D724D; /* Main dark green for header/accents */
            --light-bg: #F5F5F5; /* Very light grey/off-white for body background */
            --main-accent-green: #66BB6A; /* Primary accent green */
            --secondary-accent-green: #4CAF50; /* A slightly darker shade of accent green for hover */
            --text-on-dark: #FFFFFF; /* White text on dark backgrounds */
            --text-on-light: #333333; /* Dark grey text on light backgrounds */
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
            font-family: 'Montserrat', sans-serif; /* Use Montserrat for titles/headings */
            line-height: 1.6;
            background: linear-gradient(135deg, var(--light-bg), #e0e6e4); /* Softer gradient */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-on-light);
            overflow: hidden;
            font-size: 15px;
        }

        .login-container {
            background-color: var(--text-on-dark);
            padding: 40px;
            border-radius: var(--border-radius-main);
            box-shadow: 0 10px 30px var(--shadow-light);
            width: 100%;
            max-width: 420px;
            text-align: center;
            animation: fadeIn 0.8s ease-out;
            position: relative; /* Needed for the decorative top bar */
            overflow: hidden; /* Ensure rounded corners are applied */
        }

        .login-container::before { /* Decorative top bar */
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 10px; /* Thickness of the bar */
            background-color: var(--dark-prime); /* Main brand color */
            border-top-left-radius: var(--border-radius-main);
            border-top-right-radius: var(--border-radius-main);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-container h2 {
            margin-top: 20px; /* Space for the decorative bar */
            margin-bottom: 30px;
            color: var(--dark-prime); /* Use a darker green for the title */
            font-size: 2.4em;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .login-container h2 .fas {
            color: var(--main-accent-green);
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
            position: relative;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-on-light);
            font-weight: 500;
            font-size: 0.95em;
            font-family: 'Roboto', sans-serif; /* Roboto for body text/labels */
        }

        .input-group input[type="text"],
        .input-group input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            padding-left: 50px; /* Increased space for icon */
            border: 1px solid #ddd;
            border-radius: var(--border-radius-sm);
            font-size: 1em;
            color: var(--text-on-light);
            background-color: var(--light-bg); /* Use light-bg for input fields */
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            font-family: 'Roboto', sans-serif;
        }

        .input-group input[type="text"]:focus,
        .input-group input[type="password"]:focus {
            border-color: var(--main-accent-green); /* Green border on focus */
            box-shadow: 0 0 0 3px rgba(102, 187, 106, 0.2); /* Green shadow */
            outline: none;
        }

        .input-group .icon {
            position: absolute;
            left: 18px; /* Adjusted icon position */
            top: 50%;
            transform: translateY(50%); /* Adjust for padding-top of input */
            color: var(--dark-prime); /* Green icon color */
            font-size: 1.2em; /* Slightly larger icon */
        }
        .input-group:nth-of-type(1) .icon {
            top: 50%; /* Adjusted for username input */
            transform: translateY(50%);
        }

        .input-group:nth-of-type(2) .icon {
            top: 50%; /* Adjusted for password input */
            transform: translateY(50%);
        }

        .btn-login {
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

        .btn-login:hover {
            background-color: var(--secondary-accent-green);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .error-message {
            color: var(--accent-red);
            background-color: rgba(211, 47, 47, 0.1); /* Lighter red background */
            border: 1px solid var(--accent-red);
            padding: 10px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 20px;
            font-size: 0.9em;
            animation: slideDown 0.4s ease-out;
            font-family: 'Roboto', sans-serif;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .register-link {
            margin-top: 25px;
            font-size: 0.95em;
            color: var(--text-muted); /* Softer grey for muted text */
            font-family: 'Roboto', sans-serif;
        }

        .register-link a {
            color: var(--accent-blue);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            color: #64B5F6; /* Lighter shade of blue on hover */
            text-decoration: underline;
        }

        /* Responsive adjustments */
        @media (max-width: 500px) {
            .login-container {
                margin: 20px;
                padding: 30px;
            }
            .login-container::before {
                height: 8px; /* Slightly thinner on small screens */
            }
            .login-container h2 {
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
            .btn-login {
                font-size: 1em;
                padding: 12px 18px;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h2><i class="fas fa-leaf"></i> GoAgriculture</h2>
        <?php if ($error): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="input-group">
                <i class="fas fa-user icon"></i>
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Masukkan username Anda" required autocomplete="username">
            </div>
            <div class="input-group">
                <i class="fas fa-lock icon"></i>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Masukkan password Anda" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login">Login</button>
        </form>
        <p class="register-link">Belum punya akun? <a href="register.php">Daftar di sini</a></p>
    </div>
</body>

</html>