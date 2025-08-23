<?php
// Skrip ini dimaksudkan untuk dijalankan oleh Cron Job.
require_once 'config.php';

echo "Memulai proses pengiriman pengingat tagihan...\n";

try {
    // 1. Ambil Fonnte API Token dari database
    $stmt_settings = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'fonnte_token'");
    $stmt_settings->execute();
    $fonnte_token = $stmt_settings->fetchColumn();

    if (empty($fonnte_token)) {
        die("ERROR: Fonnte token tidak ditemukan di pengaturan.\n");
    }

    // 2. Tentukan tanggal target (misal: 3 hari dari sekarang)
    $reminder_date = date('Y-m-d', strtotime('+3 days'));

    // 3. Cari semua tagihan yang belum lunas dan jatuh tempo pada tanggal target
    $sql = "SELECT 
                c.name as customer_name,
                c.phone_number,
                i.total_amount,
                i.due_date
            FROM invoices i
            JOIN customers c ON i.customer_id = c.id
            WHERE i.status = 'UNPAID' AND i.due_date = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$reminder_date]);
    $invoices_to_remind = $stmt->fetchAll();

    if (empty($invoices_to_remind)) {
        echo "Tidak ada tagihan yang perlu diingatkan hari ini.\n";
        exit;
    }

    echo "Ditemukan " . count($invoices_to_remind) . " tagihan untuk diingatkan.\n";

    // 4. Loop dan kirim notifikasi untuk setiap tagihan
    foreach ($invoices_to_remind as $invoice) {
        $phone_number = $invoice['phone_number'];
        $customer_name = $invoice['customer_name'];
        $total_amount = number_format($invoice['total_amount'], 0, ',', '.');
        $due_date_formatted = date('d F Y', strtotime($invoice['due_date']));

        // Kustomisasi pesan Anda di sini
        $message = "Yth. Bapak/Ibu {$customer_name},\n\nKami ingin mengingatkan bahwa tagihan internet Anda sebesar *Rp {$total_amount}* akan jatuh tempo pada tanggal *{$due_date_formatted}*.\n\nMohon untuk segera melakukan pembayaran agar layanan tidak terganggu.\n\nTerima kasih.";

        // Kirim ke Fonnte API
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.fonnte.com/send',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => array(
            'target' => $phone_number,
            'message' => $message,
            'countryCode' => '62', // Kode negara Indonesia
          ),
          CURLOPT_HTTPHEADER => array(
            'Authorization: ' . $fonnte_token
          ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        
        // Catat hasil pengiriman (opsional, bisa disimpan ke log file)
        echo "  - Mengirim ke {$phone_number}: " . $response . "\n";
        sleep(1); // Beri jeda 1 detik antar pesan
    }

    echo "Proses pengiriman pengingat selesai.\n";

} catch (Exception $e) {
    echo "ERROR: Terjadi kesalahan fatal: " . $e->getMessage() . "\n";
    error_log("Fatal error during reminder sending: " . $e->getMessage());
}
?>
