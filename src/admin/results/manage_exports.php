<?php
// src/admin/results/manage_exports.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Proteksi Akses
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'master')) {
    header("Location: ../../../public/login.php");
    exit;
}

// 1. Ambil Semua Event Aktif
if ($_SESSION['role'] === 'master') {
    $stmtEvents = $pdo->query("SELECT id, event_name, event_date_start, participation_type FROM events ORDER BY id DESC");
    $events = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);
} else {
    $uid = $_SESSION['user_id'];
    $stmtEvents = $pdo->prepare("SELECT id, event_name, event_date_start, participation_type FROM events WHERE user_id = ? ORDER BY id DESC");
    $stmtEvents->execute([$uid]);
    $events = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);
}

// 2. Jika ada event yang dipilih via GET (untuk reload dropdowns)
$selectedEventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : (isset($events[0]) ? $events[0]['id'] : 0);

// Cari tipe partisipasi untuk menentukan Sekolah vs Klub
$partType = 'club';
foreach($events as $ev) {
    if ($ev['id'] == $selectedEventId) {
        $partType = strtolower($ev['participation_type'] ?? 'club');
        break;
    }
}
$isSchool = (strpos($partType, 'school') !== false || strpos($partType, 'sekolah') !== false);

// 3. Ambil Kelompok Umur berdasarkan Event
$ageGroups = [];
if ($selectedEventId > 0) {
    $stmtAge = $pdo->prepare("SELECT group_name FROM event_age_groups WHERE event_id = ? ORDER BY min_age ASC");
    $stmtAge->execute([$selectedEventId]);
    $ageGroups = $stmtAge->fetchAll(PDO::FETCH_ASSOC);
}

// 4. Ambil Daftar Klub / Sekolah berdasarkan Event
$teams = [];
if ($selectedEventId > 0) {
    if ($isSchool) {
        $stmtTeam = $pdo->prepare("SELECT DISTINCT s.asal_sekolah as team_name FROM event_entries ee JOIN swimmers s ON ee.swimmer_id = s.id WHERE ee.event_id = ? AND s.asal_sekolah != '' ORDER BY s.asal_sekolah ASC");
        $stmtTeam->execute([$selectedEventId]);
        while($r = $stmtTeam->fetch(PDO::FETCH_ASSOC)) { $teams[] = $r['team_name']; }
    } else {
        $stmtTeam = $pdo->prepare("SELECT DISTINCT c.nama_klub as team_name FROM event_entries ee JOIN clubs c ON ee.club_id = c.id WHERE ee.event_id = ? ORDER BY c.nama_klub ASC");
        $stmtTeam->execute([$selectedEventId]);
        while($r = $stmtTeam->fetch(PDO::FETCH_ASSOC)) { $teams[] = $r['team_name']; }
    }
}

// INCLUDE LAYOUT
include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="mb-8">
        <h1 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter">Ekspor Data Kustom</h1>
        <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-1">Unduh Sertifikat Canva & Laporan Resmi</p>
    </div>

    <!-- FORM FILTER -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-8">
        <form method="GET" action="" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                
                <!-- Event -->
                <div>
                    <label class="block text-xs font-black text-slate-700 uppercase tracking-widest mb-2">1. Pilih Event</label>
                    <select name="event_id" id="eventSelect" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm font-medium focus:ring-blue-500 focus:border-blue-500" onchange="document.getElementById('filterForm').submit()">
                        <?php foreach($events as $ev): ?>
                            <option value="<?= $ev['id'] ?>" <?= ($ev['id'] == $selectedEventId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ev['event_name']) ?> (<?= date('Y', strtotime($ev['event_date_start'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- KU -->
                <div>
                    <label class="block text-xs font-black text-slate-700 uppercase tracking-widest mb-2">2. Kelompok Umur</label>
                    <select name="ku" id="kuSelect" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm font-medium focus:ring-blue-500 focus:border-blue-500">
                        <option value="ALL">SEMUA KELOMPOK UMUR</option>
                        <?php foreach($ageGroups as $ag): ?>
                            <option value="<?= htmlspecialchars($ag['group_name']) ?>">KU <?= htmlspecialchars($ag['group_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tim -->
                <div>
                    <label class="block text-xs font-black text-slate-700 uppercase tracking-widest mb-2">3. Tim / Sekolah</label>
                    <select name="team" id="teamSelect" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm font-medium focus:ring-blue-500 focus:border-blue-500">
                        <option value="ALL">SEMUA TIM / SEKOLAH</option>
                        <?php foreach($teams as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Limit / Capaian -->
                <div>
                    <label class="block text-xs font-black text-slate-700 uppercase tracking-widest mb-2">4. Batasan Hasil</label>
                    <select name="limit_hasil" id="limitSelect" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm font-medium focus:ring-blue-500 focus:border-blue-500">
                        <option value="ALL">Semua Peserta (Finished)</option>
                        <option value="TOP3">Hanya Juara 1-3 (Peraih Medali)</option>
                        <option value="DQ">Hanya Pelanggaran / DQ</option>
                    </select>
                </div>

                <!-- Ranking Mode -->
                <div>
                    <label class="block text-xs font-black text-slate-700 uppercase tracking-widest mb-2">5. Mode Perangkingan</label>
                    <div class="flex bg-slate-100 p-1 rounded-xl border border-slate-200">
                        <label class="flex-1 text-center cursor-pointer">
                            <input type="radio" name="rank_mode" value="SPLIT" class="peer hidden" checked id="rankSplit">
                            <div class="text-xs font-black uppercase py-2 rounded-lg peer-checked:bg-white peer-checked:text-blue-600 peer-checked:shadow-sm transition text-slate-500">PISAH / SPLIT KU</div>
                        </label>
                        <label class="flex-1 text-center cursor-pointer">
                            <input type="radio" name="rank_mode" value="OVERALL" class="peer hidden" id="rankOverall">
                            <div class="text-xs font-black uppercase py-2 rounded-lg peer-checked:bg-white peer-checked:text-emerald-600 peer-checked:shadow-sm transition text-slate-500">GABUNGAN / OVERALL</div>
                        </label>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-2">Mempengaruhi hasil akhir khusus nomor Lomba Gabungan.</p>
                </div>
                
                <div class="md:col-span-2 lg:col-span-3 border-t border-slate-100 mt-2 pt-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Komponen Judul (PDF/Excel) -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2.5">A. Komponen Judul Acara (Khusus Laporan)</label>
                        <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-xs font-bold text-slate-600">
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" id="cfg_event_no" class="rounded border-slate-300 text-blue-600 cfg-cb" checked> Nomor Acara</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" id="cfg_date" class="rounded border-slate-300 text-blue-600 cfg-cb" checked> Tanggal & Jam</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" id="cfg_event_name" class="rounded border-slate-300 text-blue-600 cfg-cb" checked> Jarak & Gaya</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" id="cfg_group" class="rounded border-slate-300 text-blue-600 cfg-cb" checked> Kelompok Umur</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" id="cfg_gender" class="rounded border-slate-300 text-blue-600 cfg-cb" checked> Jenis Kelamin</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" id="cfg_pool" class="rounded border-slate-300 text-blue-600 cfg-cb" checked> Tipe Kolam</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" id="cfg_round" class="rounded border-slate-300 text-blue-600 cfg-cb" checked> Babak (FINAL)</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none text-amber-700 font-extrabold"><input type="checkbox" id="cfg_show_records" class="rounded border-amber-300 text-amber-600 cfg-cb" checked> Tampilkan Rekor</label>
                        </div>
                    </div>
                    
                    <!-- Kolom Tabel Atlet -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2.5">B. Kolom Tabel Data</label>
                        <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-xs font-bold text-slate-600">
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" id="col_uid" class="rounded border-slate-300 text-blue-600 cfg-cb" checked> Kolom UID</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" id="col_lahir" class="rounded border-slate-300 text-blue-600 cfg-cb" checked> Tahun Lahir</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" id="col_ku" class="rounded border-slate-300 text-blue-600 cfg-cb" checked> Kolom KU</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" id="col_tim" class="rounded border-slate-300 text-blue-600 cfg-cb" checked> TIM / Sekolah</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" id="col_waktu" class="rounded border-slate-300 text-blue-600 cfg-cb" checked> Waktu Entry</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" id="col_hasil" class="rounded border-slate-300 text-blue-600 cfg-cb" checked> Waktu Final</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" id="col_ket" class="rounded border-slate-300 text-blue-600 cfg-cb" checked> Keterangan DQ</label>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <!-- ACTION CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- CARD 1: CANVA -->
        <div class="bg-gradient-to-br from-indigo-600 to-blue-700 rounded-2xl p-8 text-white shadow-lg relative overflow-hidden group">
            <div class="relative z-10">
                <h3 class="text-2xl font-black mb-2 flex items-center gap-2">🎨 Canva Bulk Create</h3>
                <p class="text-blue-100 text-sm font-medium mb-6">Unduh format CSV khusus (Clean Header) yang dapat langsung di-upload ke Canva untuk mencetak ribuan E-Sertifikat secara otomatis.</p>
                
                <button type="button" onclick="downloadExport('canva')" class="w-full bg-white text-indigo-700 py-3 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-indigo-50 transition shadow-md">
                    📥 UNDUH CSV CANVA
                </button>
            </div>
            <div class="absolute right-[-20px] bottom-[-20px] opacity-10 group-hover:scale-110 transition text-8xl">🖼️</div>
        </div>

        <!-- CARD 2: LAPORAN -->
        <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-8 text-white shadow-lg relative overflow-hidden group border-b-4 border-emerald-500">
            <div class="relative z-10">
                <h3 class="text-2xl font-black mb-2 flex items-center gap-2">📄 Laporan Resmi</h3>
                <p class="text-slate-300 text-sm font-medium mb-6">Cetak Buku Hasil Pertandingan dengan struktur formal. Pilih format output sesuai kebutuhan dokumentasi.</p>
                
                <div class="flex flex-col sm:flex-row gap-3">
                    <select id="reportFormat" class="bg-slate-700 border-slate-600 text-white rounded-xl text-sm font-bold focus:ring-emerald-500">
                        <option value="pdf">PDF (Print Ready)</option>
                        <option value="excel">Microsoft Excel (.xls)</option>
                        <option value="csv">CSV (Spreadsheet)</option>
                    </select>
                    
                    <button type="button" onclick="downloadExport('report')" class="flex-1 bg-emerald-500 text-white py-3 px-4 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-emerald-400 transition shadow-md">
                        📥 CETAK LAPORAN
                    </button>
                </div>
            </div>
            <div class="absolute right-[-20px] bottom-[-20px] opacity-10 group-hover:scale-110 transition text-8xl">📊</div>
        </div>

    </div>

</div>

<script>
function downloadExport(type) {
    const eventId = document.getElementById('eventSelect').value;
    const ku = document.getElementById('kuSelect').value;
    const team = document.getElementById('teamSelect').value;
    const limit = document.getElementById('limitSelect').value;
    const rankMode = document.getElementById('rankSplit').checked ? 'SPLIT' : 'OVERALL';
    
    let baseUrl = type === 'canva' ? 'export_canva.php' : 'export_report.php';
    let params = `?event_id=${eventId}&ku=${encodeURIComponent(ku)}&team=${encodeURIComponent(team)}&limit=${limit}&rank_mode=${rankMode}`;
    
    // Konfigurasi Checkbox Khusus Laporan
    if (type === 'report') {
        const format = document.getElementById('reportFormat').value;
        params += `&format=${format}`;
        
        const cfgs = ['cfg_event_no', 'cfg_date', 'cfg_event_name', 'cfg_group', 'cfg_gender', 'cfg_pool', 'cfg_round', 'cfg_show_records',
                      'col_uid', 'col_lahir', 'col_ku', 'col_tim', 'col_waktu', 'col_hasil', 'col_ket'];
        cfgs.forEach(id => {
            const el = document.getElementById(id);
            if (el && el.checked) params += `&${id}=1`;
        });
    }
    
    // Buka di tab baru agar tidak mengganggu state halaman ini
    window.open(baseUrl + params, '_blank');
}
</script>

</body>
</html>
