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

// 3. AMBIL PROFIL EVENT
$stmtProfile = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtProfile->execute([$eventId]);
$eventProfile = $stmtProfile->fetch(PDO::FETCH_ASSOC);

if (!$eventProfile) die("Data Event tidak ditemukan.");

// AMBIL SPONSOR (FETCH_COLUMN agar jadi array nama file)
$stmtFooter = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE event_id = ?");
$stmtFooter->execute([$eventId]);
$footerSponsors = $stmtFooter->fetchAll(PDO::FETCH_COLUMN);

// 4. SETUP VARIABEL TAMPILAN
$header_title    = strtoupper($eventProfile['nama_event']);
$venue_name      = strtoupper($eventProfile['venue_name'] ?? $eventProfile['lokasi']);
$total_lintasan  = (int)$eventProfile['lane_count'];
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
        /* Padding bawah disiapkan untuk Footer */
        padding: 5mm 10mm 35mm 10mm; 
        color: #000; 
        font-family: 'Roboto Condensed', sans-serif;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        
        /* [FIX] Agar footer absolute menempel pada kertas ini, bukan body */
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
    .event-block { 
        margin-bottom: 15px; 
    }
    
    .event-header-grid {
        display: grid; grid-template-columns: 80px 1fr 80px; align-items: center;
        border-bottom: 2px solid #000; margin-bottom: 5px; padding-bottom: 2px;
    }
    .event-number { font-size: 14pt; font-weight: 900; line-height: 1; }
    .event-title { font-size: 11pt; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .babak-badge { font-size: 9pt; padding: 2px 5px; background: #eee; border-radius: 4px; font-weight: bold; }

    /* TABEL HEAT */
    .heat-wrapper { 
        margin-bottom: 8px; 
        page-break-inside: avoid; 
    }
    .heat-header { 
        text-align: right; font-weight: bold; font-size: 9pt; 
        border-bottom: 1px solid #000; margin-bottom: 1px; 
    }
    
    .heat-table { width: 100%; border-collapse: collapse; font-size: 7.5pt; table-layout: fixed; }
    
    .heat-table th { 
        background: #f0f0f0; border-bottom: 1px solid #000; border-top: 1px solid #000; 
        padding: 2px 4px; font-weight: bold; text-transform: uppercase;
    }
    
    .heat-table td { 
        border-bottom: 1px solid #ccc; 
        padding: 2px 4px; 
        white-space: normal; word-wrap: break-word; line-height: 1.1; 
        vertical-align: middle;
    }
    
    .col-center { text-align: center; } 
    .col-right { text-align: right; } 
    .result-dots { color: #ccc; letter-spacing: 1px; font-size: 6pt; }

    /* FOOTER SPONSOR (FIXED POSITIONING FOR PAPER) */
    .footer-sponsor {
        /* [FIX] Absolute ke bawah kertas, bukan Fixed ke layar */
        position: absolute; 
        bottom: 0; 
        left: 0; 
        width: 100%; 
        padding-bottom: 5mm; /* Jarak dari bibir kertas bawah */
        display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
        z-index: 10;
        pointer-events: none;
    }
    .sponsor-line-separator { width: 90%; border-top: 3px double #000; margin-bottom: 5px; margin-left: auto; margin-right: auto; }
    .sponsor-logo-container { display: flex; justify-content: center; align-items: center; gap: 15px; width: 100%; padding: 0 10px; }
    .sponsor-logo-container img { height: 40px; width: auto; object-fit: contain; filter: grayscale(100%); opacity: 0.9; }

    /* === KHUSUS PRINT === */
    @media print {
        @page { 
            size: A4; 
            margin: 5mm 5mm 10mm 5mm; 
        }
        
        nav, aside, header, .sidebar, .no-print, .fixed, .navbar, .topbar, .sticky, #sidebar { 
            display: none !important; 
        }

        body, html { margin: 0 !important; padding: 0 !important; background: white !important; width: 100%; height: 100%; }

        #print-wrapper {
            margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: 100% !important;
            position: static !important; background: white !important; border: none !important; display: block !important;
        }

        .paper-sheet { 
            width: 100% !important; margin: 0 !important;
            /* Reset padding bawah agar tidak terlalu besar di print, karena footer pakai Fixed */
            padding: 0 0 25mm 0 !important; 
            box-shadow: none !important; 
            min-height: auto;
        }

        .footer-sponsor { 
            /* [FIX] Saat Print, kita gunakan FIXED agar muncul di SETIAP HALAMAN */
            position: fixed; 
            bottom: 0; left: 0; right: 0;
            padding-bottom: 2mm; 
            background: white; 
        }
        
        .heat-table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; background-color: #eee !important; }
    }
</style>

<div id="print-wrapper" class="p-4 sm:ml-64 pt-24 min-h-screen bg-slate-100 text-slate-900 font-sans">
    
    <div class="max-w-[210mm] mx-auto mb-6 flex justify-between items-center no-print">
        <div>
            <h2 class="text-lg font-bold text-slate-700">FULL BOOK STARTLIST</h2>
            <p class="text-xs text-slate-500">
                Mode: <span class="font-bold text-blue-600 uppercase"><?= (stripos($participationType, 'sekolah') !== false || stripos($participationType, 'school') !== false) ? 'ANTAR SEKOLAH' : 'ANTAR CLUB' ?></span>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="index.php" class="bg-white border border-slate-300 px-4 py-2 rounded text-xs font-bold uppercase hover:bg-slate-50">Kembali</a>
            <button onclick="window.print()" class="bg-slate-900 text-white px-6 py-2 rounded text-xs font-bold uppercase hover:bg-slate-800 flex items-center gap-2">🖨️ Cetak Full</button>
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
                    <p class="text-[9pt] font-bold uppercase text-gray-800 mt-1"><?= htmlspecialchars($venue_name) ?></p>
                <?php endif; ?>
                <p class="text-[8pt] font-bold uppercase text-gray-500 mt-1"><?= htmlspecialchars($header_date_range) ?></p>
                
                <div class="inline-block border-2 border-black px-6 py-1 mt-2">
                    <p class="text-xl font-black uppercase tracking-[0.2em]">BUKU ACARA</p>
                </div>
            </div>
            <div class="logo-box">
                <?php if($logo_right): ?><img src="<?= $logo_right ?>"><?php endif; ?>
            </div>
        </div>

        <?php 
        $countData = 0;
        foreach($all_events as $event): 
            $cat_id = $event['id'];
            
            // JUDUL
            $cleanStroke = trim(str_ireplace(['Gaya', 'GAYA'], '', $event['stroke'] ?? ''));
            $gender_label = (in_array($event['jenis_kelamin'], ['L','Male'])) ? 'PUTRA' : 'PUTRI';
            $jarak_gaya  = $event['distance'] . " M GAYA " . strtoupper($cleanStroke) . " - " . ($event['age_group']??'') . " " . $gender_label . $poolSuffix;
            
            // QUERY PESERTA
            $sql = "SELECT ee.heat, ee.lane, ee.entry_time, 
                    s.nama_atlet, s.tanggal_lahir, s.asal_sekolah,
                    u.nama_lengkap as club_name
                    FROM event_entries ee
                    JOIN swimmers s ON ee.swimmer_id = s.id
                    LEFT JOIN users u ON ee.club_id = u.id
                    WHERE ee.category_id = ? AND ee.heat IS NOT NULL 
                    ORDER BY ee.heat ASC, ee.lane ASC";
            $stmt = $pdo->prepare($sql); 
            $stmt->execute([$cat_id]); 
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if(empty($entries)) continue; 
            $countData++;

            $heats = []; 
            foreach($entries as $r) { $heats[$r['heat']][$r['lane']] = $r; }
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
                    <span class="babak-badge">FINAL</span>
                </div>
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
                                <th class="col-center">
                                    <?= (stripos($participationType, 'sekolah') !== false || stripos($participationType, 'school') !== false) ? 'SEKOLAH / UNIV' : 'TIM / CLUB' ?>
                                </th>
                                <th class="col-right">ENTRY</th>
                                <th class="col-right">...</th>
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
                                    <td class="col-center font-mono text-gray-700">
                                        <?= formatLahir($s['tanggal_lahir'], $event_year) ?>
                                    </td>
                                    <td class="col-center font-bold text-gray-800">
                                        <?= shortenName(getTeamName($s, $participationType)) ?>
                                    </td>
                                    <td class="col-right font-mono font-bold">
                                        <?= ($s['entry_time'] == '99:99.99' || !$s['entry_time']) ? 'NT' : $s['entry_time'] ?>
                                    </td>
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
                <p class="text-gray-400 text-sm mt-2">Pastikan sudah melakukan seeding.</p>
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

    </div> </div>