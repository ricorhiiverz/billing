<?php
// -- DEBUGGING -- //
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Memasukkan file konfigurasi database dan memulai session
require_once 'config.php';

// Inisialisasi variabel untuk pesan error
$error_message = '';

// Cek apakah form telah disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validasi input
    if (empty(trim($_POST["email"])) || empty(trim($_POST["password"]))) {
        $error_message = "Email dan password tidak boleh kosong.";
    } else {
        $email = trim($_POST["email"]);
        $password = trim($_POST["password"]);

        // Menyiapkan statement SQL untuk mengambil data user
        $sql = "SELECT id, email, password, role FROM users WHERE email = :email";

        if ($stmt = $pdo->prepare($sql)) {
            // Bind variabel ke statement
            $stmt->bindParam(":email", $email, PDO::PARAM_STR);

            // Eksekusi statement
            if ($stmt->execute()) {
                // Cek apakah email ditemukan
                if ($stmt->rowCount() == 1) {
                    if ($row = $stmt->fetch()) {
                        $id = $row["id"];
                        $hashed_password = $row["password"];
                        $role = $row["role"];

                        // Verifikasi password
                        if (password_verify($password, $hashed_password)) {
                            // Password benar, mulai session baru
                            session_regenerate_id(true);

                            // Simpan data dasar ke dalam session
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["email"] = $email;
                            $_SESSION["role"] = $role;

                            // **LOGIKA BARU: Jika yang login adalah collector, ambil data wilayahnya**
                            if ($role == 'collector') {
                                $stmt_wilayah = $pdo->prepare("SELECT wilayah_id FROM user_wilayah WHERE user_id = ?");
                                $stmt_wilayah->execute([$id]);
                                $wilayah_ids = $stmt_wilayah->fetchAll(PDO::FETCH_COLUMN);
                                $_SESSION['wilayah_ids'] = $wilayah_ids; // Simpan ID wilayah ke session
                            }

                            // Arahkan pengguna ke halaman dashboard
                            header("location: dashboard.php");
                            exit;
                        } else {
                            // Password salah
                            $error_message = "Password yang Anda masukkan salah.";
                        }
                    }
                } else {
                    // Email tidak ditemukan
                    $error_message = "Tidak ada akun yang ditemukan dengan email tersebut.";
                }
            } else {
                $error_message = "Oops! Terjadi kesalahan saat eksekusi query. Silakan coba lagi nanti.";
            }

            // Tutup statement
            unset($stmt);
        }
    }

    // Tutup koneksi
    unset($pdo);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Billing ISP</title>
    <!-- Memuat Tailwind CSS dari CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <style>
        /* Menggunakan font Inter */
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">

    <div class="w-full max-w-sm p-8 bg-white rounded-xl shadow-lg">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Billing ISP</h1>
            <p class="text-gray-500">Silakan masuk untuk melanjutkan</p>
        </div>

        <!-- Form Login -->
        <form action="login.php" method="POST">
            <!-- Menampilkan pesan error jika ada -->
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <div class="mb-5">
                <label for="email" class="block mb-2 text-sm font-medium text-gray-600">Email</label>
                <input type="email" id="email" name="email" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-3" placeholder="nama@email.com" required>
            </div>

            <div class="mb-6">
                <label for="password" class="block mb-2 text-sm font-medium text-gray-600">Password</label>
                <input type="password" id="password" name="password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-3" required>
            </div>

            <button type="submit" class="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-3 text-center">
                Masuk
            </button>
        </form>
    </div>

</body>
</html>
