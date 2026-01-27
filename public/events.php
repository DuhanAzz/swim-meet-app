<?php
// FILE: public/events.php
// 1. KONEKSI & SESSION
require_once __DIR__ . '/../src/config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// 2. LOGIC DATA (REVISI QUERY)
// Mengambil data dari tabel 'events', join ke 'users' untuk ambil foto profil penyelenggara
// Mengubah nama kolom user.photo menjadi 'profile_image' agar HTML di bawah tidak perlu banyak ubahan
$sql = "SELECT 
            e.id, 
            e.event_name, 
            e.event_location, 
            e.event_date_start, 
            e.event_status, 
            u.photo as profile_image 
        FROM events e 
        LEFT JOIN users u ON e.created_by = u.id 
        WHERE e.event_status != 'Draft' 
        ORDER BY e.event_date_start ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper Function: Cek File PDF
function getDoc($pdo, $id, $cat) {
    $st = $pdo->prepare("SELECT file_path FROM event_results WHERE event_id = ? AND category = ? LIMIT 1");
    $st->execute([$id, $cat]);
    return $st->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Lomba - SwimMeet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* --- PRELOADER (Sama dengan Index) --- */
        #preloader { position: fixed; inset: 0; z-index: 9999; background-color: #0F172A; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .loader-container { position: relative; width: 150px; height: 150px; }
        .circle-loader { position: relative; width: 100%; height: 100%; border: 6px solid #1e293b; border-radius: 50%; overflow: hidden; background: #161e31; box-shadow: 0 0 50px rgba(59, 130, 246, 0.2); }
        .liquid { position: absolute; top: 100%; left: -50%; width: 200%; height: 200%; background-color: #3b82f6; border-radius: 40%; animation: wave 4s infinite linear; transition: top 0.3s ease; }
        .liquid::after { content: ''; position: absolute; width: 100%; height: 100%; background-color: rgba(59, 130, 246, 0.6); border-radius: 35%; animation: wave 6s infinite linear; }
        @keyframes wave { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .load-text { margin-top: 30px; color: white; font-weight: 900; letter-spacing: 0.4em; font-size: 12px; text-transform: uppercase; }
        .loader-finish { opacity: 0; visibility: hidden; transition: opacity 0.5s ease, visibility 0.5s; }

        /* --- NAVBAR --- */
        #navbar { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); height: 110px; display: flex; align-items: center; }
        #navbar.scrolled { background-color: #0F172A; height: 85px; border-bottom: 1px solid #1e293b; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .nav-link { position: relative; color: white; transition: all 0.3s ease; font-size: 0.95rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; }
        .nav-link::after { content: ''; position: absolute; width: 0; height: 3px; bottom: -8px; left: 0; background-color: #3b82f6; transition: width 0.3s ease; }
        .nav-link:hover::after, .nav-link.active::after { width: 100%; }
        .nav-link:hover { color: #3b82f6; }

        /* --- PAGE HEADER --- */
        .page-header {
            background-image: url('https://images.unsplash.com/photo-1530549387789-4c1017266635?q=80&w=2070&auto=format&fit=crop'); 
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
                    <a href="events.php" class="nav-link active text-blue-400">Jadwal Lomba</a> <a href="results.php" class="nav-link">Hasil & Dokumen</a>
                    <a href="index.php#instruction" class="nav-link text-yellow-400">Panduan</a>
                </div>
                <div class="flex items-center border-l border-white/20 pl-10">
                    <?php if(isset($_SESSION['user_id'])): 
                        // REVISI LINK DASHBOARD
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
        <div class="absolute inset-0 bg-slate-900/80"></div>
        <div class="max-w-screen-xl mx-auto px-6 relative z-10 text-center">
            <h1 class="text-4xl md:text-6xl font-black uppercase tracking-tighter text-white italic mb-4 drop-shadow-2xl">
                Kalender Event
            </h1>
            <p class="text-slate-400 text-sm font-bold uppercase tracking-[0.3em]">
                Jadwal Kejuaraan Renang Resmi
            </p>
        </div>
    </header>

    <main class="flex-grow py-20 px-6 max-w-screen-xl mx-auto w-full -mt-20 relative z-20">
        
        <?php if(empty($events)): ?>
            <div class="bg-white p-12 rounded-3xl text-center shadow-lg border border-slate-200">
                <div class="text-6xl mb-4">📅</div>
                <h3 class="text-xl font-black text-slate-800 uppercase">Belum ada Jadwal</h3>
                <p class="text-slate-400 font-bold uppercase text-xs mt-2">Silakan cek kembali nanti.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-8">
                <?php foreach($events as $ev): 
                    $pdf = getDoc($pdo, $ev['id'], 'Other'); 
                    
                    // Logic Tampilan Status
                    $status = $ev['event_status'] ?? 'Registration';
                    $isClosed = ($status == 'Finished' || $status == 'Closed');
                    
                    // Warna Badge
                    $badgeColor = 'bg-emerald-500';
                    $badgeText = 'Registration Open';
                    if($status == 'Running') { $badgeColor = 'bg-red-600 animate-pulse'; $badgeText = 'Live Now'; }
                    if($isClosed) { $badgeColor = 'bg-slate-600'; $badgeText = 'Event Closed'; }
                ?>
                
                <div class="bg-white p-8 md:p-10 rounded-[2.5rem] border border-slate-200 shadow-xl shadow-slate-200/50 hover:shadow-2xl hover:border-blue-300 transition-all duration-300 group flex flex-col md:flex-row gap-8 items-start relative overflow-hidden">
                    
                    <div class="absolute -right-6 -top-6 text-[120px] font-black text-slate-50 italic select-none pointer-events-none group-hover:text-blue-50 transition leading-none">
                        <?= date('d', strtotime($ev['event_date_start'])) ?>
                    </div>

                    <div class="w-full md:w-32 md:h-32 bg-slate-100 rounded-3xl flex items-center justify-center text-4xl shrink-0 overflow-hidden shadow-inner border border-slate-100 relative z-10">
                        <?php if(!empty($ev['profile_image'])): ?>
                            <img src="<?= htmlspecialchars($ev['profile_image']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="grayscale opacity-50 text-5xl">🏊</span>
                        <?php endif; ?>
                    </div>

                    <div class="flex-1 relative z-10 w-full">
                        <div class="flex flex-wrap items-center gap-3 mb-3">
                            <span class="<?= $badgeColor ?> text-white text-[9px] font-black px-3 py-1 rounded-full uppercase tracking-widest shadow-md">
                                <?= $badgeText ?>
                            </span>
                            <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                                <?= date('F Y', strtotime($ev['event_date_start'])) ?>
                            </span>
                        </div>
                        
                        <h3 class="text-2xl md:text-3xl font-black uppercase text-slate-800 leading-none mb-3 italic group-hover:text-blue-600 transition">
                            <?= htmlspecialchars($ev['event_name']) ?>
                        </h3>
                        
                        <div class="flex items-center gap-2 text-slate-500 font-bold text-xs uppercase tracking-wider">
                            <span class="text-lg">📍</span> 
                            <span><?= htmlspecialchars($ev['event_location']) ?></span>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto min-w-[200px] relative z-10 mt-4 md:mt-0">
                        
                        <a href="<?= $pdf ?: '#' ?>" target="_blank" class="flex-1 text-center py-4 px-6 rounded-2xl border-2 border-slate-100 text-slate-500 hover:border-blue-500 hover:text-blue-600 hover:bg-blue-50 transition font-black text-[10px] uppercase tracking-widest <?= !$pdf ? 'opacity-40 cursor-not-allowed pointer-events-none' : '' ?>">
                            <?= $pdf ? '📄 Unduh Info' : '📄 Info Belum Ada' ?>
                        </a>

                        <?php if(!$isClosed): ?>
                            <a href="<?= isset($_SESSION['user_id']) ? 'register_event.php?event_id='.$ev['id'] : 'login.php' ?>" class="flex-1 text-center py-4 px-8 rounded-2xl bg-blue-600 text-white shadow-lg shadow-blue-200 hover:bg-blue-700 hover:shadow-xl hover:-translate-y-1 transition font-black text-[10px] uppercase tracking-widest flex items-center justify-center gap-2">
                                <span>📝</span> Daftar
                            </a>
                        <?php else: ?>
                            <button disabled class="flex-1 text-center py-4 px-8 rounded-2xl bg-slate-100 text-slate-400 font-black text-[10px] uppercase tracking-widest cursor-not-allowed flex items-center justify-center gap-2">
                                <span>🏁</span> Selesai
                            </button>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endforeach; ?>
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
        // Preloader
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
                    setTimeout(() => { preloader.classList.add('loader-finish'); }, 500); 
                }
                if(liquid) liquid.style.top = (100 - progress) + '%'; 
                if(textPerc) textPerc.innerText = progress + '%';
            }, 80);
        });

        // Navbar Scroll
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
    </script>
</body>
</html>