<?php
require_once 'config.php';
require_once 'routeros_api.class.php';
require_once 'whatsapp_helper.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION['role'], ['admin', 'collector'])) {
    header("location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['invoice_ids'])) {
    $confirmed_by_id = $_SESSION['id'];
    $invoice_ids_str = $_POST['invoice_ids'] ?? '';
    $invoice_ids = explode(',', $invoice_ids_str);
    $final_amount_paid = $_POST['amount_paid'];
    $total_discount = ($_SESSION['role'] == 'admin' && isset($_POST['discount'])) ? $_POST['discount'] : 0;

    if (empty($invoice_ids) || empty($invoice_ids[0]) || !is_numeric($final_amount_paid)) {
        $_SESSION['error_message'] = "Data tidak lengkap.";
        header("location: customers.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Ambil detail semua invoice yang akan dibayar untuk validasi dan kalkulasi
        // PERBAIKAN: Menambahkan alias 'i' dan 'c' untuk menghindari ambiguitas pada kolom 'id'.
        $placeholders = implode(',', array_fill(0, count($invoice_ids), '?'));
        $sql_invoices = "SELECT i.id, i.customer_id, i.total_amount, c.wilayah_id 
                         FROM invoices i 
                         JOIN customers c ON i.customer_id = c.id 
                         WHERE i.id IN ($placeholders)";
        $stmt_invoices = $pdo->prepare($sql_invoices);
        $stmt_invoices->execute($invoice_ids);
        $invoices_to_pay = $stmt_invoices->fetchAll();

        if (count($invoices_to_pay) != count($invoice_ids)) {
            throw new Exception("Beberapa invoice tidak ditemukan atau tidak valid.");
        }

        $customer_id = $invoices_to_pay[0]['customer_id'];
        $total_bill = array_sum(array_column($invoices_to_pay, 'total_amount'));

        // --- VALIDASI WILAYAH UNTUK COLLECTOR ---
        if ($_SESSION['role'] == 'collector') {
            $customer_wilayah_id = $invoices_to_pay[0]['wilayah_id'];
            if (!$customer_wilayah_id || !in_array($customer_wilayah_id, $_SESSION['wilayah_ids'])) {
                $_SESSION['error_message'] = "Akses ditolak! Anda tidak berwenang mengonfirmasi pembayaran untuk pelanggan di luar wilayah Anda.";
                header("location: customers.php");
                exit;
            }
        }
        
        // 2. Update status SEMUA invoice menjadi 'PAID'
        $stmt_update = $pdo->prepare("UPDATE invoices SET status = 'PAID' WHERE id IN ($placeholders)");
        $stmt_update->execute($invoice_ids);

        // 3. Buat record pembayaran untuk SETIAP invoice
        $payment_date = date('Y-m-d H:i:s');
        $stmt_insert = $pdo->prepare("INSERT INTO payments (invoice_id, amount_paid, discount_amount, payment_method, confirmed_by, payment_date) VALUES (?, ?, ?, 'cash', ?, ?)");

        foreach ($invoices_to_pay as $invoice) {
            // Bagi diskon secara proporsional berdasarkan nilai tagihan
            $proportional_discount = ($total_bill > 0) ? ($invoice['total_amount'] / $total_bill) * $total_discount : 0;
            $amount_paid_for_this_invoice = $invoice['total_amount'] - $proportional_discount;

            $stmt_insert->execute([
                $invoice['id'],
                $amount_paid_for_this_invoice,
                round($proportional_discount, 2),
                $confirmed_by_id,
                $payment_date
            ]);
        }
        
        $pdo->commit();

        // 4. --- Proses Aktivasi & Notifikasi ---
        $sql_details = "SELECT c.id as customer_id, c.name as customer_name, c.phone_number, c.access_method, c.pppoe_user, c.static_ip, r.ip_address, r.api_user, r.api_password FROM customers c JOIN routers r ON c.router_id = r.id WHERE c.id = ?";
        $stmt_details = $pdo->prepare($sql_details);
        $stmt_details->execute([$customer_id]);
        $details = $stmt_details->fetch();

        if ($details) {
            $activation_success = false;
            $ip_address = $details['ip_address'];
            $custom_port = null;
            if (strpos($ip_address, ':') !== false) {
                list($ip_address, $custom_port) = explode(':', $ip_address, 2);
            }
            $API = new RouterosAPI();
            $API->debug = false; 
            if ($custom_port) $API->port = (int)$custom_port;

            if ($API->connect($ip_address, $details['api_user'], $details['api_password'])) {
                if ($details['access_method'] == 'pppoe' && !empty($details['pppoe_user'])) {
                    $secrets = $API->comm("/ppp/secret/print", ["?name" => $details['pppoe_user']]);
                    if (!empty($secrets)) {
                        $API->comm("/ppp/secret/enable", [".id" => $secrets[0]['.id']]);
                        $activation_success = true;
                    }
                } elseif ($details['access_method'] == 'static' && !empty($details['static_ip'])) {
                    $arps = $API->comm("/ip/arp/print", ["?address" => $details['static_ip']]);
                    if (!empty($arps)) {
                        $API->comm("/ip/arp/enable", [".id" => $arps[0]['.id']]);
                        $activation_success = true;
                    }
                }
                $API->disconnect();
            }

            if ($activation_success) {
                $customer_name = $details['customer_name'];
                $amount_paid_formatted = number_format($final_amount_paid, 0, ',', '.');
                $message = "Terima kasih Bapak/Ibu {$customer_name},\n\nPembayaran Anda sebesar *Rp {$amount_paid_formatted}* telah kami terima.\n\nLayanan internet Anda telah diaktifkan kembali. Selamat menikmati!";
                sendWhatsAppMessage($pdo, $details['phone_number'], $message);
                $_SESSION['success_message'] = "Pembayaran berhasil dikonfirmasi dan layanan diaktifkan.";
            } else {
                $stmt_flag = $pdo->prepare("UPDATE invoices SET requires_manual_activation = TRUE WHERE id IN ($placeholders)");
                $stmt_flag->execute($invoice_ids);
                $_SESSION['error_message'] = "Pembayaran berhasil, NAMUN aktivasi otomatis di router GAGAL. Harap periksa koneksi router dan aktifkan pelanggan secara manual.";
            }
        }

        header("location: view_customer_invoices.php?id=" . $details['customer_id'] . "&period=" . date('Y-m'));
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = "Gagal memproses pembayaran: " . $e->getMessage();
        header("location: customers.php");
        exit;
    }
} else {
    header("location: customers.php");
    exit;
}
?>
