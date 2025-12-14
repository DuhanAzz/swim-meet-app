<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

// --- LOGIKA PENGAMBILAN DATA MEDALI ---
// Query ini sangat cerdas: 
// 1. Dia mencari perenang rank 1, 2, 3.
// 2. Dia memastikan jika ada stage 'Final', maka itu yang diambil.
// 3. Jika tidak ada stage 'Final', dia mengambil dari 'Prelims' (Langsung Final).

$sql = "SELECT 
            u.nama_lengkap as nama_klub,
            SUM(CASE WHEN rl.rank = 1 THEN 1 ELSE 0 END) as emas,
            SUM(CASE WHEN rl.rank = 2 THEN 1 ELSE 0 END) as perak,
            SUM(CASE WHEN rl.rank = 3 THEN 1 ELSE 0 END) as perunggu,
            (SUM(CASE WHEN rl.rank = 1 THEN 1 ELSE 0 END) + 
             SUM(CASE WHEN rl.rank = 2 THEN 1 ELSE 0 END) + 
             SUM(CASE WHEN rl.rank = 3 THEN 1 ELSE 0 END)) as total_medali,
            (SUM(CASE WHEN rl.rank = 1 THEN 5 ELSE 0 END) + 
             SUM(CASE WHEN rl.rank = 2 THEN 3 ELSE 0 END) + 
             SUM(CASE WHEN rl.rank = 3 THEN 1 ELSE 0 END)) as total_poin
        FROM race_lines rl
        JOIN race_heats rh ON rl.heat_id = rh.id
        JOIN event_categories ec ON rh.category_id = ec.id
        JOIN event_entries ee ON (rl.swimmer_id = ee.swimmer_id AND ec.id = ee.category_id)
        JOIN users u ON ee.club_id = u.id
        WHERE ec.user_id = ? 
        AND rl.rank IN (1, 2, 3)
        AND (
            (rh.stage = 'Final') OR 
            (rh.stage = 'Prelims' AND NOT EXISTS (
                SELECT 1 FROM race_heats rh2 WHERE rh2.category_id = rh.category_id AND rh2.stage = 'Final'
            ))
        )
        GROUP BY ee.club_id
        ORDER BY emas DESC, perak DESC, perunggu DESC, total_poin DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$uid]);
$tally = $stmt->fetchAll();

// Hitung total distribusi medali untuk statistik atas
$totalEmas = array_sum(array_column($tally, 'emas'));
$totalPerak = array_sum(array_column($tally, 'perak'));
$totalPerunggu = array_sum(array_column($tally, 'perunggu'));

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6">
        <div>
            <h1 class="text-3xl font-black uppercase tracking-tighter italic text-slate-900">Medal Tally</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest">Klasemen Perolehan Medali & Juara Umum</p>
        </div>
        
        <div class="flex gap-3">
            <div class="bg-white px-6 py-3 rounded-2xl shadow-sm border border-slate-200 flex flex-col items-center">
                <span class="text-[10px] font-black text-slate-400 uppercase mb-1">🥇 Gold</span>
                <span class="font-black text-xl text-yellow-500"><?= $totalEmas ?></span>
            </div>
            <div class="bg-white px-6 py-3 rounded-2xl shadow-sm border border-slate-200 flex flex-col items-center">
                <span class="text-[10px] font-black text-slate-400 uppercase mb-1">🥈 Silver</span>
                <span class="font-black text-xl text-slate-400"><?= $totalPerak ?></span>
            </div>
            <div class="bg-white px-6 py-3 rounded-2xl shadow-sm border border-slate-200 flex flex-col items-center">
                <span class="text-[10px] font-black text-slate-400 uppercase mb-1">🥉 Bronze</span>
                <span class="font-black text-xl text-amber-600"><?= $totalPerunggu ?></span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-[3rem] shadow-2xl shadow-slate-200/50 border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-900 text-white">
                    <tr>
                        <th class="px-8 py-6 text-center w-20 font-black text-[10px] uppercase tracking-widest">Rank</th>
                        <th class="px-8 py-6 font-black text-[10px] uppercase tracking-widest">Club / Kontingen</th>
                        <th class="px-8 py-6 text-center font-black text-[10px] uppercase tracking-widest bg-yellow-500/10 text-yellow-600">🥇 Gold</th>
                        <th class="px-8 py-6 text-center font-black text-[10px] uppercase tracking-widest bg-slate-100 text-slate-500">🥈 Silver</th>
                        <th class="px-8 py-6 text-center font-black text-[10px] uppercase tracking-widest bg-amber-100/50 text-amber-700">🥉 Bronze</th>
                        <th class="px-8 py-6 text-center font-black text-[10px] uppercase tracking-widest">Total</th>
                        <th class="px-8 py-6 text-center font-black text-[10px] uppercase tracking-widest text-blue-600">PTS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($tally)): ?>
                        <tr>
                            <td colspan="7" class="px-8 py-20 text-center">
                                <p class="text-slate-300 font-black uppercase tracking-[0.2em] text-xs">Belum ada hasil yang diinput.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($tally as $idx => $row): 
                            $isTop = ($idx < 3); // Untuk highlight 3 besar
                        ?>
                        <tr class="hover:bg-slate-50 transition <?= $isTop ? 'bg-blue-50/20' : '' ?>">
                            <td class="px-8 py-5 text-center">
                                <?php if($idx == 0): ?>
                                    <span class="w-10 h-10 rounded-full bg-yellow-400 text-white flex items-center justify-center mx-auto shadow-lg shadow-yellow-200 font-black">1</span>
                                <?php elseif($idx == 1): ?>
                                    <span class="w-10 h-10 rounded-full bg-slate-300 text-white flex items-center justify-center mx-auto shadow-lg shadow-slate-200 font-black">2</span>
                                <?php elseif($idx == 2): ?>
                                    <span class="w-10 h-10 rounded-full bg-amber-500 text-white flex items-center justify-center mx-auto shadow-lg shadow-amber-200 font-black">3</span>
                                <?php else: ?>
                                    <span class="font-black text-slate-300 italic"><?= $idx + 1 ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-8 py-5">
                                <div class="font-black text-slate-800 uppercase text-sm"><?= htmlspecialchars($row['nama_klub']) ?></div>
                                <div class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter mt-1 italic">Authorized Club</div>
                            </td>
                            <td class="px-8 py-5 text-center font-black text-lg bg-yellow-500/5 text-yellow-600"><?= $row['emas'] ?></td>
                            <td class="px-8 py-5 text-center font-black text-lg bg-slate-50 text-slate-500"><?= $row['perak'] ?></td>
                            <td class="px-8 py-5 text-center font-black text-lg bg-amber-500/5 text-amber-700"><?= $row['perunggu'] ?></td>
                            <td class="px-8 py-5 text-center font-black text-lg text-slate-800"><?= $row['total_medali'] ?></td>
                            <td class="px-8 py-5 text-center font-black text-lg text-blue-600 border-l border-slate-100"><?= $row['total_poin'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-10 flex justify-end">
        <button onclick="window.print()" class="bg-slate-900 text-white font-black px-10 py-4 rounded-2xl text-[10px] uppercase tracking-widest shadow-xl hover:bg-blue-600 transition flex items-center gap-3">
            <span>🖨️</span> Cetak Rekapitulasi
        </button>
    </div>

    <div class="mt-12 text-center">
        <p class="text-[9px] font-black text-slate-300 uppercase tracking-[0.5em]">Swimming Event Timing System &copy; 2025</p>
    </div>

</div>

<style>
    @media print {
        #logo-sidebar, nav, .bg-slate-900, button { display: none !important; }
        .sm\:ml-64 { margin-left: 0 !important; padding: 0 !important; }
        .pt-24 { padding-top: 0 !important; }
        .rounded-\[3rem\] { border-radius: 0 !important; border: none !important; box-shadow: none !important; }
        table { border: 1px solid #000; }
        th { background: #eee !important; color: #000 !important; border: 1px solid #000; }
        td { border: 1px solid #eee; }
        .bg-yellow-500\/5, .bg-slate-50, .bg-amber-500\/5 { background: none !important; }
    }
</style>