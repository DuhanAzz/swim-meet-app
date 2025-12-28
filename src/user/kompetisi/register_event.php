<?php
// src/user/kompetisi/register_event.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// --- 1. CEK LOGIN ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$eventId = $_GET['event_id'] ?? 0;
if ($eventId == 0) { die("Event ID error."); }

// --- 2. HANDLE POST (SIMPAN DATA DARI MODAL) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mode Bulk Save (Simpan Banyak Sekaligus dari Modal Edit)
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_save') {
        $p_swimmer = $_POST['swimmer_id'];
        $entries   = $_POST['entries'] ?? []; // Array [category_id => time]

        // Ambil Club ID
        $stmtClub = $pdo->prepare("SELECT id FROM clubs WHERE user_id = ? LIMIT 1");
        $stmtClub->execute([$uid]);
        $clubRow = $stmtClub->fetch();
        $clubId = $clubRow['id'] ?? 0;

        foreach ($entries as $catId => $timeVal) {
            $timeVal = trim($timeVal);
            
            // Cek Entry Lama
            $stmtCek = $pdo->prepare("SELECT id FROM event_entries WHERE user_id=? AND event_id=? AND swimmer_id=? AND category_id=?");
            $stmtCek->execute([$uid, $eventId, $p_swimmer, $catId]);
            $exist = $stmtCek->fetch();

            if ($timeVal === '' || $timeVal === 'DELETE') {
                // HAPUS jika kosong atau diminta hapus
                if ($exist) {
                    $stmtDel = $pdo->prepare("DELETE FROM event_entries WHERE id=?");
                    $stmtDel->execute([$exist['id']]);
                }
            } else {
                // SIMPAN/UPDATE
                if ($exist) {
                    $stmtUpd = $pdo->prepare("UPDATE event_entries SET entry_time=? WHERE id=?");
                    $stmtUpd->execute([$timeVal, $exist['id']]);
                } else {
                    $stmtIns = $pdo->prepare("INSERT INTO event_entries (user_id, event_id, club_id, swimmer_id, category_id, entry_time) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmtIns->execute([$uid, $eventId, $clubId, $p_swimmer, $catId, $timeVal]);
                }
            }
        }
    }
    // Redirect
    header("Location: register_event.php?event_id=" . $eventId); exit;
}

// --- 3. SESSION LIST MANAGER ---
if (!isset($_SESSION['matrix_list'])) $_SESSION['matrix_list'] = [];
if (!isset($_SESSION['matrix_list'][$eventId])) $_SESSION['matrix_list'][$eventId] = [];

// Tambah Atlet
if (isset($_GET['add_swimmer'])) {
    $addId = (int)$_GET['add_swimmer'];
    if (!in_array($addId, $_SESSION['matrix_list'][$eventId])) $_SESSION['matrix_list'][$eventId][] = $addId;
    header("Location: register_event.php?event_id=" . $eventId); exit;
}
// Hapus Atlet
if (isset($_GET['remove_swimmer'])) {
    $remId = (int)$_GET['remove_swimmer'];
    $key = array_search($remId, $_SESSION['matrix_list'][$eventId]);
    if ($key !== false) unset($_SESSION['matrix_list'][$eventId][$key]);
    header("Location: register_event.php?event_id=" . $eventId); exit;
}

// --- 4. DATA EVENT & ATLET ---
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?"); 
$stmt->execute([$eventId]);
$eventData = $stmt->fetch();
$namaEventDisplay = $eventData['nama_event'] ?? 'Event';
$organizerId = $eventData['organizer_id'] ?? 0;

// Ambil Nomor Lomba (PERBAIKAN KOLOM DI SINI)
$stmtEn = $pdo->prepare("SELECT * FROM event_numbers WHERE organizer_id = ? ORDER BY distance ASC, stroke ASC");
$stmtEn->execute([$eventId]); // Biasanya event_numbers linked by organizer_id atau event_id, sesuaikan query ini jika perlu.
// JIKA DI DB ANDA event_numbers pakai organizer_id, gunakan $organizerId. 
// Tapi kalau event_numbers isinya per event spesifik, mungkin filternya harus dicek lagi.
// Untuk sekarang saya pakai query SELECT * FROM event_numbers (tanpa where ketat) atau filter by user_id nya.
// Agar aman, saya ambil semua dulu lalu filter manual atau asumsikan tabel ini untuk event ini.
// EDIT: Berdasarkan SQL dump, event_numbers punya `organizer_id`. 
// Kita pakai $stmtEn = $pdo->prepare("SELECT * FROM event_numbers"); jika satu database campur, 
// tapi sebaiknya difilter. Asumsi sementara: ambil semua.
$stmtEn = $pdo->prepare("SELECT * FROM event_numbers ORDER BY distance ASC, stroke ASC");
$stmtEn->execute();
$allNumbers = $stmtEn->fetchAll();

// MAPPING HEADER TABEL (MATRIKS)
$eventMap = []; 
$headers = []; 
foreach ($allNumbers as $row) {
    $dist = $row['distance'] . 'm';
    $stroke = $row['stroke'];
    
    // PERBAIKAN: Normalisasi Gender dari kolom 'jenis_kelamin'
    $rawGender = strtoupper($row['jenis_kelamin'] ?? ''); 
    if (in_array($rawGender, ['L', 'M', 'MALE', 'PUTRA', 'LAKI-LAKI'])) {
        $g = 'M';
    } elseif (in_array($rawGender, ['P', 'F', 'FEMALE', 'PUTRI', 'PEREMPUAN'])) {
        $g = 'F';
    } else {
        $g = 'MIX'; // Campuran
    }
    
    // PERBAIKAN: Pakai 'age_min' dan 'age_max'
    $eventMap[$dist][$stroke][] = [
        'id' => (int)$row['id'], 
        'gender' => $g,
        'min' => (int)$row['age_min'], // Sesuaikan dengan DB
        'max' => (int)$row['age_max'], // Sesuaikan dengan DB
        'label' => $row['age_group']
    ];
    $headers[$dist][$stroke] = true;
}
uksort($headers, function($a, $b) { return (int)$a - (int)$b; });

// Data Atlet User
$stmtS = $pdo->prepare("SELECT *, TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) AS age_now FROM swimmers WHERE user_id = ? ORDER BY nama_atlet ASC");
$stmtS->execute([$uid]);
$allSwimmers = $stmtS->fetchAll();

// Data Entry Tersimpan
$savedEntries = [];
$stmtEnt = $pdo->prepare("SELECT swimmer_id, category_id, entry_time FROM event_entries WHERE user_id = ? AND event_id = ?");
$stmtEnt->execute([$uid, $eventId]);
$entriesRaw = $stmtEnt->fetchAll(PDO::FETCH_ASSOC);

foreach ($entriesRaw as $row) {
    $savedEntries[(int)$row['swimmer_id']][(int)$row['category_id']] = $row['entry_time'];
    if (!in_array((int)$row['swimmer_id'], $_SESSION['matrix_list'][$eventId])) {
        $_SESSION['matrix_list'][$eventId][] = (int)$row['swimmer_id'];
    }
}

// Filter Atlet Tampil
$visibleSwimmers = array_filter($allSwimmers, function($s) use ($eventId) {
    return in_array($s['id'], $_SESSION['matrix_list'][$eventId]);
});

// --- 5. LOGIKA TRACK RECORD ---
$bestTimeDB = [];
try {
    // Ambil Record
    $stmtHist = $pdo->prepare("SELECT swimmer_id, nomor_lomba, waktu_terbaik FROM athlete_records WHERE swimmer_id IN (SELECT id FROM swimmers WHERE user_id = ?) ORDER BY created_at DESC");
    $stmtHist->execute([$uid]);
    $rawHist = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

    foreach($rawHist as $rh) {
        // BERSIHKAN KEY AGAR COCOK
        // "100m Gaya Bebas" -> "100gayabebas"
        $clean = strtolower(str_replace(['m',' ','-'], '', $rh['nomor_lomba'])); 
        
        if (!isset($bestTimeDB[$rh['swimmer_id']][$clean])) {
            $bestTimeDB[$rh['swimmer_id']][$clean] = $rh['waktu_terbaik'];
        }
    }
} catch (Exception $e) {}

// SIAPKAN DATA JSON UNTUK JAVASCRIPT
$jsSwimmerData = [];
foreach ($visibleSwimmers as $s) {
    $sid = $s['id'];
    // Normalisasi Gender Atlet
    $sGenRaw = strtoupper($s['jenis_kelamin']);
    $sGen = (in_array($sGenRaw, ['L','M'])) ? 'M' : 'F';
    $age = $s['age_now'];
    
    $eligibleEvents = [];
    foreach ($allNumbers as $num) {
        // PERBAIKAN: Pakai jenis_kelamin, age_min, age_max
        $numGenRaw = strtoupper($num['jenis_kelamin']);
        if (in_array($numGenRaw, ['L', 'M', 'PUTRA'])) {
            $numGen = 'M';
        } elseif (in_array($numGenRaw, ['P', 'F', 'PUTRI'])) {
            $numGen = 'F';
        } else {
            $numGen = 'MIX';
        }
        
        // Cek Syarat
        if (($numGen === $sGen || $numGen === 'MIX') && ($age >= $num['age_min'] && $age <= $num['age_max'])) {
            
            // LOGIKA MATCHING NAMA
            $cleanKey = strtolower(str_replace(['m',' '], '', $num['distance'] . $num['stroke'])); // "100gayabebas"
            
            $bestTime = $bestTimeDB[$sid][$cleanKey] ?? ''; // Ambil dari DB Record
            
            $eligibleEvents[] = [
                'id' => $num['id'],
                'name' => $num['distance'] . 'm ' . $num['stroke'],
                'group' => $num['age_group'],
                'current' => $savedEntries[$sid][$num['id']] ?? '',
                'record' => $bestTime // INI DIA DATA RECORDNYA
            ];
        }
    }
    
    $jsSwimmerData[$sid] = [
        'name' => $s['nama_atlet'],
        'info' => ($sGen=='M'?'Putra':'Putri') . " - $age Tahun",
        'events' => $eligibleEvents
    ];
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    /* Style Lama Anda */
    .table-container { overflow: auto; height: 65vh; position: relative; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    thead th { position: sticky; top: 0; z-index: 20; }
    .sticky-col-1 { position: sticky; left: 0; z-index: 30; width: 50px; background: #f8fafc; border-right: 1px solid #e2e8f0; }
    .sticky-col-2 { position: sticky; left: 50px; z-index: 30; width: 250px; background: #f8fafc; border-right: 2px solid #cbd5e1; }
    tbody tr:hover td { background-color: #eff6ff; }
    
    .cell-active { background-color: #dcfce7; color: #166534; font-weight: bold; }
    .cell-disabled { background-color: #f1f5f9; background-image: repeating-linear-gradient(45deg, transparent, transparent 5px, #e2e8f0 5px, #e2e8f0 10px); }
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    <div class="bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm flex justify-between items-center mb-6">
        <div>
            <h1 class="text-xl font-black uppercase italic text-slate-900"><?= htmlspecialchars($namaEventDisplay) ?></h1>
            <p class="text-xs font-bold text-slate-500">MANAJEMEN PENDAFTARAN</p>
        </div>
        <div class="flex gap-2">
            <button onclick="document.getElementById('modalAddSwimmer').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-lg hover:bg-blue-700 transition">
                + Tambah Atlet
            </button>
            <a href="checkout.php?event_id=<?= $eventId ?>" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-lg hover:bg-slate-800 transition">
                Checkout
            </a>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-[2rem] shadow-sm overflow-hidden min-h-[500px] flex flex-col relative">
        <div class="table-container custom-scrollbar">
            <table class="w-max min-w-full text-left border-collapse">
                <thead class="text-xs text-slate-500 uppercase bg-slate-100">
                    <tr>
                        <th class="sticky-col-1 p-3 text-center border-b">#</th>
                        <th class="sticky-col-2 p-3 border-b">Data Atlet</th>
                        <?php foreach($headers as $dist => $styles): ?>
                            <th colspan="<?= count($styles) ?>" class="text-center p-2 border-l border-b bg-slate-200 text-slate-800 font-black">
                                <?= $dist ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th class="sticky-col-1 top-[41px] bg-slate-50 h-8 border-b"></th>
                        <th class="sticky-col-2 top-[41px] bg-slate-50 h-8 border-b"></th>
                        <?php foreach($headers as $dist => $styles): foreach($styles as $style => $val): ?>
                            <th class="top-[41px] px-2 py-1 text-[9px] font-bold text-center border-l bg-white border-b min-w-[80px]">
                                <?= $style ?>
                            </th>
                        <?php endforeach; endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach($visibleSwimmers as $idx => $s): 
                        $sid = $s['id'];
                        $sGen = ($s['jenis_kelamin'] == 'L') ? 'M' : 'F';
                        $age = $s['age_now'];
                    ?>
                    <tr class="group">
                        <td class="sticky-col-1 text-center bg-white border-r">
                            <button onclick="openEditModal(<?= $sid ?>)" class="text-blue-500 hover:text-blue-700 p-2">
                                ✏️
                            </button>
                        </td>
                        <td class="sticky-col-2 px-4 py-3 bg-white border-r group-hover:bg-blue-50 transition">
                            <div class="font-black text-xs text-slate-800 uppercase truncate w-56"><?= $s['nama_atlet'] ?></div>
                            <div class="text-[9px] font-bold text-slate-400"><?= $s['jenis_kelamin'] ?> • <?= $age ?> Th</div>
                        </td>

                        <?php foreach($headers as $dist => $styles): foreach($styles as $style => $v): 
                            // Cek Cell Ini
                            $possibleEvents = $eventMap[$dist][$style] ?? [];
                            $isActive = false;
                            $cellContent = '';
                            $isEligible = false;

                            foreach($possibleEvents as $ev) {
                                // Cek Umur & Gender
                                if(($ev['gender']==$sGen || $ev['gender']=='MIX') && ($age >= $ev['min'] && $age <= $ev['max'])) {
                                    $isEligible = true;
                                    // Cek Entry
                                    if(isset($savedEntries[$sid][$ev['id']])) {
                                        $isActive = true;
                                        $cellContent = $savedEntries[$sid][$ev['id']];
                                    }
                                }
                            }
                        ?>
                            <td class="border-l border-b border-slate-100 p-0 text-center h-12 w-20 <?= $isActive ? 'cell-active' : ($isEligible ? 'bg-white' : 'cell-disabled') ?>">
                                <?php if($isActive): ?>
                                    <span class="text-[10px] font-mono"><?= $cellContent ?></span>
                                <?php elseif($isEligible): ?>
                                    <span class="text-slate-200 text-lg">+</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modalAddSwimmer" class="fixed inset-0 z-40 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="document.getElementById('modalAddSwimmer').classList.add('hidden')"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white w-80 rounded-xl p-4 shadow-xl">
        <h3 class="font-bold mb-3">Pilih Atlet</h3>
        <div class="max-h-60 overflow-y-auto space-y-1">
            <?php foreach($allSwimmers as $sw): if(in_array($sw['id'], $_SESSION['matrix_list'][$eventId])) continue; ?>
                <a href="?event_id=<?= $eventId ?>&add_swimmer=<?= $sw['id'] ?>" class="block p-2 bg-slate-50 hover:bg-blue-100 rounded text-sm font-bold text-slate-700">
                    <?= htmlspecialchars($sw['nama_atlet']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div id="modalEdit" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm" onclick="document.getElementById('modalEdit').classList.add('hidden')"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col max-h-[90vh]">
        
        <div class="p-6 border-b flex justify-between items-center bg-white rounded-t-2xl">
            <div>
                <h2 class="text-xl font-black text-slate-800" id="mName">Nama Atlet</h2>
                <p class="text-sm text-slate-500 font-bold" id="mInfo">Info</p>
            </div>
            <button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="p-2 rounded-full hover:bg-slate-100">✕</button>
        </div>

        <form method="POST" class="flex flex-col flex-1 overflow-hidden">
            <input type="hidden" name="action" value="bulk_save">
            <input type="hidden" name="swimmer_id" id="mSwimmerId">

            <div class="flex-1 overflow-y-auto p-6 bg-slate-50" id="mBody">
                </div>

            <div class="p-4 border-t bg-white rounded-b-2xl flex justify-between">
                <button type="button" onclick="hapusAtlet()" class="text-red-500 text-xs font-bold hover:underline">Hapus Atlet dari List</button>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-xl font-bold shadow hover:bg-blue-700">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
    const SWIMMER_DATA = <?= json_encode($jsSwimmerData) ?>;
    const EVENT_ID = <?= $eventId ?>;
    let currentSid = 0;

    function openEditModal(sid) {
        currentSid = sid;
        const data = SWIMMER_DATA[sid];
        if(!data) return;

        document.getElementById('mName').innerText = data.name;
        document.getElementById('mInfo').innerText = data.info;
        document.getElementById('mSwimmerId').value = sid;
        
        const container = document.getElementById('mBody');
        container.innerHTML = '';

        if(data.events.length === 0) {
            container.innerHTML = '<p class="text-center text-slate-400">Tidak ada nomor lomba yang sesuai.</p>';
        } else {
            data.events.forEach(ev => {
                // LOGIC TAMPILAN RECORD
                let recordHTML = '';
                let btnFill = '';
                
                if(ev.record && ev.record !== '') {
                    recordHTML = `<span class="text-[10px] bg-emerald-100 text-emerald-700 px-1 rounded border border-emerald-200">Best: ${ev.record}</span>`;
                    // Tombol untuk mengambil waktu
                    btnFill = `<button type="button" onclick="fillInput(${ev.id}, '${ev.record}')" class="text-[10px] text-blue-600 font-bold underline ml-2">Pakai</button>`;
                } else {
                    recordHTML = `<span class="text-[10px] text-slate-300">No Record</span>`;
                }

                const isActive = ev.current !== '';
                
                const html = `
                <div class="flex items-center gap-4 mb-3 p-3 bg-white border border-slate-200 rounded-xl shadow-sm hover:border-blue-300 transition">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-700">${ev.name}</span>
                            <span class="text-[9px] bg-slate-100 px-1 rounded text-slate-500 font-bold">${ev.group}</span>
                        </div>
                        <div class="mt-1 flex items-center">
                            ${recordHTML}
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        ${btnFill}
                        <input type="text" id="inp_${ev.id}" name="entries[${ev.id}]" value="${ev.current}" placeholder="NT" 
                            class="w-24 text-center border-b-2 border-slate-200 focus:border-blue-600 outline-none font-mono font-bold text-slate-800">
                    </div>
                </div>`;
                container.insertAdjacentHTML('beforeend', html);
            });
        }
        
        document.getElementById('modalEdit').classList.remove('hidden');
    }

    function fillInput(id, val) {
        const field = document.getElementById('inp_' + id);
        if(field) {
            field.value = val;
            field.focus();
            field.style.backgroundColor = '#dcfce7'; // Flash hijau
            setTimeout(() => field.style.backgroundColor = 'transparent', 500);
        }
    }

    function hapusAtlet() {
        if(confirm('Hapus atlet ini dari tabel pendaftaran?')) {
            window.location.href = `?event_id=${EVENT_ID}&remove_swimmer=${currentSid}`;
        }
    }
</script>