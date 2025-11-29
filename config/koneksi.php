<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_peminjaman";

// Koneksi MySQLi
$conn = new mysqli($host, $user, $pass, $db);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Set karakter UTF-8
$conn->set_charset("utf8mb4");
?>
