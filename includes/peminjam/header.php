<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validasi session dengan ketat
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user = filter_var($_SESSION['id_user'], FILTER_VALIDATE_INT); 
if ($id_user === false || $id_user <= 0) {                         
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

$raw_nama = $_SESSION['nama_user'] ?? $_SESSION['nama'] ?? 'Peminjam';
$nama_user = htmlspecialchars($raw_nama, ENT_QUOTES, 'UTF-8');

// Set default page title jika belum ada
if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard Peminjam';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sistem Informasi Pemfas - Peminjaman Fasilitas">
    <meta name="author" content="Pemfas">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | Pemfas</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Custom CSS - Single File -->
    <link href="../assets/css/peminjam.css" rel="stylesheet">
</head>
<body>
