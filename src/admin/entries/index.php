<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

// ==========================================
// KONFIGURASI BIAYA (Bisa disesuaikan)
// ==========================================
$biaya_per_nomor = 50000; // Contoh: Rp 50.000 per nomor lomba

// 1. HANDLE VALIDASI AKUN (Verifikasi Pembayaran & Akun Sekaligus)
if (isset($_POST['validate_club_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET account_status = 'verified' WHERE id = ?");
        $stmt->execute([$_POST['validate_club_id']]);
        $_SESSION['swal_type'] = 'success'; 
        $_SESSION['swal_msg'] = 'Pembayaran Diterima & Akun Diverifikasi!';
    } catch (Exception $e) {
        $_SESSION['swal_type'] = 'error'; 
        $_SESSION['swal_msg'] = 'Gagal memvalidasi: ' . $e->getMessage();
    }
    header("Location: index.php"); exit;
}

// 2. HANDLE BATAL VALIDASI (Jika salah klik)
if (isset($_POST['unverify_club_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET account_status = 'pending' WHERE id = ?");
        $stmt->execute([$_POST['unverify_club_id']]);
        $_SESSION['swal_type'] = 'warning'; 
        $_SESSION['swal_msg'] = 'Status Akun dikembalikan ke Pending.';
    } catch (Exception $e) {
        // Silent error
    }
    header("Location: index.php"); exit;
}

// 3. AMBIL DATA GABUNGAN (User + Jumlah Entry + Status Bayar)
try {
    // Kita hitung juga jumlah event yang diikuti untuk estimasi tagihan
    $sql = "SELECT DISTINCT u.*,
            (SELECT COUNT(*) FROM event_entries WHERE user_id = u.id) as total_entries
            FROM users u
            LEFT JOIN event_entries e ON u.id = e.user_id
            WHERE u.role = 'user' 
            AND (u.payment_proof IS NOT NULL OR e.id IS NOT NULL)
            ORDER BY u.created_at DESC";
    
    $clubs = $pdo->query($sql)->fetchAll();

} catch (PDOException $e) {
    $clubs = [];
    $error_msg = $e->getMessage();
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="max-w-[95%] mx-auto mb-8 flex flex-col md:flex-row justify-between items-end gap-4">
        <div>
            <h1 class="text-4xl font-black uppercase tracking-tighter italic text-slate-900 leading-none">Verifikasi & Entries</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Validasi Pembayaran & Data Klub</p>
        </div>
        
        <div class="flex gap-4">
            <div class="px-6 py-3 bg-white rounded-xl shadow-sm border border-slate-200 text-right">
                <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-widest">Total Pendaftar</span>
                <span class="block text-xl font-black text-slate-800"><?= count($clubs) ?> Klub</span>
            </div>
        </div>
    </div>

    <div class="max-w-[95%] mx-auto bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden">
        
        <?php if(empty($clubs)): ?>
            <div class="p-20 text-center">
                <div class="text-5xl mb-4 grayscale opacity-30">📭</div>
                <h3 class="font-black text-slate-400 uppercase tracking-widest text-lg">Belum Ada Data Masuk</h3>
            </div>
        <?php else: ?>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="py-6 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest w-12">#</th>
                            <th class="py-6 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest w-1/4">Identitas Klub</th>
                            <th class="py-6 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Statistik & Tagihan</th>
                            <th class="py-6 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Bukti Transfer</th>
                            <th class="py-6 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                            <th class="py-6 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Aksi</th>
                        </tr>
                    </thead>
                    
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($clubs as $i => $club): 
                            $isVerified = ($club['account_status'] ?? 'pending') == 'verified';
                            $hasPayment = !empty($club['payment_proof']);
                            $totalEntries = $club['total_entries'];
                            $estimasiTagihan = $totalEntries * $biaya_per_nomor;
                        ?>
                        <tr class="group hover:bg-slate-50/80 transition duration-200">
                            
                            <td class="py-6 px-6 font-black text-slate-300 italic"><?= $i + 1 ?></td>
                            
                            <td class="py-6 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-lg text-slate-400">
                                        🏛️
                                    </div>
                                    <div>
                                        <h4 class="font-black text-slate-800 uppercase italic text-xs group-hover:text-blue-600 transition">
                                            <?= htmlspecialchars($club['nama_lengkap'] ?? 'Tanpa Nama') ?>
                                        </h4>
                                        <span class="text-[10px] font-bold text-slate-400 block">
                                            <?= htmlspecialchars($club['email']) ?>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <td class="py-6 px-4">
                                <div class="flex flex-col">
                                    <span class="text-xs font-black text-slate-700">
                                        <?= $totalEntries ?> Nomor Lomba
                                    </span>
                                    <span class="text-[10px] font-bold text-slate-400">
                                        Est. Biaya: <span class="text-emerald-600">Rp <?= number_format($estimasiTagihan, 0, ',', '.') ?></span>
                                    </span>
                                </div>
                            </td>

                            <td class="py-6 px-4">
                                <?php if($hasPayment): ?>
                                    <a href="../../../public/<?= htmlspecialchars($club['payment_proof']) ?>" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition group/btn border border-blue-100">
                                        <span class="text-lg">🧾</span>
                                        <div class="flex flex-col text-left">
                                            <span class="text-[9px] font-bold uppercase tracking-wider">Cek Bukti</span>
                                            <span class="text-[8px] opacity-70">Klik untuk melihat</span>
                                        </div>
                                    </a>
                                <?php else: ?>
                                    <span class="text-[10px] font-bold text-red-400 bg-red-50 px-2 py-1 rounded">Belum Upload</span>
                                <?php endif; ?>
                            </td>

                            <td class="py-6 px-4 text-center">
                                <?php if($isVerified): ?>
                                    <span class="inline-block px-3 py-1 rounded-full bg-emerald-100 text-emerald-600 text-[9px] font-black uppercase tracking-widest border border-emerald-200">
                                        ✅ Lunas / Verified
                                    </span>
                                <?php else: ?>
                                    <span class="inline-block px-3 py-1 rounded-full bg-amber-100 text-amber-600 text-[9px] font-black uppercase tracking-widest border border-amber-200 animate-pulse">
                                        ⏳ Menunggu Validasi
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="py-6 px-6 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    
                                    <a href="detail_club.php?id=<?= $club['id'] ?>" class="px-3 py-2 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-blue-600 text-[10px] font-black uppercase transition" title="Lihat Detail Atlet">
                                        👁️ Detail
                                    </a>

                                    <?php if(!$isVerified): ?>
                                        <form method="POST" onsubmit="return confirm('Apakah bukti pembayaran sudah valid? Klik OK untuk memverifikasi akun ini.');">
                                            <input type="hidden" name="validate_club_id" value="<?= $club['id'] ?>">
                                            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 text-[10px] font-black uppercase tracking-wider transition shadow-md shadow-emerald-200">
                                                ✓ Terima & Validasi
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" onsubmit="return confirm('Batalkan validasi akun ini?');">
                                            <input type="hidden" name="unverify_club_id" value="<?= $club['id'] ?>">
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