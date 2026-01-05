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

// Tanggal
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
$stmtEvents = $pdo->prepare("SELECT * FROM event_numbers WHERE organizer_id = ? ORDER BY event_number ASC");
$stmtEvents->execute([$uid]);
$all_events = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);

// --- HELPER FUNCTIONS ---
function formatLahir($tgl, $year) {
    if(!$tgl || $tgl == '0000-00-00') return '-';
    $by = date('Y', strtotime($tgl));
    return $by . " (" . ($year - $by) . ")";
}

function shortenName($name) {
    return trim(preg_replace('/\s+/', ' ', $name ?? ''));
}

function getTeamName($row, $type) {
    $club   = $row['club_name'] ?? '';     
    $school = $row['asal_sekolah'] ?? '';  
    $club = trim($club); $school = trim($school);

    if (stripos($type, 'sekolah') !== false || stripos($type, 'school') !== false || stripos($type, 'universitas') !== false) {
        return !empty($school) ? $school : '-';
    } else {
        return !empty($club) ? $club : '-';
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
        margin: 0 auto;
        padding: 5mm 10mm 35mm 10mm; 
        color: #000; 
        font-family: 'Roboto Condensed', sans-serif;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        position: relative; 
    }

    /* HEADER HALAMAN UTAMA */
    .page-header {
        padding: 5px 0 10px 0; border-bottom: 3px double #000; margin-bottom: 10px;
        display: flex; justify-content: space-between; align-items: center; width: 100%;
    }
    .logo-box { width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; }
    .logo-box img { max-height: 100%; max-width: 100%; object-fit: contain; }

    /* HEADER PER NOMOR LOMBA */
    .event-block { margin-bottom: 25px; break-inside: avoid; }
    
    .event-header-grid {
        display: grid; grid-template-columns: 80px 1fr 80px; align-items: center;
        border-bottom: 2px solid #000; margin-bottom: 5px; padding-bottom: 2px;
    }
    .event-number { font-size: 14pt; font-weight: 900; line-height: 1; }
    .event-title { font-size: 11pt; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .babak-badge { font-size: 9pt; padding: 2px 5px; background: #eee; border-radius: 4px; font-weight: bold; }

    /* TABEL HASIL */
    .result-table { width: 100%; border-collapse: collapse; font-size: 8pt; table-layout: fixed; }
    
    .result-table th { 
        background: #f0f0f0; border-bottom: 1px solid #000; border-top: 1px solid #000; 
        padding: 4px; font-weight: bold; text-transform: uppercase;
    }
    
    .result-table td { 
        border-bottom: 1px solid #ccc; 
        padding: 3px 4px; 
        white-space: normal; line-height: 1.1; 
        vertical-align: middle;
    }

    /* Warna Baris Zebra agar mudah dibaca */
    .result-table tr:nth-child(even) { background-color: #fcfcfc; }
    
    .col-center { text-align: center; } 
    .col-right { text-align: right; } 
    .col-left { text-align: left; }
    
    .rank-text { font-size: 10pt; font-weight: bold; }
    .dq-text { color: red; font-weight: bold; font-size: 7pt; }

    /* FOOTER SPONSOR (FIXED POSITIONING FOR PAPER) */
    .footer-sponsor {
        position: absolute; bottom: 0; left: 0; width: 100%; 
        padding-bottom: 5mm; 
        display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
        z-index: 10; pointer-events: none;
    }
    .sponsor-line-separator { width: 90%; border-top: 3px double #000; margin-bottom: 5px; margin-left: auto; margin-right: auto; }
    .sponsor-logo-container { display: flex; justify-content: center; align-items: center; gap: 15px; width: 100%; padding: 0 10px; }
    .sponsor-logo-container img { height: 40px; width: auto; object-fit: contain; filter: grayscale(100%); opacity: 0.9; }

    /* === KHUSUS PRINT === */
    @media print {
        @page { size: A4; margin: 5mm 5mm 10mm 5mm; }
        
        nav, aside, header, .sidebar, .no-print, .fixed, .navbar, .topbar, .sticky, #sidebar { display: none !important; }
        body, html { margin: 0 !important; padding: 0 !important; background: white !important; width: 100%; height: 100%; }

        #print-wrapper {
            margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: 100% !important;
            position: static !important; background: white !important; border: none !important; display: block !important;
        }

        .paper-sheet { 
            width: 100% !important; margin: 0 !important; padding: 0 0 25mm 0 !important; 
            box-shadow: none !important; min-height: auto;
        }

        .footer-sponsor { 
            position: fixed; bottom: 0; left: 0; right: 0;
            padding-bottom: 2mm; background: white; 
        }
        
        .result-table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; background-color: #eee !important; }
        .result-table tr:nth-child(even) { -webkit-print-color-adjust: exact; background-color: #f8f8f8 !important; }
    }
</style>

<div id="print-wrapper" class="p-4 sm:ml-64 pt-24 min-h-screen bg-slate-100 text-slate-900 font-sans">
    
    <div class="max-w-[210mm] mx-auto mb-6 flex justify-between items-center no-print">
        <div>
            <h2 class="text-lg font-bold text-slate-700">CETAK HASIL LENGKAP</h2>
            <p class="text-xs text-slate-500">Mencetak hasil semua nomor lomba dalam satu file.</p>
        </div>
        <div class="flex gap-2">
            <a href="index.php" class="bg-white border border-slate-300 px-4 py-2 rounded text-xs font-bold uppercase hover:bg-slate-50">Kembali</a>
            <button onclick="window.print()" class="bg-slate-900 text-white px-6 py-2 rounded text-xs font-bold uppercase hover:bg-slate-800 flex items-center gap-2">🖨️ Cetak Full</button>
        </div>
    </div>

    <div class="paper-sheet">
        
        <div class="page-header">
            <div class="logo-box"><?php if($logo_left): ?><img src="<?= $logo_left ?>"><?php endif; ?></div>
            <div class="text-center flex-1 px-4">
                <h1 class="text-xl font-black uppercase leading-tight"><?= htmlspecialchars($header_title) ?></h1>
                <?php if($venue_name): ?>
                    <p class="text-[9pt] font-bold uppercase text-gray-800 mt-1"><?= htmlspecialchars($venue_name) ?></p>
                <?php endif; ?>
                <p class="text-[8pt] font-bold uppercase text-gray-500 mt-1"><?= htmlspecialchars($header_date_range) ?></p>
                
                <div class="inline-block border-2 border-black px-6 py-1 mt-2">
                    <p class="text-xl font-black uppercase tracking-[0.2em]">HASIL LENGKAP</p>
                </div>
            </div>
            <div class="logo-box"><?php if($logo_right): ?><img src="<?= $logo_right ?>"><?php endif; ?></div>
        </div>

        <?php 
        $countData = 0;
        foreach($all_events as $event): 
            $cat_id = $event['id'];
            
            // JUDUL
            $cleanStroke = trim(str_ireplace(['Gaya', 'GAYA'], '', $event['stroke'] ?? ''));
            $gender_label = (in_array($event['jenis_kelamin'], ['L','Male'])) ? 'PUTRA' : 'PUTRI';
            $jarak_gaya  = $event['distance'] . " M GAYA " . strtoupper($cleanStroke) . " - " . ($event['age_group']??'') . " " . $gender_label . $poolSuffix;
            
            // QUERY PESERTA (Diurutkan berdasarkan RANK, bukan Seri)
            // Mengambil yang sudah masuk Seri (heat IS NOT NULL)
            $sql = "SELECT ee.final_time, ee.final_rank, ee.is_dq, ee.dq_reason, ee.heat, ee.lane,
                    s.nama_atlet, s.tanggal_lahir, s.asal_sekolah,
                    u.nama_lengkap as club_name
                    FROM event_entries ee
                    JOIN swimmers s ON ee.swimmer_id = s.id
                    LEFT JOIN users u ON ee.club_id = u.id
                    WHERE ee.category_id = ? AND ee.heat IS NOT NULL 
                    ORDER BY 
                        CASE WHEN ee.final_rank IS NULL THEN 1 ELSE 0 END, -- Yang punya rank di atas
                        ee.final_rank ASC, -- Urutkan rank 1, 2, 3...
                        ee.is_dq ASC -- DQ di bawah
                    ";
            $stmt = $pdo->prepare($sql); 
            $stmt->execute([$cat_id]); 
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if(empty($results)) continue; 
            $countData++;
        ?>

        <div class="event-block">
            <div class="event-header-grid">
                <div class="event-num-box">
                    <div class="event-number">#<?= $event['event_number'] ?></div>
                </div>
                <div class="event-title-box text-center">
                    <div class="event-title"><?= $jarak_gaya ?></div>
                </div>
                <div class="text-right">
                    <span class="babak-badge">OFFICIAL</span>
                </div>
            </div>

            <table class="result-table">
                <colgroup>
                    <col style="width: 8%;">  <col style="width: 35%;"> <col style="width: 10%;"> <col style="width: 25%;"> <col style="width: 12%;"> <col style="width: 10%;"> </colgroup>
                <thead>
                    <tr>
                        <th class="col-center">RANK</th>
                        <th class="col-left">NAMA ATLET</th>
                        <th class="col-center">LHR</th>
                        <th class="col-left">
                            <?= (stripos($participationType, 'sekolah') !== false || stripos($participationType, 'school') !== false) ? 'SEKOLAH' : 'TIM / CLUB' ?>
                        </th>
                        <th class="col-right">WAKTU</th>
                        <th class="col-center">KET</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach($results as $r): 
                        $is_dq = ($r['is_dq'] == 1);
                        $no_time = (empty($r['final_time']) || $r['final_time'] == 'NT');
                    ?>
                    <tr>
                        <td class="col-center rank-text">
                            <?= $r['final_rank'] ? $r['final_rank'] : '-' ?>
                        </td>
                        <td class="col-left font-bold text-black">
                            <?= shortenName($r['nama_atlet']) ?>
                        </td>
                        <td class="col-center font-mono text-gray-700">
                            <?= formatLahir($r['tanggal_lahir'], $event_year) ?>
                        </td>
                        <td class="col-left font-bold text-gray-800">
                            <?= shortenName(getTeamName($r, $participationType)) ?>
                        </td>
                        <td class="col-right font-mono font-bold" style="font-size: 9pt;">
                            <?php 
                                if($is_dq) echo ''; 
                                elseif($no_time) echo 'NT'; 
                                else echo $r['final_time']; 
                            ?>
                        </td>
                        <td class="col-center">
                            <?php 
                                if($is_dq) echo '<span class="dq-text">'.($r['dq_reason'] ?? 'DQ').'</span>';
                                elseif($no_time && !$r['final_rank']) echo '<span class="text-gray-400 text-[7pt]">DNS</span>';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php endforeach; ?>

        <?php if($countData == 0): ?>
            <div class="text-center py-20 border-2 border-dashed border-gray-300 rounded-xl mt-10">
                <p class="text-gray-400 font-bold text-xl">Belum ada hasil lomba yang tersimpan.</p>
                <p class="text-gray-400 text-sm mt-2">Pastikan Anda sudah melakukan input hasil di menu Result.</p>
            </div>
        <?php endif; ?>

        <?php if(!empty($footerSponsors)): ?>
        <div class="footer-sponsor">
            <div class="sponsor-line-separator"></div>
            <div class="sponsor-logo-container">
                <?php foreach($footerSponsors as $img): ?>
                    <img src="../../../public/<?= $img ?>">
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div> 
</div>