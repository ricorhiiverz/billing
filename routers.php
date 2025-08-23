<?php
require_once 'config.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$sql = "SELECT id, name, ip_address FROM routers ORDER BY name ASC";
$routers = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Router - Billing ISP</title>
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
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Daftar Router</h2>
                <a href="add_router.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg w-full sm:w-auto text-center">
                    + Tambah Router
                </a>
            </div>

            <!-- Tampilan Tabel untuk Desktop -->
            <div class="hidden md:block bg-white rounded-xl shadow-lg overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Nama Router</th>
                            <th scope="col" class="px-6 py-3">Alamat IP</th>
                            <th scope="col" class="px-6 py-3">Status Koneksi</th>
                            <th scope="col" class="px-6 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($routers)): ?>
                            <tr class="bg-white border-b"><td colspan="4" class="px-6 py-4 text-center">Belum ada data router.</td></tr>
                        <?php else: ?>
                            <?php foreach ($routers as $router): ?>
                                <tr class="bg-white border-b hover:bg-gray-50" id="router-row-<?php echo $router['id']; ?>">
                                    <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap"><?php echo htmlspecialchars($router['name']); ?></th>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($router['ip_address']); ?></td>
                                    <td class="px-6 py-4">
                                        <span id="status-<?php echo $router['id']; ?>" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            Belum dites
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 space-x-4 whitespace-nowrap">
                                        <a href="edit_router.php?id=<?php echo $router['id']; ?>" class="font-medium text-blue-600 hover:underline">Edit</a>
                                        <button data-router-id="<?php echo $router['id']; ?>" class="test-connection-btn font-medium text-indigo-600 hover:underline">
                                            Tes Koneksi
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tampilan Kartu untuk Mobile -->
            <div class="md:hidden space-y-4">
                <?php if (empty($routers)): ?>
                    <div class="bg-white p-4 rounded-lg shadow text-center text-gray-500">Belum ada data router.</div>
                <?php else: ?>
                    <?php foreach ($routers as $router): ?>
                        <div class="bg-white p-4 rounded-lg shadow">
                            <div class="font-bold text-gray-800"><?php echo htmlspecialchars($router['name']); ?></div>
                            <div class="mt-2 text-sm text-gray-600 space-y-1">
                                <p><span class="font-semibold">Alamat IP:</span> <?php echo htmlspecialchars($router['ip_address']); ?></p>
                                <p class="flex items-center">
                                    <span class="font-semibold mr-2">Status:</span> 
                                    <span id="status-mobile-<?php echo $router['id']; ?>" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        Belum dites
                                    </span>
                                </p>
                            </div>
                            <div class="mt-4 pt-4 border-t border-gray-200 flex justify-end space-x-4">
                                <button data-router-id="<?php echo $router['id']; ?>" class="test-connection-btn-mobile font-medium text-indigo-600 hover:underline text-sm">
                                    Tes Koneksi
                                </button>
                                <a href="edit_router.php?id=<?php echo $router['id']; ?>" class="font-medium text-blue-600 hover:underline text-sm">Edit</a>
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
    function testConnection(routerId, isMobile) {
        const statusSpan = document.getElementById(isMobile ? 'status-mobile-' + routerId : 'status-' + routerId);
        const button = event.target;

        statusSpan.textContent = 'Mencoba...';
        statusSpan.className = 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 animate-pulse';
        button.disabled = true;

        fetch('test_router_connection.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'router_id=' + routerId
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                statusSpan.textContent = 'Berhasil';
                statusSpan.className = 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800';
            } else {
                statusSpan.textContent = 'Gagal';
                statusSpan.className = 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800';
            }
        })
        .catch(error => {
            statusSpan.textContent = 'Error';
            statusSpan.className = 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800';
        })
        .finally(() => {
            button.disabled = false;
            statusSpan.classList.remove('animate-pulse');
        });
    }

    document.querySelectorAll('.test-connection-btn').forEach(button => {
        button.addEventListener('click', (event) => testConnection(event.target.dataset.routerId, false));
    });

    document.querySelectorAll('.test-connection-btn-mobile').forEach(button => {
        button.addEventListener('click', (event) => testConnection(event.target.dataset.routerId, true));
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
