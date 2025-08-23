<?php
require_once 'config.php';

// Proteksi Halaman, hanya untuk admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

// Cek ID di URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: wilayah.php");
    exit;
}
$wilayah_id = $_GET['id'];

// Ambil data wilayah dari database
$stmt = $pdo->prepare("SELECT * FROM wilayah WHERE id = ?");
$stmt->execute([$wilayah_id]);
$wilayah = $stmt->fetch();
if (!$wilayah) {
    header("location: wilayah.php");
    exit;
}

// Inisialisasi variabel
$nama_wilayah = $wilayah['nama_wilayah'];
$errors = [];

// Proses form saat disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_nama_wilayah = trim($_POST['nama_wilayah']);

    // Validasi
    if (empty($new_nama_wilayah)) {
        $errors[] = "Nama wilayah tidak boleh kosong.";
    }

    // Cek duplikasi jika nama diubah
    if ($new_nama_wilayah != $nama_wilayah) {
        $stmt_check = $pdo->prepare("SELECT id FROM wilayah WHERE nama_wilayah = ?");
        $stmt_check->execute([$new_nama_wilayah]);
        if ($stmt_check->fetch()) {
            $errors[] = "Nama wilayah ini sudah ada.";
        }
    }

    // Jika tidak ada error, update
    if (empty($errors)) {
        try {
            $sql = "UPDATE wilayah SET nama_wilayah = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_nama_wilayah, $wilayah_id]);
            
            $_SESSION['success_message'] = "Wilayah berhasil diperbarui.";
            header("location: wilayah.php");
            exit;
        } catch (Exception $e) {
            $errors[] = "Gagal memperbarui data: " . $e->getMessage();
        }
    } else {
        $nama_wilayah = $new_nama_wilayah; // Tampilkan kembali input yang error
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Wilayah - Billing ISP</title>
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
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Edit Wilayah</h2>
                    <a href="wilayah.php" class="text-blue-600 hover:underline">Kembali ke Daftar</a>
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

                    <form action="edit_wilayah.php?id=<?php echo $wilayah_id; ?>" method="POST">
                        <div class="mb-5">
                            <label for="nama_wilayah" class="block mb-2 text-sm font-medium text-gray-600">Nama Wilayah</label>
                            <input type="text" id="nama_wilayah" name="nama_wilayah" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($nama_wilayah); ?>" required>
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
