<?php
require_once 'config.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$packages = $pdo->query("SELECT * FROM packages ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Paket - Billing ISP</title>
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
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Daftar Paket Internet</h2>
                <a href="add_package.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg w-full sm:w-auto text-center">+ Tambah Paket</a>
            </div>

            <!-- Tampilan Tabel untuk Desktop -->
            <div class="hidden md:block bg-white rounded-xl shadow-lg overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">Nama Paket</th>
                            <th class="px-6 py-3">Harga</th>
                            <th class="px-6 py-3">Profil MikroTik</th>
                            <th class="px-6 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($packages as $package): ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($package['name']); ?></td>
                                <td class="px-6 py-4">Rp <?php echo number_format($package['price'], 0, ',', '.'); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($package['mikrotik_profile']); ?></td>
                                <td class="px-6 py-4">
                                    <a href="edit_package.php?id=<?php echo $package['id']; ?>" class="font-medium text-blue-600 hover:underline">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tampilan Kartu untuk Mobile -->
            <div class="md:hidden space-y-4">
                <?php if (empty($packages)): ?>
                    <div class="bg-white p-4 rounded-lg shadow text-center text-gray-500">Belum ada paket.</div>
                <?php else: ?>
                    <?php foreach ($packages as $package): ?>
                        <div class="bg-white p-4 rounded-lg shadow">
                            <div class="font-bold text-gray-800"><?php echo htmlspecialchars($package['name']); ?></div>
                            <div class="mt-2 text-sm text-gray-600 space-y-1">
                                <p><span class="font-semibold">Harga:</span> Rp <?php echo number_format($package['price'], 0, ',', '.'); ?></p>
                                <p><span class="font-semibold">Profil MikroTik:</span> <?php echo htmlspecialchars($package['mikrotik_profile']); ?></p>
                            </div>
                            <div class="mt-4 text-right">
                                <a href="edit_package.php?id=<?php echo $package['id']; ?>" class="font-medium text-blue-600 hover:underline">Edit Paket</a>
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
