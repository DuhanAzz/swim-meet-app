<?php
// src/admin/results/export_canva.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Proteksi Akses
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'master')) {
    die("Akses Ditolak.");
}

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$filter_ku = isset($_GET['ku']) ? $_GET['ku'] : 'ALL';
$filter_team = isset($_GET['team']) ? $_GET['team'] : 'ALL';
$filter_limit = isset($_GET['limit']) ? $_GET['limit'] : 'ALL'; // ALL, TOP3, DQ
$rank_mode = isset($_GET['rank_mode']) ? $_GET['rank_mode'] : 'OVERALL'; // SPLIT, OVERALL

if ($event_id === 0) die("Event ID invalid.");

// 1. Dapatkan detail event
$stmtEvent = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtEvent->execute([$event_id]);
$event = $stmtEvent->fetch(PDO::FETCH_ASSOC);
if (!$event) die("Event tidak ditemukan.");

$partType = strtolower($event['participation_type'] ?? 'club');
$isSchool = (strpos($partType, 'school') !== false || strpos($partType, 'sekolah') !== false);
$eventYear = date('Y', strtotime($event['event_date_start']));

// 2. Dapatkan batas KU untuk pemisahan
$stmtAge = $pdo->prepare("SELECT group_name, min_age, max_age FROM event_age_groups WHERE event_id = ?");
$stmtAge->execute([$event_id]);
$ageGroups = $stmtAge->fetchAll(PDO::FETCH_ASSOC);

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

if (!function_exists('timeToMs')) {
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
}

if (!function_exists('formatTimeDisplay')) {
    function formatTimeDisplay($time) {
        $time = trim($time);
        if (empty($time) || $time == 'NT' || $time == '99:99.99' || $time == '-') return $time;
        $parts = preg_split('/[:.]/', $time);
        $menit = 0; $detik = 0; $ms = 0;
        if (count($parts) == 3) { $menit = (int)$parts[0]; $detik = (int)$parts[1]; $ms = (int)$parts[2]; } 
        elseif (count($parts) == 2) { $detik = (int)$parts[0]; $ms = (int)$parts[1]; } 
        elseif (count($parts) == 1) { $detik = (int)$parts[0]; }
        return sprintf("%02d.%02d.%02d", $menit, $detik, $ms);
    }
}

// 3. Tarik data entries
$sql = "SELECT en.event_number, en.distance, en.stroke, en.jenis_kelamin, en.age_group as event_age_group,
               s.uid, s.nama_atlet, c.nama_klub, s.asal_sekolah, s.tanggal_lahir,
               es.time_final, es.is_dq_final, es.dq_reason_final
        FROM event_numbers en
        JOIN event_entries ee ON en.id = ee.category_id
        JOIN event_seeding es ON ee.id = es.entry_id
        JOIN swimmers s ON ee.swimmer_id = s.id
        LEFT JOIN clubs c ON ee.club_id = c.id
        WHERE en.event_id = ? 
          AND (es.time_final IS NOT NULL OR es.is_dq_final = 1)";
          
$params = [$event_id];

$stmtRes = $pdo->prepare($sql);
$stmtRes->execute($params);
$results = $stmtRes->fetchAll(PDO::FETCH_ASSOC);

// 4. Kelompokkan dan Kalkulasi Ranking (Dynamic Re-Ranking)
$groupedResults = [];
foreach ($results as $r) {
    $r['ms_sort'] = 9999999999;
    if ($r['is_dq_final'] == 1) { $r['ms_sort'] = 9999999999 + 100; }
    elseif (!empty($r['time_final']) && $r['time_final'] != 'NT') { $r['ms_sort'] = timeToMs($r['time_final']); }
    
    $is_gabungan = (stripos($r['event_age_group'], 'GABUNG') !== false || strpos($r['event_age_group'], ',') !== false || strpos($r['event_age_group'], '/') !== false);
    
    // Terapkan Mode Ranking dari Input Form (Bypass deteksi database agar fleksibel)
    $isSplit = ($rank_mode === 'SPLIT' && $is_gabungan);
    
    if (!$isSplit) {
        $realKU = ($is_gabungan) ? 'OVERALL' : $r['event_age_group'];
    } else {
        $realKU = getAgeGroupLabel($r['tanggal_lahir'], $eventYear, $ageGroups);
    }
    
    // Terapkan Filter KU
    if ($filter_ku !== 'ALL' && $realKU !== $filter_ku) continue;
    
    // Terapkan Filter Tim
    $teamName = $isSchool ? ($r['asal_sekolah'] ?? '-') : ($r['nama_klub'] ?? '-');
    if ($filter_team !== 'ALL' && $teamName !== $filter_team) continue;

    $judulAcara = "ACARA #" . $r['event_number'] . " - " . $r['distance'] . "M " . strtoupper($r['stroke']) . " " . strtoupper($r['jenis_kelamin']);
    
    $r['real_ku'] = $realKU;
    $r['team_name'] = $teamName;
    
    $groupKey = $judulAcara . " (" . $realKU . ")";
    $groupedResults[$groupKey][] = $r;
}

// Lakukan Sorting dan Pemberian Rank
$finalOutput = [];
foreach ($groupedResults as $groupName => &$rows) {
    if (!is_array($rows)) $rows = [];
    usort($rows, function($a, $b) {
        $msA = isset($a['ms_sort']) ? $a['ms_sort'] : 9999999999;
        $msB = isset($b['ms_sort']) ? $b['ms_sort'] : 9999999999;
        if ($msA == $msB) return 0;
        return ($msA < $msB) ? -1 : 1;
    });
    
    $rank = 1; $real_rank = 1; $prev_time = null;
    foreach ($rows as &$atlet) {
        $isDQ = (isset($atlet['is_dq_final']) && $atlet['is_dq_final'] == 1);
        $timeFinal = isset($atlet['time_final']) ? $atlet['time_final'] : '';
        $msSort = isset($atlet['ms_sort']) ? $atlet['ms_sort'] : 9999999999;
        
        $isValid = (!$isDQ && !empty($timeFinal) && $timeFinal != 'NT');
        $atlet['dynamic_rank'] = null;
        if ($isValid) {
            if ($msSort !== $prev_time) { $real_rank = $rank; }
            $atlet['dynamic_rank'] = $real_rank;
            $prev_time = $msSort;
            $rank++;
        }
        
        // Filter Capaian
        if ($filter_limit === 'DQ' && !$isDQ) continue;
        if ($filter_limit === 'TOP3') {
            if ($isDQ || $atlet['dynamic_rank'] > 3 || $atlet['dynamic_rank'] === null) continue;
        }
        
        // Label Ranking untuk CSV
        $rankLabel = '';
        if ($isDQ) {
            $rankLabel = 'DQ (' . ($atlet['dq_reason_final'] ?: '-') . ')';
        } else if ($atlet['dynamic_rank'] !== null) {
            if ($atlet['dynamic_rank'] <= 3) {
                $rankLabel = 'Juara ' . $atlet['dynamic_rank'];
            } else {
                $rankLabel = 'Peserta';
            }
        } else {
            $rankLabel = 'Peserta'; // NT (No Time)
        }
        
        $finalOutput[] = [
            'UID' => $atlet['uid'],
            'Nama_Atlet' => strtoupper($atlet['nama_atlet']),
            'Klub_Sekolah' => strtoupper($atlet['team_name']),
            'Nomor_Acara' => $atlet['event_number'],
            'Perlombaan' => strtoupper($atlet['distance'] . "M " . $atlet['stroke'] . " " . $atlet['jenis_kelamin']),
            'Kelompok_Umur' => strtoupper($atlet['real_ku']),
            'Gender' => strtoupper($atlet['jenis_kelamin']),
            'Waktu_Final' => $timeFinal ? formatTimeDisplay($timeFinal) : '-',
            'Peringkat' => $rankLabel
        ];
    }
}
unset($rows);

// 5. Generate CSV
$filename = "Canva_Sertifikat_Batch_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
fputcsv($output, ['UID', 'Nama_Atlet', 'Klub_Sekolah', 'Nomor_Acara', 'Perlombaan', 'Kelompok_Umur', 'Gender', 'Waktu_Final', 'Peringkat']);

foreach ($finalOutput as $row) {
    fputcsv($output, $row);
}
fclose($output);
exit;
