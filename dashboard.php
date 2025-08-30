<?php
require_once 'config.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// --- PERSIAPAN DATA UNTUK FILTER ---
// 1. Dapatkan semua periode unik dari tabel invoices untuk dropdown
$stmt_periods = $pdo->query("SELECT DISTINCT billing_period FROM invoices ORDER BY billing_period DESC");
$billing_periods = $stmt_periods->fetchAll(PDO::FETCH_COLUMN);

// 2. Tentukan periode yang dipilih, default ke yang terbaru
$selected_period = $_GET['period'] ?? ($billing_periods[0] ?? date('Y-m'));

// --- LOGIKA PEMBATASAN WILAYAH & PENDAPATAN UNTUK COLLECTOR ---
$where_clause_customer = '';
$params_customer = [];
$where_clause_invoice = '';
$params_invoice = [];
$where_clause_payment = '';
$params_payment = [];


if ($_SESSION['role'] == 'collector') {
    $wilayah_ids = $_SESSION['wilayah_ids'] ?? [];
    if (!empty($wilayah_ids)) {
        $placeholders = implode(',', array_fill(0, count($wilayah_ids), '?'));
        
        // Filter untuk statistik berbasis pelanggan (Total, Aktif, Tagihan, Aktivasi)
        $where_clause_customer = "WHERE wilayah_id IN (" . $placeholders . ")";
        $params_customer = $wilayah_ids;
        $where_clause_invoice = "AND c.wilayah_id IN (" . $placeholders . ")";
        $params_invoice = $wilayah_ids;

        // --- PERBAIKAN BUG PENDAPATAN COLLECTOR ---
        // Filter untuk statistik pendapatan HANYA berdasarkan ID collector yang login
        $where_clause_payment = "AND p.confirmed_by = ?";
        $params_payment = [$_SESSION['id']];

    } else {
        // Jika collector tidak punya wilayah, tampilkan data 0
        $where_clause_customer = "WHERE 1 = 0";
        $where_clause_invoice = "AND 1 = 0";
        $where_clause_payment = "AND 1 = 0";
    }
}

// --- PENGAMBILAN DATA STATISTIK ---

// Total Pelanggan & Pelanggan Aktif (Berdasarkan wilayah collector)
$stmt_total_customers = $pdo->prepare("SELECT COUNT(id) FROM customers {$where_clause_customer}");
$stmt_total_customers->execute($params_customer);
$total_customers = $stmt_total_customers->fetchColumn();

$stmt_active_customers = $pdo->prepare("SELECT COUNT(id) FROM customers WHERE is_active = TRUE " . ($where_clause_customer ? "AND" : "") . " " . ltrim($where_clause_customer, "WHERE "));
$stmt_active_customers->execute($params_customer);
$active_customers = $stmt_active_customers->fetchColumn();


// Tagihan Belum Lunas (Berdasarkan periode dan wilayah collector)
$sql_unpaid = "SELECT COUNT(i.id) FROM invoices i JOIN customers c ON i.customer_id = c.id WHERE i.status IN ('UNPAID', 'OVERDUE') AND i.billing_period = ? {$where_clause_invoice}";
$stmt_unpaid = $pdo->prepare($sql_unpaid);
$stmt_unpaid->execute(array_merge([$selected_period], $params_invoice));
$unpaid_invoices_count = $stmt_unpaid->fetchColumn();

// Pendapatan (Berdasarkan periode dan ID collector yang mengonfirmasi)
$month_start = $selected_period . '-01 00:00:00';
$month_end = date('Y-m-t 23:59:59', strtotime($month_start));
$sql_income = "SELECT SUM(p.amount_paid) FROM payments p JOIN invoices i ON p.invoice_id = i.id JOIN customers c ON i.customer_id = c.id WHERE p.payment_date BETWEEN ? AND ? {$where_clause_payment}";
$stmt_monthly_income = $pdo->prepare($sql_income);
$stmt_monthly_income->execute(array_merge([$month_start, $month_end], $params_payment));
$monthly_income = $stmt_monthly_income->fetchColumn() ?? 0;

// Butuh Aktivasi Manual (Berdasarkan wilayah collector)
$sql_manual_activation = "SELECT COUNT(i.id) FROM invoices i JOIN customers c ON i.customer_id = c.id WHERE i.requires_manual_activation = TRUE {$where_clause_invoice}";
$stmt_manual_activation = $pdo->prepare($sql_manual_activation);
$stmt_manual_activation->execute($params_invoice);
$manual_activation_count = $stmt_manual_activation->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Billing ISP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-100">

<!-- Overlay untuk background saat sidebar mobile aktif -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-10 hidden"></div>

<div class="relative min-h-screen md:flex">
    
    <?php include 'sidebar.php'; ?>

    <div class="flex-1 flex flex-col md:ml-64">
        <!-- Header -->
        <header class="flex items-center justify-between h-16 bg-white border-b border-gray-200 px-4">
            <!-- Tombol Hamburger untuk Mobile -->
            <button id="sidebar-toggle" class="md:hidden text-gray-600 hover:text-gray-800 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            
            <div class="flex-1 flex justify-end items-center">
                 <span class="text-gray-600 mr-4 text-sm md:text-base">
                    Halo, <b><?php echo htmlspecialchars($_SESSION["email"]); ?></b>
                </span>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    Logout
                </a>
            </div>
        </header>
        
        <main class="p-4 md:p-8 flex-1 overflow-y-auto">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                <h2 class="text-3xl font-bold text-gray-800">Dashboard</h2>
                <!-- Form Filter Periode -->
                <form action="dashboard.php" method="GET" id="period-form">
                    <select name="period" id="period" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" onchange="this.form.submit()">
                        <option value="">Pilih Periode</option>
                        <?php foreach($billing_periods as $period): ?>
                            <option value="<?php echo $period; ?>" <?php echo ($period == $selected_period) ? 'selected' : ''; ?>>
                                Data <?php echo date('F Y', strtotime($period . '-01')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($manual_activation_count > 0): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6" role="alert">
                <p class="font-bold">Peringatan!</p>
                <p>Terdapat <strong><?php echo $manual_activation_count; ?> pelanggan</strong> yang butuh aktivasi manual. <a href="invoices.php?filter=manual_activation" class="text-red-800 font-semibold hover:underline">Lihat Daftar -></a></p>
            </div>
            <?php endif; ?>

            <!-- Grid Statistik -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Card Total Pelanggan -->
                <div class="bg-white p-6 rounded-xl shadow-lg flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Pelanggan</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $total_customers; ?></p>
                    </div>
                    <div class="bg-blue-100 text-blue-600 p-3 rounded-full">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                </div>

                <!-- Card Tagihan Belum Lunas -->
                <div class="bg-white p-6 rounded-xl shadow-lg flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Tagihan Belum Lunas</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $unpaid_invoices_count; ?></p>
                        <p class="text-xs text-gray-400">Periode <?php echo date('F Y', strtotime($selected_period . '-01')); ?></p>
                    </div>
                    <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                </div>

                <!-- Card Pendapatan Periode Ini -->
                <div class="bg-white p-6 rounded-xl shadow-lg flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Pendapatan</p>
                        <p class="text-3xl font-bold text-gray-800">Rp <?php echo number_format($monthly_income, 0, ',', '.'); ?></p>
                        <p class="text-xs text-gray-400">Periode <?php echo date('F Y', strtotime($selected_period . '-01')); ?></p>
                    </div>
                    <div class="bg-green-100 text-green-600 p-3 rounded-full">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v.01M12 6v-1m0-1V4m0 2.01V8m0 0h.01M12 16h.01M12 18v-2m0-1v-1m0 0v-1m0-1V8m0 0h.01M12 6h.01M12 4h.01M12 18h.01M12 20h.01M4 12H2m22 0h-2m-2-8l-1.414-1.414M18.586 18.586L20 20m-16-4.414L2 14.172M5.414 5.414L4 4m16 1.414l1.414-1.414M4 20l1.414-1.414"></path></svg>
                    </div>
                </div>

                <!-- Card Pelanggan Aktif -->
                <div class="bg-white p-6 rounded-xl shadow-lg flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Pelanggan Aktif</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $active_customers; ?></p>
                    </div>
                    <div class="bg-indigo-100 text-indigo-600 p-3 rounded-full">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- JavaScript untuk Sidebar Toggle -->
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
