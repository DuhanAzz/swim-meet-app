<?php
// FILE: public/index.php
require_once __DIR__ . '/../src/config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. AMBIL PENGATURAN DARI DATABASE
$stmt = $pdo->query("SELECT * FROM site_settings WHERE id=1");
$s = $stmt->fetch();
if (!$s) $s = [];

// ============================================================
// 🚧 LOGIKA MAINTENANCE MODE (SATPAM)
// ============================================================
$isMaintenance = isset($s['maintenance_mode']) && $s['maintenance_mode'] == 1;
$isMaster      = isset($_SESSION['role']) && $_SESSION['role'] === 'master';

if ($isMaintenance && !$isMaster) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sedang Dalam Perbaikan</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;900&display=swap" rel="stylesheet">
        <link rel="icon" type="image/png" href="<?= BASE_URL ?>/public/favicon.png?v=2">
    </head>
    <body class="bg-slate-900 h-screen flex flex-col items-center justify-center text-center p-6 font-['Inter']">
        <div class="bg-slate-800 p-10 rounded-3xl shadow-2xl border border-slate-700 max-w-lg w-full">
            <div class="text-6xl mb-6">🚧</div>
            <h1 class="text-3xl font-black text-white uppercase tracking-tighter mb-4">Under Maintenance</h1>
            <p class="text-slate-400 text-sm leading-relaxed mb-8">
                Sistem <b><?= htmlspecialchars($s['app_name'] ?? 'SwimMeet') ?></b> sedang dalam perbaikan. 
                Silakan kembali lagi nanti.
            </p>
            <div class="mt-8 pt-8 border-t border-slate-700">
                <a href="login.php" class="text-blue-500 hover:text-blue-400 text-xs font-bold uppercase tracking-widest hover:underline">Login Admin</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit; 
}
// ============================================================

// DATA HALAMAN UTAMA
$heroTitle    = $s['hero_title'] ?? 'SWIMMEET CHAMPIONSHIP'; 
$runningText  = $s['running_text'] ?? ''; 
$infoTitle    = $s['info_title'] ?? 'How to Join'; 
$infoText     = $s['info_text'] ?? ''; 
$siteDesc     = $s['site_description'] ?? 'Platform manajemen lomba renang modern.';

// Kontak
$contactEmail = $s['contact_email'] ?? 'info@swimmeet.id';
$contactWA    = $s['contact_wa'] ?? '#';
$linkIG       = $s['link_instagram'] ?? '#';
$linkFB       = $s['link_facebook'] ?? '#';

// SLIDER GAMBAR
$sliders = []; 
try { $sliders = $pdo->query("SELECT * FROM hero_slides ORDER BY id DESC")->fetchAll(); } 
catch (Exception $e) {}
if (empty($sliders)) $sliders[] = ['image_path' => 'https://images.unsplash.com/photo-1530549387789-4c1017266635'];

// 🚀 PERBAIKAN: PREVIEW JADWAL (4 Event TERBARU)
$sql = "SELECT e.id, e.event_name, e.event_location, e.event_city, e.event_date_start, e.event_status, e.poster_image, e.logo_left, e.is_result_published 
        FROM events e 
        WHERE e.event_status != 'Draft' 
        ORDER BY e.id DESC 
        LIMIT 4";
$upcoming_preview = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($s['app_name'] ?? 'SET System') ?> - Swim Meet</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/public/favicon.png?v=2">
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

        /* --- HERO SLIDER STYLE --- */
        .hero-slide { position: absolute; inset: 0; width: 100%; height: 100%; background-size: cover; background-position: center; opacity: 0; transition: opacity 1.5s ease-in-out; z-index: -1; }
        .hero-slide.active { opacity: 1; }
        .hero-overlay { background: linear-gradient(to bottom, rgba(15, 23, 42, 0.85), rgba(15, 23, 42, 0.4), rgba(15, 23, 42, 0.9)); }
    </style>
</head>
<body class="bg-slate-50 text-slate-800">

    <div id="preloader">
        <div class="loader-container"><div class="circle-loader"><div class="liquid" id="liquid-level"></div></div></div>
        <div class="load-text">LOADING <span id="load-perc" class="text-blue-500">0%</span></div>
    </div>

    <nav id="navbar" class="fixed w-full z-50 top-0 start-0 transparent px-10">
        <div class="max-w-screen-2xl flex items-center justify-between mx-auto w-full">
            <a href="index.php"><img src="img/logo.png" class="h-24 w-auto object-contain transition-all duration-300" id="nav-logo"></a>
            
            <div class="flex items-center gap-12">
                <div class="hidden lg:flex items-center space-x-10">
                    <a href="#home" class="nav-link active text-blue-400">Home</a>
                    <a href="events.php" class="nav-link">Jadwal Lomba</a>
                    <a href="results.php" class="nav-link">Hasil Lomba</a> 
                    <a href="#instruction" class="nav-link text-yellow-400">Panduan</a>
                </div>
                <div class="flex items-center border-l border-white/20 pl-10">
                    <?php if(isset($_SESSION['user_id'])): 
                        $dashLink = BASE_URL . '/src/user/dashboard.php';
                        if($_SESSION['role'] == 'master') $dashLink = BASE_URL . '/src/master/dashboard.php';
                        if($_SESSION['role'] == 'admin') $dashLink = BASE_URL . '/src/admin/dashboard.php';
                    ?>
                        <a href="<?= $dashLink ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-10 py-3 rounded-full font-black text-xs uppercase tracking-widest shadow-xl transition transform hover:scale-105">Dashboard</a>
                    <?php else: ?>
                        <a href="login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-10 py-3 rounded-full font-black text-xs uppercase tracking-widest shadow-xl transition transform hover:scale-105">Login / Daftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <section id="home" class="h-screen min-h-[850px] flex items-center relative overflow-hidden">
        <div id="slider" class="absolute inset-0">
            <?php foreach($sliders as $index => $slide): 
                $slideImg = (strpos($slide['image_path'], 'http') === 0) ? $slide['image_path'] : ltrim($slide['image_path'], '/');
            ?>
                <div class="hero-slide <?= $index === 0 ? 'active' : '' ?>" style="background-image: url('<?= htmlspecialchars($slideImg) ?>');"></div>
            <?php endforeach; ?>
            <div class="absolute inset-0 hero-overlay"></div>
        </div>
        <div class="max-w-screen-xl mx-auto px-6 w-full pt-48 relative z-10 text-white">
            <div class="max-w-5xl">
                <div class="inline-flex items-center gap-2 mb-6 text-blue-400">
                    <div class="h-1 w-12 bg-blue-500"></div><span class="font-bold tracking-[0.3em] uppercase text-xs md:text-sm">Professional Timing System</span>
                </div>
                <h1 class="text-7xl md:text-9xl font-black uppercase tracking-tighter leading-none mb-10 drop-shadow-2xl"><?= htmlspecialchars($heroTitle) ?></h1>
                
                <?php if(!empty($runningText)): ?>
                <div class="mb-12 w-full md:w-3/4 bg-yellow-400 text-slate-900 px-1 py-1 rounded font-bold overflow-hidden shadow-2xl border-l-8 border-slate-900 transform -skew-x-6">
                    <div class="flex items-center bg-yellow-400 skew-x-6">
                        <span class="bg-slate-900 text-yellow-400 text-xs px-4 py-2 uppercase font-black z-10">NEWS</span>
                        <div class="flex-1 overflow-hidden py-2">
                            <marquee class="text-sm uppercase font-black" scrollamount="6"><?= htmlspecialchars($runningText) ?></marquee>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="flex gap-6 mt-10">
                    <a href="register.php" class="bg-blue-600 px-12 py-5 rounded-2xl font-black uppercase shadow-2xl hover:bg-blue-700 transition tracking-widest">Mulai Daftar Klub</a>
                    <a href="#schedule" class="bg-white/10 backdrop-blur-md border border-white/20 px-12 py-5 rounded-2xl font-black uppercase hover:bg-white hover:text-slate-900 transition tracking-widest">Lihat Kompetisi Terbaru</a>
                </div>
            </div>
        </div>
    </section>

    <section id="schedule" class="py-32 px-6 max-w-screen-xl mx-auto section-scroll">
        <div class="flex flex-col md:flex-row md:justify-between md:items-end mb-16 gap-6">
            <div>
                <span class="text-blue-600 font-black tracking-[0.3em] uppercase text-sm mb-2 block">Upcoming Action</span>
                <h2 class="text-5xl font-black uppercase italic text-slate-900 tracking-tighter">Competition Preview</h2>
            </div>
            <a href="events.php" class="inline-flex items-center gap-2 bg-slate-900 text-white px-6 py-3 rounded-full font-black uppercase text-xs tracking-widest hover:bg-blue-600 transition shadow-lg">
                Jelajahi Semua Lomba &rarr;
            </a>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <?php foreach($upcoming_preview as $e): 
                $status = $e['event_status'] ?? 'Registration';
                $badge = ($status == 'Running') ? "bg-red-600 animate-pulse" : (($status == 'Finished') ? "bg-slate-600" : "bg-emerald-500");
                
                // Prioritas gambar: poster -> logo -> default
                $imgSrc = 'https://images.unsplash.com/photo-1530549387789-4c100476466c?w=800&auto=format&fit=crop';
                if (!empty($e['poster_image'])) {
                    $imgSrc = ltrim($e['poster_image'], '/');
                } elseif (!empty($e['logo_left'])) {
                    $imgSrc = ltrim($e['logo_left'], '/');
                }
            ?>
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden hover:shadow-2xl transition-all duration-300 group flex flex-col sm:flex-row relative">
                
                <div class="absolute top-4 left-4 z-20 <?= $badge ?> text-white px-3 py-1 rounded-full font-black text-[9px] uppercase tracking-widest shadow-lg">
                    <?= strtoupper($status) ?>
                </div>

                <div class="w-full sm:w-2/5 aspect-[1/1.4] sm:aspect-auto sm:min-h-[350px] bg-slate-900 relative overflow-hidden shrink-0">
                    <img src="<?= htmlspecialchars($imgSrc) ?>" class="w-full h-full object-cover opacity-90 group-hover:opacity-100 group-hover:scale-105 transition-all duration-700">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 sm:bg-gradient-to-r sm:from-transparent sm:to-slate-900/10 to-transparent"></div>
                </div>
                
                <div class="w-full sm:w-3/5 p-6 md:p-8 flex flex-col justify-between relative z-10">
                    <div>
                        <h3 class="text-2xl font-black uppercase text-slate-800 mb-5 italic leading-tight line-clamp-2">
                            <?= htmlspecialchars($e['event_name']) ?>
                        </h3>
                        <div class="space-y-4 text-slate-500 text-xs font-bold uppercase tracking-wide">
                            <div class="flex items-start gap-3">
                                <span class="bg-slate-50 border border-slate-100 p-2.5 rounded-xl text-lg shadow-sm">📅</span> 
                                <div class="mt-1">
                                    <span class="block text-[9px] text-slate-400 mb-0.5">Tanggal Pelaksanaan</span>
                                    <span class="text-slate-700 text-sm"><?= date('d F Y', strtotime($e['event_date_start'])) ?></span>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <span class="bg-slate-50 border border-slate-100 p-2.5 rounded-xl text-lg shadow-sm">📍</span> 
                                <div class="mt-1">
                                    <span class="block text-[9px] text-slate-400 mb-0.5">Lokasi / Kolam</span>
                                    <span class="text-slate-700 text-sm line-clamp-2"><?= htmlspecialchars($e['event_location']) ?><?= !empty($e['event_city']) ? ' - ' . htmlspecialchars($e['event_city']) : '' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mt-8">
                        <a href="events.php?id=<?= $e['id'] ?>" class="py-3.5 px-2 rounded-xl border-2 border-slate-100 flex items-center justify-center gap-2 hover:border-slate-800 hover:bg-slate-800 hover:text-white transition-all uppercase text-[10px] font-black tracking-widest text-slate-600">
                            <span>📖</span> Info Lomba
                        </a>
                        
                        <?php if ($e['is_result_published'] == 1): ?>
                            <a href="results.php?event_id=<?= $e['id'] ?>" class="py-3.5 px-2 rounded-xl flex items-center justify-center gap-2 bg-blue-50 border-2 border-blue-100 text-blue-600 hover:bg-blue-600 hover:text-white transition-all uppercase text-[10px] font-black tracking-widest">
                                <span class="animate-bounce">🏆</span> Hasil
                            </a>
                        <?php else: ?>
                            <div class="py-3.5 px-2 rounded-xl flex items-center justify-center gap-2 text-slate-400 uppercase text-[10px] font-black tracking-widest cursor-not-allowed bg-slate-50 border-2 border-slate-100" title="Hasil Belum Dipublikasikan">
                                <span>🔒</span> Tertutup
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="instruction" class="py-32 bg-white border-t border-slate-100 section-scroll">
        <div class="max-w-screen-xl mx-auto px-6 text-center">
            <span class="text-blue-600 font-black tracking-[0.3em] uppercase text-sm">Flow Registration</span>
            <h2 class="text-5xl md:text-6xl font-black text-slate-800 mt-4 uppercase italic tracking-tighter"><?= htmlspecialchars($infoTitle) ?></h2>

            <?php if(!empty($infoText)): ?>
                <p class="mt-6 text-slate-500 font-medium max-w-2xl mx-auto leading-relaxed"><?= nl2br(htmlspecialchars($infoText)) ?></p>
            <?php endif; ?>

            <div class="h-2 w-32 bg-blue-600 mx-auto mt-8 rounded-full"></div>
            
            <div class="grid md:grid-cols-4 gap-12 mt-24">
                <div class="group"><div class="w-24 h-24 mx-auto bg-blue-50 rounded-full flex items-center justify-center border-4 border-white group-hover:bg-blue-600 group-hover:text-white transition shadow-2xl mb-8 text-4xl">👤</div><h3 class="text-xl font-black text-slate-800 uppercase">1. Register</h3></div>
                <div class="group"><div class="w-24 h-24 mx-auto bg-blue-50 rounded-full flex items-center justify-center border-4 border-white group-hover:bg-blue-600 group-hover:text-white transition shadow-2xl mb-8 text-4xl">🏊</div><h3 class="text-xl font-black text-slate-800 uppercase">2. Input Atlet</h3></div>
                <div class="group"><div class="w-24 h-24 mx-auto bg-blue-50 rounded-full flex items-center justify-center border-4 border-white group-hover:bg-blue-600 group-hover:text-white transition shadow-2xl mb-8 text-4xl">💳</div><h3 class="text-xl font-black text-slate-800 uppercase">3. Bayar</h3></div>
                <div class="group"><div class="w-24 h-24 mx-auto bg-blue-50 rounded-full flex items-center justify-center border-4 border-white group-hover:bg-blue-600 group-hover:text-white transition shadow-2xl mb-8 text-4xl">🏆</div><h3 class="text-xl font-black text-slate-800 uppercase">4. Tanding</h3></div>
            </div>
        </div>
    </section>

    <footer class="bg-[#0F172A] text-white pt-20 pb-10 border-t-4 border-blue-600">
        <div class="max-w-screen-xl mx-auto px-6 grid grid-cols-1 md:grid-cols-3 gap-12 mb-16 text-center md:text-left">
            <div>
                <img src="img/logo.png" class="h-16 mx-auto md:mx-0 mb-6 grayscale brightness-200 opacity-80">
                <p class="text-slate-400 text-sm leading-relaxed font-medium">
                    <?= nl2br(htmlspecialchars($siteDesc)) ?>
                </p>
            </div>
            <div>
                <h4 class="font-black text-sm uppercase tracking-widest text-blue-500 mb-6">Hubungi Kami</h4>
                <ul class="space-y-4 text-sm text-slate-300 font-bold">
                    <li><a href="mailto:<?= htmlspecialchars($contactEmail) ?>">📧 <?= htmlspecialchars($contactEmail) ?></a></li>
                    <li><a href="https://wa.me/<?= htmlspecialchars($contactWA) ?>" target="_blank">📱 WhatsApp Support</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-black text-sm uppercase tracking-widest text-blue-500 mb-6">Ikuti Update</h4>
                <div class="flex justify-center md:justify-start gap-4">
                    <a href="<?= htmlspecialchars($linkIG) ?>" target="_blank" class="group relative w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center overflow-hidden transition hover:-translate-y-1 shadow-lg">
                        <div class="absolute inset-0 bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-600 opacity-0 group-hover:opacity-100 transition"></div>
                        <svg class="w-5 h-5 text-white z-10" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 014.43 3.014c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z" clip-rule="evenodd" /></svg>
                    </a>
                </div>
            </div>
        </div>
        <div class="border-t border-slate-800 pt-10 text-center"><p class="text-slate-600 text-[10px] font-black tracking-[0.3em] uppercase">&copy; 2026 SWIMMEET MANAGER. All Rights Reserved.</p></div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const liquid = document.getElementById('liquid-level');
            const textPerc = document.getElementById('load-perc');
            const preloader = document.getElementById('preloader');
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.floor(Math.random() * 15) + 5; 
                if (progress >= 100) { 
                    progress = 100; 
                    clearInterval(interval); 
                    setTimeout(() => { preloader.classList.add('loader-finish'); }, 500); 
                }
                if(liquid) liquid.style.top = (100 - progress) + '%'; 
                if(textPerc) textPerc.innerText = progress + '%';
            }, 80);
        });

        // NAVBAR SCROLL (LOGIC BARU)
        const navbar = document.getElementById('navbar');
        const logo = document.getElementById('nav-logo');
        window.addEventListener('scroll', () => { 
            if(window.scrollY > 50) { 
                navbar.classList.add('scrolled'); 
                if(logo) logo.classList.replace('h-24', 'h-16'); 
            } 
            else { 
                navbar.classList.remove('scrolled'); 
                if(logo) logo.classList.replace('h-16', 'h-24'); 
            }
        });

        // SLIDER (LOGIC LAMA)
        let cur=0, s=document.querySelectorAll('.hero-slide');
        if(s.length>1) setInterval(()=>{ s[cur].classList.remove('active'); cur=(cur+1)%s.length; s[cur].classList.add('active'); },6000);
    </script>
</body>
</html>