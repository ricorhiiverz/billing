<?php
require_once 'config.php';

// Proteksi Halaman, hanya untuk admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

// Logika untuk menghapus wilayah
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $wilayah_id = $_GET['id'];

    // Cek apakah wilayah masih digunakan oleh pelanggan atau pengguna
    $stmt_check_customer = $pdo->prepare("SELECT COUNT(id) FROM customers WHERE wilayah_id = ?");
    $stmt_check_customer->execute([$wilayah_id]);
    $customer_count = $stmt_check_customer->fetchColumn();

    $stmt_check_user = $pdo->prepare("SELECT COUNT(user_id) FROM user_wilayah WHERE wilayah_id = ?");
    $stmt_check_user->execute([$wilayah_id]);
    $user_count = $stmt_check_user->fetchColumn();

    if ($customer_count > 0 || $user_count > 0) {
        $_SESSION['error_message'] = "Gagal menghapus! Wilayah ini masih digunakan oleh pelanggan atau penagih.";
    } else {
        try {
            $stmt_delete = $pdo->prepare("DELETE FROM wilayah WHERE id = ?");
            $stmt_delete->execute([$wilayah_id]);
            $_SESSION['success_message'] = "Wilayah berhasil dihapus.";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Gagal menghapus wilayah: " . $e->getMessage();
        }
    }
    header("location: wilayah.php");
    exit;
}

// Ambil semua data wilayah
$wilayah = $pdo->query("SELECT * FROM wilayah ORDER BY nama_wilayah ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Wilayah - Billing ISP</title>
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
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Manajemen Wilayah</h2>
                <a href="add_wilayah.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg w-full sm:w-auto text-center">
                    + Tambah Wilayah
                </a>
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

            <!-- Tampilan Tabel untuk Desktop -->
            <div class="hidden md:block bg-white rounded-xl shadow-lg overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Nama Wilayah</th>
                            <th scope="col" class="px-6 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($wilayah)): ?>
                            <tr class="bg-white border-b"><td colspan="2" class="px-6 py-4 text-center">Belum ada wilayah.</td></tr>
                        <?php else: ?>
                            <?php foreach ($wilayah as $w): ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($w['nama_wilayah']); ?></td>
                                    <td class="px-6 py-4 space-x-4">
                                        <a href="edit_wilayah.php?id=<?php echo $w['id']; ?>" class="font-medium text-blue-600 hover:underline">Edit</a>
                                        <a href="wilayah.php?action=delete&id=<?php echo $w['id']; ?>" 
                                           onclick="return confirm('Anda yakin ingin menghapus wilayah ini?');" 
                                           class="font-medium text-red-600 hover:underline">Hapus</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tampilan Kartu untuk Mobile -->
            <div class="md:hidden space-y-4">
                 <?php if (empty($wilayah)): ?>
                    <div class="bg-white p-4 rounded-lg shadow text-center text-gray-500">Belum ada wilayah.</div>
                <?php else: ?>
                    <?php foreach ($wilayah as $w): ?>
                        <div class="bg-white p-4 rounded-lg shadow">
                            <div class="font-bold text-gray-800 break-all"><?php echo htmlspecialchars($w['nama_wilayah']); ?></div>
                            <div class="mt-4 pt-4 border-t border-gray-200 flex justify-end space-x-4">
                                <a href="edit_wilayah.php?id=<?php echo $w['id']; ?>" class="font-medium text-blue-600 hover:underline text-sm">Edit</a>
                                <a href="wilayah.php?action=delete&id=<?php echo $w['id']; ?>" 
                                   onclick="return confirm('Anda yakin ingin menghapus wilayah ini?');" 
                                   class="font-medium text-red-600 hover:underline text-sm">Hapus</a>
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
