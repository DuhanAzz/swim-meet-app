<?php
// FILE: src/master/manage_records.php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

// === 1. PROTEKSI AKSES AKUN MASTER / SUPERADMIN ===
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../public/login.php"); exit;
}

// === HELPER: KONVERSI STRING WAKTU KE MILIDETIK (ms) ===
function timeStringToMs($timeStr) {
    $timeStr = trim($timeStr);
    if (empty($timeStr) || in_array(strtoupper($timeStr), ['NT', '99.99.99', 'DQ'])) return 99999999;
    
    $parts = explode(':', $timeStr);
    if (count($parts) == 2) {
        $minutes = (int)$parts[0];
        $secondsPart = $parts[1];
    } else {
        $minutes = 0;
        $secondsPart = $parts[0];
    }
    
    $secParts = explode('.', $secondsPart);
    $seconds = (int)$secParts[0];
    $hundredths = isset($secParts[1]) ? (int)str_pad($secParts[1], 2, '0', STR_PAD_RIGHT) : 0;
    
    if (strlen($secParts[1] ?? '') > 2) {
        $hundredths = (int)substr($secParts[1], 0, 2);
    }

    return ($minutes * 60 * 1000) + ($seconds * 1000) + ($hundredths * 10);
}

$msg = '';
$tab = $_GET['tab'] ?? 'rekornas';

// === 2. PROSES CRUD MANUAL (ADD / EDIT / DELETE) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'save_manual') {
        $id            = $_POST['id'] ?? null;
        $record_type   = $_POST['record_type'];
        $record_name   = ($record_type === 'rekornas') ? 'REKOR NASIONAL' : strtoupper($_POST['record_name']);
        $distance      = (int)$_POST['distance'];
        $stroke        = $_POST['stroke'];
        $jenis_kelamin = $_POST['jenis_kelamin'];
        $age_group     = strtoupper($_POST['age_group']);
        $holder_name   = strtoupper($_POST['holder_name']);
        $location      = strtoupper($_POST['location'] ?? '');
        $record_year   = $_POST['record_year'] ?? '';
        $record_time   = trim($_POST['record_time']);
        $record_time_ms = timeStringToMs($record_time);

        if ($id) {
            $sql = "UPDATE master_records SET record_type=?, record_name=?, distance=?, stroke=?, jenis_kelamin=?, age_group=?, holder_name=?, location=?, record_year=?, record_time=?, record_time_ms=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$record_type, $record_name, $distance, $stroke, $jenis_kelamin, $age_group, $holder_name, $location, $record_year, $record_time, $record_time_ms, $id]);
            $msg = "<div class='p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-xl'>Data rekor berhasil diperbarui!</div>";
        } else {
            $sql = "INSERT INTO master_records (record_type, record_name, distance, stroke, jenis_kelamin, age_group, holder_name, location, record_year, record_time, record_time_ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$record_type, $record_name, $distance, $stroke, $jenis_kelamin, $age_group, $holder_name, $location, $record_year, $record_time, $record_time_ms]);
            $msg = "<div class='p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-xl'>Data rekor baru berhasil ditambahkan!</div>";
        }
    }
    
    if ($action === 'execute_import') {
        $selected_items = $_POST['import_items'] ?? [];
        $target_rec_name = strtoupper($_POST['target_record_name']);
        $count = 0;

        foreach ($selected_items as $index) {
            $distance   = (int)$_POST['data'][$index]['distance'];
            $stroke     = $_POST['data'][$index]['stroke'];
            $jk         = $_POST['data'][$index]['jenis_kelamin'];
            $age_group  = $_POST['data'][$index]['age_group'];
            $holder     = $_POST['data'][$index]['holder_name'];
            $waktu      = $_POST['data'][$index]['record_time'];
            $waktu_ms   = (int)$_POST['data'][$index]['record_time_ms'];
            $lokasi     = $_POST['data'][$index]['location'];
            $tahun      = $_POST['data'][$index]['record_year'];

            $checkSql = "SELECT id FROM master_records WHERE record_type='rekor_event' AND record_name=? AND distance=? AND stroke=? AND jenis_kelamin=? AND age_group=?";
            $chkStmt = $pdo->prepare($checkSql);
            $chkStmt->execute([$target_rec_name, $distance, $stroke, $jk, $age_group]);
            $existingId = $chkStmt->fetchColumn();

            if ($existingId) {
                $upSql = "UPDATE master_records SET holder_name=?, record_time=?, record_time_ms=?, location=?, record_year=? WHERE id=?";
                $pdo->prepare($upSql)->execute([$holder, $waktu, $waktu_ms, $lokasi, $tahun, $existingId]);
            } else {
                $inSql = "INSERT INTO master_records (record_type, record_name, distance, stroke, jenis_kelamin, age_group, holder_name, location, record_year, record_time, record_time_ms) VALUES ('rekor_event', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($inSql)->execute([$target_rec_name, $distance, $stroke, $jk, $age_group, $holder, $lokasi, $tahun, $waktu, $waktu_ms]);
            }
            $count++;
        }
        $msg = "<div class='p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-xl'>Berhasil mengimpor sebanyak <strong>$count</strong> data rekor event!</div>";
        $tab = 'rekor_event';
    }
}

// PROSES DELETE VIA GET
if (isset($_GET['delete_id'])) {
    $delStmt = $pdo->prepare("DELETE FROM master_records WHERE id = ?");
    $delStmt->execute([$_GET['delete_id']]);
    $msg = "<div class='p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-xl'>Data rekor berhasil dihapus.</div>";
}

// === 3. AMBIL DATA DARI DATABASE ===
$records = $pdo->prepare("SELECT * FROM master_records WHERE record_type = ? ORDER BY distance ASC, stroke ASC");
$records->execute([$tab]);
$listRecords = $records->fetchAll(PDO::FETCH_ASSOC);

$eventsList = $pdo->query("SELECT id, event_name, event_location, YEAR(event_date_start) as ev_year FROM events ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// === 4. PROSES LOGIKA PREVIEW SMART IMPORT ===
$showPreviewModal = false;
$previewData = [];
$targetRecordNameInput = '';

if (isset($_POST['action']) && $_POST['action'] === 'preview_import') {
    $source_event_id = $_POST['source_event_id'];
    $targetRecordNameInput = strtoupper(trim($_POST['target_record_name']));
    
    if (empty($source_event_id) || empty($targetRecordNameInput)) {
        $msg = "<div class='p-4 mb-4 text-sm text-amber-700 bg-amber-100 rounded-xl'>Gagal: Event Sumber dan Nama Kategori Rekor wajib diisi!</div>";
    } else {
        $sqlRank1 = "SELECT 
                        en.distance, en.stroke, en.jenis_kelamin, en.age_group,
                        s.nama_atlet, es.time_final, es.time_final_ms,
                        e.event_location, YEAR(e.event_date_start) as event_year
                     FROM event_seeding es
                     JOIN event_entries ee ON es.entry_id = ee.id
                     JOIN event_numbers en ON ee.category_id = en.id
                     JOIN events e ON en.event_id = e.id
                     JOIN swimmers s ON ee.swimmer_id = s.id
                     WHERE en.event_id = ? 
                       AND es.rank_final = 1 
                       AND es.is_dq_final = 0 
                       AND es.time_final IS NOT NULL 
                       AND es.time_final NOT IN ('', 'NT', '99.99.99', 'DQ')
                     ORDER BY en.distance ASC, en.stroke ASC";
        $stmtP1 = $pdo->prepare($sqlRank1);
        $stmtP1->execute([$source_event_id]);
        $athletesP1 = $stmtP1->fetchAll(PDO::FETCH_ASSOC);

        if (empty($athletesP1)) {
            $msg = "<div class='p-4 mb-4 text-sm text-amber-700 bg-amber-100 rounded-xl'>Tidak ditemukan data pemenang Peringkat 1 dengan catatan waktu valid pada event tersebut.</div>";
        } else {
            $showPreviewModal = true;
            foreach ($athletesP1 as $row) {
                $compSql = "SELECT holder_name, record_time, record_time_ms, record_year FROM master_records 
                            WHERE record_type='rekor_event' AND record_name=? AND distance=? AND stroke=? AND jenis_kelamin=? AND age_group=? LIMIT 1";
                $cStmt = $pdo->prepare($compSql);
                $cStmt->execute([$targetRecordNameInput, $row['distance'], $row['stroke'], $row['jenis_kelamin'], $row['age_group']]);
                $oldRec = $cStmt->fetch(PDO::FETCH_ASSOC);

                $atlet_ms = ($row['time_final_ms'] > 0) ? $row['time_final_ms'] : timeStringToMs($row['time_final']);
                $status = 'baru'; 
                $should_check = true;

                if ($oldRec) {
                    if ($atlet_ms < $oldRec['record_time_ms']) {
                        $status = 'pecah';
                        $should_check = true;
                    } else {
                        $status = 'lambat';
                        $should_check = false;
                    }
                }

                $previewData[] = [
                    'distance'       => $row['distance'],
                    'stroke'         => $row['stroke'],
                    'jenis_kelamin'  => $row['jenis_kelamin'],
                    'age_group'      => $row['age_group'],
                    'holder_name'    => $row['nama_atlet'],
                    'record_time'    => $row['time_final'],
                    'record_time_ms' => $atlet_ms,
                    'location'       => $row['event_location'],
                    'record_year'    => $row['event_year'],
                    'old_rec'        => $oldRec,
                    'status'         => $status,
                    'should_check'   => $should_check
                ];
            }
        }
    }
}

// === 5. INCLUDE LAYOUT TOPBAR & SIDEBAR ===
include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<!-- WRAPPER KONTEN UTAMA SESUAI DASHBOARD -->
<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="max-w-7xl mx-auto px-4 py-4">
        
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
        <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter">KELOLA DATA MASTER RECORD</h1>
        <p class="text-slate-500 text-sm mt-1">Manajemen basis data Rekor Nasional & Rekor Kejuaraan seluruh kategori lomba.</p>
    </div>
    <div class="flex flex-wrap gap-3">
        <!-- TAMBAHKAN TOMBOL INI -->
        <a href="inject_rekornas.php" class="px-5 py-3 bg-red-600 text-white font-bold text-xs rounded-xl uppercase tracking-wider shadow-lg shadow-red-100 hover:bg-red-700 transition">
            ⚡ Injeksi Rekornas
        </a>
        <!-- =================== -->

        <button onclick="openManualModal()" class="px-5 py-3 bg-emerald-600 text-white font-bold text-xs rounded-xl uppercase tracking-wider shadow-lg shadow-emerald-100 hover:bg-emerald-700 transition">
            ➕ Tambah Manual
        </button>
        <button onclick="openImportModal()" class="px-5 py-3 bg-blue-600 text-white font-bold text-xs rounded-xl uppercase tracking-wider shadow-lg shadow-blue-100 hover:bg-blue-700 transition">
            🔄 Import Peringkat 1
        </button>
    </div>
</div>

        <?= $msg ?>

        <!-- NAVIGATION TABS -->
        <div class="flex border-b border-slate-200 mb-6 bg-white rounded-xl p-1.5 shadow-sm">
            <a href="?tab=rekornas" class="flex-1 text-center py-3 font-bold text-sm rounded-lg transition <?= $tab === 'rekornas' ? 'bg-slate-900 text-white shadow' : 'text-slate-500 hover:text-slate-900' ?>">
                🇮🇩 DATA REKORNAS (REKOR NASIONAL)
            </a>
            <a href="?tab=rekor_event" class="flex-1 text-center py-3 font-bold text-sm rounded-lg transition <?= $tab === 'rekor_event' ? 'bg-slate-900 text-white shadow' : 'text-slate-500 hover:text-slate-900' ?>">
                🏆 DATA REKOR EVENT (MEET RECORDS)
            </a>
        </div>

        <!-- MAIN DATA TABLE -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h2 class="font-bold text-slate-900 text-base uppercase">
                    Daftar <?= $tab === 'rekornas' ? 'Rekor Nasional' : 'Rekor Kejuaraan / Event' ?> (<?= count($listRecords) ?> Data)
                </h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-slate-600 font-bold text-xs tracking-wider uppercase border-b border-slate-200">
                            <?php if($tab === 'rekor_event'): ?><th class="p-4">Nama Rekor/Kejuaraan</th><?php endif; ?>
                            <th class="p-4">Nomor Acara</th>
                            <th class="p-4">JK</th>
                            <th class="p-4">Kelompok Umur</th>
                            <th class="p-4">Nama Pemegang</th>
                            <th class="p-4 text-center">Waktu</th>
                            <th class="p-4">Lokasi & Tahun</th>
                            <th class="p-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm font-medium">
                        <?php if(empty($listRecords)): ?>
                            <tr>
                                <td colspan="8" class="p-12 text-center text-slate-400 font-medium italic">Belum ada data rekor yang tersimpan pada kategori ini.</td>
                            </tr>
                        <?php else: foreach($listRecords as $r): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <?php if($tab === 'rekor_event'): ?>
                                    <td class="p-4 font-black text-blue-600 text-xs"><?= htmlspecialchars($r['record_name']) ?></td>
                                <?php endif; ?>
                                <td class="p-4 text-slate-900 font-bold"><?= $r['distance'] ?>M <?= htmlspecialchars($r['stroke']) ?></td>
                                <td class="p-4"><span class="px-2 py-1 text-xs font-bold rounded-md <?= $r['jenis_kelamin']=='L'?'bg-sky-100 text-sky-700':'bg-rose-100 text-rose-700' ?>"><?= $r['jenis_kelamin'] ?></span></td>
                                <td class="p-4 text-slate-700 font-semibold"><?= htmlspecialchars($r['age_group']) ?></td>
                                <td class="p-4 text-slate-900 font-bold uppercase tracking-wide"><?= htmlspecialchars($r['holder_name']) ?></td>
                                <td class="p-4 text-center font-mono font-black text-emerald-600 text-base"><?= htmlspecialchars($r['record_time']) ?></td>
                                <td class="p-4 text-slate-500 text-xs font-semibold"><?= htmlspecialchars($r['location'] ?: '-') ?> (<?= $r['record_year'] ?: '-' ?>)</td>
                                <td class="p-4 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button onclick='openEditModal(<?= json_encode($r) ?>)' class="px-3 py-1.5 bg-amber-50 text-amber-700 hover:bg-amber-100 rounded-lg text-xs font-bold transition">Edit</button>
                                        <a href="?tab=<?= $tab ?>&delete_id=<?= $r['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus data rekor ini?')" class="px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg text-xs font-bold transition">Hapus</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ================= MODAL: TAMBAH / EDIT MANUAL ================= -->
    <div id="manualModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="bg-slate-900 p-6 text-white flex justify-between items-center">
                <h3 id="modalTitle" class="font-black text-lg uppercase tracking-wider">TAMBAH DATA REKOR MANUAL</h3>
                <button onclick="closeManualModal()" class="text-slate-400 hover:text-white text-xl font-bold">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="save_manual">
                <input type="hidden" name="id" id="form_id">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tipe Rekor</label>
                        <select name="record_type" id="form_record_type" onchange="toggleFormRecordName(this.value)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-semibold text-sm focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="rekornas" <?= $tab==='rekornas'?'selected':'' ?>>Rekor Nasional (REKORNAS)</option>
                            <option value="rekor_event" <?= $tab==='rekor_event'?'selected':'' ?>>Rekor Kejuaraan / Event</option>
                        </select>
                    </div>
                    <div id="wrapper_record_name" class="<?= $tab==='rekornas'?'hidden':'' ?>">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nama Rekor / Kejuaraan</label>
                        <input type="text" name="record_name" id="form_record_name" placeholder="Contoh: REKOR JATIM OPEN" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-semibold text-sm focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none uppercase">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Jarak (Meter)</label>
                        <select name="distance" id="form_distance" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-semibold text-sm focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none">
                            <?php foreach([50, 100, 200, 400, 800, 1500] as $m): ?>
                                <option value="<?= $m ?>"><?= $m ?> Meter</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Gaya Lomba</label>
                        <select name="stroke" id="form_stroke" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-semibold text-sm focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="Gaya Bebas">Gaya Bebas</option>
                            <option value="Gaya Dada">Gaya Dada</option>
                            <option value="Gaya Punggung">Gaya Punggung</option>
                            <option value="Gaya Kupu-kupu">Gaya Kupu-kupu</option>
                            <option value="Gaya Ganti Perorangan">Gaya Ganti Perorangan</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Jenis Kelamin</label>
                        <select name="jenis_kelamin" id="form_jenis_kelamin" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-semibold text-sm focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="L">Laki-laki (L)</option>
                            <option value="P">Perempuan (P)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Kelompok Umur (KU)</label>
                        <input type="text" name="age_group" id="form_age_group" placeholder="Contoh: KU 1, SENIOR" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-semibold text-sm focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none uppercase">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nama Pemegang Rekor</label>
                    <input type="text" name="holder_name" id="form_holder_name" required placeholder="NAMA LENGKAP ATLET" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-semibold text-sm focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none uppercase">
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Waktu Rekor</label>
                        <input type="text" name="record_time" id="form_record_time" required placeholder="Format: MM:SS.hh (Misal: 00:24.51)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-mono font-bold text-sm text-emerald-600 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tahun</label>
                        <input type="number" name="record_year" id="form_record_year" placeholder="2025" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-semibold text-sm focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Lokasi Pemecahan Rekor</label>
                    <input type="text" name="location" id="form_location" placeholder="Contoh: KOLAM RENANG GAJAYANA" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-semibold text-sm focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none uppercase">
                </div>

                <div class="pt-4 flex gap-3">
                    <button type="button" onclick="closeManualModal()" class="flex-1 py-3 bg-slate-100 text-slate-500 font-bold text-xs rounded-xl uppercase hover:bg-slate-200 transition">Batal</button>
                    <button type="submit" class="flex-1 py-3 bg-slate-900 text-white font-bold text-xs rounded-xl uppercase hover:bg-slate-800 transition">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= MODAL: PILIH EVENT UNTUK IMPORT ================= -->
    <div id="importModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
            <div class="bg-blue-900 p-6 text-white flex justify-between items-center">
                <h3 class="font-black text-lg uppercase tracking-wider">IMPORT REKOR EVENT DARI JUARA 1</h3>
                <button onclick="closeImportModal()" class="text-slate-300 hover:text-white text-xl font-bold">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="preview_import">
                
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">1. Pilih Event / Kejuaraan Sumber</label>
                    <select name="source_event_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 font-semibold text-sm focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="">-- Pilih Event yang Sudah Selesai --</option>
                        <?php foreach($eventsList as $ev): ?>
                            <option value="<?= $ev['id'] ?>"><?= htmlspecialchars($ev['event_name']) ?> (<?= $ev['ev_year'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-slate-400 text-[11px] mt-1">Sistem akan memindai seluruh atlet peraih medali EMAS (Rank 1) pada event ini.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">2. Simpan sebagai Kategori Rekor Apa?</label>
                    <input type="text" name="target_record_name" required placeholder="Contoh: REKOR JATIM OPEN, REKOR SPRINT SERIES" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 font-semibold text-sm focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none uppercase">
                    <p class="text-slate-400 text-[11px] mt-1">Gunakan nama kategori yang konsisten untuk kejuaraan series/tahunan agar catatan waktu saling membandingkan.</p>
                </div>

                <div class="pt-4 flex gap-3">
                    <button type="button" onclick="closeImportModal()" class="flex-1 py-3 bg-slate-100 text-slate-500 font-bold text-xs rounded-xl uppercase hover:bg-slate-200 transition">Batal</button>
                    <button type="submit" class="flex-1 py-3 bg-blue-600 text-white font-bold text-xs rounded-xl uppercase hover:bg-blue-700 transition">Mulai Cek Waktu & Rekor</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= WIZARD: LAYAR SMART PREVIEW CONFIRMATION ================= -->
    <?php if($showPreviewModal): ?>
    <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-md z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[85vh] flex flex-col overflow-hidden animate-in zoom-in duration-150">
            
            <div class="bg-slate-900 p-6 text-white flex justify-between items-center shrink-0">
                <div>
                    <h3 class="font-black text-lg uppercase tracking-wider">SMART PREVIEW PERBANDINGAN REKOR</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Target Kategori Database: <strong class="text-amber-400"><?= $targetRecordNameInput ?></strong></p>
                </div>
                <button onclick="window.location.href='?tab=rekor_event'" class="text-slate-400 hover:text-white text-2xl">&times;</button>
            </div>

            <form method="POST" class="flex flex-col flex-1 overflow-hidden">
                <input type="hidden" name="action" value="execute_import">
                <input type="hidden" name="target_record_name" value="<?= htmlspecialchars($targetRecordNameInput) ?>">

                <!-- Konten Tabel Perbandingan -->
                <div class="p-6 overflow-y-auto flex-1 bg-slate-50">
                    <p class="text-slate-600 text-xs mb-4">Berikut analisa perbandingan atlet Peringkat 1 dengan rekor yang tersimpan saat ini. Centang otomatis diberikan jika atlet **memecahkan rekor** atau **nomor lomba belum memiliki data rekor**.</p>
                    
                    <table class="w-full text-left border-collapse bg-white rounded-xl shadow-sm overflow-hidden">
                        <thead>
                            <tr class="bg-slate-800 text-slate-300 font-bold text-[11px] uppercase tracking-wider">
                                <th class="p-3 text-center w-12"><input type="checkbox" id="checkAllImport" checked class="w-4 h-4 text-blue-600"></th>
                                <th class="p-3">Nomor Acara</th>
                                <th class="p-3">KU / JK</th>
                                <th class="p-3 bg-amber-900/20 text-amber-900">Rekor Saat Ini (Database)</th>
                                <th class="p-3 bg-emerald-900/20 text-emerald-900">Hasil Juara 1 (Event Baru)</th>
                                <th class="p-3 text-center">Status Analisa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-medium">
                            <?php foreach($previewData as $i => $d): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="p-3 text-center">
                                        <input type="hidden" name="data[<?= $i ?>][distance]" value="<?= $d['distance'] ?>">
                                        <input type="hidden" name="data[<?= $i ?>][stroke]" value="<?= htmlspecialchars($d['stroke']) ?>">
                                        <input type="hidden" name="data[<?= $i ?>][jenis_kelamin]" value="<?= $d['jenis_kelamin'] ?>">
                                        <input type="hidden" name="data[<?= $i ?>][age_group]" value="<?= htmlspecialchars($d['age_group']) ?>">
                                        <input type="hidden" name="data[<?= $i ?>][holder_name]" value="<?= htmlspecialchars($d['holder_name']) ?>">
                                        <input type="hidden" name="data[<?= $i ?>][record_time]" value="<?= $d['record_time'] ?>">
                                        <input type="hidden" name="data[<?= $i ?>][record_time_ms]" value="<?= $d['record_time_ms'] ?>">
                                        <input type="hidden" name="data[<?= $i ?>][location]" value="<?= htmlspecialchars($d['location']) ?>">
                                        <input type="hidden" name="data[<?= $i ?>][record_year]" value="<?= $d['record_year'] ?>">

                                        <input type="checkbox" name="import_items[]" value="<?= $i ?>" <?= $d['should_check'] ? 'checked' : '' ?> class="row-checkbox w-4 h-4 text-blue-600">
                                    </td>
                                    <td class="p-3 font-bold text-slate-900"><?= $d['distance'] ?>M <?= $d['stroke'] ?></td>
                                    <td class="p-3"><span class="px-2 py-0.5 font-bold rounded <?= $d['jenis_kelamin']=='L'?'bg-sky-50 text-sky-700':'bg-rose-50 text-rose-700' ?>"><?= $d['jenis_kelamin'] ?></span> / <?= $d['age_group'] ?></td>
                                    
                                    <td class="p-3 bg-amber-50/50">
                                        <?php if($d['old_rec']): ?>
                                            <div class="font-bold text-slate-900"><?= $d['old_rec']['record_time'] ?></div>
                                            <div class="text-[10px] text-slate-500 uppercase"><?= htmlspecialchars($d['old_rec']['holder_name']) ?> (<?= $d['old_rec']['record_year'] ?>)</div>
                                        <?php else: ?>
                                            <span class="text-slate-400 italic">Belum ada rekor</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="p-3 bg-emerald-50/50">
                                        <div class="font-black text-emerald-600 text-sm"><?= $d['record_time'] ?></div>
                                        <div class="text-[10px] text-slate-700 uppercase font-bold"><?= htmlspecialchars($d['holder_name']) ?></div>
                                    </td>

                                    <td class="p-3 text-center">
                                        <?php if($d['status'] === 'baru'): ?>
                                            <span class="px-2 py-1 text-[10px] font-black rounded-lg bg-blue-100 text-blue-800 uppercase tracking-wider">🆕 Rekor Baru</span>
                                        <?php elseif($d['status'] === 'pecah'): ?>
                                            <span class="px-2 py-1 text-[10px] font-black rounded-lg bg-green-100 text-green-800 uppercase tracking-wider animate-pulse">🔥 Pecah Rekor!</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 text-[10px] font-bold rounded-lg bg-slate-200 text-slate-500 uppercase tracking-wider">❌ Lebih Lambat</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 bg-slate-100 border-t border-slate-200 flex justify-between items-center shrink-0">
                    <button type="button" onclick="window.location.href='?tab=rekor_event'" class="px-5 py-3 bg-white border border-slate-300 text-slate-600 font-bold text-xs rounded-xl uppercase hover:bg-slate-50">Batal / Keluar</button>
                    <button type="submit" class="px-8 py-3 bg-slate-900 text-white font-bold text-xs rounded-xl uppercase tracking-wider hover:bg-slate-800 shadow-xl">Simpan & Perbarui Rekor Terpilih</button>
                </div>
            </form>

        </div>
    </div>
    <?php endif; ?>

</div>

<!-- JAVASCRIPT DILETAKKAN DI LUAR WRAPPER AGAR TETAP BEKERJA -->
<script>
    const manualModal = document.getElementById('manualModal');
    const importModal = document.getElementById('importModal');

    function openManualModal() {
        document.getElementById('modalTitle').innerText = "TAMBAH DATA REKOR MANUAL";
        document.getElementById('form_id').value = "";
        document.getElementById('form_holder_name').value = "";
        document.getElementById('form_record_time').value = "";
        document.getElementById('form_record_year').value = new Date().getFullYear();
        document.getElementById('form_location').value = "";
        document.getElementById('form_age_group').value = "";
        
        const currentTab = "<?= $tab ?>";
        document.getElementById('form_record_type').value = currentTab;
        toggleFormRecordName(currentTab);

        manualModal.classList.remove('hidden');
    }

    function openEditModal(data) {
        document.getElementById('modalTitle').innerText = "EDIT DATA MASTER RECORD";
        document.getElementById('form_id').value = data.id;
        document.getElementById('form_record_type').value = data.record_type;
        document.getElementById('form_record_name').value = data.record_name;
        document.getElementById('form_distance').value = data.distance;
        document.getElementById('form_stroke').value = data.stroke;
        document.getElementById('form_jenis_kelamin').value = data.jenis_kelamin;
        document.getElementById('form_age_group').value = data.age_group;
        document.getElementById('form_holder_name').value = data.holder_name;
        document.getElementById('form_record_time').value = data.record_time;
        document.getElementById('form_record_year').value = data.record_year;
        document.getElementById('form_location').value = data.location;

        toggleFormRecordName(data.record_type);
        manualModal.classList.remove('hidden');
    }

    function closeManualModal() { manualModal.classList.add('hidden'); }
    function openImportModal() { importModal.classList.remove('hidden'); }
    function closeImportModal() { importModal.classList.add('hidden'); }

    function toggleFormRecordName(type) {
        const wrapper = document.getElementById('wrapper_record_name');
        const input = document.getElementById('form_record_name');
        if (type === 'rekornas') {
            wrapper.classList.add('hidden');
            input.removeAttribute('required');
        } else {
            wrapper.classList.remove('hidden');
            input.setAttribute('required', 'required');
        }
    }

    const checkAll = document.getElementById('checkAllImport');
    if(checkAll) {
        checkAll.addEventListener('change', function() {
            const checkBoxes = document.querySelectorAll('.row-checkbox');
            checkBoxes.forEach(cb => cb.checked = this.checked);
        });
    }
</script>