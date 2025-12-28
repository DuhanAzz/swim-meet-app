<?php
// src/admin/entries/detail_club.php
session_start();

// 1. CONFIG DATABASE (Naik 2 level ke folder 'src')
require_once __DIR__ . '/../../config/database.php';

// CEK LOGIN ADMIN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$club_id = $_GET['id'] ?? 0;
$current_event_id = $_GET['event_id'] ?? 0; 

// --- HANDLE POST AKSI (TERIMA / TOLAK) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $payId = $_POST['payment_id'];
    $action = $_POST['action_type']; 
    
    // LOGIKA:
    // Approve -> Status 'Paid' (Data Terkunci)
    // Reject  -> Status 'Rejected' (Data Terbuka untuk Revisi User)
    $newStatus = ($action === 'approve') ? 'Paid' : 'Rejected';
    
    try {
        $stmtUpd = $pdo->prepare("UPDATE payments SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpd->execute([$newStatus, $payId]);
        
        // Redirect supaya refresh
        header("Location: detail_club.php?id=$club_id&event_id=$current_event_id"); exit;
    } catch (Exception $e) {
        echo "Error update: " . $e->getMessage();
    }
}

// 2. AMBIL DATA KLUB
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch();
if (!$club) { echo "Klub tidak ditemukan."; exit; }

// 3. AMBIL DATA PEMBAYARAN
$payData = null;
try {
    $stmtPay = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? AND event_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmtPay->execute([$club_id, $current_event_id]);
    $payData = $stmtPay->fetch();
} catch (Exception $e) {}

// 4. AMBIL DATA ENTRIES (DAFTAR ATLET)
$entries = [];
try {
    $sqlEntries = "
        SELECT 
            ent.entry_time as seed_time,
            s.nama_atlet, 
            s.jenis_kelamin as swimmer_gender, 
            s.tanggal_lahir,
            en.event_number,
            en.event_name,   
            en.distance,
            en.stroke,
            en.age_group     
        FROM event_entries ent
        JOIN swimmers s ON ent.swimmer_id = s.id
        JOIN event_numbers en ON ent.category_id = en.id
        WHERE ent.user_id = ? AND ent.event_id = ?
        ORDER BY s.nama_atlet ASC, en.event_number ASC
    ";
    
    $stmtEntries = $pdo->prepare($sqlEntries);
    $stmtEntries->execute([$club_id, $current_event_id]);
    $entries = $stmtEntries->fetchAll();
} catch (Exception $e) {
    echo "Error Database: " . $e->getMessage(); exit;
}

// 5. INCLUDE VIEWS (Naik 3 level ke folder Root 'swim-meet')
include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    @media print {
        .no-print { display: none !important; }
        body { background: white; }
        .print-full { width: 100% !important; max-width: none !important; }
    }
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="max-w-7xl mx-auto mb-6 flex items-center justify-between no-print">
        <div class="flex items-center gap-4">
            <a href="index.php" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-slate-900 hover:text-white transition shadow-sm">←</a>
            <div>
                <h1 class="text-2xl font-black uppercase italic text-slate-900 leading-none">Detail Entry</h1>
                <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-1">
                    <?= htmlspecialchars($club['nama_lengkap']) ?>
                </p>
            </div>
        </div>
        <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-xs font-bold uppercase shadow-lg shadow-blue-200 hover:bg-blue-700">
            🖨️ Cetak
        </button>
    </div>

    <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6 pb-20 print-full">
        
        <div class="space-y-6 no-print">
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-2xl">🏟️</div>
                    <div>
                        <h2 class="text-sm font-black text-slate-800 uppercase leading-tight"><?= htmlspecialchars($club['nama_lengkap']) ?></h2>
                        <p class="text-[10px] font-bold text-slate-400"><?= htmlspecialchars($club['email']) ?></p>
                    </div>
                </div>
                
                <div class="pt-4 border-t border-slate-100 flex justify-between items-center">
                    <span class="text-[10px] font-bold text-slate-400 uppercase">Status</span>
                    <?php 
                    $statusPay = $payData['status'] ?? 'Unpaid';
                    $color = match($statusPay) { 'Paid' => 'emerald', 'Pending' => 'amber', 'Rejected' => 'red', default => 'slate' };
                    
                    $labelStatus = match($statusPay) {
                        'Paid' => 'LUNAS / TERVERIFIKASI',
                        'Pending' => 'MENUNGGU VERIFIKASI',
                        'Rejected' => 'DITOLAK / REVISI',
                        default => 'BELUM BAYAR'
                    };
                    ?>
                    <span class="px-3 py-1 bg-<?= $color ?>-100 text-<?= $color ?>-700 rounded-full text-[10px] font-black uppercase border border-<?= $color ?>-200">
                        <?= $labelStatus ?>
                    </span>
                </div>
            </div>

            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Bukti Pembayaran</h3>
                
                <?php if(!empty($payData['file_path'])): ?>
                    <a href="../../../public/uploads/payments/<?= htmlspecialchars($payData['file_path']) ?>" target="_blank" class="block group relative rounded-xl overflow-hidden border border-slate-200 bg-slate-100 aspect-video flex items-center justify-center cursor-pointer shadow-sm mb-4">
                        <img src="../../../public/uploads/payments/<?= htmlspecialchars($payData['file_path']) ?>" class="object-contain w-full h-full">
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white font-bold text-[10px] uppercase backdrop-blur-sm">
                            Lihat Gambar
                        </div>
                    </a>
                    
                    <?php if($statusPay == 'Pending' || $statusPay == 'Paid'): ?>
                        <div class="space-y-2">
                            <?php if($statusPay == 'Pending'): ?>
                                <button onclick="openModal('approve')" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white py-3 rounded-xl text-xs font-black uppercase shadow-md transition transform hover:scale-[1.02]">
                                    ✓ Terima (Lock)
                                </button>
                                <button onclick="openModal('reject')" class="w-full bg-red-500 hover:bg-red-600 text-white py-3 rounded-xl text-xs font-black uppercase shadow-md transition transform hover:scale-[1.02]">
                                    ✕ Tolak / Minta Revisi
                                </button>
                                <p class="text-[10px] text-slate-400 text-center leading-tight mt-2">
                                    *Jika ditolak, Club bisa mengedit kembali data pendaftaran.
                                </p>
                            <?php elseif($statusPay == 'Paid'): ?>
                                <button onclick="openModal('reject')" class="w-full bg-slate-100 hover:bg-red-100 text-slate-500 hover:text-red-600 border border-slate-200 py-2 rounded-lg text-[10px] font-bold uppercase">
                                    Buka Kunci (Set Status Revisi)
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center py-6 border border-dashed border-slate-300 rounded-xl">
                        <p class="text-[10px] font-bold text-slate-400">Belum ada bukti upload</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if(!empty($payData['admin_file_path'])): ?>
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Berkas Entry Form</h3>
                <a href="../../../public/uploads/admin_files/<?= htmlspecialchars($payData['admin_file_path']) ?>" target="_blank" class="flex items-center justify-between p-3 bg-blue-50 border border-blue-100 rounded-xl hover:bg-blue-100 transition cursor-pointer">
                    <span class="text-[10px] font-bold text-blue-700 truncate w-40">📄 <?= htmlspecialchars($payData['admin_file_path']) ?></span>
                    <span class="text-[10px] font-black text-blue-600">UNDUH</span>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="lg:col-span-2 print-full">
            <div class="bg-white rounded-[2.5rem] border border-slate-200 shadow-sm overflow-hidden min-h-[500px]">
                
                <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <h3 class="text-sm font-black text-slate-800 uppercase italic">Daftar Atlet & Nomor</h3>
                    <span class="bg-slate-900 text-white px-3 py-1 rounded-lg text-[10px] font-black uppercase">Total: <?= count($entries) ?></span>
                </div>
                
                <?php if(empty($entries)): ?>
                    <div class="py-20 text-center opacity-50">
                        <p class="font-bold text-slate-400 text-sm">Tidak ada data entry atlet.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 text-slate-400 border-b border-slate-100">
                                <tr>
                                    <th class="py-3 px-5 text-[9px] font-black uppercase tracking-widest">Atlet</th>
                                    <th class="py-3 px-5 text-[9px] font-black uppercase tracking-widest">Detail Nomor Lomba</th>
                                    <th class="py-3 px-5 text-[9px] font-black uppercase tracking-widest">KU</th>
                                    <th class="py-3 px-5 text-[9px] font-black uppercase tracking-widest text-right">Seed Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-600">
                                <?php foreach($entries as $ent): 
                                    $jk = $ent['swimmer_gender']; 
                                    $bgIcon = ($jk == 'L') ? 'bg-blue-100 text-blue-600' : 'bg-pink-100 text-pink-600';
                                ?>
                                <tr class="hover:bg-slate-50 transition group">
                                    <td class="py-3 px-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full <?= $bgIcon ?> flex items-center justify-center text-xs font-black">
                                                <?= $jk ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-xs uppercase text-slate-800"><?= htmlspecialchars($ent['nama_atlet']) ?></div>
                                                <div class="text-[9px] text-slate-400 font-bold">Lahir: <?= $ent['tanggal_lahir'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="py-3 px-5">
                                        <div class="flex flex-col">
                                            <span class="text-[10px] font-black text-slate-700 uppercase">
                                                No. <?= $ent['event_number'] ?>
                                            </span>
                                            <span class="text-[11px] font-bold text-blue-600 uppercase italic">
                                                <?= htmlspecialchars($ent['event_name']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    
                                    <td class="py-3 px-5">
                                        <span class="inline-block px-2 py-1 bg-slate-100 rounded text-[9px] font-bold uppercase text-slate-500">
                                            <?= htmlspecialchars($ent['age_group']) ?>
                                        </span>
                                    </td>

                                    <td class="py-3 px-5 font-mono text-xs font-bold text-right text-slate-700">
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

<form id="actionForm" method="POST" class="hidden">
    <input type="hidden" name="payment_id" value="<?= $payData['id'] ?? '' ?>">
    <input type="hidden" name="action_type" id="modalActionInput">
</form>

<script>
function openModal(action) {
    let msg = '';
    if(action === 'approve') {
        msg = 'TERIMA PEMBAYARAN?\n\n- Status akan menjadi LUNAS.\n- Data user akan TERKUNCI (tidak bisa edit lagi).';
    } else {
        msg = 'TOLAK & MINTA REVISI?\n\n- Status akan menjadi REVISI (Rejected).\n- User dapat MENGEDIT kembali data pendaftarannya.\n- User harus melakukan Checkout ulang nanti.';
    }

    if(confirm(msg)) {
        document.getElementById('modalActionInput').value = action;
        document.getElementById('actionForm').submit();
    }
}
</script>