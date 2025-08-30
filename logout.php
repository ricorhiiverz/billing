<?php
// Memulai session untuk mengakses data session yang ada.
session_start();

// 1. Menghapus semua variabel session.
$_SESSION = array();

// 2. Menghancurkan session.
// Ini akan menghapus semua data yang tersimpan di server terkait session ini.
session_destroy();

// 3. Mengarahkan pengguna kembali ke halaman login.
// header() mengirimkan header HTTP mentah ke klien.
header("location: login.php");

// Pastikan kode di bawahnya tidak dieksekusi setelah pengalihan.
exit;
?>