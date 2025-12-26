<?php
// src/user/kompetisi/register_event.php
session_start();

// Perbaikan Path Config
require_once __DIR__ . '/../../config/database.php';

// --- CEK LOGIN ---
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'club' && $_SESSION['role'] !== 'user')) {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$organizerId = $_GET['event_id'] ?? 0;

// =================================================================================
// 1. LOGIKA "GEMBOK" (LOCK SYSTEM) - WAJIB DI ATAS
// =================================================================================
$isLocked = false; // Default terbuka
$statusLabel = 'pending';

try {
    // Cek tabel event_registrations (Pastikan tabel ini sudah dibuat di database!)
    $stmtStatus = $pdo->prepare("SELECT status FROM event_registrations WHERE user_id = ? AND event_id = ?");
    $stmtStatus->execute([$uid, $organizerId]);
    $regStatus = $stmtStatus->fetch();

    if ($regStatus && $regStatus['status'] === 'approved') {
        $isLocked = true; // KUNCI HALAMAN
        $statusLabel = 'approved';
    }
} catch (PDOException $e) {
    // Jika tabel belum ada, biarkan terbuka (jangan error)
    $isLocked = false;
}
// =================================================================================

// --- HANDLE: SESSION LIST ATLET ---
if (!isset($_SESSION['matrix_list'][$organizerId])) {
    $_SESSION['matrix_list'][$organizerId] = [];
}

// 2. LOGIC TAMBAH ATLET (CEGAH JIKA LOCKED)
if (isset($_GET['add_swimmer'])) {
    if (!$isLocked) { // Hanya jalan jika TIDAK dikunci
        $addId = (int)$_GET['add_swimmer'];
        if (!in_array($addId, $_SESSION['matrix_list'][$organizerId])) {
            $_SESSION['matrix_list'][$organizerId][] = $addId;
        }
    }
    header("Location: register_event.php?event_id=" . $organizerId);
    exit;
}

// 3. LOGIC HAPUS ATLET (CEGAH JIKA LOCKED)
if (isset($_GET['remove_swimmer'])) {
    if (!$isLocked) { // Hanya jalan jika TIDAK dikunci
        $remId = (int)$_GET['remove_swimmer'];
        $key = array_search($remId, $_SESSION['matrix_list'][$organizerId]);
        if ($key !== false) {
            unset($_SESSION['matrix_list'][$organizerId][$key]);
        }
    }
    header("Location: register_event.php?event_id=" . $organizerId);
    exit;
}

// --- 4. AMBIL INFO ORGANIZER / EVENT ---
// (Menggunakan logika lama Anda yang query ke tabel users)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([$organizerId]);
$organizer = $stmt->fetch();

// Fallback jika tidak ketemu di tabel users (mungkin ID event?)
if (!$organizer) {
    try {
        $stmtEv = $pdo->prepare("SELECT nama_event as nama_lengkap, lokasi as location FROM events WHERE id = ?");
        $stmtEv->execute([$organizerId]);
        $organizer = $stmtEv->fetch();
    } catch(Exception $ex) {}
}
if (!$organizer) $organizer = ['nama_lengkap' => 'Event Tidak Ditemukan', 'location' => '-'];

// --- 5. AMBIL SEMUA ATLET SAYA ---
$mySwimmers = $pdo->prepare("SELECT * FROM swimmers WHERE user_id = ? ORDER BY nama_atlet ASC");
$mySwimmers->execute([$uid]);
$allSwimmers = $mySwimmers->fetchAll();

// --- 6. AMBIL NOMOR LOMBA ---
try {
    $sql = "SELECT * FROM event_numbers WHERE organizer_id = ? ORDER BY distance ASC, stroke ASC";
    $allCats = $pdo->prepare($sql);
    $allCats->execute([$organizerId]);
    $rawEvents = $allCats->fetchAll();
} catch (PDOException $e) { die("Error Database: " . $e->getMessage()); }

// --- 7. MAPPING DATA ---
$categories = [];
foreach ($rawEvents as $row) {
    $row['parsed_distance'] = $row['distance'];
    $row['parsed_style']    = $row['stroke'];
    $g = strtoupper($row['jenis_kelamin']);
    if(in_array($g, ['L', 'M', 'MALE', 'LAKI-LAKI', 'PUTRA', 'PRIA'])) $row['normalized_gender'] = 'M';
    elseif(in_array($g, ['P', 'F', 'FEMALE', 'PEREMPUAN', 'PUTRI', 'WANITA'])) $row['normalized_gender'] = 'F';
    else $row['normalized_gender'] = 'MIXED';
    $categories[] = $row;
}

// --- 8. BUAT HEADER MATRIX ---
$headers = [];
foreach ($categories as $cat) {
    $d = $cat['parsed_distance'] . 'm'; 
    $s = $cat['parsed_style'];
    $headers[$d][$s] = true;
}
uksort($headers, function($a, $b) { return (int)$a - (int)$b; });

// --- 9. AMBIL DATA ENTRY ---
$saved = [];
$registeredSwimmerIds = [];
try {
    $stmtEntries = $pdo->prepare("
        SELECT ee.* FROM event_entries ee 
        JOIN event_numbers en ON ee.category_id = en.id
        WHERE ee.club_id = ? AND en.organizer_id = ?
    ");
    $stmtEntries->execute([$uid, $organizerId]);
    $savedEntries = $stmtEntries->fetchAll();
    foreach($savedEntries as $row) { 
        $saved[$row['swimmer_id']][$row['category_id']] = $row['entry_time'];
        if (!in_array($row['swimmer_id'], $registeredSwimmerIds)) {
            $registeredSwimmerIds[] = $row['swimmer_id'];
        }
    }
} catch (Exception $e) {}

// --- LOGIKA FILTER TAMPILAN ---
$visibleSwimmerIds = array_unique(array_merge($registeredSwimmerIds, $_SESSION['matrix_list'][$organizerId]));
$visibleSwimmers = array_filter($allSwimmers, function($s) use ($visibleSwimmerIds) {
    return in_array($s['id'], $visibleSwimmerIds);
});

function getGenderLabel($val) {
    $v = strtoupper($val);
    if(in_array($v, ['L', 'M', 'MALE', 'LAKI-LAKI', 'PUTRA', 'PRIA'])) return ['label'=>'PUTRA', 'code'=>'M', 'color'=>'text-blue-600', 'bg'=>'bg-blue-50'];
    if(in_array($v, ['P', 'F', 'FEMALE', 'PEREMPUAN', 'PUTRI', 'WANITA'])) return ['label'=>'PUTRI', 'code'=>'F', 'color'=>'text-pink-600', 'bg'=>'bg-pink-50'];
    return ['label'=>'?', 'code'=>'?', 'color'=>'text-slate-400', 'bg'=>'bg-slate-50'];
}

// Include Layout
include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    .table-wrapper { position: relative; overflow: auto; max-height: 65vh; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
    thead th { position: sticky; top: 0; z-index: 20; }
    .sticky-col-1 { position: sticky; left: 0; z-index: 30; width: 60px; border-right: 1px solid #e2e8f0; }
    .sticky-col-2 { position: sticky; left: 60px; z-index: 29; width: 220px; border-right: 2px solid #cbd5e1; }
    
    tbody tr:nth-child(even) td { background-color: #f8fafc; }
    tbody tr:nth-child(odd) td { background-color: #ffffff; }
    tbody tr:nth-child(even) .sticky-col-1, tbody tr:nth-child(even) .sticky-col-2 { background-color: #f8fafc; }
    tbody tr:nth-child(odd) .sticky-col-1, tbody tr:nth-child(odd) .sticky-col-2 { background-color: #ffffff; }

    .custom-scrollbar::-webkit-scrollbar { height: 10px; width: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 5px; }
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-100 min-h-screen font-sans text-slate-800">
    
    <div class="bg-slate-900 text-white p-6 rounded-3xl shadow-xl flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-black uppercase italic tracking-wider"><?= htmlspecialchars($organizer['nama_lengkap']) ?></h1>
            <p class="text-blue-200 text-xs font-bold mt-1">📍 <?= htmlspecialchars($organizer['location']) ?></p>
        </div>
        <div class="flex gap-3 items-center">
             <a href="../dashboard.php" class="bg-white/10 hover:bg-white/20 px-4 py-3 rounded-xl text-xs font-bold transition flex items-center gap-2"><span>↩</span> Kembali</a>
            
             <?php if (!$isLocked): ?>
                 <a href="checkout.php?event_id=<?= $organizerId ?>" class="bg-emerald-500 hover:bg-emerald-600 px-6 py-3 rounded-xl text-xs font-black uppercase tracking-wider transition shadow-lg flex items-center gap-2 transform active:scale-95">
                    <span>🚀</span> Finalisasi Pendaftaran
                 </a>
             <?php else: ?>
                 <div class="bg-emerald-600/50 text-emerald-100 px-6 py-3 rounded-xl text-xs font-black uppercase tracking-wider flex items-center gap-2 cursor-not-allowed opacity-80 border border-emerald-500 shadow-none">
                    <span>🔒</span> Pendaftaran Disetujui
                 </div>
             <?php endif; ?>
        </div>
    </div>

    <?php if ($isLocked): ?>
        <div class="bg-blue-50 border border-blue-200 text-blue-800 px-6 py-4 rounded-2xl mb-6 flex items-center gap-3 shadow-sm">
            <span class="text-2xl">✅</span>
            <div>
                <h3 class="font-black text-sm uppercase tracking-wide">Mode Lihat Saja (Read Only)</h3>
                <p class="text-xs mt-1 text-blue-600">Admin telah memvalidasi (Approved) pendaftaran Anda untuk event ini. Anda tidak dapat mengubah data lagi.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-white border border-slate-300 rounded-3xl shadow-sm overflow-hidden mb-12 relative flex flex-col min-h-[400px]">
        
        <div class="p-4 border-b border-slate-200 bg-white flex justify-between items-center gap-4">
            <div class="flex items-center gap-3 w-full">
                
                <?php if (!$isLocked): ?>
                    <button onclick="document.getElementById('addSwimmerModal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-bold shadow-md transition flex items-center gap-2 whitespace-nowrap">
                        <span>+</span> Tambah Atlet
                    </button>
                <?php else: ?>
                    <button disabled class="bg-slate-100 text-slate-400 cursor-not-allowed px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-2 whitespace-nowrap border border-slate-200">
                        <span>🔒</span> Tambah Atlet
                    </button>
                <?php endif; ?>

                <input type="text" id="tableSearch" onkeyup="filterTable()" class="w-full md:w-64 px-4 py-2 text-xs font-bold border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none uppercase" placeholder="🔍 Filter Nama...">
            </div>
            <button onclick="location.reload()" class="text-slate-400 hover:text-blue-600 p-2 transition">🔄</button>
        </div>

        <?php if(empty($visibleSwimmers)): ?>
            <div class="flex-1 flex flex-col items-center justify-center p-16 text-center opacity-60">
                <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center text-4xl mb-4">🏊</div>
                <h3 class="font-bold text-slate-600 text-lg">List Atlet Kosong</h3>
                
                <?php if (!$isLocked): ?>
                    <p class="text-xs text-slate-500 max-w-xs mt-1">Belum ada atlet yang ditampilkan untuk event ini.</p>
                    <button onclick="document.getElementById('addSwimmerModal').classList.remove('hidden')" class="mt-4 text-blue-600 font-bold text-xs underline cursor-pointer">
                        + Klik di sini untuk memilih atlet
                    </button>
                <?php else: ?>
                    <p class="text-xs text-slate-500 max-w-xs mt-1">Anda tidak mendaftarkan atlet satupun pada event ini dan pendaftaran telah ditutup/disetujui.</p>
                <?php endif; ?>
            </div>

        <?php elseif(empty($categories)): ?>
             <div class="p-16 text-center bg-red-50 text-red-600"><h3 class="font-bold">Nomor Lomba Kosong</h3><p class="text-xs">Admin belum membuat nomor lomba.</p></div>
        
        <?php else: ?>
            <div class="table-wrapper custom-scrollbar">
                <table class="w-max text-sm text-left border-collapse" id="entryTable">
                    <thead class="text-xs text-slate-700 uppercase bg-slate-100 shadow-sm">
                        <tr>
                            <th class="sticky-col-1 bg-slate-200 p-2 text-center border-b border-slate-300">Edit</th>
                            <th class="sticky-col-2 bg-slate-200 p-2 pl-4 text-left border-b border-slate-300">Nama Atlet</th>
                            <?php foreach($headers as $dist => $styles): ?>
                                <th class="text-center border-l border-slate-300 p-2 font-black border-b border-slate-300 bg-blue-50 text-blue-800" colspan="<?= count($styles) ?>"><?= $dist ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th class="sticky-col-1 top-[41px] bg-slate-100 border-b border-slate-300 h-10"></th>
                            <th class="sticky-col-2 top-[41px] bg-slate-100 border-b border-slate-300 h-10"></th>
                            <?php foreach($headers as $dist => $styles): foreach($styles as $style => $v): ?>
                                <th class="text-center border-l border-slate-200 min-w-[90px] px-2 py-1 text-[9px] bg-white text-slate-500 border-b border-slate-300 top-[41px]"><?= $style ?></th>
                            <?php endforeach; endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($visibleSwimmers as $s): 
                            $genderData = getGenderLabel($s['jenis_kelamin']);
                            $age = (!empty($s['tanggal_lahir']) && $s['tanggal_lahir'] != '0000-00-00') ? (date('Y') - date('Y', strtotime($s['tanggal_lahir']))) . ' TH' : '-';
                        ?>
                        <tr class="h-12 border-b border-slate-100 transition-colors swimmer-row hover:bg-blue-50">
                            
                            <td class="sticky-col-1 text-center border-r border-slate-200">
                                <?php if (!$isLocked): ?>
                                    <button onclick="openEntryModal(<?= $s['id'] ?>)" class="text-orange-500 hover:scale-110 transition p-2">✏️</button>
                                <?php else: ?>
                                    <span class="text-slate-300 cursor-not-allowed select-none p-2" title="Terkunci">🔒</span>
                                <?php endif; ?>
                            </td>

                            <td class="sticky-col-2 px-4 border-r border-slate-300 align-middle">
                                <div class="font-bold text-slate-800 text-[11px] uppercase truncate w-[180px]"><?= htmlspecialchars($s['nama_atlet']) ?></div>
                                <div class="text-[9px] text-slate-500 font-semibold flex gap-1 items-center mt-0.5">
                                    <span class="<?= $genderData['color'] ?>"><?= $genderData['label'] ?></span>
                                    <span class="text-slate-300">•</span>
                                    <span><?= $age ?></span>
                                </div>
                            </td>
                            <?php foreach($headers as $dist => $styles): foreach($styles as $style => $v): 
                                $matchId = null;
                                foreach($categories as $cat) {
                                    if($cat['parsed_distance'].'m' == $dist && $cat['parsed_style'] == $style) {
                                        if($cat['normalized_gender'] == 'MIXED' || $cat['normalized_gender'] == $genderData['code']) {
                                            $matchId = $cat['id']; break;
                                        }
                                    }
                                }
                                $val = ($matchId && isset($saved[$s['id']][$matchId])) ? $saved[$s['id']][$matchId] : '';
                            ?>
                                <td class="border-l border-slate-100 text-center relative p-0 align-middle">
                                    <?php if($matchId): ?>
                                        <?php if($val): ?>
                                            <div class="flex items-center justify-center h-full w-full"><span class="px-2 py-0.5 rounded bg-blue-600 text-white text-[10px] font-bold shadow-sm"><?= $val ?></span></div>
                                        <?php else: ?>
                                            <span class="text-slate-300 text-[8px] font-bold">-</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="w-full h-full bg-slate-100 flex items-center justify-center cursor-not-allowed"><span class="text-slate-200 text-[8px]">✕</span></div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="entryModal" class="fixed inset-0 z-[100] hidden bg-slate-900/50 backdrop-blur-sm flex justify-center items-center p-4"><div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden" id="modalContent"></div><div class="absolute inset-0 -z-10" onclick="closeEntryModal()"></div></div>

<div id="addSwimmerModal" class="fixed inset-0 z-[100] hidden bg-slate-900/50 backdrop-blur-sm flex justify-center items-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[80vh]">
        <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-700">Pilih Atlet</h3>
            <button onclick="document.getElementById('addSwimmerModal').classList.add('hidden')" class="text-slate-400 hover:text-red-500 font-bold">✕</button>
        </div>
        <div class="p-4 overflow-y-auto custom-scrollbar space-y-2 flex-1">
            <input type="text" onkeyup="filterSwimmerList(this)" placeholder="Cari nama..." class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs mb-3 font-bold uppercase">
            
            <?php foreach($allSwimmers as $sw): 
                if(in_array($sw['id'], $visibleSwimmerIds)) continue; 
            ?>
            
            <?php if (!$isLocked): ?>
                <a href="register_event.php?event_id=<?= $organizerId ?>&add_swimmer=<?= $sw['id'] ?>" class="swimmer-item block p-3 rounded-xl border border-slate-100 hover:bg-blue-50 hover:border-blue-200 transition group">
                    <div class="font-bold text-slate-700 text-sm group-hover:text-blue-700 uppercase"><?= htmlspecialchars($sw['nama_atlet']) ?></div>
                    <div class="text-[10px] text-slate-400 font-mono"><?= $sw['jenis_kelamin'] == 'L' ? 'Putra' : 'Putri' ?> • <?= $sw['tanggal_lahir'] ?></div>
                </a>
            <?php else: ?>
                 <div class="swimmer-item block p-3 rounded-xl border border-slate-100 bg-slate-50 opacity-60 cursor-not-allowed">
                    <div class="font-bold text-slate-500 text-sm uppercase"><?= htmlspecialchars($sw['nama_atlet']) ?> (Terkunci)</div>
                 </div>
            <?php endif; ?>

            <?php endforeach; ?>
            
            <?php if(count($visibleSwimmerIds) == count($allSwimmers)): ?>
                <p class="text-center text-xs text-slate-400 py-4">Semua atlet sudah dimasukkan.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="absolute inset-0 -z-10" onclick="document.getElementById('addSwimmerModal').classList.add('hidden')"></div>
</div>

<script>
function filterTable() {
    const filter = document.getElementById("tableSearch").value.toUpperCase();
    document.querySelectorAll("#entryTable .swimmer-row").forEach(row => {
        const txt = row.querySelector(".sticky-col-2 div").textContent;
        row.style.display = txt.toUpperCase().includes(filter) ? "" : "none";
    });
}
function filterSwimmerList(input) {
    const filter = input.value.toUpperCase();
    document.querySelectorAll(".swimmer-item").forEach(item => {
        const txt = item.innerText;
        item.style.display = txt.toUpperCase().includes(filter) ? "block" : "none";
    });
}
function openEntryModal(id) {
    // SECURITY JAVASCRIPT: Mencegah modal terbuka paksa
    <?php if ($isLocked): ?>
        alert("Pendaftaran event ini sudah disetujui Admin. Anda tidak bisa mengedit data.");
        return; 
    <?php endif; ?>

    document.getElementById('entryModal').classList.remove('hidden');
    document.getElementById('modalContent').innerHTML = '<div class="p-10 text-center text-slate-500 text-sm font-bold">Mengambil data...</div>';
    fetch(`edit_entry.php?event_id=<?= $organizerId ?>&swimmer_id=${id}`).then(r=>r.text()).then(h=>{
        document.getElementById('modalContent').innerHTML=h;
        document.getElementById('modalContent').querySelectorAll("script").forEach(s=>eval(s.textContent));
    });
}
function closeEntryModal() { document.getElementById('entryModal').classList.add('hidden'); }
</script>