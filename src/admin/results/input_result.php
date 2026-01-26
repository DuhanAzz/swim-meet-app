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

// CEK MODE TAMPILAN
if (isset($_GET['mode'])) {
    $_SESSION['ranking_mode_' . $cat_id] = $_GET['mode'];
}
$currentMode = $_SESSION['ranking_mode_' . $cat_id] ?? 'split'; 

// --- HELPER: TIME TO MS ---
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

// --- HELPER FORMATTING ---
function formatLahir($tgl, $year) {
    if(!$tgl || $tgl == '0000-00-00') return '-';
    $by = date('Y', strtotime($tgl));
    return $by . " (" . ($year - $by) . ")";
}
function shortenName($name) { return trim(preg_replace('/\s+/', ' ', $name ?? '')); }
function getTeamName($row, $type) {
    $club = trim($row['club_name'] ?? ''); $school = trim($row['asal_sekolah'] ?? '');
    if (stripos($type, 'sekolah') !== false || stripos($type, 'school') !== false) {
        return !empty($school) ? $school : (!empty($club) ? $club : '-');
    } else {
        return !empty($club) ? $club : (!empty($school) ? $school : '-');
    }
}

// --- DATA EVENT ---
$stmtRace = $pdo->prepare("SELECT * FROM event_numbers WHERE id = ?");
$stmtRace->execute([$cat_id]);
$raceInfo = $stmtRace->fetch(PDO::FETCH_ASSOC);
if (!$raceInfo) die("Nomor lomba tidak ditemukan.");

// AMBIL PROFIL EVENT (UNTUK TAHUN EVENT & DATA KU)
$eventId = $raceInfo['event_id'] ?? 0; // Pastikan event_id ada di tabel event_numbers
// Jika event_id kosong, fallback ke organizer_id (meski kurang akurat jika 1 organizer punya banyak event)
if (empty($eventId)) {
    $stmtEvent = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtEvent->execute([$raceInfo['organizer_id']]);
    $eventProfile = $stmtEvent->fetch(PDO::FETCH_ASSOC);
    $eventId = $eventProfile['id'];
} else {
    $stmtEvent = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmtEvent->execute([$eventId]);
    $eventProfile = $stmtEvent->fetch(PDO::FETCH_ASSOC);
}

$event_year = date('Y', strtotime($eventProfile['event_start_date']));

// AMBIL DEFINISI AGE GROUP DARI DATABASE
$stmtAgeGroups = $pdo->prepare("SELECT * FROM event_age_groups WHERE event_id = ? ORDER BY min_age ASC");
$stmtAgeGroups->execute([$eventId]);
$ageGroups = $stmtAgeGroups->fetchAll(PDO::FETCH_ASSOC);

// Helper function untuk menentukan Nama KU berdasarkan Tanggal Lahir
function getAgeGroupLabel($dob, $eventYear, $ageGroups) {
    if (empty($dob) || $dob == '0000-00-00') return 'UMUR TIDAK DIKETAHUI';
    
    $birthYear = date('Y', strtotime($dob));
    $age = $eventYear - $birthYear;
    
    foreach ($ageGroups as $group) {
        if ($age >= $group['min_age'] && $age <= $group['max_age']) {
            return $group['group_name']; // Misal: "KU 3" atau "KU 2019"
        }
    }
    
    return "DILUAR KATEGORI ($age TH)"; // Fallback jika tidak masuk range manapun
}


// --- PROSES SIMPAN DATA (DATABASE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $entries = $_POST['entries'] ?? [];
        $rankModePost = $_POST['rank_mode_input'] ?? 'split'; 
        
        $_SESSION['ranking_mode_' . $cat_id] = $rankModePost;
        $currentMode = $rankModePost;

        $stmtUpd = $pdo->prepare("UPDATE event_entries SET final_time = ?, is_dq = ?, dq_reason = ? WHERE id = ?");

        foreach ($entries as $id => $data) {
            $time = trim($data['time'] ?? '');
            $status = $data['status']; 
            $is_dq = ($status !== '') ? 1 : 0;
            $reason = ($status !== '') ? $status : NULL;
            if ($is_dq) $time = NULL; 
            if ($time === '') $time = NULL;
            $stmtUpd->execute([$time, $is_dq, $reason, $id]);
        }

        // LOGIKA RANKING
        $stmtAll = $pdo->prepare("
            SELECT ee.id, ee.final_time, ee.is_dq, s.tanggal_lahir 
            FROM event_entries ee 
            JOIN swimmers s ON ee.swimmer_id = s.id 
            WHERE ee.category_id = ?
        ");
        $stmtAll->execute([$cat_id]);
        $allSwimmers = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        $stmtRank = $pdo->prepare("UPDATE event_entries SET final_rank = ? WHERE id = ?");

        if ($rankModePost === 'overall') {
            // MODE GABUNGAN (OVERALL)
            $valid = []; $invalid = [];
            foreach ($allSwimmers as $s) {
                if ($s['is_dq'] == 0 && !empty($s['final_time']) && $s['final_time'] != 'NT') {
                    $s['ms'] = timeToMs($s['final_time']);
                    $valid[] = $s;
                } else { $invalid[] = $s; }
            }
            usort($valid, function($a, $b) { return $a['ms'] - $b['ms']; });

            $rank = 1; $counter = 1; $prevMs = null;
            foreach ($valid as $s) {
                if ($prevMs !== null && $s['ms'] != $prevMs) { $rank = $counter; }
                $stmtRank->execute([$rank, $s['id']]);
                $prevMs = $s['ms']; $counter++;
            }
            foreach ($invalid as $s) { $stmtRank->execute([NULL, $s['id']]); }
            $msg_success = "Data disimpan! Ranking dihitung GABUNGAN (OVERALL).";

        } else {
            // MODE PER KELOMPOK UMUR (SPLIT BY AGE GROUP)
            $groupedSwimmers = [];
            foreach ($allSwimmers as $s) {
                // Tentukan Grup berdasarkan tabel event_age_groups
                $groupName = getAgeGroupLabel($s['tanggal_lahir'], $event_year, $ageGroups);
                $groupedSwimmers[$groupName][] = $s;
            }
            
            // Loop per Grup KU
            foreach ($groupedSwimmers as $groupName => $swimmersInGroup) {
                $valid = []; $invalid = [];
                foreach ($swimmersInGroup as $s) {
                    if ($s['is_dq'] == 0 && !empty($s['final_time']) && $s['final_time'] != 'NT') {
                        $s['ms'] = timeToMs($s['final_time']);
                        $valid[] = $s;
                    } else { $invalid[] = $s; }
                }
                usort($valid, function($a, $b) { return $a['ms'] - $b['ms']; });

                $rank = 1; $counter = 1; $prevMs = null;
                foreach ($valid as $s) {
                    if ($prevMs !== null && $s['ms'] != $prevMs) { $rank = $counter; }
                    $stmtRank->execute([$rank, $s['id']]);
                    $prevMs = $s['ms']; $counter++;
                }
                foreach ($invalid as $s) { $stmtRank->execute([NULL, $s['id']]); }
            }
            $msg_success = "Data disimpan! Ranking dihitung PER KELOMPOK UMUR (DATABASE).";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack(); $msg_error = "Gagal menyimpan: " . $e->getMessage();
    }
}

// NAVIGASI
$currentOrganizerId = $raceInfo['organizer_id'];
$stmtPrev = $pdo->prepare("SELECT id FROM event_numbers WHERE organizer_id = ? AND id < ? ORDER BY id DESC LIMIT 1");
$stmtPrev->execute([$currentOrganizerId, $cat_id]);
$rowPrev = $stmtPrev->fetch(PDO::FETCH_ASSOC);
$prevUrl = $rowPrev ? "input_result.php?category_id=" . $rowPrev['id'] : "#";
$prevClass = $rowPrev ? "bg-slate-700 hover:bg-slate-800 text-white" : "bg-slate-200 text-slate-400 cursor-not-allowed pointer-events-none";

$stmtNext = $pdo->prepare("SELECT id FROM event_numbers WHERE organizer_id = ? AND id > ? ORDER BY id ASC LIMIT 1");
$stmtNext->execute([$currentOrganizerId, $cat_id]);
$rowNext = $stmtNext->fetch(PDO::FETCH_ASSOC);
$nextUrl = $rowNext ? "input_result.php?category_id=" . $rowNext['id'] : "#";
$nextClass = $rowNext ? "bg-slate-700 hover:bg-slate-800 text-white" : "bg-slate-200 text-slate-400 cursor-not-allowed pointer-events-none";

// VARIABLES HEADER
$header_title = strtoupper($eventProfile['nama_event'] ?? 'KEJUARAAN RENANG');
$venue_name   = strtoupper($eventProfile['venue_name'] ?? ($eventProfile['lokasi'] ?? ''));
$display_date = strtoupper(date('d F Y', strtotime($eventProfile['event_start_date'])));

if(!empty($eventProfile['event_end_date']) && strtotime($eventProfile['event_start_date']) != strtotime($eventProfile['event_end_date'])) {
    $header_date_range = date('d', strtotime($eventProfile['event_start_date'])) . ' - ' . date('d F Y', strtotime($eventProfile['event_end_date']));
} else { $header_date_range = $display_date; }

$total_lintasan = (int)($eventProfile['lane_count'] ?? 8);
$pool_type    = strtoupper($eventProfile['pool_type'] ?? 'LCM');
$poolSuffix   = ($pool_type == 'SCM') ? ' - SCM' : ' - LCM';
$participationType = $eventProfile['participation_type'] ?? 'club';

$logo_left  = !empty($eventProfile['logo_left']) ? '../../../public/' . $eventProfile['logo_left'] : null;
$logo_right = !empty($eventProfile['logo_right']) ? '../../../public/' . $eventProfile['logo_right'] : null;

$cleanStroke = trim(str_ireplace(['Gaya', 'GAYA'], '', $raceInfo['stroke'] ?? ''));
$gender_label = (in_array($raceInfo['jenis_kelamin'], ['L','Male','Man'])) ? 'PUTRA' : 'PUTRI';
$judul_tengah = $raceInfo['distance'] . " M GAYA " . strtoupper($cleanStroke) . " - " . ($raceInfo['age_group']??'') . " " . $gender_label . $poolSuffix;
$nomor_acara = "#" . $raceInfo['event_number'];

// SPONSOR (Dipakai di Footer)
$stmtSpon = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE event_id = ?");
$stmtSpon->execute([$eventId]);
$sponsors = $stmtSpon->fetchAll(PDO::FETCH_COLUMN);

// DATA PESERTA
try {
    $sql = "SELECT ee.*, 
            s.nama_atlet, s.tanggal_lahir, s.asal_sekolah,
            u.nama_lengkap as club_name
            FROM event_entries ee
            JOIN swimmers s ON ee.swimmer_id = s.id
            LEFT JOIN users u ON ee.club_id = u.id 
            WHERE ee.category_id = ? AND ee.heat IS NOT NULL 
            ORDER BY ee.heat ASC, ee.lane ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cat_id]);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Error Database: " . $e->getMessage()); }

$heats = [];
foreach ($raw_data as $row) { $heats[$row['heat']][$row['lane']] = $row; }

// GROUPING RESULTS UNTUK TAMPILAN CETAK
$groupedResults = [];

if ($currentMode === 'overall') {
    // MODE GABUNGAN
    $kelompokUmurLabel = $raceInfo['age_group'] ?? 'SEMUA UMUR';
    $groupTitle = "KELOMPOK UMUR (" . $kelompokUmurLabel . ")";

    foreach ($raw_data as $row) {
        if (!empty($row['swimmer_id'])) {
            $row['ms_sort'] = 9999999999;
            if (($row['is_dq']??0) == 1) { $row['ms_sort'] = 9999999999 + 100; } 
            elseif (!empty($row['final_time']) && $row['final_time'] != 'NT') {
                $row['ms_sort'] = timeToMs($row['final_time']);
            }
            $groupedResults[$groupTitle][] = $row;
        }
    }
} else {
    // MODE SPLIT BY AGE GROUP (DATABASE)
    foreach ($raw_data as $row) {
        if (!empty($row['swimmer_id'])) {
            // Gunakan fungsi helper yang sama untuk konsistensi tampilan
            $groupName = getAgeGroupLabel($row['tanggal_lahir'], $event_year, $ageGroups);
            
            $row['ms_sort'] = 9999999999; 
            if (($row['is_dq']??0) == 1) { $row['ms_sort'] = 9999999999 + 100; } 
            elseif (!empty($row['final_time']) && $row['final_time'] != 'NT') {
                $row['ms_sort'] = timeToMs($row['final_time']);
            }
            $groupedResults[$groupName][] = $row;
        }
    }
    // Urutkan Nama Grup secara Ascending (Misal: KU 2019, KU 2018...)
    // Jika nama grup mengandung angka, ksort() biasanya sudah cukup rapi
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
$default_qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&margin=0&data=" . urlencode($default_target_link);

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700;900&family=Courier+Prime:wght@400;700&display=swap');
    .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
    .font-mono { font-family: 'Courier Prime', monospace; }

    /* TOGGLE SWITCH UI */
    .toggle-checkbox { display: none; }
    .toggle-label {
        width: 44px; height: 24px;
        background-color: #cbd5e1; border-radius: 9999px; position: relative; cursor: pointer;
        transition: background-color 0.3s ease; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
    }
    .toggle-label::after {
        content: ''; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px;
        background-color: white; border-radius: 50%; transition: transform 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
        box-shadow: 0 1px 3px rgba(0,0,0,0.3);
    }
    .toggle-checkbox:checked + .toggle-label { background-color: #3b82f6; }
    .toggle-checkbox:checked + .toggle-label::after { transform: translateX(20px); }
    
    /* PAPER & PRINT STYLES */
    .paper-sheet { width: 210mm; min-height: 297mm; background: white; margin: 0 auto; padding: 5mm 10mm 25mm 10mm; color: #000; position: relative; font-family: 'Roboto Condensed', sans-serif; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .page-header { padding: 5px 0 10px 0; border-bottom: 2px double #000; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; width: 100%; }
    .logo-box { width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; }
    .logo-box img { max-height: 100%; max-width: 100%; object-fit: contain; }
    .event-header-grid { display: grid; grid-template-columns: 80px 1fr 50px 90px; gap: 5px; align-items: center; border-bottom: 1px solid #000; margin-bottom: 10px; padding-bottom: 5px; }
    .event-number { font-size: 14pt; font-weight: 900; line-height: 1; }
    .event-title { font-size: 11pt; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .babak-badge { font-size: 9pt; padding: 2px 5px; }
    .qr-box { display: flex; flex-direction: column; align-items: center; justify-content: center; }
    
    .heat-wrapper { margin-bottom: 10px; break-inside: avoid; }
    .heat-header { text-align: right; font-weight: bold; font-size: 9pt; border-bottom: 1px solid #000; margin-bottom: 1px; }
    .heat-table, .rank-table { width: 100%; border-collapse: collapse; font-size: 9pt; table-layout: fixed; }
    .heat-table th, .rank-table th { background: #f0f0f0; border-bottom: 1px solid #000; border-top: 1px solid #000; padding: 2px 4px; font-weight: bold; text-transform: uppercase; }
    .heat-table td, .rank-table td { border-bottom: 1px solid #ccc; padding: 4px; vertical-align: middle; white-space: normal; line-height: 1.1; }
    .col-center { text-align: center; } .col-left { text-align: left; } .col-right { text-align: right; }
    .year-header { background: #333; color: #fff; font-weight: 900; padding: 3px 5px; font-size: 10pt; text-transform: uppercase; margin-bottom: 0; margin-top: 10px; display: inline-block; border-radius: 3px 3px 0 0; }
    
    .input-time { width: 100%; border: 1px solid #ccc; background: #f9f9f9; padding: 2px; font-family: 'Courier Prime', monospace; font-weight: bold; text-align: right; font-size: 10pt; color: blue; outline: none; border-radius: 4px; }
    .input-status { width: 100%; border: none; background: transparent; font-size: 8pt; font-weight: bold; text-align: center; cursor: pointer; }
    
    /* FOOTER STYLES (SCREEN & PRINT) */
    .footer-sponsor { 
        display: none; /* Hidden on input screen to save space */
    }

    .print-only { display: none; } .screen-only { display: block; }

    @media print {
        @page { size: A4; margin: 5mm; }
        nav, aside, .no-print, .alert-box { display: none !important; }
        .p-4, .sm\:ml-64, .pt-24 { padding: 0 !important; margin: 0 !important; }
        .min-h-screen { min-height: auto !important; }
        .paper-sheet { width: 100% !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; padding-bottom: 30mm !important; }
        .screen-only { display: none !important; } .print-only { display: block !important; }
        
        /* FOOTER TETAP DI BAWAH SETIAP HALAMAN */
        .footer-sponsor { 
            display: flex !important;
            position: fixed; bottom: 0; left: 0; right: 0; 
            padding-bottom: 2mm; background: white; z-index: 9999; 
            flex-direction: column; align-items: center;
        }
        .sponsor-line-separator { width: 95%; border-top: 2px double #000; margin-bottom: 5px; }
        .sponsor-logo-container { display: flex; justify-content: center; align-items: center; gap: 15px; width: 100%; padding: 0 10px; }
        .sponsor-logo-container img { height: 35px; width: auto; object-fit: contain; filter: grayscale(100%); opacity: 0.9; }

        .rank-table th { background-color: #eee !important; -webkit-print-color-adjust: exact; }
        .rank-table tr:nth-child(even) td { background-color: #f8f8f8 !important; -webkit-print-color-adjust: exact; }
        .year-group { page-break-inside: avoid; }
    }
</style>

<div class="p-4 sm:ml-64 pt-24 min-h-screen bg-slate-100 text-slate-900 font-sans">

    <?php if(isset($msg_success)): ?>
        <div class="alert-box max-w-[210mm] mx-auto mb-4 bg-emerald-100 border border-emerald-400 text-emerald-800 px-4 py-3 rounded-lg flex items-center gap-2 shadow-sm sticky top-20 z-50 no-print">
            <span>✅</span> <strong><?= $msg_success ?></strong>
        </div>
    <?php endif; ?>

    <div class="no-print max-w-[210mm] mx-auto mb-6 flex flex-col items-center bg-white p-4 rounded-xl border border-slate-200 shadow-sm sticky top-20 z-40 gap-4">
        
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
                    <button onclick="window.print()" class="h-8 px-3 flex items-center bg-orange-500 text-white rounded font-bold text-[10px] uppercase hover:bg-orange-600 gap-1">🖨️ PDF</button>
                    <button type="submit" form="formResult" class="h-8 px-4 flex items-center bg-blue-600 text-white rounded font-bold text-[10px] uppercase hover:bg-blue-700 gap-1 shadow-sm">💾 SIMPAN</button>
                </div>
                <a href="<?= $nextUrl ?>" class="h-10 px-4 flex items-center justify-center rounded-r-lg font-bold text-xs uppercase transition border-l border-slate-600 <?= $nextClass ?>">NEXT &raquo;</a>
            </div>
        </div>

        <div class="w-full border-t pt-3 mt-1">
            <label for="driveLink" class="block text-xs font-bold text-blue-500 mb-1 text-center">🔗 Link Google Drive / PDF (Untuk QR Code):</label>
            <input type="text" id="driveLink" class="w-full p-2 border border-dashed border-blue-300 rounded bg-blue-50 text-center text-xs" placeholder="Tempel link file di sini... (Auto Save)">
        </div>
    </div>

    <form id="formResult" method="POST">
        <input type="hidden" name="rank_mode_input" id="rankModeInput" value="<?= $currentMode ?>">

        <div class="paper-sheet">
            <div class="page-header">
                <div class="logo-box"><?php if($logo_left): ?><img src="<?= $logo_left ?>"><?php endif; ?></div>
                <div class="text-center flex-1 px-4 header-content">
                    <h1 class="font-black uppercase leading-tight"><?= htmlspecialchars($header_title) ?></h1>
                    <?php if($venue_name): ?><p class="font-bold uppercase text-gray-800"><?= htmlspecialchars($venue_name) ?></p><?php endif; ?>
                    <p class="font-bold uppercase text-gray-500"><?= htmlspecialchars($header_date_range) ?></p>
                    <div class="inline-block border-2 border-black buku-acara-badge px-2 mt-1">
                        <p class="font-black uppercase tracking-[0.2em] leading-none text-sm">HASIL LOMBA</p>
                    </div>
                </div>
                <div class="logo-box"><?php if($logo_right): ?><img src="<?= $logo_right ?>"><?php endif; ?></div>
            </div>

            <div class="event-header-grid">
                <div class="event-num-box"><div class="event-number"><?= $nomor_acara ?></div><div class="text-[8pt] font-bold"><?= strtoupper($display_date) ?></div></div>
                <div class="event-title-box text-center"><div class="event-title"><?= $judul_tengah ?></div></div>
                <div class="qr-box">
                    <img id="qrResultImage" src="<?= $default_qr_api ?>" style="width: 50px; height: 50px; border: 1px solid #ddd; padding: 2px;">
                    <p id="printLinkText" class="print-only text-[6pt] text-center mt-1 text-gray-500">Web Result</p>
                </div>
                <div class="text-right"><span class="font-bold bg-gray-100 border border-gray-300 babak-badge rounded"><?= $currentMode === 'overall' ? 'OVERALL' : 'FINAL' ?></span></div>
            </div>

            <?php if(empty($heats)): ?>
                <div class="text-center py-12 border-y border-dashed border-gray-400 mt-10"><p class="italic">Belum ada peserta.</p></div>
            <?php else: ?>
                <div class="screen-only">
                    <?php foreach($heats as $heatNo => $lanesData): ?>
                    <div class="heat-wrapper">
                        <div class="heat-header">SERI <?= str_pad($heatNo, 2, '0', STR_PAD_LEFT) ?> (INPUT)</div>
                        <table class="heat-table">
                            <colgroup><col style="width: 5%;"><col style="width: 30%;"><col style="width: 10%;"><col style="width: 25%;"><col style="width: 20%;"><col style="width: 10%;"></colgroup>
                            <thead>
                                <tr>
                                    <th class="col-center">LN</th><th class="col-left">NAMA ATLET</th><th class="col-center">LHR</th>
                                    <th class="col-center">TIM / SEKOLAH</th><th class="col-right">WAKTU</th><th class="col-center">KET</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for($ln = 1; $ln <= $total_lintasan; $ln++): $s = $lanesData[$ln] ?? null; ?>
                                <tr>
                                    <td class="col-center font-bold font-mono"><?= $ln ?></td>
                                    <?php if($s): ?>
                                        <td class="col-left font-bold text-black"><?= shortenName($s['nama_atlet']) ?></td>
                                        <td class="col-center font-mono text-gray-700"><?= formatLahir($s['tanggal_lahir'], $event_year) ?></td>
                                        <td class="col-center text-gray-800"><?= shortenName(getTeamName($s, $participationType)) ?></td>
                                        <td class="col-right">
                                            <input type="text" name="entries[<?= $s['id'] ?>][time]" value="<?= htmlspecialchars($s['final_time'] ?? '') ?>" class="input-time" id="time_<?= $s['id'] ?>" autocomplete="off" <?= ($s['is_dq']??0) == 1 ? 'disabled style="background:#eee;color:#ccc;"' : '' ?>>
                                        </td>
                                        <td class="col-center">
                                            <select name="entries[<?= $s['id'] ?>][status]" class="input-status" onchange="toggleTimeInput(this, '<?= $s['id'] ?>')">
                                                <option value="" <?= empty($s['dq_reason']) ? 'selected' : '' ?>></option>
                                                <option value="DQ" class="text-red-600 font-black" <?= ($s['dq_reason']=='DQ') ? 'selected' : '' ?>>DQ</option>
                                                <option value="DNF" class="text-orange-600 font-black" <?= ($s['dq_reason']=='DNF') ? 'selected' : '' ?>>DNF</option>
                                                <option value="DNS" class="text-gray-500 font-black" <?= ($s['dq_reason']=='DNS') ? 'selected' : '' ?>>DNS</option>
                                            </select>
                                        </td>
                                    <?php else: ?>
                                        <td colspan="5" class="text-gray-300 italic text-[7pt] pl-2">&lt; KOSONG &gt;</td>
                                    <?php endif; ?>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="print-only">
                    <?php foreach($groupedResults as $groupTitle => $swimmers): ?>
                    <div class="year-group">
                        <div class="year-header"><?= $groupTitle ?></div>
                        <table class="rank-table" style="margin-top: 0;">
                            <colgroup><col style="width: 8%;"><col style="width: 30%;"><col style="width: 25%;"><col style="width: 17%;"><col style="width: 20%;"></colgroup>
                            <thead>
                                <tr>
                                    <th class="col-center">RANK</th><th class="col-left">NAMA ATLET</th>
                                    <th class="col-left">TIM / SEKOLAH</th><th class="col-right">PRESTASI</th><th class="col-right">HASIL</th>
                                </tr>
                            </thead>
                            <tbody>
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
                                    <td class="col-center font-bold text-[10pt]"><?= $rank_display ?></td>
                                    <td class="col-left font-bold">
                                        <?= shortenName($p['nama_atlet']) ?>
                                        <?php if($currentMode === 'overall'): ?>
                                            <span class="text-[8pt] font-normal text-gray-500 block"><?= date('Y', strtotime($p['tanggal_lahir'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-left"><?= shortenName(getTeamName($p, $participationType)) ?></td>
                                    <td class="col-right font-mono text-[9pt]"><?= $seedTime ?></td>
                                    <td class="col-right font-mono font-bold text-[10pt]">
                                        <?php if (($p['is_dq']??0) == 1) { echo '<span style="color:red;">'.($p['dq_reason'] ?? 'DQ').'</span>'; } 
                                              elseif ($is_valid) { echo $p['final_time']; } else { echo 'NT'; } ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

            <div class="footer-sponsor">
                <div class="sponsor-line-separator"></div>
                
                <?php if(!empty($sponsors)): ?>
                    <div class="sponsor-logo-container">
                        <?php foreach($sponsors as $img): ?>
                            <img src="../../../public/<?= $img ?>">
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-xs text-gray-300 italic mb-1">Generated by System</div>
                <?php endif; ?>

                <div class="w-full text-right px-10">
                    <p class="text-[8px] text-gray-400 font-mono uppercase">Dicetak: <?= date('d/m/Y H:i') ?></p>
                </div>
            </div>

        </div>
    </form>
    <div class="h-24"></div> 
</div>

<script>
function toggleTimeInput(selectElem, id) {
    const timeInput = document.getElementById('time_' + id);
    if (selectElem.value !== "") {
        timeInput.disabled = true; timeInput.style.backgroundColor = "#eee"; timeInput.style.color = "#ccc"; timeInput.value = ""; 
    } else {
        timeInput.disabled = false; timeInput.style.backgroundColor = "#f9f9f9"; timeInput.style.color = "blue";
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
    const printText = document.getElementById("printLinkText"); 
    const storageKey = "qr_link_cat_<?= $cat_id ?>"; 
    const defaultLink = "<?= $default_target_link ?>";

    function updateQR(url) {
        let finalUrl = url;
        if (!url || url.trim() === "") { finalUrl = defaultLink; }
        const apiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&margin=0&data=" + encodeURIComponent(finalUrl);
        qrImage.src = apiUrl;
        if (url && url.trim() !== "") { printText.innerText = "GDrive File"; } else { printText.innerText = "Web Result"; }
    }

    const savedLink = localStorage.getItem(storageKey);
    if (savedLink) { inputLink.value = savedLink; updateQR(savedLink); } else { updateQR(""); }

    inputLink.addEventListener("input", function() {
        localStorage.setItem(storageKey, this.value); 
        updateQR(this.value);
    });
});
</script>