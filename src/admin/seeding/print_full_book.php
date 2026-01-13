<?php
// src/admin/seeding/print_full_book.php
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

// --- LOGIKA UPLOAD COVER ---
$msg_cover = "";
if (isset($_FILES['cover_file']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $baseDir = realpath(__DIR__ . '/../../../'); 
    $targetDir = $baseDir . "/public/uploads/covers/";
    if (!file_exists($targetDir)) { mkdir($targetDir, 0777, true); }
    
    if (file_exists($targetDir)) {
        $fileName = "cover_" . $eventId . "_" . time() . "." . pathinfo($_FILES["cover_file"]["name"], PATHINFO_EXTENSION);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        
        $allowTypes = array('jpg','png','jpeg','gif');
        if(in_array($fileType, $allowTypes)){
            if(move_uploaded_file($_FILES["cover_file"]["tmp_name"], $targetFilePath)){
                $dbPath = "uploads/covers/" . $fileName;
                $stmtUpd = $pdo->prepare("UPDATE events SET cover_image = ? WHERE id = ?");
                $stmtUpd->execute([$dbPath, $eventId]);
                $msg_cover = "Cover berhasil diupload!";
            } else { $msg_cover = "Gagal upload. Cek permission folder."; }
        } else { $msg_cover = "Format harus JPG/PNG."; }
    }
}

// 3. AMBIL DATA
$stmtProfile = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtProfile->execute([$eventId]);
$eventProfile = $stmtProfile->fetch(PDO::FETCH_ASSOC);

if (!$eventProfile) die("Data Event tidak ditemukan.");

$stmtFooter = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE event_id = ?");
$stmtFooter->execute([$eventId]);
$footerSponsors = $stmtFooter->fetchAll(PDO::FETCH_COLUMN);

// 4. SETUP VARIABEL
$header_title    = strtoupper($eventProfile['nama_event']);
$venue_name      = strtoupper($eventProfile['venue_name'] ?? $eventProfile['lokasi']);

// FIX: Pastikan total lintasan minimal 6 atau 8 jika settingan kosong/salah
$total_lintasan  = (int)$eventProfile['lane_count'];
if ($total_lintasan < 4) $total_lintasan = 8; // Default ke 8 jika data mencurigakan

$participationType = $eventProfile['participation_type'] ?? 'club';
$coverImagePath  = !empty($eventProfile['cover_image']) ? '../../../public/' . $eventProfile['cover_image'] : null;

$event_date      = $eventProfile['event_start_date'];
$display_date    = strtoupper(date('d F Y', strtotime($event_date)));
$event_year      = date('Y', strtotime($event_date));
if(!empty($eventProfile['event_end_date']) && strtotime($eventProfile['event_start_date']) != strtotime($eventProfile['event_end_date'])) {
    $header_date_range = date('d', strtotime($eventProfile['event_start_date'])) . ' - ' . date('d F Y', strtotime($eventProfile['event_end_date']));
} else { $header_date_range = $display_date; }

$logo_left  = !empty($eventProfile['logo_left']) ? '../../../public/' . $eventProfile['logo_left'] : null;
$logo_right = !empty($eventProfile['logo_right']) ? '../../../public/' . $eventProfile['logo_right'] : null;
$poolSuffix = ($eventProfile['pool_type'] == 'SCM') ? ' - SCM' : ' - LCM';

// 5. QUERY DATA
$stmtEvents = $pdo->prepare("SELECT * FROM event_numbers WHERE organizer_id = ? ORDER BY CAST(event_number AS UNSIGNED) ASC");
$stmtEvents->execute([$uid]);
$all_events = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);

// HELPER
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

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700;900&family=Courier+Prime:wght@400;700&display=swap');
    
    .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
    .font-mono { font-family: 'Courier Prime', monospace; }

    /* LAYOUT SCREEN STANDARD */
    .paper-sheet {
        width: 210mm; min-height: 297mm; background: white; margin: 0 auto;
        padding: 5mm 10mm 10mm 10mm; 
        color: #000; font-family: 'Roboto Condensed', sans-serif;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1); position: relative; 
    }

    /* COVER SHEET (Di Layar) */
    .cover-sheet {
        width: 210mm; height: 297mm; background: white; margin: 0 auto 20px auto;
        display: flex; align-items: center; justify-content: center;
        overflow: hidden; border: 1px solid #eee;
    }
    .cover-sheet img { width: 100%; height: 100%; object-fit: cover; }

    /* KOP SURAT */
    .page-header {
        padding: 5px 0 5px 0; border-bottom: 2px double #000; margin-bottom: 8px;
        display: flex; justify-content: space-between; align-items: center; width: 100%;
    }
    .logo-box { width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; }
    .logo-box img { max-height: 100%; max-width: 100%; object-fit: contain; }

    /* EVENT BLOCK */
    .event-block { 
        margin-bottom: 15px; 
        page-break-inside: auto; 
    }
    
    .event-header-grid {
        display: flex; justify-content: space-between; align-items: center;
        border-bottom: 1px solid #000; margin-bottom: 3px; padding-bottom: 2px;
        gap: 10px;
        page-break-after: avoid; break-after: avoid;
    }

    .event-num-box { flex: 0 0 70px; text-align: left; }
    .event-number { font-size: 12pt; font-weight: 900; line-height: 1; }
    .event-title-box { flex: 1; text-align: center; }
    .event-title { font-size: 10pt; font-weight: 800; text-transform: uppercase; line-height: 1.1; }
    .event-badge-box { flex: 0 0 70px; text-align: right; }
    .babak-badge { font-size: 8pt; padding: 1px 4px; background: #eee; border-radius: 4px; font-weight: bold; }

    /* HEAT WRAPPER (Per Seri) - PENTING: AVOID BREAK */
    .heat-wrapper { 
        margin-bottom: 12px; 
        page-break-inside: avoid !important; 
        break-inside: avoid !important;
        /* Tambahkan border agar terlihat sebagai satu blok utuh */
        display: block;
    }

    .heat-header { text-align: right; font-weight: bold; font-size: 8pt; border-bottom: 1px solid #000; margin-bottom: 1px; }
    
    /* TABEL: Setting Fixed Layout */
    .heat-table { width: 100%; border-collapse: collapse; font-size: 7pt; table-layout: fixed; }
    .heat-table th { background: #f0f0f0; border-bottom: 1px solid #000; padding: 1px 3px; font-weight: bold; text-transform: uppercase; }
    
    /* BARIS DAN SEL - FIX HEIGHT */
    .heat-table tr { 
        page-break-inside: avoid; break-inside: avoid; 
        height: 16px; /* Tinggi Fix per baris */
    }
    .heat-table td { 
        border-bottom: 1px solid #ccc; 
        padding: 0 3px; /* Padding minimal */
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; 
        line-height: 16px; /* Samakan dengan tinggi baris */
        vertical-align: middle;
        height: 16px; /* Pastikan sel tidak collapse */
    }

    .col-center { text-align: center; } 
    .col-right { text-align: right; } 

    /* FOOTER SPONSOR (DEFAULT HIDDEN ON SCREEN) */
    .footer-sponsor { display: none; }
    .sponsor-line-separator { width: 95%; border-top: 1px solid #000; margin-bottom: 3px; margin-left: auto; margin-right: auto; }
    .sponsor-logo-container { display: flex; justify-content: center; align-items: center; gap: 10px; width: 100%; padding: 0 10px; }
    .sponsor-logo-container img { height: 25px; width: auto; object-fit: contain; filter: grayscale(100%); opacity: 0.9; }

    /* === KHUSUS PRINT SETTINGS === */
    @media print {
        @page { size: A4; margin: 0; }
        
        nav, aside, header, .sidebar, .no-print, .fixed, .navbar, .topbar { display: none !important; }
        body, html { margin: 0 !important; padding: 0 !important; background: white !important; width: 100%; height: 100%; }

        #print-wrapper { margin: 0 !important; padding: 0 !important; width: 100%; }

        /* COVER FULL HALAMAN (OVERLAY) */
        .cover-sheet {
            display: block !important;
            position: relative;
            width: 100vw !important;
            height: 100vh !important;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            z-index: 99999;
            background: white;
            page-break-after: always;
        }
        
        /* PENGATURAN KERTAS ISI */
        .paper-sheet { 
            width: 100% !important; margin: 0 !important;
            padding: 10mm 10mm 25mm 10mm !important; 
            box-shadow: none !important; min-height: auto;
        }

        /* Footer Fixed */
        .footer-sponsor { 
            display: flex !important;
            position: fixed; bottom: 0; left: 0; right: 0;
            height: 20mm; 
            padding-bottom: 2mm; background: white; z-index: 1000;
            flex-direction: column; justify-content: flex-end; align-items: center;
        }
        
        .heat-table th { -webkit-print-color-adjust: exact; background-color: #eee !important; }
        
        /* Paksa semua baris tercetak meski kosong */
        .heat-table tr { display: table-row !important; visibility: visible !important; }
    }
</style>

<div id="print-wrapper" class="p-4 sm:ml-64 pt-24 min-h-screen bg-slate-100 text-slate-900 font-sans">
    
    <div class="max-w-[210mm] mx-auto mb-6 bg-white p-4 rounded-xl border border-slate-200 shadow-sm sticky top-20 z-50 no-print space-y-4">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-lg font-bold text-slate-700">FULL BOOK STARTLIST</h2>
                <p class="text-xs text-slate-500">Mode: <strong>Full Lanes & Cover</strong></p>
                <?php if($msg_cover): ?><span class="text-xs font-bold text-blue-600 block mt-1"><?= $msg_cover ?></span><?php endif; ?>
            </div>
            <div class="flex gap-2">
                <a href="index.php" class="bg-white border border-slate-300 px-4 py-2 rounded text-xs font-bold uppercase hover:bg-slate-50">Kembali</a>
                <button onclick="window.print()" class="bg-slate-900 text-white px-6 py-2 rounded text-xs font-bold uppercase hover:bg-slate-800 flex items-center gap-2">🖨️ Cetak</button>
            </div>
        </div>

        <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 flex items-center gap-4">
            <form action="" method="POST" enctype="multipart/form-data" class="flex items-center gap-2 w-full">
                <div class="flex-1">
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Upload Cover (Gambar Full A4):</label>
                    <input type="file" name="cover_file" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" accept="image/*" required>
                </div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-xs font-bold uppercase hover:bg-blue-700">Upload</button>
            </form>
            <?php if($coverImagePath): ?><div class="text-xs text-green-600 font-bold flex items-center gap-1">✅ Ada Cover</div><?php endif; ?>
        </div>
    </div>

    <?php if($coverImagePath): ?>
    <div class="cover-sheet">
        <img src="<?= $coverImagePath ?>" alt="Cover Buku Acara">
    </div>
    <?php endif; ?>

    <div class="paper-sheet">
        
        <div class="page-header">
            <div class="logo-box"><?php if($logo_left): ?><img src="<?= $logo_left ?>"><?php endif; ?></div>
            <div class="text-center flex-1 px-4">
                <h1 class="text-lg font-black uppercase leading-tight"><?= htmlspecialchars($header_title) ?></h1>
                <?php if($venue_name): ?><p class="text-[8pt] font-bold uppercase text-gray-800"><?= htmlspecialchars($venue_name) ?></p><?php endif; ?>
                <p class="text-[8pt] font-bold uppercase text-gray-500"><?= htmlspecialchars($header_date_range) ?></p>
            </div>
            <div class="logo-box"><?php if($logo_right): ?><img src="<?= $logo_right ?>"><?php endif; ?></div>
        </div>

        <?php 
        $countData = 0;
        foreach($all_events as $event): 
            $cat_id = $event['id'];
            $cleanStroke = trim(str_ireplace(['Gaya', 'GAYA'], '', $event['stroke'] ?? ''));
            $gender_label = (in_array($event['jenis_kelamin'], ['L','Male'])) ? 'PUTRA' : 'PUTRI';
            $jarak_gaya  = $event['distance'] . " M GAYA " . strtoupper($cleanStroke) . " - " . ($event['age_group']??'') . " " . $gender_label . $poolSuffix;
            
            $sql = "SELECT ee.heat, ee.lane, ee.entry_time, s.nama_atlet, s.tanggal_lahir, s.asal_sekolah, u.nama_lengkap as club_name
                    FROM event_entries ee JOIN swimmers s ON ee.swimmer_id = s.id LEFT JOIN users u ON ee.club_id = u.id
                    WHERE ee.category_id = ? AND ee.heat IS NOT NULL ORDER BY ee.heat ASC, ee.lane ASC";
            $stmt = $pdo->prepare($sql); $stmt->execute([$cat_id]); $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if(empty($entries)) continue; 
            $countData++;
            $heats = []; 
            foreach($entries as $r) { $heats[$r['heat']][$r['lane']] = $r; }
        ?>

        <div class="event-block">
            <div class="event-header-grid">
                <div class="event-num-box"><span class="event-number">#<?= $event['event_number'] ?></span></div>
                <div class="event-title-box"><span class="event-title"><?= $jarak_gaya ?></span></div>
                <div class="event-badge-box"><span class="babak-badge">FINAL</span></div>
            </div>

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
                                <th class="col-center">TIM</th>
                                <th class="col-right">ENTRY</th>
                                <th class="col-right">...</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for($ln = 1; $ln <= $total_lintasan; $ln++): $s = $lanesData[$ln] ?? null; ?>
                            <tr>
                                <td class="col-center font-bold font-mono"><?= $ln ?></td>
                                <?php if($s): ?>
                                    <td class="font-bold text-black"><?= shortenName($s['nama_atlet']) ?></td>
                                    <td class="col-center font-mono text-gray-700"><?= formatLahir($s['tanggal_lahir'], $event_year) ?></td>
                                    <td class="col-center font-bold text-gray-800"><?= shortenName(getTeamName($s, $participationType)) ?></td>
                                    <td class="col-right font-mono font-bold"><?= ($s['entry_time'] == '99:99.99' || !$s['entry_time']) ? 'NT' : $s['entry_time'] ?></td>
                                    <td class="col-right font-mono result-dots">.......</td>
                                <?php else: ?>
                                    <td colspan="5" class="text-gray-300 italic text-[6pt]">&lt; KOSONG &gt;</td>
                                <?php endif; ?>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php endforeach; ?>

        <?php if($countData == 0): ?>
            <div class="text-center py-20 border-2 border-dashed border-gray-300 rounded-xl mt-10">
                <p class="text-gray-400 font-bold text-xl">Belum ada data startlist.</p>
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