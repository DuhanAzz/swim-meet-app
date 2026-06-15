<?php
// FILE: public/results.php
require_once __DIR__ . '/../src/config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. DATA UMUM (HEADER/FOOTER)
$s = $pdo->query("SELECT * FROM site_settings WHERE id=1")->fetch();
$heroTitle = $s['hero_title'] ?? 'SWIMMEET CHAMPIONSHIP'; 

// 2. LOGIC PENCARIAN & FILTER DATA
$search = $_GET['q'] ?? '';

// Ambil event terbaru yang bukan draft
$sql = "SELECT id, event_name, event_location, event_date_start, event_status, is_result_published 
        FROM events 
        WHERE event_status != 'draft'"; 
$params = [];

if (!empty($search)) {
    $sql .= " AND (event_name LIKE ? OR event_location LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY id DESC"; // Urut Berdasarkan Terbaru

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. AMBIL DOKUMEN (BUKU ACARA / HASIL) DARI TABEL `documents`
$documentsByEvent = [];
if (!empty($events)) {
    $eventIds = array_column($events, 'id');
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    
    // Hanya ambil dokumen yang relevan dengan hasil perlombaan
    $docSql = "SELECT event_id, judul_file, file_path, kategori FROM documents 
               WHERE event_id IN ($placeholders) 
               AND kategori IN ('buku_acara', 'buku_hasil', 'lainnya') 
               ORDER BY kategori ASC";
    $docStmt = $pdo->prepare($docSql);
    $docStmt->execute($eventIds);
    $docs = $docStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($docs as $d) {
        $documentsByEvent[$d['event_id']][] = $d;
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <link rel="icon" type="image/png" href="/public/favicon.png?v=2">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil & Dokumen - <?= htmlspecialchars($heroTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* --- LIQUID PRELOADER --- */
        #preloader { position: fixed; inset: 0; z-index: 9999; background-color: #0F172A; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .loader-container { position: relative; width: 150px; height: 150px; }
        .circle-loader { position: relative; width: 100%; height: 100%; border: 6px solid #1e293b; border-radius: 50%; overflow: hidden; background: #161e31; box-shadow: 0 0 50px rgba(59, 130, 246, 0.2); }
        .liquid { position: absolute; top: 100%; left: -50%; width: 200%; height: 200%; background-color: #3b82f6; border-radius: 40%; animation: wave 4s infinite linear; transition: top 0.3s ease; }
        .liquid::after { content: ''; position: absolute; width: 100%; height: 100%; background-color: rgba(59, 130, 246, 0.6); border-radius: 35%; animation: wave 6s infinite linear; }
        @keyframes wave { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .load-text { margin-top: 30px; color: white; font-weight: 900; letter-spacing: 0.4em; font-size: 12px; text-transform: uppercase; }
        .loader-finish { opacity: 0; visibility: hidden; transition: opacity 0.5s ease, visibility 0.5s; }

        /* --- NAV & HEADER --- */
        #navbar { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); height: 110px; display: flex; align-items: center; }
        #navbar.scrolled { background-color: #0F172A; height: 85px; border-bottom: 1px solid #1e293b; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .nav-link { position: relative; color: white; transition: all 0.3s ease; font-size: 0.95rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; }
        .nav-link::after { content: ''; position: absolute; width: 0; height: 3px; bottom: -8px; left: 0; background-color: #3b82f6; transition: width 0.3s ease; }
        .nav-link:hover::after, .nav-link.active::after { width: 100%; }
        .nav-link:hover { color: #3b82f6; }

        .page-header { background-image: url('https://images.unsplash.com/photo-1519315901367-f34ff9154487?q=80&w=2070&auto=format&fit=crop'); background-size: cover; background-position: center; }
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
                    <a href="results.php" class="nav-link active text-blue-400">Hasil Lomba</a> 
                    <a href="index.php#instruction" class="nav-link text-yellow-400">Panduan</a>
                </div>
                <div class="flex items-center border-l border-white/20 pl-10">
                    <?php if(isset($_SESSION['user_id'])): 
                        $dashLink = '../src/user/dashboard.php';
                        if($_SESSION['role'] == 'master') $dashLink = '../src/master/dashboard.php';
                        if($_SESSION['role'] == 'admin') $dashLink = '../src/admin/dashboard.php';
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
        <div class="absolute inset-0 bg-slate-900/80"></div> 
        <div class="max-w-screen-xl mx-auto px-6 relative z-10 text-center">
            <h1 class="text-4xl md:text-6xl font-black uppercase tracking-tighter text-white italic mb-4 drop-shadow-2xl">
                Hasil Perlombaan
            </h1>
            <p class="text-slate-400 text-sm font-bold uppercase tracking-[0.3em]">
                Live Result Digital & Arsip Buku Hasil
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
                    // Logika Status Badge
                    $rawStatus = strtolower($e['event_status'] ?? 'upcoming');
                    if ($rawStatus == 'open') {
                        $badge = "bg-emerald-500"; $statusText = "OPEN";
                    } elseif ($rawStatus == 'closed' || $rawStatus == 'running') {
                        $badge = "bg-red-600 animate-pulse"; $statusText = "RUNNING";
                    } elseif ($rawStatus == 'done') {
                        $badge = "bg-slate-600"; $statusText = "FINISHED";
                    } else { 
                        $badge = "bg-amber-500"; $statusText = "UPCOMING";
                    }
                    
                    // Cek Dokumen PDF
                    $bukuAcara = null;
                    $bukuHasil = null;
                    if(!empty($documentsByEvent[$e['id']])) {
                        foreach($documentsByEvent[$e['id']] as $doc) {
                            if($doc['kategori'] == 'buku_acara') $bukuAcara = $doc;
                            if($doc['kategori'] == 'buku_hasil') $bukuHasil = $doc;
                        }
                    }
                ?>
                <div class="group bg-white rounded-3xl p-8 border border-slate-200 hover:shadow-2xl hover:border-blue-200 transition-all duration-300 flex flex-col md:flex-row md:items-center justify-between gap-8 relative overflow-hidden">
                    
                    <span class="absolute -right-6 -bottom-10 text-[10rem] font-black text-slate-50 italic select-none pointer-events-none group-hover:text-blue-50 transition">
                        <?= !empty($e['event_date_start']) ? date('d', strtotime($e['event_date_start'])) : '' ?>
                    </span>

                    <div class="flex-1 relative z-10">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="<?= $badge ?> text-white text-[9px] font-black px-3 py-1 rounded-full uppercase tracking-widest shadow-md">
                                <?= $statusText ?>
                            </span>
                            <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                                <?= !empty($e['event_date_start']) ? date('d F Y', strtotime($e['event_date_start'])) : 'TBA' ?>
                            </span>
                        </div>
                        
                        <h3 class="text-2xl md:text-3xl font-black uppercase italic text-slate-800 leading-none mb-3 group-hover:text-blue-600 transition">
                            <?= htmlspecialchars($e['event_name']) ?>
                        </h3>
                        
                        <p class="text-slate-500 font-bold text-xs uppercase flex items-center gap-2">
                            <span>📍</span> <?= htmlspecialchars($e['event_location']) ?>
                        </p>
                    </div>

                    <div class="flex flex-wrap md:flex-nowrap gap-3 relative z-10">
                        
                        <?php if($bukuAcara): ?>
                            <a href="<?= htmlspecialchars($bukuAcara['file_path']) ?>" target="_blank" class="flex items-center gap-3 px-5 py-3 rounded-xl border-2 border-slate-100 hover:border-blue-500 hover:bg-blue-50 transition group/btn min-w-[160px]">
                                <div class="bg-blue-100 text-blue-600 p-2.5 rounded-lg group-hover/btn:bg-blue-600 group-hover/btn:text-white transition text-lg">📋</div>
                                <div class="text-left">
                                    <div class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Download</div>
                                    <div class="text-xs font-bold text-slate-800 uppercase">Buku Acara</div>
                                </div>
                            </a>
                        <?php else: ?>
                            <div class="flex items-center gap-3 px-5 py-3 rounded-xl border border-slate-50 bg-slate-50 opacity-60 cursor-not-allowed min-w-[160px]" title="Buku Acara Belum Tersedia">
                                <div class="bg-slate-200 text-slate-400 p-2.5 rounded-lg text-lg">📋</div>
                                <div class="text-left">
                                    <div class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Belum Ada</div>
                                    <div class="text-xs font-bold text-slate-400 uppercase">Buku Acara</div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if($bukuHasil): ?>
                            <a href="<?= htmlspecialchars($bukuHasil['file_path']) ?>" target="_blank" class="flex items-center gap-3 px-5 py-3 rounded-xl border-2 border-emerald-100 bg-emerald-50/50 hover:border-emerald-500 hover:bg-emerald-100 transition group/btn min-w-[160px]">
                                <div class="bg-emerald-100 text-emerald-600 p-2.5 rounded-lg group-hover/btn:bg-emerald-600 group-hover/btn:text-white transition text-lg">📄</div>
                                <div class="text-left">
                                    <div class="text-[9px] text-emerald-600 font-black uppercase tracking-widest">Download</div>
                                    <div class="text-xs font-bold text-slate-800 uppercase">Buku Hasil</div>
                                </div>
                            </a>
                        <?php endif; ?>

                        <?php if ($e['is_result_published'] == 1): ?>
                            <a href="live_result.php?event_id=<?= $e['id'] ?>" class="flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-slate-900 text-white hover:bg-blue-600 transition shadow-lg min-w-[160px] md:min-w-[200px]">
                                <span class="animate-bounce text-xl">🏆</span>
                                <div class="text-left">
                                    <div class="text-[9px] text-blue-300 font-black uppercase tracking-widest">Real-Time</div>
                                    <div class="text-sm font-bold uppercase">Live Result</div>
                                </div>
                            </a>
                        <?php else: ?>
                            <div class="flex items-center justify-center gap-2 px-6 py-3 rounded-xl border-2 border-slate-100 bg-slate-50 text-slate-400 cursor-not-allowed min-w-[160px] md:min-w-[200px]" title="Live Result belum dipublikasikan panitia">
                                <span class="text-xl opacity-50">🔒</span>
                                <div class="text-left">
                                    <div class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Tertutup</div>
                                    <div class="text-sm font-bold uppercase">Live Result</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-24 border-2 border-dashed border-slate-200 rounded-3xl bg-white shadow-sm">
                <div class="text-6xl mb-4 opacity-50">📭</div>
                <h3 class="text-xl font-black text-slate-800 uppercase italic">Belum Ada Data Hasil</h3>
                <p class="text-slate-400 text-sm font-bold uppercase mt-2 tracking-widest">Hasil kompetisi akan muncul di sini.</p>
            </div>
        <?php endif; ?>

    </main>

    <footer class="bg-[#0F172A] text-white pt-32 pb-16 border-t-4 border-blue-600 text-center mt-auto">
        <div class="max-w-screen-xl mx-auto px-10">
            <img src="img/logo.png" class="h-32 mx-auto mb-16 grayscale opacity-50">
            <p class="text-slate-600 text-[11px] font-black tracking-[0.6em] uppercase">&copy; 2026 SWIMMEET MANAGER. All Rights Reserved.</p>
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
                    progress = 100; clearInterval(interval); 
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
            } else { 
                navbar.classList.remove('scrolled'); 
                if(logo) logo.classList.replace('h-16', 'h-24'); 
            }
        });
    </script>
</body>
</html>