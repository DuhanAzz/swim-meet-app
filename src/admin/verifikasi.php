<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') die("Akses Ditolak.");
$user_id = $_SESSION['user_id'];

// HANDLE VALIDASI
if (isset($_GET['verify_id'])) {
    $swimmer_id = $_GET['verify_id'];
    $pdo->prepare("UPDATE swimmers SET status_verifikasi = 'verified' WHERE id = ?")->execute([$swimmer_id]);
    $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Atlet Terverifikasi!';
    header("Location: verifikasi.php"); exit;
}

// AMBIL DATA PENDAFTAR
// (Hanya atlet yang masuk di event milik admin ini)
$sql = "SELECT DISTINCT s.id, s.nama_atlet, s.jenis_kelamin, c.nama_klub, s.status_verifikasi 
        FROM swimmers s
        JOIN clubs c ON s.club_id = c.id
        JOIN heat_entries he ON s.id = he.swimmer_id
        JOIN heats h ON he.heat_id = h.id
        JOIN events e ON h.event_id = e.id
        WHERE e.user_id = ?
        ORDER BY c.nama_klub ASC, s.nama_atlet ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$swimmers = $stmt->fetchAll();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 mt-16 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800 tracking-tight uppercase">Verifikasi Pendaftaran</h1>
            <p class="text-sm text-slate-500">Validasi atlet sesuai berkas & Generate Buku Acara.</p>
        </div>
        
        <a href="preview_buku_acara.php" target="_blank" class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg hover:bg-indigo-700 transition flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
            Generate Buku Acara
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b text-slate-500 uppercase font-bold text-xs">
                    <tr>
                        <th class="px-6 py-4">Nama Atlet</th>
                        <th class="px-6 py-4">Klub</th>
                        <th class="px-6 py-4 text-center">Gender</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach($swimmers as $s): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 font-bold text-slate-700"><?= htmlspecialchars($s['nama_atlet']) ?></td>
                        <td class="px-6 py-4"><?= htmlspecialchars($s['nama_klub']) ?></td>
                        <td class="px-6 py-4 text-center"><?= $s['jenis_kelamin'] ?></td>
                        <td class="px-6 py-4 text-center">
                            <?php if($s['status_verifikasi'] == 'verified'): ?>
                                <span class="bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs font-bold">Terverifikasi</span>
                            <?php else: ?>
                                <span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded-full text-xs font-bold">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <?php if($s['status_verifikasi'] != 'verified'): ?>
                                <a href="?verify_id=<?= $s['id'] ?>" class="text-blue-600 font-bold hover:underline">✅ Validasi</a>
                            <?php else: ?>
                                <span class="text-slate-300">Done</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
