<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Cek User Login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

// --- HANDLE REQUEST (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. Tambah Atlet
    if (isset($_POST['add_athlete'])) {
        try {
            $sql = "INSERT INTO swimmers (user_id, nama_atlet, asal_sekolah, jenis_kelamin, tanggal_lahir) VALUES (?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([
                $uid, 
                strtoupper($_POST['nama_atlet']), 
                strtoupper($_POST['asal_sekolah']), 
                $_POST['jenis_kelamin'], 
                $_POST['tanggal_lahir']
            ]);
            $_SESSION['toast_type'] = 'success'; 
            $_SESSION['toast_message'] = 'Atlet berhasil ditambahkan!';
        } catch (Exception $e) { 
            $_SESSION['toast_type'] = 'error'; 
            $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage(); 
        }
        header("Location: index.php"); exit;
    }

    // 2. Edit Atlet
    if (isset($_POST['edit_athlete'])) {
        try {
            $sql = "UPDATE swimmers SET nama_atlet=?, asal_sekolah=?, jenis_kelamin=?, tanggal_lahir=? WHERE id=? AND user_id=?";
            $pdo->prepare($sql)->execute([
                strtoupper($_POST['nama_atlet']), 
                strtoupper($_POST['asal_sekolah']), 
                $_POST['jenis_kelamin'], 
                $_POST['tanggal_lahir'], 
                $_POST['id_atlet'], 
                $uid
            ]);
            $_SESSION['toast_type'] = 'success'; 
            $_SESSION['toast_message'] = 'Data atlet diperbarui!';
        } catch (Exception $e) { 
            $_SESSION['toast_type'] = 'error'; 
            $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage(); 
        }
        header("Location: index.php"); exit;
    }

    // 3. Hapus Atlet
    if (isset($_POST['delete_id'])) {
        try {
            $pdo->prepare("DELETE FROM swimmers WHERE id = ? AND user_id = ?")->execute([$_POST['delete_id'], $uid]);
            $_SESSION['toast_type'] = 'success'; 
            $_SESSION['toast_message'] = 'Data atlet dihapus.';
        } catch (Exception $e) {
            $_SESSION['toast_type'] = 'error'; 
            $_SESSION['toast_message'] = 'Gagal menghapus data.';
        }
        header("Location: index.php"); exit;
    }

    // 4. HANDLE RECORD (TAMBAH & EDIT)
    if (isset($_POST['record_mode'])) {
        try {
            $mode = $_POST['record_mode'];
            $nomor = $_POST['jarak'] . ' ' . $_POST['gaya'];
            $waktu = $_POST['waktu_terbaik'];
            $tgl = !empty($_POST['tanggal_dicapai']) ? $_POST['tanggal_dicapai'] : date('Y-m-d');

            if ($mode == 'add') {
                $sid = $_POST['swimmer_id'];
                $stmt = $pdo->prepare("INSERT INTO athlete_records (swimmer_id, nomor_lomba, waktu_terbaik, tanggal_dicapai) VALUES (?, ?, ?, ?)");
                $stmt->execute([$sid, $nomor, $waktu, $tgl]);
                $_SESSION['toast_message'] = 'Catatan waktu disimpan!';
            } 
            elseif ($mode == 'edit') {
                $rid = $_POST['record_id'];
                $stmt = $pdo->prepare("UPDATE athlete_records SET nomor_lomba=?, waktu_terbaik=?, tanggal_dicapai=? WHERE id=?");
                $stmt->execute([$nomor, $waktu, $tgl, $rid]);
                $_SESSION['toast_message'] = 'Catatan waktu diperbarui!';
            }
            $_SESSION['toast_type'] = 'success';
        } catch (Exception $e) { 
            $_SESSION['toast_type'] = 'error'; 
            $_SESSION['toast_message'] = 'Error: ' . $e->getMessage(); 
        }
        header("Location: index.php"); exit;
    }

    // 5. Hapus Record
    if (isset($_POST['delete_record_id'])) {
        $pdo->prepare("DELETE FROM athlete_records WHERE id = ?")->execute([$_POST['delete_record_id']]);
        $_SESSION['toast_type'] = 'success'; 
        $_SESSION['toast_message'] = 'Record dihapus.';
        header("Location: index.php"); exit;
    }
}

// --- AMBIL DATA ---
$stmt = $pdo->prepare("SELECT * FROM swimmers WHERE user_id = ? ORDER BY nama_atlet ASC");
$stmt->execute([$uid]);
$swimmers = $stmt->fetchAll();

// Ambil Records untuk semua atlet user ini (di-group by swimmer_id untuk efisiensi)
$stmtRec = $pdo->prepare("
    SELECT ar.swimmer_id, ar.* FROM athlete_records ar 
    JOIN swimmers s ON ar.swimmer_id = s.id 
    WHERE s.user_id = ? 
    ORDER BY ar.created_at DESC
");
$stmtRec->execute([$uid]);
$allRecords = $stmtRec->fetchAll(PDO::FETCH_GROUP); 

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-down {
    animation: fadeInDown 0.5s ease-out forwards;
}
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Database Atlet</h1>
            <p class="text-sm text-slate-500">Kelola data profil dan catatan waktu (Best Time) atlet.</p>
        </div>
        <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-bold text-xs shadow-lg flex items-center gap-2 transition transform hover:-translate-y-0.5">
            <span>➕</span> Tambah Atlet
        </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <?php if(empty($swimmers)): ?>
            <div class="p-12 text-center">
                <span class="text-5xl block mb-4 grayscale opacity-30">🏊</span>
                <h3 class="text-lg font-bold text-slate-700">Belum ada data atlet.</h3>
                <p class="text-slate-400 text-sm mt-1">Silakan tambahkan atlet untuk mulai mendaftar lomba.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-xs border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4">Nama & Sekolah</th>
                            <th class="px-6 py-4">Gender & Usia</th>
                            <th class="px-6 py-4 text-center">Best Time</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($swimmers as $s): 
                            $dob = new DateTime($s['tanggal_lahir']);
                            $now = new DateTime();
                            $age = $now->diff($dob)->y;
                            $records = $allRecords[$s['id']] ?? [];
                        ?>
                        <tr class="hover:bg-blue-50 transition group">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800 uppercase"><?= htmlspecialchars($s['nama_atlet']) ?></div>
                                <div class="text-xs text-slate-500 mt-1 uppercase font-semibold text-blue-600/80">
                                    <?= htmlspecialchars($s['asal_sekolah'] ?? '-') ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="<?= $s['jenis_kelamin']=='Male' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700' ?> px-2 py-0.5 rounded text-[10px] font-bold uppercase">
                                        <?= $s['jenis_kelamin']=='Male' ? 'Putra' : 'Putri' ?>
                                    </span>
                                    <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-[10px] font-bold"><?= $age ?> TH</span>
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono">Lahir: <?= date('d/m/Y', strtotime($s['tanggal_lahir'])) ?></div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button onclick='openRecordModal(<?= $s['id'] ?>, "<?= htmlspecialchars($s['nama_atlet']) ?>")' class="border border-slate-300 hover:border-blue-500 hover:text-blue-600 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-2 mx-auto bg-white shadow-sm">
                                    <span>⏱️</span> <?= count($records) ?> Record
                                </button>
                                <div id="records_data_<?= $s['id'] ?>" class="hidden"><?= json_encode($records) ?></div>
                            </td>
                            <td class="px-6 py-4 text-right flex justify-end gap-2">
                                <button onclick='openEditModal(<?= json_encode($s) ?>)' class="text-blue-600 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded text-xs font-bold transition">Edit</button>
                                <button onclick="openDeleteModal('atlet', <?= $s['id'] ?>)" class="text-red-600 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded text-xs font-bold transition">Hapus</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if(isset($_SESSION['toast_message'])): ?>
<div id="toast-notification" class="fixed top-24 right-6 z-[9999] flex items-center w-full max-w-xs p-4 space-x-4 text-slate-500 bg-white divide-x divide-slate-200 rounded-lg shadow-2xl border-l-4 <?= $_SESSION['toast_type'] == 'success' ? 'border-emerald-500' : 'border-red-500' ?> animate-fade-in-down transition-all" role="alert">
    <div class="text-2xl">
        <?= $_SESSION['toast_type'] == 'success' ? '✅' : '⚠️' ?>
    </div>
    <div class="pl-4 text-sm font-bold text-slate-700">
        <?= $_SESSION['toast_message'] ?>
    </div>
    <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-white text-slate-400 hover:text-slate-900 rounded-lg focus:ring-2 focus:ring-slate-300 p-1.5 hover:bg-slate-100 inline-flex h-8 w-8" onclick="document.getElementById('toast-notification').remove()">
        <span class="sr-only">Close</span>
        ✖️
    </button>
</div>
<script>
    // Hilang otomatis setelah 3 detik
    setTimeout(() => {
        const toast = document.getElementById('toast-notification');
        if(toast) {
            toast.style.transition = 'opacity 0.5s ease';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 500);
        }
    }, 3000);
</script>
<?php 
unset($_SESSION['toast_type']);
unset($_SESSION['toast_message']);
endif; 
?>


<div id="athleteModal" class="fixed inset-0 z-50 hidden bg-slate-900/80 backdrop-blur-sm flex justify-center items-center p-4 transition-opacity">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden transform scale-100 transition-all">
        <div class="bg-white px-6 py-4 flex justify-between items-center border-b border-slate-100">
            <h3 class="font-black text-lg text-slate-800" id="modalTitle">Data Atlet</h3>
            <button onclick="document.getElementById('athleteModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-800 text-2xl font-bold">×</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="add_athlete" id="modeAdd">
            <input type="hidden" name="edit_athlete" id="modeEdit" disabled>
            <input type="hidden" name="id_atlet" id="inputId">

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nama Lengkap</label>
                <input type="text" name="nama_atlet" id="inputNama" class="w-full px-4 py-2 border rounded-lg font-bold uppercase focus:ring-2 focus:ring-blue-500 outline-none" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Asal Sekolah / Universitas</label>
                <input type="text" name="asal_sekolah" id="inputSekolah" class="w-full px-4 py-2 border rounded-lg uppercase focus:ring-2 focus:ring-blue-500 outline-none" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Gender</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="cursor-pointer">
                        <input type="radio" name="jenis_kelamin" id="genderMale" value="Male" class="peer sr-only" required>
                        <div class="text-center py-2 border rounded-lg peer-checked:bg-blue-600 peer-checked:text-white font-bold text-sm text-slate-500 transition">Putra 🚹</div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="jenis_kelamin" id="genderFemale" value="Female" class="peer sr-only" required>
                        <div class="text-center py-2 border rounded-lg peer-checked:bg-pink-500 peer-checked:text-white font-bold text-sm text-slate-500 transition">Putri 🚺</div>
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tanggal Lahir</label>
                <input type="date" name="tanggal_lahir" id="inputDob" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
            </div>
            <button class="w-full bg-slate-900 hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-lg mt-2 transition">Simpan Data</button>
        </form>
    </div>
</div>

<div id="recordModal" class="fixed inset-0 z-50 hidden bg-slate-900/80 backdrop-blur-sm flex justify-center items-center p-4 transition-opacity">
    <div class="bg-white w-full max-w-2xl h-[85vh] rounded-2xl shadow-2xl overflow-hidden flex flex-col">
        <div class="bg-slate-50 px-6 py-4 flex justify-between items-center border-b border-slate-200 shrink-0">
            <div>
                <h3 class="font-black text-lg text-slate-800">PERSONAL BEST TIME</h3>
                <p class="text-xs font-bold text-blue-600 uppercase tracking-wide" id="recSwimmerName">Nama Atlet</p>
            </div>
            <button onclick="document.getElementById('recordModal').classList.add('hidden')" class="w-8 h-8 rounded-full bg-slate-200 hover:bg-slate-300 flex items-center justify-center text-slate-600 font-bold transition">✕</button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 bg-white">
            
            <form id="recordForm" method="POST" class="bg-slate-50 p-4 rounded-xl border border-slate-200 mb-6 transition-all duration-300">
                <input type="hidden" name="record_mode" id="recMode" value="add">
                <input type="hidden" name="record_id" id="recInputId">
                <input type="hidden" name="swimmer_id" id="recSwimmerId">
                
                <div class="flex justify-between items-center mb-3">
                    <p class="text-xs font-bold text-slate-400 uppercase" id="recFormTitle">➕ Tambah Catatan Baru</p>
                    <button type="button" onclick="resetRecordForm()" id="btnCancelEdit" class="hidden text-xs text-red-500 font-bold hover:underline">Batal Edit</button>
                </div>
                
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[80px]">
                        <select name="jarak" id="recJarak" class="w-full text-sm border rounded-lg p-2 bg-white font-bold text-slate-700">
                            <option value="50m">50m</option><option value="100m">100m</option><option value="200m">200m</option>
                            <option value="400m">400m</option><option value="800m">800m</option><option value="1500m">1500m</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[120px]">
                        <select name="gaya" id="recGaya" class="w-full text-sm border rounded-lg p-2 bg-white font-bold text-slate-700">
                            <option value="Gaya Bebas">Gaya Bebas</option><option value="Gaya Dada">Gaya Dada</option>
                            <option value="Gaya Punggung">Gaya Punggung</option><option value="Gaya Kupu">Gaya Kupu</option>
                            <option value="Gaya Ganti">Gaya Ganti</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[100px]">
                        <input type="text" name="waktu_terbaik" id="recWaktu" class="w-full text-sm border rounded-lg p-2 font-mono" placeholder="00:25.50" required>
                    </div>
                    <div class="flex-1 min-w-[100px]">
                        <input type="date" name="tanggal_dicapai" id="recTanggal" class="w-full text-sm border rounded-lg p-2 text-slate-500">
                    </div>
                    <button id="btnSaveRecord" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-blue-700 shadow transition">Simpan</button>
                </div>
            </form>

            <div id="recordList" class="space-y-3"></div>
            <div id="emptyRecordMsg" class="flex flex-col items-center justify-center py-10 text-slate-400 text-sm hidden">
                <span class="text-3xl mb-2">⏱️</span>
                Belum ada catatan waktu.
            </div>
        </div>
    </div>
</div>

<div id="deleteModal" class="fixed inset-0 z-[70] hidden bg-slate-900/80 backdrop-blur-sm flex justify-center items-center p-4 transition-opacity">
    <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl p-6 text-center transform scale-100 transition-all animate-bounce-in">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <span class="text-3xl text-red-600">⚠️</span>
        </div>
        <h3 class="text-lg font-black text-slate-800 mb-2">Hapus Data Ini?</h3>
        <p class="text-sm text-slate-500 mb-6">Tindakan ini tidak dapat dibatalkan. Data yang dihapus akan hilang permanen.</p>
        
        <form method="POST" class="flex gap-3 justify-center">
            <input type="hidden" name="" id="deleteInputName" value="">
            
            <button type="button" onclick="closeDeleteModal()" class="flex-1 bg-slate-100 text-slate-700 py-3 rounded-xl font-bold text-sm hover:bg-slate-200 transition">Batal</button>
            <button type="submit" class="flex-1 bg-red-600 text-white py-3 rounded-xl font-bold text-sm hover:bg-red-700 shadow-lg transition transform hover:-translate-y-0.5">Ya, Hapus</button>
        </form>
    </div>
</div>

<script>
// --- Logic Modal Atlet ---
function openAddModal() {
    document.getElementById('modalTitle').innerText = 'Tambah Atlet Baru';
    document.querySelector('#athleteModal form').reset();
    document.getElementById('modeAdd').disabled = false;
    document.getElementById('modeEdit').disabled = true;
    document.getElementById('athleteModal').classList.remove('hidden');
}

function openEditModal(data) {
    document.getElementById('modalTitle').innerText = 'Edit Data Atlet';
    document.getElementById('inputId').value = data.id;
    document.getElementById('inputNama').value = data.nama_atlet;
    document.getElementById('inputSekolah').value = data.asal_sekolah;
    
    // Logic Radio Button
    if(data.jenis_kelamin === 'Male') {
        document.getElementById('genderMale').checked = true;
    } else {
        document.getElementById('genderFemale').checked = true;
    }
    
    document.getElementById('inputDob').value = data.tanggal_lahir;
    document.getElementById('modeAdd').disabled = true;
    document.getElementById('modeEdit').disabled = false;
    document.getElementById('athleteModal').classList.remove('hidden');
}

// --- Logic Modal Records ---
function openRecordModal(id, name) {
    document.getElementById('recSwimmerName').innerText = name;
    document.getElementById('recSwimmerId').value = id;
    
    const rawData = document.getElementById('records_data_' + id).textContent;
    const records = rawData ? JSON.parse(rawData) : [];
    const container = document.getElementById('recordList');
    container.innerHTML = '';
    
    resetRecordForm();

    if (records.length === 0) {
        document.getElementById('emptyRecordMsg').classList.remove('hidden');
    } else {
        document.getElementById('emptyRecordMsg').classList.add('hidden');
        records.forEach(r => {
            const row = document.createElement('div');
            row.className = 'flex justify-between items-center bg-white p-4 rounded-xl border border-slate-100 shadow-sm hover:border-blue-300 transition group';
            
            const recData = JSON.stringify(r).replace(/"/g, '&quot;');
            
            row.innerHTML = `
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-lg text-blue-600">🏅</div>
                    <div>
                        <div class="font-black text-slate-800 text-sm">${r.nomor_lomba}</div>
                        <div class="text-[10px] text-slate-400 font-bold uppercase mt-0.5">📅 ${r.tanggal_dicapai || '-'}</div>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <span class="font-mono font-black text-blue-600 text-lg tracking-tight">${r.waktu_terbaik}</span>
                    
                    <button type="button" onclick="editRecord(${recData})" class="w-8 h-8 rounded-full hover:bg-yellow-50 text-slate-300 hover:text-yellow-500 font-bold transition flex items-center justify-center" title="Edit">✏️</button>

                    <button type="button" onclick="openDeleteModal('record', ${r.id})" class="w-8 h-8 rounded-full hover:bg-red-50 text-slate-300 hover:text-red-500 font-bold transition flex items-center justify-center" title="Hapus">✕</button>
                </div>
            `;
            container.appendChild(row);
        });
    }
    
    document.getElementById('recordModal').classList.remove('hidden');
}

function editRecord(data) {
    document.getElementById('recMode').value = 'edit';
    document.getElementById('recInputId').value = data.id;
    document.getElementById('recWaktu').value = data.waktu_terbaik;
    document.getElementById('recTanggal').value = data.tanggal_dicapai;
    
    const parts = data.nomor_lomba.split(' ');
    document.getElementById('recJarak').value = parts[0];
    document.getElementById('recGaya').value = parts.slice(1).join(' ');

    document.getElementById('recFormTitle').innerText = '✏️ Edit Catatan Waktu';
    document.getElementById('recFormTitle').className = 'text-xs font-bold text-yellow-600 uppercase mb-3';
    document.getElementById('btnSaveRecord').innerText = 'Update';
    document.getElementById('btnSaveRecord').className = 'bg-yellow-500 text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-yellow-600 shadow transition';
    document.getElementById('btnCancelEdit').classList.remove('hidden');
    document.getElementById('recordForm').classList.add('border-yellow-400', 'bg-yellow-50');
}

function resetRecordForm() {
    document.getElementById('recordForm').reset();
    document.getElementById('recMode').value = 'add';
    document.getElementById('recInputId').value = '';
    
    document.getElementById('recFormTitle').innerText = '➕ Tambah Catatan Baru';
    document.getElementById('recFormTitle').className = 'text-xs font-bold text-slate-400 uppercase mb-3';
    document.getElementById('btnSaveRecord').innerText = 'Simpan';
    document.getElementById('btnSaveRecord').className = 'bg-blue-600 text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-blue-700 shadow transition';
    document.getElementById('btnCancelEdit').classList.add('hidden');
    document.getElementById('recordForm').classList.remove('border-yellow-400', 'bg-yellow-50');
}

// --- Logic Modal Hapus (Konfirmasi) ---
function openDeleteModal(type, id) {
    const input = document.getElementById('deleteInputName');
    input.value = id;
    
    if (type === 'atlet') {
        input.name = 'delete_id';
    } else {
        input.name = 'delete_record_id';
    }
    
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}
</script>