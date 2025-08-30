<?php
require_once 'config.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo->beginTransaction();
        foreach ($_POST as $key => $value) {
            $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$key, $value, $value]);
        }
        $pdo->commit();
        $success_message = "Pengaturan berhasil disimpan!";
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - Billing ISP</title>
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
                <h2 class="text-3xl font-bold text-gray-800 mb-6">Pengaturan Sistem</h2>

                <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
                    <span class="block sm:inline"><?php echo $success_message; ?></span>
                </div>
                <?php endif; ?>

                <form action="settings.php" method="POST" class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-2">Branding & Umum</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label for="company_name" class="block mb-2 text-sm font-medium text-gray-600">Nama Perusahaan</label>
                            <input type="text" id="company_name" name="company_name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($settings['company_name'] ?? 'Billing ISP'); ?>" placeholder="Nama Perusahaan Anda">
                        </div>
                        <div>
                            <label for="ppn_percentage" class="block mb-2 text-sm font-medium text-gray-600">PPN (%)</label>
                            <input type="number" step="0.01" id="ppn_percentage" name="ppn_percentage" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($settings['ppn_percentage'] ?? '11'); ?>">
                        </div>
                        <div class="md:col-span-2">
                            <label for="company_logo_url" class="block mb-2 text-sm font-medium text-gray-600">URL Logo Perusahaan</label>
                            <input type="text" id="company_logo_url" name="company_logo_url" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($settings['company_logo_url'] ?? ''); ?>" placeholder="https://.../logo.png">
                            <p class="mt-1 text-xs text-gray-500">Masukkan link langsung ke gambar logo Anda. Kosongkan untuk menampilkan nama perusahaan.</p>
                        </div>
                         <div>
                            <label for="fonnte_token" class="block mb-2 text-sm font-medium text-gray-600">Fonnte API Token</label>
                            <input type="text" id="fonnte_token" name="fonnte_token" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($settings['fonnte_token'] ?? ''); ?>">
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-2">Payment Gateway</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label for="active_payment_gateway" class="block mb-2 text-sm font-medium text-gray-600">Gateway Aktif</label>
                            <select id="active_payment_gateway" name="active_payment_gateway" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3">
                                <option value="midtrans" <?php echo (($settings['active_payment_gateway'] ?? '') == 'midtrans') ? 'selected' : ''; ?>>Midtrans</option>
                                <option value="tripay" <?php echo (($settings['active_payment_gateway'] ?? '') == 'tripay') ? 'selected' : ''; ?>>Tripay</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="border-t pt-6 mb-8">
                        <h4 class="font-semibold text-gray-600 mb-4">API Keys Midtrans</h4>
                        <div class="space-y-4">
                            <div>
                                <label for="midtrans_server_key" class="block mb-2 text-sm font-medium text-gray-600">Server Key</label>
                                <input type="text" id="midtrans_server_key" name="midtrans_server_key" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($settings['midtrans_server_key'] ?? ''); ?>">
                            </div>
                            <div>
                                <label for="midtrans_client_key" class="block mb-2 text-sm font-medium text-gray-600">Client Key</label>
                                <input type="text" id="midtrans_client_key" name="midtrans_client_key" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($settings['midtrans_client_key'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="border-t pt-6 mb-8">
                        <h4 class="font-semibold text-gray-600 mb-4">API Keys Tripay</h4>
                        <div class="space-y-4">
                             <div>
                                <label for="tripay_merchant_code" class="block mb-2 text-sm font-medium text-gray-600">Kode Merchant</label>
                                <input type="text" id="tripay_merchant_code" name="tripay_merchant_code" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($settings['tripay_merchant_code'] ?? ''); ?>">
                            </div>
                            <div>
                                <label for="tripay_api_key" class="block mb-2 text-sm font-medium text-gray-600">API Key</label>
                                <input type="text" id="tripay_api_key" name="tripay_api_key" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($settings['tripay_api_key'] ?? ''); ?>">
                            </div>
                            <div>
                                <label for="tripay_private_key" class="block mb-2 text-sm font-medium text-gray-600">Private Key</label>
                                <input type="text" id="tripay_private_key" name="tripay_private_key" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg w-full p-3" value="<?php echo htmlspecialchars($settings['tripay_private_key'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg w-full sm:w-auto">
                            Simpan Pengaturan
                        </button>
                    </div>
                </form>
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
