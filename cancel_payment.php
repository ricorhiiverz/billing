<?php
require_once 'config.php';

// Proteksi Halaman, hanya untuk admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

// Validasi parameter dari URL
if (!isset($_GET['invoice_id']) || !isset($_GET['customer_id']) || !isset($_GET['period'])) {
    $_SESSION['error_message'] = "Permintaan tidak valid.";
    header("location: invoices.php");
    exit;
}

$invoice_id = $_GET['invoice_id'];
$customer_id = $_GET['customer_id'];
$period = $_GET['period'];
$redirect_url = "view_customer_invoices.php?id={$customer_id}&period={$period}";

try {
    $pdo->beginTransaction();

    // 1. Hapus record pembayaran yang terkait dengan invoice ini
    // Hanya hapus pembayaran yang metodenya 'cash' untuk keamanan
    $stmt_delete = $pdo->prepare("DELETE FROM payments WHERE invoice_id = ? AND payment_method = 'cash'");
    $stmt_delete->execute([$invoice_id]);

    // Cek apakah ada baris yang dihapus. Jika tidak, berarti pembayaran bukan 'cash' atau sudah dibatalkan.
    if ($stmt_delete->rowCount() == 0) {
        throw new Exception("Pembatalan gagal. Mungkin pembayaran ini dilakukan secara online atau sudah pernah dibatalkan.");
    }

    // 2. Ambil tanggal jatuh tempo untuk menentukan status baru
    $stmt_invoice = $pdo->prepare("SELECT due_date FROM invoices WHERE id = ?");
    $stmt_invoice->execute([$invoice_id]);
    $invoice = $stmt_invoice->fetch();

    if (!$invoice) {
        throw new Exception("Invoice tidak ditemukan.");
    }
    
    // 3. Tentukan status baru: OVERDUE jika sudah lewat jatuh tempo, jika tidak UNPAID
    $new_status = (date('Y-m-d') > $invoice['due_date']) ? 'OVERDUE' : 'UNPAID';

    // 4. Update status invoice kembali ke UNPAID/OVERDUE
    $stmt_update = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
    $stmt_update->execute([$new_status, $invoice_id]);

    $pdo->commit();
    $_SESSION['success_message'] = "Pembayaran untuk invoice berhasil dibatalkan.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = "Gagal membatalkan pembayaran: " . $e->getMessage();
}

// Kembali ke halaman detail tagihan pelanggan
header("location: " . $redirect_url);
exit;
?>