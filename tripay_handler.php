<?php
require_once 'config.php';
require_once 'routeros_api.class.php';
require_once 'whatsapp_helper.php'; // Pastikan helper WhatsApp di-include

$settings_stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'tripay_private_key'");
$private_key = $settings_stmt->fetchColumn();

$json = file_get_contents("php://input");
$callbackSignature = isset($_SERVER['HTTP_X_CALLBACK_SIGNATURE']) ? $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] : '';
$signature = hash_hmac('sha256', $json, $private_key);

if ($callbackSignature !== $signature) {
    http_response_code(403);
    die("Invalid signature");
}

$data = json_decode($json, true);
$event = isset($_SERVER['HTTP_X_CALLBACK_EVENT']) ? $_SERVER['HTTP_X_CALLBACK_EVENT'] : '';

if ($event !== 'payment_status' || strtoupper($data['status']) !== 'PAID') {
    http_response_code(200);
    die("Ignoring event or status not PAID.");
}

if ($data) {
    // Ambil daftar ID invoice dari parameter URL yang dikirim oleh client_pay.php
    $invoice_ids_str = $_GET['ids'] ?? '';
    $invoice_ids = explode(',', $invoice_ids_str);

    if (empty($invoice_ids) || empty($invoice_ids[0])) {
        error_log("Tripay handler: No invoice IDs found in URL for merchant_ref: " . $data['merchant_ref']);
        http_response_code(400);
        die("No invoice IDs provided");
    }

    try {
        $pdo->beginTransaction();

        // 1. Ambil detail semua invoice yang dibayar
        $placeholders = implode(',', array_fill(0, count($invoice_ids), '?'));
        $stmt_invoices = $pdo->prepare("SELECT id, total_amount FROM invoices WHERE id IN ($placeholders)");
        $stmt_invoices->execute($invoice_ids);
        $invoices_paid = $stmt_invoices->fetchAll();

        // 2. Update status SEMUA invoice menjadi 'PAID'
        $stmt_update = $pdo->prepare("UPDATE invoices SET status = 'PAID' WHERE id IN ($placeholders) AND status != 'PAID'");
        $stmt_update->execute($invoice_ids);

        // 3. Buat record pembayaran untuk SETIAP invoice
        $payment_date = date('Y-m-d H:i:s');
        $stmt_insert = $pdo->prepare("INSERT INTO payments (invoice_id, amount_paid, discount_amount, payment_method, transaction_id, payment_date) VALUES (?, ?, 0, 'tripay', ?, ?)");

        foreach ($invoices_paid as $invoice) {
            $stmt_insert->execute([
                $invoice['id'],
                $invoice['total_amount'],
                $data['reference'], // Gunakan referensi dari Tripay sebagai transaction_id
                $payment_date
            ]);
        }
        
        $pdo->commit();

        // 4. --- Aktivasi MikroTik & Notifikasi WhatsApp ---
        $last_invoice_id = end($invoice_ids);
        $sql_details = "SELECT c.id as customer_id, c.name as customer_name, c.phone_number, c.access_method, c.pppoe_user, c.static_ip, r.ip_address, r.api_user, r.api_password FROM invoices i JOIN customers c ON i.customer_id = c.id JOIN routers r ON c.router_id = r.id WHERE i.id = ?";
        $stmt_details = $pdo->prepare($sql_details);
        $stmt_details->execute([$last_invoice_id]);
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
                // Kirim notifikasi WhatsApp jika aktivasi berhasil
                $customer_name = $details['customer_name'];
                $amount_paid_formatted = number_format($data['amount'], 0, ',', '.');
                $message = "Terima kasih Bapak/Ibu {$customer_name},\n\nPembayaran online Anda sebesar *Rp {$amount_paid_formatted}* telah berhasil.\n\nLayanan internet Anda telah diaktifkan kembali. Selamat menikmati!";
                sendWhatsAppMessage($pdo, $details['phone_number'], $message);
            } else {
                $stmt_flag = $pdo->prepare("UPDATE invoices SET requires_manual_activation = TRUE WHERE id IN ($placeholders)");
                $stmt_flag->execute($invoice_ids);
                error_log("Aktivasi otomatis GAGAL untuk invoice IDs: " . $invoice_ids_str);
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Failed to process Tripay notification: " . $e->getMessage());
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Internal Server Error']));
    }
}

echo json_encode(['success' => true]);
?>
