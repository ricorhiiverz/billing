<?php
require_once 'config.php';
require_once 'routeros_api.class.php';
require_once 'whatsapp_helper.php';

echo "Memulai proses pengecekan tagihan jatuh tempo...\n";

try {
    $today = date('Y-m-d');
    // Ambil semua data yang diperlukan
    $sql_overdue = "SELECT 
                        i.id as invoice_id, i.invoice_number, i.total_amount,
                        c.id as customer_id, c.name as customer_name, c.phone_number, 
                        c.access_method, c.pppoe_user, c.static_ip,
                        r.ip_address, r.api_user, r.api_password
                    FROM invoices i
                    JOIN customers c ON i.customer_id = c.id
                    JOIN routers r ON c.router_id = r.id
                    WHERE i.status = 'UNPAID' AND i.due_date < ?";
    
    $stmt_overdue = $pdo->prepare($sql_overdue);
    $stmt_overdue->execute([$today]);
    $overdue_invoices = $stmt_overdue->fetchAll();

    if (empty($overdue_invoices)) {
        echo "Tidak ada tagihan jatuh tempo yang ditemukan.\n";
        exit;
    }

    echo "Ditemukan " . count($overdue_invoices) . " tagihan jatuh tempo. Memproses...\n";

    // --- TAHAP 1: KIRIM SEMUA NOTIFIKASI TERLEBIH DAHULU ---
    echo "Mengirim notifikasi isolir...\n";
    foreach ($overdue_invoices as $invoice) {
        $customer_name = $invoice['customer_name'];
        $invoice_number = $invoice['invoice_number'];
        $total_amount = number_format($invoice['total_amount'], 0, ',', '.');
        
        $message = "Yth. Bapak/Ibu {$customer_name},\n\nKami informasikan bahwa tagihan no. *{$invoice_number}* sebesar *Rp {$total_amount}* telah jatuh tempo.\n\nMohon maaf, untuk sementara layanan internet Anda akan kami non-aktifkan. Layanan akan otomatis aktif kembali setelah pembayaran dilakukan.\n\nTerima kasih.";
        
        if (sendWhatsAppMessage($pdo, $invoice['phone_number'], $message)) {
            echo "  - Notifikasi ke {$customer_name} ({$invoice['phone_number']}) berhasil dikirim.\n";
        } else {
            echo "  - GAGAL mengirim notifikasi ke {$customer_name} ({$invoice['phone_number']}).\n";
        }
        sleep(2);
    }

    echo "Menunggu 60 detik sebelum melakukan isolir...\n";
    sleep(60);

    // --- TAHAP 2: LAKUKAN ISOLIR DENGAN PENGECEKAN ULANG ---
    echo "Memulai proses isolir di MikroTik...\n";
    foreach ($overdue_invoices as $invoice) {
        
        // **PERBAIKAN: Cek ulang status invoice di database sebelum eksekusi**
        $stmt_check_status = $pdo->prepare("SELECT status FROM invoices WHERE id = ?");
        $stmt_check_status->execute([$invoice['invoice_id']]);
        $current_status = $stmt_check_status->fetchColumn();

        if ($current_status !== 'PAID') {
            echo "  - Memproses isolir untuk pelanggan: " . $invoice['customer_name'] . "\n";

            // Update status tagihan menjadi 'OVERDUE'
            $stmt_update = $pdo->prepare("UPDATE invoices SET status = 'OVERDUE' WHERE id = ?");
            $stmt_update->execute([$invoice['invoice_id']]);

            // Koneksi ke MikroTik
            $ip_address = $invoice['ip_address'];
            $custom_port = null;
            if (strpos($ip_address, ':') !== false) {
                list($ip_address, $custom_port) = explode(':', $ip_address, 2);
            }

            $API = new RouterosAPI();
            $API->debug = false;
            if ($custom_port) $API->port = (int)$custom_port;

            if ($API->connect($ip_address, $invoice['api_user'], $invoice['api_password'])) {
                if ($invoice['access_method'] == 'pppoe' && !empty($invoice['pppoe_user'])) {
                    $secrets = $API->comm("/ppp/secret/print", ["?name" => $invoice['pppoe_user']]);
                    if (!empty($secrets)) {
                        $API->comm("/ppp/secret/disable", [".id" => $secrets[0]['.id']]);
                        echo "      -> PPPoE user '" . $invoice['pppoe_user'] . "' dinonaktifkan.\n";
                    }
                } elseif ($invoice['access_method'] == 'static' && !empty($invoice['static_ip'])) {
                    $arps = $API->comm("/ip/arp/print", ["?address" => $invoice['static_ip']]);
                    if (!empty($arps)) {
                        $API->comm("/ip/arp/disable", [".id" => $arps[0]['.id']]);
                         echo "      -> Static IP '" . $invoice['static_ip'] . "' dinonaktifkan.\n";
                    }
                }
                $API->disconnect();
            } else {
                echo "      -> GAGAL: Tidak dapat terhubung ke router " . $invoice['ip_address'] . "\n";
            }
        } else {
            echo "  - Melewati isolir untuk " . $invoice['customer_name'] . " karena sudah membayar.\n";
        }
    }

    echo "Proses selesai.\n";

} catch (Exception $e) {
    echo "ERROR: Terjadi kesalahan fatal: " . $e->getMessage() . "\n";
}
?>
