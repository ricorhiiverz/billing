<?php
require_once 'config.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impor Pelanggan - Billing ISP</title>
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
            <div class="max-w-2xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Impor Pelanggan dari CSV</h2>
                    <a href="customers.php" class="text-blue-600 hover:underline">Kembali ke Daftar</a>
                </div>

                <?php if(isset($_SESSION['import_status'])): ?>
                    <div class="mb-6 p-4 rounded-lg <?php echo $_SESSION['import_status']['success'] ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
                        <?php echo $_SESSION['import_status']['message']; ?>
                    </div>
                    <?php unset($_SESSION['import_status']); ?>
                <?php endif; ?>

                <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
                    <div class="mb-6 border-b pb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Panduan Impor</h3>
                        <ol class="list-decimal list-inside text-sm text-gray-600 mt-2 space-y-1">
                            <li>Unduh file template CSV yang kami sediakan. Template akan berisi daftar ID yang valid.</li>
                            <li>Isi data pelanggan sesuai dengan kolom yang ada di template.</li>
                            <li>Pastikan <strong>ID Paket</strong>, <strong>ID Router</strong>, dan <strong>ID Wilayah</strong> sudah ada di sistem sebelum mengimpor.</li>
                            <li>Simpan file sebagai format CSV.</li>
                            <li>Unggah file yang sudah diisi pada formulir di bawah ini.</li>
                        </ol>
                        <a href="download_template.php" class="mt-4 inline-block bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg text-sm">
                            Unduh Template CSV Dinamis
                        </a>
                    </div>

                    <form action="process_import.php" method="POST" enctype="multipart/form-data">
                        <div>
                            <label for="csv_file" class="block mb-2 text-sm font-medium text-gray-600">Pilih File CSV</label>
                            <input type="file" id="csv_file" name="csv_file" class="block w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 cursor-pointer focus:outline-none" accept=".csv" required>
                        </div>
                        <div class="mt-8 flex justify-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg">
                                Mulai Impor
                            </button>
                        </div>
                    </form>
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