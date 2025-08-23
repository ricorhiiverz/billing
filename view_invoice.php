<?php
require_once 'config.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: invoices.php");
    exit;
}
$invoice_id = $_GET['id'];

$sql = "SELECT i.*, c.id as customer_id, c.name as customer_name, c.address as customer_address, c.phone_number as customer_phone, p.name as package_name
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        JOIN packages p ON c.package_id = p.id
        WHERE i.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header("location: invoices.php");
    exit;
}

$sql_payment = "SELECT p.*, u.email as confirmed_by_user 
                FROM payments p 
                LEFT JOIN users u ON p.confirmed_by = u.id 
                WHERE p.invoice_id = ?";
$stmt_payment = $pdo->prepare($sql_payment);
$stmt_payment->execute([$invoice_id]);
$payment = $stmt_payment->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Tagihan - Billing ISP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-100">

<div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-10 hidden"></div>

<div class="relative min-h-screen md:flex">
    <?php include 'sidebar.php'; ?>

    <div class="flex-1 flex flex-col md:ml-64">
        <header class="flex items-center justify-between h-16 bg-white border-b border-gray-200 px-4">
            <button id="sidebar-toggle" class="md:hidden text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
            <div class="flex-1 flex justify-end items-center">
                 <span class="text-gray-600 mr-4 text-sm md:text-base">Halo, <b><?php echo htmlspecialchars($_SESSION["email"]); ?></b></span>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium">Logout</a>
            </div>
        </header>

        <main class="p-4 md:p-8 flex-1 overflow-y-auto">
            <div class="max-w-4xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Detail Tagihan</h2>
                    <a href="view_customer_invoices.php?id=<?php echo $invoice['customer_id']; ?>" class="text-blue-600 hover:underline flex-shrink-0">Kembali ke Daftar Tagihan Pelanggan</a>
                </div>

                <?php if ($invoice['requires_manual_activation']): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6 flex flex-col sm:flex-row justify-between items-center gap-4" role="alert">
                    <div>
                        <p class="font-bold">Aktivasi Otomatis Gagal!</p>
                        <p>Pelanggan ini sudah membayar, namun sistem gagal mengaktifkan layanannya di router.</p>
                    </div>
                    <form action="resolve_activation_issue.php" method="POST" onsubmit="return confirm('Anda yakin masalah ini sudah diselesaikan?');">
                        <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg w-full sm:w-auto">
                            Tandai Selesai
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
                    <!-- ... (Detail tagihan seperti nomor, periode, dll. tetap di sini) ... -->
                    <div class="flex flex-col sm:flex-row justify-between items-start mb-8 gap-4">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 break-all"><?php echo htmlspecialchars($invoice['invoice_number']); ?></h3>
                            <p class="text-gray-500">Periode: <?php echo date('F Y', strtotime($invoice['billing_period'])); ?></p>
                        </div>
                        <div class="flex-shrink-0">
                             <?php
                                $status_class = '';
                                if ($invoice['status'] == 'PAID') $status_class = 'bg-green-100 text-green-800';
                                elseif ($invoice['status'] == 'UNPAID') $status_class = 'bg-yellow-100 text-yellow-800';
                                else $status_class = 'bg-red-100 text-red-800';
                            ?>
                            <span class="px-4 py-2 inline-flex text-sm leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars($invoice['status']); ?>
                            </span>
                        </div>
                    </div>
                    <!-- ... (Sisa detail tagihan) ... -->

                    <?php if ($invoice['status'] == 'PAID' && $payment): ?>
                        <div class="bg-green-50 p-6 rounded-lg border border-green-200 mt-8">
                            <h3 class="text-lg font-semibold text-green-800 mb-4">Informasi Pembayaran</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div><p class="text-sm text-gray-500">Tanggal Bayar</p><p class="font-medium text-gray-800"><?php echo date('d F Y, H:i', strtotime($payment['payment_date'])); ?></p></div>
                                <div><p class="text-sm text-gray-500">Metode</p><p class="font-medium text-gray-800"><?php echo ucfirst($payment['payment_method']); ?></p></div>
                                <div><p class="text-sm text-gray-500">Jumlah Dibayar</p><p class="font-medium text-gray-800">Rp <?php echo number_format($payment['amount_paid'], 0, ',', '.'); ?></p></div>
                                <?php if($payment['discount_amount'] > 0): ?>
                                <div><p class="text-sm text-gray-500">Diskon Diberikan</p><p class="font-medium text-gray-800">Rp <?php echo number_format($payment['discount_amount'], 0, ',', '.'); ?></p></div>
                                <?php endif; ?>
                                <?php if($payment['payment_method'] == 'cash'): ?>
                                <div><p class="text-sm text-gray-500">Dikonfirmasi Oleh</p><p class="font-medium text-gray-800"><?php echo htmlspecialchars($payment['confirmed_by_user']); ?></p></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    
                    <?php elseif (($_SESSION['role'] == 'admin') && ($invoice['status'] == 'UNPAID' || $invoice['status'] == 'OVERDUE')): ?>
                        <!-- --- FORM KHUSUS ADMIN UNTUK PEMBAYARAN & DISKON --- -->
                        <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 mt-8">
                             <h3 class="text-lg font-semibold text-gray-800 mb-4">Konfirmasi Pembayaran (Admin)</h3>
                             <form id="payment-form" action="process_payment.php" method="POST" onsubmit="return confirm('Anda yakin ingin mengonfirmasi pembayaran ini?');">
                                <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Total Tagihan</label>
                                        <input type="text" id="total_amount_display" class="mt-1 w-full bg-gray-200 rounded-md border-gray-300 p-2" value="Rp <?php echo number_format($invoice['total_amount'], 0, ',', '.'); ?>" readonly>
                                        <input type="hidden" id="total_amount" value="<?php echo $invoice['total_amount']; ?>">
                                    </div>
                                    <div>
                                        <label for="discount" class="block text-sm font-medium text-gray-700">Diskon (Rp)</label>
                                        <input type="number" id="discount" name="discount" class="mt-1 w-full rounded-md border-gray-300 shadow-sm p-2" value="0" min="0">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Jumlah Bayar Final</label>
                                        <input type="text" id="final_amount_display" class="mt-1 w-full bg-gray-200 rounded-md border-gray-300 p-2" readonly>
                                        <input type="hidden" id="amount_paid" name="amount_paid" value="<?php echo $invoice['total_amount']; ?>">
                                    </div>
                                </div>
                                
                                <div class="text-right mt-6">
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg w-full sm:w-auto">
                                        Konfirmasi Lunas
                                    </button>
                                </div>
                             </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const totalAmount = document.getElementById('total_amount');
        const discountInput = document.getElementById('discount');
        const finalAmountDisplay = document.getElementById('final_amount_display');
        const amountPaidInput = document.getElementById('amount_paid');

        function calculateFinalAmount() {
            const total = parseFloat(totalAmount.value) || 0;
            let discount = parseFloat(discountInput.value) || 0;

            if (discount > total) {
                discount = total;
                discountInput.value = total;
            }
            if (discount < 0) {
                discount = 0;
                discountInput.value = 0;
            }

            const finalAmount = total - discount;
            
            finalAmountDisplay.value = 'Rp ' + new Intl.NumberFormat('id-ID').format(finalAmount);
            amountPaidInput.value = finalAmount;
        }

        if (discountInput) {
            discountInput.addEventListener('input', calculateFinalAmount);
            // Hitung pertama kali saat halaman dimuat
            calculateFinalAmount();
        }

        // ... (kode sidebar toggle tetap sama) ...
    });
</script>

</body>
</html>
