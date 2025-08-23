<?php
require_once 'config.php';

$error_message = '';
$customer = null;
$invoices = [];
$total_tunggakan = 0;
$invoice_ids = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['customer_number'])) {
    $customer_number = trim($_POST['customer_number']);

    if (empty($customer_number) || !ctype_digit($customer_number) || strlen($customer_number) != 10) {
        $error_message = "Harap masukkan 10 digit ID Pelanggan yang valid.";
    } else {
        $stmt_customer = $pdo->prepare("SELECT id, name FROM customers WHERE customer_number = ?");
        $stmt_customer->execute([$customer_number]);
        $customer = $stmt_customer->fetch();

        if ($customer) {
            $sql = "SELECT id, invoice_number, total_amount, due_date, status, billing_period
                    FROM invoices 
                    WHERE customer_id = ? AND status IN ('UNPAID', 'OVERDUE') 
                    ORDER BY created_at ASC"; // Urutkan dari yang paling lama
            $stmt_invoices = $pdo->prepare($sql);
            $stmt_invoices->execute([$customer['id']]);
            $invoices = $stmt_invoices->fetchAll();

            if (empty($invoices)) {
                $error_message = "Tidak ada tagihan yang perlu dibayar saat ini.";
            } else {
                // Hitung total dan kumpulkan ID invoice
                foreach ($invoices as $invoice) {
                    $total_tunggakan += $invoice['total_amount'];
                    $invoice_ids[] = $invoice['id'];
                }
            }
        } else {
            $error_message = "ID Pelanggan tidak ditemukan.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek & Bayar Tagihan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-100">
    <main class="max-w-2xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Cek Tagihan Anda</h1>
                <p class="text-gray-500">Masukkan 10 digit ID Pelanggan Anda untuk melanjutkan pembayaran.</p>
            </div>
            <form action="cek_tagihan.php" method="POST">
                <div class="mb-5">
                    <label for="customer_number" class="block mb-2 text-sm font-medium text-gray-600">ID Pelanggan</label>
                    <input type="text" id="customer_number" name="customer_number" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" placeholder="Contoh: 1234567890" required maxlength="10">
                </div>
                <button type="submit" class="w-full text-white bg-blue-600 hover:bg-blue-700 font-medium rounded-lg text-sm px-5 py-3 text-center">
                    Cari Tagihan
                </button>
            </form>
        </div>

        <?php if (!empty($error_message) && !$customer): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mt-6" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($customer && !empty($invoices)): ?>
        <div class="mt-8 bg-white p-6 md:p-8 rounded-xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-800 mb-2">Tagihan untuk <?php echo htmlspecialchars($customer['name']); ?></h2>
            <p class="text-gray-600 mb-4">Anda memiliki <?php echo count($invoices); ?> tagihan yang belum lunas. Semua tagihan harus dibayar sekaligus.</p>
            
            <div class="border-t border-b border-gray-200 divide-y divide-gray-200 mb-4">
                <?php foreach ($invoices as $invoice): ?>
                    <div class="py-3 flex justify-between items-center">
                        <div>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                            <p class="text-sm text-gray-500">Periode: <?php echo date('F Y', strtotime($invoice['billing_period'])); ?></p>
                        </div>
                        <p class="font-semibold text-gray-900">Rp <?php echo number_format($invoice['total_amount'], 0, ',', '.'); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-200 flex justify-between items-center">
                <div class="font-bold text-lg text-gray-900">
                    <p>Total Pembayaran</p>
                    <p>Rp <?php echo number_format($total_tunggakan, 0, ',', '.'); ?></p>
                </div>
                <a href="client_pay.php?cust_id=<?php echo $customer['id']; ?>&ids=<?php echo implode(',', $invoice_ids); ?>" class="font-medium text-white bg-blue-600 hover:bg-blue-700 px-6 py-3 rounded-lg text-lg">
                    Bayar Total Tagihan
                </a>
            </div>
        </div>
        <?php elseif ($customer && empty($invoices)): ?>
             <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mt-6" role="alert">
                <strong class="font-bold">Terima kasih, <?php echo htmlspecialchars($customer['name']); ?>!</strong>
                <span class="block sm:inline"> Anda tidak memiliki tagihan yang belum dibayar saat ini.</span>
            </div>
        <?php endif; ?>

    </main>
</body>
</html>