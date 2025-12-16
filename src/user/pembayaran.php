<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

// --- 1. HANDLE UPLOAD BUKTI BAYAR ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_proof'])) {
    $eventId = $_POST['event_id'];
    $amount  = $_POST['amount'];
    
    // Path Upload
    $targetDir = __DIR__ . "/../../public/uploads/"; 
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    
    // Validasi Ekstensi
    $fileExt = strtolower(pathinfo($_FILES["proof"]["name"], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    
    if (in_array($fileExt, $allowed)) {
        // Nama file unik: proof_EVENTID_USERID_TIME.ext
        $fileName = "proof_" . $eventId . "_" . $uid . "_" . time() . "." . $fileExt;
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["proof"]["tmp_name"], $targetFile)) {
            // Cek apakah sudah pernah upload (Re-upload jika ditolak)
            $check = $pdo->prepare("SELECT id FROM event_payments WHERE event_id = ? AND club_id = ?");
            $check->execute([$eventId, $uid]);
            $exists = $check->fetch();

            if ($exists) {
                // Update Data Lama
                $sql = "UPDATE event_payments SET total_amount = ?, proof_file = ?, status = 'Pending', created_at = NOW() WHERE id = ?";
                $pdo->prepare($sql)->execute([$amount, $fileName, $exists['id']]);
            } else {
                // Insert Data Baru
                $sql = "INSERT INTO event_payments (event_id, club_id, total_amount, proof_file, status) VALUES (?, ?, ?, ?, 'Pending')";
                $pdo->prepare($sql)->execute([$eventId, $uid, $amount, $fileName]);
            }

            $_SESSION['toast_type'] = 'success';
            $_SESSION['toast_message'] = 'Bukti pembayaran berhasil dikirim!';
        } else {
            $_SESSION['toast_type'] = 'error';
            $_SESSION['toast_message'] = 'Gagal mengupload file ke server.';
        }
    } else {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_message'] = 'Format file harus JPG, PNG, atau PDF.';
    }
    header("Location: pembayaran.php"); exit;
}

// --- 2. AMBIL DATA TAGIHAN & PEMBAYARAN SAYA ---
// PERBAIKAN SQL: Mengganti ev.event_start_date menjadi ev.tanggal_lomba
$sqlBills = "
    SELECT 
        ev.id as event_id,
        ev.nama_event,
        ev.tanggal_lomba, 
        ev.harga_pendaftaran,
        COUNT(e.id) as jumlah_atlet,
        (COUNT(e.id) * ev.harga_pendaftaran) as estimasi_bayar,
        ep.status,
        ep.total_amount as amount_paid,
        ep.created_at as paid_date
    FROM events ev
    JOIN entries e ON e.event_id = ev.id
    JOIN swimmers s ON e.swimmer_id = s.id
    LEFT JOIN event_payments ep ON (ep.event_id = ev.id AND ep.club_id = ?)
    WHERE s.user_id = ?
    GROUP BY ev.id
    ORDER BY ev.tanggal_lomba DESC
";
$stmt = $pdo->prepare($sqlBills);
$stmt->execute([$uid, $uid]);
$bills = $stmt->fetchAll();

// --- 3. AMBIL LIST EVENT (Untuk Dropdown Modal) ---
$stmtEv = $pdo->prepare("
    SELECT DISTINCT ev.id, ev.nama_event 
    FROM events ev 
    JOIN entries e ON e.event_id = ev.id 
    JOIN swimmers s ON e.swimmer_id = s.id 
    WHERE s.user_id = ?
");
$stmtEv->execute([$uid]);
$availableEvents = $stmtEv->fetchAll();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Keuangan & Tagihan</h1>
            <p class="text-sm text-slate-500">Pantau tagihan lomba dan status verifikasi pembayaran.</p>
        </div>
        <button onclick="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-bold text-xs shadow-lg flex items-center gap-2 transition transform hover:-translate-y-1">
            <span>📤</span> Upload Bukti Bayar
        </button>
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
                        <th class="px-6 py-4">Nama Kompetisi</th>
                        <th class="px-6 py-4 text-center">Peserta</th>
                        <th class="px-6 py-4">Tagihan (Est)</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($bills)): ?>
                        <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400 italic">Belum ada pendaftaran lomba.</td></tr>
                    <?php else: ?>
                        <?php foreach($bills as $b): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800"><?= htmlspecialchars($b['nama_event']) ?></div>
                                <div class="text-[10px] text-slate-400 mt-1 italic">
                                    📅 <?= ($b['tanggal_lomba']) ? date('d M Y', strtotime($b['tanggal_lomba'])) : '-' ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-xs font-bold"><?= $b['jumlah_atlet'] ?> Atlet</span>
                            </td>
                            <td class="px-6 py-4 font-mono font-bold text-slate-700">
                                Rp <?= number_format($b['estimasi_bayar'], 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if($b['status'] == 'Pending'): ?>
                                    <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-[10px] font-black uppercase animate-pulse">Menunggu Verifikasi</span>
                                <?php elseif($b['status'] == 'Verified'): ?>
                                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">Lunas ✅</span>
                                <?php elseif($b['status'] == 'Rejected'): ?>
                                    <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">Ditolak ❌</span>
                                <?php else: ?>
                                    <span class="bg-slate-100 text-slate-500 px-3 py-1 rounded-full text-[10px] font-black uppercase">Belum Bayar</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if($b['status'] != 'Verified'): ?>
                                    <button onclick="paySpecific('<?= $b['event_id'] ?>', '<?= $b['estimasi_bayar'] ?>')" class="text-blue-600 hover:text-blue-800 font-bold text-xs underline">
                                        Upload Bukti
                                    </button>
                                <?php else: ?>
                                    <span class="text-xs text-slate-300 font-bold italic">Selesai</span>
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
            <h3 class="font-black text-slate-800 uppercase tracking-tight">Konfirmasi Pembayaran</h3>
            <button onclick="document.getElementById('payModal').classList.add('hidden')" class="text-slate-400 hover:text-red-500 text-2xl font-bold transition">×</button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
            <input type="hidden" name="upload_proof" value="1">
            
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Pilih Kompetisi</label>
                <select name="event_id" id="modalEventId" class="w-full border border-slate-300 rounded-lg p-3 text-sm font-bold bg-white focus:ring-2 focus:ring-blue-500 outline-none" required>
                    <?php foreach($availableEvents as $ev): ?>
                        <option value="<?= $ev['id'] ?>"><?= htmlspecialchars($ev['nama_event']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Nominal Transfer (Rp)</label>
                <div class="relative">
                    <span class="absolute left-3 top-3 text-slate-400 font-bold text-sm">Rp</span>
                    <input type="number" name="amount" id="modalAmount" class="w-full border border-slate-300 rounded-lg p-3 pl-10 text-sm font-mono font-bold focus:ring-2 focus:ring-blue-500 outline-none" placeholder="0" required>
                </div>
                <p class="text-[10px] text-slate-400 mt-1">*Pastikan nominal sesuai dengan tagihan.</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Bukti Transfer (JPG/PNG/PDF)</label>
                <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer" required>
            </div>

            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl shadow-lg mt-2 transition transform hover:-translate-y-0.5">
                🚀 KIRIM BUKTI BAYAR
            </button>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('payModal').classList.remove('hidden');
}

function paySpecific(eventId, amount) {
    document.getElementById('modalEventId').value = eventId;
    document.getElementById('modalAmount').value = amount;
    openModal();
}
</script>