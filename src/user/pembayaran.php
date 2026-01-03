<?php
// FILE: src/user/pembayaran.php (VERSI FINAL BERSIH)
session_start();
require_once __DIR__ . '/../config/database.php';

// CEK LOGIN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}
$uid = $_SESSION['user_id'];

// --- A. HANDLE UPLOAD BUKTI ---
if (isset($_POST['upload_proof'])) {
    $paymentId = $_POST['payment_id'];
    $targetDir = __DIR__ . "/../../../public/uploads/payments/"; 
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    
    $fileExt = strtolower(pathinfo($_FILES["proof"]["name"], PATHINFO_EXTENSION));
    $fileName = "PAY_" . $uid . "_" . time() . "." . $fileExt;
    
    if (move_uploaded_file($_FILES["proof"]["tmp_name"], $targetDir . $fileName)) {
        $stmt = $pdo->prepare("UPDATE payments SET file_path = ?, status = 'Pending', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$fileName, $paymentId]);
        $_SESSION['toast_type'] = 'success';
        $_SESSION['toast_msg'] = 'Bukti pembayaran dikirim!';
    }
    header("Location: pembayaran.php"); exit;
}

// --- B. AMBIL DATA ---
$bills = [];
$stmtPay = $pdo->prepare("
    SELECT p.*, e.nama_event 
    FROM payments p 
    LEFT JOIN events e ON p.event_id = e.id 
    WHERE p.user_id = ? 
    ORDER BY p.id DESC
");
$stmtPay->execute([$uid]);
$payments = $stmtPay->fetchAll(PDO::FETCH_ASSOC);

foreach ($payments as $pay) {
    $eid = $pay['event_id'];
    $eventName = $pay['nama_event'] ? $pay['nama_event'] : "Event ID #$eid (Tidak Dikenal)";
    
    // Hitung Atlet
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM event_entries WHERE event_id = ? AND user_id = ?");
    $stmtCount->execute([$eid, $uid]);
    $countEntries = $stmtCount->fetchColumn();

    $bills[] = [
        'id'            => $pay['id'],
        'event_id'      => $eid,
        'nama_event'    => $eventName,
        'amount'        => $pay['amount'],
        'status'        => $pay['status'],
        'entries'       => $countEntries
    ];
}

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="mb-8">
        <h1 class="text-3xl font-black text-slate-800 uppercase italic">Tagihan Saya</h1>
        <p class="text-slate-500 text-sm">Kelola pembayaran event Anda.</p>
    </div>

    <?php if(isset($_SESSION['toast_msg'])): ?>
        <div class="mb-6 px-6 py-4 bg-emerald-500 text-white rounded-xl shadow-lg font-bold flex items-center gap-3 animate-fade-in-down">
            <span>✅</span>
            <?= $_SESSION['toast_msg']; unset($_SESSION['toast_msg']); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-[10px] border-b">
                <tr>
                    <th class="px-6 py-4">Event</th>
                    <th class="px-6 py-4">Total Tagihan</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach($bills as $b): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4 align-top">
                        <div class="font-bold text-slate-800 text-base uppercase italic mb-1">
                            <?= htmlspecialchars($b['nama_event']) ?>
                        </div>
                        <span class="text-[10px] bg-slate-100 px-2 py-1 rounded text-slate-500 font-mono">
                            #INV-<?= str_pad($b['id'], 5, '0', STR_PAD_LEFT) ?>
                        </span>
                        <div class="mt-1 text-xs text-slate-500">
                            Atlet Terdaftar: <b><?= $b['entries'] ?></b>
                        </div>
                    </td>
                    
                    <td class="px-6 py-4 align-middle">
                        <div class="font-black text-xl text-slate-700">
                            Rp<?= number_format($b['amount'], 0, ',', '.') ?>
                        </div>
                    </td>

                    <td class="px-6 py-4 align-middle">
                        <?php if($b['status'] == 'Paid'): ?>
                            <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-[10px] font-bold uppercase">Lunas</span>
                        <?php elseif($b['status'] == 'Pending'): ?>
                            <span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-[10px] font-bold uppercase">Verifikasi</span>
                        <?php else: ?>
                            <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-full text-[10px] font-bold uppercase">Unpaid</span>
                        <?php endif; ?>
                    </td>

                    <td class="px-6 py-4 align-middle text-right">
                        <?php if($b['status'] != 'Paid' && $b['status'] != 'Pending'): ?>
                            <button onclick="bukaModal('<?= $b['id'] ?>', '<?= htmlspecialchars($b['nama_event']) ?>', '<?= $b['amount'] ?>')" 
                                class="bg-slate-800 text-white px-5 py-2.5 rounded-xl font-bold text-[10px] uppercase shadow hover:bg-slate-900 transition">
                                Upload Bukti
                            </button>
                        <?php elseif($b['status'] == 'Paid'): ?>
                            <a href="../kompetisi/register_event.php?event_id=<?= $b['event_id'] ?>" class="text-blue-600 font-bold text-xs hover:underline">
                                Lihat Data
                            </a>
                        <?php else: ?>
                            <button disabled class="text-slate-400 font-bold text-xs cursor-not-allowed">Sedang Dicek</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="uploadModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-sm rounded-2xl p-6 shadow-2xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-slate-800">Upload Bukti Transfer</h3>
            <button onclick="document.getElementById('uploadModal').classList.add('hidden')" class="text-2xl text-slate-400">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="upload_proof" value="1">
            <input type="hidden" name="payment_id" id="modalPayId">
            
            <div class="bg-slate-50 p-3 rounded-lg">
                <p class="text-[10px] text-slate-400 font-bold uppercase">Event</p>
                <p class="font-bold text-slate-700 text-xs truncate" id="modalEventName"></p>
            </div>
            <div class="bg-emerald-50 p-3 rounded-lg border border-emerald-100">
                <p class="text-[10px] text-emerald-500 font-bold uppercase">Total Bayar</p>
                <p class="font-bold text-emerald-700 text-lg" id="modalAmount"></p>
            </div>
            
            <input type="file" name="proof" required class="block w-full text-xs text-slate-500 border rounded p-1">
            
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl uppercase text-xs hover:bg-blue-700">Kirim</button>
        </form>
    </div>
</div>

<script>
function bukaModal(id, nama, amount) {
    document.getElementById('modalPayId').value = id;
    document.getElementById('modalEventName').innerText = nama;
    document.getElementById('modalAmount').innerText = "Rp" + new Intl.NumberFormat('id-ID').format(amount);
    document.getElementById('uploadModal').classList.remove('hidden');
}
</script>