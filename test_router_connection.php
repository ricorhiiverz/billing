<?php
require_once 'config.php';
require_once 'routeros_api.class.php';

// Atur header untuk output JSON
header('Content-Type: application/json');

// Proteksi Halaman & Validasi Request
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['router_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Request tidak valid.']);
    exit;
}

$router_id = $_POST['router_id'];

// Ambil detail router dari database
$stmt = $pdo->prepare("SELECT ip_address, api_user, api_password FROM routers WHERE id = ?");
$stmt->execute([$router_id]);
$router = $stmt->fetch();

if (!$router) {
    echo json_encode(['status' => 'error', 'message' => 'Router tidak ditemukan di database.']);
    exit;
}

// --- PERBAIKAN: Parsing IP dan Port Kustom ---
$ip_address = $router['ip_address'];
$custom_port = null;

// Cek jika ada port kustom dalam format IP:PORT
if (strpos($ip_address, ':') !== false) {
    $parts = explode(':', $ip_address);
    $ip_address = $parts[0]; // Ambil hanya bagian IP
    $custom_port = (int)$parts[1]; // Ambil bagian port
}

// Coba koneksi ke Router
$API = new RouterosAPI();
$API->debug = false; // Matikan debug untuk produksi

// Jika ada port kustom, set port pada objek API sebelum koneksi
if ($custom_port) {
    $API->port = $custom_port;
}

// Gunakan @fsockopen untuk menekan warning jika IP tidak terjangkau
if ($API->connect($ip_address, $router['api_user'], $router['api_password'])) {
    // Jika koneksi berhasil, langsung disconnect
    $API->disconnect();
    echo json_encode(['status' => 'success', 'message' => 'Koneksi berhasil!']);
} else {
    // Jika koneksi gagal
    echo json_encode(['status' => 'error', 'message' => 'Koneksi gagal. Periksa IP, port, username, password, dan pastikan port API dapat diakses dari server ini.']);
}
?>
