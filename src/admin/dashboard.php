<?php
// src/admin/dashboard.php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

// 1. CEK KEAMANAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}
$uid = $_SESSION['user_id'];

// 2. AMBIL EVENT AKTIF (Event terakhir yang dibuat admin ini)
$stmtEvent = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmtEvent->execute([$uid]);
$event = $stmtEvent->fetch(PDO::FETCH_ASSOC);

// Default values jika belum ada event
$eventName = $event['nama_event'] ?? 'Belum Ada Event';
$eventDate = $event['event_start_date'] ?? date('Y-m-d');
$eventId   = $event['id'] ?? 0;

// Tentukan Status Event berdasarkan Tanggal
$today = date('Y-m-d');
if ($eventId == 0) {
    $statusEvent = 'No Event';
    $statusColor = 'bg-gray-100 text-gray-500 border-gray-200';
} elseif ($today < $event['event_start_date']) {
    $statusEvent = 'Persiapan / Registrasi';
    $statusColor = 'bg-blue-50 text-blue-600 border-blue-200';
} elseif ($today >= $event['event_start_date'] && $today <= ($event['event_end_date'] ?? $event['event_start_date'])) {
    $statusEvent = 'SEDANG BERLANGSUNG';
    $statusColor = 'bg-green-50 text-green-600 border-green-200 animate-pulse';
} else {
    $statusEvent = 'Selesai';
    $statusColor = 'bg-slate-100 text-slate-600 border-slate-200';
}

// 3. HITUNG STATISTIK (Hanya untuk Event Ini)
if ($eventId) {
    // A. Statistik Dasar
    $sqlStats = "SELECT 
                    COUNT(DISTINCT ee.swimmer_id) as total_swimmers,
                    COUNT(ee.id) as total_entries,
                    COUNT(DISTINCT ee.club_id) as total_clubs
                 FROM event_entries ee
                 JOIN event_numbers en ON ee.category_id = en.id
                 WHERE en.organizer_id = ?";
    $stmtStats = $pdo->prepare($sqlStats);
    $stmtStats->execute([$uid]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // B. Hitung Progress (% Penyelesaian Lomba)
    // Total Nomor Lomba
    $stmtTotalNum = $pdo->prepare("SELECT COUNT(*) FROM event_numbers WHERE organizer_id = ?");
    $stmtTotalNum->execute([$uid]);
    $totalRaceNum = $stmtTotalNum->fetchColumn();

    // Nomor Lomba yang SUDAH ada Ranking (Selesai)
    $stmtFinished = $pdo->prepare("SELECT COUNT(DISTINCT category_id) FROM event_entries ee 
                                   JOIN event_numbers en ON ee.category_id = en.id 
                                   WHERE en.organizer_id = ? AND ee.final_rank IS NOT NULL");
    $stmtFinished->execute([$uid]);
    $finishedRaceNum = $stmtFinished->fetchColumn();

    $progressPercent = ($totalRaceNum > 0) ? round(($finishedRaceNum / $totalRaceNum) * 100) : 0;

    // C. NEXT RACE TO INPUT (Fitur Pintar)
    // Cari nomor lomba terkecil yang belum ada ranking-nya
    $sqlNext = "SELECT en.id, en.event_number, en.distance, en.stroke, en.jenis_kelamin, en.age_group
                FROM event_numbers en
                LEFT JOIN (
                    SELECT category_id, COUNT(id) as has_rank 
                    FROM event_entries 
                    WHERE final_rank IS NOT NULL 
                    GROUP BY category_id
                ) res ON en.id = res.category_id
                WHERE en.organizer_id = ? AND (res.has_rank IS NULL OR res.has_rank = 0)
                ORDER BY en.event_number ASC LIMIT 1";
    $stmtNext = $pdo->prepare($sqlNext);
    $stmtNext->execute([$uid]);
    $nextRace = $stmtNext->fetch(PDO::FETCH_ASSOC);

    // D. Data Grafik (Top 5 Klub)
    $sqlChart = "SELECT 
                    COALESCE(u.nama_lengkap, 'Unattached') as club_name, 
                    COUNT(ee.id) as entry_count
                 FROM event_entries ee
                 JOIN event_numbers en ON ee.category_id = en.id
                 LEFT JOIN users u ON ee.club_id = u.id
                 WHERE en.organizer_id = ?
                 GROUP BY club_name
                 ORDER BY entry_count DESC LIMIT 5";
    $stmtChart = $pdo->prepare($sqlChart);
    $stmtChart->execute([$uid]);
    $chartData = $stmtChart->fetchAll(PDO::FETCH_ASSOC);
    
    $chartLabels = json_encode(array_column($chartData, 'club_name'));
    $chartValues = json_encode(array_column($chartData, 'entry_count'));

} else {
    // Kosongkan jika belum ada event
    $stats = ['total_swimmers'=>0, 'total_entries'=>0, 'total_clubs'=>0];
    $progressPercent = 0;
    $chartLabels = '[]';
    $chartValues = '[]';
    $nextRace = null;
}

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="mb-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-4xl font-black uppercase tracking-tighter italic text-slate-900">Dashboard</h1>
            <div class="flex items-center gap-3 mt-1">
                <p class="text-sm text-slate-500 font-bold uppercase tracking-widest"><?= htmlspecialchars($eventName) ?></p>
                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border <?= $statusColor ?>">
                    <?= $statusEvent ?>
                </span>
            </div>
        </div>
        <div class="bg-white px-6 py-3 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-3">
            <span class="text-xl">📅</span>
            <span class="text-xs font-black uppercase tracking-widest text-slate-600"><?= date('d F Y') ?></span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        
        <div class="bg-white p-7 rounded-[2.5rem] border border-slate-200 shadow-sm flex items-center justify-between group hover:border-blue-500 transition-all">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Total Atlet</p>
                <h3 class="text-4xl font-black text-slate-900 leading-none"><?= number_format($stats['total_swimmers']) ?></h3>
            </div>
            <div class="w-14 h-14 rounded-3xl bg-blue-50 text-blue-600 flex items-center justify-center text-3xl group-hover:scale-110 transition-transform">🏊</div>
        </div>

        <div class="bg-white p-7 rounded-[2.5rem] border border-slate-200 shadow-sm flex items-center justify-between group hover:border-purple-500 transition-all">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Total Splash</p>
                <h3 class="text-4xl font-black text-slate-900 leading-none"><?= number_format($stats['total_entries']) ?></h3>
            </div>
            <div class="w-14 h-14 rounded-3xl bg-purple-50 text-purple-600 flex items-center justify-center text-3xl group-hover:scale-110 transition-transform">⚡</div>
        </div>

        <div class="bg-white p-7 rounded-[2.5rem] border border-slate-200 shadow-sm flex items-center justify-between group hover:border-emerald-500 transition-all">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Klub/Sekolah</p>
                <h3 class="text-4xl font-black text-slate-900 leading-none"><?= number_format($stats['total_clubs']) ?></h3>
            </div>
            <div class="w-14 h-14 rounded-3xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-3xl group-hover:scale-110 transition-transform">🏰</div>
        </div>

        <div class="bg-white p-7 rounded-[2.5rem] border border-slate-200 shadow-sm group hover:border-orange-500 transition-all relative overflow-hidden">
            <div class="flex justify-between items-start mb-2">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Penyelesaian</p>
                    <h3 class="text-4xl font-black text-slate-900 leading-none"><?= $progressPercent ?>%</h3>
                </div>
                <div class="w-14 h-14 rounded-3xl bg-orange-50 text-orange-600 flex items-center justify-center text-3xl">📊</div>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2 mt-2 overflow-hidden">
                <div class="bg-orange-500 h-2 rounded-full transition-all duration-1000" style="width: <?= $progressPercent ?>%"></div>
            </div>
        </div>

    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-10">
        
        <div class="xl:col-span-2 space-y-8">
            
            <?php if($nextRace): ?>
            <div class="bg-slate-900 rounded-[3rem] p-10 text-white shadow-2xl relative overflow-hidden group">
                <div class="absolute right-0 top-0 opacity-10 transform translate-x-10 -translate-y-10 group-hover:scale-110 transition-transform duration-700">
                    <svg class="w-64 h-64" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm1 11H9v2h2v-2zm0-8H9v6h2V5z"/></svg>
                </div>

                <div class="relative z-10">
                    <div class="flex items-center gap-4 mb-4">
                        <span class="p-2 bg-blue-500 rounded-xl text-xl animate-bounce">🔥</span>
                        <div>
                            <h3 class="text-2xl font-black uppercase italic tracking-tighter">Live Action Required</h3>
                            <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">Ada nomor lomba yang belum diinput</p>
                        </div>
                    </div>
                    
                    <div class="mb-8">
                        <p class="text-blue-400 font-bold text-sm uppercase">Nomor Lomba Selanjutnya:</p>
                        <h2 class="text-4xl md:text-5xl font-black uppercase leading-tight mt-1">
                            #<?= $nextRace['event_number'] ?> <?= $nextRace['distance'] ?>M <?= $nextRace['stroke'] ?>
                        </h2>
                        <p class="text-xl text-slate-300 font-bold mt-2">
                            Kategori: <span class="text-white"><?= $nextRace['age_group'] ?></span> • <span class="text-white"><?= $nextRace['jenis_kelamin']=='L'?'Putra':'Putri' ?></span>
                        </p>
                    </div>
                    
                    <a href="results/input_result.php?category_id=<?= $nextRace['id'] ?>" class="inline-flex items-center gap-3 bg-blue-600 text-white px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-blue-500 hover:scale-105 transition shadow-lg shadow-blue-900/50">
                        <span>✍️ Input Hasil Sekarang</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-emerald-600 rounded-[3rem] p-10 text-white shadow-xl flex items-center gap-6">
                <div class="text-6xl">🎉</div>
                <div>
                    <h2 class="text-3xl font-black uppercase italic tracking-tight">Semua Selesai!</h2>
                    <p class="font-bold opacity-90 mt-1">Tidak ada nomor lomba yang pending. Semua hasil telah diinput.</p>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-[2.5rem] p-8 border border-slate-200 shadow-sm">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-black text-slate-800 uppercase text-sm tracking-widest">Top 5 Tim Terbanyak</h3>
                    <span class="text-xs font-bold text-slate-400">Berdasarkan Entry</span>
                </div>
                <div class="h-64 w-full">
                    <canvas id="clubChart"></canvas>
                </div>
            </div>

        </div>

        <div class="bg-white rounded-[3rem] p-10 border border-slate-200 shadow-sm flex flex-col h-full">
            <h3 class="text-xl font-black uppercase text-slate-800 mb-2 italic tracking-tight">Menu Pintas</h3>
            <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-8">Akses cepat manajemen lomba</p>
            
            <div class="space-y-4 flex-1">
                <a href="seeding/index.php" class="flex items-center gap-4 p-4 bg-slate-50 border border-slate-100 rounded-2xl hover:bg-slate-100 transition group">
                    <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center text-xl shadow-sm group-hover:scale-110 transition">📊</div>
                    <div>
                        <div class="font-black text-slate-800 text-[10px] uppercase tracking-widest">Atur Seeding</div>
                        <div class="text-[9px] text-slate-500 font-bold uppercase italic">Penentuan Lintasan</div>
                    </div>
                </a>

                <a href="seeding/print_full_book.php" class="flex items-center gap-4 p-4 bg-slate-50 border border-slate-100 rounded-2xl hover:bg-slate-100 transition group">
                    <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center text-xl shadow-sm group-hover:scale-110 transition">📖</div>
                    <div>
                        <div class="font-black text-slate-800 text-[10px] uppercase tracking-widest">Buku Acara</div>
                        <div class="text-[9px] text-slate-500 font-bold uppercase italic">Print Startlist</div>
                    </div>
                </a>

                <a href="results/medal_tally.php" class="flex items-center gap-4 p-4 bg-yellow-50 border border-yellow-100 rounded-2xl hover:bg-yellow-100 transition group">
                    <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center text-xl shadow-sm group-hover:scale-110 transition">🥇</div>
                    <div>
                        <div class="font-black text-slate-800 text-[10px] uppercase tracking-widest">Klasemen</div>
                        <div class="text-[9px] text-yellow-600 font-bold uppercase italic">Medal Tally</div>
                    </div>
                </a>

                <a href="results/export_all_results.php" class="flex items-center gap-4 p-4 bg-blue-50 border border-blue-100 rounded-2xl hover:bg-blue-100 transition group">
                    <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center text-xl shadow-sm group-hover:scale-110 transition">🖨️</div>
                    <div>
                        <div class="font-black text-slate-800 text-[10px] uppercase tracking-widest">Hasil Lengkap</div>
                        <div class="text-[9px] text-blue-600 font-bold uppercase italic">Cetak Hasil Akhir</div>
                    </div>
                </a>
            </div>
            
            <div class="mt-8 pt-8 border-t border-slate-100">
                 <a href="events/index.php" class="flex items-center justify-center w-full py-3 bg-slate-800 text-white rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-slate-700 transition">
                    Kelola Nomor Lomba
                 </a>
            </div>
        </div>
    </div>

</div>

<script>
    const ctx = document.getElementById('clubChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= $chartLabels ?>,
            datasets: [{
                label: 'Jumlah Atlet (Splash)',
                data: <?= $chartValues ?>,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(249, 115, 22, 0.8)',
                    'rgba(139, 92, 246, 0.8)',
                    'rgba(236, 72, 153, 0.8)'
                ],
                borderRadius: 8,
                barThickness: 30
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, grid: { display: false } },
                x: { grid: { display: false } }
            }
        }
    });
</script>