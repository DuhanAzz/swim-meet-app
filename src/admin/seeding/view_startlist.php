<?php
// src/admin/seeding/view_startlist.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// 1. CEK KEAMANAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

// AMBIL ID NOMOR LOMBA
$cat_id = $_GET['event_id'] ?? ($_GET['category_id'] ?? null);
if (!$cat_id) { header("Location: index.php"); exit; }

// 2. CONFIG CETAK
$pc = $_SESSION['print_config'] ?? [
    'show_event_no' => true, 'show_date' => true, 'show_event_name' => true,
    'show_group' => true, 'show_gender' => true, 'show_pool' => true, 'show_round' => true
];

// 3. AMBIL INFO NOMOR LOMBA
$stmtRace = $pdo->prepare("SELECT * FROM event_numbers WHERE id = ?");
$stmtRace->execute([$cat_id]);
$raceInfo = $stmtRace->fetch(PDO::FETCH_ASSOC);

if (!$raceInfo) die("Nomor lomba tidak ditemukan.");

// ==========================================
// [FIX LOGIKA PENGAMBILAN EVENT]
// ==========================================
$eventProfile = [];
if (!empty($raceInfo['organizer_id'])) {
    // Cari event terbaru milik User ini
    $stmtEvent = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtEvent->execute([$raceInfo['organizer_id']]);
    $eventProfile = $stmtEvent->fetch(PDO::FETCH_ASSOC);
}

// Fallback: Cari by ID langsung
if (!$eventProfile && !empty($raceInfo['organizer_id'])) {
    $stmtEvent = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmtEvent->execute([$raceInfo['organizer_id']]);
    $eventProfile = $stmtEvent->fetch(PDO::FETCH_ASSOC);
}

if (!$eventProfile) { $eventProfile = []; }
// ==========================================


// HEADER VARIABLES
$header_title = strtoupper($eventProfile['nama_event'] ?? 'NAMA EVENT BELUM DISET');
$venue_name   = strtoupper($eventProfile['venue_name'] ?? ($eventProfile['lokasi'] ?? ''));
$event_date   = !empty($eventProfile['event_start_date']) ? $eventProfile['event_start_date'] : date('Y-m-d');
$total_lintasan = (int)($eventProfile['lane_count'] ?? 8);
$pool_type    = strtoupper($eventProfile['pool_type'] ?? 'LCM');
$parentEventId = $eventProfile['id'] ?? 0;

// AMBIL TIPE PARTISIPASI
$participationType = !empty($eventProfile['participation_type']) ? $eventProfile['participation_type'] : 'club';

// LOGO
$logo_left  = !empty($eventProfile['logo_left']) ? '../../../public/' . $eventProfile['logo_left'] : null;
$logo_right = !empty($eventProfile['logo_right']) ? '../../../public/' . $eventProfile['logo_right'] : null;

// TANGGAL
$display_date = strtoupper(date('d F Y', strtotime($event_date)));
$event_year   = date('Y', strtotime($event_date));

// KOP SURAT
if(!empty($eventProfile['event_end_date']) && strtotime($eventProfile['event_start_date']) != strtotime($eventProfile['event_end_date'])) {
    $header_date_range = date('d', strtotime($eventProfile['event_start_date'])) . ' - ' . date('d F Y', strtotime($eventProfile['event_end_date']));
} else {
    $header_date_range = $display_date;
}

// 4. JUDUL DINAMIS
$judul_parts = [];
if(!empty($pc['show_event_name'])) {
    $cleanStroke = trim(str_ireplace(['Gaya', 'GAYA'], '', $raceInfo['stroke'] ?? ''));
    $judul_parts[] = ($raceInfo['distance'] ?? '') . " M GAYA " . strtoupper($cleanStroke);
}
if(!empty($pc['show_group']))      $judul_parts[] = ($raceInfo['age_group'] ?? '-'); 
if(!empty($pc['show_gender']))     $judul_parts[] = (in_array($raceInfo['jenis_kelamin'], ['L','Male'])) ? 'PUTRA' : 'PUTRI';
if(!empty($pc['show_pool']))       $judul_parts[] = $pool_type;

$judul_tengah_dinamis = implode(" - ", $judul_parts);
$nomor_acara_dinamis  = !empty($pc['show_event_no']) ? "#" . $raceInfo['event_number'] : "";
$babak_dinamis        = !empty($pc['show_round']) ? "FINAL" : "";

// 5. AMBIL DATA PESERTA
try {
    $sql = "SELECT ee.heat as heat_no, ee.lane as lane_no, ee.entry_time,
            s.nama_atlet, s.tanggal_lahir, 
            u.nama_lengkap as club_name,  
            s.asal_sekolah
            FROM event_entries ee
            JOIN swimmers s ON ee.swimmer_id = s.id
            LEFT JOIN users u ON ee.club_id = u.id
            LEFT JOIN payments p ON p.user_id = ee.club_id AND p.event_id = ee.event_id
            WHERE ee.category_id = ? AND ee.heat IS NOT NULL 
            AND (p.status = 'Paid' OR p.status = 'Verified') 
            ORDER BY ee.heat ASC, ee.lane ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cat_id]);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

// SPONSOR
$stmtSpon = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE event_id = ?");
$stmtSpon->execute([$parentEventId]);
$sponsors = $stmtSpon->fetchAll(PDO::FETCH_COLUMN);

// HELPERS
function formatLahir($tgl, $year) {
    if(!$tgl || $tgl == '0000-00-00') return '-';
    $by = date('Y', strtotime($tgl));
    return $by . " (" . ($year - $by) . ")";
}
function shortenName($name) {
    $name = trim(preg_replace('/\s+/', ' ', $name ?? ''));
    // Kita tidak perlu shorten terlalu agresif karena sekarang bisa wrap (2 baris)
    // if (strlen($name) > 22) return substr($name, 0, 22) . '...';
    return $name;
}

// LOGIKA NAMA TIM
function getTeamName($row, $type) {
    $club   = $row['club_name'] ?? '';     
    $school = $row['asal_sekolah'] ?? '';  
    
    $club = trim($club);
    $school = trim($school);

    if (stripos($type, 'sekolah') !== false || stripos($type, 'school') !== false || stripos($type, 'universitas') !== false) {
        return !empty($school) ? $school : '-';
    } else {
        return !empty($club) ? $club : '-';
    }
}

$heats = [];
foreach ($raw_data as $row) {
    $heats[$row['heat_no']][$row['lane_no']] = $row;
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
        /* Padding bawah disisakan untuk sponsor */
        padding: 5mm 10mm 25mm 10mm; 
        color: #000; 
        position: relative; 
        font-family: 'Roboto Condensed', sans-serif;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    /* HEADER */
    .page-header {
        /* Lebih rapat paddingnya */
        padding: 5px 0 10px 0; 
        border-bottom: 2px double #000; 
        margin-bottom: 10px;
        display: flex; justify-content: space-between; align-items: center; width: 100%;
    }
    /* Logo diperkecil sedikit agar header tidak makan tempat */
    .logo-box { width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; }
    .logo-box img { max-height: 100%; max-width: 100%; object-fit: contain; }

    .header-content h1 { font-size: 14pt; margin-bottom: 2px; }
    .header-content p { font-size: 9pt; margin: 0; }
    .buku-acara-badge { font-size: 12pt; padding: 2px 10px; margin-top: 5px; border-width: 2px; }

    /* INFO EVENT BARIS TENGAH */
    .event-header-grid {
        display: grid; grid-template-columns: 80px 1fr 80px; align-items: center;
        border-bottom: 1px solid #000; margin-bottom: 10px; padding-bottom: 5px;
    }
    .event-number { font-size: 14pt; font-weight: 900; line-height: 1; }
    .event-title { font-size: 11pt; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .babak-badge { font-size: 9pt; padding: 2px 5px; }
    
    /* TABEL HEAT */
    .heat-wrapper { margin-bottom: 10px; page-break-inside: avoid; }
    .heat-header { text-align: right; font-weight: bold; font-size: 9pt; border-bottom: 1px solid #000; margin-bottom: 1px; }
    
    .heat-table { width: 100%; border-collapse: collapse; font-size: 7.5pt; table-layout: fixed; }
    .heat-table th { 
        background: #f0f0f0; border-bottom: 1px solid #000; border-top: 1px solid #000; 
        padding: 2px 4px; font-weight: bold; text-transform: uppercase; 
    }
    .heat-table td { 
        border-bottom: 1px solid #ccc; 
        padding: 2px 4px; 
        /* Text Wrapping */
        white-space: normal; 
        word-wrap: break-word; 
        line-height: 1.1; /* Jarak antar baris teks rapat */
        vertical-align: middle;
    }
    
    .col-center { text-align: center; } 
    .col-right { text-align: right; } 
    .result-dots { color: #ccc; letter-spacing: 1px; font-size: 6pt; }

    /* FOOTER SPONSOR */
    .footer-sponsor {
        position: absolute; bottom: 0; left: 0; width: 100%; 
        padding-bottom: 5mm; /* Jarak dari bawah kertas */
        display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
        background: white; /* Hindari transparan agar tidak tertimpa */
    }
    .sponsor-line-separator { width: 100%; border-top: 3px double #000; margin-bottom: 5px; }
    .sponsor-logo-container { display: flex; justify-content: center; align-items: center; gap: 15px; width: 100%; padding: 0 10px; }
    .sponsor-logo-container img { height: 40px; width: auto; object-fit: contain; filter: grayscale(100%); opacity: 0.9; }

    /* === KHUSUS PRINT === */
    @media print {
        @page { 
            size: A4; 
            /* Margin kertas tipis agar muat banyak */
            margin: 5mm; 
        }
        
        nav, aside, header, .sidebar, .no-print, .fixed, .navbar, .topbar, .sticky, #sidebar { 
            display: none !important; 
        }

        body, html {
            margin: 0 !important; padding: 0 !important; background: white !important; width: 100%; height: 100%;
        }

        #print-wrapper {
            margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: 100% !important;
            position: static !important; background: white !important; border: none !important; display: block !important;
        }

        .paper-sheet { 
            width: 100% !important; margin: 0 !important;
            padding: 0 !important; /* Reset padding wrapper, kita andalkan margin @page */
            box-shadow: none !important; 
            min-height: auto;
            /* Sisakan ruang di bawah untuk footer fixed */
            padding-bottom: 25mm !important; 
        }

        /* Agar sponsor muncul di SETIAP HALAMAN */
        .footer-sponsor { 
            position: fixed; 
            bottom: 0; 
            left: 0; right: 0;
            padding-bottom: 2mm;
            background: white;
            z-index: 9999;
        }

        /* Paksa background table head tercetak */
        .heat-table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; background-color: #eee !important; }
    }
</style>

<div id="print-wrapper" class="p-4 sm:ml-64 pt-24 min-h-screen bg-slate-100 text-slate-900 font-sans">
    
    <div class="max-w-[210mm] mx-auto mb-6 flex justify-between items-center no-print">
        <div>
            <h2 class="text-lg font-bold text-slate-700">PREVIEW (HEMAT KERTAS)</h2>
            <p class="text-xs text-slate-500">
                Mode: 
                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded font-bold uppercase">
                    <?= (stripos($participationType, 'sekolah') !== false || stripos($participationType, 'school') !== false) ? 'ANTAR SEKOLAH' : 'ANTAR CLUB' ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="index.php" class="bg-white border border-slate-300 px-4 py-2 rounded text-xs font-bold uppercase hover:bg-slate-50">Kembali</a>
            <button onclick="window.print()" class="bg-slate-900 text-white px-6 py-2 rounded text-xs font-bold uppercase hover:bg-slate-800 flex items-center gap-2">🖨️ Cetak</button>
        </div>
    </div>

    <div class="paper-sheet">
        
        <div class="page-header">
            <div class="logo-box">
                <?php if($logo_left): ?><img src="<?= $logo_left ?>"><?php endif; ?>
            </div>
            <div class="text-center flex-1 px-4 header-content">
                <h1 class="font-black uppercase leading-tight"><?= htmlspecialchars($header_title) ?></h1>
                <?php if($venue_name): ?>
                    <p class="font-bold uppercase text-gray-800"><?= htmlspecialchars($venue_name) ?></p>
                <?php endif; ?>
                <p class="font-bold uppercase text-gray-500"><?= htmlspecialchars($header_date_range) ?></p>
                
                <div class="inline-block border-2 border-black buku-acara-badge">
                    <p class="font-black uppercase tracking-[0.2em]">BUKU ACARA</p>
                </div>
            </div>
            <div class="logo-box">
                <?php if($logo_right): ?><img src="<?= $logo_right ?>"><?php endif; ?>
            </div>
        </div>

        <div class="event-header-grid">
            <div class="event-num-box">
                <div class="event-number"><?= $nomor_acara_dinamis ?></div>
                <?php if(!empty($pc['show_date'])): ?>
                <div class="text-[8pt] font-bold"><?= strtoupper($display_date) ?></div>
                <?php endif; ?>
            </div>
            <div class="event-title-box text-center">
                <div class="event-title"><?= $judul_tengah_dinamis ?></div>
            </div>
            <div class="text-right">
                <span class="font-bold bg-gray-100 border border-gray-300 babak-badge rounded">
                    <?= $babak_dinamis ?>
                </span>
            </div>
        </div>

        <?php if(empty($heats)): ?>
            <div class="text-center py-12 border-y border-dashed border-gray-400 mt-10">
                <p class="italic text-gray-500 font-bold">Data Seeding Belum Tersedia.</p>
            </div>
        <?php else: ?>
            <?php foreach($heats as $heatNo => $lanesData): ?>
            <div class="heat-wrapper">
                <div class="heat-header">SERI <?= str_pad($heatNo, 2, '0', STR_PAD_LEFT) ?></div>
                <table class="heat-table">
                    <colgroup>
                        <col style="width: 5%;">  <col style="width: 35%;"> <col style="width: 10%;"> <col style="width: 30%;"> <col style="width: 12%;"> <col style="width: 8%;">  </colgroup>
                    <thead>
                        <tr>
                            <th class="col-center">LN</th>
                            <th>NAMA ATLET</th>
                            <th class="col-center">LHR</th>
                            <th class="col-center">
                                <?= (stripos($participationType, 'sekolah') !== false || stripos($participationType, 'school') !== false) ? 'SEKOLAH / UNIV' : 'TIM / CLUB' ?>
                            </th>
                            <th class="col-right">ENTRY</th>
                            <th class="col-right">HASIL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for($ln = 1; $ln <= $total_lintasan; $ln++): $s = $lanesData[$ln] ?? null; ?>
                        <tr>
                            <td class="col-center font-bold font-mono"><?= $ln ?></td>
                            <?php if($s): ?>
                                <td class="font-bold text-black">
                                    <?= shortenName($s['nama_atlet']) ?>
                                </td>
                                <td class="col-center text-gray-700 font-mono">
                                    <?= formatLahir($s['tanggal_lahir'], $event_year) ?>
                                </td>
                                <td class="col-center text-gray-800">
                                    <?= shortenName(getTeamName($s, $participationType)) ?>
                                </td>
                                <td class="col-right font-mono font-bold">
                                    <?= ($s['entry_time'] == '99:99.99' || !$s['entry_time']) ? 'NT' : $s['entry_time'] ?>
                                </td>
                                <td class="col-right font-mono result-dots">.......</td>
                            <?php else: ?>
                                <td colspan="5" class="text-gray-300 italic text-[7pt]">&lt; KOSONG &gt;</td>
                            <?php endif; ?>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if(!empty($sponsors)): ?>
        <div class="footer-sponsor">
            <div class="sponsor-line-separator"></div>
            <div class="sponsor-logo-container">
                <?php foreach($sponsors as $img): ?>
                    <img src="../../../public/<?= $img ?>">
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>