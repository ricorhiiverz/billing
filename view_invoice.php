<?php
require_once 'config.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Cek ID di URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: invoices.php");
    exit;
}
$invoice_id = $_GET['id'];

// Ambil data invoice
$sql = "SELECT i.*, c.id as customer_id, c.name as customer_name, c.address as customer_address, 
        c.phone_number as customer_phone, p.name as package_name,
        pay.payment_method
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        JOIN packages p ON c.package_id = p.id
        LEFT JOIN payments pay ON i.id = pay.invoice_id
        WHERE i.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header("location: invoices.php");
    exit;
}

// Ambil data pembayaran jika ada
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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-y py-6">
                        <div>
                           <h4 class="text-sm font-medium text-gray-500 mb-2">Ditujukan Kepada:</h4>
                           <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                           <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($invoice['customer_address'])); ?></p>
                           <p class="text-gray-600"><?php echo htmlspecialchars($invoice['customer_phone']); ?></p>
                        </div>
                         <div>
                           <h4 class="text-sm font-medium text-gray-500 mb-2">Detail Tanggal:</h4>
                           <div class="space-y-1">
                                <p><span class="font-semibold text-gray-700">Tanggal Dibuat:</span> <?php echo date('d F Y', strtotime($invoice['created_at'])); ?></p>
                                <p><span class="font-semibold text-gray-700">Jatuh Tempo:</span> <?php echo date('d F Y', strtotime($invoice['due_date'])); ?></p>
                           </div>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <h4 class="text-sm font-medium text-gray-500 mb-2">Rincian:</h4>
                        <div class="space-y-2">
                             <div class="flex justify-between items-center">
                                <p class="text-gray-600">Layanan Internet: <?php echo htmlspecialchars($invoice['package_name']); ?></p>
                                <p class="text-gray-800 font-medium">Rp <?php echo number_format($invoice['amount'], 0, ',', '.'); ?></p>
                            </div>
                            <?php if ($invoice['ppn_amount'] > 0): ?>
                            <div class="flex justify-between items-center">
                                <p class="text-gray-600">PPN</p>
                                <p class="text-gray-800 font-medium">Rp <?php echo number_format($invoice['ppn_amount'], 0, ',', '.'); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mt-6 pt-4 border-t-2 border-gray-200">
                        <div class="flex justify-between items-center">
                            <p class="text-lg font-bold text-gray-900">Total</p>
                            <p class="text-lg font-bold text-gray-900">Rp <?php echo number_format($invoice['total_amount'], 0, ',', '.'); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($invoice['status'] == 'PAID' && $payment): ?>
                        <div class="bg-green-50 p-6 rounded-lg border border-green-200 mt-8">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-green-800">Informasi Pembayaran</h3>
                                <?php if ($_SESSION['role'] == 'admin' && $invoice['payment_method'] == 'cash'): ?>
                                    <a href="cancel_payment.php?invoice_id=<?php echo $invoice['id']; ?>&customer_id=<?php echo $invoice['customer_id']; ?>&period=<?php echo $invoice['billing_period']; ?>" 
                                       onclick="return confirm('Anda yakin ingin membatalkan pembayaran ini? Aksi ini akan mengembalikan status tagihan menjadi BELUM LUNAS.');"
                                       title="Batalkan Pembayaran"
                                       class="text-sm text-red-600 hover:text-red-800 flex items-center gap-1">
                                       <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                       Batalkan
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div><p class="text-sm text-gray-500">Tanggal Bayar</p><p class="font-medium text-gray-800"><?php echo date('d F Y, H:i', strtotime($payment['payment_date'])); ?></p></div>
                                <div><p class="text-sm text-gray-500">Metode</p><p class="font-medium text-gray-800 capitalize"><?php echo htmlspecialchars($payment['payment_method']); ?></p></div>
                                <div><p class="text-sm text-gray-500">Jumlah Dibayar</p><p class="font-medium text-gray-800">Rp <?php echo number_format($payment['amount_paid'], 0, ',', '.'); ?></p></div>
                                <?php if($payment['discount_amount'] > 0): ?>
                                <div><p class="text-sm text-gray-500">Diskon Diberikan</p><p class="font-medium text-gray-800">Rp <?php echo number_format($payment['discount_amount'], 0, ',', '.'); ?></p></div>
                                <?php endif; ?>
                                <?php if($payment['payment_method'] == 'cash'): ?>
                                <div><p class="text-sm text-gray-500">Dikonfirmasi Oleh</p><p class="font-medium text-gray-800"><?php echo htmlspecialchars($payment['confirmed_by_user'] ?? 'N/A'); ?></p></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mt-8 pt-6 border-t text-center">
                        <a href="invoices.php" class="inline-flex items-center gap-2 bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                            Kembali ke Daftar Tagihan
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
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

