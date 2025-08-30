<?php
require_once 'config.php';

// Proteksi Halaman (admin & collector)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION['role'], ['admin', 'collector'])) {
    header("location: login.php");
    exit;
}

// --- PERSIAPAN DATA UNTUK FILTER ---
$stmt_months = $pdo->query("SELECT DISTINCT DATE_FORMAT(payment_date, '%Y-%m') as payment_month FROM payments ORDER BY payment_month DESC");
$available_months = $stmt_months->fetchAll(PDO::FETCH_COLUMN);

$stmt_wilayah = $pdo->query("SELECT id, nama_wilayah FROM wilayah ORDER BY nama_wilayah ASC");
$wilayah_list = $stmt_wilayah->fetchAll();

// --- LOGIKA FILTER BERDASARKAN PERAN ---
$selected_month = $_GET['month'] ?? ($available_months[0] ?? date('Y-m'));
$selected_wilayah = $_GET['wilayah'] ?? 'all';
$selected_user_info = ''; // Untuk PDF

if ($_SESSION['role'] == 'admin') {
    $selected_user = $_GET['user'] ?? 'all';
    $selected_method = $_GET['method'] ?? 'all';
    // Ambil data user hanya jika admin
    $stmt_users = $pdo->query("SELECT id, email, role FROM users WHERE role IN ('admin', 'collector') ORDER BY role, email");
    $users = $stmt_users->fetchAll();
} else { // Untuk Collector
    $selected_user = $_SESSION['id'];
    $selected_method = 'cash';
    $users = [];
}

$where_clauses = ["DATE_FORMAT(p.payment_date, '%Y-%m') = ?"];
$params = [$selected_month];

// Filter berdasarkan metode pembayaran
if ($selected_method == 'cash') {
    $where_clauses[] = "p.payment_method = 'cash'";
} elseif ($selected_method == 'online') {
    $where_clauses[] = "p.payment_method != 'cash'";
}

// Filter berdasarkan wilayah
if ($selected_wilayah != 'all' && is_numeric($selected_wilayah)) {
    $where_clauses[] = "c.wilayah_id = ?";
    $params[] = $selected_wilayah;
}

// Filter berdasarkan siapa yang mengonfirmasi
if ($selected_method != 'online') {
    if ($_SESSION['role'] == 'admin') {
        if ($selected_user == 'admins') {
            $where_clauses[] = "u.role = 'admin'";
        } elseif ($selected_user == 'collectors') {
            $where_clauses[] = "u.role = 'collector'";
        } elseif (is_numeric($selected_user)) {
            $where_clauses[] = "p.confirmed_by = ?";
            $params[] = $selected_user;
        }
    } else { // Untuk collector, selalu filter berdasarkan ID-nya
        $where_clauses[] = "p.confirmed_by = ?";
        $params[] = $_SESSION['id'];
    }
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// --- EKSEKUSI QUERY UTAMA ---
$sql_payments = "SELECT 
                    p.payment_date, p.amount_paid, p.discount_amount, p.payment_method, 
                    i.invoice_number, i.billing_period, c.name as customer_name,
                    u.email as confirmed_by_user
                 FROM payments p
                 JOIN invoices i ON p.invoice_id = i.id
                 JOIN customers c ON i.customer_id = c.id
                 LEFT JOIN users u ON p.confirmed_by = u.id
                 {$where_sql}
                 ORDER BY p.payment_date DESC";
$stmt_payments = $pdo->prepare($sql_payments);
$stmt_payments->execute($params);
$payments = $stmt_payments->fetchAll();

$total_income = array_sum(array_column($payments, 'amount_paid'));
$total_discount = array_sum(array_column($payments, 'discount_amount'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Billing ISP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
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
            <h2 class="text-3xl font-bold text-gray-800 mb-6">Laporan Pendapatan</h2>

            <div class="bg-white p-6 rounded-xl shadow-lg mb-6">
                <form action="reports.php" method="GET" class="flex flex-wrap items-end gap-4">
                    <div class="flex-grow min-w-[150px]">
                        <label for="month" class="block text-sm font-medium text-gray-700">Bulan</label>
                        <select id="month" name="month" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2">
                            <?php foreach($available_months as $month): ?>
                                <option value="<?php echo $month; ?>" <?php echo ($month == $selected_month) ? 'selected' : ''; ?>><?php echo date('F Y', strtotime($month . '-01')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex-grow min-w-[150px]">
                        <label for="wilayah" class="block text-sm font-medium text-gray-700">Wilayah</label>
                        <select id="wilayah" name="wilayah" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2">
                            <option value="all">Semua Wilayah</option>
                            <?php foreach($wilayah_list as $wilayah): ?>
                                <option value="<?php echo $wilayah['id']; ?>" <?php echo ($selected_wilayah == $wilayah['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($wilayah['nama_wilayah']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                    <div class="flex-grow min-w-[150px]">
                        <label for="method" class="block text-sm font-medium text-gray-700">Via</label>
                        <select id="method" name="method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2">
                            <option value="all" <?php echo ($selected_method == 'all') ? 'selected' : ''; ?>>Semua</option>
                            <option value="cash" <?php echo ($selected_method == 'cash') ? 'selected' : ''; ?>>Tunai</option>
                            <option value="online" <?php echo ($selected_method == 'online') ? 'selected' : ''; ?>>Online</option>
                        </select>
                    </div>
                    <div class="flex-grow min-w-[150px]">
                        <label for="user" class="block text-sm font-medium text-gray-700">Oleh</label>
                        <select id="user" name="user" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2">
                            <option value="all" <?php echo ($selected_user == 'all') ? 'selected' : ''; ?>>Semua</option>
                            <option value="admins" <?php echo ($selected_user == 'admins') ? 'selected' : ''; ?>>Admin</option>
                            <option value="collectors" <?php echo ($selected_user == 'collectors') ? 'selected' : ''; ?>>Penagih</option>
                            <optgroup label="Spesifik">
                                <?php foreach($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo ($selected_user == $user['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($user['email']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="flex-shrink-0 flex gap-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg w-full sm:w-auto">Filter</button>
                        <button type="button" id="export-pdf" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg w-full sm:w-auto">Ekspor</button>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white p-6 rounded-xl shadow-lg">
                    <p class="text-sm font-medium text-gray-500">Total Pendapatan</p>
                    <p class="text-3xl font-bold text-gray-800">Rp <?php echo number_format($total_income, 0, ',', '.'); ?></p>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-lg">
                    <p class="text-sm font-medium text-gray-500">Total Transaksi</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo count($payments); ?></p>
                </div>
                 <div class="bg-white p-6 rounded-xl shadow-lg">
                    <p class="text-sm font-medium text-gray-500">Total Diskon</p>
                    <p class="text-3xl font-bold text-gray-800">Rp <?php echo number_format($total_discount, 0, ',', '.'); ?></p>
                </div>
            </div>

            <!-- Tampilan Tabel untuk Desktop -->
            <div class="hidden md:block bg-white rounded-xl shadow-lg overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Tanggal Bayar</th>
                            <th scope="col" class="px-6 py-3">Pelanggan</th>
                            <th scope="col" class="px-6 py-3">Metode</th>
                            <th scope="col" class="px-6 py-3">Dikonfirmasi Oleh</th>
                            <th scope="col" class="px-6 py-3 text-right">Diskon</th>
                            <th scope="col" class="px-6 py-3 text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr class="bg-white border-b"><td colspan="6" class="px-6 py-4 text-center">Tidak ada transaksi yang cocok dengan filter.</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4"><?php echo date('d M Y, H:i', strtotime($payment['payment_date'])); ?></td>
                                    <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                                    <td class="px-6 py-4"><span class="capitalize"><?php echo htmlspecialchars($payment['payment_method']); ?></span></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($payment['confirmed_by_user'] ?? 'Online'); ?></td>
                                    <td class="px-6 py-4 text-right">Rp <?php echo number_format($payment['discount_amount'], 0, ',', '.'); ?></td>
                                    <td class="px-6 py-4 text-right">Rp <?php echo number_format($payment['amount_paid'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- (BARU) Tampilan Kartu untuk Mobile -->
            <div class="md:hidden space-y-4">
                 <?php if (empty($payments)): ?>
                    <div class="bg-white p-4 rounded-lg shadow text-center text-gray-500">Tidak ada transaksi yang cocok.</div>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                        <div class="bg-white p-4 rounded-lg shadow">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-bold text-gray-800"><?php echo htmlspecialchars($payment['customer_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo date('d M Y, H:i', strtotime($payment['payment_date'])); ?></div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-lg text-green-600">Rp <?php echo number_format($payment['amount_paid'], 0, ',', '.'); ?></div>
                                    <?php if($payment['discount_amount'] > 0): ?>
                                        <div class="text-xs text-red-500">Diskon Rp <?php echo number_format($payment['discount_amount'], 0, ',', '.'); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-t text-xs text-gray-500">
                                Dikonfirmasi oleh: <span class="font-medium text-gray-700"><?php echo htmlspecialchars($payment['confirmed_by_user'] ?? 'Online'); ?></span>
                                via <span class="font-medium text-gray-700 capitalize"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- FUNGSI EKSPOR PDF ---
        document.getElementById('export-pdf').addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            const paymentsData = <?php echo json_encode($payments); ?>;
            const selectedMonthText = "<?php echo date('F Y', strtotime($selected_month . '-01')); ?>";
            const totalIncome = "<?php echo 'Rp ' . number_format($total_income, 0, ',', '.'); ?>";
            const totalTransactions = "<?php echo count($payments); ?>";
            const totalDiscount = "<?php echo 'Rp ' . number_format($total_discount, 0, ',', '.'); ?>";
            
            doc.setFontSize(18);
            doc.text("Laporan Pendapatan", 14, 22);
            doc.setFontSize(11);
            doc.setTextColor(100);
            doc.text(`Periode Pembayaran: ${selectedMonthText}`, 14, 30);
            
            <?php if ($_SESSION['role'] == 'collector'): ?>
            doc.setFontSize(10);
            doc.text("Collector: <?php echo htmlspecialchars($_SESSION['email']); ?>", 14, 36);
            <?php endif; ?>

            doc.autoTable({
                startY: 42,
                head: [['Total Pendapatan', 'Total Transaksi', 'Total Diskon']],
                body: [[totalIncome, totalTransactions, totalDiscount]],
                theme: 'grid',
                styles: { fontStyle: 'bold', halign: 'center' }
            });
            const tableData = paymentsData.map(p => [
                p.customer_name,
                p.invoice_number,
                new Date(p.payment_date).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }),
                new Date(p.billing_period + '-01').toLocaleDateString('id-ID', { month: 'short', year: 'numeric'}),
                p.confirmed_by_user || 'Online',
                p.payment_method.charAt(0).toUpperCase() + p.payment_method.slice(1),
                new Intl.NumberFormat('id-ID').format(p.amount_paid),
                new Intl.NumberFormat('id-ID').format(p.discount_amount)
            ]);
            doc.autoTable({
                startY: doc.lastAutoTable.finalY + 10,
                head: [['Nama Pelanggan', 'No. Invoice', 'Tgl Bayar', 'Bulan Bayar', 'Oleh', 'Via', 'Jumlah Bayar (Rp)', 'Diskon (Rp)']],
                body: tableData,
                theme: 'striped',
                headStyles: { fillColor: [22, 160, 133] },
                didParseCell: function (data) {
                    if (data.column.index >= 6) data.cell.styles.halign = 'right';
                }
            });
            doc.save(`Laporan_Pendapatan_${selectedMonthText.replace(' ', '_')}.pdf`);
        });

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

