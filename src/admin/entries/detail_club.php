<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$club_id = $_GET['id'] ?? 0;
// Default Event ID = 2
$current_event_id = $_GET['event_id'] ?? 2; 

// --- HANDLE POST AKSI (TERIMA/TOLAK) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $payId = $_POST['payment_id'];
    $action = $_POST['action_type']; 
    $newStatus = ($action === 'approve') ? 'Paid' : 'Rejected';
    
    try {
        $stmtUpd = $pdo->prepare("UPDATE payments SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpd->execute([$newStatus, $payId]);
        // Refresh page
        header("Location: detail_club.php?id=" . $club_id . "&event_id=" . $current_event_id); exit;
    } catch (Exception $e) {
        echo "Error Update: " . $e->getMessage();
    }
}

// --- 1. AMBIL DATA KLUB (USER) ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch();
if (!$club) { echo "Data Klub tidak ditemukan."; exit; }

// --- 2. AMBIL DATA PEMBAYARAN ---
$payData = null;
try {
    $stmtPay = $pdo->prepare("
        SELECT p.*, e.nama_event 
        FROM payments p
        JOIN events e ON p.event_id = e.id
        WHERE p.user_id = ? AND p.event_id = ?
        ORDER BY p.created_at DESC LIMIT 1
    ");
    $stmtPay->execute([$club_id, $current_event_id]);
    $payData = $stmtPay->fetch();
} catch (Exception $e) {}

// --- 3. AMBIL DATA ENTRIES (FIXED QUERY) ---
// Perbaikan: Menggunakan 'en.jenis_kelamin' sesuai struktur database Anda
$entries = [];
try {
    $stmtEntries = $pdo->prepare("
        SELECT 
            ent.entry_time as seed_time,
            s.nama_atlet, 
            s.jenis_kelamin as swimmer_gender, 
            en.event_number,
            en.distance,
            en.stroke,
            en.age_group,
            en.jenis_kelamin as category_gender
        FROM event_entries ent
        JOIN swimmers s ON ent.swimmer_id = s.id
        JOIN event_numbers en ON ent.category_id = en.id
        WHERE ent.user_id = ? AND ent.event_id = ?
        ORDER BY s.nama_atlet ASC
    ");
    $stmtEntries->execute([$club_id, $current_event_id]);
    $entries = $stmtEntries->fetchAll();
} catch (Exception $e) {
    echo "<div class='p-4 bg-red-100 text-red-700 font-bold'>Error Database: " . $e->getMessage() . "</div>";
    exit;
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    .modal-enter { opacity: 0; transform: scale(0.95); }
    .modal-enter-active { opacity: 1; transform: scale(1); transition: all 0.2s ease-out; }
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="max-w-7xl mx-auto mb-8 flex items-center gap-4">
        <a href="index.php" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-slate-900 hover:text-white transition shadow-sm">←</a>
        <div>
            <h1 class="text-2xl font-black uppercase italic text-slate-900 leading-none">Detail Klub</h1>
            <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-1">Verifikasi Data & Atlet</p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6 pb-20">
        
        <div class="space-y-6">
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Nama Klub</span>
                <h2 class="text-xl font-black text-slate-800 uppercase italic leading-none mb-2"><?= htmlspecialchars($club['nama_lengkap']) ?></h2>
                <div class="text-xs font-bold text-slate-500 mb-4"><?= htmlspecialchars($club['email']) ?></div>
                
                <div class="mt-4 pt-4 border-t border-slate-100 flex justify-between items-center">
                    <span class="text-[10px] font-bold text-slate-400 uppercase">Status Pembayaran</span>
                    <?php 
                    $statusPay = $payData['status'] ?? 'Unpaid';
                    $color = match($statusPay) { 'Paid' => 'emerald', 'Pending' => 'amber', 'Rejected' => 'red', default => 'slate' };
                    ?>
                    <span class="px-3 py-1 bg-<?= $color ?>-100 text-<?= $color ?>-700 rounded-full text-[10px] font-black uppercase border border-<?= $color ?>-200">
                        <?= $statusPay ?>
                    </span>
                </div>
            </div>

            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm">
                <h3 class="text-sm font-black text-slate-800 uppercase italic mb-3">📁 Berkas Administrasi</h3>
                <?php if(!empty($payData['admin_file_path'])): ?>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 flex justify-between items-center">
                        <div class="text-xs font-bold text-slate-800 truncate w-32"><?= htmlspecialchars($payData['admin_file_path']) ?></div>
                        <a href="../../../public/uploads/admin_files/<?= htmlspecialchars($payData['admin_file_path']) ?>" target="_blank" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-[10px] font-bold shadow-md">Unduh</a>
                    </div>
                <?php else: ?>
                    <p class="text-[10px] font-bold text-slate-400 uppercase text-center py-4 border border-dashed rounded-xl">Belum ada berkas</p>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm">
                <h3 class="text-sm font-black text-slate-800 uppercase italic mb-3">💳 Bukti Transfer</h3>
                <?php if(!empty($payData['file_path'])): ?>
                    <div class="group relative rounded-xl overflow-hidden border border-slate-200 bg-slate-100 aspect-video flex items-center justify-center cursor-pointer shadow-sm mb-4">
                        <img src="../../../public/uploads/payments/<?= htmlspecialchars($payData['file_path']) ?>" class="object-contain w-full h-full">
                        <a href="../../../public/uploads/payments/<?= htmlspecialchars($payData['file_path']) ?>" target="_blank" class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white font-bold text-[10px] uppercase tracking-widest backdrop-blur-sm">Lihat Full</a>
                    </div>

                    <?php if($statusPay == 'Pending'): ?>
                        <div class="grid grid-cols-2 gap-3">
                            <form id="form-approve" method="POST">
                                <input type="hidden" name="payment_id" value="<?= $payData['id'] ?>">
                                <input type="hidden" name="action_type" value="approve">
                                <button type="button" onclick="openModal('approve')" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white py-3 rounded-xl text-[10px] font-black uppercase tracking-wider shadow-lg shadow-emerald-200 transition transform active:scale-95">✓ Terima</button>
                            </form>
                            <form id="form-reject" method="POST">
                                <input type="hidden" name="payment_id" value="<?= $payData['id'] ?>">
                                <input type="hidden" name="action_type" value="reject">
                                <button type="button" onclick="openModal('reject')" class="w-full bg-red-500 hover:bg-red-600 text-white py-3 rounded-xl text-[10px] font-black uppercase tracking-wider shadow-lg shadow-red-200 transition transform active:scale-95">✕ Tolak</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-[10px] font-bold text-slate-400 uppercase text-center py-4 border border-dashed rounded-xl">Belum upload bukti</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white rounded-[2.5rem] border border-slate-200 shadow-sm overflow-hidden min-h-[500px]">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <h3 class="text-lg font-black text-slate-800 uppercase italic">Matriks Atlet</h3>
                    <span class="bg-slate-900 text-white px-3 py-1 rounded-lg text-[10px] font-black uppercase">Total: <?= count($entries) ?></span>
                </div>
                
                <?php if(empty($entries)): ?>
                    <div class="py-20 text-center opacity-50"><p class="font-bold text-slate-300">Tidak ada data entry.</p></div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 text-slate-400 border-b border-slate-100">
                                <tr>
                                    <th class="py-3 px-5 text-[9px] font-black uppercase tracking-widest">Atlet</th>
                                    <th class="py-3 px-5 text-[9px] font-black uppercase tracking-widest">Gender</th>
                                    <th class="py-3 px-5 text-[9px] font-black uppercase tracking-widest">Nomor Lomba</th>
                                    <th class="py-3 px-5 text-[9px] font-black uppercase tracking-widest text-right">Waktu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-600">
                                <?php foreach($entries as $ent): 
                                    // Logika Gender Database: 'L' = Laki-laki, 'P' = Perempuan
                                    $gCode = strtoupper($ent['swimmer_gender']); 
                                    $isMale = ($gCode === 'L' || $gCode === 'M' || $gCode === 'MALE');
                                    
                                    $gClass = $isMale ? 'text-blue-600 bg-blue-50' : 'text-pink-600 bg-pink-50';
                                    $gLabel = $isMale ? 'PUTRA' : 'PUTRI';
                                ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-3 px-5 font-bold text-xs uppercase text-slate-700">
                                        <?= htmlspecialchars($ent['nama_atlet']) ?>
                                    </td>
                                    <td class="py-3 px-5">
                                        <span class="px-2 py-1 rounded text-[9px] font-bold uppercase <?= $gClass ?>">
                                            <?= $gLabel ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-5">
                                        <div class="flex flex-col">
                                            <span class="text-[10px] font-black text-blue-600 uppercase">
                                                #<?= htmlspecialchars($ent['event_number']) ?> - <?= $ent['distance'] ?>m <?= ucfirst($ent['stroke']) ?>
                                            </span>
                                            <span class="text-[9px] font-bold text-slate-400 uppercase">
                                                KU <?= $ent['age_group'] ?> 
                                                <span class="text-slate-300 mx-1">|</span> 
                                                Kategori: <?= ($ent['category_gender'] == 'L') ? 'Putra' : (($ent['category_gender'] == 'P') ? 'Putri' : 'Mix') ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-5 font-mono text-xs font-bold text-right">
                                        <?= htmlspecialchars($ent['seed_time'] ?? 'NT') ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="confirmModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity opacity-0" id="modalBackdrop"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div id="modalPanel" class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg opacity-0 scale-95">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div id="modalIconBg" class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg id="modalIconCheck" class="h-6 w-6 text-emerald-600 hidden" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            <svg id="modalIconX" class="h-6 w-6 text-red-600 hidden" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg font-black leading-6 text-slate-900 uppercase italic" id="modalTitle">Konfirmasi</h3>
                            <div class="mt-2"><p class="text-sm text-slate-500 font-medium" id="modalDesc">Yakin?</p></div>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                    <button type="button" id="btnConfirm" class="inline-flex w-full justify-center rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-black text-white shadow-sm hover:bg-emerald-500 sm:ml-3 sm:w-auto uppercase tracking-wider transition-colors">YA, LANJUTKAN</button>
                    <button type="button" onclick="closeModal()" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-5 py-2.5 text-xs font-bold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto uppercase tracking-wider transition-colors">BATAL</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let currentAction = null;
    const modal = document.getElementById('confirmModal');
    const modalBackdrop = document.getElementById('modalBackdrop');
    const modalPanel = document.getElementById('modalPanel');
    const btnConfirm = document.getElementById('btnConfirm');
    const title = document.getElementById('modalTitle');
    const desc = document.getElementById('modalDesc');
    const iconBg = document.getElementById('modalIconBg');
    const iconCheck = document.getElementById('modalIconCheck');
    const iconX = document.getElementById('modalIconX');

    function openModal(action) {
        currentAction = action;
        modal.classList.remove('hidden');
        iconCheck.classList.add('hidden');
        iconX.classList.add('hidden');
        
        setTimeout(() => {
            modalBackdrop.classList.remove('opacity-0');
            modalPanel.classList.remove('opacity-0', 'scale-95');
            modalPanel.classList.add('opacity-100', 'scale-100');
        }, 10);

        if (action === 'approve') {
            title.textContent = "TERIMA PEMBAYARAN";
            desc.textContent = "Data akan diverifikasi sebagai LUNAS.";
            iconBg.className = "mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 sm:mx-0 sm:h-10 sm:w-10";
            iconCheck.classList.remove('hidden');
            btnConfirm.className = "inline-flex w-full justify-center rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-black text-white shadow-sm hover:bg-emerald-500 sm:ml-3 sm:w-auto uppercase tracking-wider";
            btnConfirm.textContent = "YA, TERIMA";
        } else {
            title.textContent = "TOLAK PEMBAYARAN";
            desc.textContent = "Anda akan menolak bukti transfer ini.";
            iconBg.className = "mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10";
            iconX.classList.remove('hidden');
            btnConfirm.className = "inline-flex w-full justify-center rounded-xl bg-red-600 px-5 py-2.5 text-xs font-black text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto uppercase tracking-wider";
            btnConfirm.textContent = "YA, TOLAK";
        }
    }

    function closeModal() {
        modalBackdrop.classList.add('opacity-0');
        modalPanel.classList.remove('opacity-100', 'scale-100');
        modalPanel.classList.add('opacity-0', 'scale-95');
        setTimeout(() => { modal.classList.add('hidden'); }, 200);
    }

    btnConfirm.addEventListener('click', function() {
        if (currentAction === 'approve') document.getElementById('form-approve').submit();
        else if (currentAction === 'reject') document.getElementById('form-reject').submit();
    });
    
    modal.addEventListener('click', function(e) {
        if (e.target === modalBackdrop) closeModal();
    });
</script>