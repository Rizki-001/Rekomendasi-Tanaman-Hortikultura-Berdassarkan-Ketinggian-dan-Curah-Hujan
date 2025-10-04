<?php
$host = 'localhost';
$dbname = 'hortikultura_db';
$user = 'root'; // ganti kalau pakai password
$pass = '';     // jika ada password, isikan

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}
