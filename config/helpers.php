<?php
/**
 * ================================================
 * E-FASILITAS - HELPER FUNCTIONS
 * ================================================
 */

if (!function_exists('limit_text')) {
    function limit_text($text, $limit = 50) {
        $text = strip_tags($text);
        $text = htmlspecialchars_decode($text);
        if (mb_strlen($text, 'UTF-8') > $limit) {
            return mb_substr($text, 0, $limit, 'UTF-8') . '...';
        }
        return $text;
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime($datetime) {
        if (empty($datetime)) {
            return '-';
        }
        
        $timestamp = strtotime($datetime);
        $now = time();
        $diff = $now - $timestamp;
        
        if ($diff < 60) {
            return 'Baru saja';
        }
        
        if ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' menit lalu';
        }
        
        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' jam lalu';
        }
        
        if ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' hari lalu';
        }
        
        return date('d M Y, H:i', $timestamp);
    }
}

if (!function_exists('format_tanggal')) {
    function format_tanggal($date) {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '-';
        }
        
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $timestamp = strtotime($date);
        $hari = date('d', $timestamp);
        $bulan_num = (int)date('m', $timestamp);
        $tahun = date('Y', $timestamp);
        
        return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
    }
}

if (!function_exists('format_rupiah')) {
    function format_rupiah($angka, $prefix = true) {
        $hasil = number_format($angka, 0, ',', '.');
        return $prefix ? 'Rp ' . $hasil : $hasil;
    }
}

if (!function_exists('sanitize_input')) {
    function sanitize_input($input) {
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        return $input;
    }
}

if (!function_exists('get_status_badge')) {
    function get_status_badge($status) {
        $status_lower = strtolower(trim($status));
        
        $badges = [
            'usulan' => '<span class="badge bg-info text-white"><i class="fas fa-clock me-1"></i>Usulan</span>',
            'disetujui' => '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Disetujui</span>',
            'ditolak' => '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Ditolak</span>',
            'selesai' => '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Selesai</span>',
            'dikembalikan' => '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Dikembalikan</span>',
            'proses' => '<span class="badge bg-warning text-dark"><i class="fas fa-hourglass-split me-1"></i>Proses</span>',
            'dipinjam' => '<span class="badge bg-primary"><i class="fas fa-box-arrow-right me-1"></i>Dipinjam</span>',
        ];
        
        return $badges[$status_lower] ?? '<span class="badge bg-secondary">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
