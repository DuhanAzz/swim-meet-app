<?php
// FILE: public/index.php
require_once __DIR__ . '/../src/config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. DATA DATABASE
$stmt = $pdo->query("SELECT * FROM site_settings WHERE id=1");
$s = $stmt->fetch();
if (!$s) $s = [];

// MAINTENANCE CHECK
$isMaintenance = isset($s['maintenance_mode']) && $s['maintenance_mode'] == 1;
$isMaster      = isset($_SESSION['role']) && $_SESSION['role'] === 'master';
if ($isMaintenance && !$isMaster) { /* ... (Kode Maintenance tetap sama) ... */ exit; }

// VARIABLES
$heroTitle    = $s['hero_title'] ?? 'SWIMMEET CHAMPIONSHIP'; 
$runningText  = $s['running_text'] ?? ''; 
$infoTitle    = $s['info_title'] ?? 'How to Join'; 
$infoText     = $s['info_text'] ?? ''; 
$siteDesc     = $s['site_description'] ?? 'Platform manajemen lomba renang modern.';
$contactEmail = $s['contact_email'] ?? 'info@swimmeet.id';
$contactWA    = $s['contact_wa'] ?? '#';
$linkIG       = $s['link_instagram'] ?? '#';

// SLIDER
$sliders = []; 
try { $sliders = $pdo->query("SELECT * FROM hero_slides ORDER BY id DESC")->fetchAll(); } catch (Exception $e) {}
if (empty($sliders)) $sliders[] = ['image_path' => 'https://images.unsplash.com/photo-1530549387789-4c1017266635'];

// PREVIEW JADWAL
$sql = "SELECT e.id, e.event_name, e.event_location, e.event_date_start, e.event_status FROM events e WHERE e.event_status != 'Draft' ORDER BY e.event_date_start ASC LIMIT 4";
$upcoming_preview = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($s['app_name'] ?? 'SwimMeet') ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }
        
        /* NAVBAR STYLE */
        #navbar { transition: background-color 0.3s ease, height 0.3s ease, box-shadow 0.3s ease; height: 90px; }
        #navbar.scrolled { background-color: rgba(15, 23, 42, 0.95); backdrop-filter: blur(10px); height: 70px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        
        .nav-link { position: relative; color: white; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; font-size: 0.9rem; }
        .nav-link::after { content: ''; position: absolute; width: 0; height: 2px; bottom: -5px; left: 0; background-color: #3b82f6; transition: width 0.3s ease; }
        .nav-link:hover::after { width: 100%; }

        /* HERO SLIDER */
        .hero-slide { position: absolute; inset: 0; background-size: cover; background-position: center; opacity: 0; transition: opacity 1.5s ease-in-out; z-index: -1; }
        .hero-slide.active { opacity: 1; }
        .hero-overlay { background: linear-gradient(to bottom, rgba(15, 23, 42, 0.7), rgba(15, 23, 42, 0.3), rgba(15, 23, 42, 0.9)); }

        /* MOBILE MENU (App-Like) */
        #mobile-menu { 
            position: fixed; inset: 0; z-index: 100; 
            background-color: #0F172A; 
            transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex; flex-direction: column; 
        }
        #mobile-menu.open { transform: translateX(0); }
        .mobile-link { opacity: 0; transform: translateY(20px); transition: all 0.4s ease; transition-delay: 0.1s; }
        #mobile-menu.open .mobile-link { opacity: 1; transform: translateY(0); }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-blue-500 selection:text-white">

    <nav id="navbar" class="fixed w-full z-50 top-0 left-0 px-5 md:px-10 flex items-center">
        <div class="w-full max-w-screen-2xl mx-auto flex items-center justify-between">
            
            <a href="index.php" class="relative z-50">
                <img src="img/logo.png" id="nav-logo" class="h-14 md:h-20 w-auto object-contain transition-all duration-300">
            </a>
            
            <div class="hidden lg:flex items-center gap-10">
                <a href="#home" class="nav-link text-blue-400">Home</a>
                <a href="events.php" class="nav-link hover:text-blue-400">Jadwal</a>
                <a href="results.php" class="nav-link hover:text-blue-400">Hasil</a>
                <a href="#instruction" class="nav-link text-yellow-400 hover:text-yellow-300">Panduan</a>
                
                <div class="h-6 w-px bg-white/20 mx-2"></div>

                <?php if(isset($_SESSION['user_id'])): 
                    $dashLink = ($_SESSION['role'] == 'master') ? '../src/master/dashboard.php' : (($_SESSION['role'] == 'admin') ? '../src/admin/dashboard.php' : '../src/user/dashboard.php');
                ?>
                    <a href="<?= $dashLink ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-2.5 rounded-full font-bold text-xs uppercase tracking-widest shadow-lg transition transform hover:scale-105">Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-2.5 rounded-full font-bold text-xs uppercase tracking-widest shadow-lg transition transform hover:scale-105">Login</a>
                <?php endif; ?>
            </div>

            <button id="mobile-toggle" class="lg:hidden relative z-50 p-2 text-white focus:outline-none">
                <svg class="w-8 h-8 drop-shadow-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
            </button>
        </div>
    </nav>

    <div id="mobile-menu">
        <div class="flex items-center justify-between p-6 border-b border-slate-800">
            <span class="text-white font-bold tracking-widest uppercase text-sm">Menu</span>
            <button id="close-menu" class="text-white/70 hover:text-white p-2">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <div class="flex-1 flex flex-col justify-center px-8 gap-8">
            <a href="#home" class="mobile-link text-4xl font-black text-white uppercase tracking-tighter hover:text-blue-500">Home</a>
            <a href="events.php" class="mobile-link text-4xl font-black text-white uppercase tracking-tighter hover:text-blue-500" style="transition-delay: 0.15s">Jadwal</a>
            <a href="results.php" class="mobile-link text-4xl font-black text-white uppercase tracking-tighter hover:text-blue-500" style="transition-delay: 0.2s">Hasil</a>
            <a href="#instruction" class="mobile-link text-4xl font-black text-yellow-400 uppercase tracking-tighter" style="transition-delay: 0.25s">Panduan</a>
            
            <div class="w-20 h-1 bg-blue-600 rounded-full my-4 mobile-link" style="transition-delay: 0.3s"></div>

            <div class="mobile-link" style="transition-delay: 0.35s">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="<?= $dashLink ?>" class="block w-full bg-blue-600 text-center text-white py-4 rounded-xl font-bold uppercase tracking-widest shadow-xl active:scale-95 transition">Buka Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="block w-full bg-blue-600 text-center text-white py-4 rounded-xl font-bold uppercase tracking-widest shadow-xl active:scale-95 transition">Login / Daftar</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="p-8 text-center border-t border-slate-800">
            <p class="text-slate-500 text-[10px] font-bold uppercase tracking-widest">&copy; 2026 SWIMMEET MANAGER</p>
        </div>
    </div>

    <section id="home" class="relative min-h-screen flex items-center pt-20 overflow-hidden">
        <div id="slider" class="absolute inset-0">
            <?php foreach($sliders as $index => $slide): ?>
                <div class="hero-slide <?= $index === 0 ? 'active' : '' ?>" style="background-image: url('<?= $slide['image_path'] ?>');"></div>
            <?php endforeach; ?>
            <div class="absolute inset-0 hero-overlay"></div>
        </div>

        <div class="w-full max-w-screen-xl mx-auto px-6 relative z-10 text-white flex flex-col justify-center h-full pb-20 pt-10">
            <div class="max-w-4xl">
                <div class="inline-flex items-center gap-2 mb-4 text-blue-400">
                    <span class="bg-blue-600 text-white text-[10px] font-black px-2 py-0.5 rounded uppercase tracking-wider">Official</span>
                    <span class="font-bold tracking-[0.2em] uppercase text-xs">Timing System</span>
                </div>
                
                <h1 class="text-5xl md:text-8xl lg:text-9xl font-black uppercase tracking-tighter leading-[0.9] mb-6 drop-shadow-2xl">
                    <?= htmlspecialchars($heroTitle) ?>
                </h1>
                
                <?php if(!empty($runningText)): ?>
                <div class="mb-8 w-full md:w-2/3 bg-yellow-400/90 text-slate-900 px-1 py-1 rounded font-bold shadow-lg border-l-4 border-slate-900 -skew-x-3 backdrop-blur-sm">
                    <div class="flex items-center skew-x-3">
                        <span class="bg-slate-900 text-yellow-400 text-[10px] px-3 py-1 uppercase font-black z-10">INFO</span>
                        <marquee class="text-xs uppercase font-black py-1" scrollamount="5"><?= htmlspecialchars($runningText) ?></marquee>
                    </div>
                </div>
                <?php endif; ?>

                <div class="flex flex-col sm:flex-row gap-4 mt-4 w-full sm:w-auto">
                    <a href="register.php" class="bg-blue-600 text-white px-8 py-4 rounded-xl font-black uppercase text-sm tracking-widest shadow-xl hover:bg-blue-700 active:scale-95 transition text-center">
                        Mulai Daftar
                    </a>
                    <a href="#schedule" class="bg-white/10 backdrop-blur-md border border-white/20 text-white px-8 py-4 rounded-xl font-black uppercase text-sm tracking-widest hover:bg-white hover:text-slate-900 active:scale-95 transition text-center">
                        Lihat Jadwal
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section id="schedule" class="py-24 px-5 max-w-screen-xl mx-auto">
        <div class="flex items-end justify-between mb-12">
            <div>
                <span class="text-blue-600 font-bold tracking-[0.2em] uppercase text-xs">Calendar</span>
                <h2 class="text-3xl md:text-5xl font-black uppercase italic text-slate-900 tracking-tighter leading-none mt-1">Upcoming Events</h2>
            </div>
            <a href="events.php" class="text-slate-400 text-xs font-bold uppercase underline hover:text-blue-600">All Events</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-10">
            <?php foreach($upcoming_preview as $e): 
                $status = $e['event_status'] ?? 'Registration';
                $badge = ($status == 'Running') ? "bg-red-600 animate-pulse" : (($status == 'Finished') ? "bg-slate-600" : "bg-emerald-500");
            ?>
            <div class="group bg-white rounded-3xl border border-slate-200 overflow-hidden hover:shadow-2xl hover:border-blue-200 transition relative flex flex-col">
                <div class="absolute top-4 right-4 z-10 <?= $badge ?> text-white px-3 py-1 rounded-full font-black text-[9px] uppercase tracking-widest shadow-sm"><?= strtoupper($status) ?></div>
                
                <div class="p-8 pb-4 flex-1">
                    <h3 class="text-2xl font-black uppercase text-slate-800 mb-4 italic leading-none group-hover:text-blue-600 transition"><?= htmlspecialchars($e['event_name']) ?></h3>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 text-slate-500 text-xs font-bold uppercase">
                            <span class="w-5 text-center">📅</span> <?= date('d F Y', strtotime($e['event_date_start'])) ?>
                        </div>
                        <div class="flex items-center gap-2 text-slate-500 text-xs font-bold uppercase">
                            <span class="w-5 text-center">📍</span> <?= htmlspecialchars($e['event_location']) ?>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 border-t bg-slate-50 divide-x divide-slate-200">
                    <a href="results.php?event_id=<?= $e['id'] ?>&cat=StartList" class="py-4 flex items-center justify-center gap-2 hover:bg-slate-900 hover:text-white transition uppercase text-[10px] font-black text-slate-600">
                        <span>📖</span> Start List
                    </a>
                    <a href="results.php?event_id=<?= $e['id'] ?>&cat=Result" class="py-4 flex items-center justify-center gap-2 hover:bg-blue-600 hover:text-white transition uppercase text-[10px] font-black text-slate-600">
                        <span>🏆</span> Hasil
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="instruction" class="py-24 bg-white border-t border-slate-100">
        <div class="max-w-screen-xl mx-auto px-6 text-center">
            <span class="text-blue-600 font-black tracking-[0.3em] uppercase text-xs">Guide</span>
            <h2 class="text-4xl md:text-5xl font-black text-slate-800 mt-2 uppercase italic tracking-tighter"><?= htmlspecialchars($infoTitle) ?></h2>
            <p class="mt-6 text-slate-500 text-sm md:text-base font-medium max-w-2xl mx-auto leading-relaxed"><?= nl2br(htmlspecialchars($infoText)) ?></p>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mt-16">
                <div class="p-6 rounded-2xl bg-slate-50 border border-slate-100 hover:border-blue-200 transition">
                    <div class="text-4xl mb-4">👤</div>
                    <h3 class="font-black text-slate-800 uppercase text-sm">1. Register</h3>
                </div>
                <div class="p-6 rounded-2xl bg-slate-50 border border-slate-100 hover:border-blue-200 transition">
                    <div class="text-4xl mb-4">🏊</div>
                    <h3 class="font-black text-slate-800 uppercase text-sm">2. Input Atlet</h3>
                </div>
                <div class="p-6 rounded-2xl bg-slate-50 border border-slate-100 hover:border-blue-200 transition">
                    <div class="text-4xl mb-4">💳</div>
                    <h3 class="font-black text-slate-800 uppercase text-sm">3. Payment</h3>
                </div>
                <div class="p-6 rounded-2xl bg-slate-50 border border-slate-100 hover:border-blue-200 transition">
                    <div class="text-4xl mb-4">🏆</div>
                    <h3 class="font-black text-slate-800 uppercase text-sm">4. Race Day</h3>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-[#0F172A] text-white py-16 border-t-4 border-blue-600">
        <div class="max-w-screen-xl mx-auto px-6 text-center">
            <img src="img/logo.png" class="h-16 mx-auto mb-8 grayscale brightness-200 opacity-50">
            <p class="text-slate-500 text-xs font-bold uppercase tracking-widest">&copy; 2026 SWIMMEET MANAGER</p>
            <div class="flex justify-center gap-6 mt-8">
                <a href="#" class="text-slate-400 hover:text-white transition">Instagram</a>
                <a href="#" class="text-slate-400 hover:text-white transition">WhatsApp</a>
            </div>
        </div>
    </footer>

    <script>
        // NAVBAR SCROLL & LOGO RESIZE
        const navbar = document.getElementById('navbar');
        const logo = document.getElementById('nav-logo');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 20) {
                navbar.classList.add('scrolled');
                logo.classList.replace('h-14', 'h-10');   // Mobile size shrink
                logo.classList.replace('md:h-20', 'md:h-12'); // Desktop size shrink
            } else {
                navbar.classList.remove('scrolled');
                logo.classList.replace('h-10', 'h-14');
                logo.classList.replace('md:h-12', 'md:h-20');
            }
        });

        // MOBILE MENU TOGGLE (APP STYLE)
        const menuBtn = document.getElementById('mobile-toggle');
        const closeBtn = document.getElementById('close-menu');
        const menu = document.getElementById('mobile-menu');
        const links = document.querySelectorAll('.mobile-link');

        function toggleMenu() {
            menu.classList.toggle('open');
            document.body.classList.toggle('overflow-hidden'); // Prevent scrolling when menu is open
        }

        if(menuBtn) menuBtn.addEventListener('click', toggleMenu);
        if(closeBtn) closeBtn.addEventListener('click', toggleMenu);
        links.forEach(l => l.addEventListener('click', toggleMenu));

        // HERO SLIDER
        let cur=0, s=document.querySelectorAll('.hero-slide');
        if(s.length>1) setInterval(()=>{ s[cur].classList.remove('active'); cur=(cur+1)%s.length; s[cur].classList.add('active'); },6000);
    </script>
</body>
</html>