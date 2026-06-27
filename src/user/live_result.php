<?php
// FILE: src/user/live_result.php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../public/login.php"); exit;
}
$user_id = $_SESSION['user_id'];
$event_id = $_GET['event_id'] ?? 0;

// 🚀 PERBAIKAN 1: AUTO-DETEKSI EVENT (Untuk akses dari Sidebar)
// Jika tidak ada ID yang dikirim, cari event terbaru yang sudah punya Live Result
if ($event_id == 0) {
    $stmtFind = $pdo->query("SELECT e.id FROM events e JOIN event_numbers en ON e.id = en.event_id WHERE en.is_published = 1 ORDER BY e.event_date_start DESC LIMIT 1");
    $event_id = $stmtFind->fetchColumn() ?: 0;
}

// 🚀 PERBAIKAN 2: BYPASS GEMBOK UTAMA
// Hapus syarat "is_result_published = 1" karena keamanan sudah diatur per-nomor lomba di bawah.
$stmtEvt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtEvt->execute([$event_id]);
$event = $stmtEvt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "<script>alert('Belum ada Live Result yang tersedia untuk saat ini.'); window.location.href='pengumuman.php';</script>";
    exit;
}

// Cek Tipe Partisipasi (Sekolah vs Klub)
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

// 3. Mengambil Hasil Perlombaan
// Di sini keamanannya dijaga: HANYA menampilkan nomor lomba yang is_published = 1
$sql = "SELECT en.event_number, en.distance, en.stroke, en.jenis_kelamin, en.age_group,
               s.nama_atlet, c.nama_klub, s.asal_sekolah, s.user_id as swimmer_owner_id, s.tanggal_lahir,
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

// Deteksi Mode per Acara dari Database (berdasarkan jumlah atlet dengan rank_final = 1)
$modePerAcara = [];
foreach($results as $r) {
    if(!isset($modePerAcara[$r['event_number']])) {
        $modePerAcara[$r['event_number']] = [
             'rank1_count' => 0, 
             'is_gabungan' => (stripos($r['age_group'], 'GABUNG') !== false || strpos($r['age_group'], ',') !== false || strpos($r['age_group'], '/') !== false)
        ];
    }
    if($r['rank_final'] == 1) {
        $modePerAcara[$r['event_number']]['rank1_count']++;
    }
}

// 4. Kelompokkan berdasarkan Nomor Acara
$groupedResults = [];
foreach ($results as $r) {
    $r['ms_sort'] = 9999999999;
    if ($r['is_dq_final'] == 1) { $r['ms_sort'] = 9999999999 + 100; }
    elseif (!empty($r['time_final']) && $r['time_final'] != 'NT') { $r['ms_sort'] = timeToMs($r['time_final']); }
    
    // Cek apakah Admin menyimpannya sebagai OVERALL atau SPLIT
    $isSplit = false;
    $m = $modePerAcara[$r['event_number']];
    if ($m['is_gabungan']) {
        if ($m['rank1_count'] > 1) {
            $isSplit = true; // Banyak juara 1 (berarti di-split per KU)
        } elseif ($m['rank1_count'] == 1) {
            $isSplit = false; // Hanya 1 juara 1 (berarti digabung Overall)
        } else {
            $isSplit = true; // Belum disimpan Admin (rank_final kosong semua), default: Split
        }
    } else {
        $isSplit = false; // Bukan grup gabungan
    }
    
    if (!$isSplit) {
        $label = ($m['is_gabungan']) ? 'OVERALL' : $r['age_group'];
        $judulAcara = "ACARA #" . $r['event_number'] . " - " . $r['distance'] . "M " . strtoupper($r['stroke']) . " " . strtoupper($r['jenis_kelamin']) . " (" . $label . ")";
    } else {
        $realKU = getAgeGroupLabel($r['tanggal_lahir'], $eventYear, $ageGroups);
        $judulAcara = "ACARA #" . $r['event_number'] . " - " . $r['distance'] . "M " . strtoupper($r['stroke']) . " " . strtoupper($r['jenis_kelamin']) . " (" . $realKU . ")";
    }

    $groupedResults[$judulAcara][] = $r;
}

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
    preg_match('/ACARA #(\d+)/', $a, $matchA);
    preg_match('/ACARA #(\d+)/', $b, $matchB);
    $numA = isset($matchA[1]) ? (int)$matchA[1] : 9999;
    $numB = isset($matchB[1]) ? (int)$matchB[1] : 9999;
    
    if ($numA === $numB) {
        return strcmp($a, $b); // sort by KU name if same event
    }
    return $numA < $numB ? -1 : 1;
});
unset($rows);

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="max-w-6xl mx-auto">
        <a href="pengumuman.php" class="inline-flex items-center text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-blue-600 transition mb-6">
            &larr; Kembali ke Pusat Informasi
        </a>

        <div class="bg-slate-900 text-white p-8 rounded-3xl shadow-xl mb-8 relative overflow-hidden">
            <div class="absolute -right-10 -top-10 text-9xl opacity-10">🏆</div>
            <div class="relative z-10">
                <span class="inline-block px-3 py-1 bg-blue-600 text-white text-[9px] font-black uppercase tracking-widest rounded-lg mb-3">Live Result Digital</span>
                <h1 class="text-3xl font-black uppercase italic leading-tight mb-2"><?= htmlspecialchars($event['event_name']) ?></h1>
                <p class="text-xs text-slate-300 font-bold uppercase tracking-widest">
                    📍 <?= htmlspecialchars($event['event_location']) ?> | 📅 <?= date('d F Y', strtotime($event['event_date_start'])) ?>
                </p>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 mb-8 flex items-center gap-4">
            <span class="text-2xl ml-2">🔍</span>
            <input type="text" id="searchInput" placeholder="Cari nama atlet atau tim di sini..." class="w-full bg-transparent border-none focus:ring-0 text-sm font-bold text-slate-700 uppercase placeholder:text-slate-300 placeholder:normal-case">
        </div>

        <?php if (empty($groupedResults)): ?>
            <div class="bg-white p-12 text-center rounded-3xl border border-slate-200 border-dashed">
                <span class="text-4xl block mb-3 opacity-30">📭</span>
                <p class="text-sm font-bold text-slate-400 uppercase tracking-widest">Belum ada hasil perlombaan yang diterbitkan.</p>
                <p class="text-[10px] text-slate-400 mt-2">Silakan tunggu panitia memperbarui data atau mengaktifkan saklar Live Result.</p>
            </div>
        <?php else: ?>
            <div id="resultContainer" class="space-y-6 pb-12">
                <?php foreach ($groupedResults as $judul => $atletList): ?>
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden result-card">
                        <div class="bg-slate-100 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                            <h2 class="text-sm font-black text-slate-800 uppercase italic"><?= $judul ?></h2>
                            <span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-[9px] font-black uppercase tracking-widest animate-pulse">🔴 LIVE</span>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">
                                        <th class="py-3 px-4 w-12 text-center">Rank</th>
                                        <th class="py-3 px-4">Nama Atlet</th>
                                        <th class="py-3 px-4 text-center w-20">KU</th>
                                        <th class="py-3 px-4"><?= $teamHeaderLabel ?></th>
                                        <th class="py-3 px-4 text-center w-28">Wkt. Prestasi</th>
                                        <th class="py-3 px-4 text-right w-28">Wkt. Final</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach ($atletList as $atlet): 
                                        $isMyTeam = ($atlet['swimmer_owner_id'] == $user_id);
                                        $isDQ = ($atlet['is_dq_final'] == 1);
                                        
                                        $rowClass = 'border-b border-slate-100 hover:bg-slate-50 transition-colors';
                                        
                                        if ($isSchoolEvent) {
                                            $displayTeam = !empty($atlet['asal_sekolah']) ? $atlet['asal_sekolah'] : '-';
                                        } else {
                                            $displayTeam = !empty($atlet['nama_klub']) ? $atlet['nama_klub'] : '-';
                                        }

                                        $rankBadge = '-';
                                        $rankClass = 'text-slate-600';
                                        if ($atlet['dynamic_rank'] !== null) {
                                            $rankBadge = $atlet['dynamic_rank'];
                                            if($rankBadge == 1) { $rankBadge = '🥇 1'; $rankClass = 'text-amber-500'; }
                                            if($rankBadge == 2) { $rankBadge = '🥈 2'; $rankClass = 'text-slate-400'; }
                                            if($rankBadge == 3) { $rankBadge = '🥉 3'; $rankClass = 'text-orange-500'; }
                                        }

                                        $waktuDaftar = $atlet['entry_time'];
                                        if (empty($waktuDaftar) || $waktuDaftar === '00:00.00' || $waktuDaftar === '00:00:00') {
                                            $waktuDaftar = 'NT';
                                        }
                                    ?>
                                    <tr class="searchable-row <?= $rowClass ?>">
                                        
                                        <td class="p-4 text-center font-bold <?= $rankClass ?>">
                                            <?= $rankBadge ?>
                                        </td>
                                        
                                        <td class="p-4">
                                            <div class="font-extrabold text-slate-800 athlete-name <?= $isMyTeam ? 'text-yellow-600' : '' ?>"><?= htmlspecialchars($atlet['nama_atlet']) ?></div>
                                        </td>
                                        
                                        <td class="p-4 text-center font-bold text-slate-500 text-xs">
                                            <?php 
                                                if (stripos($atlet['age_group'], 'GABUNG') !== false) {
                                                    echo htmlspecialchars(getAgeGroupLabel($atlet['tanggal_lahir'], $eventYear, $ageGroups));
                                                } else {
                                                    echo htmlspecialchars($atlet['age_group']);
                                                }
                                            ?>
                                        </td>
                                        
                                        <td class="p-4 text-sm font-bold text-slate-600 team-name">
                                            <?= htmlspecialchars($displayTeam) ?>
                                        </td>
                                        
                                        <td class="p-4 text-center font-mono text-xs text-slate-400 font-bold">
                                            <?= htmlspecialchars($waktuDaftar) ?>
                                        </td>
                                        
                                        <!-- 🌟 FASE 4: TOMBOL BADGE DQ & WAKTU FINAL -->
                                        <td class="py-3 px-4 text-right font-mono text-sm font-black text-slate-800">
                                            <?php if($isDQ): 
                                                $reason = $atlet['dq_reason_final'] ?? 'DQ';
                                                if (in_array($reason, ['DNS', 'DNF'])):
                                            ?>
                                                <span class="bg-slate-100 text-slate-500 border border-slate-300 px-2 py-1 rounded text-[10px] uppercase font-sans tracking-wider">
                                                    <?= htmlspecialchars($reason) ?>
                                                </span>
                                            <?php else: ?>
                                                <!-- TOMBOL DIPERBAIKI: Hanya menampilkan ⚠️ DQ di tabel -->
                                                <button onclick="showDqDetail('<?= htmlspecialchars($reason) ?>')" class="bg-red-50 text-red-600 border border-red-300 px-2 py-1 rounded text-[10px] uppercase hover:bg-red-100 transition inline-flex items-center justify-end gap-1 ml-auto cursor-pointer shadow-sm animate-pulse font-sans tracking-wider">
                                                    ⚠️ DQ
                                                </button>
                                            <?php endif; ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($atlet['time_final'] ?? '-') ?>
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
</div>

<!-- 🌟 FASE 4: SWEETALERT & FUNGSI POP-UP -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Ambil data JSON dari PHP ke JavaScript
const dqRulesData = <?= $dqRulesJson ?>;

function showDqDetail(pasal) {
    // Cari deskripsi pasal, jika tidak ada berikan teks default
    let deskripsi = dqRulesData[pasal] || "Penjelasan detail untuk pasal ini belum tersedia di sistem.";
    
    // Tampilkan SweetAlert
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

document.getElementById('searchInput').addEventListener('keyup', function() {
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