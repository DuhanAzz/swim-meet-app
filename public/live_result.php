<?php
// FILE: public/live_result.php
require_once __DIR__ . '/../src/config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// 🚀 TANPA PENGECEKAN LOGIN! Bebas diakses publik.

$event_id = $_GET['event_id'] ?? 0;

// 1. DATA SETTING GLOBAL (Untuk Logo & Judul persis seperti Index)
$s = $pdo->query("SELECT * FROM site_settings WHERE id=1")->fetch();
$heroTitle = $s['hero_title'] ?? 'SWIMMEET CHAMPIONSHIP'; 

// 2. AMBIL INFO EVENT
$stmtEvt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtEvt->execute([$event_id]);
$event = $stmtEvt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "<script>alert('Event tidak ditemukan.'); window.location.href='results.php';</script>";
    exit;
}

// Cek Tipe Partisipasi
$partType = strtolower($event['participation_type'] ?? 'club');
$isSchoolEvent = (strpos($partType, 'school') !== false || strpos($partType, 'sekolah') !== false);
$teamHeaderLabel = $isSchoolEvent ? 'SEKOLAH' : 'KLUB / TIM';

$groupedResults = [];

// 3. MENGAMBIL HASIL (Hanya yang is_published = 1)
$sql = "SELECT en.event_number, en.distance, en.stroke, en.jenis_kelamin, en.age_group,
               s.nama_atlet, c.nama_klub, s.asal_sekolah,
               ee.entry_time, 
               es.time_final, es.rank_final, es.is_dq_final, es.dq_reason_final
        FROM event_numbers en
        JOIN event_entries ee ON en.id = ee.category_id
        JOIN event_seeding es ON ee.id = es.entry_id
        JOIN swimmers s ON ee.swimmer_id = s.id
        LEFT JOIN clubs c ON s.club_id = c.id
        WHERE en.event_id = ? 
          AND en.is_published = 1  
          AND (es.time_final IS NOT NULL OR es.is_dq_final = 1)
        ORDER BY 
            CAST(en.event_number AS UNSIGNED) ASC,
            es.is_dq_final ASC,
            es.rank_final ASC";

$stmtRes = $pdo->prepare($sql);
$stmtRes->execute([$event_id]);
$results = $stmtRes->fetchAll(PDO::FETCH_ASSOC);

// --- 🌟 FASE 4: AMBIL DATA PASAL DQ UNTUK POP-UP ---
$stmtDqRules = $pdo->query("SELECT pasal, deskripsi FROM dq_rules");
$dqRulesArray = [];
while ($row = $stmtDqRules->fetch(PDO::FETCH_ASSOC)) {
    $dqRulesArray[$row['pasal']] = $row['deskripsi'];
}
$dqRulesJson = json_encode($dqRulesArray);
// ---------------------------------------------------

// Kelompokkan hasil berdasarkan nomor acara
foreach ($results as $r) {
    $judulAcara = "ACARA #" . $r['event_number'] . " - " . $r['distance'] . "M " . strtoupper($r['stroke']) . " " . strtoupper($r['jenis_kelamin']) . " (" . $r['age_group'] . ")";
    $groupedResults[$judulAcara][] = $r;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/png" href="/public/favicon.png?v=2">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Result - <?= htmlspecialchars($event['event_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,700;0,800;0,900;1,400;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <!-- 🌟 Tambahan SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            letter-spacing: -0.01em;
        }
        /* Style transisi navbar & logo sepasang (set) persis seperti index */
        #navbar { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-logo-item { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        #navbar.scrolled { background-color: rgba(11, 19, 41, 0.95); backdrop-filter: blur(12px); box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5); border-color: rgba(30, 41, 59, 0.5); }
        
        /* Penyesuaian Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #0b1329; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }
    </style>
</head>
<body class="bg-[#0b1329] min-h-screen pb-24 text-slate-100">

   <nav id="navbar" class="fixed w-full z-50 top-0 start-0 transparent px-10">
        <div class="max-w-screen-2xl flex items-center justify-between mx-auto w-full">
            <a href="index.php"><img src="img/logo.png" class="h-24 w-auto object-contain transition-all duration-300" id="nav-logo"></a>
            
            <div class="flex justify-between items-center h-24 transition-all duration-300" id="nav-container">
                <div>
                    <a href="results.php" class="inline-flex items-center gap-2 px-4 py-2 border border-slate-700/60 hover:border-blue-500 rounded-xl bg-slate-900/80 text-xs font-black text-slate-300 hover:text-white uppercase tracking-widest transition-all shadow-md">
                        &larr; Kembali
                    </a>
                </div>

            </div>
        </div>
    </nav>

    <div class="max-w-5xl mx-auto p-4 sm:p-6 lg:p-8 pt-32">
        
        <div class="bg-gradient-to-br from-slate-900 via-[#111c44] to-[#0b1329] text-white p-6 sm:p-8 rounded-[2rem] shadow-2xl mb-8 relative overflow-hidden border border-slate-800/80">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(59,130,246,0.08),transparent_45%)]"></div>
            
            <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="inline-block px-3 py-1 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-[9px] font-black uppercase tracking-widest rounded-full shadow-md shadow-blue-500/20 animate-pulse">🔴 LIVE RESULT</span>
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Hasil Resmi</span>
                    </div>
                    <h1 class="text-xl sm:text-3xl font-black uppercase italic leading-tight mb-2 tracking-tight text-white">
                        <?= htmlspecialchars($event['event_name']) ?>
                    </h1>
                    <p class="text-xs text-blue-300 font-bold uppercase tracking-widest flex items-center gap-x-4 gap-y-1 flex-wrap opacity-90">
                        <span class="flex items-center gap-1.5">📍 <?= htmlspecialchars($event['event_location']) ?></span>
                        <span class="flex items-center gap-1.5">📅 <?= date('d F Y', strtotime($event['event_date_start'])) ?></span>
                    </p>
                </div>

                <?php if(!empty($event['logo_left']) && file_exists('../' . $event['logo_left'])): ?>
                    <div class="hidden md:block shrink-0 bg-slate-950/40 p-3 rounded-2xl border border-slate-800/60">
                        <img src="../<?= htmlspecialchars($event['logo_left']) ?>" alt="Logo Event" class="h-14 w-14 object-contain">
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-slate-900/80 backdrop-blur p-2 rounded-full shadow-2xl border border-slate-800 mb-8 flex items-center gap-2 sticky top-24 z-40 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-950/50 transition-all">
            <span class="text-lg ml-4 opacity-40">🔍</span>
            <input type="text" id="searchInput" placeholder="Cari nama atlet atau tim..." class="w-full bg-transparent border-none focus:outline-none focus:ring-0 text-sm font-bold text-slate-100 uppercase placeholder:text-slate-500 placeholder:normal-case placeholder:font-medium py-2">
        </div>

        <?php if (empty($groupedResults)): ?>
            <div class="bg-slate-900/60 p-12 text-center rounded-3xl border border-slate-800 shadow-xl">
                <span class="text-5xl block mb-5 opacity-20">⏳</span>
                <p class="text-sm font-bold text-slate-400 uppercase tracking-widest">Hasil Belum Tersedia</p>
                <p class="text-[10px] text-slate-500 mt-2">Panitia belum menerbitkan hasil resmi untuk nomor perlombaan di event ini.</p>
            </div>
        <?php else: ?>
            <div id="resultContainer" class="space-y-8">
                <?php foreach ($groupedResults as $judul => $atletList): ?>
                    
                    <div class="bg-slate-900 rounded-3xl border border-slate-800 shadow-xl overflow-hidden result-card transition-all duration-300 hover:border-slate-700/70">
                        
                        <div class="bg-gradient-to-r from-slate-800/50 to-slate-800/10 px-5 sm:px-6 py-4 border-b border-slate-800/60">
                            <h2 class="text-xs sm:text-sm font-black text-white uppercase italic tracking-tight flex items-center gap-2">
                                <span class="w-1.5 h-3 bg-blue-500 rounded-full block"></span>
                                <?= $judul ?>
                            </h2>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse min-w-[650px]">
                                <thead>
                                    <tr class="bg-slate-950/80 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-800">
                                        <th class="py-4 px-4 w-14 text-center">Rank</th>
                                        <th class="py-4 px-4">Nama Atlet</th>
                                        <th class="py-4 px-4 text-center w-20">KU</th>
                                        <th class="py-4 px-4"><?= $teamHeaderLabel ?></th>
                                        <th class="py-4 px-4 text-center w-28 text-slate-500">Wkt. Daftar</th>
                                        <th class="py-4 px-4 text-right w-28">Wkt. Final</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/40">
                                    <?php 
                                    foreach ($atletList as $atlet): 
                                        $isDQ = ($atlet['is_dq_final'] == 1);
                                        if ($isSchoolEvent) {
                                            $displayTeam = !empty($atlet['asal_sekolah']) ? $atlet['asal_sekolah'] : '-';
                                        } else {
                                            $displayTeam = !empty($atlet['nama_klub']) ? $atlet['nama_klub'] : 'UNATTACHED';
                                        }

                                        $rankBadge = '-';
                                        $rankClass = 'text-slate-500';
                                        if (!$isDQ && !empty($atlet['rank_final'])) {
                                            $rankBadge = $atlet['rank_final'];
                                            if($rankBadge == 1) { $rankBadge = '🥇 1'; $rankClass = 'text-amber-400'; }
                                            elseif($rankBadge == 2) { $rankBadge = '🥈 2'; $rankClass = 'text-slate-300'; }
                                            elseif($rankBadge == 3) { $rankBadge = '🥉 3'; $rankClass = 'text-orange-400'; }
                                        }

                                        $waktuDaftar = $atlet['entry_time'];
                                        if (empty($waktuDaftar) || $waktuDaftar === '00:00.00' || $waktuDaftar === '00:00:00') {
                                            $waktuDaftar = 'NT';
                                        }
                                    ?>
                                    <tr class="searchable-row hover:bg-slate-800/30 transition-colors">
                                        <td class="py-3.5 px-4 text-center text-xs font-bold <?= $rankClass ?>">
                                            <?= $rankBadge ?>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <span class="text-xs sm:text-sm font-extrabold uppercase athlete-name text-slate-100 tracking-tight">
                                                <?= htmlspecialchars($atlet['nama_atlet']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-center text-[10px] font-black uppercase tracking-widest text-slate-400">
                                            <?= htmlspecialchars($atlet['age_group']) ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-[10px] font-bold uppercase tracking-widest team-name text-slate-400">
                                            <?= htmlspecialchars($displayTeam) ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono text-xs text-slate-600 font-medium">
                                            <?= htmlspecialchars($waktuDaftar) ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-mono text-sm font-black text-white">
                                            <?php if($isDQ): 
                                                $reason = $atlet['dq_reason_final'] ?? 'DQ';
                                                if (in_array($reason, ['DNS', 'DNF'])):
                                            ?>
                                                <span class="text-slate-400 text-[10px] font-black px-2 py-1 bg-slate-800 border border-slate-700 rounded uppercase tracking-wider font-sans">
                                                    <?= htmlspecialchars($reason) ?>
                                                </span>
                                            <?php else: ?>
                                                <!-- 🌟 FASE 4: TOMBOL BUTTON DQ YANG DAPAT DIKLIK -->
                                                <button onclick="showDqDetail('<?= htmlspecialchars($reason) ?>')" class="bg-red-950/80 text-red-400 border border-red-900 hover:bg-red-900 transition-colors px-2 py-1 rounded text-[10px] uppercase cursor-pointer inline-flex items-center justify-end gap-1 ml-auto shadow-sm animate-pulse font-sans tracking-wider">
                                                    ⚠️ DQ
                                                </button>
                                            <?php endif; ?>
                                            <?php else: ?>
                                                <span>
                                                    <?= htmlspecialchars($atlet['time_final']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // 🌟 DATA PASAL DQ UNTUK POP-UP
        const dqRulesData = <?= $dqRulesJson ?>;

        function showDqDetail(pasal) {
            let deskripsi = dqRulesData[pasal] || "Penjelasan detail untuk pasal ini belum tersedia di sistem.";
            
            Swal.fire({
                title: '<span class="text-red-600 font-black italic">DISKUALIFIKASI!</span>',
                html: `
                    <div class="text-left mt-2 p-4 bg-slate-50 border border-slate-200 rounded-lg">
                        <div class="mb-2">
                            <span class="bg-red-100 border border-red-300 text-red-700 font-black px-2 py-1 rounded text-xs">
                                ${pasal}
                            </span>
                        </div>
                        <p class="text-slate-700 text-sm font-medium leading-relaxed font-sans">
                            ${deskripsi}
                        </p>
                    </div>
                `,
                icon: 'warning',
                iconColor: '#ef4444',
                confirmButtonText: 'Tutup',
                confirmButtonColor: '#3b82f6',
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'rounded-lg font-bold px-6'
                }
            });
        }

        const navbar = document.getElementById('navbar');
        const logoItems = document.querySelectorAll('.nav-logo-item');
        const navContainer = document.getElementById('nav-container');

        window.addEventListener('scroll', () => { 
            if(window.scrollY > 50) { 
                navbar.classList.add('scrolled'); 
                if(navContainer) navContainer.classList.replace('h-24', 'h-16');
                logoItems.forEach(logo => {
                    logo.classList.replace('h-24', 'h-16');
                });
            } 
            else { 
                navbar.classList.remove('scrolled'); 
                if(navContainer) navContainer.classList.replace('h-16', 'h-24');
                logoItems.forEach(logo => {
                    logo.classList.replace('h-16', 'h-24');
                });
            }
        });

        // SCRIPT PENCARIAN REALTIME
        document.getElementById('searchInput')?.addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let cards = document.querySelectorAll('.result-card');

            cards.forEach(card => {
                let rows = card.querySelectorAll('.searchable-row');
                let cardHasVisibleRow = false;

                rows.forEach(row => {
                    let athleteName = row.querySelector('.athlete-name').textContent.toLowerCase();
                    let teamName = row.querySelector('.team-name').textContent.toLowerCase();

                    if (athleteName.includes(filter) || teamName.includes(filter)) {
                        row.style.display = '';
                        cardHasVisibleRow = true;
                    } else {
                        row.style.display = 'none';
                    }
                });
                card.style.display = cardHasVisibleRow ? '' : 'none';
            });
        });
    </script>
</body>
</html>