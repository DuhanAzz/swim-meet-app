<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$search = $_GET['q'] ?? '';

// --- QUERY DATA ACARA DENGAN LOGIKA PENYISIHAN ---
$sql = "SELECT ec.*, 
        -- Cek apakah sudah ada Seri Final yang dibuat
        (SELECT COUNT(*) FROM race_heats rh WHERE rh.category_id = ec.id AND rh.stage = 'Final') as has_final,
        -- Hitung berapa atlet yang sudah punya HASIL (result_time) di babak Prelims
        (SELECT COUNT(rl.id) FROM race_lines rl 
         JOIN race_heats rh ON rl.heat_id = rh.id 
         WHERE rh.category_id = ec.id AND rh.stage = 'Prelims' AND rl.result_time IS NOT NULL AND rl.result_time != '') as prelims_done
        FROM event_categories ec 
        WHERE ec.user_id = ?";

$params = [$uid];
if ($search) {
    $sql .= " AND (ec.event_no LIKE ? OR ec.style LIKE ? OR ec.age_group LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$sql .= " ORDER BY ec.event_no ASC";

$stmt = $pdo->prepare($sql); 
$stmt->execute($params);
$events = $stmt->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="mb-10 flex flex-col xl:flex-row justify-between items-center gap-6">
        <div>
            <h1 class="text-3xl font-black uppercase italic text-slate-900 leading-none tracking-tighter">Final Stage Manager</h1>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-2">Penyusunan Lintasan Babak Final (Top 8/10)</p>
        </div>
        <div class="flex flex-wrap gap-3 no-print">
            <a href="index.php" class="bg-white border border-slate-200 text-slate-400 font-black px-6 py-4 rounded-2xl text-[10px] uppercase hover:bg-slate-50 transition">← Kembali Ke Seeding Awal</a>
        </div>
    </div>

    <div class="mb-10">
        <form method="GET" action="" class="relative group max-w-2xl">
            <div class="absolute inset-y-0 left-0 pl-6 flex items-center pointer-events-none">
                <span class="text-xl text-slate-300 group-focus-within:text-blue-500 transition-colors">🔍</span>
            </div>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" 
                   placeholder="Cari Nomor Acara, Gaya, atau KU..." 
                   class="w-full pl-16 pr-6 py-5 bg-white border border-slate-200 rounded-[2rem] font-bold text-sm shadow-sm outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all">
        </form>
    </div>

    <div class="bg-white rounded-[2.5rem] border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-white text-[10px] font-black uppercase tracking-[0.2em]">
                    <tr>
                        <th class="px-8 py-6 text-center w-24">#</th>
                        <th class="px-8 py-6">Event Description</th>
                        <th class="px-8 py-6 text-center">Status Penyisihan</th>
                        <th class="px-8 py-6 text-center">Status Final</th>
                        <th class="px-8 py-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($events)): ?>
                        <tr><td colspan="5" class="px-8 py-20 text-center text-slate-400 font-bold italic">Data tidak ditemukan.</td></tr>
                    <?php else: foreach($events as $e): 
                        $canFinalize = $e['prelims_done'] > 0;
                        $isFinalReady = $e['has_final'] > 0;
                    ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-8 py-6 text-center font-black text-2xl text-slate-300 italic">#<?= $e['event_no'] ?></td>
                            <td class="px-8 py-6">
                                <div class="font-black text-slate-800 uppercase italic tracking-tighter text-lg"><?= $e['distance'] ?>m <?= $e['style'] ?></div>
                                <div class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-widest"><?= $e['age_group'] ?> • <?= $e['gender'] == 'Male' ? 'Putra' : 'Putri' ?></div>
                            </td>
                            
                            <td class="px-8 py-6 text-center">
                                <?php if($canFinalize): ?>
                                    <span class="text-[10px] font-black text-emerald-600 uppercase">✅ Hasil Masuk (<?= $e['prelims_done'] ?>)</span>
                                <?php else: ?>
                                    <span class="text-[10px] font-black text-slate-300 uppercase italic">⏳ Belum Input Waktu</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-8 py-6 text-center">
                                <?php if($isFinalReady): ?>
                                    <span class="px-4 py-2 rounded-full bg-blue-100 text-blue-700 text-[9px] font-black uppercase border border-blue-200">🏁 Final Ready</span>
                                <?php elseif($canFinalize): ?>
                                    <span class="px-4 py-2 rounded-full bg-amber-100 text-amber-700 text-[9px] font-black uppercase border border-amber-200 animate-pulse">⚡ Siap Seed Final</span>
                                <?php else: ?>
                                    <span class="px-4 py-2 rounded-full bg-slate-100 text-slate-400 text-[9px] font-black uppercase border border-slate-200">Locked</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-8 py-6 text-right">
                                <div class="flex justify-end gap-2">
                                    <?php if($canFinalize): ?>
                                        <form action="final_logic.php" method="POST">
                                            <input type="hidden" name="category_id" value="<?= $e['id'] ?>">
                                            <button type="submit" class="bg-amber-500 text-white font-black px-6 py-3 rounded-2xl text-[9px] uppercase tracking-widest hover:bg-slate-900 transition shadow-lg shadow-amber-100">
                                                <?= $isFinalReady ? '🔄 Re-Seed Final' : '⚡ Seed Final' ?>
                                            </button>
                                        </form>
                                        <?php if($isFinalReady): ?>
                                            <a href="view_startlist.php?category_id=<?= $e['id'] ?>&stage=Final" class="bg-white border-2 border-slate-100 text-slate-400 font-black px-6 py-3 rounded-2xl text-[9px] uppercase hover:bg-slate-900 hover:text-white transition">👁️ View</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button disabled class="bg-slate-100 text-slate-300 font-black px-6 py-3 rounded-2xl text-[9px] uppercase cursor-not-allowed">Input Hasil Dulu</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>