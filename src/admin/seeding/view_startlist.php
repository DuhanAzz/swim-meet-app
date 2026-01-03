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

// AMBIL HEADER EVENT
$stmtEvent = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmtEvent->execute([$raceInfo['organizer_id']]);
$eventProfile = $stmtEvent->fetch(PDO::FETCH_ASSOC);

// JIKA DATA EVENT KOSONG, PAKAI DEFAULT
$header_title = strtoupper($eventProfile['nama_event'] ?? 'NAMA EVENT BELUM DISET');
$venue_name   = strtoupper($eventProfile['venue_name'] ?? ($eventProfile['lokasi'] ?? ''));
$event_date   = !empty($eventProfile['event_start_date']) ? $eventProfile['event_start_date'] : date('Y-m-d');
$total_lintasan = (int)($eventProfile['lane_count'] ?? 8);
$pool_type    = strtoupper($eventProfile['pool_type'] ?? 'LCM');
$parentEventId = $eventProfile['id'] ?? 0;

// LOGO HEADER
$logo_left  = !empty($eventProfile['logo_left']) ? '../../../public/' . $eventProfile['logo_left'] : null;
$logo_right = !empty($eventProfile['logo_right']) ? '../../../public/' . $eventProfile['logo_right'] : null;

// TANGGAL FORMAT
$display_date = strtoupper(date('d F Y', strtotime($event_date)));
$event_year   = date('Y', strtotime($event_date));

// KOP SURAT TANGGAL
if(!empty($eventProfile['event_end_date']) && strtotime($eventProfile['event_start_date']) != strtotime($eventProfile['event_end_date'])) {
    $header_date_range = date('d', strtotime($eventProfile['event_start_date'])) . ' - ' . date('d F Y', strtotime($eventProfile['event_end_date']));
} else {
    $header_date_range = $display_date;
}

// 4. BANGUN JUDUL DINAMIS
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
            c.nama_klub as club_name, s.asal_sekolah
            FROM event_entries ee
            JOIN swimmers s ON ee.swimmer_id = s.id
            LEFT JOIN clubs c ON s.club_id = c.id
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

// 6. SPONSOR FOOTER
$stmtSpon = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE event_id = ?");
$stmtSpon->execute([$parentEventId]);
$sponsors = $stmtSpon->fetchAll(PDO::FETCH_COLUMN);

// --- HELPER FUNCTIONS ---
function formatLahir($tgl, $year) {
    if(!$tgl || $tgl == '0000-00-00') return '-';
    $by = date('Y', strtotime($tgl));
    return $by . " (" . ($year - $by) . ")";
}
function shortenName($name) {
    $name = trim(preg_replace('/\s+/', ' ', $name ?? ''));
    if (strlen($name) > 22) return substr($name, 0, 22) . '...';
    return $name;
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

    .paper-sheet {
        width: 210mm; min-height: 297mm; background: white; margin: 0 auto;
        padding: 10mm 10mm 30mm 10mm; /* Padding bawah dibesarkan agar footer tidak nabrak content */
        color: #000; position: relative; font-family: 'Roboto Condensed', sans-serif;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .page-header {
        padding: 10px 0 20px 0; border-bottom: 3px double #000; margin-bottom: 20px;
        display: flex; justify-content: space-between; align-items: center; width: 100%;
    }
    .logo-box { width: 90px; height: 90px; display: flex; align-items: center; justify-content: center; }
    .logo-box img { max-height: 100%; max-width: 100%; object-fit: contain; }

    .event-header-grid {
        display: grid; grid-template-columns: 100px 1fr 100px; align-items: center;
        border-bottom: 2px solid #000; margin-bottom: 15px; padding-bottom: 5px;
    }
    .event-number { font-size: 18pt; font-weight: 900; line-height: 1; }
    .event-title { font-size: 14pt; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
    
    .heat-wrapper { margin-bottom: 15px; page-break-inside: avoid; }
    .heat-header { text-align: right; font-weight: bold; font-size: 10pt; border-bottom: 1px solid #000; margin-bottom: 2px; }
    
    .heat-table { width: 100%; border-collapse: collapse; font-size: 8pt; table-layout: fixed; }
    .heat-table th { background: #f0f0f0; border-bottom: 1px solid #000; border-top: 1px solid #000; padding: 4px 6px; font-weight: bold; text-transform: uppercase; }
    .heat-table td { border-bottom: 1px solid #ddd; padding: 3px 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-transform: uppercase; }
    
    .col-center { text-align: center; } 
    .col-right { text-align: right; } 
    .result-dots { color: #ccc; letter-spacing: 2px; }

    /* --- FOOTER SPONSOR REVISI --- */
    .footer-sponsor {
        position: absolute; 
        bottom: 0; 
        left: 0; 
        width: 100%; 
        padding-bottom: 10mm;
        display: flex;
        flex-direction: column;
        align-items: center;  /* Pastikan di tengah secara horizontal */
        justify-content: flex-end;
    }

    .sponsor-line-separator {
        width: 100%;
        border-top: 4px double #000; /* Garis Double Tebal */
        margin-bottom: 15px;
    }

    .sponsor-logo-container {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
        width: 100%;
        padding: 0 20px;
    }

    .sponsor-logo-container img { 
        height: 60px; /* PERBESAR LOGO DI SINI */
        width: auto;
        object-fit: contain;
        filter: grayscale(100%); 
        opacity: 0.9; 
    }

    @media print {
        @page { size: A4; margin: 10mm; }
        body { background: white; margin: 0; padding: 0; }
        nav, aside, .no-print { display: none !important; }
        .p-4, .sm\:ml-64 { padding: 0 !important; margin: 0 !important; }
        .paper-sheet { width: 100%; box-shadow: none; padding: 0; margin: 0; min-height: 297mm; }
        .footer-sponsor { bottom: 0; position: absolute; }
    }
</style>

<div class="p-4 sm:ml-64 pt-24 min-h-screen bg-slate-100 text-slate-900 font-sans">
    
    <div class="max-w-[210mm] mx-auto mb-6 flex justify-between items-center no-print">
        <div>
            <h2 class="text-lg font-bold text-slate-700">PREVIEW START LIST</h2>
            <p class="text-xs text-slate-500">Footer Sponsor: Centered & Enlarged.</p>
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
            <div class="text-center flex-1 px-4">
                <h1 class="text-xl font-black uppercase leading-tight"><?= htmlspecialchars($header_title) ?></h1>
                <?php if($venue_name): ?>
                    <p class="text-sm font-bold uppercase text-gray-800 mt-1"><?= htmlspecialchars($venue_name) ?></p>
                <?php endif; ?>
                <p class="text-xs font-bold uppercase text-gray-500 mt-1"><?= htmlspecialchars($header_date_range) ?></p>
                
                <div class="inline-block border-2 border-black px-6 py-1 mt-2">
                    <p class="text-xl font-black uppercase tracking-[0.2em]">BUKU ACARA</p>
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
                <div class="text-[9pt] font-bold"><?= strtoupper($display_date) ?></div>
                <?php endif; ?>
            </div>
            <div class="event-title-box text-center">
                <div class="event-title"><?= $judul_tengah_dinamis ?></div>
            </div>
            <div class="text-right font-bold bg-gray-100 px-2 py-1 rounded">
                <?= $babak_dinamis ?>
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
                        <col style="width: 5%;">
                        <col style="width: 32%;">
                        <col style="width: 13%;">
                        <col style="width: 25%;">
                        <col style="width: 15%;">
                        <col style="width: 10%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="col-center">LN</th>
                            <th>NAMA ATLET</th>
                            <th class="col-center">LAHIR</th>
                            <th>TIM / SEKOLAH</th>
                            <th class="col-right">ENTRY</th>
                            <th class="col-right">HASIL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for($ln = 1; $ln <= $total_lintasan; $ln++): $s = $lanesData[$ln] ?? null; ?>
                        <tr>
                            <td class="col-center font-bold font-mono"><?= $ln ?></td>
                            <?php if($s): ?>
                                <td class="font-bold text-black" title="<?= $s['nama_atlet'] ?>">
                                    <?= shortenName($s['nama_atlet']) ?>
                                </td>
                                <td class="col-center text-gray-700 font-mono">
                                    <?= formatLahir($s['tanggal_lahir'], $event_year) ?>
                                </td>
                                <td class="text-gray-800">
                                    <?= shortenName(!empty($s['club_name']) ? $s['club_name'] : $s['asal_sekolah']) ?>
                                </td>
                                <td class="col-right font-mono font-bold">
                                    <?= ($s['entry_time'] == '99:99.99' || !$s['entry_time']) ? 'NT' : $s['entry_time'] ?>
                                </td>
                                <td class="col-right font-mono result-dots">.......</td>
                            <?php else: ?>
                                <td colspan="5" class="text-gray-300 italic text-[9px]">&lt; KOSONG &gt;</td>
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