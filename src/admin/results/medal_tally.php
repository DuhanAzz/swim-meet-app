<?php
// FILE: src/admin/results/medal_tally.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { die("Akses Ditolak"); }

$uid = $_SESSION['user_id'];
$eventId = $_GET['event_id'] ?? 0;

if ($eventId == 0) {
    $stmtLast = $pdo->prepare("SELECT id FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtLast->execute([$uid]);
    $eventId = $stmtLast->fetchColumn() ?: 0;
}

$stmtProfile = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtProfile->execute([$eventId]);
$raceInfo = $stmtProfile->fetch(PDO::FETCH_ASSOC);

if (!$raceInfo) { die("Data Event tidak ditemukan."); }

// Setup Variabel Header (Sesuai kolom database Buku Acara)
$eventName  = strtoupper($raceInfo['event_name'] ?? 'EVENT NAME');
$venueName  = strtoupper($raceInfo['event_location'] ?? '-');
$eventDate  = $raceInfo['event_date_start'] ?? date('Y-m-d');
$eventYear  = date('Y', strtotime($eventDate)); 
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'];
$logoLeft   = !empty($raceInfo['logo_left']) ? $protocol . "://" . $host . BASE_URL . '/public/' . ltrim($raceInfo['logo_left'], '/') : null;
$logoRight  = !empty($raceInfo['logo_right']) ? $protocol . "://" . $host . BASE_URL . '/public/' . ltrim($raceInfo['logo_right'], '/') : null;

$displayDate = strtoupper(date('d F Y', strtotime($eventDate)));
if(!empty($raceInfo['event_date_end']) && $raceInfo['event_date_end'] != '0000-00-00' && $raceInfo['event_date_end'] != $eventDate) {
    $dateRange = date('d', strtotime($eventDate)) . ' - ' . date('d F Y', strtotime($raceInfo['event_date_end']));
} else { $dateRange = $displayDate; }
$dateRange = strtoupper($dateRange);

$stmtSpon = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE event_id = ?");
$stmtSpon->execute([$eventId]); 
$sponsors = $stmtSpon->fetchAll(PDO::FETCH_COLUMN);

$stmtKU = $pdo->prepare("SELECT * FROM event_age_groups WHERE event_id = ? ORDER BY min_age ASC");
$stmtKU->execute([$eventId]);
$available_kus = $stmtKU->fetchAll(PDO::FETCH_ASSOC);

// --- PROSES FILTER ---
$mode = $_GET['mode'] ?? 'team'; 
$filter_gender = $_GET['gender'] ?? 'all';

// Auto-detect default team source based on participation_type
$partType = strtolower($raceInfo['participation_type'] ?? 'club');
$isSchoolEvent = (strpos($partType, 'school') !== false || strpos($partType, 'sekolah') !== false);
$defaultTeamSource = $isSchoolEvent ? 'school' : 'club';
$team_source = $_GET['team_source'] ?? $defaultTeamSource; // Filter Sumber Tim

$selected_ku_ids = $_GET['ku'] ?? []; 

$valid_birth_years = [];
$selected_ku_names = [];

if (!empty($selected_ku_ids)) {
    foreach ($available_kus as $ku) {
        if (in_array($ku['id'], $selected_ku_ids)) {
            $selected_ku_names[] = $ku['group_name'];
            $start_year = $eventYear - $ku['max_age']; 
            $end_year   = $eventYear - $ku['min_age']; 
            for ($y = $start_year; $y <= $end_year; $y++) { $valid_birth_years[] = $y; }
        }
    }
    $valid_birth_years = array_unique($valid_birth_years);
}

// Logika Pemilihan Nama Tim
if ($team_source == 'school') {
    $teamColumn = "COALESCE(NULLIF(s.asal_sekolah, ''), 'TANPA SEKOLAH')";
} else {
    $teamColumn = "COALESCE(NULLIF(c.nama_klub, ''), 'TANPA KLUB/TIM')";
}

// --- FUNGSI UTILITAS ---
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
if (!function_exists('getKUNameTally')) {
    function getKUNameTally($dob, $evtYear, $groups) {
        if(!$dob || $dob == '0000-00-00') return 'UMUR TIDAK DIKETAHUI';
        $age = $evtYear - (int)date('Y', strtotime($dob));
        foreach($groups as $g) {
            if ($age >= $g['min_age'] && $age <= $g['max_age']) return $g['group_name'];
        }
        return 'DILUAR KATEGORI (' . $age . ' TH)';
    }
}

// --- PERHITUNGAN MEDALI DINAMIS (Mengacu pada time_final) ---
$whereClauses = ["en.event_id = ?", "(es.time_final IS NOT NULL OR es.is_dq_final = 1)"];
$params = [$eventId];

if ($mode == 'athlete' && $filter_gender !== 'all') {
    $whereClauses[] = "s.jenis_kelamin = ?";
    $params[] = $filter_gender;
}

$whereSql = implode(" AND ", $whereClauses);

$sqlRaw = "SELECT 
            en.event_number, en.age_group as event_age_group, en.rank_mode,
            s.id as swimmer_id, s.uid, s.nama_atlet, s.jenis_kelamin, s.tanggal_lahir,
            $teamColumn as team_name,
            es.time_final, es.is_dq_final, es.rank_final
        FROM event_entries ee
        JOIN event_seeding es ON ee.id = es.entry_id
        JOIN swimmers s ON ee.swimmer_id = s.id
        JOIN event_numbers en ON ee.category_id = en.id
        LEFT JOIN clubs c ON ee.club_id = c.id
        WHERE $whereSql";

$stmt = $pdo->prepare($sqlRaw);
$stmt->execute($params);
$allEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Kelompokkan dan Sortir untuk mendapatkan Ranking Dinamis
$eventsGrouped = [];
foreach($allEntries as $r) {
    $r['ms_sort'] = 9999999999;
    if ($r['is_dq_final'] == 1) { $r['ms_sort'] = 9999999999 + 100; }
    elseif (!empty($r['time_final']) && $r['time_final'] != 'NT') { $r['ms_sort'] = timeToMs($r['time_final']); }
    
    $isSplit = ($r['rank_mode'] === 'split');
    
    $is_gabungan = (stripos($r['event_age_group'], 'GABUNG') !== false || strpos($r['event_age_group'], ',') !== false || strpos($r['event_age_group'], '/') !== false);

    
    $groupKey = $isSplit ? getKUNameTally($r['tanggal_lahir'], $eventYear, $available_kus) : 'OVERALL';
    $eventsGrouped[$r['event_number']][$groupKey][] = $r;
}

// 3. Berikan Ranking dan Tally Medali
$rawTally = []; // Menyimpan akumulasi
foreach($eventsGrouped as $eventNum => $groups) {
    foreach($groups as $groupName => &$swimmers) {
        usort($swimmers, function($a, $b) {
            if ($a['ms_sort'] == $b['ms_sort']) return 0;
            return ($a['ms_sort'] < $b['ms_sort']) ? -1 : 1;
        });
        
        $rank = 1; $real_rank = 1; $prev_time = null;
        foreach($swimmers as &$s) {
            $isValid = ($s['is_dq_final'] == 0 && !empty($s['time_final']) && $s['time_final'] != 'NT');
            if ($isValid) {
                if ($s['ms_sort'] !== $prev_time) { $real_rank = $rank; }
                if ($real_rank <= 3) {
                    // Tambahkan ke keranjang medali
                    $key = ($mode == 'team') ? $s['team_name'] : $s['swimmer_id'];
                    if (!isset($rawTally[$key])) {
                        $rawTally[$key] = [
                            'entity_name' => ($mode == 'team') ? $s['team_name'] : $s['nama_atlet'],
                            'uid' => $s['uid'], 'jenis_kelamin' => $s['jenis_kelamin'], 'tanggal_lahir' => $s['tanggal_lahir'],
                            'team_name' => $s['team_name'],
                            'gold' => 0, 'silver' => 0, 'bronze' => 0, 'total' => 0
                        ];
                    }
                    if ($real_rank == 1) $rawTally[$key]['gold']++;
                    if ($real_rank == 2) $rawTally[$key]['silver']++;
                    if ($real_rank == 3) $rawTally[$key]['bronze']++;
                    $rawTally[$key]['total']++;
                }
                $prev_time = $s['ms_sort'];
                $rank++;
            }
        }
    }
}
unset($swimmers);

// 4. Ubah ke array index numerik dan aplikasikan filter KU jika ada
$tallyData = [];
foreach($rawTally as $t) {
    // Filter KU jika dipilih
    if (!empty($valid_birth_years)) {
        $bYear = (int)date('Y', strtotime($t['tanggal_lahir']));
        if (!in_array($bYear, $valid_birth_years)) {
            continue; // Skip jika di luar filter KU
        }
    }
    $tallyData[] = $t;
}

// 5. Urutkan klasemen Emas > Perak > Perunggu > Total
usort($tallyData, function($a, $b) {
    if ($a['gold'] != $b['gold']) return $b['gold'] - $a['gold'];
    if ($a['silver'] != $b['silver']) return $b['silver'] - $a['silver'];
    if ($a['bronze'] != $b['bronze']) return $b['bronze'] - $a['bronze'];
    return $b['total'] - $a['total'];
});

if ($mode == 'team') {
    $titlePage = "KLASEMEN JUARA UMUM (" . ($team_source == 'school' ? 'SEKOLAH' : 'KLUB/TIM') . ")";
} else {
    $titlePage = "PERENANG TERBAIK";
}

// --- PENGELOMPOKAN KHUSUS PERENANG TERBAIK (ATHLETE MODE) ---
$groupedAthleteData = [];
if ($mode == 'athlete') {
    foreach ($tallyData as $row) {
        $age = $eventYear - date('Y', strtotime($row['tanggal_lahir']));
        $ku_name = 'UMUM';
        foreach ($available_kus as $ku) {
            if ($age >= $ku['min_age'] && $age <= $ku['max_age']) {
                $ku_name = strtoupper($ku['group_name']);
                break;
            }
        }
        $row['ku_name'] = $ku_name;
        $groupedAthleteData[$ku_name][$row['jenis_kelamin']][] = $row;
    }
    ksort($groupedAthleteData); 
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700;900&family=Courier+Prime:wght@400;700&display=swap');
    
    * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    
    .ku-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; max-height: 150px; overflow-y: auto; padding: 5px; border: 1px solid #e2e8f0; border-radius: 6px; background-color: #f8fafc; }
    .ku-item label { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: bold; cursor: pointer; padding: 4px; border-radius: 4px; transition: background 0.2s; }
    .ku-item label:hover { background-color: #e0f2fe; }
    .ku-item input[type="checkbox"] { accent-color: #2563eb; width: 14px; height: 14px; }

    .page-wrapper { background: white; width: 210mm; margin: 20px auto; padding: 0 10mm; min-height: 297mm; position: relative; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    
    /* KOP SURAT (Identik dgn Start List) */
    .header-fixed { position: fixed; top: 0; left: 0; right: 0; height: 35mm; background: white; border-bottom: 3px double #000; display: grid; grid-template-columns: 110px 1fr 110px; align-items: flex-end; padding: 5px 10mm 3px 10mm; z-index: 999; display: none; }
    .header-center { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; text-align: center; line-height: 1.2; color: #000; }
    .header-line-1 { font-size: 14pt; font-weight: 900; text-transform: uppercase; margin-bottom: 2px; }
    .header-line-2 { font-size: 9pt; font-weight: bold; text-transform: uppercase; }
    .header-line-3 { font-size: 9pt; font-weight: bold; text-transform: uppercase; }
    .header-line-4 { height: 3px; } 
    .header-line-5 { font-size: 18pt; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: #000; margin-top: 2px; margin-bottom: 0px; line-height: 1; }
    .logo-img { max-height: 100px; max-width: 100%; object-fit: contain; margin-bottom: 2px; }

    /* FOOTER SPONSOR TENGAH - PRINTED TEXT REMOVED */
    .footer-fixed { position: fixed; bottom: 0; left: 0; right: 0; height: 20mm; background: white; border-top: 2px double #000; display: flex; justify-content: center; align-items: center; padding: 0 10mm; z-index: 999; display: none; }
    .footer-sponsors { display: flex; gap: 10px; align-items: center; justify-content: center; flex: 1; }
    .footer-sponsors img { height: 45px; object-fit: contain; } 

    /* SPACER TABEL CETAK */
    .layout-table { width: 100%; border-collapse: collapse; border: none; }
    .layout-header-space { height: 42mm; } 
    .layout-footer-space { height: 25mm; }

    /* EVENT HEADER PADAT (Persis Buku Acara) */
    .event-header { position: relative; display: flex; justify-content: space-between; align-items: flex-end; border-top: none; border-bottom: 2px solid #000; padding: 2px 0; margin-top: 10px; margin-bottom: 2px; background: #fff; font-family: 'Arial', sans-serif; min-height: 30px; }
    .eh-left-group { display: flex; flex-direction: column; justify-content: center; width: 180px; line-height: 1.1; z-index: 2; position: relative; background: white; text-align: left; }
    .eh-number { font-size: 12pt; font-weight: 900; margin-bottom: 2px; }
    .eh-date { font-size: 8pt; font-weight: bold; font-style: normal; }
    .eh-center { position: absolute; left: 50%; bottom: 3px; transform: translateX(-50%); text-align: center; width: 60%; z-index: 1; }
    .eh-title  { font-size: 11pt; font-weight: 800; text-transform: uppercase; }
    .eh-right  { width: 180px; text-align: right; z-index: 2; position: relative; background: white; display: flex; flex-direction: column; justify-content: flex-end; }

    /* TABEL KLASEMEN PADAT (Courier New, 8pt) */
    .data-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-family: 'Courier New', Courier, monospace; font-size: 8pt; margin-bottom: 8px; }
    .data-table th { background-color: #e5e7eb; border-top: 1px solid #000; border-bottom: 2px solid #000; padding: 2px 4px; font-family: 'Arial Narrow', sans-serif; font-weight: bold; font-size: 8pt; text-align: center; }
    .data-table td { padding: 4px; border-bottom: 1px solid #ccc; vertical-align: middle; }
    
    .col-rank { width: 5%; text-align: center; font-weight: bold; background: #f8f9fa; border-right: 1px solid #eee; }
    .col-nama { width: 35%; text-align: left; padding-left: 5px; font-weight: bold; line-height: 1.1; text-transform: uppercase; white-space: normal; }
    .col-tim { width: 30%; text-align: left; padding-left: 5px; white-space: normal; line-height: 1.1; }
    .col-med { width: 7.5%; text-align: center; font-weight: 900; font-size: 10pt; }
    
    .bg-gold { background-color: #fef3c7 !important; color: #92400e; }
    .bg-silver { background-color: #f3f4f6 !important; color: #374151; }
    .bg-bronze { background-color: #ffedd5 !important; color: #9a3412; }
    .bg-total { background-color: #e0f2fe !important; color: #075985; border-left: 1px solid #ccc; }

    .block-tabel { page-break-inside: avoid; }

    @media print {
        @page { size: A4; margin: 0; }
        nav, aside, header, .sidebar, .no-print, .fixed, .navbar, .topbar, .sticky, #sidebar { display: none !important; }
        .p-4, .sm\:ml-64, .pt-24, .min-h-screen, #main-content { padding: 0 !important; margin: 0 !important; min-height: auto !important; background: white !important; }
        body, html { margin: 0 !important; padding: 0 !important; background: white !important; width: 100%; height: 100%; font-family: 'Arial', sans-serif; }
        .page-wrapper { margin: 0; width: 100%; box-shadow: none; padding: 0 10mm; min-height: auto; position: relative; }
        .header-fixed { display: grid !important; }
        .footer-fixed { display: flex !important; justify-content: center !important; }
        .layout-table > thead { display: table-header-group !important; }
        .data-table > thead { display: table-row-group !important; }
        tfoot { display: table-footer-group; }
    }
</style>

<div class="p-4 sm:ml-64 pt-24 min-h-screen bg-slate-100 text-slate-900 font-sans" id="main-content">
    
    <div class="max-w-[210mm] mx-auto mb-6 space-y-4 no-print">
        <div class="bg-white p-4 rounded-xl shadow border border-slate-200 flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
                <h1 class="font-bold text-lg text-slate-800">REKAPITULASI MEDALI</h1>
                <p class="text-xs text-slate-500">Pilih mode tampilan:</p>
            </div>
            <div class="flex gap-2 bg-slate-100 p-1 rounded-lg">
                <a href="?mode=team&team_source=<?= $team_source ?>" class="<?= $mode=='team'?'bg-white shadow text-blue-700':'text-gray-500 hover:text-gray-700' ?> px-4 py-1.5 rounded text-xs font-bold uppercase transition">
                    🏆 Juara Umum (Tim)
                </a>
                <a href="?mode=athlete&team_source=<?= $team_source ?>" class="<?= $mode=='athlete'?'bg-white shadow text-blue-700':'text-gray-500 hover:text-gray-700' ?> px-4 py-1.5 rounded text-xs font-bold uppercase transition">
                    🏊‍♂️ Perenang Terbaik
                </a>
            </div>
        </div>

        <?php if($mode == 'athlete'): ?>
        <div class="bg-white p-4 rounded-xl shadow border border-blue-100">
            <form method="GET" class="space-y-4">
                <input type="hidden" name="mode" value="athlete">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-5 flex flex-col gap-1">
                        <label class="text-[10px] font-bold text-slate-400 uppercase">Pilih Kelompok Umur</label>
                        <?php if(empty($available_kus)): ?>
                            <p class="text-xs text-red-500 italic">Belum ada data KU di database.</p>
                        <?php else: ?>
                            <div class="ku-grid scrollbar-thin">
                                <?php foreach($available_kus as $ku): 
                                    $isChecked = in_array($ku['id'], $selected_ku_ids) ? 'checked' : '';
                                ?>
                                <div class="ku-item">
                                    <label><input type="checkbox" name="ku[]" value="<?= $ku['id'] ?>" <?= $isChecked ?>> <?= htmlspecialchars($ku['group_name']) ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="md:col-span-3 flex flex-col gap-1">
                        <label class="text-[10px] font-bold text-slate-400 uppercase">Sumber Data Tim</label>
                        <select name="team_source" class="border border-slate-300 rounded px-3 py-2 text-xs font-bold bg-slate-50 h-10 w-full outline-none">
                            <option value="club" <?= $team_source=='club'?'selected':'' ?>>KLUB / TIM</option>
                            <option value="school" <?= $team_source=='school'?'selected':'' ?>>ASAL SEKOLAH</option>
                        </select>
                    </div>

                    <div class="md:col-span-2 flex flex-col gap-1">
                        <label class="text-[10px] font-bold text-slate-400 uppercase">Gender</label>
                        <select name="gender" class="border border-slate-300 rounded px-3 py-2 text-xs font-bold bg-slate-50 h-10 w-full outline-none">
                            <option value="all">SEMUA</option>
                            <option value="L" <?= $filter_gender=='L'?'selected':'' ?>>PUTRA</option>
                            <option value="P" <?= $filter_gender=='P'?'selected':'' ?>>PUTRI</option>
                        </select>
                    </div>

                    <div class="md:col-span-2 flex flex-col justify-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded text-xs font-bold uppercase shadow w-full h-10">🔍 Terapkan</button>
                    </div>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="bg-white p-4 rounded-xl shadow border border-blue-100">
            <form method="GET" class="space-y-4">
                <input type="hidden" name="mode" value="team">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-6 flex flex-col gap-1">
                        <label class="text-[10px] font-bold text-slate-400 uppercase">Sumber Data Tim</label>
                        <select name="team_source" class="border border-slate-300 rounded px-3 py-2 text-xs font-bold bg-slate-50 h-10 w-full outline-none">
                            <option value="club" <?= $team_source=='club'?'selected':'' ?>>KLUB / TIM</option>
                            <option value="school" <?= $team_source=='school'?'selected':'' ?>>ASAL SEKOLAH</option>
                        </select>
                    </div>
                    <div class="md:col-span-6 flex flex-col justify-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded text-xs font-bold uppercase shadow w-full h-10">🔍 Terapkan</button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="flex justify-end">
            <button onclick="window.print()" class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-2 rounded text-xs font-bold uppercase shadow flex items-center gap-2">
                🖨️ Cetak Laporan
            </button>
        </div>
    </div>

    <div class="header-fixed">
        <div style="text-align: left;"><?php if($logoLeft): ?><img src="<?= $logoLeft ?>" class="logo-img"><?php endif; ?></div>
        <div class="header-center">
            <div class="header-line-1"><?= htmlspecialchars($eventName) ?></div>
            <div class="header-line-2"><?= htmlspecialchars($venueName) ?></div>
            <div class="header-line-3"><?= htmlspecialchars($dateRange) ?></div>
            <div class="header-line-4"></div>
            <div class="header-line-5">KLASEMEN AKHIR</div>
        </div>
        <div style="text-align: right;"><?php if($logoRight): ?><img src="<?= $logoRight ?>" class="logo-img"><?php endif; ?></div>
    </div>

    <div class="footer-fixed" style="justify-content: center;">
        <div class="footer-sponsors">
            <?php if(!empty($sponsors)): ?>
                <?php foreach($sponsors as $img): ?>
                    <img src="<?= $protocol . "://" . $host . BASE_URL . '/public/' . ltrim($img, '/') ?>">
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="page-wrapper">
        <table class="layout-table">
            <thead><tr><td><div class="layout-header-space"></div></td></tr></thead>
            <tfoot><tr><td><div class="layout-footer-space"></div></td></tr></tfoot>
            <tbody>
                <tr>
                    <td>
                        <?php if(empty($tallyData)): ?>
                            <div style="text-align:center; padding: 50px; font-weight: bold; color:#888; border: 2px dashed #ccc; margin-top: 15px;">
                                Belum ada data perolehan medali untuk filter ini.
                            </div>
                        <?php else: ?>
                            
                            <?php if($mode == 'team'): ?>
                                <div class="block-tabel">
                                    <div class="event-header">
                                        <div class="eh-left-group">
                                            <div class="eh-number">REKAP <?= $team_source == 'school' ? 'SEKOLAH' : 'TIM' ?></div>
                                            <div class="eh-date"><?= htmlspecialchars($dateRange) ?></div>
                                        </div>
                                        <div class="eh-center"><div class="eh-title"><?= $titlePage ?></div></div>
                                        <div class="eh-right"></div>
                                    </div>

                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th class="col-rank">RANK</th>
                                                <th class="col-nama" style="width: 65%;">NAMA <?= $team_source == 'school' ? 'SEKOLAH' : 'KLUB / TIM' ?></th>
                                                <th class="col-med bg-gold">E</th>
                                                <th class="col-med bg-silver">P</th>
                                                <th class="col-med bg-bronze">P</th>
                                                <th class="col-med bg-total">TOT</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $rank=1; 
                                            $tot_e = 0; $tot_p = 0; $tot_b = 0; $tot_all = 0;
                                            foreach($tallyData as $row): 
                                                $tot_e += $row['gold'];
                                                $tot_p += $row['silver'];
                                                $tot_b += $row['bronze'];
                                                $tot_all += $row['total'];
                                            ?>
                                            <tr>
                                                <td class="col-rank"><?= $rank++ ?></td>
                                                <td class="col-nama"><?= htmlspecialchars($row['entity_name']) ?></td>
                                                <td class="col-med bg-gold"><?= $row['gold'] ?></td>
                                                <td class="col-med bg-silver"><?= $row['silver'] ?></td>
                                                <td class="col-med bg-bronze"><?= $row['bronze'] ?></td>
                                                <td class="col-med bg-total"><?= $row['total'] ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <tr style="background-color: #cbd5e1; border-top: 2px solid #334155;">
                                                <td colspan="2" style="text-align: right; padding-right: 15px; font-weight: 900; font-size: 9pt;">TOTAL KESELURUHAN:</td>
                                                <td class="col-med bg-gold"><?= $tot_e ?></td>
                                                <td class="col-med bg-silver"><?= $tot_p ?></td>
                                                <td class="col-med bg-bronze"><?= $tot_b ?></td>
                                                <td class="col-med bg-total"><?= $tot_all ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <?php foreach($groupedAthleteData as $ku_name => $genders): ?>
                                    <?php foreach(['L', 'P'] as $gender): ?>
                                        <?php if(!empty($genders[$gender])): ?>
                                            <div class="block-tabel">
                                                
                                                <div class="event-header">
                                                    <div class="eh-left-group">
                                                        <div class="eh-number">KU: <?= htmlspecialchars($ku_name) ?></div>
                                                    </div>
                                                    <div class="eh-center"><div class="eh-title"><?= $titlePage ?></div></div>
                                                    <div class="eh-right">
                                                        <div class="eh-number"><?= $gender == 'L' ? 'PUTRA' : 'PUTRI' ?></div>
                                                    </div>
                                                </div>

                                                <table class="data-table">
                                                    <thead>
                                                        <tr>
                                                            <th class="col-rank">RANK</th>
                                                            <th class="col-nama">NAMA PERENANG</th>
                                                            <th class="col-tim"><?= $team_source == 'school' ? 'SEKOLAH' : 'TIM / KLUB' ?></th>
                                                            <th class="col-med bg-gold">E</th>
                                                            <th class="col-med bg-silver">P</th>
                                                            <th class="col-med bg-bronze">P</th>
                                                            <th class="col-med bg-total">TOT</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $rank=1; 
                                                        $tot_e = 0; $tot_p = 0; $tot_b = 0; $tot_all = 0;
                                                        foreach($genders[$gender] as $row): 
                                                            $tot_e += $row['gold'];
                                                            $tot_p += $row['silver'];
                                                            $tot_b += $row['bronze'];
                                                            $tot_all += $row['total'];
                                                        ?>
                                                        <tr>
                                                            <td class="col-rank"><?= $rank++ ?></td>
                                                            <td class="col-nama">
                                                                <?= htmlspecialchars($row['entity_name']) ?>
                                                            </td>
                                                            <td class="col-tim"><?= htmlspecialchars($row['team_name']) ?></td>
                                                            <td class="col-med bg-gold"><?= $row['gold'] ?></td>
                                                            <td class="col-med bg-silver"><?= $row['silver'] ?></td>
                                                            <td class="col-med bg-bronze"><?= $row['bronze'] ?></td>
                                                            <td class="col-med bg-total"><?= $row['total'] ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                        <tr style="background-color: #cbd5e1; border-top: 2px solid #334155;">
                                                            <td colspan="3" style="text-align: right; padding-right: 15px; font-weight: 900; font-size: 9pt;">TOTAL KESELURUHAN:</td>
                                                            <td class="col-med bg-gold"><?= $tot_e ?></td>
                                                            <td class="col-med bg-silver"><?= $tot_p ?></td>
                                                            <td class="col-med bg-bronze"><?= $tot_b ?></td>
                                                            <td class="col-med bg-total"><?= $tot_all ?></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>