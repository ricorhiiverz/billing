<?php
// whatsapp_helper.php

/**
 * Mengirim pesan WhatsApp menggunakan API Fonnte.
 *
 * @param PDO $pdo Objek koneksi database PDO.
 * @param string $phone_number Nomor telepon tujuan (tanpa 62 atau +62).
 * @param string $message Isi pesan yang akan dikirim.
 * @return bool True jika berhasil, false jika gagal.
 */
function sendWhatsAppMessage(PDO $pdo, string $phone_number, string $message): bool {
    try {
        // Ambil Fonnte API Token dari database
        $stmt_settings = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'fonnte_token'");
        $stmt_settings->execute();
        $fonnte_token = $stmt_settings->fetchColumn();

        if (empty($fonnte_token)) {
            error_log("Fonnte token tidak ditemukan di pengaturan.");
            return false;
        }

        // Pastikan nomor telepon diawali dengan 62
        if (substr($phone_number, 0, 1) === '0') {
            $phone_number = '62' . substr($phone_number, 1);
        } elseif (substr($phone_number, 0, 2) !== '62') {
            $phone_number = '62' . $phone_number;
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.fonnte.com/send',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30, // Timeout 30 detik
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => array(
            'target' => $phone_number,
            'message' => $message,
          ),
          CURLOPT_HTTPHEADER => array(
            'Authorization: ' . $fonnte_token
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            error_log("Fonnte cURL Error: " . $err);
            return false;
        } else {
            // Anda bisa menambahkan logging response di sini jika perlu
            // error_log("Fonnte Response: " . $response);
            return true;
        }
    } catch (Exception $e) {
        error_log("Error in sendWhatsAppMessage: " . $e->getMessage());
        return false;
    }
}
?>
