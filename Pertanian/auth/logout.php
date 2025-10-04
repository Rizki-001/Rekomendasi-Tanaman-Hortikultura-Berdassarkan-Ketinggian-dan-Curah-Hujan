<?php
error_reporting(E_ALL); // Melaporkan semua jenis error
ini_set('display_errors', 1); // Menampilkan error di browser

session_start();
session_destroy();
header("Location: ../auth/login.php"); // Pastikan path ini benar!
exit();
?>