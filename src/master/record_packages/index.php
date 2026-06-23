<?php
// FILE: src/master/record_packages/index.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Proteksi akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); exit;
}

// Hapus paket
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $pdo->prepare("DELETE FROM record_packages WHERE id = ?")->execute([$delId]);
    $_SESSION['flash_message'] = "Paket rekor berhasil dihapus!";
    $_SESSION['flash_type'] = "success";
    header("Location: index.php"); exit;
}

// Ambil daftar paket dan hitung jumlah rekor di dalamnya
$sql = "SELECT rp.*, COUNT(ehr.id) as total_records 
        FROM record_packages rp 
        LEFT JOIN event_historical_records ehr ON ehr.package_id = rp.id 
        GROUP BY rp.id 
        ORDER BY rp.id DESC";
$packages = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    <div class="max-w-7xl mx-auto px-4 py-4">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter">MANAJEMEN REKOR</h1>
                <p class="text-slate-500 text-sm mt-1">Kelola basis data Rekor Nasional & Paket Rekor Acuan Event.</p>
            </div>
            <div>
                <a href="create.php" class="px-5 py-3 bg-blue-600 text-white font-bold text-xs rounded-xl uppercase tracking-wider shadow-lg shadow-blue-100 hover:bg-blue-700 transition flex items-center gap-2">
                    ➕ Buat Paket Baru
                </a>
            </div>
        </div>

        <!-- NAVIGATION TABS -->
        <div class="flex border-b border-slate-200 mb-6 bg-white rounded-xl p-1.5 shadow-sm">
            <a href="../manage_records.php" class="flex-1 text-center py-3 font-bold text-sm rounded-lg transition text-slate-500 hover:text-slate-900">
                🇮🇩 DATA REKOR NASIONAL
            </a>
            <a href="index.php" class="flex-1 text-center py-3 font-bold text-sm rounded-lg transition bg-slate-900 text-white shadow">
                📦 PAKET REKOR ACUAN EVENT
            </a>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50">
                <h2 class="font-bold text-slate-900 text-base uppercase">Daftar Paket (<?= count($packages) ?> Data)</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-slate-600 font-bold text-xs tracking-wider uppercase border-b border-slate-200">
                            <th class="p-4 w-16 text-center">ID</th>
                            <th class="p-4">Nama Paket Rekor</th>
                            <th class="p-4 text-center">Total Rekor</th>
                            <th class="p-4">Tgl Dibuat</th>
                            <th class="p-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm font-medium">
                        <?php if(empty($packages)): ?>
                            <tr><td colspan="5" class="p-12 text-center text-slate-400 font-medium italic">Belum ada paket rekor.</td></tr>
                        <?php else: foreach($packages as $p): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-4 text-center text-slate-400">#<?= $p['id'] ?></td>
                                <td class="p-4 font-black text-blue-600 uppercase"><?= htmlspecialchars($p['package_name']) ?></td>
                                <td class="p-4 text-center"><span class="bg-slate-100 text-slate-700 px-3 py-1 rounded-lg font-bold"><?= $p['total_records'] ?> Rekor</span></td>
                                <td class="p-4 text-slate-500 text-xs"><?= date('d M Y, H:i', strtotime($p['created_at'])) ?></td>
                                <td class="p-4 text-center flex justify-center gap-2">
                                    <a href="detail.php?id=<?= $p['id'] ?>" class="px-4 py-2 bg-slate-900 text-white hover:bg-slate-800 rounded-lg text-xs font-bold transition">Lihat Detail</a>
                                    <a href="?delete_id=<?= $p['id'] ?>" onclick="return confirm('Hapus paket ini beserta isinya?')" class="px-4 py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg text-xs font-bold transition">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
