<?php
require_once 'config.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

// Set header untuk memberitahu browser bahwa ini adalah file CSV yang akan diunduh
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_pelanggan_dinamis.csv"');

// Buat output file
$output = fopen('php://output', 'w');

// --- Ambil Data untuk Panduan ---
$packages = $pdo->query("SELECT id, name, price FROM packages ORDER BY id ASC")->fetchAll();
$routers = $pdo->query("SELECT id, name FROM routers ORDER BY id ASC")->fetchAll();
$wilayah_list = $pdo->query("SELECT id, nama_wilayah FROM wilayah ORDER BY id ASC")->fetchAll();

// --- Tulis Panduan ke File CSV ---

// Panduan Paket
fputcsv($output, ['# --- PANDUAN ID PAKET ---']);
fputcsv($output, ['# ID Paket', 'Nama Paket', 'Harga']);
foreach ($packages as $package) {
    fputcsv($output, ['# ' . $package['id'], $package['name'], $package['price']]);
}
fputcsv($output, []); // Baris kosong sebagai pemisah

// Panduan Router
fputcsv($output, ['# --- PANDUAN ID ROUTER ---']);
fputcsv($output, ['# ID Router', 'Nama Router']);
foreach ($routers as $router) {
    fputcsv($output, ['# ' . $router['id'], $router['name']]);
}
fputcsv($output, []); // Baris kosong sebagai pemisah

// Panduan Wilayah
fputcsv($output, ['# --- PANDUAN ID WILAYAH ---']);
fputcsv($output, ['# ID Wilayah', 'Nama Wilayah']);
foreach ($wilayah_list as $wilayah) {
    fputcsv($output, ['# ' . $wilayah['id'], $wilayah['nama_wilayah']]);
}
fputcsv($output, []); // Baris kosong sebagai pemisah

fputcsv($output, ['# --- MULAI ISI DATA DARI BARIS DI BAWAH INI ---']);
fputcsv($output, []); // Baris kosong sebagai pemisah


// --- Tulis Header Utama dan Contoh Data ---

// Header
fputcsv($output, ['name', 'address', 'phone_number', 'package_id', 'router_id', 'access_method', 'pppoe_user', 'pppoe_password', 'static_ip', 'mac_address', 'registration_date', 'wilayah_id']);

// Contoh Data
fputcsv($output, [
    'Contoh Pelanggan PPPoE', 'Jl. Merdeka No. 1', '08123456789', '1', '1', 'pppoe', 'user-contoh', 'pass123', '', '', date('Y-m-d'), '1'
]);
fputcsv($output, [
    'Pelanggan Contoh Statik', 'Jl. Pahlawan No. 2', '0876543210', '2', '1', 'static', '', '', '192.168.1.100', '', date('Y-m-d'), '2'
]);

fclose($output);
exit;

?>