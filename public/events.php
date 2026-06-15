<?php
// FILE: public/events.php
require_once __DIR__ . '/../src/config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. DATA UMUM & PENGATURAN
$s = $pdo->query("SELECT * FROM site_settings WHERE id=1")->fetch();
$heroTitle = $s['hero_title'] ?? 'SWIMMEET CHAMPIONSHIP'; 

// 2. LOGIC PENCARIAN & FILTER DATA EVENT
$search = $_GET['q'] ?? '';

// Mengurutkan dari event terbaru yang dimasukkan ke database (ORDER BY e.id DESC)
$sql = "SELECT id, event_name, event_location, event_date_start, event_status, pool_type, lane_count, poster_image, logo_left, is_result_published 
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

// 3. AMBIL DOKUMEN (JUKNIS / FORMULIR) UNTUK EVENT-EVENT DI ATAS
$documentsByEvent = [];
if (!empty($events)) {
    $eventIds = array_column($events, 'id');
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    
    $docSql = "SELECT event_id, judul_file, file_path, kategori FROM documents WHERE event_id IN ($placeholders) ORDER BY kategori DESC";
    $docStmt = $pdo->prepare($docSql);
    $docStmt->execute($eventIds);
    $docs = $docStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kelompokkan dokumen berdasarkan event_id
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
    <title>Jadwal Lomba - <?= htmlspecialchars($heroTitle) ?></title>
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

        .page-header { background-image: url('https://images.unsplash.com/photo-1530549387789-4c1017266635?q=80&w=2070&auto=format&fit=crop'); background-size: cover; background-position: center; }
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
                    <a href="events.php" class="nav-link active text-blue-400">Jadwal Lomba</a>
                    <a href="results.php" class="nav-link">Hasil Lomba</a> 
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
                Jadwal Kompetisi
            </h1>
            <p class="text-slate-400 text-sm font-bold uppercase tracking-[0.3em]">
                Kalender Event Renang Terbaru
            </p>
        </div>
    </header>

    <main class="flex-grow py-20 px-6 max-w-screen-xl mx-auto w-full">
        
        <div class="bg-white p-4 rounded-2xl shadow-lg border border-slate-100 mb-16 flex flex-col md:flex-row gap-4 items-center -mt-28 relative z-20">
            <div class="flex-1 w-full">
                <form action="" method="GET" class="flex gap-2">
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama atau lokasi event..." 
                           class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm font-bold rounded-xl px-5 py-4 focus:ring-blue-500 focus:border-blue-500 outline-none uppercase placeholder:normal-case transition">
                    <button type="submit" class="bg-slate-900 hover:bg-blue-600 text-white px-8 rounded-xl font-black uppercase text-xs tracking-widest transition duration-300 shadow-lg">
                        Cari
                    </button>
                </form>
            </div>
        </div>

        <?php if(count($events) > 0): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <?php foreach($events as $e): 
                    // 🚀 LOGIKA STATUS MENGIKUTI STRUKTUR DATABASE BARU
                    $rawStatus = strtolower($e['event_status'] ?? 'upcoming');
                    $isOpen = false;
                    
                    if ($rawStatus == 'open') {
                        $badge = "bg-emerald-500"; $statusText = "OPEN REGISTRATION"; $isOpen = true;
                    } elseif ($rawStatus == 'closed') {
                        $badge = "bg-red-600 animate-pulse"; $statusText = "RUNNING / CLOSED";
                    } elseif ($rawStatus == 'done') {
                        $badge = "bg-slate-600"; $statusText = "FINISHED";
                    } else { // upcoming
                        $badge = "bg-amber-500"; $statusText = "UPCOMING";
                    }
                    
                    $pool = $e['pool_type'] ?? '50m';

                    // Karena kita ada di folder `public/`, path gambar cukup langsung panggil path database
                    $imgSrc = 'https://images.unsplash.com/photo-1530549387789-4c100476466c?w=800&auto=format&fit=crop';
                    if (!empty($e['poster_image'])) $imgSrc = $e['poster_image'];
                    elseif (!empty($e['logo_left'])) $imgSrc = $e['logo_left'];
                ?>
                <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden hover:shadow-2xl transition-all duration-300 group flex flex-col sm:flex-row relative">
                    
                    <div class="absolute top-4 left-4 z-20 <?= $badge ?> text-white px-3 py-1 rounded-full font-black text-[9px] uppercase tracking-widest shadow-lg">
                        <?= $statusText ?>
                    </div>

                    <div class="w-full sm:w-2/5 aspect-[1/1.4] sm:aspect-auto sm:min-h-[350px] bg-slate-900 relative overflow-hidden shrink-0">
                        <img src="<?= htmlspecialchars($imgSrc) ?>" class="w-full h-full object-cover opacity-90 group-hover:opacity-100 group-hover:scale-105 transition-all duration-700">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 sm:bg-gradient-to-r sm:from-transparent sm:to-slate-900/10 to-transparent"></div>
                    </div>
                    
                    <div class="w-full sm:w-3/5 p-6 md:p-8 flex flex-col justify-between relative z-10">
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="bg-slate-100 text-slate-500 text-[9px] font-black px-2 py-0.5 rounded uppercase tracking-widest border border-slate-200">
                                    <?= $pool ?>
                                </span>
                                <span class="bg-slate-100 text-slate-500 text-[9px] font-black px-2 py-0.5 rounded uppercase tracking-widest border border-slate-200">
                                    <?= $e['lane_count'] ?? 8 ?> Lintasan
                                </span>
                            </div>

                            <h3 class="text-2xl font-black uppercase text-slate-800 mb-4 italic leading-tight line-clamp-2">
                                <?= htmlspecialchars($e['event_name']) ?>
                            </h3>
                            <div class="space-y-3 text-slate-500 text-xs font-bold uppercase tracking-wide">
                                <div class="flex items-start gap-3">
                                    <span class="bg-slate-50 border border-slate-100 p-2 rounded-xl text-sm shadow-sm">📅</span> 
                                    <div class="mt-0.5">
                                        <span class="text-slate-700"><?= !empty($e['event_date_start']) ? date('d F Y', strtotime($e['event_date_start'])) : 'TBA' ?></span>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="bg-slate-50 border border-slate-100 p-2 rounded-xl text-sm shadow-sm">📍</span> 
                                    <div class="mt-0.5">
                                        <span class="text-slate-700 line-clamp-2"><?= htmlspecialchars($e['event_location'] ?? 'TBA') ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if(!empty($documentsByEvent[$e['id']])): ?>
                            <div class="mt-5 border-t border-slate-100 pt-4">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">📥 Dokumen & Unduhan:</p>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach($documentsByEvent[$e['id']] as $doc): 
                                        $cat = strtoupper($doc['kategori'] ?? 'LAINNYA');
                                        $btnColor = ($cat == 'JUKNIS') ? 'bg-indigo-50 text-indigo-700 border-indigo-200 hover:bg-indigo-600 hover:text-white' : 'bg-slate-100 text-slate-700 border-slate-200 hover:bg-slate-700 hover:text-white';
                                    ?>
                                        <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="px-2.5 py-1 rounded border text-[9px] font-black tracking-widest uppercase transition-all <?= $btnColor ?>">
                                            📄 <?= htmlspecialchars($doc['judul_file'] ?? $cat) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div>

                        <div class="grid grid-cols-2 gap-3 mt-6">
                            <?php if($isOpen): ?>
                                <a href="login.php" class="py-3 px-2 rounded-xl flex items-center justify-center gap-2 bg-slate-900 text-white hover:bg-blue-600 transition-all uppercase text-[10px] font-black tracking-widest shadow-lg">
                                    <span>✍️</span> Daftar
                                </a>
                            <?php else: ?>
                                <button disabled class="py-3 px-2 rounded-xl border-2 border-slate-100 flex items-center justify-center gap-2 bg-slate-50 text-slate-400 uppercase text-[10px] font-black tracking-widest cursor-not-allowed">
                                    Tertutup
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($e['is_result_published'] == 1): ?>
                                <a href="results.php?event_id=<?= $e['id'] ?>" class="py-3 px-2 rounded-xl flex items-center justify-center gap-2 bg-blue-50 border-2 border-blue-100 text-blue-600 hover:bg-blue-600 hover:text-white transition-all uppercase text-[10px] font-black tracking-widest">
                                    <span class="animate-bounce">🏆</span> Hasil
                                </a>
                            <?php else: ?>
                                <div class="py-3 px-2 rounded-xl flex items-center justify-center gap-2 text-slate-400 uppercase text-[10px] font-black tracking-widest cursor-not-allowed bg-slate-50 border-2 border-slate-100" title="Hasil Belum Dipublikasikan">
                                    <span>🔒</span> Hasil
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-24 border-2 border-dashed border-slate-200 rounded-3xl bg-white shadow-sm">
                <div class="text-6xl mb-4 opacity-50">📅</div>
                <h3 class="text-xl font-black text-slate-800 uppercase italic">Belum Ada Jadwal</h3>
                <p class="text-slate-400 text-sm font-bold uppercase mt-2 tracking-widest">Nantikan event renang selanjutnya!</p>
                <?php if(!empty($search)): ?>
                    <a href="events.php" class="inline-block mt-6 text-blue-600 text-xs font-black uppercase tracking-widest hover:underline">&larr; Tampilkan Semua</a>
                <?php endif; ?>
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
        // PRELOADER & NAVBAR
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