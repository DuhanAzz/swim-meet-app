<?php
// FILE: src/master/finance/revenue.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// CEK AKSES
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); exit;
}

// --- 1. HANDLE VERIFIKASI PEMBAYARAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_verify'])) {
    try {
        $payId   = $_POST['payment_id'];
        $status  = $_POST['status']; // 'Paid' or 'Rejected'
        
        $pdo->prepare("UPDATE payments SET status = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$status, $payId]);
            
        $_SESSION['msg'] = "Status pembayaran berhasil diubah menjadi $status.";
        $_SESSION['msg_type'] = "success";
    } catch (Exception $e) {
        $_SESSION['msg'] = "Gagal update: " . $e->getMessage();
        $_SESSION['msg_type'] = "error";
    }
    header("Location: revenue.php"); exit;
}

// --- 2. QUERY DATA STATISTIK ---

// Total Pendapatan (Paid)
$totalRevenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'Paid'")->fetchColumn() ?: 0;

// Total Menunggu Verifikasi
$pendingCount = $pdo->query("SELECT COUNT(*) FROM payments WHERE status NOT IN ('Paid', 'Rejected')")->fetchColumn() ?: 0;

// Top Event (Event dengan uang masuk terbanyak)
$topEvent = $pdo->query("
    SELECT e.event_name, SUM(p.amount) as total 
    FROM payments p 
    JOIN events e ON p.event_id = e.id 
    WHERE p.status = 'Paid' 
    GROUP BY p.event_id 
    ORDER BY total DESC LIMIT 1
")->fetch();

// --- QUERY TAMBAHAN: DATA UNTUK POPUP (SEMUA EVENT) ---
$allEventsRevenue = $pdo->query("
    SELECT e.event_name, SUM(p.amount) as total, COUNT(p.id) as trx_count
    FROM payments p 
    JOIN events e ON p.event_id = e.id 
    WHERE p.status = 'Paid' 
    GROUP BY p.event_id 
    ORDER BY total DESC
")->fetchAll();

// --- 3. QUERY DATA TABEL TRANSAKSI UTAMA ---
$sql = "SELECT p.*, u.nama_lengkap as club_name, e.event_name 
        FROM payments p
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN events e ON p.event_id = e.id
        ORDER BY p.created_at DESC LIMIT 50";
$payments = $pdo->query($sql)->fetchAll();

include __DIR__ . '/../../../views/layout/sidebar.php';
include __DIR__ . '/../../../views/layout/topbar.php';
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans relative">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter">
                Financial Center
            </h1>
            <p class="text-sm text-slate-500 font-medium">Monitoring arus kas dari tabel <b>Payments</b>.</p>
        </div>
        <button onclick="window.print()" class="bg-slate-800 text-white px-5 py-2 rounded-xl font-bold text-xs uppercase hover:bg-slate-900 transition flex items-center gap-2">
            <span>🖨️</span> Cetak Laporan
        </button>
    </div>

    <?php if(isset($_SESSION['msg'])): ?>
        <div class="p-4 mb-6 rounded-xl text-sm font-bold border flex items-center gap-3 shadow-sm 
            <?= $_SESSION['msg_type'] == 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
            <?= htmlspecialchars($_SESSION['msg']) ?>
        </div>
        <?php unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-gradient-to-br from-emerald-500 to-teal-700 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden">
            <div class="relative z-10">
                <div class="text-[10px] font-black uppercase tracking-widest opacity-80 mb-1">Total Pendapatan Bersih</div>
                <div class="text-3xl font-black">Rp <?= number_format($totalRevenue, 0, ',', '.') ?></div>
                <div class="mt-4 text-xs font-medium bg-white/20 inline-block px-2 py-1 rounded">Status: Paid</div>
            </div>
            <div class="absolute right-0 bottom-0 opacity-10 transform translate-x-4 translate-y-4">
                <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.15-1.46-3.27-3.4h1.96c.1 1.05 1.18 1.91 2.53 1.91 1.29 0 2.13-.81 2.13-1.88 0-1.1-.68-1.57-1.75-2.25-1.55-.98-2.69-1.66-2.69-3.5 0-1.81 1.4-2.97 3.09-3.32V4h2.67v1.93c1.71.36 3.15 1.46 3.27 3.4h-1.96c-.1-1.05-1.18-1.91-2.53-1.91-1.29 0-2.13.81-2.13 1.88 0 1.1.68 1.57 1.75 2.25 1.55.98 2.69 1.66 2.69 3.5 0 1.81-1.4 2.97-3.09 3.32z"/></svg>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div>
                <div class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-1">Perlu Verifikasi</div>
                <div class="text-3xl font-black text-orange-500"><?= $pendingCount ?> <span class="text-sm text-slate-400">Transaksi</span></div>
            </div>
            <div class="text-xs text-slate-500 mt-2">Menunggu konfirmasi admin.</div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between relative group">
            <div>
                <div class="flex justify-between items-start">
                    <div class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-1">Event Paling Cuan</div>
                    <span class="text-xl">🏆</span>
                </div>
                <div class="text-lg font-bold text-slate-800 leading-tight mb-1 truncate">
                    <?= $topEvent ? htmlspecialchars($topEvent['event_name']) : '-' ?>
                </div>
                <div class="text-xl font-black text-blue-600">
                    Rp <?= $topEvent ? number_format($topEvent['total'], 0, ',', '.') : '0' ?>
                </div>
            </div>
            
            <button onclick="toggleModal('revenueModal')" class="mt-3 w-full bg-slate-100 hover:bg-blue-50 text-slate-600 hover:text-blue-600 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest transition flex justify-center items-center gap-2">
                <span>📊</span> Lihat Semua Event
            </button>
        </div>
    </div>

    <div class="bg-white rounded-[2rem] shadow-xl border border-slate-200 overflow-hidden">
        <div class="bg-slate-800 px-8 py-5 border-b border-slate-700">
            <h3 class="text-white font-black text-sm uppercase tracking-wider">
                📥 Transaksi Masuk (Real Data)
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 font-bold border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4">ID / Tanggal</th>
                        <th class="px-6 py-4">Klub (Pengirim)</th>
                        <th class="px-6 py-4">Untuk Event</th>
                        <th class="px-6 py-4">Bukti TF</th>
                        <th class="px-6 py-4 text-right">Nominal</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($payments)): ?>
                        <tr><td colspan="7" class="p-8 text-center text-slate-400 italic">Belum ada data di tabel payments.</td></tr>
                    <?php else: foreach($payments as $p): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800">#INV-<?= $p['id'] ?></div>
                                <div class="text-xs text-slate-500 font-mono"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-700"><?= htmlspecialchars($p['club_name'] ?? 'Unknown User') ?></div>
                                <div class="text-[10px] text-slate-400">ID: <?= $p['user_id'] ?></div>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-600 font-medium">
                                <?= htmlspecialchars($p['event_name'] ?? 'Unknown Event') ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if(!empty($p['file_path'])): ?>
                                    <a href="../../../public/uploads/payment_proofs/<?= htmlspecialchars($p['file_path']) ?>" target="_blank" class="text-blue-600 underline text-xs font-bold hover:text-blue-800">
                                        Lihat Foto
                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-300 text-[10px] italic">No File</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-slate-700">
                                Rp <?= number_format($p['amount'], 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if($p['status'] == 'Paid'): ?>
                                    <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">Lunas</span>
                                <?php elseif($p['status'] == 'Rejected'): ?>
                                    <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">Ditolak</span>
                                <?php else: ?>
                                    <span class="bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-[10px] font-black uppercase animate-pulse">Menunggu</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if($p['status'] != 'Paid' && $p['status'] != 'Rejected'): ?>
                                    <div class="flex justify-center gap-2">
                                        <form method="POST">
                                            <input type="hidden" name="action_verify" value="1">
                                            <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="status" value="Paid">
                                            <button onclick="return confirm('Verifikasi pembayaran ini valid?')" class="bg-emerald-500 hover:bg-emerald-600 text-white p-2 rounded-lg shadow transition" title="Terima">
                                                ✅
                                            </button>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="action_verify" value="1">
                                            <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="status" value="Rejected">
                                            <button onclick="return confirm('Tolak pembayaran ini?')" class="bg-red-500 hover:bg-red-600 text-white p-2 rounded-lg shadow transition" title="Tolak">
                                                ❌
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-slate-300 text-xs italic">Selesai</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="revenueModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity" onclick="toggleModal('revenueModal')"></div>

    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200">
            
            <div class="bg-slate-800 px-6 py-4 flex justify-between items-center">
                <h3 class="text-white font-black text-sm uppercase tracking-wider flex items-center gap-2">
                    <span>📊</span> Peringkat Pendapatan Event
                </h3>
                <button type="button" class="text-slate-400 hover:text-white" onclick="toggleModal('revenueModal')">
                    <span class="text-2xl">&times;</span>
                </button>
            </div>

            <div class="p-6 max-h-[60vh] overflow-y-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-[10px] text-slate-500 uppercase font-black bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 rounded-l-lg">#</th>
                            <th class="px-4 py-3">Nama Event</th>
                            <th class="px-4 py-3 text-right">Total Transaksi</th>
                            <th class="px-4 py-3 rounded-r-lg text-right">Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php 
                        $rank = 1; 
                        foreach($allEventsRevenue as $ev): 
                            // Styling untuk Rank 1, 2, 3
                            $rowClass = "";
                            $icon = "";
                            if($rank == 1) { $icon = "🥇"; $rowClass="bg-yellow-50/50"; }
                            elseif($rank == 2) { $icon = "🥈"; }
                            elseif($rank == 3) { $icon = "🥉"; }
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td class="px-4 py-3 font-black text-slate-400"><?= $rank++ ?> <?= $icon ?></td>
                            <td class="px-4 py-3 font-bold text-slate-700"><?= htmlspecialchars($ev['event_name']) ?></td>
                            <td class="px-4 py-3 text-right text-xs text-slate-500"><?= $ev['trx_count'] ?> Club</td>
                            <td class="px-4 py-3 text-right font-black text-blue-600">
                                Rp <?= number_format($ev['total'], 0, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($allEventsRevenue)): ?>
                            <tr><td colspan="4" class="p-4 text-center text-slate-400 italic">Belum ada data pendapatan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="bg-slate-50 px-6 py-4 flex justify-end">
                <button type="button" class="bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 font-bold py-2 px-4 rounded-lg text-xs uppercase tracking-wider transition" onclick="toggleModal('revenueModal')">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleModal(modalID) {
        document.getElementById(modalID).classList.toggle("hidden");
    }
</script>