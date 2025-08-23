<?php
require_once 'config.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Validasi ID Pelanggan dan Periode dari URL
if (!isset($_GET['id']) || empty($_GET['id']) || !isset($_GET['period'])) {
    header("location: customers.php");
    exit;
}
$customer_id = $_GET['id'];
$selected_period = $_GET['period'];

// Ambil detail pelanggan
$stmt_customer = $pdo->prepare("SELECT id, name FROM customers WHERE id = ?");
$stmt_customer->execute([$customer_id]);
$customer = $stmt_customer->fetch();
if (!$customer) {
    header("location: customers.php");
    exit;
}

// Ambil SEMUA tagihan (lunas & belum lunas) HINGGA periode yang dipilih
$sql = "SELECT i.id, i.invoice_number, i.amount, i.ppn_amount, i.total_amount, i.status, i.billing_period, p.payment_method 
        FROM invoices i
        LEFT JOIN payments p ON i.id = p.invoice_id
        WHERE i.customer_id = ?
        ORDER BY i.billing_period ASC";
$stmt_invoices = $pdo->prepare($sql);
$stmt_invoices->execute([$customer_id]);
$all_invoices = $stmt_invoices->fetchAll();

// Pisahkan invoice yang belum lunas untuk form pembayaran
$unpaid_invoices = [];
foreach ($all_invoices as $invoice) {
    if (($invoice['status'] == 'UNPAID' || $invoice['status'] == 'OVERDUE') && $invoice['billing_period'] <= $selected_period) {
        $unpaid_invoices[] = $invoice;
    }
}

// Hitung total tunggakan dan kumpulkan ID dari invoice yang belum lunas
$total_tunggakan = 0;
$invoice_ids_to_pay = [];
foreach ($unpaid_invoices as $invoice) {
    $total_tunggakan += $invoice['total_amount'];
    $invoice_ids_to_pay[] = $invoice['id'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tagihan Pelanggan - Billing ISP</title>
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
                    <div>
                        <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Riwayat Tagihan</h2>
                        <p class="text-lg text-gray-600"><?php echo htmlspecialchars($customer['name']); ?></p>
                    </div>
                    <a href="invoices.php?period=<?php echo $selected_period; ?>" class="text-blue-600 hover:underline flex-shrink-0">Kembali ke Daftar Tagihan</a>
                </div>

                <!-- Notifikasi -->
                <?php if(isset($_SESSION['success_message'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $_SESSION['success_message']; ?></span>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                <?php if(isset($_SESSION['error_message'])): ?>
                     <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Semua Tagihan</h3>
                    <div class="border rounded-lg overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Periode</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($all_invoices as $invoice): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo date('F Y', strtotime($invoice['billing_period'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php
                                            $status_class = '';
                                            if ($invoice['status'] == 'PAID') $status_class = 'bg-green-100 text-green-800';
                                            elseif ($invoice['status'] == 'UNPAID') $status_class = 'bg-yellow-100 text-yellow-800';
                                            else $status_class = 'bg-red-100 text-red-800';
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($invoice['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-semibold">Rp <?php echo number_format($invoice['total_amount'], 0, ',', '.'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <?php if ($invoice['status'] == 'PAID' && $_SESSION['role'] == 'admin' && $invoice['payment_method'] == 'cash'): ?>
                                            <a href="cancel_payment.php?invoice_id=<?php echo $invoice['id']; ?>&customer_id=<?php echo $customer_id; ?>&period=<?php echo $selected_period; ?>" 
                                               onclick="return confirm('Anda yakin ingin membatalkan pembayaran ini? Aksi ini tidak dapat diurungkan.');"
                                               title="Batalkan Pembayaran"
                                               class="text-red-500 hover:text-red-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($unpaid_invoices)): ?>
                        <div class="mt-8 pt-8 border-t">
                            <h3 class="text-lg font-semibold text-gray-700 mb-4">Konfirmasi Pembayaran Tertunggak</h3>
                            <p class="text-sm text-gray-600 mb-4">Formulir ini untuk melunasi semua tagihan yang belum dibayar hingga periode **<?php echo date('F Y', strtotime($selected_period . '-01')); ?>**.</p>
                            <form id="payment-form" action="process_payment.php" method="POST" onsubmit="return confirm('Anda yakin ingin mengonfirmasi pembayaran ini?');">
                                <input type="hidden" name="invoice_ids" value="<?php echo implode(',', $invoice_ids_to_pay); ?>">
                                
                                <?php if ($_SESSION['role'] == 'admin'): ?>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end p-6 bg-gray-50 rounded-lg border">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Total Tagihan</label>
                                        <input type="text" class="mt-1 w-full bg-gray-200 rounded-md border-gray-300 p-2" value="Rp <?php echo number_format($total_tunggakan, 0, ',', '.'); ?>" readonly>
                                        <input type="hidden" id="total_amount" value="<?php echo $total_tunggakan; ?>">
                                    </div>
                                    <div>
                                        <label for="discount" class="block text-sm font-medium text-gray-700">Diskon (Rp)</label>
                                        <input type="number" id="discount" name="discount" class="mt-1 w-full rounded-md border-gray-300 shadow-sm p-2" value="0" min="0">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Jumlah Bayar Final</label>
                                        <input type="text" id="final_amount_display" class="mt-1 w-full bg-gray-200 rounded-md border-gray-300 p-2 font-bold" readonly>
                                        <input type="hidden" id="amount_paid" name="amount_paid" value="<?php echo $total_tunggakan; ?>">
                                    </div>
                                </div>
                                <?php else: ?>
                                    <input type="hidden" name="amount_paid" value="<?php echo $total_tunggakan; ?>">
                                <?php endif; ?>
                                
                                <div class="text-right mt-6">
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg w-full sm:w-auto">
                                        Konfirmasi Pembayaran Tunai
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
        if (document.getElementById('discount')) {
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
            discountInput.addEventListener('input', calculateFinalAmount);
            calculateFinalAmount();
        }

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
