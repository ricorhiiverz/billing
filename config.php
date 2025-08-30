<?php
/**
 * File Konfigurasi Database
 *
 * File ini berisi pengaturan untuk koneksi ke database MySQL.
 * Ganti nilai-nilai di bawah ini dengan informasi database Anda.
 */

// -- PENGATURAN KONEKSI DATABASE -- //
define('DB_HOST', 'localhost');      // Biasanya 'localhost' atau alamat IP server database
define('DB_USERNAME', 'u409826558_billing');       // Username database Anda
define('DB_PASSWORD', 'i;tF>yN?7');           // Password database Anda
define('DB_NAME', 'u409826558_billing');    // Nama database yang telah kita buat

// -- MEMBUAT KONEKSI -- //
try {
    // Membuat objek PDO untuk koneksi
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);

    // Mengatur mode error PDO ke exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Mengatur fetch mode default ke associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    // Jika koneksi gagal, hentikan skrip dan tampilkan pesan error
    // Pada lingkungan produksi, sebaiknya error ini dicatat ke log, bukan ditampilkan ke pengguna
    die("ERROR: Tidak dapat terhubung ke database. " . $e->getMessage());
}

// -- PENGATURAN SESSION -- //
// Memulai session jika belum ada. Ini penting untuk mengelola status login pengguna.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// --- (BARU) MEKANISME PENYEGARAN DAN VALIDASI SESI ---
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    
    // Ambil versi izin terbaru dari DB
    $stmt_check_version = $pdo->prepare("SELECT permissions_version FROM users WHERE id = ?");
    $stmt_check_version->execute([$_SESSION['id']]);
    $current_version = $stmt_check_version->fetchColumn();

    // 1. Validasi Sesi (Force Logout jika versi tidak cocok atau user dihapus)
    if ($current_version === false || $current_version != $_SESSION['permissions_version']) {
        // Hancurkan sesi dan redirect ke login
        session_unset();
        session_destroy();
        header("location: login.php");
        exit;
    }

    // 2. Penyegaran Data Wilayah untuk Collector
    if ($_SESSION['role'] == 'collector') {
        $stmt_wilayah = $pdo->prepare("SELECT wilayah_id FROM user_wilayah WHERE user_id = ?");
        $stmt_wilayah->execute([$_SESSION['id']]);
        $_SESSION['wilayah_ids'] = $stmt_wilayah->fetchAll(PDO::FETCH_COLUMN);
    }
}

