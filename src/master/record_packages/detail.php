<?php
// FILE: src/master/record_packages/detail.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: index.php"); exit;
}

// Data Paket
$stmtPkg = $pdo->prepare("SELECT * FROM record_packages WHERE id = ?");
$stmtPkg->execute([$id]);
$package = $stmtPkg->fetch(PDO::FETCH_ASSOC);

if (!$package) {
    header("Location: index.php"); exit;
}

// Data Detail Rekor Historis
$sqlRec = "
    SELECT ehr.*, e.event_name, YEAR(e.event_date_start) as event_year 
    FROM event_historical_records ehr 
    LEFT JOIN events e ON ehr.source_event_id = e.id 
    WHERE ehr.package_id = ? 
    ORDER BY ehr.distance ASC, ehr.stroke ASC, ehr.jenis_kelamin ASC, ehr.age_group ASC
";
$stmtRec = $pdo->prepare($sqlRec);
$stmtRec->execute([$id]);
$records = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    <div class="max-w-7xl mx-auto px-4 py-4">
        
        <div class="mb-8">
            <a href="index.php" class="text-blue-600 hover:underline font-bold text-sm mb-2 inline-block">← Kembali ke Daftar Paket</a>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter">DETAIL PAKET REKOR</h1>
            <p class="text-slate-500 text-sm mt-1">Paket: <strong class="text-slate-800"><?= htmlspecialchars($package['package_name']) ?></strong></p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h2 class="font-bold text-slate-900 text-base uppercase">Isi Rekor (<?= count($records) ?> Data)</h2>
                <a href="?delete_id=<?= $id ?>" class="text-xs text-red-600 hover:underline font-bold">Hapus Paket Ini</a>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-slate-600 font-bold text-xs tracking-wider uppercase border-b border-slate-200">
                            <th class="p-4">Nomor Acara</th>
                            <th class="p-4">Jenis Kelamin</th>
                            <th class="p-4">Kelompok Umur</th>
                            <th class="p-4">Pemegang Rekor</th>
                            <th class="p-4 text-center">Waktu</th>
                            <th class="p-4">Sumber Event</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm font-medium">
                        <?php if(empty($records)): ?>
                            <tr><td colspan="6" class="p-12 text-center text-slate-400 font-medium italic">Tidak ada data rekor di paket ini.</td></tr>
                        <?php else: foreach($records as $r): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-4 font-bold text-slate-900"><?= $r['distance'] ?>M <?= htmlspecialchars($r['stroke']) ?></td>
                                <td class="p-4"><span class="px-2 py-1 text-[10px] font-bold rounded-md <?= ($r['jenis_kelamin']=='L' || $r['jenis_kelamin']=='M')?'bg-sky-100 text-sky-700':'bg-rose-100 text-rose-700' ?>"><?= ($r['jenis_kelamin']=='L' || $r['jenis_kelamin']=='M')?'PUTRA':(($r['jenis_kelamin']=='P' || $r['jenis_kelamin']=='F')?'PUTRI':$r['jenis_kelamin']) ?></span></td>
                                <td class="p-4 text-slate-700 font-semibold"><?= htmlspecialchars($r['age_group']) ?></td>
                                <td class="p-4 font-bold text-slate-900 uppercase"><?= htmlspecialchars($r['holder_name']) ?></td>
                                <td class="p-4 text-center font-mono font-black text-emerald-600 text-base"><?= htmlspecialchars($r['record_time']) ?></td>
                                <td class="p-4">
                                    <div class="text-xs font-bold text-blue-700 uppercase"><?= htmlspecialchars($r['event_name'] ?: $package['package_name']) ?></div>
                                    <div class="text-[10px] text-slate-500">Tahun: <?= $r['event_year'] ?: date('Y') ?></div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
