<?php
// FILE: src/admin/entries/index.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id']; 

// --- AMBIL ID EVENT TERAKHIR MILIK ADMIN ---
// Karena di URL biasanya tidak ada event_id saat admin baru masuk menu ini
$targetEventId = $_GET['event_id'] ?? 0;
if ($targetEventId == 0) {
    $stmtLastEvt = $pdo->prepare("SELECT id FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtLastEvt->execute([$uid]);
    $targetEventId = $stmtLastEvt->fetchColumn() ?: 0;
}

// --- HANDLE QUICK ACTION (Validasi Pembayaran) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // APPROVE
    if (isset($_POST['approve_payment_id'])) {
        try {
            // PERBAIKAN: Ubah menjadi event_id dari form atau url
            $stmt = $pdo->prepare("UPDATE payments SET status = 'Paid', updated_at = NOW() WHERE id = ? AND event_id = ?");
            $stmt->execute([$_POST['approve_payment_id'], $targetEventId]);
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = 'Pembayaran Lunas! Klub dapat mencetak ID Card.';
        } catch (Exception $e) {}
        header("Location: index.php?event_id=" . $targetEventId); exit;
    }
    // REJECT
    if (isset($_POST['reject_payment_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE payments SET status = 'Rejected', updated_at = NOW() WHERE id = ? AND event_id = ?");
            $stmt->execute([$_POST['reject_payment_id'], $targetEventId]);
            $_SESSION['swal_type'] = 'warning'; $_SESSION['swal_msg'] = 'Pembayaran Ditolak.';
        } catch (Exception $e) {}
        header("Location: index.php?event_id=" . $targetEventId); exit;
    }
}

// --- AMBIL DATA ENTRY & PEMBAYARAN ---
try {
    // PERBAIKAN: Pastikan kolom p.amount dan p.file_path sesuai dengan database
    $sql = "SELECT 
                p.id as payment_id,
                p.status as payment_status,
                p.file_path,
                p.amount,
                p.event_id,
                u.id as club_id,
                u.nama_lengkap,
                u.email,
                (SELECT COUNT(*) FROM event_entries WHERE user_id = u.id AND event_id = p.event_id) as total_entries
            FROM payments p
            JOIN users u ON p.user_id = u.id
            WHERE p.event_id = ? 
            ORDER BY 
                CASE WHEN p.status = 'Pending' THEN 1 ELSE 2 END, 
                p.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$targetEventId]);
    $listData = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $listData = [];
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="max-w-[95%] mx-auto mb-8 flex flex-col md:flex-row justify-between items-end gap-4">
        <div>
            <h1 class="text-3xl font-black uppercase italic text-slate-900 leading-none">Verifikasi Pendaftaran</h1>
            <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-2">Pantau Klub & Pembayaran Masuk</p>
        </div>
        
        <div class="flex gap-3">
            <div class="px-5 py-2 bg-white rounded-xl shadow-sm border border-slate-200 text-right">
                <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-widest">Total Pendaftar</span>
                <span class="block text-xl font-black text-slate-800"><?= count($listData) ?></span>
            </div>
        </div>
    </div>

    <?php if(isset($_SESSION['swal_msg'])): ?>
        <div class="max-w-[95%] mx-auto mb-6 px-6 py-3 rounded-xl shadow-lg font-bold text-white flex items-center gap-3 text-xs uppercase tracking-wide <?= $_SESSION['swal_type']=='success' ? 'bg-emerald-500' : 'bg-red-500' ?>">
            <span><?= $_SESSION['swal_type']=='success' ? '✅' : '⚠️' ?></span>
            <?= $_SESSION['swal_msg']; unset($_SESSION['swal_msg']); unset($_SESSION['swal_type']); ?>
        </div>
    <?php endif; ?>

    <div class="max-w-[95%] mx-auto bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden min-h-[500px]">
        
        <?php if($targetEventId == 0): ?>
            <div class="flex flex-col items-center justify-center py-32 text-center opacity-50">
                <div class="text-5xl mb-4 grayscale">⚠️</div>
                <h3 class="font-black text-slate-400 uppercase tracking-widest text-lg">Anda Belum Memiliki Event Aktif</h3>
            </div>
        <?php elseif(empty($listData)): ?>
            <div class="flex flex-col items-center justify-center py-32 text-center opacity-50">
                <div class="text-5xl mb-4 grayscale">📭</div>
                <h3 class="font-black text-slate-400 uppercase tracking-widest text-lg">Belum Ada Pendaftar</h3>
            </div>
        <?php else: ?>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="py-4 px-6 text-[9px] font-black text-slate-400 uppercase tracking-widest w-12">No</th>
                            <th class="py-4 px-4 text-[9px] font-black text-slate-400 uppercase tracking-widest">Klub / Kontingen</th>
                            <th class="py-4 px-4 text-[9px] font-black text-slate-400 uppercase tracking-widest">Ringkasan</th>
                            <th class="py-4 px-4 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Bukti Bayar</th>
                            <th class="py-4 px-4 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                            <th class="py-4 px-6 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Aksi</th>
                        </tr>
                    </thead>
                    
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($listData as $i => $row): 
                            $status = $row['payment_status'];
                        ?>
                        <tr class="group hover:bg-slate-50 transition <?= $status == 'Pending' ? 'bg-amber-50/40' : '' ?>">
                            
                            <td class="py-4 px-6 font-black text-slate-300 italic"><?= $i + 1 ?></td>
                            
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-lg shadow-sm border border-slate-200">
                                        🏊
                                    </div>
                                    <div>
                                        <h4 class="font-black text-slate-800 uppercase text-xs">
                                            <?= htmlspecialchars($row['nama_lengkap']) ?>
                                        </h4>
                                        <div class="text-[10px] font-bold text-slate-400">
                                            <?= htmlspecialchars($row['email']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="py-4 px-4">
                                <div class="flex flex-col gap-1">
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-500">
                                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                        <?= $row['total_entries'] ?> Nomor Lomba
                                    </span>
                                    <span class="text-[10px] font-bold text-slate-400">
                                        Tagihan: <span class="text-emerald-600">Rp <?= number_format($row['amount'], 0, ',', '.') ?></span>
                                    </span>
                                </div>
                            </td>

                            <td class="py-4 px-4 text-center">
                                <?php if(!empty($row['file_path'])): ?>
                                    <a href="../../../public/uploads/payments/<?= htmlspecialchars($row['file_path']) ?>" target="_blank" class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 text-[9px] font-bold uppercase transition">
                                        👁️ Lihat Bukti
                                    </a>
                                <?php else: ?>
                                    <span class="text-[9px] font-bold text-slate-400 italic">Belum Upload</span>
                                <?php endif; ?>
                            </td>

                            <td class="py-4 px-4 text-center">
                                <?php 
                                $badgeClass = match($status) {
                                    'Paid' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                    'completed' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                    'Pending' => 'bg-amber-100 text-amber-700 border-amber-200 animate-pulse',
                                    'Rejected' => 'bg-red-100 text-red-700 border-red-200',
                                    default => 'bg-slate-100 text-slate-500 border-slate-200'
                                };
                                ?>
                                <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border <?= $badgeClass ?>">
                                    <?= $status == 'completed' ? 'Paid' : $status ?>
                                </span>
                            </td>

                            <td class="py-4 px-6 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    
                                    <a href="detail_club.php?id=<?= $row['club_id'] ?>&event_id=<?= $row['event_id'] ?>" 
                                       class="px-4 py-2 rounded-lg bg-slate-800 text-white hover:bg-slate-900 text-[10px] font-black uppercase transition shadow-lg shadow-slate-200">
                                        Lihat Detail
                                    </a>

                                    <?php if($status == 'Pending'): ?>
                                        <form method="POST" onsubmit="return confirm('Tolak Pembayaran ini?');">
                                            <input type="hidden" name="reject_payment_id" value="<?= $row['payment_id'] ?>">
                                            <button type="submit" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 border border-red-200 hover:bg-red-500 hover:text-white flex items-center justify-center transition" title="Tolak">
                                                ✕
                                            </button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Verifikasi LUNAS?');">
                                            <input type="hidden" name="approve_payment_id" value="<?= $row['payment_id'] ?>">
                                            <button type="submit" class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-200 hover:bg-emerald-500 hover:text-white flex items-center justify-center transition" title="Terima">
                                                ✓
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                </div>
                            </td>

                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>
</div>