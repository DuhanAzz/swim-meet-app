<?php
require_once __DIR__ . '/../src/config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$eventId = $_GET['event_id'] ?? 0; $cat = $_GET['cat'] ?? '';

// LOGIKA: Urutkan yang Running (LIVE) paling atas
$events = $pdo->query("SELECT id, nama_lengkap, location, event_start_date, event_status FROM users WHERE role = 'admin' 
                      ORDER BY CASE WHEN event_status = 'Running' THEN 1 WHEN event_status = 'Registration' THEN 2 ELSE 3 END ASC, 
                      event_start_date DESC")->fetchAll();

$files = []; 
if($eventId && $cat) { 
    $st = $pdo->prepare("SELECT * FROM event_results WHERE event_id = ? AND category = ? ORDER BY created_at DESC"); 
    $st->execute([$eventId, $cat]); $files = $st->fetchAll(); 
    $evName = $pdo->query("SELECT nama_lengkap FROM users WHERE id = $eventId")->fetchColumn(); 
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Results Center - SwimMeet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; padding-top: 8rem; }
        #navbar { background: #0F172A; height: 90px; }
        .loader-finish { opacity: 0; visibility: hidden; transition: 0.4s; }
    </style>
</head>
<body class="bg-slate-50">

    <div id="preloader" class="fixed inset-0 z-[99] bg-slate-900 flex items-center justify-center text-white font-black text-xs uppercase tracking-widest">LOADING <span id="load-perc" class="ml-2">0%</span></div>

    <nav id="navbar" class="fixed w-full z-50 top-0 start-0 flex items-center px-10 border-b border-white/10">
        <div class="max-w-screen-2xl flex items-center justify-between mx-auto w-full">
            <a href="index.php"><img src="img/logo.png" class="h-14 w-auto object-contain"></a>
            <a href="login.php" class="bg-blue-600 text-white px-8 py-2.5 rounded-full font-black text-[10px] uppercase">Login</a>
        </div>
    </nav>

    <main class="max-w-screen-xl mx-auto px-6 py-10 min-h-screen">
        <?php if(!$eventId || !$cat): ?>
            <h1 class="text-6xl font-black uppercase italic mb-16 tracking-tighter leading-none">Results Center</h1>
            <div class="grid grid-cols-1 gap-6">
                <?php foreach($events as $ev): $isLive = ($ev['event_status'] == 'Running'); ?>
                    <div class="bg-white p-10 rounded-[3rem] border <?= $isLive ? 'border-blue-500 shadow-2xl' : 'border-slate-200' ?> flex flex-col md:flex-row items-center gap-10 group transition-all">
                        <div class="w-24 h-24 <?= $isLive ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-400' ?> rounded-[2rem] flex items-center justify-center text-4xl shrink-0">
                            <?= $isLive ? '🏊' : '🏆' ?>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-3xl font-black uppercase italic leading-none"><?= htmlspecialchars($ev['nama_lengkap'] ?? '') ?></h3>
                            <p class="text-slate-400 font-bold text-[10px] uppercase mt-3">📍 <?= htmlspecialchars($ev['location'] ?? '') ?> | 📅 <?= date('d M Y', strtotime($ev['event_start_date'])) ?></p>
                        </div>
                        <div class="flex gap-4">
                            <a href="results.php?event_id=<?= $ev['id'] ?>&cat=StartList" class="px-8 py-4 border-2 border-slate-900 rounded-2xl font-black text-[10px] uppercase">📖 Start List</a>
                            <a href="results.php?event_id=<?= $ev['id'] ?>&cat=Result" class="px-8 py-4 bg-blue-600 text-white rounded-2xl font-black text-[10px] uppercase">🏆 Results</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="max-w-4xl mx-auto">
                <a href="results.php" class="text-blue-600 font-black text-xs uppercase mb-10 inline-block tracking-widest">&larr; Back to List</a>
                <div class="bg-slate-900 rounded-[3rem] p-16 text-white mb-12 relative overflow-hidden">
                    <span class="bg-blue-600 px-5 py-2 rounded-full text-[10px] font-black uppercase italic"><?= htmlspecialchars($cat ?? '') ?></span>
                    <h2 class="text-5xl font-black uppercase italic mt-8"><?= htmlspecialchars($evName ?? '') ?></h2>
                </div>
                <div class="grid gap-4">
                    <?php if(empty($files)): ?><div class="p-20 text-center bg-white rounded-[2rem] border-2 border-dashed text-slate-300 font-black uppercase">No files found.</div><?php else: foreach($files as $f): ?>
                        <div class="bg-white p-8 rounded-[2rem] border flex justify-between items-center group hover:border-blue-500 transition shadow-sm">
                            <span class="font-black text-slate-800 uppercase text-sm italic">📄 <?= htmlspecialchars($f['file_name'] ?? '') ?></span>
                            <a href="<?= $f['file_path'] ?>" download class="bg-blue-600 text-white px-8 py-3 rounded-xl font-black text-[10px] uppercase">Download</a>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const textPerc = document.getElementById('load-perc');
            const preloader = document.getElementById('preloader');
            let progress = 0;
            const interval = setInterval(() => {
                progress += 10;
                if (progress >= 100) { progress = 100; clearInterval(interval); setTimeout(() => { preloader.classList.add('loader-finish'); }, 400); }
                textPerc.innerText = progress + '%';
            }, 80);
        });
    </script>
</body>
</html>