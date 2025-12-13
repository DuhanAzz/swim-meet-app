<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

// --- HANDLE VERIFIKASI ---
if (isset($_POST['action_id'])) {
    $payId  = $_POST['action_id'];
    $status = $_POST['status']; // 'Verified' atau 'Rejected'
    
    $pdo->prepare("UPDATE event_payments SET status = ? WHERE id = ?")->execute([$status, $payId]);
    
    $_SESSION['toast_type'] = ($status == 'Verified') ? 'success' : 'error'; 
    $_SESSION['toast_message'] = 'Status pembayaran diperbarui: ' . $status;
    
    header("Location: index.php"); exit;
}

// --- AMBIL DATA PEMBAYARAN MASUK ---
// PERBAIKAN SQL DI SINI:
// Mengganti 'u.nama_klub' menjadi 'u.nama_lengkap as nama_klub'
$sql = "SELECT ep.*, u.nama_lengkap as nama_klub
        FROM event_payments ep
        JOIN users u ON ep.club_id = u.id
        WHERE ep.event_id = ?
        ORDER BY ep.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$uid]);
$payments = $stmt->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Keuangan & Verifikasi</h1>
            <p class="text-sm text-slate-500">Validasi pembayaran masuk dari klub peserta.</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <?php if(empty($payments)): ?>
            <div class="p-12 text-center text-slate-400">
                <span class="text-5xl block mb-4 grayscale opacity-30">💰</span>
                <h3 class="text-lg font-bold text-slate-700">Belum ada pembayaran masuk.</h3>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-xs border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4">Klub / Pengirim</th>
                            <th class="px-6 py-4">Jumlah Transfer</th>
                            <th class="px-6 py-4">Bukti</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($payments as $p): ?>
                        <tr class="hover:bg-blue-50 transition">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800"><?= htmlspecialchars($p['nama_klub']) ?></div>
                                <div class="text-xs text-slate-500 mt-1"><?= date('d M Y H:i', strtotime($p['created_at'])) ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-mono text-slate-700 font-bold">
                                    Rp <?= number_format($p['total_amount'], 0, ',', '.') ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <a href="../../../public/<?= $p['proof_file'] ?>" target="_blank" class="text-blue-600 font-bold text-xs hover:underline flex items-center gap-1">
                                    📄 Lihat Foto
                                </a>
                            </td>
                            <td class="px-6 py-4">
                                <?php if($p['status'] == 'Pending'): ?>
                                    <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-bold uppercase animate-pulse">Pending</span>
                                <?php elseif($p['status'] == 'Verified'): ?>
                                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold uppercase">Lunas ✅</span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-bold uppercase">Ditolak</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if($p['status'] == 'Pending'): ?>
                                    <div class="flex justify-end gap-2">
                                        <form method="POST">
                                            <input type="hidden" name="action_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="status" value="Verified">
                                            <button class="bg-green-600 text-white px-3 py-1.5 rounded text-xs font-bold hover:bg-green-700 shadow transition">✔ Terima</button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Tolak pembayaran ini?')">
                                            <input type="hidden" name="action_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="status" value="Rejected">
                                            <button class="bg-red-100 text-red-600 px-3 py-1.5 rounded text-xs font-bold hover:bg-red-200 transition">✕ Tolak</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-slate-400 text-xs font-bold">Selesai</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>