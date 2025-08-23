<?php
require_once 'config.php';

// Proteksi Halaman, hanya untuk admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

// Cek ID di URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: packages.php");
    exit;
}
$package_id = $_GET['id'];

// Ambil data paket dari database
$stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
$stmt->execute([$package_id]);
$package = $stmt->fetch();
if (!$package) {
    header("location: packages.php");
    exit;
}

// Inisialisasi variabel dari data yang ada
$name = $package['name'];
$price = $package['price'];
$mikrotik_profile = $package['mikrotik_profile'];
$errors = [];

// Proses form saat disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $price = trim($_POST['price']);
    $mikrotik_profile = trim($_POST['mikrotik_profile']);

    // Validasi
    if (empty($name)) $errors[] = "Nama paket tidak boleh kosong.";
    if (empty($price) || !is_numeric($price)) $errors[] = "Harga harus berupa angka.";
    if (empty($mikrotik_profile)) $errors[] = "Profil MikroTik tidak boleh kosong.";

    // Jika tidak ada error, update data
    if (empty($errors)) {
        try {
            $sql = "UPDATE packages SET name = ?, price = ?, mikrotik_profile = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $price, $mikrotik_profile, $package_id]);
            
            $_SESSION['success_message'] = "Paket berhasil diperbarui.";
            header("location: packages.php");
            exit;
        } catch (Exception $e) {
            $errors[] = "Gagal memperbarui data: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Paket - Billing ISP</title>
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
            <div class="max-w-lg mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Edit Paket</h2>
                    <a href="packages.php" class="text-blue-600 hover:underline">Kembali ke Daftar</a>
                </div>

                <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
                    <?php if (!empty($errors)): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
                            <strong class="font-bold">Oops!</strong>
                            <ul class="mt-2 list-disc list-inside">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="edit_package.php?id=<?php echo $package_id; ?>" method="POST">
                        <div class="mb-5">
                            <label for="name" class="block mb-2 text-sm font-medium text-gray-600">Nama Paket</label>
                            <input type="text" id="name" name="name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($name); ?>" required>
                        </div>
                        <div class="mb-5">
                            <label for="price" class="block mb-2 text-sm font-medium text-gray-600">Harga</label>
                            <input type="number" id="price" name="price" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($price); ?>" required>
                        </div>
                        <div class="mb-5">
                            <label for="mikrotik_profile" class="block mb-2 text-sm font-medium text-gray-600">Nama Profil di MikroTik</label>
                            <input type="text" id="mikrotik_profile" name="mikrotik_profile" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($mikrotik_profile); ?>" required>
                        </div>
                        <div class="mt-8 flex justify-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg">
                                Simpan Perubahan
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
