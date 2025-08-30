<?php
require_once 'config.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["csv_file"])) {
    $file = $_FILES["csv_file"]["tmp_name"];
    $filename = $_FILES["csv_file"]["name"];
    $file_ext = pathinfo($filename, PATHINFO_EXTENSION);

    if (strtolower($file_ext) != 'csv') {
        $_SESSION['import_status'] = ['success' => false, 'message' => 'Gagal! Harap unggah file dengan format .csv'];
        header("location: import_customers.php");
        exit;
    }

    $handle = fopen($file, "r");
    if ($handle === FALSE) {
        $_SESSION['import_status'] = ['success' => false, 'message' => 'Gagal! Tidak dapat membuka file CSV.'];
        header("location: import_customers.php");
        exit;
    }

    $success_count = 0;
    $error_count = 0;
    $errors = [];
    $row_number = 0;
    $header_found = false;

    try {
        $pdo->beginTransaction();

        $sql = "INSERT INTO customers (customer_number, name, address, phone_number, package_id, router_id, access_method, pppoe_user, pppoe_password, static_ip, mac_address, registration_date, is_active, wilayah_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?)";
        $stmt_insert = $pdo->prepare($sql);

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row_number++;

            // Cek apakah baris ini adalah baris komentar/panduan
            if (isset($data[0]) && strpos(trim($data[0]), '#') === 0) {
                continue; // Lewati baris ini
            }
            
            // Cek apakah ini baris kosong (pemisah)
            if (empty(array_filter($data))) {
                continue;
            }

            // Temukan baris header
            if (!$header_found) {
                // Asumsikan baris pertama setelah semua komentar adalah header
                if (strtolower(trim($data[0])) === 'name') {
                    $header_found = true;
                    continue; // Lewati baris header, mulai proses data dari baris berikutnya
                }
            }
            
            if (!$header_found) continue; // Jangan proses apapun sebelum header ditemukan
            
            // Asumsi urutan kolom: name, address, phone_number, package_id, router_id, access_method, pppoe_user, pppoe_password, static_ip, mac_address, registration_date, wilayah_id
            if (count($data) < 12) {
                $error_count++;
                $errors[] = "Baris data ke-$row_number: Jumlah kolom tidak sesuai template (diharapkan 12).";
                continue;
            }

            // Generate ID Pelanggan unik
            do {
                $customer_number = (string)mt_rand(1000000000, 9999999999);
                $stmt_check = $pdo->prepare("SELECT id FROM customers WHERE customer_number = ?");
                $stmt_check->execute([$customer_number]);
            } while ($stmt_check->rowCount() > 0);

            // Bind and execute
            try {
                $stmt_insert->execute([
                    $customer_number,
                    $data[0], // name
                    $data[1], // address
                    $data[2], // phone_number
                    (int)$data[3], // package_id
                    (int)$data[4], // router_id
                    $data[5], // access_method
                    $data[6], // pppoe_user
                    $data[7], // pppoe_password
                    $data[8], // static_ip
                    $data[9], // mac_address
                    $data[10], // registration_date
                    (int)$data[11]  // wilayah_id
                ]);
                $success_count++;
            } catch (PDOException $e) {
                $error_count++;
                $error_info = $e->errorInfo;
                $error_message = $e->getMessage();
                // Cek jika error karena foreign key constraint
                if (isset($error_info[1]) && $error_info[1] == 1452) {
                    $error_message = "Pastikan ID Paket/Router/Wilayah valid dan sudah ada di sistem.";
                }
                $errors[] = "Baris data ke-$row_number ('{$data[0]}'): Gagal. " . $error_message;
            }
        }

        $pdo->commit();
        fclose($handle);

        $message = "Impor Selesai! Berhasil: $success_count, Gagal: $error_count.";
        if (!empty($errors)) {
            $message .= "<br><strong>Detail Kesalahan:</strong><ul class='list-disc list-inside'>";
            foreach ($errors as $error) {
                $message .= "<li>$error</li>";
            }
            $message .= "</ul>";
        }

        $_SESSION['import_status'] = ['success' => ($error_count == 0), 'message' => $message];

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['import_status'] = ['success' => false, 'message' => 'Terjadi kesalahan kritis saat impor: ' . $e->getMessage()];
    }

    header("location: import_customers.php");
    exit;
} else {
    header("location: customers.php");
    exit;
}
?>