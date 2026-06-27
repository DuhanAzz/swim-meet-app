<?php
// FILE: public/live_result.php
require_once __DIR__ . '/../src/config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// 🚀 TANPA PENGECEKAN LOGIN! Bebas diakses publik.

$event_id = $_GET['event_id'] ?? 0;
$currentMode = $_GET['mode'] ?? 'split';

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

// AMBIL RENTANG UMUR UNTUK FUNGSI KALKULASI SPLIT
$stmtAge = $pdo->prepare("SELECT group_name, min_age, max_age FROM event_age_groups WHERE event_id = ?");
$stmtAge->execute([$event_id]);
$ageGroups = $stmtAge->fetchAll(PDO::FETCH_ASSOC);
$eventYear = date('Y', strtotime($event['event_date_start']));

if (!function_exists('getAgeGroupLabel')) {
    function getAgeGroupLabel($dob, $evtYear, $groups) {
        if(!$dob || $dob == '0000-00-00') return '-';
        $age = $evtYear - (int)date('Y', strtotime($dob));
        foreach($groups as $g) {
            if ($age >= $g['min_age'] && $age <= $g['max_age']) return $g['group_name'];
        }
        return "DILUAR KATEGORI ($age TH)";
    }
}

// 3. MENGAMBIL HASIL (Hanya yang is_published = 1)
$sql = "SELECT en.event_number, en.distance, en.stroke, en.jenis_kelamin, en.age_group,
               s.nama_atlet, c.nama_klub, s.asal_sekolah, s.tanggal_lahir,
               ee.entry_time, 
               es.time_final, es.rank_final, es.is_dq_final, es.dq_reason_final
        FROM event_numbers en
        JOIN event_entries ee ON en.id = ee.category_id
        JOIN event_seeding es ON ee.id = es.entry_id
        JOIN swimmers s ON ee.swimmer_id = s.id
        LEFT JOIN clubs c ON ee.club_id = c.id
        WHERE en.event_id = ? 
          AND en.is_published = 1  
          AND (es.time_final IS NOT NULL OR es.is_dq_final = 1)
        ORDER BY 
            CAST(en.event_number AS UNSIGNED) ASC,
            es.is_dq_final ASC";

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

function timeToMs($time) {
    $time = trim($time);
    if (empty($time) || $time == 'NT' || $time == '99:99.99' || $time == '-') return 9999999999; 
    $parts = preg_split('/[:.]/', $time);
    $menit = 0; $detik = 0; $ms = 0;
    if (count($parts) == 3) { $menit = (int)$parts[0]; $detik = (int)$parts[1]; $ms = (int)$parts[2]; } 
    elseif (count($parts) == 2) { $detik = (int)$parts[0]; $ms = (int)$parts[1]; } 
    elseif (count($parts) == 1) { $detik = (int)$parts[0]; }
    return ($menit * 60000) + ($detik * 1000) + ($ms * 10);
}

// 4. Kelompokkan hasil berdasarkan nomor acara & MODE
$groupedResults = [];
foreach ($results as $r) {
    $r['ms_sort'] = 9999999999;
    if ($r['is_dq_final'] == 1) { $r['ms_sort'] = 9999999999 + 100; }
    elseif (!empty($r['time_final']) && $r['time_final'] != 'NT') { $r['ms_sort'] = timeToMs($r['time_final']); }
    
    // Default Title
    $judulAcara = "ACARA #" . $r['event_number'] . " - " . $r['distance'] . "M " . strtoupper($r['stroke']) . " " . strtoupper($r['jenis_kelamin']) . " (" . $r['age_group'] . ")";
    
    if ($currentMode === 'overall') {
        $judulAcara = "ACARA #" . $r['event_number'] . " - " . $r['distance'] . "M " . strtoupper($r['stroke']) . " " . strtoupper($r['jenis_kelamin']) . " (OVERALL)";
    } else {
        // SPLIT MODE
        if (stripos($r['age_group'], 'GABUNG') !== false) {
            $realKU = getAgeGroupLabel($r['tanggal_lahir'], $eventYear, $ageGroups);
            $judulAcara = "ACARA #" . $r['event_number'] . " - " . $r['distance'] . "M " . strtoupper($r['stroke']) . " " . strtoupper($r['jenis_kelamin']) . " (" . $realKU . ")";
        }
    }

    $groupedResults[$judulAcara][] = $r;
}

// 5. Dynamic Re-Ranking (usort) untuk memastikan setiap grup memiliki rank 1..N
foreach ($groupedResults as &$rows) {
    usort($rows, function($a, $b) {
        if ($a['ms_sort'] == $b['ms_sort']) return 0;
        return ($a['ms_sort'] < $b['ms_sort']) ? -1 : 1;
    });
    
    $rank = 1; $real_rank = 1; $prev_time = null;
    foreach ($rows as &$atlet) {
        $isDQ = ($atlet['is_dq_final'] == 1);
        $isValid = (!$isDQ && !empty($atlet['time_final']) && $atlet['time_final'] != 'NT');
        $atlet['dynamic_rank'] = null;
        if ($isValid) {
            if ($atlet['ms_sort'] !== $prev_time) { $real_rank = $rank; }
            $atlet['dynamic_rank'] = $real_rank;
            $prev_time = $atlet['ms_sort'];
            $rank++;
        }
    }
}
unset($rows);
// Supaya jika di split, array keys yang berubah berantakan bisa dirapihkan
uksort($groupedResults, function($a, $b) {
    // Cari ACARA # angka
    preg_match('/ACARA #(\d+)/', $a, $matchA);
    preg_match('/ACARA #(\d+)/', $b, $matchB);
    $numA = isset($matchA[1]) ? (int)$matchA[1] : 9999;
    $numB = isset($matchB[1]) ? (int)$matchB[1] : 9999;
    
    if ($numA === $numB) {
        return strcmp($a, $b); // sort by KU name if same event
    }
    return $numA < $numB ? -1 : 1;
});

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/public/favicon.png?v=2">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Result - <?= htmlspecialchars($event['event_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,700;0,800;0,900;1,400;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            letter-spacing: -0.01em;
        }
        #navbar { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-logo-item { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        #navbar.scrolled { background-color: rgba(11, 19, 41, 0.95); backdrop-filter: blur(12px); box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5); border-color: rgba(30, 41, 59, 0.5); }
        
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #0b1329; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }

        /* Style animasi rotasi panah akordion */
        .accordion-arrow { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
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
                        <span class="flex items-center gap-1.5">📍 <?= htmlspecialchars($event['event_location']) ?><?= !empty($event['event_city']) ? ' - ' . htmlspecialchars($event['event_city']) : '' ?></span>
                        <span class="flex items-center gap-1.5">📅 <?= date('d F Y', strtotime($event['event_date_start'])) ?></span>
                    </p>
                </div>

                <?php if(!empty($event['logo_left']) && file_exists(__DIR__ . '/' . $event['logo_left'])): ?>
                    <div class="hidden md:block shrink-0 bg-slate-950/40 p-3 rounded-2xl border border-slate-800/60">
                        <img src="<?= htmlspecialchars($event['logo_left']) ?>" alt="Logo Event" class="h-14 w-14 object-contain">
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 items-center justify-between mb-8 sticky top-24 z-40">
            <div class="w-full sm:w-2/3 bg-slate-900/80 backdrop-blur p-2 rounded-full shadow-2xl border border-slate-800 flex items-center gap-2 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-950/50 transition-all">
                <span class="text-lg ml-4 opacity-40">🔍</span>
                <input type="text" id="searchInput" placeholder="Cari nama atlet atau tim..." class="w-full bg-transparent border-none focus:outline-none focus:ring-0 text-sm font-bold text-slate-100 uppercase placeholder:text-slate-500 placeholder:normal-case placeholder:font-medium py-2">
            </div>
            
            <div class="w-full sm:w-1/3 flex justify-end">
                <label for="modeToggle" class="flex items-center cursor-pointer bg-slate-900/80 rounded-full border border-slate-800 shadow-xl backdrop-blur select-none p-1 relative w-56 h-12">
                    <input type="checkbox" id="modeToggle" class="sr-only peer" onchange="toggleMode(this)" <?= $currentMode === 'overall' ? 'checked' : '' ?>>
                    
                    <div class="absolute inset-0 flex items-center justify-between px-6 z-10 pointer-events-none">
                        <span class="text-[11px] font-black uppercase tracking-widest text-slate-400 peer-checked:text-slate-600 transition-colors duration-300">SPLIT KU</span>
                        <span class="text-[11px] font-black uppercase tracking-widest text-slate-600 peer-checked:text-slate-400 transition-colors duration-300">OVERALL</span>
                    </div>
                    
                    <div class="w-1/2 h-full bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full shadow-md transform transition-transform duration-300 ease-in-out peer-checked:translate-x-full flex items-center justify-center z-20 pointer-events-none">
                        <span class="text-[11px] font-black uppercase tracking-widest text-white shadow-sm" id="sliderText">
                            <?= $currentMode === 'overall' ? 'OVERALL' : 'SPLIT KU' ?>
                        </span>
                    </div>
                </label>
            </div>
        </div>

        <?php if (empty($groupedResults)): ?>
            <div class="bg-slate-900/60 p-12 text-center rounded-3xl border border-slate-800 shadow-xl">
                <span class="text-5xl block mb-5 opacity-20">⏳</span>
                <p class="text-sm font-bold text-slate-400 uppercase tracking-widest">Hasil Belum Tersedia</p>
                <p class="text-[10px] text-slate-500 mt-2">Panitia belum menerbitkan hasil resmi untuk nomor perlombaan di event ini.</p>
            </div>
        <?php else: ?>
            <div id="resultContainer" class="space-y-4">
                <?php foreach ($groupedResults as $judul => $atletList): ?>
                    
                    <div class="bg-slate-900 rounded-2xl border border-slate-800 shadow-xl overflow-hidden result-card transition-all duration-300 hover:border-slate-700">
                        
                        <button onclick="toggleAccordion(this)" class="w-full flex items-center justify-between px-5 sm:px-6 py-4 bg-gradient-to-r from-slate-800/40 to-slate-800/5 text-left focus:outline-none transition-colors hover:bg-slate-800/60 group">
                            <h2 class="text-xs sm:text-sm font-black text-white uppercase italic tracking-tight flex items-center gap-3">
                                <span class="w-1.5 h-3 bg-blue-500 rounded-full block group-hover:bg-blue-400 transition-colors"></span>
                                <?= $judul ?>
                            </h2>
                            <svg class="w-5 h-5 text-slate-400 group-hover:text-white accordion-arrow transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        
                        <div class="accordion-body hidden border-t border-slate-800/60 transition-all duration-300">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse min-w-[650px]">
                                    <thead>
                                        <tr class="bg-slate-950/50 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-800">
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
                                                $displayTeam = !empty($atlet['nama_klub']) ? $atlet['nama_klub'] : '-';
                                            }

                                            $rankBadge = '-';
                                            $rankClass = 'text-slate-500';
                                            if ($atlet['dynamic_rank'] !== null) {
                                                $rankBadge = $atlet['dynamic_rank'];
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
                                                <?php 
                                                    // Jika Split, mungkin butuh label KU asli walau ada di tabel gabungan
                                                    if (stripos($atlet['age_group'], 'GABUNG') !== false) {
                                                        echo htmlspecialchars(getAgeGroupLabel($atlet['tanggal_lahir'], $eventYear, $ageGroups));
                                                    } else {
                                                        echo htmlspecialchars($atlet['age_group']);
                                                    }
                                                ?>
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

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // DATA PASAL DQ UNTUK POP-UP
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
                customClass: { popup: 'rounded-2xl', confirmButton: 'rounded-lg font-bold px-6' }
            });
        }

        // FUNGSI TOGGLE KLIK AKORDION (BUKA/TUTUP)
        function toggleAccordion(headerButton) {
            const card = headerButton.closest('.result-card');
            const body = card.querySelector('.accordion-body');
            const arrow = card.querySelector('.accordion-arrow');
            
            body.classList.toggle('hidden');
            
            if (body.classList.contains('hidden')) {
                arrow.style.transform = 'rotate(0deg)';
            } else {
                arrow.style.transform = 'rotate(180deg)';
            }
        }
        
        // FUNGSI TOGGLE MODE OVERALL / SPLIT
        function toggleMode(checkbox) {
            const isOverall = checkbox.checked;
            document.getElementById('sliderText').textContent = isOverall ? 'OVERALL' : 'SPLIT KU';
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('mode', isOverall ? 'overall' : 'split');
            window.location.search = urlParams.toString();
        }

        // NAVBAR SCROLL CONTROL
        const navbar = document.getElementById('navbar');
        const navContainer = document.getElementById('nav-container');
        window.addEventListener('scroll', () => { 
            if(window.scrollY > 50) { 
                navbar.classList.add('scrolled'); 
                if(navContainer) navContainer.classList.replace('h-24', 'h-16');
            } else { 
                navbar.classList.remove('scrolled'); 
                if(navContainer) navContainer.classList.replace('h-16', 'h-24');
            }
        });

        // SCRIPT PENCARIAN REALTIME
        document.getElementById('searchInput')?.addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let cards = document.querySelectorAll('.result-card');

            cards.forEach(card => {
                let rows = card.querySelectorAll('.searchable-row');
                let body = card.querySelector('.accordion-body');
                let arrow = card.querySelector('.accordion-arrow');
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

                if (filter === "") {
                    body.classList.add('hidden');
                    if(arrow) arrow.style.transform = 'rotate(0deg)';
                    card.style.display = '';
                } else {
                    if (cardHasVisibleRow) {
                        card.style.display = '';
                        body.classList.remove('hidden'); 
                        if(arrow) arrow.style.transform = 'rotate(180deg)';
                    } else {
                        card.style.display = 'none'; 
                    }
                }
            });
        });
    </script>
</body>
</html>