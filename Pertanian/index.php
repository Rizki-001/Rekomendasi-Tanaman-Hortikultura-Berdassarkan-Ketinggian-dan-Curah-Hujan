<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user'])) {
    header("Location: /auth/login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Memuat Lokasi - GoAgriculture</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="/manifest.json">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* Define your color variables (consistent with other pages) */
        :root {
            --dark-prime: #3D724D; /* Main dark green for header/accents */
            --light-bg: #F5F5F5; /* Very light grey/off-white for body background */
            --main-accent-green: #66BB6A; /* Primary accent green */
            --text-on-dark: #FFFFFF; /* White text on dark backgrounds */
            --text-on-light: #333333; /* Dark grey text on light backgrounds */
            --shadow-light: rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, var(--light-bg), #e0e6e4);
            color: var(--text-on-light);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            flex-direction: column;
            margin: 0;
            overflow: hidden; /* Prevent scrolling */
        }

        .loading-container {
            background-color: var(--text-on-dark);
            padding: 50px;
            border-radius: 12px;
            box-shadow: 0 8px 25px var(--shadow-light);
            text-align: center;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .spinner {
            border: 8px solid rgba(0, 0, 0, 0.1);
            border-top: 8px solid var(--main-accent-green);
            border-radius: 50%;
            width: 70px;
            height: 70px;
            animation: spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite; /* More sophisticated spin */
            margin: 0 auto 30px auto; /* Center spinner and add margin below */
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.2em;
            color: var(--dark-prime);
            margin-bottom: 15px;
            font-weight: 700;
        }

        p {
            font-family: 'Roboto', sans-serif;
            font-size: 1.1em;
            color: var(--text-on-light);
            line-height: 1.5;
            max-width: 300px;
            margin: 0 auto;
        }

        /* Responsive adjustments */
        @media (max-width: 600px) {
            .loading-container {
                padding: 30px;
                margin: 20px; /* Add margin for smaller screens */
            }
            .spinner {
                width: 60px;
                height: 60px;
                border-width: 6px;
                margin-bottom: 25px;
            }
            h1 {
                font-size: 1.8em;
            }
            p {
                font-size: 1em;
            }
        }
    </style>
</head>

<body>

    <div class="loading-container">
        <h1>Memuat Lokasi</h1>
        <div class="spinner"></div>
        <p>Mohon tunggu, kami sedang mengambil data lokasi Anda untuk memberikan rekomendasi terbaik.</p>
    </div>

    <script>
        // Register service worker jika ada
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/service-worker.js')
                .then(() => console.log("✅ Service Worker Registered"))
                .catch((err) => console.error("❌ SW registration failed:", err));
        }

        // Timeout jika lokasi gagal diambil
        let timeout = setTimeout(() => {
            alert("Waktu untuk mendapatkan lokasi habis. Anda akan dialihkan tanpa data lokasi.");
            window.location.href = "/user/dashboard.php";
        }, 10000);
        
        // Ambil lokasi dari browser
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                clearTimeout(timeout);
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;

                // Redirect ke dashboard dengan koordinat
                window.location.href = `/user/dashboard.php?lat=${lat}&lng=${lng}`;
            },
            function(err) {
                clearTimeout(timeout);
                let errorMessage = "Gagal mendapatkan lokasi: " + err.message + ". Anda akan dialihkan tanpa data lokasi.";
                // Custom messages for common errors
                if (err.code === err.PERMISSION_DENIED) {
                    errorMessage = "Anda menolak izin lokasi. Anda akan dialihkan tanpa data lokasi.";
                } else if (err.code === err.POSITION_UNAVAILABLE) {
                    errorMessage = "Lokasi tidak tersedia. Anda akan dialihkan tanpa data lokasi.";
                } else if (err.code === err.TIMEOUT) {
                    errorMessage = "Permintaan lokasi waktu habis. Anda akan dialihkan tanpa data lokasi.";
                }
                alert(errorMessage);
                window.location.href = "/user/dashboard.php";
            },
            {
                enableHighAccuracy: true, // Request high accuracy if available
                timeout: 8000,           // Give it 8 seconds to get a position
                maximumAge: 0            // Do not use cached positions
            }
        );
    </script>

</body>

</html>