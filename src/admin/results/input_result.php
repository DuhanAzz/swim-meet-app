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

// --- LOGIC: FUNGSI KONVERSI WAKTU KE MILIDETIK (UNTUK SORTING) ---
function timeToMs($time) {
    $time = trim($time);
    if (empty($time) || $time == 'NT' || $time == '99:99.99' || $time == '-') return 9999999999; 

    $parts = preg_split('/[:.]/', $time);
    $menit = 0; $detik = 0; $ms = 0;

    if (count($parts) == 3) {
        $menit = (int)$parts[0]; $detik = (int)$parts[1]; $ms = (int)$parts[2];
    } elseif (count($parts) == 2) {
        $detik = (int)$parts[0]; $ms = (int)$parts[1];
    } elseif (count($parts) == 1) {
        $detik = (int)$parts[0];
    }
    return ($menit * 60000) + ($detik * 1000) + ($ms * 10);
}

// --- PROSES SIMPAN DATA (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        $entries = $_POST['entries'] ?? [];

        // 1. Simpan Waktu & Status (DQ/DNF)
        $stmtUpd = $pdo->prepare("UPDATE event_entries SET final_time = ?, is_dq = ?, dq_reason = ? WHERE id = ?");

        foreach ($entries as $id => $data) {
            $time = trim($data['time'] ?? '');
            $status = $data['status']; // '', 'DQ', 'DNF', 'DNS'

            $is_dq = ($status !== '') ? 1 : 0;
            $reason = ($status !== '') ? $status : NULL;
            
            if ($is_dq) $time = NULL; 
            if ($time === '') $time = NULL;

            $stmtUpd->execute([$time, $is_dq, $reason, $id]);
        }

        // 2. Hitung Ranking Otomatis
        $stmtAll = $pdo->prepare("SELECT id, final_time, is_dq FROM event_entries WHERE category_id = ?");
        $stmtAll->execute([$cat_id]);
        $allSwimmers = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        $validSwimmers = [];
        $invalidSwimmers = [];

        foreach ($allSwimmers as $s) {
            if ($s['is_dq'] == 0 && !empty($s['final_time']) && $s['final_time'] != 'NT') {
                $s['ms'] = timeToMs($s['final_time']);
                $validSwimmers[] = $s;
            } else {
                $invalidSwimmers[] = $s;
            }
        }

        // Sort Valid Swimmers
        usort($validSwimmers, function($a, $b) { return $a['ms'] - $b['ms']; });

        $stmtRank = $pdo->prepare("UPDATE event_entries SET final_rank = ? WHERE id = ?");
        
        $rank = 1; $counter = 1; $prevMs = null;
        foreach ($validSwimmers as $s) {
            if ($prevMs !== null && $s['ms'] != $prevMs) { $rank = $counter; }
            $stmtRank->execute([$rank, $s['id']]);
            $prevMs = $s['ms']; $counter++;
        }

        foreach ($invalidSwimmers as $s) {
            $stmtRank->execute([NULL, $s['id']]);
        }

        $pdo->commit();
        $msg_success = "Data berhasil disimpan & Peringkat diperbarui!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $msg_error = "Gagal menyimpan: " . $e->getMessage();
    }
}

// --- AMBIL DATA UNTUK TAMPILAN ---
$stmtRace = $pdo->prepare("SELECT * FROM event_numbers WHERE id = ?");
$stmtRace->execute([$cat_id]);
$raceInfo = $stmtRace->fetch(PDO::FETCH_ASSOC);
if (!$raceInfo) die("Nomor lomba tidak ditemukan.");

// PROFIL EVENT
$eventProfile = [];
if (!empty($raceInfo['organizer_id'])) {
    // Ambil event terakhir dari user ini atau event spesifik jika ada relasi
    // Kita ambil event terakhir saja sebagai default
    $stmtEvent = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtEvent->execute([$raceInfo['organizer_id']]);
    $eventProfile = $stmtEvent->fetch(PDO::FETCH_ASSOC);
}
if (!$eventProfile) $eventProfile = [];

// SETUP VARIABEL TAMPILAN
$header_title = strtoupper($eventProfile['nama_event'] ?? 'KEJUARAAN RENANG');
$venue_name   = strtoupper($eventProfile['venue_name'] ?? ($eventProfile['lokasi'] ?? ''));
$event_date   = !empty($eventProfile['event_start_date']) ? $eventProfile['event_start_date'] : date('Y-m-d');
$total_lintasan = (int)($eventProfile['lane_count'] ?? 8);
$pool_type    = strtoupper($eventProfile['pool_type'] ?? 'LCM');
$poolSuffix   = ($pool_type == 'SCM') ? ' - SCM' : ' - LCM';
$participationType = $eventProfile['participation_type'] ?? 'club';

// Logo
$logo_left  = !empty($eventProfile['logo_left']) ? '../../../public/' . $eventProfile['logo_left'] : null;
$logo_right = !empty($eventProfile['logo_right']) ? '../../../public/' . $eventProfile['logo_right'] : null;

// Tanggal Header
$display_date = strtoupper(date('d F Y', strtotime($event_date)));
$event_year   = date('Y', strtotime($event_date));
if(!empty($eventProfile['event_end_date']) && strtotime($eventProfile['event_start_date']) != strtotime($eventProfile['event_end_date'])) {
    $header_date_range = date('d', strtotime($eventProfile['event_start_date'])) . ' - ' . date('d F Y', strtotime($eventProfile['event_end_date']));
} else {
    $header_date_range = $display_date;
}

// Judul Acara
$cleanStroke = trim(str_ireplace(['Gaya', 'GAYA'], '', $raceInfo['stroke'] ?? ''));
$gender_label = (in_array($raceInfo['jenis_kelamin'], ['L','Male','Man'])) ? 'PUTRA' : 'PUTRI';
$judul_tengah = $raceInfo['distance'] . " M GAYA " . strtoupper($cleanStroke) . " - " . ($raceInfo['age_group']??'') . " " . $gender_label . $poolSuffix;
$nomor_acara = "#" . $raceInfo['event_number'];

// 5. AMBIL DATA PESERTA (QUERY DIPERBAIKI: HAPUS JOIN TEAMS)
try {
    // FIX: Menggunakan JOIN ke users (sebagai club) dan swimmers. 
    // Tidak ada JOIN ke tabel 'teams' yg menyebabkan error.
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

// Helper
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
    
    // Logika sederhana: jika tipe event sekolah, utamakan sekolah. Jika tidak, utamakan klub.
    if (stripos($type, 'sekolah') !== false || stripos($type, 'school') !== false) {
        return !empty($school) ? $school : (!empty($club) ? $club : '-');
    } else {
        return !empty($club) ? $club : (!empty($school) ? $school : '-');
    }
}

// Grouping
$heats = [];
foreach ($raw_data as $row) { $heats[$row['heat']][$row['lane']] = $row; }

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=Courier+Prime:wght@400;700&display=swap');
    
    /* Layar Normal */
    .paper-sheet {
        width: 210mm; min-height: 297mm; background: white; margin: 0 auto;
        padding: 5mm 10mm; color: #000; position: relative; 
        font-family: 'Roboto Condensed', sans-serif;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    
    /* Header Report */
    .page-header {
        padding: 5px 0 10px 0; border-bottom: 3px double #000; margin-bottom: 10px;
        display: flex; justify-content: space-between; align-items: center;
    }
    .logo-box { width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; }
    .logo-box img { max-height: 100%; max-width: 100%; object-fit: contain; }
    
    .event-header-grid {
        display: grid; grid-template-columns: 80px 1fr 80px; align-items: center;
        border-bottom: 2px solid #000; margin-bottom: 15px; padding-bottom: 5px;
    }
    .event-num-box { text-align: left; } .event-number { font-size: 14pt; font-weight: 900; line-height: 1; }
    .event-date { font-size: 8pt; font-weight: bold; color: #444; }
    .event-title-box { text-align: center; } .event-title { font-size: 11pt; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .event-round-box { text-align: right; font-size: 9pt; font-weight: bold; background: #eee; padding: 2px 8px; border-radius: 4px; }

    /* Tabel Heat */
    .heat-wrapper { margin-bottom: 15px; break-inside: avoid; }
    .heat-header { text-align: right; font-weight: bold; font-size: 9pt; border-bottom: 1px solid #000; margin-bottom: 2px; }

    .heat-table { width: 100%; border-collapse: collapse; font-size: 9pt; table-layout: fixed; }
    .heat-table th { background: #f0f0f0; border-bottom: 1px solid #000; border-top: 1px solid #000; padding: 4px; font-weight: bold; text-transform: uppercase; }
    .heat-table td { border-bottom: 1px solid #ccc; padding: 4px; vertical-align: middle; white-space: nowrap; }

    /* Input Fields */
    .input-time {
        width: 100%; border: 1px solid #ccc; background: #f9f9f9; padding: 4px;
        font-family: 'Courier Prime', monospace; font-weight: bold; text-align: right; font-size: 11pt; color: blue;
        outline: none; transition: all 0.2s; border-radius: 4px;
    }
    .input-time:focus { border-color: blue; background: #fff; box-shadow: 0 0 5px rgba(0,0,255,0.2); }

    .input-status {
        width: 100%; border: 1px solid #ccc; background: #fff; padding: 4px;
        font-size: 8pt; font-weight: bold; text-align: center; border-radius: 4px;
        outline: none; cursor: pointer;
    }
    
    .col-center { text-align: center; } .col-left { text-align: left; } .col-right { text-align: right; }

    /* PENTING: SETTING PRINT */
    @media print {
        @page { size: A4; margin: 0; }
        body { background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        
        /* Sembunyikan Elemen UI Admin */
        nav, aside, .no-print, .btn-action, .alert-box { display: none !important; }
        
        /* Reset Layout Kertas */
        .p-4, .sm\:ml-64, .pt-24 { padding: 0 !important; margin: 0 !important; }
        .min-h-screen { min-height: auto !important; }
        
        .paper-sheet {
            width: 100%; margin: 0; box-shadow: none; padding: 10mm;
        }

        /* Styling Input Saat Print (Agar terlihat bersih) */
        .input-time {
            border: none; background: transparent; text-align: right; color: black; padding: 0;
            font-size: 10pt;
        }
        .input-status {
            border: none; background: transparent; appearance: none; -webkit-appearance: none;
            text-align: center; color: black; font-weight: bold; padding: 0;
        }
        
        /* Pastikan background baris & header tercetak */
        .heat-table th { background-color: #f0f0f0 !important; }
        .event-round-box { background-color: #eee !important; }
    }
</style>

<div class="p-4 sm:ml-64 pt-24 min-h-screen bg-slate-100 text-slate-900 font-sans">

    <?php if(isset($msg_success)): ?>
        <div class="alert-box max-w-[210mm] mx-auto mb-4 bg-emerald-100 border border-emerald-400 text-emerald-800 px-4 py-3 rounded-lg flex items-center gap-2 shadow-sm sticky top-20 z-50">
            <span>✅</span> <strong><?= $msg_success ?></strong>
        </div>
    <?php endif; ?>

    <div class="no-print max-w-[210mm] mx-auto mb-6 flex flex-col md:flex-row justify-between items-center bg-white p-4 rounded-xl border border-slate-200 shadow-sm sticky top-20 z-40 gap-4">
        <div>
            <h2 class="text-lg font-black text-slate-800 italic">INPUT HASIL LOMBA</h2>
            <p class="text-xs text-slate-500 font-bold uppercase">Masukkan waktu, simpan, lalu cetak hasilnya.</p>
        </div>
        <div class="flex gap-3 flex-wrap justify-center">
            <a href="index.php" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg font-bold text-xs uppercase hover:bg-slate-200 transition">
                Kembali
            </a>

            <button onclick="window.print()" class="px-4 py-2 bg-orange-500 text-white rounded-lg font-bold text-xs uppercase hover:bg-orange-600 transition flex items-center gap-2 shadow-md">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Cetak (PDF)
            </button>
            
            <button type="submit" form="formResult" class="px-6 py-2 bg-blue-600 text-white rounded-lg font-bold text-xs uppercase hover:bg-blue-700 shadow-lg shadow-blue-200 transition flex items-center gap-2">
                <span>💾</span> SIMPAN HASIL
            </button>
        </div>
    </div>

    <form id="formResult" method="POST">
        <div class="paper-sheet">

            <div class="page-header">
                <div class="logo-box">
                    <?php if($logo_left): ?><img src="<?= $logo_left ?>" alt="Logo Left"><?php endif; ?>
                </div>
                <div class="text-center flex-1 px-4">
                    <h1 class="text-xl font-black uppercase leading-tight tracking-wide"><?= htmlspecialchars($header_title) ?></h1>
                    <?php if($venue_name): ?>
                        <p class="text-[9pt] font-bold uppercase text-gray-800 mt-1"><?= htmlspecialchars($venue_name) ?></p>
                    <?php endif; ?>
                    <p class="text-[8pt] font-bold uppercase text-gray-500 mt-1"><?= htmlspecialchars($header_date_range) ?></p>
                    
                    <div class="inline-block border-2 border-black px-6 py-1 mt-2">
                        <p class="text-xl font-black uppercase tracking-[0.2em] leading-none">HASIL LOMBA</p>
                    </div>
                </div>
                <div class="logo-box">
                    <?php if($logo_right): ?><img src="<?= $logo_right ?>" alt="Logo Right"><?php endif; ?>
                </div>
            </div>

            <div class="event-header-grid">
                <div class="event-num-box">
                    <div class="event-number"><?= $nomor_acara ?></div>
                    <div class="event-date"><?= strtoupper($display_date) ?></div>
                </div>
                <div class="event-title-box">
                    <div class="event-title"><?= $judul_tengah ?></div>
                </div>
                <div class="event-round-box">FINAL RESULT</div>
            </div>

            <?php if(empty($heats)): ?>
                <div class="text-center py-12 border-y border-dashed border-gray-400 mt-10"><p class="italic">Belum ada peserta.</p></div>
            <?php else: ?>

                <?php foreach($heats as $heatNo => $lanesData): ?>
                <div class="heat-wrapper">
                    
                    <div class="heat-header">SERI <?= str_pad($heatNo, 2, '0', STR_PAD_LEFT) ?></div>

                    <table class="heat-table">
                        <colgroup>
                            <col style="width: 5%;">  <col style="width: 30%;"> <col style="width: 10%;"> <col style="width: 25%;"> <col style="width: 20%;"> <col style="width: 10%;"> 
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="col-center">LN</th>
                                <th class="col-left">NAMA ATLET</th>
                                <th class="col-center">LHR</th>
                                <th class="col-center">TIM / SEKOLAH</th>
                                <th class="col-right">WAKTU</th>
                                <th class="col-center">KET</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            for($ln = 1; $ln <= $total_lintasan; $ln++): 
                                $s = isset($lanesData[$ln]) ? $lanesData[$ln] : null;
                            ?>
                            <tr>
                                <td class="col-center font-bold font-mono"><?= $ln ?></td>

                                <?php if($s): ?>
                                    <td class="col-left font-bold text-black">
                                        <?= shortenName($s['nama_atlet']) ?>
                                    </td>
                                    
                                    <td class="col-center font-mono text-gray-700">
                                        <?= formatLahir($s['tanggal_lahir'], $event_year) ?>
                                    </td>
                                    
                                    <td class="col-center text-gray-800">
                                        <?= shortenName(getTeamName($s, $participationType)) ?>
                                    </td>
                                    
                                    <td class="col-right">
                                        <input type="text" 
                                               name="entries[<?= $s['id'] ?>][time]" 
                                               value="<?= htmlspecialchars($s['final_time'] ?? '') ?>" 
                                               class="input-time" 
                                               placeholder=""
                                               <?= ($s['is_dq']??0) == 1 ? 'disabled style="background:#eee;color:#ccc;"' : '' ?>
                                               id="time_<?= $s['id'] ?>"
                                               autocomplete="off">
                                    </td>
                                    
                                    <td class="col-center">
                                        <select name="entries[<?= $s['id'] ?>][status]" 
                                                class="input-status" 
                                                onchange="toggleTimeInput(this, '<?= $s['id'] ?>')">
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

            <?php endif; ?>

        </div>
    </form>
    <div class="h-24"></div> 
</div>

<script>
// Logic: Jika pilih DQ/NF/NS, matikan input waktu
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
</script>