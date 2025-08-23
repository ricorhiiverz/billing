<?php
require_once 'config.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// --- LOGIKA PENCARIAN & PAGINASI ---
$limit = 15; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search_term = $_GET['search'] ?? '';
$search_query = "%" . $search_term . "%";

// --- LOGIKA BARU: PEMBATASAN BERDASARKAN WILAYAH ---
$where_clauses = ["(c.name LIKE ? OR c.customer_number LIKE ? OR c.phone_number LIKE ?)"];
$params = [$search_query, $search_query, $search_query];

if ($_SESSION['role'] == 'collector') {
    $wilayah_ids = $_SESSION['wilayah_ids'] ?? [];
    if (!empty($wilayah_ids)) {
        $placeholders = implode(',', array_fill(0, count($wilayah_ids), '?'));
        $where_clauses[] = "c.wilayah_id IN (" . $placeholders . ")";
        $params = array_merge($params, $wilayah_ids);
    } else {
        $where_clauses[] = "1 = 0";
    }
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Query untuk menghitung total pelanggan (dengan filter)
$sql_total = "SELECT COUNT(c.id) FROM customers c " . $where_sql;
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->execute($params);
$total_customers = $stmt_total->fetchColumn();
$total_pages = ceil($total_customers / $limit);

// Query untuk mengambil data pelanggan (dengan filter dan paginasi)
$sql = "SELECT c.id, c.customer_number, c.name, c.phone_number, p.name as package_name, c.is_active, w.nama_wilayah
        FROM customers c
        LEFT JOIN packages p ON c.package_id = p.id
        LEFT JOIN wilayah w ON c.wilayah_id = w.id
        " . $where_sql . "
        ORDER BY c.name ASC
        LIMIT ? OFFSET ?";

$params_with_pagination = array_merge($params, [$limit, $offset]);

$stmt = $pdo->prepare($sql);
for ($i = 1; $i <= count($params); $i++) {
    $stmt->bindValue($i, $params[$i-1]);
}
$stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);

$stmt->execute();
$customers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pelanggan - Billing ISP</title>
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
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Daftar Pelanggan</h2>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <a href="add_customer.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg w-full sm:w-auto text-center">
                    + Tambah Pelanggan
                </a>
                <?php endif; ?>
            </div>

            <div class="mb-6">
                <form action="customers.php" method="GET">
                    <div class="relative">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Cari ID, nama, atau telepon..." class="w-full p-3 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tampilan Tabel untuk Desktop -->
            <div class="hidden md:block bg-white rounded-xl shadow-lg overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3">ID Pelanggan</th>
                            <th scope="col" class="px-6 py-3">Nama</th>
                            <th scope="col" class="px-6 py-3">Wilayah</th>
                            <th scope="col" class="px-6 py-3">Status</th>
                            <th scope="col" class="px-6 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr class="bg-white border-b"><td colspan="5" class="px-6 py-4 text-center">Tidak ada pelanggan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 font-mono text-gray-700"><?php echo htmlspecialchars($customer['customer_number']); ?></td>
                                    <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                        <?php echo htmlspecialchars($customer['name']); ?>
                                        <span class="block text-xs text-gray-500"><?php echo htmlspecialchars($customer['phone_number']); ?></span>
                                    </th>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($customer['nama_wilayah'] ?? 'N/A'); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $customer['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $customer['is_active'] ? 'Aktif' : 'Non-Aktif'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 space-x-4">
                                        <a href="view_customer_invoices.php?id=<?php echo $customer['id']; ?>&period=<?php echo date('Y-m'); ?>" class="font-medium text-green-600 hover:underline">Lihat Tagihan</a>
                                        <?php if ($_SESSION['role'] == 'admin'): ?>
                                        <a href="edit_customer.php?id=<?php echo $customer['id']; ?>" class="font-medium text-blue-600 hover:underline">Edit</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tampilan Kartu untuk Mobile -->
            <div class="md:hidden space-y-4">
                <?php if (empty($customers)): ?>
                    <div class="bg-white p-4 rounded-lg shadow text-center text-gray-500">Tidak ada pelanggan.</div>
                <?php else: ?>
                    <?php foreach ($customers as $customer): ?>
                        <div class="bg-white p-4 rounded-lg shadow">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-bold text-gray-800"><?php echo htmlspecialchars($customer['name']); ?></div>
                                    <div class="text-sm font-mono text-gray-500"><?php echo htmlspecialchars($customer['customer_number']); ?></div>
                                </div>
                                <span class="text-xs font-semibold rounded-full px-2 py-0.5 <?php echo $customer['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $customer['is_active'] ? 'Aktif' : 'Non-Aktif'; ?>
                                </span>
                            </div>
                            <div class="mt-4 pt-4 border-t border-gray-200 flex justify-end space-x-4">
                                <a href="view_customer_invoices.php?id=<?php echo $customer['id']; ?>&period=<?php echo date('Y-m'); ?>" class="font-medium text-green-600 hover:underline">Lihat Tagihan</a>
                                <?php if ($_SESSION['role'] == 'admin'): ?>
                                <a href="edit_customer.php?id=<?php echo $customer['id']; ?>" class="font-medium text-blue-600 hover:underline">Edit</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Navigasi Paginasi -->
            <div class="mt-6 flex justify-between items-center">
                <span class="text-sm text-gray-700">
                    Menampilkan <?php echo count($customers); ?> dari <?php echo $total_customers; ?>
                </span>
                <div class="inline-flex -space-x-px">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_term); ?>" class="py-2 px-3 ml-0 leading-tight text-gray-500 bg-white rounded-l-lg border border-gray-300 hover:bg-gray-100">Sebelumnya</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_term); ?>" class="py-2 px-3 leading-tight text-gray-500 bg-white rounded-r-lg border border-gray-300 hover:bg-gray-100">Berikutnya</a>
                    <?php endif; ?>
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
