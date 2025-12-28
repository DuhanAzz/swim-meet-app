<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id']; // ID Admin / Event Organizer yang sedang login

// ==========================================
// 1. HANDLE QUICK ACTION (Validasi Cepat)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_payment_id'])) {
        try {
            // Update status di tabel PAYMENTS
            // Tambahan keamanan: Pastikan payment ini milik event si Admin (AND event_id = $uid)
            $stmt = $pdo->prepare("UPDATE payments SET status = 'Paid', updated_at = NOW() WHERE id = ? AND event_id = ?");
            $stmt->execute([$_POST['approve_payment_id'], $uid]);
            
            $_SESSION['swal_type'] = 'success'; 
            $_SESSION['swal_msg'] = 'Pembayaran Berhasil Diterima (Lunas)!';
        } catch (Exception $e) {
            $_SESSION['swal_type'] = 'error'; 
            $_SESSION['swal_msg'] = 'Error: ' . $e->getMessage();
        }
        header("Location: index.php"); exit;
    }
    
    if (isset($_POST['reject_payment_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE payments SET status = 'Rejected', updated_at = NOW() WHERE id = ? AND event_id = ?");
            $stmt->execute([$_POST['reject_payment_id'], $uid]);
            
            $_SESSION['swal_type'] = 'warning'; 
            $_SESSION['swal_msg'] = 'Pembayaran Ditolak. User harus upload ulang.';
        } catch (Exception $e) {}
        header("Location: index.php"); exit;
    }
}

// ==========================================
// 2. AMBIL DATA (HANYA MILIK EVENT INI)
// ==========================================
try {
    // FIX DATA BOCOR: Tambahkan WHERE p.event_id = ?
    // Kita asumsikan p.event_id di tabel payments merujuk pada ID Admin penyelenggara ($uid)
    
    $sql = "SELECT 
                p.id as payment_id,
                p.status as payment_status,
                p.file_path,
                p.amount,
                p.event_id,
                u.id as user_id,
                u.nama_lengkap,
                u.email,
                -- e.nama_event, -- (Opsional: Jika tabel events tidak sinkron, bisa ambil nama dari users/profil admin)
                (SELECT COUNT(*) FROM event_entries WHERE user_id = u.id AND event_id = p.event_id) as total_entries
            FROM payments p
            JOIN users u ON p.user_id = u.id -- Ini User Peserta (Club)
            -- JOIN events e ON p.event_id = e.id -- (Dinonaktifkan sementara jika bikin error, aktifkan jika perlu nama event spesifik)
            WHERE p.event_id = ?  -- <--- INI KUNCI PERBAIKANNYA
            ORDER BY 
                CASE WHEN p.status = 'Pending' THEN 1 ELSE 2 END, 
                p.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid]); // Filter sesuai Admin yang login
    $listData = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $listData = [];
    // Tampilkan error jika perlu debugging
    // echo $e->getMessage(); 
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="max-w-[95%] mx-auto mb-8 flex flex-col md:flex-row justify-between items-end gap-4">
        <div>
            <h1 class="text-4xl font-black uppercase tracking-tighter italic text-slate-900 leading-none">Verifikasi Entries</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Dashboard Pembayaran & Validasi</p>
        </div>
        
        <div class="flex gap-4">
            <div class="px-6 py-3 bg-white rounded-xl shadow-sm border border-slate-200 text-right">
                <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-widest">Total Transaksi</span>
                <span class="block text-xl font-black text-slate-800"><?= count($listData) ?></span>
            </div>
        </div>
    </div>

    <?php if(isset($_SESSION['swal_msg'])): ?>
        <div class="max-w-[95%] mx-auto mb-6 px-6 py-4 rounded-xl shadow-lg font-bold text-white flex items-center gap-3 <?= $_SESSION['swal_type']=='success' ? 'bg-emerald-500' : 'bg-amber-500' ?>">
            <span><?= $_SESSION['swal_type']=='success' ? '✅' : '⚠️' ?></span>
            <?= $_SESSION['swal_msg']; unset($_SESSION['swal_msg']); unset($_SESSION['swal_type']); ?>
        </div>
    <?php endif; ?>

    <div class="max-w-[95%] mx-auto bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden min-h-[500px]">
        
        <?php if(empty($listData)): ?>
            <div class="flex flex-col items-center justify-center py-32 text-center opacity-50">
                <div class="text-6xl mb-4 grayscale">📭</div>
                <h3 class="font-black text-slate-400 uppercase tracking-widest text-xl">Belum Ada Transaksi</h3>
                <p class="text-xs text-slate-400 mt-2">Data transaksi untuk event ini belum tersedia.</p>
            </div>
        <?php else: ?>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="py-6 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest w-12">#</th>
                            <th class="py-6 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest w-1/4">Klub Pengirim</th>
                            <th class="py-6 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Tagihan</th>
                            <th class="py-6 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Bukti Transfer</th>
                            <th class="py-6 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                            <th class="py-6 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Aksi</th>
                        </tr>
                    </thead>
                    
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($listData as $i => $row): 
                            $status = $row['payment_status'];
                            $entriesCount = $row['total_entries'];
                            $tagihan = $row['amount']; 
                        ?>
                        <tr class="group hover:bg-slate-50/80 transition duration-200 <?= $status == 'Pending' ? 'bg-amber-50/30' : '' ?>">
                            
                            <td class="py-6 px-6 font-black text-slate-300 italic"><?= $i + 1 ?></td>
                            
                            <td class="py-6 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-lg text-slate-400">
                                        🏛️
                                    </div>
                                    <div>
                                        <h4 class="font-black text-slate-800 uppercase italic text-xs group-hover:text-blue-600 transition">
                                            <?= htmlspecialchars($row['nama_lengkap']) ?>
                                        </h4>
                                        <div class="text-[10px] font-bold text-slate-400 mt-0.5">
                                            <?= htmlspecialchars($row['email']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="py-6 px-4">
                                <div class="flex flex-col">
                                    <span class="text-xs font-black text-slate-700">
                                        <?= $entriesCount ?> Nomor Lomba
                                    </span>
                                    <span class="text-[10px] font-bold text-slate-400">
                                        Total: <span class="text-emerald-600">Rp <?= number_format($tagihan, 0, ',', '.') ?></span>
                                    </span>
                                </div>
                            </td>

                            <td class="py-6 px-4">
                                <?php if(!empty($row['file_path'])): ?>
                                    <a href="../../../public/uploads/payments/<?= htmlspecialchars($row['file_path']) ?>" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white border border-slate-200 text-slate-600 hover:border-blue-300 hover:text-blue-600 transition group/btn shadow-sm">
                                        <span class="text-lg">🧾</span>
                                        <div class="flex flex-col text-left">
                                            <span class="text-[9px] font-bold uppercase tracking-wider">Lihat Bukti</span>
                                        </div>
                                    </a>
                                <?php else: ?>
                                    <span class="text-[10px] font-bold text-slate-400 border border-dashed border-slate-300 px-2 py-1 rounded">No File</span>
                                <?php endif; ?>
                            </td>

                            <td class="py-6 px-4 text-center">
                                <?php 
                                $badgeClass = match($status) {
                                    'Paid' => 'bg-emerald-100 text-emerald-600 border-emerald-200',
                                    'Pending' => 'bg-amber-100 text-amber-600 border-amber-200 animate-pulse',
                                    'Rejected' => 'bg-red-100 text-red-600 border-red-200',
                                    default => 'bg-slate-100 text-slate-500 border-slate-200'
                                };
                                ?>
                                <span class="inline-block px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border <?= $badgeClass ?>">
                                    <?= $status ?>
                                </span>
                            </td>

                            <td class="py-6 px-6 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    
                                    <a href="detail_club.php?id=<?= $row['user_id'] ?>&event_id=<?= $row['event_id'] ?>" class="px-3 py-2 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-blue-600 text-[10px] font-black uppercase transition shadow-sm" title="Lihat Detail Lengkap">
                                        👁️ Detail
                                    </a>

                                    <?php if($status == 'Pending'): ?>
                                        <form method="POST" onsubmit="return confirm('Verifikasi pembayaran ini sebagai LUNAS?');">
                                            <input type="hidden" name="approve_payment_id" value="<?= $row['payment_id'] ?>">
                                            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 text-[10px] font-black uppercase tracking-wider transition shadow-md shadow-emerald-200">
                                                ✓ Terima
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if($status == 'Paid'): ?>
                                        <form method="POST" onsubmit="return confirm('Batalkan status lunas? User harus upload ulang.');">
                                            <input type="hidden" name="reject_payment_id" value="<?= $row['payment_id'] ?>">
                                            <button type="submit" class="px-3 py-2 rounded-lg border border-red-100 text-red-400 hover:bg-red-50 hover:text-red-600 text-[10px] font-bold uppercase transition" title="Batalkan Validasi">
                                                ✕ Batal
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