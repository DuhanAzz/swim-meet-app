<?php
// src/admin/results/export_all_results.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// 1. CEK KEAMANAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}
$uid = $_SESSION['user_id']; 

// 2. TANGKAP EVENT ID
$eventId = $_GET['event_id'] ?? 0;

if ($eventId == 0) {
    $stmtLast = $pdo->prepare("SELECT id FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtLast->execute([$uid]);
    $lastEvent = $stmtLast->fetch();
    $eventId = $lastEvent['id'] ?? 0;
}
if ($eventId == 0) { die("Event belum dibuat."); }

// 3. AMBIL PROFIL EVENT
$stmtProfile = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtProfile->execute([$eventId]);
$eventProfile = $stmtProfile->fetch(PDO::FETCH_ASSOC);

if (!$eventProfile) die("Data Event tidak ditemukan.");

// AMBIL SPONSOR
$stmtFooter = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE event_id = ?");
$stmtFooter->execute([$eventId]);
$footerSponsors = $stmtFooter->fetchAll(PDO::FETCH_COLUMN);

// 4. SETUP VARIABEL TAMPILAN
$header_title    = strtoupper($eventProfile['nama_event']);
$venue_name      = strtoupper($eventProfile['venue_name'] ?? $eventProfile['lokasi']);
$participationType = $eventProfile['participation_type'] ?? 'club';

$event_date      = $eventProfile['event_start_date'];
$display_date    = strtoupper(date('d F Y', strtotime($event_date)));
$event_year      = date('Y', strtotime($event_date));
if(!empty($eventProfile['event_end_date']) && strtotime($eventProfile['event_start_date']) != strtotime($eventProfile['event_end_date'])) {
    $header_date_range = date('d', strtotime($eventProfile['event_start_date'])) . ' - ' . date('d F Y', strtotime($eventProfile['event_end_date']));
} else {
    $header_date_range = $display_date;
}

$logo_left  = !empty($eventProfile['logo_left']) ? '../../../public/' . $eventProfile['logo_left'] : null;
$logo_right = !empty($eventProfile['logo_right']) ? '../../../public/' . $eventProfile['logo_right'] : null;
$poolSuffix = ($eventProfile['pool_type'] == 'SCM') ? ' - SCM' : ' - LCM';

// 5. AMBIL SEMUA NOMOR LOMBA
$stmtEvents = $pdo->prepare("SELECT * FROM event_numbers WHERE organizer_id = ? ORDER BY CAST(event_number AS UNSIGNED) ASC");
$stmtEvents->execute([$uid]);
$all_events = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);

// --- HELPER FUNCTIONS ---
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

function shortenName($name) {
    return trim(preg_replace('/\s+/', ' ', $name ?? ''));
}

function getTeamName($row, $type) {
    $club = trim($row['club_name'] ?? ''); 
    $school = trim($row['asal_sekolah'] ?? '');
    if (stripos($type, 'sekolah') !== false || stripos($type, 'school') !== false) {
        return !empty($school) ? $school : (!empty($club) ? $club : '-');
    } else {
        return !empty($club) ? $club : (!empty($school) ? $school : '-');
    }
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700;900&family=Courier+Prime:wght@400;700&display=swap');
    
    .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
    .font-mono { font-family: 'Courier Prime', monospace; }

    /* LAYOUT SCREEN */
    .paper-sheet {
        width: 210mm; 
        min-height: 297mm; 
        background: white; 
        margin: 0 auto 30px auto; 
        padding: 5mm 10mm 25mm 10mm; 
        color: #000; 
        position: relative; 
        font-family: 'Roboto Condensed', sans-serif;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    /* HEADER HALAMAN (KOP) */
    .page-header {
        padding: 5px 0 10px 0; border-bottom: 2px double #000; margin-bottom: 20px;
        display: flex; justify-content: space-between; align-items: center; width: 100%;
    }
    .logo-box { width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; }
    .logo-box img { max-height: 100%; max-width: 100%; object-fit: contain; }
    .header-content h1 { font-size: 14pt; margin-bottom: 2px; }
    .header-content p { font-size: 9pt; margin: 0; }
    .buku-acara-badge { font-size: 12pt; padding: 2px 10px; margin-top: 5px; border-width: 2px; }

    /* ITEM CONTAINER PER NOMOR LOMBA */
    .event-item-container {
        margin-bottom: 20px;
        border-bottom: 2px dashed #ccc; 
        padding-bottom: 15px;
        page-break-inside: avoid; 
    }
    .event-item-container:last-child { border-bottom: none; margin-bottom: 0; }

    /* HEADER PER NOMOR LOMBA */
    .event-header-grid {
        display: grid; 
        grid-template-columns: 80px 1fr 50px 90px; 
        gap: 5px; align-items: center;
        border-bottom: 1px solid #000; margin-bottom: 5px; padding-bottom: 5px;
    }
    .event-number { font-size: 14pt; font-weight: 900; line-height: 1; }
    .event-title { font-size: 11pt; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .babak-badge { font-size: 9pt; padding: 2px 5px; }
    .qr-box { display: flex; flex-direction: column; align-items: center; justify-content: center; }

    /* TABEL HASIL */
    .rank-table { width: 100%; border-collapse: collapse; font-size: 9pt; table-layout: fixed; }
    .rank-table th { 
        background: #f0f0f0; border-bottom: 1px solid #000; border-top: 1px solid #000; 
        padding: 2px 4px; font-weight: bold; text-transform: uppercase; 
    }
    .rank-table td { 
        border-bottom: 1px solid #ccc; padding: 3px 4px; 
        vertical-align: middle; white-space: normal; line-height: 1.1;
    }
    .col-center { text-align: center; } .col-left { text-align: left; } .col-right { text-align: right; }

    /* YEAR HEADER (Pemisah Tahun) */
    .year-header {
        background: #333; color: #fff; font-weight: 900; padding: 3px 5px;
        font-size: 9pt; text-transform: uppercase; margin-bottom: 0; margin-top: 10px;
        display: inline-block; border-radius: 3px 3px 0 0;
    }

    /* FOOTER SPONSOR (Tampil di setiap halaman print) */
    .footer-sponsor { display: none; }

    /* === KHUSUS PRINT === */
    @media print {
        @page { size: A4; margin: 10mm 10mm 15mm 10mm; } 
        
        nav, aside, header, .sidebar, .no-print, .fixed, .navbar, .topbar, .sticky, #sidebar { display: none !important; }
        
        body, html { margin: 0 !important; padding: 0 !important; background: white !important; width: 100%; height: 100%; }
        .p-4.sm\:ml-64.pt-24 { padding: 0 !important; margin: 0 !important; }

        .paper-sheet { 
            width: 100% !important; margin: 0 !important; box-shadow: none !important; 
            padding: 0 !important; 
            min-height: auto;
        }

        .rank-table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; background-color: #eee !important; }
        .rank-table tr:nth-child(even) td { -webkit-print-color-adjust: exact; background-color: #f8f8f8 !important; }
        
        .year-wrapper { page-break-inside: avoid; }

        .footer-sponsor { 
            display: flex !important;
            position: fixed; bottom: 0; left: 0; right: 0; 
            padding-bottom: 2mm; background: white; z-index: 10;
            flex-direction: column; align-items: center; justify-content: flex-end;
        }
        .sponsor-line-separator { width: 100%; border-top: 3px double #000; margin-bottom: 5px; }
        .sponsor-logo-container { display: flex; justify-content: center; align-items: center; gap: 15px; width: 100%; padding: 0 10px; }
        .sponsor-logo-container img { height: 30px; width: auto; object-fit: contain; filter: grayscale(100%); opacity: 0.9; }
    }
</style>

<div class="p-4 sm:ml-64 pt-24 min-h-screen bg-slate-100 text-slate-900 font-sans">
    
    <div class="max-w-[210mm] mx-auto mb-6 flex flex-col sm:flex-row justify-between items-center bg-white p-4 rounded-xl border border-slate-200 shadow-sm sticky top-20 z-40 gap-4 no-print">
        <div>
            <h2 class="text-lg font-black text-slate-800 italic">CETAK HASIL LENGKAP</h2>
            <p class="text-xs text-slate-500 font-bold uppercase">Mode: Kelompok Umur per Tahun (Continuous).</p>
        </div>
        <div class="flex gap-3">
            <a href="index.php" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg font-bold text-xs uppercase hover:bg-slate-200 transition">Kembali</a>
            <button onclick="window.print()" class="px-4 py-2 bg-green-600 text-white rounded-lg font-bold text-xs uppercase hover:bg-green-700 transition flex items-center gap-2 shadow-md">
                🖨️ Cetak
            </button>
        </div>
    </div>

    <div class="paper-sheet">

        <div class="page-header">
            <div class="logo-box"><?php if($logo_left): ?><img src="<?= $logo_left ?>"><?php endif; ?></div>
            <div class="text-center flex-1 px-4 header-content">
                <h1 class="font-black uppercase leading-tight"><?= htmlspecialchars($header_title) ?></h1>
                <?php if($venue_name): ?><p class="font-bold uppercase text-gray-800"><?= htmlspecialchars($venue_name) ?></p><?php endif; ?>
                <p class="font-bold uppercase text-gray-500"><?= htmlspecialchars($header_date_range) ?></p>
                <div class="inline-block border-2 border-black buku-acara-badge">
                    <p class="font-black uppercase tracking-[0.2em] leading-none">HASIL LENGKAP</p>
                </div>
            </div>
            <div class="logo-box"><?php if($logo_right): ?><img src="<?= $logo_right ?>"><?php endif; ?></div>
        </div>

        <?php 
        $countData = 0;
        // --- OUTER LOOP: SEMUA NOMOR LOMBA ---
        foreach ($all_events as $raceInfo): 
            $cat_id = $raceInfo['id'];

            // JUDUL
            $cleanStroke = trim(str_ireplace(['Gaya', 'GAYA'], '', $raceInfo['stroke'] ?? ''));
            $gender_label = (in_array($raceInfo['jenis_kelamin'], ['L','Male','Man'])) ? 'PUTRA' : 'PUTRI';
            $judul_tengah = $raceInfo['distance'] . " M GAYA " . strtoupper($cleanStroke) . " - " . ($raceInfo['age_group']??'') . " " . $gender_label . $poolSuffix;
            $nomor_acara = "#" . $raceInfo['event_number'];

            // QR
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
            $host = $_SERVER['HTTP_HOST'];
            $default_target_link = $protocol . "://" . $host . "/public/result.php?category_id=" . $cat_id;
            $qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&margin=0&data=" . urlencode($default_target_link);

            // DATA
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
            } catch (PDOException $e) { continue; }

            if (empty($raw_data)) continue; 
            $countData++;

            // --- GROUPING PER TAHUN LAHIR ---
            $groupedResults = [];
            foreach ($raw_data as $row) {
                if (!empty($row['swimmer_id'])) {
                    $year = date('Y', strtotime($row['tanggal_lahir']));
                    
                    $row['ms_sort'] = 9999999999; 
                    if (($row['is_dq']??0) == 1) { 
                        $row['ms_sort'] = 9999999999 + 100; 
                    } elseif (!empty($row['final_time']) && $row['final_time'] != 'NT' && $row['final_time'] != '99:99.99') {
                        $row['ms_sort'] = timeToMs($row['final_time']);
                    }
                    $groupedResults[$year][] = $row;
                }
            }
            krsort($groupedResults); // Urutkan Tahun (Descending)

            // Urutkan Peserta di dalam setiap tahun
            foreach ($groupedResults as $y => &$rows) {
                usort($rows, function($a, $b) {
                    if ($a['ms_sort'] == $b['ms_sort']) return 0;
                    return ($a['ms_sort'] < $b['ms_sort']) ? -1 : 1;
                });
            }
            unset($rows);
        ?>

        <div class="event-item-container">
            
            <div class="event-header-grid">
                <div class="event-num-box">
                    <div class="event-number"><?= $nomor_acara ?></div>
                    <div class="text-[8pt] font-bold"><?= strtoupper($display_date) ?></div>
                </div>

                <div class="event-title-box text-center">
                    <div class="event-title"><?= $judul_tengah ?></div>
                </div>

                <div class="qr-box">
                    <img src="<?= $qr_api ?>" alt="QR" style="width: 50px; height: 50px; border: 1px solid #ddd; padding: 2px;">
                </div>

                <div class="text-right">
                    <span class="font-bold bg-gray-100 border border-gray-300 babak-badge rounded">FINAL</span>
                </div>
            </div>

            <?php foreach($groupedResults as $year => $swimmersInYear): ?>
            <div class="year-wrapper">
                <div class="year-header">KELOMPOK UMUR <?= $year ?></div>
                <table class="rank-table" style="margin-top: 0;">
                    <colgroup>
                        <col style="width: 8%;"> <col style="width: 30%;"> <col style="width: 25%;"> <col style="width: 17%;"> <col style="width: 20%;"> 
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="col-center">RANK</th>
                            <th class="col-left">NAMA ATLET</th>
                            <th class="col-left">TIM / SEKOLAH</th>
                            <th class="col-right">PRESTASI</th>
                            <th class="col-right">HASIL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1; $real_rank = 1; $prev_time = null;
                        foreach($swimmersInYear as $p): 
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
                            <td class="col-left font-bold"><?= shortenName($p['nama_atlet']) ?></td>
                            <td class="col-left"><?= shortenName(getTeamName($p, $participationType)) ?></td>
                            <td class="col-right font-mono text-[9pt]"><?= $seedTime ?></td>
                            <td class="col-right font-mono font-bold text-[10pt]">
                                <?php 
                                if (($p['is_dq']??0) == 1) { echo '<span style="color:red;">'.($p['dq_reason'] ?? 'DQ').'</span>'; } 
                                elseif ($is_valid) { echo $p['final_time']; } 
                                else { echo 'NT'; } 
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>

        </div>
        <?php endforeach; ?>

        <?php if($countData == 0): ?>
            <div class="text-center py-20 border-2 border-dashed border-gray-300 rounded-xl mt-10">
                <p class="text-gray-400 font-bold">Belum ada hasil.</p>
            </div>
        <?php endif; ?>

        <?php if(!empty($footerSponsors)): ?>
        <div class="footer-sponsor">
            <div class="sponsor-line-separator"></div>
            <div class="sponsor-logo-container">
                <?php foreach($footerSponsors as $img): ?><img src="../../../public/<?= $img ?>"><?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div> <div class="h-24 no-print"></div> 
</div>