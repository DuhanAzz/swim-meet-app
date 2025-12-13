<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$selectedCatId = $_GET['category_id'] ?? 0;

// 1. Ambil Menu Kategori
$stmtCat = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY distance ASC, style ASC, gender DESC");
$stmtCat->execute([$uid]);
$categories = $stmtCat->fetchAll();

// 2. Ambil Start List (Jika kategori dipilih)
$heats = [];
if ($selectedCatId) {
    // Ambil Heat
    $stmtH = $pdo->prepare("SELECT * FROM race_heats WHERE category_id = ? ORDER BY heat_number DESC"); 
    $stmtH->execute([$selectedCatId]);
    $rawHeats = $stmtH->fetchAll();

    foreach($rawHeats as $h) {
        // PERBAIKAN SQL DI SINI (Baris 33)
        // Menggunakan 'u.nama_lengkap as nama_klub'
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

// 3. Hitung Peserta (Untuk info sebelum generate)
$countEntries = 0;
if ($selectedCatId) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM event_entries WHERE category_id = ?");
    $c->execute([$selectedCatId]);
    $countEntries = $c->fetchColumn();
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Atur Lintasan (Seeding)</h1>
            <p class="text-sm text-slate-500">Susun atlet ke dalam seri dan lintasan secara otomatis.</p>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6 flex flex-col md:flex-row gap-4 items-center">
        <div class="flex-1 w-full">
            <label class="text-xs font-bold text-slate-500 uppercase mb-1 block">Pilih Nomor Lomba</label>
            <select onchange="window.location.href='index.php?category_id='+this.value" class="w-full border border-slate-300 rounded-lg p-2.5 font-bold text-slate-700 focus:ring-blue-500 focus:border-blue-500">
                <option value="">-- Pilih Kategori --</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedCatId == $c['id'] ? 'selected' : '' ?>>
                        <?= $c['distance'] ?>m <?= $c['style'] ?> - <?= $c['gender'] ?> (<?= $c['age_group'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if($selectedCatId): ?>
            <div class="flex items-end pb-0.5 gap-2">
                <form action="logic.php" method="POST">
                    <input type="hidden" name="category_id" value="<?= $selectedCatId ?>">
                    <input type="hidden" name="generate_startlist" value="1">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-lg shadow-lg flex items-center gap-2 transition transform hover:-translate-y-0.5 text-xs">
                        <span class="text-lg">⚡</span> GENERATE
                    </button>
                </form>

                <?php if(!empty($heats)): ?>
                    <a href="print.php?category_id=<?= $selectedCatId ?>" target="_blank" class="bg-slate-800 hover:bg-slate-900 text-white font-bold py-2.5 px-4 rounded-lg shadow-lg flex items-center gap-2 transition transform hover:-translate-y-0.5 text-xs">
                        <span class="text-lg">🖨️</span> CETAK
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if(!$selectedCatId): ?>
        <div class="text-center py-20 text-slate-400">
            <span class="text-5xl block mb-4 grayscale opacity-30">🏊</span>
            Silakan pilih nomor lomba di atas untuk melihat start list.
        </div>
    <?php elseif(empty($heats)): ?>
        <div class="bg-white rounded-xl border-2 border-dashed border-slate-300 p-12 text-center">
            <h3 class="text-xl font-bold text-slate-700">Start List Belum Dibuat</h3>
            <p class="text-slate-500 mt-2">Terdapat <span class="font-bold text-blue-600"><?= $countEntries ?> atlet</span> terdaftar di nomor ini.</p>
            <p class="text-sm text-slate-400 mt-1">Klik tombol "Generate Start List" untuk menyusun lintasan.</p>
        </div>
    <?php else: ?>
        
        <div class="grid grid-cols-1 gap-8">
            <?php foreach($heats as $h): ?>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="bg-slate-800 text-white px-6 py-3 flex justify-between items-center">
                        <h3 class="font-bold text-lg">SERI <?= $h['heat_number'] ?></h3>
                        <span class="text-xs bg-slate-700 px-3 py-1 rounded-full font-mono text-slate-300">Fastest Heat Last</span>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3 text-center w-16">LINT</th>
                                    <th class="px-4 py-3">Nama Atlet</th>
                                    <th class="px-4 py-3">Klub</th>
                                    <th class="px-4 py-3 text-right">Waktu (Seed)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php 
                                // Siapkan array kosong untuk 8 lintasan
                                $lanes = array_fill(1, 8, null);
                                foreach($h['lanes'] as $l) {
                                    $lanes[$l['lane_number']] = $l;
                                }
                                
                                for($i=1; $i<=8; $i++): 
                                    $swimmer = $lanes[$i];
                                ?>
                                <tr class="hover:bg-blue-50 transition <?= $swimmer ? '' : 'bg-slate-50/50' ?>">
                                    <td class="px-4 py-3 text-center font-bold text-slate-400 bg-slate-50 border-r border-slate-100">
                                        <?= $i ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if($swimmer): ?>
                                            <div class="font-bold text-slate-800"><?= htmlspecialchars($swimmer['nama_atlet']) ?></div>
                                        <?php else: ?>
                                            <span class="text-slate-300 italic text-xs">Kosong</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        <?= $swimmer ? htmlspecialchars($swimmer['nama_klub']) : '-' ?>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-blue-600 font-bold">
                                        <?= $swimmer ? $swimmer['entry_time'] : '-' ?>
                                    </td>
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