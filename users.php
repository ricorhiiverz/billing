<?php
require_once 'config.php';

// Proteksi Halaman, hanya untuk admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    header("location: dashboard.php");
    exit;
}

// Logika untuk menghapus pengguna
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if ($_GET['id'] != $_SESSION['id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'customer'");
        $stmt->execute([$_GET['id']]);
        $_SESSION['success_message'] = "Pengguna berhasil dihapus.";
    } else {
        $_SESSION['error_message'] = "Anda tidak dapat menghapus akun Anda sendiri.";
    }
    header("location: users.php");
    exit;
}

// Ambil semua pengguna yang bukan 'customer'
$sql = "SELECT id, email, role FROM users WHERE role != 'customer' ORDER BY role, email ASC";
$users = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - Billing ISP</title>
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
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Manajemen Pengguna</h2>
                <a href="add_user.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg w-full sm:w-auto text-center">
                    + Tambah Pengguna
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
                            <th scope="col" class="px-6 py-3">Email</th>
                            <th scope="col" class="px-6 py-3">Peran</th>
                            <th scope="col" class="px-6 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr class="bg-white border-b"><td colspan="3" class="px-6 py-4 text-center">Belum ada pengguna.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="capitalize px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $user['role'] == 'admin' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 space-x-4">
                                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="font-medium text-blue-600 hover:underline">Edit</a>
                                        <?php if ($user['id'] != $_SESSION['id']): ?>
                                            <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" 
                                               onclick="return confirm('Anda yakin ingin menghapus pengguna ini?');" 
                                               class="font-medium text-red-600 hover:underline">Hapus</a>
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
                 <?php if (empty($users)): ?>
                    <div class="bg-white p-4 rounded-lg shadow text-center text-gray-500">Belum ada pengguna.</div>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <div class="bg-white p-4 rounded-lg shadow">
                            <div class="flex justify-between items-start">
                                <div class="font-bold text-gray-800 break-all"><?php echo htmlspecialchars($user['email']); ?></div>
                                <span class="capitalize flex-shrink-0 ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $user['role'] == 'admin' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo htmlspecialchars($user['role']); ?>
                                </span>
                            </div>
                            <div class="mt-4 pt-4 border-t border-gray-200 flex justify-end space-x-4">
                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="font-medium text-blue-600 hover:underline text-sm">Edit</a>
                                <?php if ($user['id'] != $_SESSION['id']): ?>
                                    <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" 
                                       onclick="return confirm('Anda yakin ingin menghapus pengguna ini?');" 
                                       class="font-medium text-red-600 hover:underline text-sm">Hapus</a>
                                <?php endif; ?>
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
