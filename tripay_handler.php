<?php
/**
 * File: tripay_handler.php
 * Deskripsi: Menangani callback (IPN - Instant Payment Notification) dari Tripay
 * untuk memproses pembayaran online, melunasi tagihan, dan mengaktifkan layanan pelanggan.
 * * Untuk melihat log, periksa file error.log di server web Anda.
 */
require_once 'config.php';
require_once 'routeros_api.class.php';
require_once 'whatsapp_helper.php';

// Ambil Private Key dari database untuk memverifikasi signature
$settings_stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'tripay_private_key'");
$private_key = $settings_stmt->fetchColumn();

// Ambil data POST mentah dari Tripay
$json = file_get_contents("php://input");
// Ambil signature dari header HTTP
$callbackSignature = isset($_SERVER['HTTP_X_CALLBACK_SIGNATURE']) ? $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] : '';

// --- LOGGING: Catat data callback yang masuk ---
error_log("Tripay Callback: Received POST request.");
error_log("Tripay Callback: Raw JSON data: " . $json);
error_log("Tripay Callback: Received signature: " . $callbackSignature);

// Hitung signature lokal untuk verifikasi
$signature = hash_hmac('sha256', $json, $private_key);

// Verifikasi apakah signature cocok
if ($callbackSignature !== $signature) {
    error_log("Tripay Callback: Invalid signature. Local: " . $signature . ", Received: " . $callbackSignature);
    http_response_code(403);
    die("Invalid signature");
}
error_log("Tripay Callback: Signature verified successfully.");

// Dekode data JSON
$data = json_decode($json, true);
$event = isset($_SERVER['HTTP_X_CALLBACK_EVENT']) ? $_SERVER['HTTP_X_CALLBACK_EVENT'] : '';

// Abaikan notifikasi yang bukan pembayaran atau statusnya tidak PAID
if ($event !== 'payment_status' || strtoupper($data['status']) !== 'PAID') {
    error_log("Tripay Callback: Ignoring event. Event: " . $event . ", Status: " . ($data['status'] ?? 'N/A'));
    http_response_code(200);
    die("Ignoring event or status not PAID.");
}
error_log("Tripay Callback: Processing a PAID payment status.");

// Logika pemrosesan callback dimulai
if ($data) {
    // PERBAIKAN: Ambil ID tagihan dari `custom_field` yang dikirim oleh Tripay via POST.
    $invoice_ids_str = $data['custom_field'] ?? '';
    $invoice_ids = explode(',', $invoice_ids_str);

    // Cek jika ID tagihan tidak ada
    if (empty($invoice_ids) || empty($invoice_ids[0])) {
        error_log("Tripay handler: ERROR - No invoice IDs found in callback payload.");
        http_response_code(400);
        die("No invoice IDs provided");
    }
    error_log("Tripay Callback: Found invoice IDs: " . $invoice_ids_str);

    try {
        $pdo->beginTransaction();
        error_log("Tripay Callback: Database transaction started.");

        // 1. Ambil detail semua invoice yang dibayar
        $placeholders = implode(',', array_fill(0, count($invoice_ids), '?'));
        $stmt_invoices = $pdo->prepare("SELECT id, total_amount FROM invoices WHERE id IN ($placeholders)");
        $stmt_invoices->execute($invoice_ids);
        $invoices_paid = $stmt_invoices->fetchAll();

        // 2. Update status SEMUA invoice menjadi 'PAID'
        $stmt_update = $pdo->prepare("UPDATE invoices SET status = 'PAID' WHERE id IN ($placeholders) AND status != 'PAID'");
        $stmt_update->execute($invoice_ids);
        error_log("Tripay Callback: Updated status of " . count($invoices_paid) . " invoices to PAID.");

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
        error_log("Tripay Callback: Database transaction committed successfully.");

        // 4. --- Aktivasi MikroTik & Notifikasi WhatsApp ---
        // Asumsi semua invoice berasal dari satu pelanggan, ambil ID dari invoice pertama
        $first_invoice = reset($invoices_paid);
        $first_invoice_id = $first_invoice['id'];

        $sql_details = "SELECT c.id as customer_id, c.name as customer_name, c.phone_number, c.access_method, c.pppoe_user, c.static_ip, r.ip_address, r.api_user, r.api_password 
                        FROM invoices i 
                        JOIN customers c ON i.customer_id = c.id 
                        JOIN routers r ON c.router_id = r.id 
                        WHERE i.id = ?";
        $stmt_details = $pdo->prepare($sql_details);
        $stmt_details->execute([$first_invoice_id]);
        $details = $stmt_details->fetch();

        if ($details) {
            error_log("Tripay Callback: Attempting to connect to router " . $details['ip_address']);
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
                        error_log("Tripay Callback: Successfully enabled PPPoE user '" . $details['pppoe_user'] . "' on MikroTik.");
                    }
                } elseif ($details['access_method'] == 'static' && !empty($details['static_ip'])) {
                    $arps = $API->comm("/ip/arp/print", ["?address" => $details['static_ip']]);
                    if (!empty($arps)) {
                        $API->comm("/ip/arp/enable", [".id" => $arps[0]['.id']]);
                        $activation_success = true;
                        error_log("Tripay Callback: Successfully enabled static IP '" . $details['static_ip'] . "' on MikroTik.");
                    }
                }
                $API->disconnect();
            } else {
                error_log("Tripay Callback: FAILED to connect to router " . $details['ip_address']);
            }

            if ($activation_success) {
                $customer_name = $details['customer_name'];
                $amount_paid_formatted = number_format($data['amount'], 0, ',', '.');
                $message = "Terima kasih Bapak/Ibu {$customer_name},\n\nPembayaran online Anda sebesar *Rp {$amount_paid_formatted}* telah berhasil.\n\nLayanan internet Anda telah diaktifkan kembali. Selamat menikmati!";
                if (sendWhatsAppMessage($pdo, $details['phone_number'], $message)) {
                    error_log("Tripay Callback: WhatsApp notification sent to " . $details['phone_number']);
                } else {
                    error_log("Tripay Callback: FAILED to send WhatsApp notification to " . $details['phone_number']);
                }
            } else {
                $stmt_flag = $pdo->prepare("UPDATE invoices SET requires_manual_activation = TRUE WHERE id IN ($placeholders)");
                $stmt_flag->execute($invoice_ids);
                error_log("Tripay Callback: FAILED to activate service on router. Marked for manual activation.");
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Tripay Callback: FATAL ERROR - " . $e->getMessage());
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Internal Server Error']));
    }
}

// Beri respons sukses ke Tripay
error_log("Tripay Callback: Process finished successfully. Sending 200 OK response.");
echo json_encode(['success' => true]);
?>
