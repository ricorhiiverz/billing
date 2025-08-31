<?php
// Mencegah output error PHP merusak format JSON
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';
require_once 'routeros_api.class.php';
require_once 'whatsapp_helper.php';

// Set header untuk response JSON di awal
header('Content-Type: application/json');

// Fungsi bantuan untuk mengirim response JSON dan menghentikan skrip
function send_json_response($success, $message, $data = []) {
    // Pastikan tidak ada output lain sebelum ini
    if (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION['role'], ['admin', 'collector'])) {
    send_json_response(false, "Sesi tidak valid atau hak akses tidak memadai.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['invoice_ids'])) {
    $confirmed_by_id = $_SESSION['id'];
    $invoice_ids_str = $_POST['invoice_ids'] ?? '';
    $invoice_ids = explode(',', $invoice_ids_str);
    $final_amount_paid = $_POST['amount_paid'];
    $total_discount = (in_array($_SESSION['role'], ['admin', 'collector']) && isset($_POST['discount'])) ? $_POST['discount'] : 0;

    if (empty($invoice_ids) || empty($invoice_ids[0]) || !is_numeric($final_amount_paid)) {
        send_json_response(false, "Data yang dikirim tidak lengkap atau tidak valid.");
    }

    try {
        $pdo->beginTransaction();

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

        if ($_SESSION['role'] == 'collector') {
            $customer_wilayah_id = $invoices_to_pay[0]['wilayah_id'];
            if (!$customer_wilayah_id || !in_array($customer_wilayah_id, $_SESSION['wilayah_ids'])) {
                 throw new Exception("Akses ditolak! Anda tidak berwenang mengonfirmasi pembayaran untuk pelanggan di luar wilayah Anda.");
            }
        }
        
        $stmt_update = $pdo->prepare("UPDATE invoices SET status = 'PAID' WHERE id IN ($placeholders)");
        $stmt_update->execute($invoice_ids);

        $payment_date = date('Y-m-d H:i:s');
        $stmt_insert = $pdo->prepare("INSERT INTO payments (invoice_id, amount_paid, discount_amount, payment_method, confirmed_by, payment_date) VALUES (?, ?, ?, 'cash', ?, ?)");

        foreach ($invoices_to_pay as $invoice) {
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

        $sql_details = "SELECT c.id as customer_id, c.name as customer_name, c.phone_number, c.access_method, c.pppoe_user, c.static_ip, r.ip_address, r.api_user, r.api_password FROM customers c JOIN routers r ON c.router_id = r.id WHERE c.id = ?";
        $stmt_details = $pdo->prepare($sql_details);
        $stmt_details->execute([$customer_id]);
        $details = $stmt_details->fetch();
        
        $final_message = "Pembayaran berhasil dikonfirmasi.";

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
                $final_message = "Pembayaran berhasil dikonfirmasi dan layanan diaktifkan.";
            } else {
                $stmt_flag = $pdo->prepare("UPDATE invoices SET requires_manual_activation = TRUE WHERE id IN ($placeholders)");
                $stmt_flag->execute($invoice_ids);
                $final_message = "Pembayaran berhasil, NAMUN aktivasi otomatis di router GAGAL. Harap periksa koneksi router dan aktifkan pelanggan secara manual.";
            }
        }
        
        $_SESSION['success_message'] = $final_message;
        send_json_response(true, $final_message);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Catat error ke log server untuk debugging
        error_log("Payment Processing Error: " . $e->getMessage());
        send_json_response(false, "Gagal memproses pembayaran: " . $e->getMessage());
    }
} else {
    send_json_response(false, "Permintaan tidak valid.");
}
?>

