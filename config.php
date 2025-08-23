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
?>
