<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$selectedCatId = $_GET['category_id'] ?? 0;
$msg = $_GET['msg'] ?? '';

// 1. Ambil Menu Kategori
$stmtCat = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY distance ASC, style ASC, gender DESC");
$stmtCat->execute([$uid]);
$categories = $stmtCat->fetchAll();

// 2. Ambil Data Start List + Hasil (Jika kategori dipilih)
$heats = [];
if ($selectedCatId) {
    $stmtH = $pdo->prepare("SELECT * FROM race_heats WHERE category_id = ? ORDER BY heat_number ASC");
    $stmtH->execute([$selectedCatId]);
    $rawHeats = $stmtH->fetchAll();

    foreach($rawHeats as $h) {
        // PERBAIKAN SQL DI SINI (Baris 32)
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
    
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Input Hasil Lomba</h1>
            <p class="text-sm text-slate-500">Masukkan waktu finis atlet untuk menentukan juara.</p>
        </div>
        <?php if($msg == 'success'): ?>
            <div class="bg-green-100 text-green-700 px-4 py-2 rounded-lg font-bold text-sm shadow-sm animate-pulse">
                ✅ Data berhasil disimpan & Peringkat diperbarui!
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6">
        <label class="text-xs font-bold text-slate-500 uppercase mb-1 block">Pilih Nomor yang Sedang Bertanding</label>
        <select onchange="window.location.href='index.php?category_id='+this.value" class="w-full border border-slate-300 rounded-lg p-2.5 font-bold text-slate-700 focus:ring-blue-500 focus:border-blue-500">
            <option value="">-- Pilih Kategori --</option>
            <?php foreach($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $selectedCatId == $c['id'] ? 'selected' : '' ?>>
                    <?= $c['distance'] ?>m <?= $c['style'] ?> - <?= $c['gender'] ?> (<?= $c['age_group'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if(!$selectedCatId): ?>
        <div class="text-center py-20 text-slate-400 bg-white rounded-xl border border-slate-200">
            <span class="text-5xl block mb-4 grayscale opacity-30">⏱️</span>
            <p>Silakan pilih nomor lomba di atas untuk mulai input hasil.</p>
        </div>
    <?php elseif(empty($heats)): ?>
        <div class="text-center py-12 text-slate-400 border-2 border-dashed border-slate-300 rounded-xl">
            <span class="text-4xl block mb-2">🤷‍♂️</span>
            <p>Start List belum dibuat. Silakan ke menu "Start List" untuk generate lintasan dulu.</p>
        </div>
    <?php else: ?>
        
        <form action="logic.php" method="POST">
            <input type="hidden" name="category_id" value="<?= $selectedCatId ?>">
            <input type="hidden" name="save_results" value="1">

            <div class="grid grid-cols-1 gap-8 mb-20">
                <?php foreach($heats as $h): ?>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="bg-blue-900 text-white px-6 py-3 flex justify-between items-center">
                            <h3 class="font-bold text-lg">SERI <?= $h['heat_number'] ?></h3>
                            <span class="text-xs bg-blue-800 px-3 py-1 rounded-full text-blue-200 font-mono">Input Time</span>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-slate-100 text-slate-500 uppercase text-xs">
                                    <tr>
                                        <th class="px-4 py-3 w-16 text-center">LN</th>
                                        <th class="px-4 py-3">Atlet</th>
                                        <th class="px-4 py-3">Klub</th>
                                        <th class="px-4 py-3 text-right">HASIL WAKTU</th>
                                        <th class="px-4 py-3 w-16 text-center">RANK</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach($h['lanes'] as $l): ?>
                                    <tr class="hover:bg-blue-50 transition">
                                        <td class="px-4 py-3 text-center font-bold text-slate-500 bg-slate-50 border-r border-slate-100">
                                            <?= $l['lane_number'] ?>
                                        </td>
                                        <td class="px-4 py-3 font-bold text-slate-800">
                                            <?= htmlspecialchars($l['nama_atlet']) ?>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600 text-xs">
                                            <?= htmlspecialchars($l['nama_klub']) ?>
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <input type="text" 
                                                   name="result[<?= $l['id'] ?>]" 
                                                   value="<?= htmlspecialchars($l['result_time'] ?? '') ?>"
                                                   class="w-32 text-right font-mono font-bold text-lg border border-slate-300 rounded px-2 py-1 focus:ring-2 focus:ring-blue-500 uppercase placeholder-slate-300"
                                                   placeholder="00:00.00">
                                        </td>
                                        <td class="px-4 py-3 text-center font-black text-lg <?= $l['rank']==1 ? 'text-yellow-500' : ($l['rank']==2 ? 'text-slate-400' : ($l['rank']==3 ? 'text-orange-700' : 'text-slate-300')) ?>">
                                            <?= $l['rank'] ? '#' . $l['rank'] : '-' ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="fixed bottom-6 right-6 z-50 flex gap-3">
                
                <a href="print.php?category_id=<?= $selectedCatId ?>" target="_blank" class="bg-slate-800 hover:bg-slate-900 text-white font-bold py-3 px-6 rounded-full shadow-2xl flex items-center gap-2 transition transform hover:scale-105 border-2 border-white ring-2 ring-slate-200">
                    <span class="text-xl">🖨️</span> CETAK
                </a>

                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-full shadow-2xl flex items-center gap-3 transition transform hover:scale-105 border-2 border-white ring-2 ring-green-200">
                    <span class="text-xl">💾</span> SIMPAN
                </button>
            </div>
        </form>

    <?php endif; ?>

</div>