<?php
require_once 'config.php';

// Proteksi Halaman, hanya untuk admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    header("location: dashboard.php");
    exit;
}

// Ambil daftar wilayah untuk checkbox
$wilayah_list = $pdo->query("SELECT id, nama_wilayah FROM wilayah ORDER BY nama_wilayah ASC")->fetchAll();

$email = $role = "";
$assigned_wilayah = [];
$errors = [];

// Proses form saat disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    $assigned_wilayah = $_POST['wilayah'] ?? [];

    // Validasi
    if (empty($email)) $errors[] = "Email tidak boleh kosong.";
    if (empty($password)) $errors[] = "Password tidak boleh kosong.";
    if (empty($role)) $errors[] = "Peran harus dipilih.";
    if (!in_array($role, ['admin', 'collector'])) $errors[] = "Peran tidak valid.";
    if ($role == 'collector' && empty($assigned_wilayah)) {
        $errors[] = "Penagih harus memiliki setidaknya satu wilayah.";
    }

    // Cek apakah email sudah ada
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = "Email ini sudah terdaftar.";
    }

    // Jika tidak ada error, simpan
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Buat user baru
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql_user = "INSERT INTO users (email, password, role) VALUES (?, ?, ?)";
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute([$email, $hashed_password, $role]);
            $user_id = $pdo->lastInsertId();

            // 2. Jika perannya collector, simpan wilayahnya
            if ($role == 'collector' && !empty($assigned_wilayah)) {
                $sql_wilayah = "INSERT INTO user_wilayah (user_id, wilayah_id) VALUES (?, ?)";
                $stmt_wilayah = $pdo->prepare($sql_wilayah);
                foreach ($assigned_wilayah as $wilayah_id) {
                    $stmt_wilayah->execute([$user_id, $wilayah_id]);
                }
            }
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Pengguna baru berhasil ditambahkan.";
            header("location: users.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Gagal menyimpan data: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pengguna - Billing ISP</title>
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
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Tambah Pengguna Baru</h2>
                    <a href="users.php" class="text-blue-600 hover:underline">Kembali ke Daftar</a>
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

                    <form action="add_user.php" method="POST">
                        <div class="mb-5">
                            <label for="email" class="block mb-2 text-sm font-medium text-gray-600">Alamat Email</label>
                            <input type="email" id="email" name="email" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                        <div class="mb-5">
                            <label for="password" class="block mb-2 text-sm font-medium text-gray-600">Password</label>
                            <input type="password" id="password" name="password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" required>
                        </div>
                        <div class="mb-5">
                            <label for="role" class="block mb-2 text-sm font-medium text-gray-600">Peran (Role)</label>
                            <select id="role" name="role" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" required>
                                <option value="">-- Pilih Peran --</option>
                                <option value="admin" <?php if($role == 'admin') echo 'selected'; ?>>Admin</option>
                                <option value="collector" <?php if($role == 'collector') echo 'selected'; ?>>Collector</option>
                            </select>
                        </div>

                        <!-- Pilihan Wilayah (hanya muncul jika role = collector) -->
                        <div id="wilayah-section" class="mb-5 hidden">
                            <label class="block mb-2 text-sm font-medium text-gray-600">Wilayah Penagihan</label>
                            <div class="bg-gray-50 border border-gray-300 rounded-lg p-3 space-y-2">
                                <?php foreach ($wilayah_list as $wilayah): ?>
                                <div class="flex items-center">
                                    <input id="wilayah-<?php echo $wilayah['id']; ?>" name="wilayah[]" type="checkbox" value="<?php echo $wilayah['id']; ?>" 
                                           class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded"
                                           <?php if (in_array($wilayah['id'], $assigned_wilayah)) echo 'checked'; ?>>
                                    <label for="wilayah-<?php echo $wilayah['id']; ?>" class="ml-2 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($wilayah['nama_wilayah']); ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mt-8 flex justify-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg">
                                Simpan Pengguna
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
        const roleSelect = document.getElementById('role');
        const wilayahSection = document.getElementById('wilayah-section');

        function toggleWilayahSection() {
            if (roleSelect.value === 'collector') {
                wilayahSection.classList.remove('hidden');
            } else {
                wilayahSection.classList.add('hidden');
            }
        }

        // Jalankan saat halaman dimuat
        toggleWilayahSection();

        // Jalankan saat pilihan role berubah
        roleSelect.addEventListener('change', toggleWilayahSection);
        
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
