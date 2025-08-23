<?php
require_once 'config.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// --- LOGIKA FILTER PERIODE & PEMBATASAN WILAYAH ---
// 1. Dapatkan semua periode unik untuk dropdown
$stmt_periods = $pdo->query("SELECT DISTINCT billing_period FROM invoices ORDER BY billing_period DESC");
$billing_periods = $stmt_periods->fetchAll(PDO::FETCH_COLUMN);

// 2. Tentukan periode yang dipilih, default ke yang terbaru
$selected_period = $_GET['period'] ?? ($billing_periods[0] ?? date('Y-m'));

$where_clauses = ["i.billing_period = ?"];
$params = [$selected_period];

// Filter berdasarkan wilayah untuk collector
if ($_SESSION['role'] == 'collector') {
    $wilayah_ids = $_SESSION['wilayah_ids'] ?? [];
    if (!empty($wilayah_ids)) {
        $placeholders = implode(',', array_fill(0, count($wilayah_ids), '?'));
        $where_clauses[] = "c.wilayah_id IN (" . $placeholders . ")";
        $params = array_merge($params, $wilayah_ids);
    } else {
        $where_clauses[] = "1 = 0"; // Kondisi false jika collector tidak punya wilayah
    }
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Ambil semua data tagihan dengan filter yang sudah dibangun
$sql = "SELECT i.id, i.invoice_number, i.total_amount, i.due_date, i.status, c.id as customer_id, c.name as customer_name, i.requires_manual_activation
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        {$where_sql}
        ORDER BY i.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Tagihan - Billing ISP</title>
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
            <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Daftar Tagihan</h2>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <a href="generate_invoices.php" onclick="return confirm('Anda yakin ingin membuat tagihan untuk bulan ini? Proses ini tidak dapat diulang.');" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg w-full sm:w-auto text-center">
                    Buat Tagihan Bulan Ini
                </a>
                <?php endif; ?>
            </div>

            <!-- Form Filter Periode -->
            <div class="mb-6">
                <form action="invoices.php" method="GET" id="period-form">
                    <label for="period" class="block text-sm font-medium text-gray-700 mb-1">Tampilkan Periode Tagihan:</label>
                    <select name="period" id="period" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full sm:w-auto p-2.5">
                        <?php foreach($billing_periods as $period): ?>
                            <option value="<?php echo $period; ?>" <?php echo ($period == $selected_period) ? 'selected' : ''; ?>>
                                <?php echo date('F Y', strtotime($period . '-01')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>


            <!-- Tampilan Tabel -->
            <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3">No. Tagihan</th>
                            <th scope="col" class="px-6 py-3">Pelanggan</th>
                            <th scope="col" class="px-6 py-3">Jumlah</th>
                            <th scope="col" class="px-6 py-3">Status</th>
                            <th scope="col" class="px-6 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr class="bg-white border-b"><td colspan="5" class="px-6 py-4 text-center">Tidak ada data tagihan untuk periode ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                    <td class="px-6 py-4">Rp <?php echo number_format($invoice['total_amount'], 0, ',', '.'); ?></td>
                                    <td class="px-6 py-4">
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
                                    <td class="px-6 py-4">
                                        <a href="view_customer_invoices.php?id=<?php echo $invoice['customer_id']; ?>&period=<?php echo $selected_period; ?>" class="font-medium text-blue-600 hover:underline">Proses Bayar</a>
                                    </td>
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
        // Auto-submit form ketika dropdown berubah
        document.getElementById('period').addEventListener('change', function() {
            document.getElementById('period-form').submit();
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
