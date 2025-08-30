<?php
// Dapatkan nama file saat ini untuk menandai menu aktif
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'guest'; // Ambil peran pengguna dari session

// Ambil data logo dan nama perusahaan dari database
try {
    $stmt_branding = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('company_name', 'company_logo_url')");
    $branding_settings = $stmt_branding->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $branding_settings = []; // Set default jika query gagal
}
$company_name = $branding_settings['company_name'] ?? 'Billing ISP';
$company_logo_url = $branding_settings['company_logo_url'] ?? '';
?>
<div id="sidebar-menu" class="fixed top-0 left-0 w-64 h-full bg-white shadow-lg z-20 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
    <div class="flex items-center justify-center h-16 bg-white shadow-md px-4">
        <?php if (!empty($company_logo_url)): ?>
            <img src="<?php echo htmlspecialchars($company_logo_url); ?>" alt="<?php echo htmlspecialchars($company_name); ?> Logo" class="h-10 w-auto object-contain">
        <?php else: ?>
            <h1 class="text-2xl font-bold text-gray-800 truncate"><?php echo htmlspecialchars($company_name); ?></h1>
        <?php endif; ?>
    </div>
    <div class="flex-grow">
        <nav class="flex-1 px-2 py-4 space-y-1">
            <a href="dashboard.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-200 rounded-md <?php echo ($current_page == 'dashboard.php') ? 'bg-gray-200 text-gray-800' : ''; ?>">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                Dashboard
            </a>
            <a href="customers.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-200 rounded-md <?php echo in_array($current_page, ['customers.php', 'add_customer.php', 'edit_customer.php']) ? 'bg-gray-200 text-gray-800' : ''; ?>">
                 <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Pelanggan
            </a>
             <a href="invoices.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-200 rounded-md <?php echo in_array($current_page, ['invoices.php', 'view_invoice.php']) ? 'bg-gray-200 text-gray-800' : ''; ?>">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Tagihan
            </a>
            
            <!-- --- Menu untuk Admin & Collector --- -->
            <?php if (in_array($user_role, ['admin', 'collector'])): ?>
                <a href="reports.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-200 rounded-md <?php echo ($current_page == 'reports.php') ? 'bg-gray-200 text-gray-800' : ''; ?>">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    Laporan
                </a>
            <?php endif; ?>
            
            <!-- --- Menu hanya untuk Admin --- -->
            <?php if ($user_role == 'admin'): ?>
                <a href="packages.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-200 rounded-md <?php echo in_array($current_page, ['packages.php', 'add_package.php', 'edit_package.php']) ? 'bg-gray-200 text-gray-800' : ''; ?>">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    Paket
                </a>
                <a href="routers.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-200 rounded-md <?php echo in_array($current_page, ['routers.php', 'add_router.php', 'edit_router.php']) ? 'bg-gray-200 text-gray-800' : ''; ?>">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg>
                    Router
                </a>
                <a href="wilayah.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-200 rounded-md <?php echo in_array($current_page, ['wilayah.php', 'add_wilayah.php', 'edit_wilayah.php']) ? 'bg-gray-200 text-gray-800' : ''; ?>">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Wilayah
                </a>
                <a href="settings.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-200 rounded-md <?php echo ($current_page == 'settings.php') ? 'bg-gray-200 text-gray-800' : ''; ?>">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.096 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Pengaturan
                </a>
                <a href="users.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-200 rounded-md <?php echo in_array($current_page, ['users.php', 'add_user.php', 'edit_user.php']) ? 'bg-gray-200 text-gray-800' : ''; ?>">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M15 21v-1.5a2.5 2.5 0 00-5 0V21"></path></svg>
                    Pengguna
                </a>
            <?php endif; ?>
        </nav>
    </div>
</div>

