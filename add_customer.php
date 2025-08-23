<?php
require_once 'config.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

// Ambil data untuk dropdown
$packages = $pdo->query("SELECT id, name, price FROM packages ORDER BY name ASC")->fetchAll();
$routers = $pdo->query("SELECT id, name FROM routers ORDER BY name ASC")->fetchAll();
$wilayah_list = $pdo->query("SELECT id, nama_wilayah FROM wilayah ORDER BY nama_wilayah ASC")->fetchAll();

$name = $address = $phone_number = "";
$package_id = $router_id = $access_method = $wilayah_id = "";
$pppoe_user = $pppoe_password = $static_ip = $mac_address = "";
$registration_date = date('Y-m-d');
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil semua data dari form
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $phone_number = trim($_POST['phone_number']);
    $package_id = trim($_POST['package_id']);
    $router_id = trim($_POST['router_id']);
    $access_method = trim($_POST['access_method']);
    $registration_date = trim($_POST['registration_date']);
    $wilayah_id = trim($_POST['wilayah_id']);

    // Validasi dasar
    if (empty($name)) $errors[] = "Nama tidak boleh kosong.";
    if (empty($wilayah_id)) $errors[] = "Wilayah harus dipilih.";
    
    // Validasi berdasarkan metode akses
    if ($access_method == 'pppoe') {
        $pppoe_user = trim($_POST['pppoe_user']);
        $pppoe_password = trim($_POST['pppoe_password']);
        if (empty($pppoe_user) || empty($pppoe_password)) {
            $errors[] = "Username dan Password PPPoE tidak boleh kosong.";
        }
    } elseif ($access_method == 'static') {
        $static_ip = trim($_POST['static_ip']);
        $mac_address = trim($_POST['mac_address']);
        if (empty($static_ip)) {
            $errors[] = "IP Statik tidak boleh kosong.";
        }
    }

    // Jika tidak ada error, proses ke database
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Generate ID Pelanggan 10 digit yang unik
            do {
                $customer_number = (string)mt_rand(1000000000, 9999999999);
                $stmt_check = $pdo->prepare("SELECT id FROM customers WHERE customer_number = ?");
                $stmt_check->execute([$customer_number]);
            } while ($stmt_check->rowCount() > 0);

            // 2. Buat customer baru
            $sql_customer = "INSERT INTO customers (customer_number, name, address, phone_number, package_id, router_id, access_method, pppoe_user, pppoe_password, static_ip, mac_address, registration_date, is_active, wilayah_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?)";
            $stmt_customer = $pdo->prepare($sql_customer);
            $stmt_customer->execute([$customer_number, $name, $address, $phone_number, $package_id, $router_id, $access_method, $pppoe_user, $pppoe_password, $static_ip, $mac_address, $registration_date, $wilayah_id]);
            
            $pdo->commit();
            header("location: customers.php");
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
    <title>Tambah Pelanggan - Billing ISP</title>
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
            <div class="max-w-4xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Tambah Pelanggan Baru</h2>
                    <a href="customers.php" class="text-blue-600 hover:underline">Kembali ke Daftar</a>
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

                    <form action="add_customer.php" method="POST" id="customerForm">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-2">Data Pribadi</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="name" class="block mb-2 text-sm font-medium text-gray-600">Nama Lengkap</label>
                                <input type="text" id="name" name="name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($name); ?>" required>
                            </div>
                            <div>
                                <label for="phone_number" class="block mb-2 text-sm font-medium text-gray-600">Nomor Telepon (WA)</label>
                                <input type="text" id="phone_number" name="phone_number" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($phone_number); ?>" required>
                            </div>
                            <div class="md:col-span-2">
                                <label for="address" class="block mb-2 text-sm font-medium text-gray-600">Alamat Lengkap</label>
                                <textarea id="address" name="address" rows="3" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3"><?php echo htmlspecialchars($address); ?></textarea>
                            </div>
                        </div>

                        <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-2 mt-8">Data Layanan & Teknis</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="wilayah_id" class="block mb-2 text-sm font-medium text-gray-600">Wilayah</label>
                                <select id="wilayah_id" name="wilayah_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" required>
                                    <option value="">-- Pilih Wilayah --</option>
                                    <?php foreach($wilayah_list as $w): ?>
                                        <option value="<?php echo $w['id']; ?>" <?php if($wilayah_id == $w['id']) echo 'selected'; ?>><?php echo htmlspecialchars($w['nama_wilayah']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="package_id" class="block mb-2 text-sm font-medium text-gray-600">Paket Internet</label>
                                <select id="package_id" name="package_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" required>
                                    <option value="">-- Pilih Paket --</option>
                                    <?php foreach($packages as $package): ?>
                                        <option value="<?php echo $package['id']; ?>" <?php if($package_id == $package['id']) echo 'selected'; ?>><?php echo htmlspecialchars($package['name']); ?> (Rp <?php echo number_format($package['price']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                             <div>
                                <label for="registration_date" class="block mb-2 text-sm font-medium text-gray-600">Tanggal Registrasi</label>
                                <input type="date" id="registration_date" name="registration_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($registration_date); ?>" required>
                            </div>
                            <div>
                                <label for="router_id" class="block mb-2 text-sm font-medium text-gray-600">Router</label>
                                <select id="router_id" name="router_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" required>
                                    <option value="">-- Pilih Router --</option>
                                    <?php foreach($routers as $router): ?>
                                        <option value="<?php echo $router['id']; ?>" <?php if($router_id == $router['id']) echo 'selected'; ?>><?php echo htmlspecialchars($router['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label for="access_method" class="block mb-2 text-sm font-medium text-gray-600">Metode Akses</label>
                                <select id="access_method" name="access_method" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" required>
                                    <option value="">-- Pilih Metode --</option>
                                    <option value="pppoe" <?php if($access_method == 'pppoe') echo 'selected'; ?>>PPPoE</option>
                                    <option value="static" <?php if($access_method == 'static') echo 'selected'; ?>>IP Static</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="pppoe_details" class="hidden grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="pppoe_user" class="block mb-2 text-sm font-medium text-gray-600">Username PPPoE</label>
                                <input type="text" id="pppoe_user" name="pppoe_user" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($pppoe_user); ?>">
                            </div>
                            <div>
                                <label for="pppoe_password" class="block mb-2 text-sm font-medium text-gray-600">Password PPPoE</label>
                                <input type="text" id="pppoe_password" name="pppoe_password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($pppoe_password); ?>">
                            </div>
                        </div>

                        <div id="static_details" class="hidden grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="static_ip" class="block mb-2 text-sm font-medium text-gray-600">Alamat IP</label>
                                <input type="text" id="static_ip" name="static_ip" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($static_ip); ?>">
                            </div>
                            <div>
                                <label for="mac_address" class="block mb-2 text-sm font-medium text-gray-600">MAC Address (Opsional)</label>
                                <input type="text" id="mac_address" name="mac_address" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($mac_address); ?>">
                            </div>
                        </div>
                        
                        <div class="mt-8 flex justify-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg w-full sm:w-auto">
                                Simpan Pelanggan
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
        const accessMethodSelect = document.getElementById('access_method');
        const pppoeDetails = document.getElementById('pppoe_details');
        const staticDetails = document.getElementById('static_details');

        function toggleDetails() {
            if (accessMethodSelect.value === 'pppoe') {
                pppoeDetails.style.display = 'grid';
                staticDetails.style.display = 'none';
            } else if (accessMethodSelect.value === 'static') {
                pppoeDetails.style.display = 'none';
                staticDetails.style.display = 'grid';
            } else {
                pppoeDetails.style.display = 'none';
                staticDetails.style.display = 'none';
            }
        }
        toggleDetails();
        accessMethodSelect.addEventListener('change', toggleDetails);

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