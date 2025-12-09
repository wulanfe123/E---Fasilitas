<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check
if (!isset($_SESSION['id_user']) || !in_array($_SESSION['role'], ['super_admin', 'bagian_umum'])) {
    header("Location: ../auth/login.php");
    exit;
}
include '../config/koneksi.php';

$id_user_login = (int) ($_SESSION['id_user'] ?? 0);

// default
$namaUser = 'Admin';
$username = 'admin';
$role     = $_SESSION['role'] ?? '';

if ($id_user_login > 0) {
    $sqlUser = "SELECT nama, username, role FROM users WHERE id_user = ? LIMIT 1";
    if ($stmt = $conn->prepare($sqlUser)) {
        $stmt->bind_param("i", $id_user_login);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['nama'])) {
                $namaUser = $row['nama'];
                $_SESSION['nama'] = $row['nama'];       // sinkronkan session
            }
            if (!empty($row['username'])) {
                $username = $row['username'];
                $_SESSION['username'] = $row['username'];
            }
            if (!empty($row['role'])) {
                $role = $row['role'];
                $_SESSION['role'] = $row['role'];
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="Sistem Peminjaman Fasilitas Kampus" />
    <meta name="author" content="E-Fasilitas Polbeng" />
    <title><?= $pageTitle ?? 'E-Fasilitas'; ?> - Admin Panel</title>

    <!-- Bootstrap 5.3.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Font Awesome 6.5.1 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" 
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" />

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time(); ?>">

</head>
<body>

<!-- Layout Wrapper Start -->
<div id="layoutSidenav">
