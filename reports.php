<?php
require_once 'config.php';

// Proteksi Halaman (hanya admin)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

// --- PERSIAPAN DATA UNTUK FILTER ---

// 1. Ambil semua periode bulan unik dari tabel payments untuk dropdown
$stmt_months = $pdo->query("SELECT DISTINCT DATE_FORMAT(payment_date, '%Y-%m') as payment_month FROM payments ORDER BY payment_month DESC");
$available_months = $stmt_months->fetchAll(PDO::FETCH_COLUMN);

// 2. Ambil semua user admin dan collector untuk dropdown
$stmt_users = $pdo->query("SELECT id, email, role FROM users WHERE role IN ('admin', 'collector') ORDER BY role, email");
$users = $stmt_users->fetchAll();

// --- LOGIKA FILTER ---
$selected_month = $_GET['month'] ?? ($available_months[0] ?? date('Y-m'));
$selected_user = $_GET['user'] ?? 'all';
$selected_method = $_GET['method'] ?? 'all';

$where_clauses = ["DATE_FORMAT(p.payment_date, '%Y-%m') = ?"];
$params = [$selected_month];

// Filter berdasarkan metode pembayaran
if ($selected_method == 'cash') {
    $where_clauses[] = "p.payment_method = 'cash'";
} elseif ($selected_method == 'online') {
    $where_clauses[] = "p.payment_method != 'cash'";
}

// Filter berdasarkan siapa yang mengonfirmasi (hanya berlaku untuk 'cash')
if ($selected_method != 'online') { // Berlaku jika 'all' atau 'cash'
    if ($selected_user == 'admins') {
        $where_clauses[] = "u.role = 'admin'";
    } elseif ($selected_user == 'collectors') {
        $where_clauses[] = "u.role = 'collector'";
    } elseif (is_numeric($selected_user)) {
        $where_clauses[] = "p.confirmed_by = ?";
        $params[] = $selected_user;
    }
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// --- EKSEKUSI QUERY UTAMA ---
$sql_payments = "SELECT 
                    p.payment_date, 
                    p.amount_paid, 
                    p.discount_amount,
                    p.payment_method, 
                    i.invoice_number, 
                    i.billing_period,
                    c.name as customer_name,
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

// Hitung total pendapatan dari hasil filter
$total_income = array_sum(array_column($payments, 'amount_paid'));
$total_discount = array_sum(array_column($payments, 'discount_amount'));

// Siapkan teks untuk header PDF jika user spesifik dipilih
$selected_user_info = '';
if (is_numeric($selected_user)) {
    foreach ($users as $user) {
        if ($user['id'] == $selected_user) {
            $selected_user_info = 'Oleh: ' . htmlspecialchars($user['email']);
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Billing ISP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <!-- Pustaka untuk Ekspor PDF -->
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
                <form action="reports.php" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    <div>
                        <label for="month" class="block text-sm font-medium text-gray-700">Bulan Pembayaran</label>
                        <select id="month" name="month" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2">
                            <?php foreach($available_months as $month): ?>
                                <option value="<?php echo $month; ?>" <?php echo ($month == $selected_month) ? 'selected' : ''; ?>>
                                    <?php echo date('F Y', strtotime($month . '-01')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="method" class="block text-sm font-medium text-gray-700">Dibayar Via</label>
                        <select id="method" name="method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2">
                            <option value="all" <?php echo ($selected_method == 'all') ? 'selected' : ''; ?>>Semua</option>
                            <option value="cash" <?php echo ($selected_method == 'cash') ? 'selected' : ''; ?>>Tunai (Cash)</option>
                            <option value="online" <?php echo ($selected_method == 'online') ? 'selected' : ''; ?>>Online</option>
                        </select>
                    </div>
                    <div>
                        <label for="user" class="block text-sm font-medium text-gray-700">Dikonfirmasi Oleh</label>
                        <select id="user" name="user" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 <?php echo ($selected_method == 'online') ? 'bg-gray-200' : ''; ?>" <?php echo ($selected_method == 'online') ? 'disabled' : ''; ?>>
                            <option value="all" <?php echo ($selected_user == 'all') ? 'selected' : ''; ?>>Semua Petugas</option>
                            <option value="admins" <?php echo ($selected_user == 'admins') ? 'selected' : ''; ?>>Semua Admin</option>
                            <option value="collectors" <?php echo ($selected_user == 'collectors') ? 'selected' : ''; ?>>Semua Penagih</option>
                            <optgroup label="Spesifik">
                                <?php foreach($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo ($selected_user == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['email']) . " (" . ucfirst($user['role']) . ")"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg w-full">Filter</button>
                    <button type="button" id="export-pdf" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg w-full">Ekspor ke PDF</button>
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

            <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Tanggal Bayar</th>
                            <th scope="col" class="px-6 py-3">Pelanggan</th>
                            <th scope="col" class="px-6 py-3">Metode</th>
                            <th scope="col" class="px-6 py-3">Dikonfirmasi Oleh</th>
                            <th scope="col" class="px-6 py-3 text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr class="bg-white border-b"><td colspan="5" class="px-6 py-4 text-center">Tidak ada transaksi yang cocok dengan filter.</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4"><?php echo date('d M Y, H:i', strtotime($payment['payment_date'])); ?></td>
                                    <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                                    <td class="px-6 py-4"><span class="capitalize"><?php echo htmlspecialchars($payment['payment_method']); ?></span></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($payment['confirmed_by_user'] ?? 'Online'); ?></td>
                                    <td class="px-6 py-4 text-right">Rp <?php echo number_format($payment['amount_paid'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // ... (kode filter dan sidebar toggle tetap sama) ...
        const methodSelect = document.getElementById('method');
        const userSelect = document.getElementById('user');

        function toggleUserSelect() {
            if (methodSelect.value === 'online') {
                userSelect.disabled = true;
                userSelect.classList.add('bg-gray-200');
            } else {
                userSelect.disabled = false;
                userSelect.classList.remove('bg-gray-200');
            }
        }
        methodSelect.addEventListener('change', toggleUserSelect);
        toggleUserSelect();

        // --- FUNGSI EKSPOR PDF ---
        document.getElementById('export-pdf').addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            // Ambil data dari PHP dan konversi ke JSON
            const paymentsData = <?php echo json_encode($payments); ?>;
            const selectedMonthText = "<?php echo date('F Y', strtotime($selected_month . '-01')); ?>";
            const selectedUserInfo = "<?php echo $selected_user_info; ?>";
            const totalIncome = "<?php echo 'Rp ' . number_format($total_income, 0, ',', '.'); ?>";
            const totalTransactions = "<?php echo count($payments); ?>";
            const totalDiscount = "<?php echo 'Rp ' . number_format($total_discount, 0, ',', '.'); ?>";

            // Judul Dokumen
            doc.setFontSize(18);
            doc.text("Laporan Pendapatan", 14, 22);
            doc.setFontSize(11);
            doc.setTextColor(100);
            doc.text(`Periode Pembayaran: ${selectedMonthText}`, 14, 30);

            // Tambahkan kotak info user jika ada filter spesifik
            if (selectedUserInfo) {
                doc.setFontSize(9);
                doc.setTextColor(130);
                const textWidth = doc.getTextWidth(selectedUserInfo);
                const pageWidth = doc.internal.pageSize.getWidth();
                const x = pageWidth - textWidth - 14 - 4; // page width - text width - margin - padding
                const y = 20;
                doc.setDrawColor(200); // Warna bingkai abu-abu
                doc.rect(x, y - 5, textWidth + 8, 8); // Gambar bingkai
                doc.text(selectedUserInfo, x + 4, y); // Tulis teks di dalam bingkai
            }

            // Ringkasan
            doc.autoTable({
                startY: 38,
                head: [['Total Pendapatan', 'Total Transaksi', 'Total Diskon']],
                body: [[totalIncome, totalTransactions, totalDiscount]],
                theme: 'grid',
                styles: {
                    fontStyle: 'bold',
                    halign: 'center'
                }
            });

            // Urutkan data berdasarkan nama pelanggan
            paymentsData.sort((a, b) => a.customer_name.localeCompare(b.customer_name));

            // Siapkan data untuk tabel utama
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

            // Buat tabel utama
            doc.autoTable({
                startY: doc.lastAutoTable.finalY + 10,
                head: [['Nama Pelanggan', 'No. Invoice', 'Tgl Bayar', 'Bulan Bayar', 'Oleh', 'Via', 'Jumlah Bayar (Rp)', 'Diskon (Rp)']],
                body: tableData,
                theme: 'striped',
                headStyles: { fillColor: [22, 160, 133] },
                didParseCell: function (data) {
                    // Rata kanan untuk kolom angka
                    if (data.column.index >= 6) {
                        data.cell.styles.halign = 'right';
                    }
                }
            });

            // Simpan file
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
