<?php
require_once __DIR__ . '/../src/config/database.php';

// 1. AMBIL PENGATURAN DARI DATABASE MASTER
$s = $pdo->query("SELECT * FROM site_settings WHERE id=1")->fetch();

// 2. SUSUN SLIDE BERDASARKAN DATA DB
$slides = [];

// Cek Slide 1 (Utama)
if ($s && !empty($s['hero_image'])) {
    $slides[] = [
        'img'   => $s['hero_image'], 
        'title' => $s['hero_title'] ?? 'SET SYSTEM', 
        'sub'   => $s['hero_subtitle'] ?? ''
    ];
}

// Cek Slide 2
if ($s && !empty($s['hero_image_2'])) {
    $slides[] = [
        'img'   => $s['hero_image_2'], 
        'title' => $s['hero_title_2'] ?? '', 
        'sub'   => $s['hero_subtitle_2'] ?? ''
    ];
}

// Cek Slide 3
if ($s && !empty($s['hero_image_3'])) {
    $slides[] = [
        'img'   => $s['hero_image_3'], 
        'title' => $s['hero_title_3'] ?? '', 
        'sub'   => $s['hero_subtitle_3'] ?? ''
    ];
}

// Fallback: Jika Master belum atur gambar sama sekali, pakai Default
if (empty($slides)) {
    $slides[] = [
        'img'   => '', // Kosong = Gradient Default
        'title' => 'SELAMAT DATANG', 
        'sub'   => 'Sistem Manajemen Lomba Renang Profesional'
    ];
}

// 3. AMBIL DATA EVENT/LOMBA
$search = $_GET['q'] ?? '';
$sql = "SELECT u.*, (SELECT COUNT(*) FROM events WHERE user_id = u.id) as total_nomor 
        FROM users u 
        WHERE u.role = 'admin' AND u.nama_lengkap LIKE ? 
        ORDER BY u.event_start_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(["%$search%"]);
$events = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/public/favicon.png?v=2">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Result - SET System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col">

    <nav class="bg-[#0F172A] text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-3">
                    <img src="img/logo.png" class="h-10 w-auto" onerror="this.style.display='none'">
                    <div>
                        <h1 class="text-xl font-black tracking-widest">SET SYSTEM</h1>
                        <p class="text-[10px] text-cyan-400 font-bold tracking-wider">LIVE RESULT CENTER</p>
                    </div>
                </div>
                <div class="flex items-center">
                    <a href="login.php" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg text-sm font-bold transition">Login</a>
                </div>
            </div>
        </div>
    </nav>

    <div id="hero-carousel" class="relative w-full h-[500px] bg-slate-900" data-carousel="slide">
        
        <div class="relative h-full overflow-hidden">
            <?php foreach($slides as $index => $slide): ?>
            <div class="hidden duration-1000 ease-in-out" data-carousel-item>
                
                <?php if(!empty($slide['img'])): ?>
                    <img src="<?= $slide['img'] ?>?t=<?= time() ?>" class="absolute block w-full h-full object-cover opacity-50" alt="Banner Slide">
                <?php else: ?>
                    <div class="absolute block w-full h-full bg-gradient-to-br from-slate-800 via-blue-900 to-slate-900 opacity-90"></div>
                <?php endif; ?>
                
                <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-4 z-20">
                    <h2 class="text-4xl md:text-6xl font-black text-white uppercase tracking-tight drop-shadow-lg mb-4">
                        <?= htmlspecialchars($slide['title']) ?>
                    </h2>
                    <p class="text-blue-100 text-lg md:text-xl font-medium max-w-2xl drop-shadow-md mb-8 bg-black/20 px-4 py-1 rounded backdrop-blur-sm">
                        <?= htmlspecialchars($slide['sub']) ?>
                    </p>
                    
                    <?php if($index == 0): ?>
                    <form class="w-full max-w-lg flex gap-2 animate-fade-in-up">
                        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama kompetisi..." class="flex-1 px-5 py-3 rounded-xl text-slate-800 font-bold focus:outline-none focus:ring-4 focus:ring-blue-400 shadow-xl">
                        <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-blue-500 transition shadow-xl">Cari</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if(count($slides) > 1): ?>
        <div class="absolute z-30 flex space-x-3 -translate-x-1/2 bottom-5 left-1/2">
            <?php foreach($slides as $i => $s): ?>
                <button type="button" class="w-3 h-3 rounded-full bg-white/50 hover:bg-white" aria-current="<?= $i==0?'true':'false' ?>" aria-label="Slide <?= $i+1 ?>" data-carousel-slide-to="<?= $i ?>"></button>
            <?php endforeach; ?>
        </div>
        
        <button type="button" class="absolute top-0 start-0 z-30 flex items-center justify-center h-full px-4 cursor-pointer group focus:outline-none" data-carousel-prev>
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/20 group-hover:bg-white/40 group-focus:ring-4 group-focus:ring-white">
                <svg class="w-4 h-4 text-white rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 1 1 5l4 4"/></svg>
            </span>
        </button>
        <button type="button" class="absolute top-0 end-0 z-30 flex items-center justify-center h-full px-4 cursor-pointer group focus:outline-none" data-carousel-next>
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/20 group-hover:bg-white/40 group-focus:ring-4 group-focus:ring-white">
                <svg class="w-4 h-4 text-white rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/></svg>
            </span>
        </button>
        <?php endif; ?>
    </div>

    <div class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 w-full">
        <div class="flex items-center gap-4 mb-8">
            <div class="h-10 w-2 bg-blue-600 rounded-full"></div>
            <h3 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Daftar Kompetisi</h3>
        </div>

        <?php if(empty($events)): ?>
            <div class="text-center py-20 bg-white rounded-2xl border border-dashed border-slate-300">
                <div class="text-6xl mb-4">📭</div>
                <h3 class="text-xl font-bold text-slate-700">Tidak ada kompetisi ditemukan.</h3>
                <p class="text-slate-500">Coba kata kunci lain atau kembali nanti.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach($events as $e): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-xl transition group duration-300 transform hover:-translate-y-1">
                    <div class="h-40 bg-slate-800 relative overflow-hidden">
                        <?php if(!empty($e['photo'])): ?>
                            <img src="<?= $e['photo'] ?>" class="w-full h-full object-cover opacity-60 group-hover:scale-105 transition duration-700">
                        <?php else: ?>
                            <div class="absolute inset-0 bg-gradient-to-br from-blue-600 to-slate-900 opacity-90"></div>
                        <?php endif; ?>
                        
                        <div class="absolute bottom-0 left-0 w-full p-5 bg-gradient-to-t from-black/90 to-transparent">
                            <h3 class="font-black text-lg text-white uppercase leading-tight line-clamp-2"><?= htmlspecialchars($e['nama_lengkap']) ?></h3>
                            <p class="text-xs text-blue-300 flex items-center gap-1 mt-1 font-bold">
                                📍 <?= htmlspecialchars($e['location'] ?? 'Indonesia') ?>
                            </p>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="flex justify-between items-center text-sm mb-6">
                            <?php if($e['event_start_date'] > date('Y-m-d')): ?>
                                <span class="bg-blue-50 text-blue-700 font-bold px-3 py-1 rounded-full text-[10px] uppercase border border-blue-100">Segera Datang</span>
                            <?php else: ?>
                                <span class="bg-green-50 text-green-700 font-bold px-3 py-1 rounded-full text-[10px] uppercase border border-green-100 flex items-center gap-1">
                                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> Live / Selesai
                                </span>
                            <?php endif; ?>
                            <span class="text-slate-500 font-bold text-xs">
                                📅 <?= date('d M Y', strtotime($e['event_start_date'])) ?>
                            </span>
                        </div>
                        <a href="../src/results/print_result.php?event_id=<?= $e['id'] ?>" class="block w-full text-center bg-slate-900 hover:bg-blue-600 text-white font-bold py-3 rounded-xl transition shadow-lg">
                            Lihat Hasil Lengkap
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="bg-slate-900 text-slate-500 py-8 text-center text-sm mt-auto border-t border-slate-800">
        &copy; <?= date('Y') ?> SET System. All rights reserved.
    </footer>

</body>
</html>
