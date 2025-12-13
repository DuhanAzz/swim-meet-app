<?php
require_once __DIR__ . '/../src/config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$eventId = $_GET['event_id'] ?? 0;
$cat = $_GET['cat'] ?? '';
$events = $pdo->query("SELECT id, nama_lengkap, location, event_start_date FROM users WHERE role = 'admin' ORDER BY event_start_date DESC")->fetchAll();

$files = [];
if($eventId && $cat) {
    $st = $pdo->prepare("SELECT * FROM event_results WHERE event_id = ? AND category = ? ORDER BY created_at DESC");
    $st->execute([$eventId, $cat]);
    $files = $st->fetchAll();
    $evName = $pdo->query("SELECT nama_lengkap FROM users WHERE id = $eventId")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hasil & Dokumen - SwimMeet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        #navbar { background-color: #0F172A; height: 90px; display: flex; align-items: center; border-bottom: 1px solid #1e293b; }
        .nav-link { position: relative; color: white; transition: 0.3s; font-size: 0.95rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; }
        .nav-link:hover, .nav-link.active { color: #3b82f6; }
    </style>
</head>
<body class="bg-slate-50 pt-32">

    <nav id="navbar" class="fixed w-full z-50 top-0 start-0 px-10">
        <div class="max-w-screen-2xl flex items-center justify-between mx-auto w-full">
            <a href="index.php"><img src="img/logo.png" class="h-16 w-auto object-contain"></a>
            <div class="flex items-center gap-12">
                <div class="hidden lg:flex items-center space-x-12">
                    <a href="index.php" class="nav-link">Home</a>
                    <a href="events.php" class="nav-link">Jadwal Lomba</a>
                    <a href="results.php" class="nav-link active">Hasil & Dokumen</a>
                    <a href="index.php#instruction" class="nav-link text-yellow-400">Panduan</a>
                </div>
                <div class="flex items-center border-l border-white/20 pl-12">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="../src/user/dashboard.php" class="bg-blue-600 text-white px-10 py-3 rounded-full font-black text-xs uppercase tracking-widest">Dashboard</a>
                    <?php else: ?>
                        <a href="login.php" class="bg-blue-600 text-white px-10 py-3 rounded-full font-black text-xs uppercase tracking-widest">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-screen-xl mx-auto px-6 py-12 min-h-screen">
        <?php if(!$eventId || !$cat): ?>
            <h1 class="text-5xl font-black uppercase italic mb-12 border-l-[12px] border-blue-600 pl-8 tracking-tighter">Results Center</h1>
            <div class="flex flex-col gap-6">
                <?php foreach($events as $ev): ?>
                <div class="bg-white p-8 rounded-[2.5rem] border border-slate-200 flex flex-col md:flex-row items-center gap-8 shadow-sm hover:border-blue-500 transition-all">
                    <div class="w-20 h-20 bg-blue-50 rounded-2xl flex items-center justify-center text-4xl shrink-0">🏆</div>
                    <div class="flex-1 text-center md:text-left">
                        <h3 class="text-2xl font-black uppercase text-slate-800 leading-tight"><?= $ev['nama_lengkap'] ?></h3>
                        <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mt-2">Silakan pilih kategori dokumen:</p>
                    </div>
                    <div class="flex gap-3 w-full md:w-auto min-w-[380px]">
                        <a href="results.php?event_id=<?= $ev['id'] ?>&cat=StartList" class="flex-1 text-center border-2 border-blue-600 text-blue-600 py-4 rounded-2xl font-black text-[10px] uppercase hover:bg-blue-600 hover:text-white transition tracking-widest">📖 Buku Acara</a>
                        <a href="results.php?event_id=<?= $ev['id'] ?>&cat=Result" class="flex-1 text-center bg-blue-600 text-white py-4 rounded-2xl font-black text-[10px] uppercase hover:bg-blue-700 shadow-lg tracking-widest">🏆 Hasil Lomba</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="max-w-3xl mx-auto">
                <a href="results.php" class="text-blue-600 font-black text-xs uppercase underline mb-8 inline-block tracking-[0.2em]">&larr; Kembali ke Daftar Event</a>
                <div class="bg-slate-900 rounded-[3rem] p-12 text-white mb-10 shadow-2xl relative overflow-hidden">
                    <span class="bg-blue-600 text-[10px] font-black px-4 py-1.5 rounded-full uppercase tracking-widest"><?= $cat ?></span>
                    <h2 class="text-4xl font-black uppercase mt-6 leading-tight"><?= $evName ?></h2>
                    <div class="absolute -right-10 -bottom-10 opacity-10 text-[10rem]">🏊</div>
                </div>
                <div class="space-y-4">
                    <?php if(empty($files)): ?>
                        <div class="bg-white p-24 text-center rounded-[2rem] border-2 border-dashed text-slate-400 font-bold uppercase tracking-widest">Belum ada file di kategori ini.</div>
                    <?php else: ?>
                        <?php foreach($files as $f): ?>
                        <div class="bg-white p-7 rounded-2xl border border-slate-200 flex justify-between items-center group hover:border-blue-500 transition-all">
                            <span class="font-black text-slate-800 uppercase text-sm tracking-tight leading-none flex items-center gap-4"><span class="text-2xl">📄</span> <?= htmlspecialchars($f['file_name']) ?></span>
                            <a href="<?= $f['file_path'] ?>" download class="bg-blue-600 text-white px-8 py-3 rounded-xl font-black text-[10px] uppercase hover:scale-105 transition shadow-lg tracking-widest">Download</a>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer class="bg-[#0F172A] text-white pt-24 pb-12 border-t-4 border-blue-600 text-center">
        <img src="img/logo.png" class="h-24 mx-auto mb-8 grayscale opacity-50">
        <p class="text-slate-500 text-[11px] font-black tracking-[0.5em] uppercase">&copy; 2025 SWIMMEET MANAGER</p>
    </footer>
</body>
</html>