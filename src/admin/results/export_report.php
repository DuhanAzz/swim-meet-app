<?php
// src/admin/results/export_report.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Proteksi Akses
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'master')) {
    die("Akses Ditolak.");
}

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$filter_ku = isset($_GET['ku']) ? $_GET['ku'] : 'ALL';
$filter_team = isset($_GET['team']) ? $_GET['team'] : 'ALL';
$filter_limit = isset($_GET['limit']) ? $_GET['limit'] : 'ALL'; 
$rank_mode = isset($_GET['rank_mode']) ? $_GET['rank_mode'] : 'OVERALL'; 
$format = isset($_GET['format']) ? $_GET['format'] : 'pdf'; // pdf, excel, csv

// Config Panel Options
$cfg_event_no = isset($_GET['cfg_event_no']) && $_GET['cfg_event_no'] == 1;
$cfg_date = isset($_GET['cfg_date']) && $_GET['cfg_date'] == 1;
$cfg_event_name = isset($_GET['cfg_event_name']) && $_GET['cfg_event_name'] == 1;
$cfg_group = isset($_GET['cfg_group']) && $_GET['cfg_group'] == 1;
$cfg_gender = isset($_GET['cfg_gender']) && $_GET['cfg_gender'] == 1;
$cfg_pool = isset($_GET['cfg_pool']) && $_GET['cfg_pool'] == 1;
$cfg_round = isset($_GET['cfg_round']) && $_GET['cfg_round'] == 1;
$cfg_show_records = isset($_GET['cfg_show_records']) && $_GET['cfg_show_records'] == 1;

$col_uid = isset($_GET['col_uid']) && $_GET['col_uid'] == 1;
$col_lahir = isset($_GET['col_lahir']) && $_GET['col_lahir'] == 1;
$col_ku = isset($_GET['col_ku']) && $_GET['col_ku'] == 1;
$col_tim = isset($_GET['col_tim']) && $_GET['col_tim'] == 1;
$col_waktu = isset($_GET['col_waktu']) && $_GET['col_waktu'] == 1;
$col_hasil = isset($_GET['col_hasil']) && $_GET['col_hasil'] == 1;
$col_ket = isset($_GET['col_ket']) && $_GET['col_ket'] == 1;

if ($event_id === 0) die("Event ID invalid.");

// 1. Dapatkan detail event
$stmtEvent = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtEvent->execute([$event_id]);
$event = $stmtEvent->fetch(PDO::FETCH_ASSOC);
if (!$event) die("Event tidak ditemukan.");

$partType = strtolower($event['participation_type'] ?? 'club');
$isSchool = (strpos($partType, 'school') !== false || strpos($partType, 'sekolah') !== false);
$eventYear = date('Y', strtotime($event['event_date_start']));

$eventName = strtoupper($event['event_name'] ?? 'EVENT NAME');
$eventLoc = strtoupper($event['event_location'] ?? 'LOKASI');
$eventDateStr = strtoupper(date('d F Y', strtotime($event['event_date_start'])));

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

// 3. Tarik data entries
$sql = "SELECT en.event_number, en.distance, en.stroke, en.jenis_kelamin, en.age_group as event_age_group,
               s.uid, s.nama_atlet, c.nama_klub, s.asal_sekolah, s.tanggal_lahir,
               ee.entry_time,
               es.time_final, es.is_dq_final, es.dq_reason_final
        FROM event_numbers en
        JOIN event_entries ee ON en.id = ee.category_id
        JOIN event_seeding es ON ee.id = es.entry_id
        JOIN swimmers s ON ee.swimmer_id = s.id
        LEFT JOIN clubs c ON ee.club_id = c.id
        WHERE en.event_id = ? 
          AND (es.time_final IS NOT NULL OR es.is_dq_final = 1)";
          
$stmtRes = $pdo->prepare($sql);
$stmtRes->execute([$event_id]);
$results = $stmtRes->fetchAll(PDO::FETCH_ASSOC);

// 4. Kelompokkan dan Kalkulasi Ranking (Dynamic Re-Ranking)
$groupedResults = [];
foreach ($results as $r) {
    $r['ms_sort'] = 9999999999;
    if ($r['is_dq_final'] == 1) { $r['ms_sort'] = 9999999999 + 100; }
    elseif (!empty($r['time_final']) && $r['time_final'] != 'NT') { $r['ms_sort'] = timeToMs($r['time_final']); }
    
    $is_gabungan = (stripos($r['event_age_group'], 'GABUNG') !== false || strpos($r['event_age_group'], ',') !== false || strpos($r['event_age_group'], '/') !== false);
    
    $isSplit = ($rank_mode === 'SPLIT' && $is_gabungan);
    
    if (!$isSplit) {
        $realKU = ($is_gabungan) ? 'OVERALL' : $r['event_age_group'];
    } else {
        $realKU = getAgeGroupLabel($r['tanggal_lahir'], $eventYear, $ageGroups);
    }
    
    if ($filter_ku !== 'ALL' && $realKU !== $filter_ku) continue;
    
    $teamName = $isSchool ? ($r['asal_sekolah'] ?? '-') : ($r['nama_klub'] ?? '-');
    if ($filter_team !== 'ALL' && $teamName !== $filter_team) continue;

    $judulAcara = "ACARA #" . $r['event_number'] . " - " . $r['distance'] . "M " . strtoupper($r['stroke']) . " " . strtoupper($r['jenis_kelamin']);
    
    $r['real_ku'] = $realKU;
    $r['team_name'] = $teamName;
    
    $groupKey = $judulAcara . " (" . $realKU . ")";
    
    if (!isset($groupedResults[$groupKey])) {
        $poolLabel = (stripos($event['pool_type']??'', '25m') !== false || stripos($event['pool_type']??'', 'SCM') !== false) ? 'SCM' : 'LCM';
        
        $cleanStroke = trim(str_ireplace(['Gaya', 'GAYA'], '', $r['stroke']));
        $genderLabel = (in_array($r['jenis_kelamin'], ['L','Male','Man'])) ? 'PUTRA' : 'PUTRI';
        
        $judulParts = [];
        if ($cfg_event_name) $judulParts[] = $r['distance']."M ".strtoupper($cleanStroke); 
        if ($cfg_group) $judulParts[] = $realKU; 
        if ($cfg_gender) $judulParts[] = strtoupper($genderLabel); 
        if ($cfg_pool) $judulParts[] = $poolLabel; 
        if ($cfg_round) $judulParts[] = "FINAL";
        
        // Fetch records if enabled
        $records = [];
        if ($cfg_show_records) {
            $stmtRec = $pdo->prepare("SELECT record_type, holder_name, record_time, location, record_year FROM master_records WHERE distance = ? AND stroke = ? AND jenis_kelamin = ? AND record_type = 'rekornas' ORDER BY id ASC");
            $stmtRec->execute([$r['distance'], $r['stroke'], $r['jenis_kelamin']]);
            $records = array_merge($records, $stmtRec->fetchAll(PDO::FETCH_ASSOC));

            if (!empty($event['record_package_id'])) {
                $stmtPkg = $pdo->prepare("
                    SELECT 'rekor_event' as record_type, ehr.holder_name, ehr.record_time, e.event_city as location, YEAR(e.event_date_start) as record_year 
                    FROM event_historical_records ehr 
                    LEFT JOIN events e ON ehr.source_event_id = e.id
                    WHERE ehr.package_id = ? AND ehr.distance = ? AND ehr.stroke = ? AND ehr.jenis_kelamin = ? AND ehr.age_group = ?
                ");
                $stmtPkg->execute([$event['record_package_id'], $r['distance'], $r['stroke'], $r['jenis_kelamin'], $r['event_age_group']]);
                $records = array_merge($records, $stmtPkg->fetchAll(PDO::FETCH_ASSOC));
            }
        }
        
        $groupedResults[$groupKey] = [
            'meta' => [
                'nomor' => $r['event_number'],
                'judul' => implode(" - ", $judulParts),
                'records' => $records
            ],
            'rows' => []
        ];
    }
    
    $groupedResults[$groupKey]['rows'][] = $r;
}

uksort($groupedResults, function($a, $b) {
    preg_match('/ACARA #(\d+)/', $a, $matchA);
    preg_match('/ACARA #(\d+)/', $b, $matchB);
    $numA = isset($matchA[1]) ? (int)$matchA[1] : 9999;
    $numB = isset($matchB[1]) ? (int)$matchB[1] : 9999;
    if ($numA === $numB) { return strcmp($a, $b); }
    return $numA < $numB ? -1 : 1;
});

$finalGroups = [];
foreach ($groupedResults as $groupName => &$rows) {
    usort($rows, function($a, $b) {
        if ($a['ms_sort'] == $b['ms_sort']) return 0;
        return ($a['ms_sort'] < $b['ms_sort']) ? -1 : 1;
    });
    
    $rank = 1; $real_rank = 1; $prev_time = null;
    $filteredRows = [];
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
        
        if ($filter_limit === 'DQ' && !$isDQ) continue;
        if ($filter_limit === 'TOP3') {
            if ($isDQ || $atlet['dynamic_rank'] > 3 || $atlet['dynamic_rank'] === null) continue;
        }
        
        $filteredRows[] = $atlet;
    }
    
    if (count($filteredRows) > 0) {
        $finalGroups[$groupName] = $filteredRows;
    }
}
unset($rows);

// ==============================================================================
// OUTPUT HANDLING
// ==============================================================================

if ($format === 'csv') {
    $filename = "Laporan_Resmi_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Acara_KU', 'Rank', 'UID', 'Nama_Atlet', 'Klub_Sekolah', 'Waktu_Daftar', 'Waktu_Final', 'Keterangan']);

    foreach ($finalGroups as $groupName => $rows) {
        foreach ($rows as $atlet) {
            $rankLabel = ($atlet['is_dq_final'] == 1) ? 'DQ' : ($atlet['dynamic_rank'] ?: '-');
            $ket = ($atlet['is_dq_final'] == 1) ? ($atlet['dq_reason_final'] ?: 'DQ') : '';
            fputcsv($output, [
                $groupName,
                $rankLabel,
                $atlet['uid'],
                strtoupper($atlet['nama_atlet']),
                strtoupper($atlet['team_name']),
                $atlet['entry_time'] ?: '-',
                $atlet['time_final'] ?: '-',
                $ket
            ]);
        }
    }
    fclose($output);
    exit;

} else if ($format === 'excel') {
    $filename = "Laporan_Resmi_" . date('Ymd_His') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    echo '<table border="1" style="border-collapse:collapse; text-align:left;">';
    echo '<tr><th colspan="7" style="font-size:20px; font-weight:bold; text-align:center;">LAPORAN RESMI HASIL PERTANDINGAN</th></tr>';
    echo '<tr><th colspan="7" style="font-size:16px; font-weight:bold; text-align:center;">' . $eventName . '</th></tr>';
    echo '<tr><th colspan="7" style="text-align:center;">' . $eventLoc . ' | ' . $eventDateStr . '</th></tr>';
    echo '<tr><th colspan="7"></th></tr>'; // Spasi
    
    foreach ($finalGroups as $groupName => $rows) {
        echo '<tr style="background-color:#e2e8f0; font-weight:bold;">';
        echo '<td colspan="7">' . $groupName . '</td>';
        echo '</tr>';
        
        echo '<tr style="background-color:#f1f5f9; font-weight:bold;">';
        echo '<td>Rank</td>';
        echo '<td>UID</td>';
        echo '<td>Nama Atlet</td>';
        echo '<td>Klub / Sekolah</td>';
        echo '<td>Kelompok Umur</td>';
        echo '<td>Waktu Daftar</td>';
        echo '<td>Waktu Final</td>';
        echo '<td>Keterangan</td>';
        echo '</tr>';
        
        foreach ($rows as $atlet) {
            $rankLabel = ($atlet['is_dq_final'] == 1) ? 'DQ' : ($atlet['dynamic_rank'] ?: '-');
            $ket = ($atlet['is_dq_final'] == 1) ? ($atlet['dq_reason_final'] ?: 'DQ') : '';
            
            echo '<tr>';
            echo '<td>' . $rankLabel . '</td>';
            echo '<td>' . htmlspecialchars($atlet['uid']) . '</td>';
            echo '<td>' . strtoupper($atlet['nama_atlet']) . '</td>';
            echo '<td>' . strtoupper($atlet['team_name']) . '</td>';
            echo '<td>' . strtoupper($atlet['real_ku']) . '</td>';
            echo '<td>' . ($atlet['entry_time'] ?: '-') . '</td>';
            echo '<td>' . ($atlet['time_final'] ?: '-') . '</td>';
            echo '<td>' . $ket . '</td>';
            echo '</tr>';
        }
        echo '<tr><th colspan="8"></th></tr>'; // Spasi antar acara
    }
    echo '</table>';
    exit;

} else {
    // FORMAT PDF (Window Print API)
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Laporan Resmi - <?= $eventName ?></title>
        <style>
            body { font-family: 'Arial', sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #333; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
            .header h1 { font-size: 16px; margin: 0 0 5px 0; text-transform: uppercase; }
            .header h2 { font-size: 14px; margin: 0 0 5px 0; }
            .header p { font-size: 11px; margin: 0; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
            th { background-color: #f1f5f9; font-weight: bold; }
            .acara-header { background-color: #e2e8f0; font-weight: bold; font-size: 12px; }
            .text-center { text-align: center; }
            .text-red { color: #dc2626; font-weight: bold; }
            @media print {
                @page { margin: 1cm; size: A4; }
                body { padding: 0; }
                .no-print { display: none !important; }
            }
        </style>
    </head>
    <body onload="window.print()">
        
        <div class="no-print" style="margin-bottom: 20px; text-align: center;">
            <button onclick="window.print()" style="padding: 10px 20px; background: #000; color: #fff; border: none; cursor: pointer; font-weight: bold;">🖨️ Cetak PDF</button>
            <p>Atur <strong>Destination</strong> ke "Save as PDF" di dialog cetak browser Anda.</p>
        </div>

        <div class="header">
            <h1>LAPORAN RESMI HASIL PERTANDINGAN</h1>
            <h2><?= htmlspecialchars($eventName) ?></h2>
            <p><?= htmlspecialchars($eventLoc) ?> | <?= htmlspecialchars($eventDateStr) ?></p>
        </div>

        <?php if(empty($finalGroups)): ?>
            <p class="text-center">Tidak ada data yang sesuai dengan filter yang dipilih.</p>
        <?php else: ?>
            <?php foreach ($finalGroups as $groupName => $rows): ?>
                <table>
                    <thead>
                        <tr>
                            <td colspan="7" class="acara-header"><?= htmlspecialchars($groupName) ?></td>
                        </tr>
                        <tr>
                            <th width="5%" class="text-center">Rank</th>
                            <th width="10%" class="text-center">UID</th>
                            <th width="25%">Nama Atlet</th>
                            <th width="25%">Tim / Sekolah</th>
                            <th width="10%" class="text-center">Entry</th>
                            <th width="10%" class="text-center">Final</th>
                            <th width="15%">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $atlet): ?>
                            <?php 
                                $isDQ = ($atlet['is_dq_final'] == 1);
                                $rankLabel = $isDQ ? 'DQ' : ($atlet['dynamic_rank'] ?: '-');
                                $ket = $isDQ ? ($atlet['dq_reason_final'] ?: 'DQ') : '';
                            ?>
                            <tr>
                                <td class="text-center <?= $isDQ ? 'text-red' : '' ?>"><?= $rankLabel ?></td>
                                <td class="text-center"><?= htmlspecialchars($atlet['uid']) ?></td>
                                <td><?= htmlspecialchars(strtoupper($atlet['nama_atlet'])) ?></td>
                                <td><?= htmlspecialchars(strtoupper($atlet['team_name'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars($atlet['entry_time'] ?: '-') ?></td>
                                <td class="text-center <?= $isDQ ? 'text-red' : '' ?>"><?= htmlspecialchars($atlet['time_final'] ?: '-') ?></td>
                                <td class="<?= $isDQ ? 'text-red' : '' ?>"><?= htmlspecialchars($ket) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php endif; ?>

        <div style="margin-top: 30px; font-size: 10px; text-align: right; color: #666;">
            Waktu Cetak Dokumen: <?= date('d M Y H:i:s') ?>
        </div>
    </body>
    </html>
    <?php
}
