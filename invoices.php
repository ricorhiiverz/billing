<?php
require_once 'config.php';

// Proteksi Halaman
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// --- LOGIKA FILTER & PENCARIAN ---
// 1. Dapatkan semua periode unik untuk dropdown
$stmt_periods = $pdo->query("SELECT DISTINCT billing_period FROM invoices ORDER BY billing_period DESC");
$billing_periods = $stmt_periods->fetchAll(PDO::FETCH_COLUMN);

// 2. Ambil nilai filter dari URL atau set nilai default
$selected_period = $_GET['period'] ?? ($billing_periods[0] ?? date('Y-m'));
$selected_status = $_GET['status'] ?? 'UNPAID_OVERDUE'; // Default: Belum Lunas
$search_term = $_GET['search'] ?? '';

// 3. Bangun query WHERE secara dinamis
$where_clauses = ["i.billing_period = ?"];
$params = [$selected_period];

// Filter berdasarkan status
if ($selected_status == 'UNPAID_OVERDUE') {
    $where_clauses[] = "i.status IN ('UNPAID', 'OVERDUE')";
} elseif ($selected_status == 'PAID') {
    $where_clauses[] = "i.status = 'PAID'";
} elseif ($selected_status == 'OVERDUE') {
    $where_clauses[] = "i.status = 'OVERDUE'";
} // Jika 'ALL', tidak ada filter status

// Filter berdasarkan pencarian nama pelanggan
if (!empty($search_term)) {
    $where_clauses[] = "c.name LIKE ?";
    $params[] = "%" . $search_term . "%";
}

// Filter berdasarkan wilayah untuk collector
if ($_SESSION['role'] == 'collector') {
    $wilayah_ids = $_SESSION['wilayah_ids'] ?? [];
    if (!empty($wilayah_ids)) {
        $placeholders = implode(',', array_fill(0, count($wilayah_ids), '?'));
        $where_clauses[] = "c.wilayah_id IN (" . $placeholders . ")";
        $params = array_merge($params, $wilayah_ids);
    } else {
        $where_clauses[] = "1 = 0"; // Kondisi false jika collector tidak punya wilayah
    }
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// 4. Ambil semua data tagihan dengan filter dan urutan yang sudah dibangun
$sql = "SELECT i.id, i.invoice_number, i.total_amount, i.due_date, i.status, c.id as customer_id, c.name as customer_name, i.requires_manual_activation
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        {$where_sql}
        ORDER BY c.name ASC"; // Diurutkan berdasarkan nama pelanggan
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Tagihan - Billing ISP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <style> 
        body { font-family: 'Inter', sans-serif; } 
        #toast-container {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .toast {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            color: white;
            min-width: 300px;
            max-width: 400px;
            transform: translateX(120%);
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }
        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        .toast.toast-success {
            background-color: #10B981; /* green-500 */
        }
        .toast.toast-error {
            background-color: #EF4444; /* red-500 */
        }
        .toast-icon {
            margin-right: 0.75rem;
            flex-shrink: 0;
        }
    </style>
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
            <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Daftar Tagihan</h2>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <a href="generate_invoices.php" onclick="return confirm('Anda yakin ingin membuat tagihan untuk bulan ini? Proses ini tidak dapat diulang.');" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg w-full sm:w-auto text-center">
                    Buat Tagihan Bulan Ini
                </a>
                <?php endif; ?>
            </div>

            <!-- Form Filter -->
            <div class="mb-6 bg-white p-4 rounded-xl shadow-lg">
                <form action="invoices.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700">Cari Nama Pelanggan</label>
                        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Ketik nama..." class="mt-1 w-full p-2 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="period" class="block text-sm font-medium text-gray-700">Periode Tagihan</label>
                        <select name="period" id="period" class="mt-1 w-full p-2 border border-gray-300 rounded-lg bg-white focus:ring-blue-500 focus:border-blue-500">
                            <?php foreach($billing_periods as $period): ?>
                                <option value="<?php echo $period; ?>" <?php echo ($period == $selected_period) ? 'selected' : ''; ?>>
                                    <?php echo date('F Y', strtotime($period . '-01')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="status" class="mt-1 w-full p-2 border border-gray-300 rounded-lg bg-white focus:ring-blue-500 focus:border-blue-500">
                            <option value="ALL" <?php echo ($selected_status == 'ALL') ? 'selected' : ''; ?>>Semua Status</option>
                            <option value="UNPAID_OVERDUE" <?php echo ($selected_status == 'UNPAID_OVERDUE') ? 'selected' : ''; ?>>Belum Lunas</option>
                            <option value="PAID" <?php echo ($selected_status == 'PAID') ? 'selected' : ''; ?>>Lunas</option>
                            <option value="OVERDUE" <?php echo ($selected_status == 'OVERDUE') ? 'selected' : ''; ?>>Jatuh Tempo</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg w-full">Filter</button>
                </form>
            </div>


            <!-- Tampilan Tabel untuk Desktop -->
            <div class="hidden md:block bg-white rounded-xl shadow-lg overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Pelanggan</th>
                            <th scope="col" class="px-6 py-3">No. Tagihan</th>
                            <th scope="col" class="px-6 py-3">Jumlah</th>
                            <th scope="col" class="px-6 py-3">Status</th>
                            <th scope="col" class="px-6 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr class="bg-white border-b"><td colspan="5" class="px-6 py-4 text-center">Tidak ada data tagihan yang cocok dengan filter.</td></tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                    <td class="px-6 py-4">Rp <?php echo number_format($invoice['total_amount'], 0, ',', '.'); ?></td>
                                    <td class="px-6 py-4">
                                        <?php
                                            $status_class = '';
                                            if ($invoice['status'] == 'PAID') $status_class = 'bg-green-100 text-green-800';
                                            elseif ($invoice['status'] == 'UNPAID') $status_class = 'bg-yellow-100 text-yellow-800';
                                            else $status_class = 'bg-red-100 text-red-800';
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($invoice['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center space-x-4">
                                            <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="text-gray-500 hover:text-gray-800" title="Lihat Detail">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            </a>
                                            <button data-customer-id="<?php echo $invoice['customer_id']; ?>" data-customer-name="<?php echo htmlspecialchars($invoice['customer_name']); ?>" data-period="<?php echo $selected_period; ?>" class="open-payment-modal text-blue-600 hover:text-blue-800" title="Proses Bayar">
                                                <svg class="w-6 h-6 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Tampilan Kartu untuk Mobile -->
            <div class="md:hidden space-y-4">
                <?php if (empty($invoices)): ?>
                    <div class="bg-white p-4 rounded-lg shadow text-center text-gray-500">Tidak ada data tagihan yang cocok dengan filter.</div>
                <?php else: ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <div class="bg-white p-4 rounded-lg shadow">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-bold text-gray-800"><?php echo htmlspecialchars($invoice['customer_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                                    <div class="text-sm font-semibold text-gray-700 mt-1">Rp <?php echo number_format($invoice['total_amount'], 0, ',', '.'); ?></div>
                                </div>
                                <?php
                                    $status_class = '';
                                    if ($invoice['status'] == 'PAID') $status_class = 'bg-green-100 text-green-800';
                                    elseif ($invoice['status'] == 'UNPAID') $status_class = 'bg-yellow-100 text-yellow-800';
                                    else $status_class = 'bg-red-100 text-red-800';
                                ?>
                                <span class="flex-shrink-0 ml-4 px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($invoice['status']); ?>
                                </span>
                            </div>
                            <div class="mt-4 pt-4 border-t border-gray-200 flex justify-end space-x-4">
                                <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="text-gray-500 hover:text-gray-800" title="Lihat Detail">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </a>
                                <button data-customer-id="<?php echo $invoice['customer_id']; ?>" data-customer-name="<?php echo htmlspecialchars($invoice['customer_name']); ?>" data-period="<?php echo $selected_period; ?>" class="open-payment-modal text-blue-600 hover:text-blue-800" title="Proses Bayar">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<!-- Payment Modal -->
<div id="payment-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div id="modal-content" class="bg-white rounded-xl shadow-lg w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-xl font-bold text-gray-800" id="modal-customer-name">Konfirmasi Pembayaran</h3>
            <button id="close-modal-btn" class="text-gray-500 hover:text-gray-800">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="p-6 overflow-y-auto" id="modal-body">
            <!-- Content will be loaded here via JavaScript -->
            <div class="text-center py-10">
                <p>Memuat data tagihan...</p>
            </div>
        </div>
    </div>
</div>

<div id="toast-container"></div>


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

        // --- TOAST NOTIFICATION FUNCTION ---
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;

            const iconSvg = type === 'success'
                ? `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`
                : `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;

            toast.innerHTML = `
                <div class="toast-icon">${iconSvg}</div>
                <div>${message}</div>
            `;
            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('show');
            }, 100);

            setTimeout(() => {
                toast.classList.remove('show');
                toast.addEventListener('transitionend', () => toast.remove());
            }, 3000);
        }

        // --- MODAL LOGIC ---
        const paymentModal = document.getElementById('payment-modal');
        const closeModalBtn = document.getElementById('close-modal-btn');
        const modalBody = document.getElementById('modal-body');
        const modalCustomerName = document.getElementById('modal-customer-name');
        const openModalButtons = document.querySelectorAll('.open-payment-modal');

        function openModal() {
            paymentModal.classList.remove('hidden');
        }

        function closeModal() {
            paymentModal.classList.add('hidden');
            modalBody.innerHTML = '<div class="text-center py-10"><p>Memuat data tagihan...</p></div>'; // Reset body
        }

        closeModalBtn.addEventListener('click', closeModal);
        paymentModal.addEventListener('click', function(e) {
            if (e.target === paymentModal) {
                closeModal();
            }
        });

        openModalButtons.forEach(button => {
            button.addEventListener('click', function() {
                const customerId = this.dataset.customerId;
                const customerName = this.dataset.customerName;
                const period = this.dataset.period;

                modalCustomerName.textContent = `Tagihan untuk ${customerName}`;
                openModal();

                fetch(`get_invoice_details.php?id=${customerId}&period=${period}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            modalBody.innerHTML = data.html;
                            attachFormHandlers();
                        } else {
                            modalBody.innerHTML = `<p class="text-red-500">${data.message}</p>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching invoice details:', error);
                        modalBody.innerHTML = '<p class="text-red-500">Terjadi kesalahan saat mengambil data.</p>';
                    });
            });
        });

        function attachFormHandlers() {
            const paymentForm = document.getElementById('payment-form-modal');
            if (paymentForm) {
                const discountInput = paymentForm.querySelector('#discount');
                const totalAmount = paymentForm.querySelector('#total_amount');
                const finalAmountDisplay = paymentForm.querySelector('#final_amount_display');
                const amountPaidInput = paymentForm.querySelector('#amount_paid');

                function calculateFinalAmount() {
                    const total = parseFloat(totalAmount.value) || 0;
                    let discount = parseFloat(discountInput.value) || 0;
                    if (discount > total) {
                        discount = total;
                        discountInput.value = total;
                    }
                    if (discount < 0) {
                        discount = 0;
                        discountInput.value = 0;
                    }
                    const finalAmount = total - discount;
                    finalAmountDisplay.value = 'Rp ' + new Intl.NumberFormat('id-ID').format(finalAmount);
                    amountPaidInput.value = finalAmount;
                }
                
                if(discountInput) {
                   discountInput.addEventListener('input', calculateFinalAmount);
                   calculateFinalAmount();
                }

                paymentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(paymentForm);
                    const submitButton = paymentForm.querySelector('button[type="submit"]');
                    submitButton.disabled = true;
                    submitButton.innerHTML = 'Memproses...';

                    fetch('process_payment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            closeModal();
                            showToast(data.message, 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500); 
                        } else {
                            showToast(data.message, 'error');
                            submitButton.disabled = false;
                            submitButton.innerHTML = 'Konfirmasi Pembayaran Tunai';
                        }
                    })
                    .catch(error => {
                        console.error('Error processing payment:', error);
                        showToast('Terjadi kesalahan jaringan. Silakan coba lagi.', 'error');
                        submitButton.disabled = false;
                        submitButton.innerHTML = 'Konfirmasi Pembayaran Tunai';
                    });
                });
            }
        }
    });
</script>

</body>
</html>

