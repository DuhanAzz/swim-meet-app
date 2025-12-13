<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$selectedCatId = $_GET['category_id'] ?? 0;
$msg = $_GET['msg'] ?? '';

// 1. Ambil List Kategori
$stmtCat = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY age_group ASC, gender DESC");
$stmtCat->execute([$uid]);
$categories = $stmtCat->fetchAll();

$allQualifiers = [];
$finalHeat = null;
$L = 8; // Default

if ($selectedCatId) {
    // Ambil setting lintasan
    $stmtSet = $pdo->prepare("SELECT lane_count FROM users WHERE id = ?");
    $stmtSet->execute([$uid]);
    $L = (int)($stmtSet->fetchColumn() ?: 8);

    // 2. Ambil Kualifikasi (Ambil L + 2 untuk menyertakan Reserves)
    $limitPlus = $L + 2;
    $sqlQualifiers = "SELECT rl.*, s.nama_atlet, u.nama_lengkap as nama_klub 
                      FROM race_lines rl
                      JOIN race_heats rh ON rl.heat_id = rh.id
                      JOIN swimmers s ON rl.swimmer_id = s.id
                      JOIN users u ON s.user_id = u.id
                      WHERE rh.category_id = ? AND rh.stage = 'Prelims' 
                      AND rl.result_time IS NOT NULL AND rl.result_time != '' AND rl.result_time != 'NT'
                      ORDER BY rl.result_time ASC LIMIT $limitPlus";
    $stmtQ = $pdo->prepare($sqlQualifiers);
    $stmtQ->execute([$selectedCatId]);
    $allQualifiers = $stmtQ->fetchAll();

    // Pisahkan Finalis (1-8) dan Reserves (9-10)
    $finalists = array_slice($allQualifiers, 0, $L);
    $reserves = array_slice($allQualifiers, $L, 2);

    // 3. Ambil Start List Final yang sudah tersimpan di DB
    $stmtFH = $pdo->prepare("SELECT * FROM race_heats WHERE category_id = ? AND stage = 'Final' LIMIT 1");
    $stmtFH->execute([$selectedCatId]);
    $finalHeat = $stmtFH->fetch();
    
    if ($finalHeat) {
        $stmtFL = $pdo->prepare("SELECT rl.*, s.nama_atlet, u.nama_lengkap as nama_klub 
                                 FROM race_lines rl 
                                 JOIN swimmers s ON rl.swimmer_id = s.id 
                                 JOIN users u ON s.user_id = u.id
                                 WHERE rl.heat_id = ? ORDER BY rl.lane_number ASC");
        $stmtFL->execute([$finalHeat['id']]);
        $finalHeat['lanes'] = $stmtFL->fetchAll();
    }
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="flex justify-between items-center mb-10">
        <div>
            <h1 class="text-3xl font-black uppercase tracking-tighter italic text-slate-900">Final Stage Seeding</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest">Penentuan Finalis & Cadangan (Reserves)</p>
        </div>
        <?php if($msg == 'final_success'): ?>
            <div class="bg-green-500 text-white px-6 py-2 rounded-2xl font-black text-xs shadow-lg animate-bounce">
                🏆 BERHASIL DISUSUN!
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white p-6 rounded-[2.5rem] border border-slate-200 shadow-sm mb-10">
        <label class="block text-[10px] font-black text-slate-400 uppercase mb-3 ml-1">Pilih Nomor Pertandingan</label>
        <select onchange="window.location.href='final.php?category_id='+this.value" class="w-full p-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-slate-700 uppercase text-sm focus:bg-white focus:border-blue-500 transition outline-none">
            <option value="">-- PILIH NOMOR LOMBA --</option>
            <?php foreach($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $selectedCatId == $c['id'] ? 'selected' : '' ?>>
                    <?= $c['age_group'] ?> - <?= $c['distance'] ?>m <?= $c['style'] ?> (<?= $c['gender'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($selectedCatId): ?>
        
        <?php if (count($allQualifiers) <= $L && count($allQualifiers) > 0): ?>
            <div class="mb-10 p-6 bg-blue-50 border-2 border-blue-100 rounded-3xl flex items-center gap-6">
                <span class="text-4xl">ℹ️</span>
                <div>
                    <h4 class="font-black text-blue-800 uppercase text-sm">Informasi World Aquatics (Rule 3.1.1.1)</h4>
                    <p class="text-blue-600 text-xs font-medium">Jumlah peserta (<?= count($allQualifiers) ?>) tidak melebihi jumlah lintasan (<?= $L ?>). Babak ini bisa dianggap sebagai Direct Final.</p>
                </div>
            </div>
        <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-10">
        
        <div class="xl:col-span-1 space-y-8">
            <div class="bg-white rounded-[2.5rem] border border-slate-200 overflow-hidden shadow-sm">
                <div class="bg-slate-900 p-6 text-white flex justify-between items-center">
                    <h3 class="font-black text-xs uppercase tracking-widest">Qualification Result</h3>
                    <span class="bg-blue-600 px-3 py-1 rounded-full text-[9px] font-black italic">PRELIMS</span>
                </div>
                <div class="p-6">
                    <?php if (empty($allQualifiers)): ?>
                        <p class="text-center py-10 text-slate-400 text-xs font-black uppercase">Belum ada data hasil.</p>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach($allQualifiers as $idx => $q): 
                                $isReserve = ($idx >= $L);
                            ?>
                            <div class="flex items-center gap-4 p-3 <?= $isReserve ? 'bg-amber-50 border-amber-100 opacity-70' : 'bg-slate-50 border-slate-100' ?> rounded-2xl border transition">
                                <div class="w-7 h-7 rounded-full bg-white border font-black text-[10px] flex items-center justify-center <?= $isReserve ? 'text-amber-600' : 'text-slate-400' ?>">
                                    <?= $idx + 1 ?>
                                </div>
                                <div class="flex-1">
                                    <div class="font-black text-slate-800 text-[11px] uppercase truncate"><?= htmlspecialchars($q['nama_atlet']) ?></div>
                                    <div class="text-[9px] font-bold text-slate-400 uppercase"><?= $isReserve ? 'RESERVE ' . ($idx - $L + 1) : 'FINALIST' ?></div>
                                </div>
                                <div class="font-mono font-black text-blue-600 text-[11px]"><?= $q['result_time'] ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <form action="final_logic.php" method="POST" class="mt-8">
                            <input type="hidden" name="category_id" value="<?= $selectedCatId ?>">
                            <button type="submit" class="w-full bg-slate-900 text-white font-black py-4 rounded-3xl shadow-xl hover:bg-orange-500 transition transform hover:-translate-y-1 uppercase tracking-widest text-[10px]">
                                ⚡ GENERATE START LIST FINAL
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="xl:col-span-2">
            <?php if ($finalHeat): ?>
                <div class="bg-white rounded-[3rem] border-2 border-orange-500 overflow-hidden shadow-2xl shadow-orange-100 relative mb-8">
                    <div class="bg-orange-500 p-8 text-white flex justify-between items-end relative overflow-hidden">
                        <div class="relative z-10">
                            <h2 class="font-black text-3xl uppercase tracking-tighter italic leading-none mb-2 text-white">CHAMPIONSHIP FINAL</h2>
                            <p class="text-[10px] font-bold uppercase tracking-[0.3em] opacity-80">Lane Assignment - Spearhead Standard</p>
                        </div>
                        <a href="print_final.php?category_id=<?= $selectedCatId ?>" target="_blank" class="bg-white text-orange-600 font-black px-8 py-3 rounded-2xl text-[10px] uppercase tracking-widest shadow-xl relative z-10 hover:scale-105 transition">
                            🖨️ PRINT FINAL LIST
                        </a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-400 border-b">
                                <tr>
                                    <th class="px-8 py-5 w-20 text-center border-r">LN</th>
                                    <th class="px-8 py-5">Finalist Name</th>
                                    <th class="px-8 py-5">Club</th>
                                    <th class="px-8 py-5 text-right">Seed Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php 
                                $lanes = array_fill(1, $L, null);
                                foreach($finalHeat['lanes'] as $l) { $lanes[$l['lane_number']] = $l; }
                                for($i=1; $i<=$L; $i++): $sw = $lanes[$i];
                                ?>
                                <tr class="<?= $sw ? 'bg-white' : 'bg-slate-50/50 opacity-40' ?> h-16">
                                    <td class="px-8 py-4 text-center font-black text-2xl <?= $sw ? 'text-orange-500' : 'text-slate-200' ?> border-r"><?= $i ?></td>
                                    <td class="px-8 py-4 font-black uppercase text-slate-800 text-sm italic"><?= $sw ? htmlspecialchars($sw['nama_atlet']) : 'Empty' ?></td>
                                    <td class="px-8 py-4 text-[10px] font-bold text-slate-400 uppercase"><?= $sw ? htmlspecialchars($sw['nama_klub']) : '-' ?></td>
                                    <td class="px-8 py-4 text-right font-mono font-black text-slate-700"><?= $sw ? $sw['entry_time'] : '-' ?></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!empty($reserves)): ?>
                <div class="bg-white rounded-3xl border border-slate-200 p-6 flex flex-col md:flex-row items-center gap-6 shadow-sm">
                    <div class="bg-amber-100 text-amber-600 px-4 py-2 rounded-xl font-black text-[10px] uppercase tracking-widest italic shrink-0">Official Reserves</div>
                    <div class="flex flex-wrap gap-4">
                        <?php foreach($reserves as $idx => $r): ?>
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-black text-slate-400"><?= $idx + $L + 1 ?></span>
                                <span class="text-xs font-black text-slate-700 uppercase"><?= htmlspecialchars($r['nama_atlet']) ?></span>
                                <span class="text-[10px] font-mono font-bold text-blue-500">(<?= $r['result_time'] ?>)</span>
                            </div>
                            <?php if($idx == 0 && count($reserves) > 1): ?> <div class="w-px h-4 bg-slate-200"></div> <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="h-full min-h-[400px] flex flex-col items-center justify-center bg-white border-4 border-dashed border-slate-200 rounded-[3rem] p-20 text-slate-300">
                    <span class="text-8xl mb-6">🏆</span>
                    <p class="font-black uppercase tracking-[0.4em] text-xs">Waiting for Final Seeding</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>