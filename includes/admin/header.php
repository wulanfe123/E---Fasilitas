<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>E-Fasilitas Kampus</title>

    <!-- BOOTSTRAP -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- SB ADMIN / CUSTOM -->
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js"></script>
        <!-- Admin CSS (WAJIB) -->
    <link rel="stylesheet" href="../assets/css/admin.css">

</head>

<body class="sb-nav-fixed">
