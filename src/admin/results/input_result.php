<?php
// src/admin/results/input_result.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// 1. CEK KEAMANAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$cat_id = $_GET['category_id'] ?? null;
if (!$cat_id) { header("Location: index.php"); exit; }

if (isset($_GET['mode'])) { $_SESSION['ranking_mode_' . $cat_id] = $_GET['mode']; }
$currentMode = $_SESSION['ranking_mode_' . $cat_id] ?? 'split'; 

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

function shortenName($name) { return trim(preg_replace('/\s+/', ' ', $name ?? '')); }
function getTeamName($row, $type) {
    $club = $row['club_name'] ?? ''; $school = $row['asal_sekolah'] ?? '';
    if (stripos($type, 'sekolah') !== false || stripos($type, 'school') !== false) return $school ?: '-';
    return $club ?: '-';
}


// AMBIL MASTER DATA DQ RULES UNTUK POPUP
$stmtDq = $pdo->query("SELECT * FROM dq_rules ORDER BY CAST(SUBSTRING(pasal, 4) AS UNSIGNED) ASC, pasal ASC");
$dq_rules_list = $stmtDq->fetchAll(PDO::FETCH_ASSOC);

$stmtRace = $pdo->prepare("SELECT * FROM event_numbers WHERE id = ?");
$stmtRace->execute([$cat_id]);
$raceInfo = $stmtRace->fetch(PDO::FETCH_ASSOC);
if (!$raceInfo) die("Nomor lomba tidak ditemukan.");

$eventId = $raceInfo['event_id'] ?? 0; 
if (empty($eventId)) { die("Error: Nomor lomba ini tidak terikat pada Event manapun."); }

$stmtEvent = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtEvent->execute([$eventId]);
$eventProfile = $stmtEvent->fetch(PDO::FETCH_ASSOC);

// Variabel Header
$eventName  = strtoupper($eventProfile['event_name'] ?? 'EVENT NAME');
$loc  = $eventProfile['event_location'] ?? '-';
if (!empty($eventProfile['event_city'])) $loc .= ' - ' . $eventProfile['event_city'];
$venueName  = strtoupper($loc);
$eventDate  = $eventProfile['event_date_start'] ?? date('Y-m-d');
$eventYear  = date('Y', strtotime($eventDate)); 
$displayDate = strtoupper(date('d F Y', strtotime($eventDate)));

if(!empty($eventProfile['event_date_end']) && $eventProfile['event_date_end'] != '0000-00-00' && $eventProfile['event_date_end'] != $eventDate) {
    $dateRange = date('d', strtotime($eventDate)) . ' - ' . date('d F Y', strtotime($eventProfile['event_date_end']));
} else {
    $dateRange = $displayDate;
}
$dateRange = strtoupper($dateRange);

function getBase64Image($urlPath) {
    if (empty($urlPath)) return null;
    $baseDir = dirname(dirname(dirname(__DIR__)));
    $cleanPath = ltrim(preg_replace('/^(\.\.\/)+/', '', $urlPath), '/');
    if (strpos($cleanPath, 'swim-meet/') === 0) $cleanPath = substr($cleanPath, 10);
    if (strpos($cleanPath, 'public/') !== 0) $cleanPath = "public/" . $cleanPath;
    
    $fullPath = $baseDir . "/" . $cleanPath;
    if (file_exists($fullPath)) {
        $ext = pathinfo($fullPath, PATHINFO_EXTENSION);
        $data = file_get_contents($fullPath);
        return 'data:image/' . $ext . ';base64,' . base64_encode($data);
    }
    return BASE_URL . '/' . ltrim($urlPath, '/');
}

$logoLeft   = getBase64Image($eventProfile['logo_left'] ?? '');
$logoRight  = getBase64Image($eventProfile['logo_right'] ?? '');

$total_lintasan = (int)($eventProfile['lane_count'] ?? 8);
$pool_type    = strtoupper($eventProfile['pool_type'] ?? 'LCM');
$poolSuffix   = ($pool_type == 'SCM') ? ' - SCM' : ' - LCM';
$participationType = $eventProfile['participation_type'] ?? 'club';

$stmtAgeGroups = $pdo->prepare("SELECT * FROM event_age_groups WHERE event_id = ? ORDER BY min_age ASC");
$stmtAgeGroups->execute([$eventId]);
$ageGroups = $stmtAgeGroups->fetchAll(PDO::FETCH_ASSOC);

function getAgeGroupLabel($dob, $eventYear, $ageGroups) {
    if (empty($dob) || $dob == '0000-00-00') return 'UMUR TIDAK DIKETAHUI';
    $birthYear = date('Y', strtotime($dob));
    $age = $eventYear - $birthYear;
    foreach ($ageGroups as $group) {
        if ($age >= $group['min_age'] && $age <= $group['max_age']) return $group['group_name']; 
    }
    return "DILUAR KATEGORI ($age TH)"; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. Proses Import TXT Stopwatch Backup
        if (isset($_FILES['txt_backup']) && $_FILES['txt_backup']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['txt_backup']['tmp_name'];
            $fileContent = file_get_contents($fileTmp);
            $lines = explode("\n", $fileContent);
            
            $stmtUpdTxt = $pdo->prepare("UPDATE event_seeding SET time_final = ? WHERE entry_id = ?");
            $updateCount = 0;
            
            foreach ($lines as $line) {
                // Cari format: Lintasan X [Nama] |ID:1234|: 01:23.45
                if (preg_match('/\|ID:(\d+)\|:\s*([\d:.]+)/', $line, $matches)) {
                    $entryId = $matches[1];
                    $time = trim($matches[2]);
                    if ($time !== '' && $time !== '00:00.00' && $time !== '00:00.000') {
                        $stmtUpdTxt->execute([$time, $entryId]);
                        $updateCount++;
                    }
                }
            }
            $pdo->commit();
            $msg_success = "Import TXT Berhasil! $updateCount waktu atlet telah diperbarui.";
            // Refresh halaman agar form di bawah tidak tereksekusi bersamaan
            header("Location: input_result.php?category_id=" . $cat_id . "&msg=" . urlencode($msg_success));
            exit;
        }

        // 2. Proses Input Manual Biasa
        $entries = $_POST['entries'] ?? [];
        $rankModePost = $_POST['rank_mode_input'] ?? 'split'; 
        $_SESSION['ranking_mode_' . $cat_id] = $rankModePost;
        $currentMode = $rankModePost;

        $stmtUpd = $pdo->prepare("UPDATE event_seeding SET time_final = ?, is_dq_final = ?, dq_reason_final = ? WHERE entry_id = ?");
        foreach ($entries as $id => $data) {
            $time = trim($data['time'] ?? '');
            $status = $data['status']; // "", "DQ", "DNF", "DNS"
            $dqReasonInput = $data['dq_reason'] ?? ''; // Menangkap pasal DQ
            
            $is_dq = ($status !== '') ? 1 : 0;
            
            // Penentuan Alasan Diskualifikasi
            $reason = NULL;
            if ($status === 'DQ') {
                $reason = !empty($dqReasonInput) ? $dqReasonInput : 'DQ'; 
            } elseif ($status !== '') {
                $reason = $status; // Untuk DNF atau DNS
            }

            if ($is_dq) $time = NULL; 
            if ($time === '') $time = NULL;
            $stmtUpd->execute([$time, $is_dq, $reason, $id]);
        }

        $stmtAll = $pdo->prepare("
            SELECT ee.id, es.time_final as final_time, es.is_dq_final as is_dq, s.tanggal_lahir
            FROM event_entries ee JOIN event_seeding es ON ee.id = es.entry_id JOIN swimmers s ON ee.swimmer_id = s.id 
            WHERE ee.category_id = ?
        ");
        $stmtAll->execute([$cat_id]);
        $allSwimmers = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        $stmtRank = $pdo->prepare("UPDATE event_seeding SET rank_final = ? WHERE entry_id = ?");
        if ($rankModePost === 'overall') {
            $valid = []; $invalid = [];
            foreach ($allSwimmers as $s) {
                if ($s['is_dq'] == 0 && !empty($s['final_time']) && $s['final_time'] != 'NT') {
                    $s['ms'] = timeToMs($s['final_time']); $valid[] = $s;
                } else { $invalid[] = $s; }
            }
            usort($valid, function($a, $b) { return $a['ms'] - $b['ms']; });
            $rank = 1; $counter = 1; $prevMs = null;
            foreach ($valid as $s) {
                if ($prevMs !== null && $s['ms'] != $prevMs) { $rank = $counter; }
                $stmtRank->execute([$rank, $s['id']]); $prevMs = $s['ms']; $counter++;
            }
            foreach ($invalid as $s) { $stmtRank->execute([NULL, $s['id']]); }
            $msg_success = "Data disimpan! Ranking GABUNGAN (Final).";
        } else {
            $groupedSwimmers = [];
            foreach ($allSwimmers as $s) {
                $groupName = getAgeGroupLabel($s['tanggal_lahir'], $eventYear, $ageGroups);
                $groupedSwimmers[$groupName][] = $s;
            }
            foreach ($groupedSwimmers as $groupName => $swimmersInGroup) {
                $valid = []; $invalid = [];
                foreach ($swimmersInGroup as $s) {
                    if ($s['is_dq'] == 0 && !empty($s['final_time']) && $s['final_time'] != 'NT') {
                        $s['ms'] = timeToMs($s['final_time']); $valid[] = $s;
                    } else { $invalid[] = $s; }
                }
                usort($valid, function($a, $b) { return $a['ms'] - $b['ms']; });
                $rank = 1; $counter = 1; $prevMs = null;
                foreach ($valid as $s) {
                    if ($prevMs !== null && $s['ms'] != $prevMs) { $rank = $counter; }
                    $stmtRank->execute([$rank, $s['id']]); $prevMs = $s['ms']; $counter++;
                }
                foreach ($invalid as $s) { $stmtRank->execute([NULL, $s['id']]); }
            }
            $msg_success = "Data disimpan! Ranking SPLIT KU (Final).";
        }
        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); $msg_error = "Error: " . $e->getMessage(); }
}

$currentEventId = $raceInfo['event_id'];
$stmtPrev = $pdo->prepare("SELECT id FROM event_numbers WHERE event_id = ? AND id < ? ORDER BY id DESC LIMIT 1");
$stmtPrev->execute([$currentEventId, $cat_id]);
$rowPrev = $stmtPrev->fetch(PDO::FETCH_ASSOC);
$prevUrl = $rowPrev ? "input_result.php?category_id=" . $rowPrev['id'] : "#";
$prevClass = $rowPrev ? "bg-slate-700 hover:bg-slate-800 text-white" : "bg-slate-200 text-slate-400 cursor-not-allowed pointer-events-none";

$stmtNext = $pdo->prepare("SELECT id FROM event_numbers WHERE event_id = ? AND id > ? ORDER BY id ASC LIMIT 1");
$stmtNext->execute([$currentEventId, $cat_id]);
$rowNext = $stmtNext->fetch(PDO::FETCH_ASSOC);
$nextUrl = $rowNext ? "input_result.php?category_id=" . $rowNext['id'] : "#";
$nextClass = $rowNext ? "bg-slate-700 hover:bg-slate-800 text-white" : "bg-slate-200 text-slate-400 cursor-not-allowed pointer-events-none";    

$cleanStroke = trim(str_ireplace(['Gaya', 'GAYA'], '', $raceInfo['stroke'] ?? ''));
$gender_label = (in_array($raceInfo['jenis_kelamin'], ['L','Male','Man'])) ? 'PUTRA' : 'PUTRI';
$judul_tengah = $raceInfo['distance'] . " M " . strtoupper($cleanStroke) . " - " . ($raceInfo['age_group']??'') . " " . $gender_label . $poolSuffix;
$judul_print = $raceInfo['distance'] . "M " . strtoupper($cleanStroke) . " - " . ($raceInfo['age_group']??'') . " " . $gender_label . " - LCM";
$nomor_acara = $raceInfo['event_number'];

$stmtSpon = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE event_id = ?");
$stmtSpon->execute([$eventId]);
$sponsors = $stmtSpon->fetchAll(PDO::FETCH_COLUMN);

try {
    $sql = "SELECT ee.id, es.heat_prelim as heat, es.lane_prelim as lane, es.time_final as final_time, es.is_dq_final as is_dq, es.dq_reason_final as dq_reason, es.time_prelim as entry_time,
            s.uid, s.nama_atlet, s.tanggal_lahir, s.asal_sekolah, c.nama_klub as club_name
            FROM event_entries ee JOIN event_seeding es ON ee.id = es.entry_id JOIN swimmers s ON ee.swimmer_id = s.id LEFT JOIN clubs c ON ee.club_id = c.id 
            WHERE ee.category_id = ? AND es.heat_prelim IS NOT NULL ORDER BY es.heat_prelim ASC, es.lane_prelim ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cat_id]);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Error Database: " . $e->getMessage()); }

$heats = [];
foreach ($raw_data as $row) { $heats[$row['heat']][$row['lane']] = $row; }

// --- LOGIKA EXPORT TXT ---
if (isset($_GET['export_txt'])) {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="Backup_Acara_' . preg_replace('/[^a-zA-Z0-9]/', '_', $nomor_acara) . '.txt"');
    
    echo "HASIL LOMBA: " . $judul_tengah . "\r\n";
    echo "ACARA: " . $nomor_acara . "\r\n\r\n";
    
    foreach($heats as $heatNo => $lanesData) {
        echo "HEAT " . $heatNo . "\r\n";
        for($ln = 1; $ln <= $total_lintasan; $ln++) {
            $s = $lanesData[$ln] ?? null;
            if ($s) {
                $timeStr = !empty($s['final_time']) ? $s['final_time'] : "00:00.00";
                echo "Lintasan " . $ln . " [" . $s['nama_atlet'] . "] |ID:" . $s['id'] . "|: " . $timeStr . "\r\n";
            }
        }
        echo "\r\n";
    }
    exit;
}

$groupedResults = [];
if ($currentMode === 'overall') {
    $groupTitle = "OPEN KATEGORI";
    foreach ($raw_data as $row) {
        if (!empty($row['id'])) {
            $row['ms_sort'] = 9999999999;
            if (($row['is_dq']??0) == 1) { $row['ms_sort'] = 9999999999 + 100; } 
            elseif (!empty($row['final_time']) && $row['final_time'] != 'NT') { $row['ms_sort'] = timeToMs($row['final_time']); }
            $groupedResults[$groupTitle][] = $row;
        }
    }
} else {
    foreach ($raw_data as $row) {
        if (!empty($row['id'])) {
            $groupName = getAgeGroupLabel($row['tanggal_lahir'], $eventYear, $ageGroups);
            $row['ms_sort'] = 9999999999; 
            if (($row['is_dq']??0) == 1) { $row['ms_sort'] = 9999999999 + 100; } 
            elseif (!empty($row['final_time']) && $row['final_time'] != 'NT') { $row['ms_sort'] = timeToMs($row['final_time']); }
            $groupedResults[$groupName][] = $row;
        }
    }
    ksort($groupedResults);
}
foreach ($groupedResults as $key => &$rows) {
    usort($rows, function($a, $b) {
        if ($a['ms_sort'] == $b['ms_sort']) return 0;
        return ($a['ms_sort'] < $b['ms_sort']) ? -1 : 1;
    });
}
unset($rows);

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'];
$default_target_link = $protocol . "://" . $host . "/public/result.php?category_id=" . $cat_id;
$default_qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&margin=0&data=" . urlencode($default_target_link);

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700;900&family=Courier+Prime:wght@400;700&display=swap');
    
    .toggle-checkbox { display: none; }
    .toggle-label { width: 44px; height: 24px; background-color: #cbd5e1; border-radius: 9999px; position: relative; cursor: pointer; transition: background-color 0.3s ease; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1); }
    .toggle-label::after { content: ''; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; background-color: white; border-radius: 50%; transition: transform 0.3s cubic-bezier(0.4, 0.0, 0.2, 1); box-shadow: 0 1px 3px rgba(0,0,0,0.3); }
    .toggle-checkbox:checked + .toggle-label { background-color: #3b82f6; }
    .toggle-checkbox:checked + .toggle-label::after { transform: translateX(20px); }
    
    .screen-only { display: block; }
    .print-only { display: none; }
    
    .input-time { width: 100%; border: 1px solid #ccc; background: #f9f9f9; padding: 2px; font-family: 'Courier Prime', monospace; font-weight: bold; text-align: right; font-size: 10pt; color: blue; outline: none; border-radius: 4px; }
    .input-status { width: 100%; border: none; background: transparent; font-size: 8pt; font-weight: bold; text-align: center; cursor: pointer; }

    @media print {
        @page { size: A4; margin: 0; }
        nav, aside, .no-print, .alert-box, .screen-only { display: none !important; }
        .p-4, .sm\:ml-64, .pt-24, .min-h-screen { padding: 0 !important; margin: 0 !important; min-height: auto !important; background: white !important; }
        body { background: white !important; font-family: 'Arial', sans-serif; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        img, .header-fixed { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        
        .print-only { display: block !important; }
        .page-wrapper { margin: 0; width: 100%; padding: 0 10mm; position: relative; }

        .header-fixed { position: fixed; top: 0; left: 0; right: 0; height: 35mm; background: white; border-bottom: 3px double #000; display: grid; grid-template-columns: 110px 1fr 110px; align-items: flex-end; padding: 5px 10mm 3px 10mm; z-index: 999; }
        .header-center { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; text-align: center; line-height: 1.2; color: #000; }
        .header-line-1 { font-size: 14pt; font-weight: 900; text-transform: uppercase; margin-bottom: 2px; }
        .header-line-2 { font-size: 9pt; font-weight: bold; text-transform: uppercase; }
        .header-line-3 { font-size: 9pt; font-weight: bold; text-transform: uppercase; }
        .header-line-4 { height: 3px; } 
        .header-line-5 { font-size: 18pt; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: #000; margin-top: 2px; margin-bottom: 0px; line-height: 1; }
        .logo-img { max-height: 100px; max-width: 100%; object-fit: contain; margin-bottom: 2px; }

        .footer-fixed { position: fixed; bottom: 0; left: 0; right: 0; height: 20mm; background: white; border-top: 2px double #000; display: flex; justify-content: space-between; align-items: center; padding: 0 10mm; z-index: 999; }
        .footer-sponsors { display: flex; gap: 10px; align-items: center; justify-content: center; flex: 1; }
        .footer-sponsors img { height: 45px; object-fit: contain; } 
        .footer-time { font-family: monospace; font-size: 8pt; color: #666; width: 120px; }

        .layout-table { width: 100%; border-collapse: collapse; border: none; }
        .layout-header-space { height: 40mm; } 
        .layout-footer-space { height: 22mm; }

        .event-header { display: flex; justify-content: space-between; align-items: flex-end; border-top: none; border-bottom: 2px solid #000; padding: 2px 0; margin-top: 5px; margin-bottom: 2px; background: #fff; font-family: 'Arial', sans-serif; min-height: 35px; }
        .eh-left { width: 150px; }
        .eh-number { font-size: 14pt; font-weight: 900; }
        .eh-date { font-size: 8pt; font-weight: bold; margin-top: 2px; }
        .eh-center { flex: 1; text-align: center; font-size: 11pt; font-weight: 800; text-transform: uppercase; padding-bottom: 2px; }
        .eh-right { width: 150px; text-align: right; display: flex; justify-content: flex-end; }
        .qr-header { width: 45px; height: 45px; object-fit: contain; margin-bottom: 2px; }

        .event-records-container { border-bottom: 1px solid #000; padding: 4px 0; margin-bottom: 10px; font-size: 8pt; font-family: 'Arial Narrow', sans-serif; font-weight: bold; line-height: 1.3; text-align: left; }
        .rec-row { display: flex; justify-content: flex-start; text-transform: uppercase; }
        .rec-label { width: 140px; font-weight: 900; color: #000; }
        .rec-details { flex: 1; color: #000; }

        .data-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 10px; font-family: 'Courier New', Courier, monospace; font-size: 8pt; }
        .data-table th { background-color: #e5e7eb !important; color: #000; font-family: 'Arial Narrow', sans-serif; font-weight: bold; font-size: 8pt; text-transform: uppercase; padding: 4px; border-top: 1px solid #000; border-bottom: 2px solid #000; text-align: center; }
        .data-table td { padding: 4px; border-bottom: 1px solid #ccc; vertical-align: middle; }
        
        .col-rank { width: 5%; text-align: center; background: #f8f9fa !important; font-weight: bold; }
        .col-uid { width: 12%; text-align: center; }
        .col-nama { width: 33%; text-align: left; padding-left: 5px; font-weight: bold; }
        .col-ku { width: 10%; text-align: center; }
        .col-tim { width: 20%; text-align: left; padding-left: 5px; }
        .col-waktu-awal { width: 10%; text-align: right; padding-right: 5px; color: #666; font-size: 7.5pt; }
        .col-hasil { width: 10%; text-align: right; font-weight: bold; font-size: 9pt; }
        
        .group-row td { background-color: #f3f4f6 !important; font-family: 'Arial', sans-serif; font-weight: bold; text-transform: uppercase; color: #6b7280; border-top: 1px solid #9ca3af; border-bottom: 1px solid #9ca3af; padding: 6px 8px; text-align: left; font-size: 9pt; }

        .layout-table > thead { display: table-header-group !important; }
        .data-table > thead { display: table-row-group !important; }
        tfoot { display: table-footer-group; }
    }
</style>

<div class="p-4 sm:ml-64 pt-24 min-h-screen bg-slate-100 text-slate-900 font-sans relative">

    <?php if(isset($msg_success)): ?>
        <div class="alert-box max-w-3xl mx-auto mb-4 bg-emerald-100 border border-emerald-400 text-emerald-800 px-4 py-3 rounded-lg flex items-center gap-2 shadow-sm sticky top-20 z-50 no-print">
            <span>✅</span> <strong><?= $msg_success ?></strong>
        </div>
    <?php endif; ?>

    <div class="no-print max-w-4xl mx-auto mb-6 flex flex-col items-center bg-white p-4 rounded-xl border border-slate-200 shadow-sm sticky top-20 z-40 gap-4">
        <div class="flex flex-col md:flex-row justify-between w-full items-center gap-4">
            <div>
                <h2 class="text-lg font-black text-slate-800 italic">INPUT HASIL LOMBA</h2>
                <div class="flex items-center gap-2 mt-1">
                    <span class="text-[10px] font-bold uppercase text-slate-500">Mode Ranking:</span>
                    <div class="relative inline-block w-12 align-middle select-none mr-2">
                        <input type="checkbox" name="toggle" id="modeToggle" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" <?= $currentMode === 'overall' ? 'checked' : '' ?> onclick="updateModeInput()">
                        <label for="modeToggle" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                    </div>
                    <span id="modeLabel" class="text-xs font-bold <?= $currentMode === 'overall' ? 'text-blue-600' : 'text-slate-600' ?>">
                        <?= $currentMode === 'overall' ? 'GABUNGAN (OVERALL)' : 'PER KELOMPOK UMUR (SPLIT)' ?>
                    </span>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="<?= $prevUrl ?>" class="h-10 px-4 flex items-center justify-center rounded-l-lg font-bold text-xs uppercase transition border-r border-slate-600 <?= $prevClass ?>">&laquo; PREV</a>
                <div class="flex bg-slate-100 rounded-none p-1 gap-1">
                    <a href="index.php" class="h-8 px-3 flex items-center bg-white border border-slate-300 rounded text-slate-600 font-bold text-[10px] uppercase hover:bg-slate-50">Menu</a>
                    <a href="input_result.php?category_id=<?= $cat_id ?>&export_txt=1" class="h-8 px-3 flex items-center bg-teal-500 text-white rounded font-bold text-[10px] uppercase hover:bg-teal-600 gap-1" title="Download Data ke TXT Format Stopwatch">📤 EXPORT TXT</a>
                    <button type="button" onclick="document.getElementById('txtUploadForm').classList.toggle('hidden')" class="h-8 px-3 flex items-center bg-emerald-500 text-white rounded font-bold text-[10px] uppercase hover:bg-emerald-600 gap-1" title="Import TXT Backup dari Stopwatch">📝 IMPORT TXT</button>
                    <button type="button" onclick="window.print()" class="h-8 px-3 flex items-center bg-orange-500 text-white rounded font-bold text-[10px] uppercase hover:bg-orange-600 gap-1">🖨️ PDF</button>
                    <button type="submit" form="formResult" class="h-8 px-4 flex items-center bg-blue-600 text-white rounded font-bold text-[10px] uppercase hover:bg-blue-700 gap-1 shadow-sm">💾 SIMPAN</button>
                </div>
                <a href="<?= $nextUrl ?>" class="h-10 px-4 flex items-center justify-center rounded-r-lg font-bold text-xs uppercase transition border-l border-slate-600 <?= $nextClass ?>">NEXT &raquo;</a>
            </div>
        </div>
        
        <!-- Form Upload TXT Hidden -->
        <div id="txtUploadForm" class="hidden w-full border-t pt-3 mt-1 bg-emerald-50 p-3 rounded-lg border border-emerald-200">
            <label class="block text-xs font-bold text-emerald-700 mb-2">Import Hasil Lomba dari File .TXT Stopwatch (Fallback)</label>
            <form method="POST" enctype="multipart/form-data" class="flex gap-2 items-center">
                <input type="file" name="txt_backup" accept=".txt" required class="text-xs w-full p-1 bg-white border border-emerald-200 rounded">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-1.5 rounded font-bold text-xs whitespace-nowrap shadow-sm">Upload & Sinkron</button>
            </form>
        </div>

        <div class="w-full border-t pt-3 mt-1">
            <label for="driveLink" class="block text-xs font-bold text-blue-500 mb-1 text-center">🔗 Link GDrive / Web (Untuk QR Code di PDF):</label>
            <input type="text" id="driveLink" class="w-full p-2 border border-dashed border-blue-300 rounded bg-blue-50 text-center text-xs" placeholder="Tempel link file hasil di sini... (Auto Save)">
        </div>
    </div>

    <form id="formResult" method="POST">
        <input type="hidden" name="rank_mode_input" id="rankModeInput" value="<?= $currentMode ?>">

        <div class="screen-only max-w-4xl mx-auto bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <div class="text-center mb-6 pb-4 border-b">
                <h1 class="text-2xl font-black text-slate-800 uppercase italic">ACARA #<?= $nomor_acara ?></h1>
                <p class="font-bold text-slate-600"><?= $judul_tengah ?></p>
            </div>
            
            <?php if(empty($heats)): ?>
                <div class="text-center py-12"><p class="italic text-slate-400">Belum ada peserta di nomor acara ini.</p></div>
            <?php else: ?>
                <?php foreach($heats as $heatNo => $lanesData): ?>
                <div class="mb-8">
                    <div class="text-right font-bold text-sm border-b-2 border-slate-800 mb-2">SERI <?= str_pad($heatNo, 2, '0', STR_PAD_LEFT) ?></div>
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-slate-700 uppercase bg-slate-100">
                            <tr>
                                <th class="px-2 py-2 text-center w-12">LN</th>
                                <th class="px-2 py-2">NAMA ATLET</th>
                                <th class="px-2 py-2 text-center">KU</th>
                                <th class="px-2 py-2">TIM</th>
                                <th class="px-2 py-2 text-right w-32">WAKTU</th>
                                <th class="px-2 py-2 text-center w-24">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for($ln = 1; $ln <= $total_lintasan; $ln++): $s = $lanesData[$ln] ?? null; ?>
                            <tr class="border-b hover:bg-slate-50">
                                <td class="px-2 py-2 text-center font-bold"><?= $ln ?></td>
                                <?php if($s): 
                                    // Logika membedakan DQ (dengan pasal) dan DNS/DNF
                                    $is_real_dq = (($s['is_dq']??0) == 1 && !in_array($s['dq_reason'], ['DNF', 'DNS', '']));
                                    $dq_text = $is_real_dq ? ($s['dq_reason'] ?? '') : '';
                                ?>
                                    <td class="px-2 py-2 font-bold"><?= shortenName($s['nama_atlet']) ?></td>
                                    <td class="px-2 py-2 text-center text-xs text-slate-500"><?= getAgeGroupLabel($s['tanggal_lahir'], $eventYear, $ageGroups) ?></td>
                                    <td class="px-2 py-2 text-xs"><?= shortenName(getTeamName($s, $participationType)) ?></td>
                                    <td class="px-2 py-2 text-right">
                                        <input type="text" name="entries[<?= $s['id'] ?>][time]" value="<?= htmlspecialchars($s['final_time'] ?? '') ?>" class="input-time" id="time_<?= $s['id'] ?>" autocomplete="off" <?= (($s['is_dq']??0) == 1) ? 'disabled style="background:#eee;color:#ccc;"' : '' ?>>
                                    </td>
                                    <td class="px-2 py-2 text-center relative">
                                        <select name="entries[<?= $s['id'] ?>][status]" id="status_<?= $s['id'] ?>" class="input-status" onchange="handleStatusChange(this, '<?= $s['id'] ?>')">
                                            <option value="" <?= empty($s['dq_reason']) ? 'selected' : '' ?>></option>
                                            <option value="DQ" class="text-red-600 font-black" <?= $is_real_dq ? 'selected' : '' ?>>DQ</option>
                                            <option value="DNF" class="text-orange-600 font-black" <?= ($s['dq_reason']=='DNF') ? 'selected' : '' ?>>DNF</option>
                                            <option value="DNS" class="text-gray-500 font-black" <?= ($s['dq_reason']=='DNS') ? 'selected' : '' ?>>DNS</option>
                                        </select>
                                        <!-- Input Tersembunyi untuk menyimpan nilai pasal -->
                                        <input type="hidden" name="entries[<?= $s['id'] ?>][dq_reason]" id="dq_reason_<?= $s['id'] ?>" value="<?= htmlspecialchars($dq_text) ?>">
                                        
                                        <!-- Tampilan Teks Pasal di bawah Select -->
                                        <div id="dq_display_<?= $s['id'] ?>" class="text-[9px] text-red-600 font-bold mt-0.5 text-center truncate w-full" title="<?= htmlspecialchars($dq_text) ?>">
                                            <?= htmlspecialchars($dq_text) ?>
                                        </div>
                                    </td>
                                <?php else: ?>
                                    <td colspan="5" class="px-2 py-2 text-slate-300 italic text-xs">&lt; KOSONG &gt;</td>
                                <?php endif; ?>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ======================= MODAL DQ RULES ======================= -->
        <!-- PERBAIKAN: Mengganti class 'screen-only' menjadi 'no-print' agar tidak terpaksa menjadi display: block -->
        <div id="dqModal" class="fixed inset-0 z-[1000] hidden bg-slate-900/50 backdrop-blur-sm items-center justify-center p-4 no-print">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50">
                    <div>
                        <h3 class="font-black text-slate-800 text-lg uppercase italic">Pilih Regulasi DQ</h3>
                        <p class="text-xs font-bold text-slate-500">Pilih pasal pelanggaran dari federasi.</p>
                    </div>
                    <button type="button" onclick="closeDqModal()" class="text-slate-400 hover:text-red-500 transition"><span class="text-2xl">&times;</span></button>
                </div>
                
                <div class="p-4 border-b bg-white">
                     <input type="text" id="searchDq" class="w-full border-slate-300 rounded-lg text-sm bg-slate-50 font-medium focus:ring-blue-500 focus:border-blue-500" placeholder="🔍 Cari pasal atau deskripsi pelanggaran..." onkeyup="filterDq()">
                </div>

                <div class="flex-1 overflow-y-auto p-4 bg-slate-50">
                    <div class="grid gap-2" id="dqList">
                        <?php foreach($dq_rules_list as $rule): ?>
                        <button type="button" onclick="selectDqRule('<?= htmlspecialchars($rule['pasal']) ?>')" class="dq-item text-left w-full bg-white border border-slate-200 hover:border-blue-500 hover:shadow-md p-3 rounded-xl transition flex gap-3 group">
                            <span class="bg-red-50 border border-red-200 text-red-700 font-black px-2 py-1 rounded text-xs h-fit whitespace-nowrap group-hover:bg-blue-100 group-hover:text-blue-700 transition">
                                <?= htmlspecialchars($rule['pasal']) ?>
                            </span>
                            <div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5"><?= htmlspecialchars($rule['kategori_gaya']) ?></div>
                                <div class="text-xs font-medium text-slate-700 leading-snug dq-desc"><?= htmlspecialchars($rule['deskripsi']) ?></div>
                            </div>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- ======================= END MODAL DQ ======================= -->

        <div class="print-only">
            
            <div class="header-fixed">
                <div style="text-align: left;">
                    <?php if($logoLeft): ?><img src="<?= $logoLeft ?>" class="logo-img"><?php endif; ?>
                </div>
                <div class="header-center">
                    <div class="header-line-1"><?= htmlspecialchars($eventName) ?></div>
                    <div class="header-line-2"><?= htmlspecialchars($venueName) ?></div>
                    <div class="header-line-3"><?= htmlspecialchars($dateRange) ?></div>
                    <div class="header-line-4"></div>
                    <div class="header-line-5">BUKU HASIL LOMBA</div>
                </div>
                <div style="text-align: right;">
                    <?php if($logoRight): ?><img src="<?= $logoRight ?>" class="logo-img"><?php endif; ?>
                </div>
            </div>

            <div class="footer-fixed">
                <div class="footer-sponsors">
                    <?php if(!empty($sponsors)): ?>
                        <?php foreach($sponsors as $img): ?>
                            <img src="<?= BASE_URL . '/public/' . ltrim($img, '/') ?>">
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="footer-time">Printed: <?= date('d/m/Y H:i') ?></div>
            </div>

            <div class="page-wrapper">
                <table class="layout-table">
                    <thead><tr><td><div class="layout-header-space"></div></td></tr></thead>
                    <tfoot><tr><td><div class="layout-footer-space"></div></td></tr></tfoot>
                    <tbody>
                        <tr>
                            <td>
                                <div class="event-header">
                                    <div class="eh-left">
                                        <div class="eh-number">ACARA #<?= $nomor_acara ?></div>
                                        <div class="eh-date"><?= htmlspecialchars($dateRange) ?></div>
                                    </div>
                                    <div class="eh-center"><?= $judul_print ?></div>
                                    <div class="eh-right">
                                        <img id="qrResultImage" src="<?= $default_qr_api ?>" class="qr-header" alt="QR Code">
                                    </div>
                                </div>

                                <div class="event-records-container" style="border:none; padding:0; margin-bottom:10px;">
                                    <?php 
                                    $records = [];
                                    
                                    // 1. Ambil Rekornas
                                    $stmtRec = $pdo->prepare("SELECT record_type, holder_name, record_time, location, record_year FROM master_records WHERE distance = ? AND stroke = ? AND jenis_kelamin = ? AND record_type = 'rekornas' ORDER BY id ASC");
                                    $stmtRec->execute([$raceInfo['distance'], $raceInfo['stroke'], $raceInfo['jenis_kelamin']]);
                                    $records = array_merge($records, $stmtRec->fetchAll(PDO::FETCH_ASSOC));

                                    // 2. Ambil Rekor Acuan
                                    if (!empty($eventProfile['record_package_id'])) {
                                        $stmtPkg = $pdo->prepare("
                                            SELECT 'rekor_event' as record_type, ehr.holder_name, ehr.record_time, e.event_city as location, YEAR(e.event_date_start) as record_year 
                                            FROM event_historical_records ehr 
                                            LEFT JOIN events e ON ehr.source_event_id = e.id
                                            WHERE ehr.package_id = ? AND ehr.distance = ? AND ehr.stroke = ? AND ehr.jenis_kelamin = ? AND ehr.age_group = ?
                                        ");
                                        $stmtPkg->execute([$eventProfile['record_package_id'], $raceInfo['distance'], $raceInfo['stroke'], $raceInfo['jenis_kelamin'], $raceInfo['age_group']]);
                                        $records = array_merge($records, $stmtPkg->fetchAll(PDO::FETCH_ASSOC));
                                    }
                                    
                                    if(!empty($records)):
                                        ?>
                                        <table style="width: 100%; border-collapse: collapse; font-size: 8pt; font-family: 'Arial Narrow', sans-serif; font-weight: bold; border-bottom: 1px solid #000; text-transform: uppercase;">
                                            <thead>
                                                <tr style="border-bottom: 1px solid #000;">
                                                    <th style="text-align: left; padding: 2px 0; width: 140px; color: #000;">REKOR</th>
                                                    <th style="text-align: left; padding: 2px 0; color: #000;">NAMA ATLET</th>
                                                    <th style="text-align: left; padding: 2px 0; width: 180px; color: #000;">LOKASI</th>
                                                    <th style="text-align: center; padding: 2px 0; width: 60px; color: #000;">TAHUN</th>
                                                    <th style="text-align: right; padding: 2px 0; width: 80px; color: #000;">WAKTU</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                        <?php
                                        foreach($records as $rec):
                                            $tipeLabel = strtoupper(str_replace('_', ' ', $rec['record_type']));
                                            if($tipeLabel === 'REKORNAS') $tipeLabel = 'REKOR NAS';
                                            
                                            $lokasiDisplay = !empty($rec['location']) ? strtoupper($rec['location']) : '-';
                                            $tahunDisplay = !empty($rec['record_year']) ? $rec['record_year'] : '-';
                                            ?>
                                            <tr>
                                                <td style="padding: 2px 0; color: #000;"><?= $tipeLabel ?></td>
                                                <td style="padding: 2px 0; color: #000;"><?= strtoupper($rec['holder_name']) ?></td>
                                                <td style="padding: 2px 0; color: #000;"><?= $lokasiDisplay ?></td>
                                                <td style="text-align: center; padding: 2px 0; color: #000;"><?= $tahunDisplay ?></td>
                                                <td style="text-align: right; padding: 2px 0; color: #000;"><?= $rec['record_time'] ?></td>
                                            </tr>
                                            <?php 
                                        endforeach;
                                        ?>
                                            </tbody>
                                        </table>
                                        <?php
                                    else:
                                        echo "<div style='height:2px;'></div>";
                                    endif; 
                                    ?>
                                </div>

                                <?php if(empty($groupedResults)): ?>
                                    <div style="text-align:center; padding: 50px;">BELUM ADA HASIL</div>
                                <?php else: ?>
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th class="col-rank">RANK</th> 
                                                <th class="col-uid">UID</th> 
                                                <th class="col-nama">NAMA ATLET</th> 
                                                <th class="col-ku">KU</th> 
                                                <th class="col-tim">TIM / SEKOLAH</th> 
                                                <th class="col-waktu-awal">ENTRY</th> 
                                                <th class="col-hasil">HASIL</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($groupedResults as $groupTitle => $swimmers): ?>
                                                <tr class="group-row"><td colspan="7"><?= htmlspecialchars($groupTitle) ?></td></tr>
                                                <?php 
                                                $rank = 1; $real_rank = 1; $prev_time = null;
                                                foreach($swimmers as $p): 
                                                    $is_valid = (($p['is_dq']??0) == 0 && !empty($p['final_time']) && $p['final_time'] != 'NT');
                                                    $rank_display = '';
                                                    if ($is_valid) {
                                                        if ($p['ms_sort'] !== $prev_time) { $real_rank = $rank; }
                                                        $rank_display = $real_rank;
                                                        $prev_time = $p['ms_sort'];
                                                        $rank++;
                                                    }
                                                    $seedTime = (!empty($p['entry_time']) && $p['entry_time'] != '99:99.99') ? $p['entry_time'] : 'NT';
                                                ?>
                                                <tr>
                                                    <td class="col-rank"><?= $rank_display ?></td>
                                                    <td class="col-uid"><?= htmlspecialchars($p['uid'] ?? '-') ?></td>
                                                    <td class="col-nama"><?= shortenName($p['nama_atlet']) ?></td>
                                                    <td class="col-ku"><?= getAgeGroupLabel($p['tanggal_lahir'], $eventYear, $ageGroups) ?></td>
                                                    <td class="col-tim"><?= shortenName(getTeamName($p, $participationType)) ?></td>
                                                    <td class="col-waktu-awal"><?= $seedTime ?></td>
                                                    <td class="col-hasil">
                                                        <?php 
                                                        if (($p['is_dq']??0) == 1) { 
                                                            $reason = $p['dq_reason'] ?? 'DQ';
                                                            $print_text = (in_array($reason, ['DNS', 'DNF'])) ? $reason : 'DQ';
                                                            echo '<span style="color:red;">' . $print_text . '</span>'; 
                                                        } elseif ($is_valid) { 
                                                            echo $p['final_time']; 
                                                        } else { 
                                                            echo '-'; 
                                                        } 
                                                        ?>
                                                </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<script>
// --- LOGIKA MODAL DQ ---
let currentDqSwimmerId = null;
const modalDq = document.getElementById('dqModal');
const searchInput = document.getElementById('searchDq');

function handleStatusChange(selectElem, id) {
    if (selectElem.value === 'DQ') {
        // Jika pilih DQ, buka modal
        currentDqSwimmerId = id;
        
        // Tampilkan modal dengan menghapus 'hidden' dan menambah 'flex'
        modalDq.classList.remove('hidden');
        modalDq.classList.add('flex');
        
        // Reset input pencarian
        searchInput.value = '';
        filterDq();
        setTimeout(() => searchInput.focus(), 100); // Beri sedikit jeda sebelum fokus
        
    } else {
        // Jika pilih opsi lain (DNS, DNF, KOSONG), reset text DQ
        document.getElementById('dq_reason_' + id).value = '';
        document.getElementById('dq_display_' + id).innerText = '';
        toggleTimeInput(selectElem, id);
    }
}

function selectDqRule(pasal) {
    if (currentDqSwimmerId) {
        // Simpan pasal ke input hidden
        document.getElementById('dq_reason_' + currentDqSwimmerId).value = pasal;
        // Tampilkan pasal di bawah select box
        document.getElementById('dq_display_' + currentDqSwimmerId).innerText = pasal;
        
        let selectElem = document.getElementById('status_' + currentDqSwimmerId);
        toggleTimeInput(selectElem, currentDqSwimmerId);
    }
    closeDqModal();
}

function closeDqModal() {
    // Sembunyikan modal
    modalDq.classList.remove('flex');
    modalDq.classList.add('hidden');
    
    // Jika user menutup modal dengan tombol X (tanpa memilih pasal), kembalikan status ke kosong
    if (currentDqSwimmerId) {
        let hiddenInput = document.getElementById('dq_reason_' + currentDqSwimmerId);
        let selectElem = document.getElementById('status_' + currentDqSwimmerId);
        
        // Cek jika hidden value kosong (berarti batal pilih)
        if (!hiddenInput.value || hiddenInput.value.trim() === '') {
            selectElem.value = ""; // Kembalikan opsi ke kosong
            toggleTimeInput(selectElem, currentDqSwimmerId);
        }
    }
    
    // Reset ID agar tidak bocor ke atlet lain
    currentDqSwimmerId = null;
}

function filterDq() {
    let input = searchInput.value.toLowerCase();
    let items = document.querySelectorAll('.dq-item');
    items.forEach(item => {
        let text = item.innerText.toLowerCase();
        // Gunakan flex karena awalnya display flex
        item.style.display = text.includes(input) ? 'flex' : 'none'; 
    });
}
// --- END LOGIKA MODAL DQ ---

function toggleTimeInput(selectElem, id) {
    const timeInput = document.getElementById('time_' + id);
    if (selectElem.value !== "") {
        timeInput.disabled = true; 
        timeInput.style.backgroundColor = "#eee"; 
        timeInput.style.color = "#ccc"; 
        timeInput.value = ""; 
    } else {
        timeInput.disabled = false; 
        timeInput.style.backgroundColor = "#f9f9f9"; 
        timeInput.style.color = "blue";
    }
}

function updateModeInput() {
    const checkbox = document.getElementById('modeToggle');
    const hiddenInput = document.getElementById('rankModeInput');
    const label = document.getElementById('modeLabel');
    if (checkbox.checked) { 
        hiddenInput.value = 'overall'; 
        label.innerText = 'GABUNGAN (OVERALL)'; 
        label.classList.remove('text-slate-600'); 
        label.classList.add('text-blue-600');
    } else { 
        hiddenInput.value = 'split'; 
        label.innerText = 'PER KELOMPOK UMUR (SPLIT)'; 
        label.classList.remove('text-blue-600'); 
        label.classList.add('text-slate-600'); 
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const inputLink = document.getElementById("driveLink");
    const qrImage = document.getElementById("qrResultImage");
    const storageKey = "qr_link_cat_<?= $cat_id ?>"; 
    const defaultLink = "<?= $default_target_link ?>";
    
    function updateQR(url) {
        let finalUrl = url; 
        if (!url || url.trim() === "") { finalUrl = defaultLink; }
        qrImage.src = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&margin=0&data=" + encodeURIComponent(finalUrl);
    }
    
    const savedLink = localStorage.getItem(storageKey);
    if (savedLink) { 
        inputLink.value = savedLink; 
        updateQR(savedLink); 
    } else { 
        updateQR(""); 
    }
    
    inputLink.addEventListener("input", function() { 
        localStorage.setItem(storageKey, this.value); 
        updateQR(this.value); 
    });
    
    document.querySelectorAll('.input-time').forEach(function(input) {
        input.addEventListener('input', function(e) {
            let val = this.value.replace(/\D/g, '');
            if (val.length > 6) { val = val.substring(0, 6); }
            let formatted = '';
            if (val.length > 0) { formatted += val.substring(0, 2); }
            if (val.length > 2) { formatted += ':' + val.substring(2, 4); }
            if (val.length > 4) { formatted += '.' + val.substring(4, 6); }
            this.value = formatted;
        });
    });
});
</script>
</body>
</html>