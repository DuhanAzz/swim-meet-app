<?php
// 1. KONEKSI DATABASE
require_once __DIR__ . '/../src/config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// 2. DATA SETTINGS
$s = $pdo->query("SELECT * FROM site_settings WHERE id=1")->fetch();
$heroTitle   = $s['hero_title'] ?? 'SWIMMEET CHAMPIONSHIP'; 
$runningText = $s['running_text'] ?? ''; // <--- Fitur Running Text
$infoTitle   = $s['info_title'] ?? 'PENDAFTARAN DIBUKA';
$infoText    = $s['info_text'] ?? 'Sistem Manajemen Lomba Renang Terintegrasi - Cepat, Akurat, dan Transparan.';

// 3. SLIDER
$sliders = $pdo->query("SELECT * FROM hero_slides ORDER BY id DESC")->fetchAll();
if (empty($sliders)) $sliders[] = ['image_path' => 'https://images.unsplash.com/photo-1530549387789-4c1017266635'];

// 4. DATA JADWAL (Hanya 4 Event Terbaru)
$upcoming_preview = $pdo->query("SELECT * FROM users WHERE role = 'admin' ORDER BY event_start_date DESC LIMIT 4")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($heroTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .hero-slide { position: absolute; inset: 0; width: 100%; height: 100%; background-size: cover; background-position: center; opacity: 0; transition: opacity 1.5s ease-in-out; z-index: -1; }
        .hero-slide.active { opacity: 1; }
        .hero-overlay { background: linear-gradient(to bottom, rgba(15, 23, 42, 0.85) 0%, rgba(15, 23, 42, 0.4) 50%, rgba(15, 23, 42, 0.9) 100%); }
        
        /* NAVBAR PROPORSI BARU */
        #navbar { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); height: 110px; display: flex; align-items: center; }
        #navbar.scrolled { background-color: #0F172A; height: 90px; border-bottom: 1px solid #1e293b; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        
        .nav-link { 
            position: relative; color: white; transition: all 0.3s ease; 
            font-size: 1rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase;
        }
        .nav-link::after { 
            content: ''; position: absolute; width: 0; height: 3px; 
            bottom: -8px; left: 0; background-color: #3b82f6; transition: width 0.3s ease; 
        }
        .nav-link:hover::after, .nav-link.active::after { width: 100%; }
        .nav-link:hover { color: #3b82f6; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800">

    <nav id="navbar" class="fixed w-full z-50 top-0 start-0 transparent px-10">
        <div class="max-w-screen-2xl flex items-center justify-between mx-auto w-full">
            <a href="#home" class="shrink-0">
                <img src="img/logo.png" class="h-24 w-auto object-contain transition-all duration-300" id="nav-logo">
            </a>

            <div class="flex items-center gap-12">
                <div class="hidden lg:flex items-center space-x-12">
                    <a href="#home" class="nav-link active">Home</a>
                    <a href="events.php" class="nav-link">Jadwal Lomba</a>
                    <a href="results.php" class="nav-link">Hasil & Dokumen</a>
                    <a href="#instruction" class="nav-link text-yellow-400">Panduan</a>
                </div>
                
                <div class="flex items-center border-l border-white/20 pl-12">
                    <?php if(isset($_SESSION['user_id'])): 
                         $dashLink = ($_SESSION['role'] == 'admin') ? '../src/admin/dashboard.php' : '../src/user/dashboard.php';
                    ?>
                        <a href="<?= $dashLink ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-10 py-3.5 rounded-full font-black text-xs uppercase tracking-widest shadow-xl transition transform hover:scale-105">Dashboard</a>
                    <?php else: ?>
                        <a href="login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-10 py-3.5 rounded-full font-black text-xs uppercase tracking-widest shadow-xl transition transform hover:scale-105">Login / Daftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <section id="home" class="h-screen min-h-[850px] flex items-center relative overflow-hidden">
        <div id="slider" class="absolute inset-0">
            <?php foreach($sliders as $index => $slide): ?>
                <div class="hero-slide <?= $index === 0 ? 'active' : '' ?>" style="background-image: url('<?= $slide['image_path'] ?>');"></div>
            <?php endforeach; ?>
            <div class="absolute inset-0 hero-overlay"></div>
        </div>

        <div class="max-w-screen-xl mx-auto px-6 w-full pt-48 relative z-10 text-white">
            <div class="text-white max-w-5xl">
                <div class="inline-flex items-center gap-2 mb-6 text-blue-400">
                    <div class="h-1 w-12 bg-blue-500"></div>
                    <span class="font-bold tracking-[0.3em] uppercase text-xs md:text-sm shadow-black drop-shadow-md">Professional Timing System</span>
                </div>
                
                <h1 class="text-7xl md:text-9xl font-black uppercase tracking-tighter leading-none mb-10 drop-shadow-2xl">
                    <?= htmlspecialchars($heroTitle) ?>
                </h1>

                <?php if(!empty($runningText)): ?>
                <div class="mb-12 w-full md:w-3/4 bg-yellow-400 text-slate-900 px-1 py-1 rounded font-bold overflow-hidden shadow-2xl border-l-8 border-slate-900 transform -skew-x-6">
                    <div class="flex items-center gap-0 bg-yellow-400 skew-x-6">
                        <span class="bg-slate-900 text-yellow-400 text-[10px] md:text-xs px-4 py-2 uppercase tracking-wider shrink-0 font-black z-10 shadow-xl italic">NEWS</span>
                        <div class="flex-1 overflow-hidden py-2 text-slate-900">
                            <marquee class="text-xs md:text-sm uppercase tracking-wide font-black" scrollamount="6"><?= htmlspecialchars($runningText) ?></marquee>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="border-l-4 border-blue-400 pl-8 py-2 mb-12 max-w-3xl bg-gradient-to-r from-black/40 to-transparent">
                    <p class="text-3xl md:text-4xl font-black text-blue-300 mb-2 uppercase italic"><?= htmlspecialchars($infoTitle) ?></p>
                    <p class="text-slate-200 text-sm md:text-xl font-medium leading-relaxed opacity-90"><?= nl2br(htmlspecialchars($infoText)) ?></p>
                </div>

                <div class="flex flex-wrap gap-6">
                    <a href="register.php" class="bg-blue-600 px-12 py-5 rounded-2xl font-black uppercase text-base shadow-2xl hover:bg-blue-700 hover:-translate-y-1 transition tracking-widest">Mulai Daftar</a>
                    <a href="#schedule" class="bg-white/10 backdrop-blur-md border border-white/20 px-12 py-5 rounded-2xl font-black uppercase text-base hover:bg-white hover:text-slate-900 transition tracking-widest">Lihat Jadwal</a>
                </div>
            </div>
        </div>
    </section>

    <section id="schedule" class="py-32 px-6 max-w-screen-xl mx-auto">
        <div class="flex justify-between items-end mb-16">
            <h2 class="text-5xl font-black uppercase italic text-slate-900 tracking-tighter">Competition Preview</h2>
            <a href="events.php" class="text-blue-600 font-bold text-sm uppercase underline tracking-[0.2em]">Lihat Semua Lomba &rarr;</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
            <?php foreach($upcoming_preview as $e): 
                $stmtP = $pdo->prepare("SELECT file_path FROM event_results WHERE event_id = ? AND category = 'Other' LIMIT 1");
                $stmtP->execute([$e['id']]);
                $pathPersyaratan = $stmtP->fetchColumn();
            ?>
            <div class="bg-white rounded-[3rem] border border-slate-200 overflow-hidden hover:shadow-2xl transition-all duration-500 group flex flex-col">
                <div class="p-10 flex-1">
                    <h3 class="text-3xl font-black uppercase text-slate-800 mb-6 group-hover:text-blue-600 transition leading-tight"><?= $e['nama_lengkap'] ?></h3>
                    <div class="flex gap-8 text-slate-500 text-sm font-bold uppercase tracking-widest">
                        <span>📅 <?= date('d M Y', strtotime($e['event_start_date'])) ?></span>
                        <span>📍 <?= $e['location'] ?></span>
                    </div>
                </div>
                <div class="grid grid-cols-3 border-t bg-slate-50">
                    <a href="<?= $pathPersyaratan ? $pathPersyaratan : '#' ?>" <?= $pathPersyaratan ? 'target="_blank"' : '' ?> class="py-6 border-r flex flex-col items-center hover:bg-blue-600 hover:text-white transition uppercase text-[10px] font-black tracking-tighter <?= !$pathPersyaratan ? 'opacity-30 cursor-not-allowed' : '' ?>"><span>📄</span>Persyaratan</a>
                    <a href="results.php?event_id=<?= $e['id'] ?>&cat=StartList" class="py-6 border-r flex flex-col items-center hover:bg-blue-600 hover:text-white transition uppercase text-[10px] font-black tracking-tighter"><span>📖</span>Buku Acara</a>
                    <a href="results.php?event_id=<?= $e['id'] ?>&cat=Result" class="py-6 flex flex-col items-center hover:bg-blue-600 hover:text-white transition uppercase text-[10px] font-black tracking-tighter"><span>🏆</span>Hasil Lomba</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="instruction" class="py-32 bg-white border-t border-slate-100">
        <div class="max-w-screen-xl mx-auto px-6 text-center">
            <span class="text-blue-600 font-black tracking-[0.3em] uppercase text-sm">Flow Registration</span>
            <h2 class="text-5xl md:text-6xl font-black text-slate-800 mt-4 uppercase italic tracking-tighter">How to Join</h2>
            <div class="h-2 w-32 bg-blue-600 mx-auto mt-8 rounded-full"></div>
            
            <div class="grid md:grid-cols-4 gap-12 mt-24">
                <div class="group"><div class="w-24 h-24 mx-auto bg-blue-50 rounded-full flex items-center justify-center border-4 border-white group-hover:bg-blue-600 group-hover:text-white transition duration-500 shadow-2xl mb-8 text-4xl">👤</div><h3 class="text-xl font-black text-slate-800 uppercase">1. Register</h3><p class="text-slate-500 text-sm mt-4 font-medium">Daftarkan akun Klub atau Perkumpulan Anda.</p></div>
                <div class="group"><div class="w-24 h-24 mx-auto bg-blue-50 rounded-full flex items-center justify-center border-4 border-white group-hover:bg-blue-600 group-hover:text-white transition duration-500 shadow-2xl mb-8 text-4xl">🏊</div><h3 class="text-xl font-black text-slate-800 uppercase">2. Input Atlet</h3><p class="text-slate-500 text-sm mt-4 font-medium">Masukkan database Atlet & Waktu Terbaik.</p></div>
                <div class="group"><div class="w-24 h-24 mx-auto bg-blue-50 rounded-full flex items-center justify-center border-4 border-white group-hover:bg-blue-600 group-hover:text-white transition duration-500 shadow-2xl mb-8 text-4xl">💳</div><h3 class="text-xl font-black text-slate-800 uppercase">3. Bayar</h3><p class="text-slate-500 text-sm mt-4 font-medium">Selesaikan pembayaran pendaftaran online.</p></div>
                <div class="group"><div class="w-24 h-24 mx-auto bg-blue-50 rounded-full flex items-center justify-center border-4 border-white group-hover:bg-blue-600 group-hover:text-white transition duration-500 shadow-2xl mb-8 text-4xl">🏆</div><h3 class="text-xl font-black text-slate-800 uppercase">4. Tanding</h3><p class="text-slate-500 text-sm mt-4 font-medium">Bersiap bertanding di lintasan juara.</p></div>
            </div>
        </div>
    </section>

    <footer class="bg-[#0F172A] text-white pt-32 pb-16 border-t-4 border-blue-600">
        <div class="max-w-screen-xl mx-auto px-10">
            <div class="grid md:grid-cols-3 gap-24 mb-24 items-start">
                <div class="flex justify-start"><img src="img/logo.png" alt="Footer Logo" class="h-32 w-auto object-contain"></div>
                <div>
                    <h4 class="font-black text-blue-400 uppercase tracking-widest mb-10 text-sm border-b border-slate-800 pb-3 inline-block">Hubungi Kami</h4>
                    <ul class="space-y-8 text-slate-400">
                        <li class="flex items-start gap-5"><span>📞</span><div class="font-bold text-white text-lg">+62 812 3456 7890<br><span class="text-[10px] text-slate-500 uppercase tracking-widest font-black mt-2 inline-block">Official WhatsApp</span></div></li>
                        <li class="flex items-center gap-5"><span>📧</span><span class="font-bold text-white text-lg">support@swimmeet.id</span></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-black text-blue-400 uppercase tracking-widest mb-10 text-sm border-b border-slate-800 pb-3 inline-block">Navigasi</h4>
                    <ul class="space-y-5 text-sm text-slate-400 font-bold uppercase tracking-widest">
                        <li><a href="events.php" class="hover:text-blue-400 transition flex items-center gap-2"><span>&rarr;</span> Jadwal Lomba</a></li>
                        <li><a href="results.php" class="hover:text-blue-400 transition flex items-center gap-2"><span>&rarr;</span> Hasil & Dokumen</a></li>
                        <li><a href="login.php" class="text-blue-400 hover:text-white transition flex items-center gap-2 font-black"><span>&rarr;</span> Login System</a></li>
                    </ul>
                </div>
            </div>
            <div class="pt-10 border-t border-slate-800 text-center"><p class="text-slate-600 text-[11px] font-black tracking-[0.6em] uppercase">&copy; 2025 SWIMMEET MANAGER.</p></div>
        </div>
    </footer>

    <script>
        const navbar = document.getElementById('navbar');
        const logo = document.getElementById('nav-logo');
        window.addEventListener('scroll', () => { 
            if(window.scrollY > 50) { navbar.classList.add('scrolled'); logo.classList.replace('h-24', 'h-16'); } 
            else { navbar.classList.remove('scrolled'); logo.classList.replace('h-16', 'h-24'); }
        });
        let cur = 0; const slides = document.querySelectorAll('.hero-slide');
        if(slides.length > 1) { setInterval(() => { slides[cur].classList.remove('active'); cur = (cur + 1) % slides.length; slides[cur].classList.add('active'); }, 6000); }
    </script>
</body>
</html>