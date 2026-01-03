<?php
// FILE: src/admin/entries/detail_club.php
session_start();

// 1. CONFIG DATABASE
require_once __DIR__ . '/../../config/database.php';

// CEK LOGIN ADMIN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

// Tangkap ID User (Akun Klub/Pendaftar) dan ID Event
$targetUserId = $_GET['id'] ?? 0;
$eventId      = $_GET['event_id'] ?? 0;

if ($targetUserId == 0 || $eventId == 0) {
    echo "Parameter URL tidak lengkap (id atau event_id hilang)."; exit;
}

// --- HANDLE POST AKSI (TERIMA / TOLAK PEMBAYARAN) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $payId = $_POST['payment_id'];
    $action = $_POST['action_type']; 
    
    $newStatus = ($action === 'approve') ? 'Paid' : 'Rejected';
    
    try {
        $stmtUpd = $pdo->prepare("UPDATE payments SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpd->execute([$newStatus, $payId]);
        header("Location: detail_club.php?id=$targetUserId&event_id=$eventId"); exit;
    } catch (Exception $e) {
        echo "Error update: " . $e->getMessage();
    }
}

// 2. AMBIL DATA AKUN PENDAFTAR (KLUB/USER)
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$targetUserId]);
$userData = $stmtUser->fetch();
if (!$userData) { echo "User tidak ditemukan."; exit; }

// --- PERBAIKAN ERROR "UNDEFINED KEY" ---
// Kita cari nama yang tersedia di database (nama_lengkap / nama / name)
$namaUser = $userData['nama_lengkap'] ?? $userData['nama'] ?? $userData['name'] ?? $userData['username'] ?? 'User ID: ' . $targetUserId;
$emailUser = $userData['email'] ?? '-';

// 3. AMBIL DATA PEMBAYARAN TERBARU
$payData = null;
$stmtPay = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? AND event_id = ? ORDER BY created_at DESC LIMIT 1");
$stmtPay->execute([$targetUserId, $eventId]);
$payData = $stmtPay->fetch();

// 4. AMBIL DATA ENTRIES
$entries = [];
try {
    // Menggunakan 'en.jenis_kelamin' (sesuai pengecekan database terakhir)
    $sqlEntries = "
        SELECT 
            ent.id as entry_id,
            ent.entry_time,
            s.nama_atlet, 
            s.jenis_kelamin as swimmer_gender, 
            s.tanggal_lahir,
            en.distance,
            en.stroke,
            en.age_group,
            en.jenis_kelamin as event_gender 
        FROM event_entries ent
        JOIN swimmers s ON ent.swimmer_id = s.id
        JOIN event_numbers en ON ent.category_id = en.id
        WHERE ent.user_id = ? AND ent.event_id = ?
        ORDER BY s.nama_atlet ASC, en.distance ASC
    ";
    
    $stmtEntries = $pdo->prepare($sqlEntries);
    $stmtEntries->execute([$targetUserId, $eventId]);
    $entries = $stmtEntries->fetchAll();
} catch (Exception $e) {
    echo "Error Database: " . $e->getMessage(); exit;
}

// 5. INCLUDE LAYOUT
include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    @media print {
        .no-print { display: none !important; }
        body { background: white; }
        .print-full { width: 100% !important; max-width: none !important; }
        aside { display: none; }
        .sm\:ml-64 { margin-left: 0 !important; }
    }
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="max-w-7xl mx-auto mb-6 flex items-center justify-between no-print">
        <div class="flex items-center gap-4">
            <a href="index.php?event_id=<?= $eventId ?>" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-slate-900 hover:text-white transition shadow-sm">←</a>
            <div>
                <h1 class="text-2xl font-black uppercase italic text-slate-900 leading-none">Verifikasi Entry</h1>
                <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-1">
                    Pendaftar: <span class="text-blue-600"><?= htmlspecialchars($namaUser) ?></span>
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
                    <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-2xl">👤</div>
                    <div class="overflow-hidden">
                        <h2 class="text-sm font-black text-slate-800 uppercase leading-tight truncate"><?= htmlspecialchars($namaUser) ?></h2>
                        <p class="text-[10px] font-bold text-slate-400 truncate"><?= htmlspecialchars($emailUser) ?></p>
                    </div>
                </div>
                
                <div class="pt-4 border-t border-slate-100 flex justify-between items-center">
                    <span class="text-[10px] font-bold text-slate-400 uppercase">Status Bayar</span>
                    <?php 
                    $statusPay = $payData['status'] ?? 'Unpaid';
                    $color = match($statusPay) { 'Paid' => 'emerald', 'Pending' => 'amber', 'Rejected' => 'red', default => 'slate' };
                    $labelStatus = match($statusPay) {
                        'Paid' => 'LUNAS (VERIFIED)',
                        'Pending' => 'PERLU CEK',
                        'Rejected' => 'DITOLAK (REVISI)',
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
                        <?php $ext = pathinfo($payData['file_path'], PATHINFO_EXTENSION); ?>
                        <?php if(in_array(strtolower($ext), ['jpg','jpeg','png'])): ?>
                            <img src="../../../public/uploads/payments/<?= htmlspecialchars($payData['file_path']) ?>" class="object-contain w-full h-full">
                        <?php else: ?>
                            <span class="text-4xl">📄</span>
                        <?php endif; ?>
                        
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white font-bold text-[10px] uppercase backdrop-blur-sm">
                            Lihat File Asli
                        </div>
                    </a>
                    
                    <?php if($statusPay == 'Pending' || $statusPay == 'Paid'): ?>
                        <div class="space-y-2">
                            <?php if($statusPay == 'Pending'): ?>
                                <button onclick="openModal('approve')" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white py-3 rounded-xl text-xs font-black uppercase shadow-md transition transform hover:scale-[1.02]">
                                    ✓ Terima (Lock Data)
                                </button>
                                <button onclick="openModal('reject')" class="w-full bg-red-500 hover:bg-red-600 text-white py-3 rounded-xl text-xs font-black uppercase shadow-md transition transform hover:scale-[1.02]">
                                    ✕ Tolak / Minta Revisi
                                </button>
                                <p class="text-[10px] text-slate-400 text-center leading-tight mt-2 italic">
                                    *Jika ditolak, User bisa upload bukti baru & edit atlet.
                                </p>
                            <?php elseif($statusPay == 'Paid'): ?>
                                <button onclick="openModal('reject')" class="w-full bg-slate-100 hover:bg-red-100 text-slate-500 hover:text-red-600 border border-slate-200 py-2 rounded-lg text-[10px] font-bold uppercase">
                                    🔓 Buka Kunci (Set Status Revisi)
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center py-8 border border-dashed border-slate-300 rounded-xl bg-slate-50">
                        <p class="text-[10px] font-bold text-slate-400">User belum upload bukti bayar</p>
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
                    <h3 class="text-sm font-black text-slate-800 uppercase italic">Daftar Atlet & Nomor Lomba</h3>
                    <span class="bg-slate-900 text-white px-3 py-1 rounded-lg text-[10px] font-black uppercase">Total: <?= count($entries) ?> Nomor</span>
                </div>
                
                <?php if(empty($entries)): ?>
                    <div class="py-20 text-center opacity-50 flex flex-col items-center">
                        <span class="text-4xl mb-2">🤷‍♂️</span>
                        <p class="font-bold text-slate-400 text-sm">Belum ada atlet yang didaftarkan user ini.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 text-slate-400 border-b border-slate-100">
                                <tr>
                                    <th class="py-3 px-5 text-[9px] font-black uppercase tracking-widest">Atlet</th>
                                    <th class="py-3 px-5 text-[9px] font-black uppercase tracking-widest">Nomor Lomba</th>
                                    <th class="py-3 px-5 text-[9px] font-black uppercase tracking-widest text-center">Kelompok Umur</th>
                                    <th class="py-3 px-5 text-[9px] font-black uppercase tracking-widest text-right">Waktu (Entry)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-600">
                                <?php foreach($entries as $ent): 
                                    $jk = $ent['swimmer_gender'] ?? 'L'; 
                                    $bgIcon = ($jk == 'L') ? 'bg-blue-100 text-blue-600' : 'bg-pink-100 text-pink-600';
                                    $genderLabel = ($jk == 'L') ? 'L' : 'P';
                                ?>
                                <tr class="hover:bg-slate-50 transition group">
                                    <td class="py-3 px-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full <?= $bgIcon ?> flex items-center justify-center text-xs font-black shrink-0">
                                                <?= $genderLabel ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-xs uppercase text-slate-800"><?= htmlspecialchars($ent['nama_atlet']) ?></div>
                                                <div class="text-[9px] text-slate-400 font-bold">Lahir: <?= $ent['tanggal_lahir'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="py-3 px-5">
                                        <div class="flex flex-col">
                                            <span class="text-[11px] font-bold text-slate-700 uppercase italic">
                                                <?= $ent['distance'] ?>M <?= strtoupper($ent['stroke']) ?>
                                            </span>
                                            <span class="text-[9px] font-bold text-slate-400">
                                                Kategori: <?= $ent['event_gender'] == 'L' ? 'Putra' : 'Putri' ?>
                                            </span>
                                        </div>
                                    </td>
                                    
                                    <td class="py-3 px-5 text-center">
                                        <span class="inline-block px-3 py-1 bg-slate-100 rounded text-[9px] font-bold uppercase text-slate-500">
                                            KU <?= htmlspecialchars($ent['age_group']) ?>
                                        </span>
                                    </td>

                                    <td class="py-3 px-5 font-mono text-xs font-bold text-right text-blue-600">
                                        <?= htmlspecialchars($ent['entry_time'] ?? 'NT') ?>
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
        msg = 'KONFIRMASI TERIMA:\n\n1. Status user menjadi LUNAS (Hijau).\n2. Data user akan TERKUNCI.';
    } else {
        msg = 'KONFIRMASI TOLAK:\n\n1. Status user menjadi REVISI (Merah).\n2. User akan diminta upload bukti baru.\n3. User bisa mengubah data atlet lagi.';
    }

    if(confirm(msg)) {
        document.getElementById('modalActionInput').value = action;
        document.getElementById('actionForm').submit();
    }
}
</script>