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

// === 2. PROSES CRUD MANUAL (ADD / EDIT / DELETE) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'save_manual') {
        $id            = $_POST['id'] ?? null;
        $record_type   = 'rekornas';
        $record_name   = 'REKOR NASIONAL';
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
            $msg = "<div class='p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-xl'>Data rekornas berhasil diperbarui!</div>";
        } else {
            $sql = "INSERT INTO master_records (record_type, record_name, distance, stroke, jenis_kelamin, age_group, holder_name, location, record_year, record_time, record_time_ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$record_type, $record_name, $distance, $stroke, $jenis_kelamin, $age_group, $holder_name, $location, $record_year, $record_time, $record_time_ms]);
            $msg = "<div class='p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-xl'>Data rekornas baru berhasil ditambahkan!</div>";
        }
    }
}

// PROSES DELETE VIA GET
if (isset($_GET['delete_id'])) {
    $delStmt = $pdo->prepare("DELETE FROM master_records WHERE id = ?");
    $delStmt->execute([$_GET['delete_id']]);
    $msg = "<div class='p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-xl'>Data rekornas berhasil dihapus.</div>";
}

// === 3. AMBIL DATA DARI DATABASE ===
$records = $pdo->prepare("SELECT * FROM master_records WHERE record_type = 'rekornas' ORDER BY distance ASC, stroke ASC");
$records->execute();
$listRecords = $records->fetchAll(PDO::FETCH_ASSOC);

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
                <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter">MANAJEMEN REKOR</h1>
                <p class="text-slate-500 text-sm mt-1">Kelola basis data Rekor Nasional & Paket Rekor Acuan Event.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="inject_rekornas.php" class="px-5 py-3 bg-red-600 text-white font-bold text-xs rounded-xl uppercase tracking-wider shadow-lg shadow-red-100 hover:bg-red-700 transition">
                    ⚡ Injeksi Rekornas
                </a>
                <button onclick="openManualModal()" class="px-5 py-3 bg-emerald-600 text-white font-bold text-xs rounded-xl uppercase tracking-wider shadow-lg shadow-emerald-100 hover:bg-emerald-700 transition">
                    ➕ Tambah Manual
                </button>
            </div>
        </div>

        <?= $msg ?>

        <!-- NAVIGATION TABS -->
        <div class="flex border-b border-slate-200 mb-6 bg-white rounded-xl p-1.5 shadow-sm">
            <a href="manage_records.php" class="flex-1 text-center py-3 font-bold text-sm rounded-lg transition bg-slate-900 text-white shadow">
                🇮🇩 DATA REKOR NASIONAL
            </a>
            <a href="record_packages/index.php" class="flex-1 text-center py-3 font-bold text-sm rounded-lg transition text-slate-500 hover:text-slate-900">
                📦 PAKET REKOR ACUAN EVENT
            </a>
        </div>

        <!-- MAIN DATA TABLE -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h2 class="font-bold text-slate-900 text-base uppercase">
                    Daftar Rekor Nasional (<?= count($listRecords) ?> Data)
                </h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-slate-600 font-bold text-xs tracking-wider uppercase border-b border-slate-200">
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
                                <td colspan="7" class="p-12 text-center text-slate-400 font-medium italic">Belum ada data rekornas yang tersimpan.</td>
                            </tr>
                        <?php else: foreach($listRecords as $r): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-4 text-slate-900 font-bold"><?= $r['distance'] ?>M <?= htmlspecialchars($r['stroke']) ?></td>
                                <td class="p-4"><span class="px-2 py-1 text-xs font-bold rounded-md <?= $r['jenis_kelamin']=='L'?'bg-sky-100 text-sky-700':'bg-rose-100 text-rose-700' ?>"><?= $r['jenis_kelamin'] ?></span></td>
                                <td class="p-4 text-slate-700 font-semibold"><?= htmlspecialchars($r['age_group']) ?></td>
                                <td class="p-4 text-slate-900 font-bold uppercase tracking-wide"><?= htmlspecialchars($r['holder_name']) ?></td>
                                <td class="p-4 text-center font-mono font-black text-emerald-600 text-base"><?= htmlspecialchars($r['record_time']) ?></td>
                                <td class="p-4 text-slate-500 text-xs font-semibold"><?= htmlspecialchars($r['location'] ?: '-') ?> (<?= $r['record_year'] ?: '-' ?>)</td>
                                <td class="p-4 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button onclick='openEditModal(<?= json_encode($r) ?>)' class="px-3 py-1.5 bg-amber-50 text-amber-700 hover:bg-amber-100 rounded-lg text-xs font-bold transition">Edit</button>
                                        <a href="?delete_id=<?= $r['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus data rekor ini?')" class="px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg text-xs font-bold transition">Hapus</a>
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
                <h3 id="modalTitle" class="font-black text-lg uppercase tracking-wider">TAMBAH DATA REKORNAS</h3>
                <button onclick="closeManualModal()" class="text-slate-400 hover:text-white text-xl font-bold">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="save_manual">
                <input type="hidden" name="id" id="form_id">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Jarak (Meter)</label>
                        <select name="distance" id="form_distance" class="w-full border-slate-300 rounded-lg text-sm bg-slate-50" required>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                            <option value="400">400</option>
                            <option value="800">800</option>
                            <option value="1500">1500</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Gaya</label>
                        <select name="stroke" id="form_stroke" class="w-full border-slate-300 rounded-lg text-sm bg-slate-50" required>
                            <option value="Gaya Bebas">Gaya Bebas</option>
                            <option value="Gaya Dada">Gaya Dada</option>
                            <option value="Gaya Punggung">Gaya Punggung</option>
                            <option value="Gaya Kupu-kupu">Gaya Kupu-kupu</option>
                            <option value="Gaya Ganti">Gaya Ganti</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Jenis Kelamin</label>
                        <select name="jenis_kelamin" id="form_jk" class="w-full border-slate-300 rounded-lg text-sm bg-slate-50" required>
                            <option value="L">Laki-Laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Kelompok Umur</label>
                        <select name="age_group" id="form_age" class="w-full border-slate-300 rounded-lg text-sm bg-slate-50" required>
                            <option value="SENIOR">SENIOR</option>
                            <option value="KU 1">KU 1</option>
                            <option value="KU 2">KU 2</option>
                            <option value="KU 3">KU 3</option>
                            <option value="KU 4">KU 4</option>
                            <option value="KU 5">KU 5</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Nama Pemegang Rekor</label>
                    <input type="text" name="holder_name" id="form_holder" required class="w-full border-slate-300 rounded-lg text-sm uppercase bg-slate-50 placeholder-slate-400" placeholder="Contoh: I GEDE SIMAN">
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Lokasi</label>
                        <input type="text" name="location" id="form_location" class="w-full border-slate-300 rounded-lg text-sm bg-slate-50 uppercase" placeholder="Contoh: JAKARTA">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Tahun</label>
                        <input type="number" name="record_year" id="form_year" class="w-full border-slate-300 rounded-lg text-sm bg-slate-50" placeholder="2024">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Waktu</label>
                        <input type="text" name="record_time" id="form_time" required class="w-full border-slate-300 rounded-lg text-sm font-mono font-bold bg-amber-50 text-amber-900 text-center" placeholder="00:00.00">
                    </div>
                </div>

                <div class="pt-4 border-t flex justify-end gap-3">
                    <button type="button" onclick="closeManualModal()" class="px-5 py-2.5 bg-slate-100 text-slate-600 font-bold rounded-xl hover:bg-slate-200 transition">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white font-bold rounded-xl shadow-lg hover:bg-emerald-700 transition">Simpan Rekor</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
    function openManualModal() {
        document.getElementById('modalTitle').innerText = 'TAMBAH DATA REKORNAS';
        document.getElementById('form_id').value = '';
        document.getElementById('form_distance').value = '50';
        document.getElementById('form_stroke').value = 'Gaya Bebas';
        document.getElementById('form_jk').value = 'L';
        document.getElementById('form_age').value = 'SENIOR';
        document.getElementById('form_holder').value = '';
        document.getElementById('form_location').value = '';
        document.getElementById('form_year').value = '';
        document.getElementById('form_time').value = '';
        
        document.getElementById('manualModal').classList.remove('hidden');
    }

    function openEditModal(data) {
        document.getElementById('modalTitle').innerText = 'EDIT DATA REKORNAS';
        document.getElementById('form_id').value = data.id;
        document.getElementById('form_distance').value = data.distance;
        document.getElementById('form_stroke').value = data.stroke;
        document.getElementById('form_jk').value = data.jenis_kelamin;
        document.getElementById('form_age').value = data.age_group;
        document.getElementById('form_holder').value = data.holder_name;
        document.getElementById('form_location').value = data.location;
        document.getElementById('form_year').value = data.record_year;
        document.getElementById('form_time').value = data.record_time;
        
        document.getElementById('manualModal').classList.remove('hidden');
    }

    function closeManualModal() {
        document.getElementById('manualModal').classList.add('hidden');
    }
</script>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>