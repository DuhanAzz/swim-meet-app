<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

// --- 1. HANDLE UPLOAD BUKTI BAYAR ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_proof'])) {
    $paymentId = $_POST['payment_id'];
    
    // Folder Upload
    $targetDir = __DIR__ . "/../../public/uploads/payments/"; 
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    
    // Validasi & Upload
    $fileExt = strtolower(pathinfo($_FILES["proof"]["name"], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    
    if (in_array($fileExt, $allowed)) {
        // Nama file unik
        $fileName = time() . "_PAY_" . $_FILES["proof"]["name"];
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["proof"]["tmp_name"], $targetFile)) {
            // Update Data Pembayaran
            $sql = "UPDATE payments SET file_path = ?, status = 'Pending', updated_at = NOW() WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$fileName, $paymentId, $uid]);

            $_SESSION['toast_type'] = 'success';
            $_SESSION['toast_message'] = 'Bukti pembayaran berhasil dikirim! Menunggu konfirmasi Admin.';
        } else {
            $_SESSION['toast_type'] = 'error';
            $_SESSION['toast_message'] = 'Gagal upload file ke server.';
        }
    } else {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_message'] = 'Format file harus JPG, PNG, atau PDF.';
    }
    header("Location: pembayaran.php"); exit;
}

// --- 2. AMBIL DATA DARI TABEL PAYMENTS (TRANSAKSI) ---
// PERBAIKAN: Menghapus 'e.event_start_date' yang bikin error
$sqlBills = "
    SELECT 
        p.id as payment_id,
        p.event_id,
        p.amount as total_tagihan,
        p.status,
        p.file_path,
        p.created_at as tgl_transaksi,
        e.nama_event
    FROM payments p
    JOIN events e ON p.event_id = e.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
";

$stmt = $pdo->prepare($sqlBills);
$stmt->execute([$uid]);
$bills = $stmt->fetchAll();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Status Pembayaran</h1>
            <p class="text-sm text-slate-500">Daftar tagihan dari event yang sudah Anda Checkout.</p>
        </div>
    </div>

    <?php if(isset($_SESSION['toast_message'])): ?>
        <div class="mb-6 px-6 py-4 rounded-xl shadow-lg font-bold text-white flex items-center gap-3 <?= $_SESSION['toast_type']=='success' ? 'bg-green-500' : 'bg-red-500' ?>">
            <span><?= $_SESSION['toast_type']=='success' ? '✅' : '⚠️' ?></span>
            <?= $_SESSION['toast_message']; unset($_SESSION['toast_message']); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-xs border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4">Nama Event</th>
                        <th class="px-6 py-4">Tanggal Checkout</th>
                        <th class="px-6 py-4">Total Tagihan</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($bills)): ?>
                        <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400 italic">
                            Belum ada tagihan.<br>Silakan lakukan pendaftaran event & checkout terlebih dahulu.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach($bills as $b): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800 text-base"><?= htmlspecialchars($b['nama_event']) ?></div>
                                <div class="text-[10px] text-slate-400 mt-1">ID Transaksi: #<?= $b['payment_id'] ?></div>
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                <?= date('d M Y H:i', strtotime($b['tgl_transaksi'])) ?>
                            </td>
                            <td class="px-6 py-4 font-mono font-bold text-slate-700 text-base">
                                Rp <?= number_format($b['total_tagihan'], 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if($b['status'] == 'Pending'): ?>
                                    <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-[10px] font-black uppercase animate-pulse">
                                        ⏳ Menunggu Verifikasi
                                    </span>
                                <?php elseif($b['status'] == 'Paid' || $b['status'] == 'Verified'): ?>
                                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">
                                        ✅ Lunas / Approved
                                    </span>
                                <?php elseif($b['status'] == 'Rejected'): ?>
                                    <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">
                                        ❌ Ditolak
                                    </span>
                                <?php else: ?>
                                    <span class="bg-slate-100 text-slate-500 px-3 py-1 rounded-full text-[10px] font-black uppercase">
                                        ⚠️ Belum Bayar
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if($b['status'] == 'Paid' || $b['status'] == 'Verified'): ?>
                                    <span class="text-xs text-green-600 font-bold italic">Selesai</span>
                                <?php else: ?>
                                    <button onclick="paySpecific('<?= $b['payment_id'] ?>', '<?= $b['nama_event'] ?>', '<?= $b['total_tagihan'] ?>')" 
                                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-xs shadow-md transition transform hover:-translate-y-0.5">
                                        <?= ($b['status'] == 'Rejected') ? 'Upload Ulang' : 'Upload Bukti' ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="payModal" class="fixed inset-0 z-50 hidden bg-slate-900/80 backdrop-blur-sm flex justify-center items-center p-4 transition-opacity">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden transform scale-100 transition-all">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="font-black text-slate-800 uppercase tracking-tight">Upload Bukti Transfer</h3>
            <button onclick="document.getElementById('payModal').classList.add('hidden')" class="text-slate-400 hover:text-red-500 text-2xl font-bold transition">×</button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
            <input type="hidden" name="upload_proof" value="1">
            <input type="hidden" name="payment_id" id="modalPaymentId">
            
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Event</label>
                <input type="text" id="modalEventName" class="w-full border border-slate-200 bg-slate-100 rounded-lg p-3 text-sm font-bold text-slate-600" readonly>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Total Tagihan</label>
                <div class="relative">
                    <span class="absolute left-3 top-3 text-slate-400 font-bold text-sm">Rp</span>
                    <input type="text" id="modalAmount" class="w-full border border-slate-200 bg-slate-100 rounded-lg p-3 pl-10 text-sm font-mono font-bold text-slate-600" readonly>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">File Bukti (JPG/PNG/PDF)</label>
                <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer" required>
            </div>

            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl shadow-lg mt-2 transition transform hover:-translate-y-0.5">
                🚀 KIRIM BUKTI
            </button>
        </form>
    </div>
</div>

<script>
function paySpecific(paymentId, eventName, amount) {
    document.getElementById('modalPaymentId').value = paymentId;
    document.getElementById('modalEventName').value = eventName;
    // Format rupiah untuk tampilan modal
    let formattedAmount = new Intl.NumberFormat('id-ID').format(amount);
    document.getElementById('modalAmount').value = formattedAmount;
    
    document.getElementById('payModal').classList.remove('hidden');
}
</script>