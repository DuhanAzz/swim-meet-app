<?php
// FILE: public/results.php
// 1. KONEKSI DATABASE
require_once __DIR__ . '/../src/config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// 2. DATA UMUM (HEADER/FOOTER)
$s = $pdo->query("SELECT * FROM site_settings WHERE id=1")->fetch();
$heroTitle = $s['hero_title'] ?? 'SWIMMEET CHAMPIONSHIP'; 

// 3. LOGIC PENCARIAN & FILTER DATA (REVISI: Tabel Events)
$search = $_GET['q'] ?? '';

// Ubah query: Ambil dari tabel 'events', bukan 'users'
$sql = "SELECT * FROM events WHERE event_status != 'Draft'"; 
$params = [];

if (!empty($search)) {
    // Revisi kolom pencarian: nama_lengkap -> event_name, location -> event_location
    $sql .= " AND (event_name LIKE ? OR event_location LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Revisi Order: event_start_date -> event_date_start
$sql .= " ORDER BY event_date_start DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil & Dokumen - <?= htmlspecialchars($heroTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* --- LIQUID PRELOADER STYLE --- */
        #preloader { position: fixed; inset: 0; z-index: 9999; background-color: #0F172A; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .loader-container { position: relative; width: 150px; height: 150px; }
        .circle-loader { position: relative; width: 100%; height: 100%; border: 6px solid #1e293b; border-radius: 50%; overflow: hidden; background: #161e31; box-shadow: 0 0 50px rgba(59, 130, 246, 0.2); }
        .liquid { position: absolute; top: 100%; left: -50%; width: 200%; height: 200%; background-color: #3b82f6; border-radius: 40%; animation: wave 4s infinite linear; transition: top 0.3s ease; }
        .liquid::after { content: ''; position: absolute; width: 100%; height: 100%; background-color: rgba(59, 130, 246, 0.6); border-radius: 35%; animation: wave 6s infinite linear; }
        @keyframes wave { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .load-text { margin-top: 30px; color: white; font-weight: 900; letter-spacing: 0.4em; font-size: 12px; text-transform: uppercase; }
        .loader-finish { opacity: 0; visibility: hidden; transition: opacity 0.5s ease, visibility 0.5s; }

        /* --- NAV & HEADER STYLE --- */
        #navbar { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); height: 110px; display: flex; align-items: center; }
        #navbar.scrolled { background-color: #0F172A; height: 85px; border-bottom: 1px solid #1e293b; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .nav-link { position: relative; color: white; transition: all 0.3s ease; font-size: 0.95rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; }
        .nav-link::after { content: ''; position: absolute; width: 0; height: 3px; bottom: -8px; left: 0; background-color: #3b82f6; transition: width 0.3s ease; }
        .nav-link:hover::after, .nav-link.active::after { width: 100%; }
        .nav-link:hover { color: #3b82f6; }

        /* --- PAGE HEADER BG --- */
        .page-header {
            background-image: url('https://images.unsplash.com/photo-1519315901367-f34ff9154487?q=80&w=2070&auto=format&fit=crop'); 
            background-size: cover; background-position: center;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 flex flex-col min-h-screen">

    <div id="preloader">
        <div class="loader-container"><div class="circle-loader"><div class="liquid" id="liquid-level"></div></div></div>
        <div class="load-text">LOADING <span id="load-perc" class="text-blue-500">0%</span></div>
    </div>

    <nav id="navbar" class="fixed w-full z-50 top-0 start-0 transparent px-10">
        <div class="max-w-screen-2xl flex items-center justify-between mx-auto w-full">
            <a href="index.php"><img src="img/logo.png" class="h-24 w-auto object-contain transition-all duration-300" id="nav-logo"></a>
            
            <div class="flex items-center gap-12">
                <div class="hidden lg:flex items-center space-x-10">
                    <a href="index.php" class="nav-link">Home</a>
                    <a href="events.php" class="nav-link">Jadwal Lomba</a>
                    <a href="results.php" class="nav-link active text-blue-400">Hasil & Dokumen</a> <a href="index.php#instruction" class="nav-link text-yellow-400">Panduan</a>
                </div>
                <div class="flex items-center border-l border-white/20 pl-10">
                    <?php if(isset($_SESSION['user_id'])): 
                        // REVISI: Link Dashboard sesuai Role
                        $dashLink = 'dashboard.php';
                        if($_SESSION['role'] == 'master') $dashLink = '../src/master/dashboard.php';
                        if($_SESSION['role'] == 'admin') $dashLink = '../src/admin/dashboard.php';
                        if($_SESSION['role'] == 'user') $dashLink = '../src/user/dashboard.php';
                    ?>
                        <a href="<?= $dashLink ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-10 py-3 rounded-full font-black text-xs uppercase tracking-widest shadow-xl transition transform hover:scale-105">Dashboard</a>
                    <?php else: ?>
                        <a href="login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-10 py-3 rounded-full font-black text-xs uppercase tracking-widest shadow-xl transition transform hover:scale-105">Login / Daftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <header class="page-header relative pt-48 pb-20 overflow-hidden">
        <div class="absolute inset-0 bg-slate-900/80"></div> <div class="max-w-screen-xl mx-auto px-6 relative z-10 text-center">
            <h1 class="text-4xl md:text-6xl font-black uppercase tracking-tighter text-white italic mb-4 drop-shadow-2xl">
                Hasil & Dokumen
            </h1>
            <p class="text-slate-400 text-sm font-bold uppercase tracking-[0.3em]">
                Arsip Lengkap Kejuaraan & Startlist
            </p>
        </div>
    </header>

    <main class="flex-grow py-20 px-6 max-w-screen-xl mx-auto w-full">
        
        <div class="bg-white p-4 rounded-2xl shadow-lg border border-slate-100 mb-12 flex flex-col md:flex-row gap-4 items-center -mt-28 relative z-20">
            <div class="flex-1 w-full">
                <form action="" method="GET" class="flex gap-2">
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama event atau lokasi..." 
                           class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm font-bold rounded-xl px-5 py-4 focus:ring-blue-500 focus:border-blue-500 outline-none uppercase placeholder:normal-case transition">
                    <button type="submit" class="bg-slate-900 hover:bg-blue-600 text-white px-8 rounded-xl font-black uppercase text-xs tracking-widest transition duration-300">
                        Cari
                    </button>
                </form>
            </div>
        </div>

        <?php if(count($events) > 0): ?>
            <div class="grid grid-cols-1 gap-8">
                <?php foreach($events as $e): 
                    // Cek Status untuk Badge
                    $status = $e['event_status'] ?? 'Registration';
                    $statusColor = ($status == 'Finished' || $status == 'Closed') ? 'bg-slate-600' : (($status == 'Running') ? 'bg-red-600 animate-pulse' : 'bg-emerald-500');
                    
                    // Ambil File Dokumen dari tabel event_results
                    $stmtDoc = $pdo->prepare("SELECT file_path, category FROM event_results WHERE event_id = ?");
                    $stmtDoc->execute([$e['id']]);
                    $docs = $stmtDoc->fetchAll();
                    
                    $hasStartlist = false;
                    $hasResult = false;
                    foreach($docs as $d) {
                        if($d['category'] == 'StartList') $hasStartlist = $d['file_path'];
                        if($d['category'] == 'Result') $hasResult = $d['file_path'];
                    }
                ?>
                <div class="group bg-white rounded-3xl p-8 border border-slate-200 hover:shadow-2xl hover:border-blue-200 transition-all duration-300 flex flex-col md:flex-row md:items-center gap-8 relative overflow-hidden">
                    
                    <span class="absolute -right-6 -bottom-10 text-[10rem] font-black text-slate-50 italic select-none pointer-events-none group-hover:text-blue-50 transition"><?= date('d', strtotime($e['event_date_start'])) ?></span>

                    <div class="flex-1 relative z-10">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="<?= $statusColor ?> text-white text-[9px] font-black px-3 py-1 rounded-full uppercase tracking-widest shadow-md">
                                <?= $status ?>
                            </span>
                            <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                                <?= date('d F Y', strtotime($e['event_date_start'])) ?>
                            </span>
                        </div>
                        
                        <h3 class="text-2xl md:text-3xl font-black uppercase italic text-slate-800 leading-none mb-2 group-hover:text-blue-600 transition">
                            <?= htmlspecialchars($e['event_name']) ?>
                        </h3>
                        
                        <p class="text-slate-500 font-bold text-xs uppercase flex items-center gap-1">
                            <span>📍</span> <?= htmlspecialchars($e['event_location']) ?>
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3 relative z-10">
                        <?php if($hasStartlist): ?>
                            <a href="<?= $hasStartlist ?>" target="_blank" class="flex items-center gap-3 px-6 py-4 rounded-xl border-2 border-slate-100 hover:border-blue-500 hover:bg-blue-50 transition group/btn">
                                <div class="bg-blue-100 text-blue-600 p-2 rounded-lg group-hover/btn:bg-blue-600 group-hover/btn:text-white transition">📄</div>
                                <div class="text-left">
                                    <div class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Dokumen</div>
                                    <div class="text-xs font-bold text-slate-800 uppercase">Start List</div>
                                </div>
                            </a>
                        <?php else: ?>
                            <button disabled class="flex items-center gap-3 px-6 py-4 rounded-xl border border-slate-50 bg-slate-50 opacity-50 cursor-not-allowed grayscale">
                                <div class="bg-slate-200 text-slate-400 p-2 rounded-lg">📄</div>
                                <div class="text-left">
                                    <div class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Belum Ada</div>
                                    <div class="text-xs font-bold text-slate-400 uppercase">Start List</div>
                                </div>
                            </button>
                        <?php endif; ?>

                        <?php if($hasResult): ?>
                            <a href="<?= $hasResult ?>" target="_blank" class="flex items-center gap-3 px-6 py-4 rounded-xl border-2 border-emerald-100 bg-emerald-50/50 hover:border-emerald-500 hover:bg-emerald-100 transition group/btn">
                                <div class="bg-emerald-100 text-emerald-600 p-2 rounded-lg group-hover/btn:bg-emerald-600 group-hover/btn:text-white transition">🏆</div>
                                <div class="text-left">
                                    <div class="text-[9px] text-emerald-600 font-black uppercase tracking-widest">Official</div>
                                    <div class="text-xs font-bold text-slate-800 uppercase">Hasil Lomba</div>
                                </div>
                            </a>
                        <?php else: ?>
                             <div class="flex items-center gap-3 px-6 py-4 rounded-xl border border-slate-100 bg-white opacity-60">
                                <div class="bg-slate-100 text-slate-400 p-2 rounded-lg">⏳</div>
                                <div class="text-left">
                                    <div class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Pending</div>
                                    <div class="text-xs font-bold text-slate-400 uppercase">Hasil Lomba</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-20 border-2 border-dashed border-slate-200 rounded-3xl">
                <div class="text-6xl mb-4">📂</div>
                <h3 class="text-xl font-black text-slate-800 uppercase">Data Tidak Ditemukan</h3>
                <p class="text-slate-400 text-sm font-bold uppercase mt-2">Belum ada dokumen yang diunggah.</p>
            </div>
        <?php endif; ?>

    </main>

    <footer class="bg-[#0F172A] text-white pt-32 pb-16 border-t-4 border-blue-600 text-center mt-auto">
        <div class="max-w-screen-xl mx-auto px-10">
            <img src="img/logo.png" class="h-32 mx-auto mb-16 grayscale opacity-50">
            <p class="text-slate-600 text-[11px] font-black tracking-[0.6em] uppercase">&copy; 2025 SWIMMEET MANAGER. All Rights Reserved.</p>
        </div>
    </footer>

    <script>
        // PRELOADER
        document.addEventListener('DOMContentLoaded', () => {
            const liquid = document.getElementById('liquid-level');
            const textPerc = document.getElementById('load-perc');
            const preloader = document.getElementById('preloader');
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.floor(Math.random() * 20) + 10;
                if (progress >= 100) { 
                    progress = 100; 
                    clearInterval(interval); 
                    setTimeout(() => { preloader.classList.add('loader-finish'); }, 400); 
                }
                if(liquid) liquid.style.top = (100 - progress) + '%'; 
                if(textPerc) textPerc.innerText = progress + '%';
            }, 60);
        });

        // NAVBAR SCROLL EFFECT
        const navbar = document.getElementById('navbar');
        const logo = document.getElementById('nav-logo');
        window.addEventListener('scroll', () => { 
            if(window.scrollY > 20) { 
                navbar.classList.add('scrolled'); 
                if(logo) logo.classList.replace('h-24', 'h-16'); 
            } 
            else { 
                navbar.classList.remove('scrolled'); 
                if(logo) logo.classList.replace('h-16', 'h-24'); 
            }
        });
    </script>
</body>
</html>