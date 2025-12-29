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

// --- PROSES PENYIMPANAN DATA (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        $entries = $_POST['entries'] ?? [];

        // 1. Reset Ranking Kategori Ini (Supaya bersih sebelum hitung ulang)
        $pdo->prepare("UPDATE event_entries SET final_rank = NULL WHERE category_id = ?")->execute([$cat_id]);

        // 2. Simpan Data Waktu & Status
        $stmtUpd = $pdo->prepare("UPDATE event_entries SET final_time = ?, is_dq = ?, dq_reason = ? WHERE id = ?");

        foreach ($entries as $id => $data) {
            $time = trim($data['time'] ?? '');
            $status = $data['status']; // Values: '', 'DQ', 'DNF', 'DNS'

            $is_dq = ($status !== '') ? 1 : 0;
            $reason = ($status !== '') ? $status : NULL; // Simpan alasan (DQ/DNF/DNS)
            
            // Jika status tidak sah, waktu dikosongkan/NULL
            if ($is_dq) $time = NULL; 
            if ($time === '') $time = NULL;

            $stmtUpd->execute([$time, $is_dq, $reason, $id]);
        }

        // 3. Hitung Ranking Otomatis (Hanya yang Punya Waktu & Tidak DQ)
        $stmtRank = $pdo->prepare("
            SELECT id, final_time FROM event_entries 
            WHERE category_id = ? AND final_time IS NOT NULL AND is_dq = 0 
            ORDER BY final_time ASC
        ");
        $stmtRank->execute([$cat_id]);
        $validSwimmers = $stmtRank->fetchAll(PDO::FETCH_ASSOC);

        $rank = 1;
        $counter = 1;
        $prevTime = null;
        $stmtSaveRank = $pdo->prepare("UPDATE event_entries SET final_rank = ? WHERE id = ?");

        foreach ($validSwimmers as $s) {
            // Logika Rank Kembar (Tie)
            if ($prevTime !== null && $s['final_time'] != $prevTime) {
                $rank = $counter;
            }
            $stmtSaveRank->execute([$rank, $s['id']]);
            $prevTime = $s['final_time'];
            $counter++;
        }

        $pdo->commit();
        $msg_success = "Data berhasil disimpan & Peringkat diperbarui!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $msg_error = "Gagal menyimpan: " . $e->getMessage();
    }
}

// --- AMBIL DATA DATA UNTUK TAMPILAN ---
// A. Profil Event
$uid = $_SESSION['user_id'];
$stmtProfile = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtProfile->execute([$uid]);
$profile = $stmtProfile->fetch();

// B. Data Header
$header_title    = $profile['nama_lengkap'] ?? 'KEJUARAAN RENANG';
$raw_date        = strtotime($profile['event_start_date']);
$event_year      = date('Y', $raw_date);
$display_date    = date('d F Y', $raw_date);
if(strtotime($profile['event_start_date']) != strtotime($profile['event_end_date'])) {
    $header_date_range = date('d', $raw_date) . ' - ' . date('d F Y', strtotime($profile['event_end_date']));
} else {
    $header_date_range = $display_date;
}
$logo_left  = !empty($profile['logo_left']) ? '../../../public/' . $profile['logo_left'] : null;
$logo_right = !empty($profile['logo_right']) ? '../../../public/' . $profile['logo_right'] : null;

// --- LOGIKA BARU: MENENTUKAN LCM / SCM DARI PROFIL ---
$poolSuffix = ""; 
$pType = "";

if (!empty($profile['pool_type'])) {
    $pType = $profile['pool_type'];
} elseif (!empty($profile['pool_length'])) {
    $pType = $profile['pool_length'];
}

$pType = strtolower(trim($pType));
if ($pType === '50m' || $pType === 'lcm' || $pType === 'long course') {
    $poolSuffix = " - LCM";
} elseif ($pType === '25m' || $pType === 'scm' || $pType === 'short course') {
    $poolSuffix = " - SCM";
}
// -----------------------------------------------------

// C. Info Nomor Lomba
$stmtEvent = $pdo->prepare("SELECT * FROM event_numbers WHERE id = ?");
$stmtEvent->execute([$cat_id]);
$eventData = $stmtEvent->fetch();
$nomor_lomba  = $eventData['event_number'];
$gender_label = ($eventData['jenis_kelamin'] == 'L' || $eventData['jenis_kelamin'] == 'Male') ? 'PUTRA' : 'PUTRI';

// Update Judul: Tambahkan $poolSuffix
$jarak_gaya   = $eventData['distance'] . " M " . strtoupper($eventData['stroke']) . " " . $gender_label . $poolSuffix;

// D. Ambil Data Peserta (Termasuk data hasil yang sudah tersimpan)
$sql = "SELECT ee.*, s.nama_atlet, s.tanggal_lahir, u.nama_lengkap as club_name, s.asal_sekolah
        FROM event_entries ee
        JOIN swimmers s ON ee.swimmer_id = s.id
        LEFT JOIN users u ON ee.user_id = u.id 
        WHERE ee.category_id = ? AND ee.heat IS NOT NULL 
        ORDER BY ee.heat ASC, ee.lane ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$cat_id]);
$raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper Functions
function formatLahir($tgl, $year) {
    if(!$tgl || $tgl == '0000-00-00') return '-';
    $by = date('Y', strtotime($tgl));
    return $by . " (" . ($year - $by) . ")";
}
function shortenName($name) {
    $name = trim(preg_replace('/\s+/', ' ', $name));
    $parts = explode(' ', $name);
    if (count($parts) <= 3) return $name;
    $final = [];
    foreach ($parts as $i => $w) {
        if ($i < 3) $final[] = $w; else $final[] = substr($w, 0, 1) . '.';
    }
    return implode(' ', $final);
}

// Grouping Heat
$heats = [];
foreach ($raw_data as $row) { $heats[$row['heat']][$row['lane']] = $row; }
$total_lintasan = !empty($profile['lane_count']) ? (int)$profile['lane_count'] : 8;

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=Courier+Prime:wght@400;700&display=swap');
    .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
    .font-mono { font-family: 'Courier Prime', monospace; }

    /* LAYOUT KERTAS (Sama seperti view_startlist) */
    .paper-sheet {
        width: 210mm; min-height: 297mm; background: white; margin: 0 auto;
        padding: 10mm; color: #000; position: relative; font-family: 'Roboto Condensed', sans-serif;
    }
    
    /* STYLE IDENTIK DENGAN PRINT FULL BOOK */
    .page-header {
        padding: 10px 0 20px 0; border-bottom: 3px double #000; margin-bottom: 20px;
        display: flex; justify-content: space-between; align-items: center;
    }
    .logo-box { width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; }
    
    .event-header-grid {
        display: grid; grid-template-columns: 100px 1fr 100px; align-items: center;
        border-bottom: 2px solid #000; margin-bottom: 15px; padding-bottom: 5px;
    }
    .event-num-box { text-align: left; } .event-number { font-size: 18pt; font-weight: 900; line-height: 1; }
    .event-date { font-size: 9pt; font-weight: bold; color: #444; margin-top: 2px; text-transform: uppercase;}
    .event-title-box { text-align: center; } .event-title { font-size: 14pt; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
    .event-round-box { text-align: right; font-size: 10pt; font-weight: bold; background: #eee; padding: 2px 8px; border-radius: 4px; }

    .heat-wrapper { margin-bottom: 20px; }
    .heat-header { text-align: right; font-weight: bold; font-size: 10pt; border-bottom: 1px solid #000; margin-bottom: 2px; padding-right: 5px; }

    /* TABEL INPUT */
    .heat-table { width: 100%; border-collapse: collapse; font-size: 9pt; table-layout: fixed; }
    .heat-table th { background: #f0f0f0; border-bottom: 1px solid #000; border-top: 1px solid #000; padding: 5px; font-weight: bold; text-transform: uppercase; font-size: 8pt; vertical-align: middle; }
    .heat-table td { border-bottom: 1px solid #ddd; padding: 4px 5px; vertical-align: middle; white-space: nowrap; }

    /* INPUT STYLING (Supaya menyatu dengan kertas) */
    .input-time {
        width: 100%; border: 1px solid #ccc; background: #fff; padding: 2px 5px;
        font-family: 'Courier Prime', monospace; font-weight: bold; text-align: right; font-size: 10pt;
        outline: none; transition: all 0.2s;
    }
    .input-time:focus { border-color: blue; background: #f0f8ff; }

    .input-status {
        width: 100%; border: 1px solid #ccc; background: #fff; padding: 2px;
        font-size: 8pt; font-weight: bold; text-align: center;
        outline: none; cursor: pointer;
    }
    .input-status option { font-weight: bold; }
    
    /* Highlight Row saat input */
    tr:hover td { background-color: #f9fafb; }

    /* Utilities */
    .col-center { text-align: center; } .col-left { text-align: left; } .col-right { text-align: right; }
</style>

<div class="p-4 sm:ml-64 pt-24 min-h-screen bg-slate-100 text-slate-900 font-sans">

    <?php if(isset($msg_success)): ?>
        <div class="max-w-[210mm] mx-auto mb-4 bg-emerald-100 border border-emerald-400 text-emerald-800 px-4 py-3 rounded-lg flex items-center gap-2 shadow-sm">
            <span>✅</span> <strong><?= $msg_success ?></strong>
        </div>
    <?php endif; ?>

    <div class="max-w-[210mm] mx-auto mb-6 flex justify-between items-center no-print bg-white p-4 rounded-xl border border-slate-200 shadow-sm sticky top-20 z-50">
        <div>
            <h2 class="text-lg font-black text-slate-800 italic">INPUT HASIL LOMBA</h2>
            <p class="text-xs text-slate-500 font-bold uppercase">Masukkan waktu, lalu tekan Simpan.</p>
        </div>
        <div class="flex gap-3">
            <a href="index.php" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg font-bold text-xs uppercase hover:bg-slate-200 transition">
                Kembali
            </a>
            
            <a href="print_result.php?category_id=<?= $cat_id ?>" target="_blank" class="px-5 py-2 bg-slate-800 text-white rounded-lg font-bold text-xs uppercase hover:bg-slate-900 shadow transition flex items-center gap-2">
                <span>🖨️</span> Cetak Hasil
            </a>

            <button type="submit" form="formResult" class="px-6 py-2 bg-blue-600 text-white rounded-lg font-bold text-xs uppercase hover:bg-blue-700 shadow-lg shadow-blue-200 transition flex items-center gap-2">
                <span>💾</span> SIMPAN HASIL
            </button>
        </div>
    </div>

    <form id="formResult" method="POST">
        <div class="paper-sheet">

            <div class="page-header">
                <div class="logo-box">
                    <?php if($logo_left): ?><img src="<?= $logo_left ?>" class="max-h-full max-w-full object-contain"><?php endif; ?>
                </div>
                <div class="text-center flex-1 px-4">
                    <h1 class="text-xl font-black uppercase leading-tight tracking-wide"><?= htmlspecialchars($header_title) ?></h1>
                    <p class="text-sm font-bold uppercase text-gray-600 mt-1"><?= htmlspecialchars($header_date_range) ?></p>
                    <div class="inline-block border-2 border-black px-6 py-1 mt-2">
                        <p class="text-xl font-black uppercase tracking-[0.2em] leading-none">INPUT SCORE SHEET</p>
                    </div>
                </div>
                <div class="logo-box">
                    <?php if($logo_right): ?><img src="<?= $logo_right ?>" class="max-h-full max-w-full object-contain"><?php endif; ?>
                </div>
            </div>

            <div class="event-header-grid">
                <div class="event-num-box">
                    <div class="event-number">#<?= $nomor_lomba ?></div>
                    <div class="event-date"><?= strtoupper($display_date) ?></div>
                </div>
                <div class="event-title-box">
                    <div class="event-title"><?= $jarak_gaya ?></div>
                </div>
                <div class="event-round-box">FINAL</div>
            </div>

            <?php if(empty($heats)): ?>
                <div class="text-center py-12 border-y border-dashed border-gray-400 mt-10"><p class="italic">Belum ada seeding.</p></div>
            <?php else: ?>

                <?php foreach($heats as $heatNo => $lanesData): ?>
                <div class="heat-wrapper">
                    
                    <div class="heat-header">SERI <?= str_pad($heatNo, 2, '0', STR_PAD_LEFT) ?></div>

                    <table class="heat-table">
                        <colgroup>
                            <col style="width: 4%;">  
                            <col style="width: 30%;"> 
                            <col style="width: 13%;"> 
                            <col style="width: 20%;"> 
                            <col style="width: 20%;"> 
                            <col style="width: 13%;"> 
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="col-center">LN</th>
                                <th class="col-left">NAMA ATLET</th>
                                <th class="col-center">LAHIR</th>
                                <th class="col-left">TIM / SEKOLAH</th>
                                <th class="col-right">WAKTU (Input)</th>
                                <th class="col-center">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            for($ln = 1; $ln <= $total_lintasan; $ln++): 
                                $s = isset($lanesData[$ln]) ? $lanesData[$ln] : null;
                                $uniq = $s ? $s['id'] : 'empty_'.$heatNo.'_'.$ln;
                            ?>
                            <tr>
                                <td class="col-center font-bold font-mono"><?= $ln ?></td>

                                <?php if($s): ?>
                                    <td class="col-left font-bold text-black" title="<?= $s['nama_atlet'] ?>">
                                        <?= shortenName($s['nama_atlet']) ?>
                                    </td>
                                    
                                    <td class="col-center text-gray-700 font-mono">
                                        <?= formatLahir($s['tanggal_lahir'], $event_year) ?>
                                    </td>
                                    
                                    <td class="col-left text-gray-800" title="<?= $s['asal_sekolah'] ?>">
                                        <?= !empty($s['asal_sekolah']) ? $s['asal_sekolah'] : $s['club_name'] ?>
                                    </td>
                                    
                                    <td class="col-right">
                                        <input type="text" 
                                               name="entries[<?= $s['id'] ?>][time]" 
                                               value="<?= htmlspecialchars($s['final_time'] ?? '') ?>" 
                                               class="input-time" 
                                               placeholder="00:00.00"
                                               <?= ($s['is_dq']??0) == 1 ? 'disabled style="background:#eee;"' : '' ?>
                                               id="time_<?= $s['id'] ?>">
                                    </td>
                                    
                                    <td class="col-center">
                                        <select name="entries[<?= $s['id'] ?>][status]" 
                                                class="input-status" 
                                                onchange="toggleTimeInput(this, '<?= $s['id'] ?>')">
                                            <option value="" <?= empty($s['dq_reason']) ? 'selected' : '' ?>>- SAH -</option>
                                            <option value="DQ" class="text-red-600" <?= ($s['dq_reason']=='DQ') ? 'selected' : '' ?>>DQ</option>
                                            <option value="DNF" class="text-orange-600" <?= ($s['dq_reason']=='DNF') ? 'selected' : '' ?>>NF</option>
                                            <option value="DNS" class="text-gray-500" <?= ($s['dq_reason']=='DNS') ? 'selected' : '' ?>>NS</option>
                                        </select>
                                    </td>

                                <?php else: ?>
                                    <td colspan="5" class="text-gray-300 italic font-light pl-2">&lt; KOSONG &gt;</td>
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
// Fungsi JS untuk mematikan input waktu jika DQ/NF/NS dipilih
function toggleTimeInput(selectElem, id) {
    const timeInput = document.getElementById('time_' + id);
    if (selectElem.value !== "") {
        timeInput.disabled = true;
        timeInput.style.backgroundColor = "#eee";
        timeInput.value = ""; // Kosongkan waktu jika DQ
    } else {
        timeInput.disabled = false;
        timeInput.style.backgroundColor = "#fff";
    }
}
</script>