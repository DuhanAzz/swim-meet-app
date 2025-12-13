<?php
require_once __DIR__ . '/../src/config/database.php';

$eventId = $_GET['event_id'] ?? 0;
$catId = $_GET['category_id'] ?? 0;

// 1. Ambil Event Aktif
$stmtEv = $pdo->prepare("SELECT id, nama_lengkap, location, event_start_date FROM users WHERE role='admin' ORDER BY event_start_date DESC");
$stmtEv->execute();
$events = $stmtEv->fetchAll();

// Default ke event terbaru jika tidak dipilih
if (!$eventId && !empty($events)) {
    $eventId = $events[0]['id'];
}

// 2. Ambil Kategori Event Terpilih
$categories = [];
if ($eventId) {
    $stmtCat = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY distance ASC, style ASC, gender DESC");
    $stmtCat->execute([$eventId]);
    $categories = $stmtCat->fetchAll();
}

// 3. Ambil Data Race
$heats = [];
$eventName = "";
$catName = "";

if ($eventId && $catId) {
    foreach($events as $e) { if($e['id'] == $eventId) { $eventName = $e['nama_lengkap']; } }
    foreach($categories as $c) { 
        if($c['id'] == $catId) $catName = $c['distance']."m ".$c['style']." - ".$c['gender']." (".$c['age_group'].")"; 
    }

    $stmtH = $pdo->prepare("SELECT * FROM race_heats WHERE category_id = ? ORDER BY heat_number ASC");
    $stmtH->execute([$catId]);
    $rawHeats = $stmtH->fetchAll();

    foreach($rawHeats as $h) {
        $sqlL = "SELECT rl.*, s.nama_atlet, u.nama_klub 
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Result - SwimMeet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }</style>
</head>
<body>

    <nav class="bg-slate-900 text-white p-4 shadow-lg sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <a href="index.php" class="font-black text-xl tracking-tighter">SWIM<span class="text-blue-400">MEET</span><span class="text-[10px] font-normal text-slate-400 ml-1">LIVE</span></a>
            <a href="login.php" class="text-xs font-bold bg-white/10 px-3 py-1.5 rounded-full hover:bg-white/20 transition">Login Peserta</a>
        </div>
    </nav>

    <div class="container mx-auto p-4 max-w-3xl min-h-screen">
        
        <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 mb-4">
            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1 block">Pilih Kompetisi</label>
            <select onchange="window.location.href='live.php?event_id='+this.value" class="w-full text-sm font-bold text-slate-800 border-b-2 border-slate-100 pb-2 outline-none focus:border-blue-500 bg-transparent">
                <?php foreach($events as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $eventId == $e['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['nama_lengkap']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if($eventId): ?>
        <div class="bg-white p-2 rounded-xl shadow-sm border border-slate-200 mb-6 flex overflow-x-auto gap-2 no-scrollbar">
            <?php foreach($categories as $c): 
                $isActive = ($catId == $c['id']);
                $styleClass = $isActive ? 'bg-blue-600 text-white shadow-lg scale-105' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-200';
            ?>
            <a href="live.php?event_id=<?= $eventId ?>&category_id=<?= $c['id'] ?>" 
               class="flex-shrink-0 px-4 py-2 rounded-lg text-xs font-bold transition transform <?= $styleClass ?>">
                <div class="whitespace-nowrap"><?= $c['distance'] ?>m <?= $c['style'] ?></div>
                <div class="text-[9px] opacity-80 whitespace-nowrap"><?= $c['gender'] ?> • <?= $c['age_group'] ?></div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if($catId && !empty($heats)): ?>
            
            <div class="text-center mb-6">
                <span class="bg-blue-100 text-blue-800 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-wider">Official Result</span>
                <h2 class="text-lg font-black text-slate-800 mt-2 leading-tight"><?= $catName ?></h2>
            </div>

            <div class="space-y-4">
                <?php foreach($heats as $h): ?>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-slate-200">
                    <div class="bg-slate-50 px-4 py-2 border-b border-slate-100 flex justify-between items-center">
                        <span class="text-xs font-bold text-slate-500 uppercase">Seri <?= $h['heat_number'] ?></span>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <?php foreach($h['lanes'] as $l): ?>
                        <div class="flex items-center p-3 hover:bg-blue-50 transition relative overflow-hidden">
                            <?php if($l['rank'] == 1): ?>
                                <div class="absolute left-0 top-0 bottom-0 w-1 bg-yellow-400"></div>
                            <?php elseif($l['rank'] == 2): ?>
                                <div class="absolute left-0 top-0 bottom-0 w-1 bg-slate-300"></div>
                            <?php elseif($l['rank'] == 3): ?>
                                <div class="absolute left-0 top-0 bottom-0 w-1 bg-orange-300"></div>
                            <?php endif; ?>

                            <div class="w-8 text-center text-xs font-bold text-slate-400 mr-2"><?= $l['lane_number'] ?></div>
                            <div class="flex-1">
                                <div class="text-sm font-bold text-slate-800 leading-none">
                                    <?= htmlspecialchars($l['nama_atlet']) ?>
                                    <?php if($l['rank'] <= 3 && $l['rank'] != null) echo '🏅'; ?>
                                </div>
                                <div class="text-[10px] text-slate-500 mt-1"><?= htmlspecialchars($l['nama_klub']) ?></div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-mono font-black text-blue-600">
                                    <?= $l['result_time'] ? $l['result_time'] : '-' ?>
                                </div>
                                <?php if($l['rank']): ?>
                                    <div class="text-[9px] font-bold text-slate-400">Rank #<?= $l['rank'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php elseif($catId): ?>
            <div class="flex flex-col items-center justify-center py-20 text-slate-400">
                <span class="text-4xl mb-2">⏱️</span>
                <p class="text-sm">Start List / Hasil belum tersedia.</p>
            </div>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center py-20 text-slate-400">
                <span class="text-4xl mb-2">👆</span>
                <p class="text-sm">Pilih nomor lomba di atas.</p>
            </div>
        <?php endif; ?>

    </div>

    <footer class="text-center py-8 text-[10px] text-slate-400">
        &copy; <?= date('Y') ?> SwimMeet Manager. Live Timing System.
    </footer>

</body>
</html>
