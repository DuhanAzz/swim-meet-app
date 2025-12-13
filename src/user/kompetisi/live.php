<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$eventId = $_GET['event_id'] ?? 0;
$catId = $_GET['category_id'] ?? 0;

// 1. Ambil Daftar Event yang Aktif
$stmtEv = $pdo->prepare("SELECT id, nama_lengkap FROM users WHERE role='admin' ORDER BY event_start_date DESC");
$stmtEv->execute();
$events = $stmtEv->fetchAll();

// 2. Jika Event Dipilih, Ambil Kategori
$categories = [];
if ($eventId) {
    $stmtCat = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY distance ASC, style ASC, gender DESC");
    $stmtCat->execute([$eventId]);
    $categories = $stmtCat->fetchAll();
}

// 3. Ambil Data Race (Heat & Line)
$heats = [];
$eventName = "";
$catName = "";

if ($eventId && $catId) {
    // Nama Event & Kategori
    foreach($events as $e) { if($e['id'] == $eventId) $eventName = $e['nama_lengkap']; }
    foreach($categories as $c) { 
        if($c['id'] == $catId) $catName = $c['distance']."m ".$c['style']." - ".$c['gender']." (".$c['age_group'].")"; 
    }

    // Ambil Data Race
    $stmtH = $pdo->prepare("SELECT * FROM race_heats WHERE category_id = ? ORDER BY heat_number ASC");
    $stmtH->execute([$catId]);
    $rawHeats = $stmtH->fetchAll();

    foreach($rawHeats as $h) {
        // Join ke tabel swimmers dan users (klub)
        // Kita juga cek apakah swimmer_id milik user yang sedang login (untuk highlight)
        $sqlL = "SELECT rl.*, s.nama_atlet, u.nama_klub, u.id as club_id
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
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Live Result</h1>
            <p class="text-sm text-slate-500">Pantau jadwal lintasan dan hasil pertandingan secara real-time.</p>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="text-xs font-bold text-slate-500 uppercase mb-1 block">Pilih Kompetisi</label>
            <select onchange="window.location.href='live.php?event_id='+this.value" class="w-full border border-slate-300 rounded-lg p-2.5 font-bold text-slate-700">
                <option value="">-- Pilih Event --</option>
                <?php foreach($events as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $eventId == $e['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['nama_lengkap']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if($eventId): ?>
        <div>
            <label class="text-xs font-bold text-slate-500 uppercase mb-1 block">Pilih Nomor Lomba</label>
            <select onchange="window.location.href='live.php?event_id=<?= $eventId ?>&category_id='+this.value" class="w-full border border-slate-300 rounded-lg p-2.5 font-bold text-slate-700">
                <option value="">-- Pilih Nomor --</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $catId == $c['id'] ? 'selected' : '' ?>>
                        <?= $c['distance'] ?>m <?= $c['style'] ?> - <?= $c['gender'] ?> (<?= $c['age_group'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </div>

    <?php if($eventId && $catId): ?>
        
        <?php if(empty($heats)): ?>
            <div class="text-center py-20 text-slate-400 bg-white rounded-xl border border-slate-200">
                <span class="text-4xl block mb-2">⏳</span>
                <p>Start List (Lintasan) belum dirilis oleh panitia.</p>
            </div>
        <?php else: ?>
            
            <div class="mb-4 text-center">
                <h2 class="text-xl font-black text-blue-900 uppercase"><?= htmlspecialchars($eventName) ?></h2>
                <span class="bg-blue-100 text-blue-700 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">
                    <?= $catName ?>
                </span>
            </div>

            <div class="grid grid-cols-1 gap-6">
                <?php foreach($heats as $h): ?>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="bg-slate-900 text-white px-6 py-3 flex justify-between items-center">
                            <h3 class="font-bold text-md">SERI <?= $h['heat_number'] ?></h3>
                            <span class="text-[10px] bg-slate-700 px-2 py-1 rounded text-slate-300">Official Result</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                                    <tr>
                                        <th class="px-4 py-2 w-10 text-center">LN</th>
                                        <th class="px-4 py-2">Atlet</th>
                                        <th class="px-4 py-2 text-right">Waktu</th>
                                        <th class="px-4 py-2 w-10 text-center">Rank</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach($h['lanes'] as $l): 
                                        $isMyClub = ($l['club_id'] == $uid);
                                        $rowClass = $isMyClub ? 'bg-yellow-50' : 'hover:bg-slate-50';
                                    ?>
                                    <tr class="<?= $rowClass ?> transition">
                                        <td class="px-4 py-3 text-center font-bold text-slate-400 border-r border-slate-100 bg-slate-50">
                                            <?= $l['lane_number'] ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-bold text-slate-800 <?= $isMyClub ? 'text-blue-700' : '' ?>">
                                                <?= htmlspecialchars($l['nama_atlet']) ?>
                                                <?php if($isMyClub): ?>
                                                    <span class="text-[9px] bg-blue-600 text-white px-1.5 rounded ml-1">YOU</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-xs text-slate-500"><?= htmlspecialchars($l['nama_klub']) ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono font-bold text-base text-slate-700">
                                            <?= $l['result_time'] ? $l['result_time'] : '<span class="text-slate-300 text-xs font-normal">Belum Main</span>' ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php if($l['rank'] == 1): ?>
                                                <span class="text-xl">🥇</span>
                                            <?php elseif($l['rank'] == 2): ?>
                                                <span class="text-xl">🥈</span>
                                            <?php elseif($l['rank'] == 3): ?>
                                                <span class="text-xl">🥉</span>
                                            <?php elseif($l['rank']): ?>
                                                <span class="font-bold text-slate-500">#<?= $l['rank'] ?></span>
                                            <?php else: ?>
                                                -
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

        <?php endif; ?>

    <?php else: ?>
        <div class="flex flex-col items-center justify-center py-20 text-slate-400 bg-white rounded-xl border border-slate-200">
            <span class="text-6xl mb-4 grayscale opacity-30">🏆</span>
            <p class="font-bold">Silakan pilih Kompetisi & Nomor Lomba</p>
            <p class="text-sm">Untuk melihat Start List atau Hasil Pertandingan.</p>
        </div>
    <?php endif; ?>

</div>

