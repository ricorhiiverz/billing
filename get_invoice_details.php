<?php
require_once 'config.php';
header('Content-Type: application/json');

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid.']);
    exit;
}

// Validasi
if (!isset($_GET['id']) || empty($_GET['id']) || !isset($_GET['period'])) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap.']);
    exit;
}
$customer_id = $_GET['id'];
$selected_period = $_GET['period'];

try {
    // Ambil detail pelanggan
    $stmt_customer = $pdo->prepare("SELECT id, name FROM customers WHERE id = ?");
    $stmt_customer->execute([$customer_id]);
    $customer = $stmt_customer->fetch();
    if (!$customer) {
        throw new Exception("Pelanggan tidak ditemukan.");
    }

    // Ambil semua tagihan BELUM LUNAS hingga periode yang dipilih
    $sql = "SELECT i.id, i.invoice_number, i.total_amount, i.status, i.billing_period 
            FROM invoices i
            WHERE i.customer_id = ? AND i.billing_period <= ? AND i.status IN ('UNPAID', 'OVERDUE')
            ORDER BY i.billing_period ASC";
    $stmt_invoices = $pdo->prepare($sql);
    $stmt_invoices->execute([$customer_id, $selected_period]);
    $unpaid_invoices = $stmt_invoices->fetchAll();

    $total_tunggakan = 0;
    $invoice_ids_to_pay = [];
    foreach ($unpaid_invoices as $invoice) {
        $total_tunggakan += $invoice['total_amount'];
        $invoice_ids_to_pay[] = $invoice['id'];
    }
    
    // Mulai membuat konten HTML untuk dikirim sebagai response
    ob_start();
    ?>

    <?php if (empty($unpaid_invoices)): ?>
        <div class="text-center py-10">
            <p class="text-lg text-gray-700">Tidak ada tagihan yang perlu dibayar untuk pelanggan ini hingga periode yang dipilih.</p>
        </div>
    <?php else: ?>
        <h4 class="text-lg font-semibold text-gray-700 mb-4">Tagihan Belum Lunas</h4>
        <div class="border rounded-lg overflow-hidden mb-6 max-h-48 overflow-y-auto">
            <table class="w-full">
                <thead class="bg-gray-50 sticky top-0">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Periode</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($unpaid_invoices as $invoice): ?>
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo date('F Y', strtotime($invoice['billing_period'])); ?></td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 text-right font-semibold">Rp <?php echo number_format($invoice['total_amount'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-6 pt-6 border-t">
            <h4 class="text-lg font-semibold text-gray-700 mb-4">Konfirmasi Pembayaran</h4>
            <form id="payment-form-modal" method="POST">
                <div class="error-message-container"></div>
                <input type="hidden" name="invoice_ids" value="<?php echo implode(',', $invoice_ids_to_pay); ?>">
                
                <?php if (in_array($_SESSION['role'], ['admin', 'collector'])): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end p-4 bg-gray-50 rounded-lg border">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Total Tagihan</label>
                        <input type="text" class="mt-1 w-full bg-gray-200 rounded-md border-gray-300 p-2" value="Rp <?php echo number_format($total_tunggakan, 0, ',', '.'); ?>" readonly>
                        <input type="hidden" id="total_amount" value="<?php echo $total_tunggakan; ?>">
                    </div>
                    <div>
                        <label for="discount" class="block text-sm font-medium text-gray-700">Diskon (Rp)</label>
                        <input type="number" id="discount" name="discount" class="mt-1 w-full rounded-md border-gray-300 shadow-sm p-2" value="0" min="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jumlah Bayar Final</label>
                        <input type="text" id="final_amount_display" class="mt-1 w-full bg-gray-200 rounded-md border-gray-300 p-2 font-bold" readonly>
                        <input type="hidden" id="amount_paid" name="amount_paid" value="<?php echo $total_tunggakan; ?>">
                    </div>
                </div>
                <?php else: ?>
                    <input type="hidden" name="amount_paid" value="<?php echo $total_tunggakan; ?>">
                <?php endif; ?>
                
                <div class="text-right mt-6">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg w-full sm:w-auto">
                        Konfirmasi Pembayaran Tunai
                    </button>
                </div>
             </form>
        </div>
    <?php endif; ?>

    <?php
    $html_content = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html_content]);

} catch (Exception $e) {
    ob_end_clean(); // Hapus buffer jika terjadi error
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
