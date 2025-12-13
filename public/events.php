<?php
require_once __DIR__ . '/../src/config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$events = $pdo->query("SELECT id, nama_lengkap, location, event_start_date, profile_image FROM users WHERE role = 'admin' ORDER BY event_start_date DESC")->fetchAll();
function getDoc($pdo, $id, $cat) {
    $st = $pdo->prepare("SELECT file_path FROM event_results WHERE event_id = ? AND category = ? LIMIT 1");
    $st->execute([$id, $cat]);
    return $st->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Lomba - SwimMeet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        #navbar { background-color: #0F172A; height: 90px; display: flex; align-items: center; border-bottom: 1px solid #1e293b; }
        .nav-link { position: relative; color: white; transition: 0.3s; font-size: 0.95rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; }
        .nav-link:hover, .nav-link.active { color: #3b82f6; }
        #preloader { position: fixed; inset: 0; z-index: 9999; background-color: #0F172A; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: opacity 0.5s ease, visibility 0.5s; }
        .loader-container { position: relative; width: 120px; height: 120px; }
        .circle-loader { position: relative; width: 100%; height: 100%; border: 4px solid #1e293b; border-radius: 50%; overflow: hidden; background: #161e31; }
        .liquid { position: absolute; top: 100%; left: -50%; width: 200%; height: 200%; background-color: #3b82f6; border-radius: 40%; animation: wave 4s infinite linear; }
        @keyframes wave { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .loader-finish { opacity: 0; visibility: hidden; }
    </style>
</head>
<body class="bg-slate-50 pt-32">
    <div id="preloader"><div class="loader-container"><div class="circle-loader"><div class="liquid" id="liquid-level"></div></div></div><div class="load-text mt-6 text-white font-black tracking-widest text-xs uppercase">LOADING <span id="load-perc">0%</span></div></div>

    <nav id="navbar" class="fixed w-full z-50 top-0 start-0 px-10">
        <div class="max-w-screen-2xl flex items-center justify-between mx-auto w-full">
            <a href="index.php"><img src="img/logo.png" class="h-16 w-auto object-contain"></a>
            <div class="flex items-center gap-12">
                <div class="hidden lg:flex items-center space-x-12">
                    <a href="index.php" class="nav-link">Home</a>
                    <a href="events.php" class="nav-link active">Jadwal Lomba</a>
                    <a href="results.php" class="nav-link">Hasil & Dokumen</a>
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

    <main class="max-w-screen-xl mx-auto px-6 py-12">
        <h1 class="text-5xl font-black uppercase italic mb-12 border-l-[12px] border-blue-600 pl-8 tracking-tighter">Event Schedule</h1>
        <div class="flex flex-col gap-6">
            <?php foreach($events as $ev): $pdf = getDoc($pdo, $ev['id'], 'Other'); ?>
            <div class="bg-white p-8 rounded-[2.5rem] border border-slate-200 flex flex-col md:flex-row items-center gap-8 hover:border-blue-500 transition-all shadow-sm">
                <div class="w-24 h-24 bg-slate-100 rounded-3xl flex items-center justify-center text-4xl shrink-0 overflow-hidden"><?php if($ev['profile_image']): ?> <img src="<?= $ev['profile_image'] ?>" class="w-full h-full object-cover"> <?php else: ?> 🏊 <?php endif; ?></div>
                <div class="flex-1 text-center md:text-left"><h3 class="text-2xl font-black uppercase text-slate-800 leading-tight"><?= $ev['nama_lengkap'] ?></h3><p class="text-slate-500 font-bold text-sm uppercase mt-2 tracking-widest">📅 <?= date('d M Y', strtotime($ev['event_start_date'])) ?> | 📍 <?= $ev['location'] ?></p></div>
                <div class="flex gap-3 w-full md:w-auto min-w-[380px]"><a href="<?= $pdf ?: '#' ?>" target="_blank" class="flex-1 text-center border-2 border-slate-200 py-4 px-6 rounded-2xl font-black text-[10px] uppercase hover:bg-slate-50 transition tracking-widest <?= !$pdf ? 'opacity-30' : '' ?>">📄 PDF Persyaratan</a><a href="login.php" class="flex-1 text-center bg-blue-600 text-white py-4 px-8 rounded-2xl font-black text-[10px] uppercase hover:bg-blue-700 shadow-lg tracking-widest">🎫 Daftar Sekarang</a></div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <footer class="bg-[#0F172A] text-white pt-24 pb-12 mt-20 border-t-4 border-blue-600 text-center"><div class="max-w-screen-xl mx-auto px-10"><img src="img/logo.png" class="h-24 mx-auto mb-8 grayscale opacity-50"><p class="text-slate-500 text-[11px] font-black tracking-[0.5em] uppercase tracking-widest">&copy; 2025 SWIMMEET MANAGER</p></div></footer>
    <script>
        window.addEventListener('load', () => { const liquid = document.getElementById('liquid-level'); const textPerc = document.getElementById('load-perc'); const preloader = document.getElementById('preloader'); let progress = 0; const interval = setInterval(() => { progress += 10; if (progress >= 100) { progress = 100; clearInterval(interval); setTimeout(() => { preloader.classList.add('loader-finish'); }, 400); } liquid.style.top = (100 - progress) + '%'; textPerc.innerText = progress + '%'; }, 80); });
    </script>
</body>
</html>