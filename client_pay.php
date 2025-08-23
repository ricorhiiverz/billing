<?php
require_once 'config.php';

// Validasi parameter dari URL
if (!isset($_GET['cust_id']) || !isset($_GET['ids']) || empty($_GET['cust_id']) || empty($_GET['ids'])) {
    header("location: cek_tagihan.php");
    exit;
}

$customer_id = $_GET['cust_id'];
$invoice_ids_str = $_GET['ids'];
$invoice_ids = explode(',', $invoice_ids_str);

// Ambil detail pelanggan
$stmt_cust = $pdo->prepare("SELECT name, phone_number, customer_number FROM customers WHERE id = ?");
$stmt_cust->execute([$customer_id]);
$customer = $stmt_cust->fetch();

if (!$customer) {
    header("location: cek_tagihan.php");
    exit;
}
$customer_email = $customer['customer_number'] . '@domain.com'; // Email dummy

// Hitung ulang total tagihan dari database untuk keamanan
$placeholders = implode(',', array_fill(0, count($invoice_ids), '?'));
$sql = "SELECT SUM(total_amount) as total FROM invoices WHERE id IN ($placeholders) AND customer_id = ? AND status IN ('UNPAID', 'OVERDUE')";
$params = array_merge($invoice_ids, [$customer_id]);
$stmt_total = $pdo->prepare($sql);
$stmt_total->execute($params);
$total_amount = $stmt_total->fetchColumn();

if ($total_amount <= 0) {
    // Jika tidak ada yang perlu dibayar (mungkin sudah lunas), kembalikan
    header("location: cek_tagihan.php");
    exit;
}

// Buat nomor referensi unik untuk pembayaran gabungan ini
$payment_reference = 'PAY-' . $customer_id . '-' . time();

// Ambil semua settings dari database
$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$active_gateway = $settings['active_payment_gateway'] ?? 'midtrans';

$payment_gateway_name = '';
$error_message = '';
$snap_token = null;
$tripay_redirect_url = null;

// =================================================================
// LOGIKA PAYMENT GATEWAY (TETAP SAMA, HANYA MENGGUNAKAN DATA BARU)
// =================================================================

if ($active_gateway == 'midtrans') {
    $payment_gateway_name = 'Midtrans';
    $is_production = false;
    $server_key = $settings['midtrans_server_key'] ?? '';
    $client_key = $settings['midtrans_client_key'] ?? '';

    if (empty($server_key) || empty($client_key)) {
        $error_message = "Konfigurasi Midtrans belum lengkap.";
    } else {
        // Gunakan payment_reference sebagai order_id
        $transaction_details = ['order_id' => $payment_reference, 'gross_amount' => (int)$total_amount];
        $customer_details = ['first_name' => $customer['name'], 'email' => $customer_email, 'phone' => $customer['phone_number']];
        // Tambahkan ID invoice ke metadata untuk dilacak di webhook
        $params = [
            'transaction_details' => $transaction_details, 
            'customer_details' => $customer_details,
            'custom_field1' => $invoice_ids_str // Simpan daftar ID invoice
        ];

        $auth = base64_encode($server_key . ':');
        $url = $is_production ? 'https://app.midtrans.com/snap/v1/transactions' : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
        // ... (sisa cURL request tetap sama)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Basic ' . $auth]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        $response_data = json_decode($response, true);
        $snap_token = $response_data['token'] ?? null;
        if (!$snap_token) {
             $error_message = "Gagal mendapatkan token pembayaran dari Midtrans.";
        }
    }

} elseif ($active_gateway == 'tripay') {
    $payment_gateway_name = 'Tripay';
    $is_production = false;
    $api_key = $settings['tripay_api_key'] ?? '';
    $private_key = $settings['tripay_private_key'] ?? '';
    $merchant_code = 'T14282';

    if (empty($api_key) || empty($private_key)) {
        $error_message = "Konfigurasi Tripay belum lengkap.";
    } else {
        // Gunakan payment_reference sebagai merchant_ref
        $merchant_ref = $payment_reference;
        $amount = (int)$total_amount;

        $data = [
            'method'         => 'QRIS',
            'merchant_ref'   => $merchant_ref,
            'amount'         => $amount,
            'customer_name'  => $customer['name'],
            'customer_email' => $customer_email,
            'customer_phone' => $customer['phone_number'],
            'order_items'    => [
                [
                    'sku'      => 'TUNGGAKAN',
                    'name'     => 'Total Tagihan Internet',
                    'price'    => $amount,
                    'quantity' => 1
                ]
            ],
            // Sertakan ID invoice di callback_url untuk dilacak di webhook
            'callback_url'   => 'https://namadomainanda.com/billing/tripay_handler.php?ids=' . $invoice_ids_str,
            'return_url'     => 'https://namadomainanda.com/billing/cek_tagihan.php',
            'expired_time'   => (time() + (24 * 60 * 60)), // 24 jam
            'signature'      => hash_hmac('sha256', $merchant_code . $merchant_ref . $amount, $private_key)
        ];

        $url = $is_production ? 'https://tripay.co.id/api/transaction/create' : 'https://tripay.co.id/api-sandbox/transaction/create';
        // ... (sisa cURL request tetap sama)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_key]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $response = curl_exec($ch);
        curl_close($ch);
        $response_data = json_decode($response, true);
        if (isset($response_data['success']) && $response_data['success'] == true) {
            $tripay_redirect_url = $response_data['data']['checkout_url'];
        } else {
            $error_message = "Gagal membuat transaksi Tripay: " . ($response_data['message'] ?? 'Unknown error');
        }
    }
} else {
    $error_message = "Payment gateway tidak valid.";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proses Pembayaran</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php if ($active_gateway == 'midtrans' && $snap_token): ?>
        <script type="text/javascript"
                src="<?php echo $is_production ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js'; ?>"
                data-client-key="<?php echo htmlspecialchars($client_key); ?>"></script>
    <?php endif; ?>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen text-center">
    <div class="p-8 bg-white rounded-xl shadow-lg">
        
        <?php if ($error_message): ?>
            <h1 class="text-2xl font-bold text-red-600 mb-4">Terjadi Kesalahan</h1>
            <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($error_message); ?></p>
            <a href="cek_tagihan.php" class="text-blue-600 hover:underline">Kembali Cek Tagihan</a>
        
        <?php elseif ($active_gateway == 'midtrans' && $snap_token): ?>
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Mempersiapkan Pembayaran...</h1>
            <p class="text-gray-600 mb-6">Anda akan diarahkan ke halaman pembayaran <?php echo $payment_gateway_name; ?>.</p>
            <script type="text/javascript">
                document.addEventListener('DOMContentLoaded', function() {
                    snap.pay('<?php echo $snap_token; ?>', {
                        onSuccess: function(result){ window.location.href = 'cek_tagihan.php?status=success'; },
                        onPending: function(result){ window.location.href = 'cek_tagihan.php?status=pending'; },
                        onError: function(result){ window.location.href = 'cek_tagihan.php?status=error'; },
                        onClose: function(){ window.location.href = 'cek_tagihan.php?status=closed'; }
                    });
                });
            </script>

        <?php elseif ($active_gateway == 'tripay' && $tripay_redirect_url): ?>
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Mengarahkan ke Pembayaran...</h1>
            <p class="text-gray-600 mb-6">Anda akan diarahkan ke halaman pembayaran <?php echo $payment_gateway_name; ?>.</p>
            <script type="text/javascript">
                document.addEventListener('DOMContentLoaded', function() {
                    window.location.href = '<?php echo $tripay_redirect_url; ?>';
                });
            </script>
            
        <?php endif; ?>

    </div>
</body>
</html>