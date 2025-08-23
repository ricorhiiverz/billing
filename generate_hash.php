<?php
// -- DEBUGGING -- //
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Password yang ingin kita hash
$passwordToHash = 'admin123';

// Membuat hash yang aman
$hashedPassword = password_hash($passwordToHash, PASSWORD_DEFAULT);

// Email admin yang ingin di-update
$adminEmail = 'admin@billing.com';

// Membuat query SQL untuk update
$sqlUpdateQuery = "UPDATE `users` SET `password` = '" . $hashedPassword . "' WHERE `email` = '" . $adminEmail . "';";

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Generate Password Hash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="w-full max-w-2xl p-8 bg-white rounded-xl shadow-lg text-center">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Perbarui Password Admin</h1>
        <p class="text-gray-600 mb-6">Jalankan perintah SQL di bawah ini di phpMyAdmin untuk memperbaiki password admin.</p>
        <div class="bg-gray-800 text-white p-4 rounded-lg font-mono text-left overflow-x-auto">
            <code>
                <?php echo htmlspecialchars($sqlUpdateQuery); ?>
            </code>
        </div>
        <p class="text-sm text-gray-500 mt-4">Setelah menjalankan query ini, Anda bisa menghapus file `generate_hash.php` ini.</p>
    </div>
</body>
</html>
