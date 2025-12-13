<?php
session_start();
// PERBAIKAN PATH: Hanya naik satu tingkat (../) ke folder src
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

// --- 1. HANDLE UPLOAD BUKTI BAYAR ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_proof'])) {
    $eventId = $_POST['event_id'];
    $amount  = $_POST['amount'];
    
    // Proses File
    $targetDir = "../../public/img/payments/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    
    $fileExt = pathinfo($_FILES["proof"]["name"], PATHINFO_EXTENSION);
    $fileName = "PAY_" . time() . "_" . $uid . "." . $fileExt;
    $targetFile = $targetDir . $fileName;
    $dbFilePath = "img/payments/" . $fileName;

    if (move_uploaded_file($_FILES["proof"]["tmp_name"], $targetFile)) {
        $stmt = $pdo->prepare("INSERT INTO event_payments (event_id, club_id, total_amount, proof_file, status) VALUES (?, ?, ?, ?, 'Pending')");
        $stmt->execute([$eventId, $uid, $amount, $dbFilePath]);
        $_SESSION['toast_type'] = 'success';
        $_SESSION['toast_message'] = 'Bukti pembayaran berhasil dikirim!';
    } else {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_message'] = 'Gagal mengupload gambar.';
    }
    header("Location: pembayaran.php"); exit;
}

// --- 2. AMBIL DATA PEMBAYARAN SAYA ---
$stmt = $pdo->prepare("
    SELECT ep.*, u.nama_lengkap as event_name 
    FROM event_payments ep 
    JOIN users u ON ep.event_id = u.id 
    WHERE ep.club_id = ? 
    ORDER BY ep.created_at DESC
");
$stmt->execute([$uid]);
$myPayments = $stmt->fetchAll();

// --- 3. AMBIL DAFTAR EVENT (Untuk Pilihan di Form) ---
$stmtEv = $pdo->prepare("SELECT id, nama_lengkap FROM users WHERE role = 'admin'");
$stmtEv->execute();
$availableEvents = $stmtEv->fetchAll();

// Path layout juga harus benar (naik dua tingkat ke root, lalu ke views)
include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Status Pembayaran</h1>
            <p class="text-sm text-slate-500">Kirim bukti transfer dan cek status verifikasi panitia.</p>
        </div>
        <button onclick="document.getElementById('payModal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-bold text-xs shadow-lg flex items-center gap-2">
            <span>💸</span> Konfirmasi Bayar
        </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-xs border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">Nama Kompetisi</th>
                    <th class="px-6 py-4">Total Bayar</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4">Tanggal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if(empty($myPayments)): ?>
                    <tr><td colspan="4" class="px-6 py-10 text-center text-slate-400 italic">Belum ada riwayat pembayaran.</td></tr>
                <?php else: ?>
                    <?php foreach($myPayments as $p): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4 font-bold text-slate-800"><?= htmlspecialchars($p['event_name']) ?></td>
                        <td class="px-6 py-4 font-mono font-bold text-blue-600">Rp <?= number_format($p['total_amount'], 0, ',', '.') ?></td>
                        <td class="px-6 py-4">
                            <?php if($p['status'] == 'Pending'): ?>
                                <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">Menunggu Verifikasi</span>
                            <?php elseif($p['status'] == 'Verified'): ?>
                                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">Lunas ✅</span>
                            <?php else: ?>
                                <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">Ditolak</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-400"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="payModal" class="fixed inset-0 z-50 hidden bg-slate-900/80 backdrop-blur-sm flex justify-center items-center p-4">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-black text-slate-800 uppercase">Upload Bukti Transfer</h3>
            <button onclick="document.getElementById('payModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-2xl">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="upload_proof" value="1">
            
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Pilih Kompetisi</label>
                <select name="event_id" class="w-full border rounded-lg p-2.5 text-sm font-bold bg-slate-50" required>
                    <?php foreach($availableEvents as $ev): ?>
                        <option value="<?= $ev['id'] ?>"><?= htmlspecialchars($ev['nama_lengkap']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Total Nominal</label>
                <input type="number" name="amount" class="w-full border rounded-lg p-2.5 text-sm font-mono font-bold" placeholder="Contoh: 150000" required>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Foto Bukti Transfer (JPG/PNG)</label>
                <input type="file" name="proof" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
            </div>

            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-lg mt-4 transition">KIRIM KONFIRMASI</button>
        </form>
    </div>
</div>
