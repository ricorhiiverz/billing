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
$tripay_channels = [];
$loading_channels = false;
$fetch_channels_error = '';

// =================================================================
// LOGIKA PEMBAYARAN ONLINE
// =================================================================

if ($active_gateway == 'midtrans') {
    $payment_gateway_name = 'Midtrans';
    $is_production = false;
    $server_key = $settings['midtrans_server_key'] ?? '';
    $client_key = $settings['midtrans_client_key'] ?? '';

    if (empty($server_key) || empty($client_key)) {
        $error_message = "Konfigurasi Midtrans belum lengkap.";
    } else {
        $transaction_details = ['order_id' => $payment_reference, 'gross_amount' => (int)$total_amount];
        $customer_details = ['first_name' => $customer['name'], 'email' => $customer_email, 'phone' => $customer['phone_number']];
        $params = [
            'transaction_details' => $transaction_details,
            'customer_details' => $customer_details,
            'custom_field1' => $invoice_ids_str
        ];
        $auth = base64_encode($server_key . ':');
        $url = $is_production ? 'https://app.midtrans.com/snap/v1/transactions' : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Basic ' . $auth]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
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
    $merchant_code = $settings['tripay_merchant_code'] ?? '';

    if (empty($api_key) || empty($private_key) || empty($merchant_code)) {
        $error_message = "Konfigurasi Tripay belum lengkap.";
    } else {
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['channel_code'])) {
            $payment_channel = $_POST['channel_code'];
            $merchant_ref = $payment_reference;
            
            $amount = (int)$_POST['final_amount'];
            
            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            $callback_url = $base_url . "/billing/tripay_handler.php"; // PERBAIKAN: Hapus parameter `ids` dari URL callback.
            $return_url = $base_url . "/billing/cek_tagihan.php";

            $signature = hash_hmac('sha256', $merchant_code . $merchant_ref . $amount, $private_key);

            $data = [
                'method'         => $payment_channel,
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
                'custom_field'   => $invoice_ids_str, // PERBAIKAN: Kirim ID tagihan via custom field.
                'callback_url'   => $callback_url,
                'return_url'     => $return_url,
                'expired_time'   => (time() + (24 * 60 * 60)),
                'signature'      => $signature
            ];

            $url = $is_production ? 'https://tripay.co.id/api/transaction/create' : 'https://tripay.co.id/api-sandbox/transaction/create';
            $ch = curl_init();
            curl_setopt_array($ch, array(
              CURLOPT_URL => $url,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $api_key],
              CURLOPT_POST => 1,
              CURLOPT_POSTFIELDS => http_build_query($data),
              CURLOPT_SSL_VERIFYPEER => false,
              CURLOPT_SSL_VERIFYHOST => false
            ));
            $response = curl_exec($ch);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) {
                $error_message = "Kesalahan cURL: " . $curl_error;
            } else {
                $response_data = json_decode($response, true);
                if (isset($response_data['success']) && $response_data['success'] == true) {
                    $tripay_redirect_url = $response_data['data']['checkout_url'];
                } else {
                    $error_message = "Gagal membuat transaksi Tripay: " . ($response_data['message'] ?? 'Unknown error');
                }
            }
        } else {
            $loading_channels = true;
            $url = $is_production ? 'https://tripay.co.id/api/merchant/payment-channel' : 'https://tripay.co.id/api-sandbox/merchant/payment-channel';
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $api_key],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_FRESH_CONNECT  => true,
                CURLOPT_HEADER         => false,
                CURLOPT_FAILONERROR    => false,
                CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
            ));
            $response = curl_exec($ch);
            $curl_error = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($curl_error) {
                 $fetch_channels_error = "Kesalahan cURL saat mengambil channel: " . $curl_error;
            } elseif ($http_code != 200) {
                 $fetch_channels_error = "Gagal terhubung ke API Tripay. Kode HTTP: " . $http_code;
                 $response_data = json_decode($response, true);
                 if (isset($response_data['message'])) {
                     $fetch_channels_error .= " - Pesan dari Tripay: " . $response_data['message'];
                 }
            } else {
                $response_data = json_decode($response, true);
                if (isset($response_data['success']) && $response_data['success'] == true) {
                    $tripay_channels = $response_data['data'];
                    if (empty($tripay_channels)) {
                        $fetch_channels_error = "Tidak ada metode pembayaran yang aktif di akun Tripay Anda. Mohon aktifkan setidaknya satu channel di dashboard Tripay.";
                    }
                } else {
                    $fetch_channels_error = "Gagal mengambil daftar channel pembayaran: " . ($response_data['message'] ?? 'Respons tidak valid.');
                }
            }
            $loading_channels = false;
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
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <style> body { font-family: 'Inter', sans-serif; } </style>
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
        
        <?php elseif (!empty($fetch_channels_error)): ?>
             <h1 class="text-2xl font-bold text-red-600 mb-4">Terjadi Kesalahan</h1>
            <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($fetch_channels_error); ?></p>
            <p class="text-gray-600 mb-6">Pastikan Kode Merchant, API Key, dan Private Key Tripay di halaman pengaturan sudah benar.</p>
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

        <?php elseif ($active_gateway == 'tripay' && $loading_channels): ?>
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Memuat Metode Pembayaran...</h1>
            <p class="text-gray-600 mb-6">Harap tunggu sebentar.</p>

        <?php elseif ($active_gateway == 'tripay' && !empty($tripay_channels)): ?>
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Pilih Metode Pembayaran</h1>
            <div class="mb-6">
                <p class="text-sm font-semibold text-gray-700">Total Tagihan:</p>
                <p class="text-2xl font-bold text-gray-900" id="base-amount-display">Rp <?php echo number_format($total_amount, 0, ',', '.'); ?></p>
            </div>
            
            <form method="POST" action="client_pay.php?cust_id=<?php echo $customer_id; ?>&ids=<?php echo $invoice_ids_str; ?>">
                <input type="hidden" name="final_amount" id="final-amount-input" value="<?php echo $total_amount; ?>">
                 <div class="space-y-4 max-h-80 overflow-y-auto mb-6">
                    <?php foreach ($tripay_channels as $channel): ?>
                    <label class="flex items-center p-4 rounded-lg bg-gray-50 border border-gray-200 cursor-pointer hover:bg-gray-100">
                        <input type="radio" name="channel_code" value="<?php echo htmlspecialchars($channel['code']); ?>" 
                               class="form-radio h-4 w-4 text-blue-600" required
                               data-fee-flat="<?php echo htmlspecialchars($channel['total_fee']['flat']); ?>"
                               data-fee-percent="<?php echo htmlspecialchars($channel['total_fee']['percent']); ?>">
                        <div class="ml-4 text-left flex-grow flex items-center justify-between">
                            <div class="flex items-center">
                                <img src="<?php echo htmlspecialchars($channel['icon_url']); ?>" alt="Logo <?php echo htmlspecialchars($channel['name']); ?>" class="w-8 h-8 mr-4 rounded">
                                <div>
                                    <span class="block font-medium text-gray-900"><?php echo htmlspecialchars($channel['name']); ?></span>
                                    <span class="block text-xs text-gray-500">
                                        Biaya Layanan: Rp <?php echo number_format($channel['total_fee']['flat'], 0, ',', '.'); ?> + <?php echo number_format($channel['total_fee']['percent'], 2, ',', '.'); ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                 </div>
                 <div class="border-t border-gray-200 pt-4 mt-4 flex justify-between items-center font-bold text-lg">
                    <span>Total Pembayaran</span>
                    <span id="final-amount-display">Rp <?php echo number_format($total_amount, 0, ',', '.'); ?></span>
                </div>
                 <button type="submit" class="w-full text-white bg-blue-600 hover:bg-blue-700 font-medium rounded-lg text-sm px-5 py-3 text-center mt-4">
                    Bayar Sekarang
                 </button>
            </form>

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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const radioButtons = document.querySelectorAll('input[name="channel_code"]');
            const baseAmount = <?php echo $total_amount; ?>;
            const finalAmountDisplay = document.getElementById('final-amount-display');
            const finalAmountInput = document.getElementById('final-amount-input');

            function updateFinalAmount() {
                const selectedChannel = document.querySelector('input[name="channel_code"]:checked');
                let finalAmount = baseAmount;
                if (selectedChannel) {
                    const feeFlat = parseFloat(selectedChannel.dataset.feeFlat);
                    const feePercent = parseFloat(selectedChannel.dataset.feePercent);
                    
                    const feeAmount = feeFlat + (baseAmount * feePercent / 100);
                    finalAmount = baseAmount + feeAmount;
                }
                
                finalAmountDisplay.textContent = `Rp ${new Intl.NumberFormat('id-ID').format(Math.ceil(finalAmount))}`;
                finalAmountInput.value = Math.ceil(finalAmount);
            }

            radioButtons.forEach(radio => {
                radio.addEventListener('change', updateFinalAmount);
            });
            
            // Inisialisasi tampilan total saat halaman dimuat
            updateFinalAmount();

            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebarMenu = document.getElementById('sidebar-menu');
            const sidebarOverlay = document.getElementById('sidebar-overlay');

            sidebarToggle.addEventListener('click', function() {
                sidebarMenu.classList.toggle('-translate-x-full');
                sidebarMenu.classList.toggle('translate-x-0');
                sidebarOverlay.classList.toggle('hidden');
            });

            sidebarOverlay.addEventListener('click', function() {
                sidebarMenu.classList.add('-translate-x-full');
                sidebarMenu.classList.remove('translate-x-0');
                sidebarOverlay.classList.add('hidden');
            });
        });
    </script>
</body>
</html>
