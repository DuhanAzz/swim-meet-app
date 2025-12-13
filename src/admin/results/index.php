<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$adminMode = $_SESSION['event_type'] ?? 'Langsung Final';
$selectedCatId = $_GET['category_id'] ?? 0;
// Default stage adalah Prelims, kecuali dipilih Final
$stage = $_GET['stage'] ?? 'Prelims'; 
$msg = $_GET['msg'] ?? '';

// 1. Ambil Menu Kategori
$stmtCat = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY age_group ASC, gender DESC");
$stmtCat->execute([$uid]);
$categories = $stmtCat->fetchAll();

// 2. Ambil Data Seri + Hasil berdasarkan Stage (Prelims/Final)
$heats = [];
if ($selectedCatId) {
    $stmtH = $pdo->prepare("SELECT * FROM race_heats WHERE category_id = ? AND stage = ? ORDER BY heat_number ASC");
    $stmtH->execute([$selectedCatId, $stage]);
    $rawHeats = $stmtH->fetchAll();

    foreach($rawHeats as $h) {
        $sqlL = "SELECT rl.*, s.nama_atlet, u.nama_lengkap as nama_klub 
                 FROM race_lines rl 
                 JOIN swimmers s ON rl.swimmer_id = s.id 
                 JOIN users u ON s.user_id = u.id
                 WHERE rl.heat_id = ? ORDER BY rl.lane_number ASC";
        $stmtL = $pdo->prepare($sqlL);
        $stmtL->execute([$h['id']]);
        $h['lanes'] = $stmtL->fetchAll();
        $heats[] = $h;
    }
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase italic tracking-tight">Race Results</h1>
            <p class="text-sm text-slate-500 font-medium uppercase tracking-widest">Manajemen Waktu Finis & Peringkat</p>
        </div>
        <?php if($msg == 'success'): ?>
            <div class="bg-green-600 text-white px-6 py-2 rounded-xl font-bold text-xs shadow-lg animate-bounce">
                ✅ Berhasil Disimpan!
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm mb-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Pilih Nomor Lomba</label>
                <select onchange="window.location.href='index.php?category_id='+this.value+'&stage=<?= $stage ?>'" class="w-full p-4 border-2 border-slate-50 rounded-2xl font-black text-slate-700 uppercase text-sm focus:border-blue-500 transition outline-none bg-slate-50">
                    <option value="">-- PILIH NOMOR --</option>
                    <?php foreach($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selectedCatId == $c['id'] ? 'selected' : '' ?>>
                            <?= $c['age_group'] ?> - <?= $c['distance'] ?>m <?= $c['style'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if($adminMode == 'Babak Penyisihan' && $selectedCatId): ?>
            <div class="flex bg-slate-100 p-1.5 rounded-2xl w-fit ml-auto">
                <a href="index.php?category_id=<?= $selectedCatId ?>&stage=Prelims" 
                   class="px-8 py-2.5 rounded-xl text-[10px] font-black uppercase transition <?= $stage == 'Prelims' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-400 hover:text-slate-600' ?>">
                   Penyisihan (Prelims)
                </a>
                <a href="index.php?category_id=<?= $selectedCatId ?>&stage=Final" 
                   class="px-8 py-2.5 rounded-xl text-[10px] font-black uppercase transition <?= $stage == 'Final' ? 'bg-white text-orange-600 shadow-sm' : 'text-slate-400 hover:text-slate-600' ?>">
                   Babak FINAL
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if(!$selectedCatId): ?>
        <div class="text-center py-32 text-slate-300">
            <span class="text-7xl block mb-4 grayscale opacity-20">⏱️</span>
            <p class="font-black uppercase tracking-widest text-xs">Pilih kategori untuk input hasil</p>
        </div>
    <?php elseif(empty($heats)): ?>
        <div class="text-center py-20 bg-white rounded-[2.5rem] border-2 border-dashed border-slate-200 mx-auto max-w-2xl">
            <p class="text-slate-400 font-bold mb-2 uppercase text-sm">Data Seri tidak ditemukan.</p>
            <p class="text-slate-300 text-[10px] font-black uppercase">Pastikan Seeding babak <?= $stage ?> sudah dilakukan.</p>
        </div>
    <?php else: ?>
        
        <form action="logic.php" method="POST">
            <input type="hidden" name="category_id" value="<?= $selectedCatId ?>">
            <input type="hidden" name="stage" value="<?= $stage ?>">
            <input type="hidden" name="save_results" value="1">

            <div class="space-y-10 mb-32">
                <?php foreach($heats as $h): ?>
                    <div class="bg-white rounded-[2.5rem] border border-slate-200 overflow-hidden shadow-sm">
                        <div class="<?= $stage == 'Final' ? 'bg-orange-500' : 'bg-slate-900' ?> px-8 py-5 text-white flex justify-between items-center italic transition-colors">
                            <h3 class="font-black text-xl uppercase tracking-tighter">
                                <?= $stage == 'Final' ? '🏆 CHAMPIONSHIP FINAL' : 'SERI / HEAT ' . $h['heat_number'] ?>
                            </h3>
                            <span class="text-[10px] font-bold uppercase tracking-widest opacity-60">Input Mode</span>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-400 border-b">
                                    <tr>
                                        <th class="px-8 py-4 w-20 text-center border-r">LN</th>
                                        <th class="px-8 py-4">Nama Atlet / Klub</th>
                                        <th class="px-8 py-4 text-center">Waktu Finis (MM:SS.ms)</th>
                                        <th class="px-8 py-4 w-32 text-center">Rank</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach($h['lanes'] as $l): ?>
                                    <tr class="hover:bg-blue-50/30 transition h-20">
                                        <td class="px-8 py-4 text-center font-black text-2xl text-slate-300 border-r">
                                            <?= $l['lane_number'] ?>
                                        </td>
                                        <td class="px-8 py-4">
                                            <div class="font-black uppercase text-slate-800 text-sm leading-tight"><?= htmlspecialchars($l['nama_atlet']) ?></div>
                                            <div class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter mt-1"><?= htmlspecialchars($l['nama_klub']) ?></div>
                                        </td>
                                        <td class="px-8 py-4 text-center">
                                            <input type="text" 
                                                   name="result[<?= $l['id'] ?>]" 
                                                   value="<?= htmlspecialchars($l['result_time'] ?? '') ?>"
                                                   class="w-44 text-center font-mono font-black text-2xl border-2 border-slate-100 rounded-2xl py-3 focus:border-blue-500 transition outline-none text-blue-600 bg-slate-50 focus:bg-white"
                                                   placeholder="00:00.00">
                                        </td>
                                        <td class="px-8 py-4 text-center">
                                            <?php if($l['rank'] == 1): ?>
                                                <span class="text-3xl">🥇</span>
                                            <?php elseif($l['rank'] == 2): ?>
                                                <span class="text-3xl">🥈</span>
                                            <?php elseif($l['rank'] == 3): ?>
                                                <span class="text-3xl">🥉</span>
                                            <?php else: ?>
                                                <span class="font-black text-xl text-slate-200 italic"><?= $l['rank'] ? '#' . $l['rank'] : '-' ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="fixed bottom-10 right-10 z-50 flex gap-4">
                <button type="submit" class="bg-blue-600 text-white font-black px-12 py-5 rounded-[2.5rem] shadow-2xl shadow-blue-200 transition transform hover:-translate-y-1 uppercase tracking-widest text-xs flex items-center gap-3">
                    <span class="text-lg">💾</span> Simpan Babak <?= $stage ?>
                </button>
            </div>
        </form>

    <?php endif; ?>

</div>