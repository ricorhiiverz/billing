<?php
require_once 'config.php';
require_once 'whatsapp_helper.php'; // Panggil helper WhatsApp

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

try {
    // Ambil pengaturan PPN
    $stmt_settings = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'ppn_percentage'");
    $stmt_settings->execute();
    $ppn_percentage = $stmt_settings->fetchColumn();
    $ppn_rate = is_numeric($ppn_percentage) ? (float)$ppn_percentage / 100 : 0;

    // Ambil semua pelanggan aktif beserta data yang diperlukan untuk notifikasi
    $sql_customers = "SELECT c.id, c.name, c.phone_number, p.price 
                      FROM customers c
                      JOIN packages p ON c.package_id = p.id
                      WHERE c.is_active = TRUE";
    $active_customers = $pdo->query($sql_customers)->fetchAll();

    $current_month = date('Y-m');
    $invoices_created = 0;
    $notifications_sent = 0;

    foreach ($active_customers as $customer) {
        // Cek apakah tagihan untuk bulan ini sudah ada
        $sql_check = "SELECT id FROM invoices WHERE customer_id = ? AND billing_period = ?";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([$customer['id'], $current_month]);
        
        if ($stmt_check->rowCount() == 0) {
            // Jika belum ada, buat tagihan baru
            $base_price = (float)$customer['price'];
            $ppn_amount = $base_price * $ppn_rate;
            $total_amount = $base_price + $ppn_amount;
            
            $invoice_number = 'INV/' . date('Y/m/') . $customer['id'];
            $due_date = date('Y-m-10', strtotime('+1 month')); 

            $sql_insert = "INSERT INTO invoices (customer_id, invoice_number, billing_period, amount, ppn_amount, total_amount, due_date, status)
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'UNPAID')";
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([
                $customer['id'],
                $invoice_number,
                $current_month,
                $base_price,
                $ppn_amount,
                $total_amount,
                $due_date
            ]);
            $invoices_created++;

            // --- KIRIM NOTIFIKASI WHATSAPP TAGIHAN BARU ---
            $customer_name = $customer['name'];
            $total_amount_formatted = number_format($total_amount, 0, ',', '.');
            $due_date_formatted = date('d F Y', strtotime($due_date));

            $message = "Yth. Bapak/Ibu {$customer_name},\n\nTagihan internet Anda untuk periode " . date('F Y') . " telah terbit.\n\nNomor Tagihan: *{$invoice_number}*\nJumlah: *Rp {$total_amount_formatted}*\nJatuh Tempo: *{$due_date_formatted}*\n\nTerima kasih.";
            
            if (sendWhatsAppMessage($pdo, $customer['phone_number'], $message)) {
                $notifications_sent++;
            }
            sleep(2); // Jeda antar pesan
        }
    }

    $_SESSION['success_message'] = "$invoices_created tagihan baru berhasil dibuat dan $notifications_sent notifikasi telah dikirim.";

} catch (Exception $e) {
    $_SESSION['error_message'] = "Terjadi kesalahan saat membuat tagihan: " . $e->getMessage();
}

// Kembali ke halaman tagihan
header("location: invoices.php");
exit;
