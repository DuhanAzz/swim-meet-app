<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$selectedCatId = $_GET['category_id'] ?? 0;

$stmtCat = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY age_group ASC, gender DESC");
$stmtCat->execute([$uid]);
$categories = $stmtCat->fetchAll();

$heats = [];
if ($selectedCatId) {
    $stmtH = $pdo->prepare("SELECT * FROM race_heats WHERE category_id = ? ORDER BY heat_number ASC"); 
    $stmtH->execute([$selectedCatId]);
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
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase italic tracking-tight">Race Seeding</h1>
            <p class="text-sm text-slate-500 font-medium uppercase tracking-widest">Penyusunan Lintasan Otomatis</p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm mb-10 flex flex-col md:flex-row gap-6 items-end">
        <div class="flex-1 w-full">
            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Pilih Nomor Pertandingan</label>
            <select onchange="window.location.href='index.php?category_id='+this.value" class="w-full p-4 border-2 border-slate-100 rounded-2xl font-black text-slate-700 uppercase text-sm outline-none bg-slate-50">
                <option value="">-- PILIH KATEGORI --</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedCatId == $c['id'] ? 'selected' : '' ?>>
                        <?= $c['age_group'] ?> - <?= $c['distance'] ?>m <?= $c['style'] ?> (<?= $c['gender'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if($selectedCatId): ?>
            <div class="flex gap-3">
                <form action="logic.php" method="POST">
                    <input type="hidden" name="category_id" value="<?= $selectedCatId ?>">
                    <input type="hidden" name="generate_startlist" value="1">
                    <button type="submit" class="bg-blue-600 text-white font-black px-8 py-4 rounded-2xl text-[10px] uppercase tracking-widest hover:bg-blue-700 transition shadow-lg">⚡ GENERATE</button>
                </form>
                <a href="print.php?category_id=<?= $selectedCatId ?>" target="_blank" class="bg-slate-900 text-white font-black px-8 py-4 rounded-2xl text-[10px] uppercase tracking-widest shadow-xl">🖨️ CETAK</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if(!empty($heats)): ?>
        <div class="grid grid-cols-1 gap-12">
            <?php foreach($heats as $h): ?>
                <div class="bg-white rounded-[2.5rem] border border-slate-200 overflow-hidden shadow-sm">
                    <div class="bg-slate-900 px-8 py-5 text-white flex justify-between items-center italic">
                        <h3 class="font-black text-xl uppercase tracking-tighter">SERI / HEAT <?= $h['heat_number'] ?></h3>
                        <span class="text-[10px] font-bold uppercase opacity-60">Official Start List</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-400 border-b">
                                <tr><th class="px-8 py-4 w-20 text-center">LN</th><th class="px-8 py-4">Nama Atlet</th><th class="px-8 py-4 text-right">Entry Time</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php 
                                $stmtSet = $pdo->prepare("SELECT lane_count FROM users WHERE id = ?");
                                $stmtSet->execute([$uid]);
                                $L = (int)($stmtSet->fetchColumn() ?: 8);
                                $lanes = array_fill(1, $L, null); 
                                foreach($h['lanes'] as $l) { $lanes[$l['lane_number']] = $l; }
                                for($i=1; $i<=$L; $i++): $sw = $lanes[$i];
                                ?>
                                <tr class="<?= $sw ? 'bg-white' : 'bg-slate-50/30 opacity-40' ?> h-16">
                                    <td class="px-8 py-4 text-center font-black text-xl <?= $sw ? 'text-blue-600' : 'text-slate-200' ?> border-r"><?= $i ?></td>
                                    <td class="px-8 py-4 font-black uppercase text-slate-800"><?= $sw ? htmlspecialchars($sw['nama_atlet']) : 'Empty' ?></td>
                                    <td class="px-8 py-4 text-right font-mono font-black text-slate-700"><?= $sw ? $sw['entry_time'] : '-' ?></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>