<?php
require_once 'config.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_id'])) {
    $invoice_id = $_POST['invoice_id'];

    try {
        // Update flag di database menjadi FALSE
        $stmt = $pdo->prepare("UPDATE invoices SET requires_manual_activation = FALSE WHERE id = ?");
        $stmt->execute([$invoice_id]);

        $_SESSION['success_message'] = "Masalah aktivasi untuk tagihan telah ditandai selesai.";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Gagal memperbarui status: " . $e->getMessage();
    }

    // Kembali ke halaman detail invoice
    header("location: view_invoice.php?id=" . $invoice_id);
    exit;
} else {
    // Jika diakses secara tidak benar, kembali ke dashboard
    header("location: dashboard.php");
    exit;
}
?>
