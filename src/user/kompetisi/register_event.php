<?php
// FILE: src/user/kompetisi/register_event.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// --- 1. CEK LOGIN & AMBIL ID ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$targetEventId = (int)($_GET['event_id'] ?? 0); 

if ($targetEventId == 0) { die("Error: ID Event tidak valid."); }

// --- 2. AMBIL DATA EVENT & STATUS PEMBAYARAN (LOCK SYSTEM) ---
$stmtEvt = $pdo->prepare("SELECT * FROM events WHERE id = ? LIMIT 1"); 
$stmtEvt->execute([$targetEventId]);
$eventData = $stmtEvt->fetch();

if (!$eventData) { die("Data Event tidak ditemukan."); }

$namaEventDisplay = $eventData['nama_event'];
$calcType = $eventData['age_calculation_type'] ?? 'Dec 31'; 
$startDate = $eventData['event_start_date'] ?? date('Y-m-d');
$compYear = (int)date('Y', strtotime($startDate));
$compDateObj = new DateTime($startDate);

$stmtPay = $pdo->prepare("SELECT status FROM payments WHERE user_id = ? AND event_id = ? ORDER BY created_at DESC LIMIT 1");
$stmtPay->execute([$uid, $targetEventId]);
$payStatus = $stmtPay->fetchColumn(); 

$isLocked = ($payStatus === 'Pending' || $payStatus === 'Paid');
$lockMessage = ($payStatus === 'Paid') ? 'Pendaftaran sudah DISETUJUI Admin. Data terkunci.' : 'Menunggu Verifikasi Admin. Data terkunci sementara.';

// --- 3. HELPER: HITUNG UMUR ---
function hitungUmur($tglLahir, $calcType, $compYear, $compDateObj) {
    if (empty($tglLahir)) return 0;
    $dobObj = new DateTime($tglLahir);
    $birthYear = (int)$dobObj->format('Y');
    return ($calcType === 'Meet Start') ? $dobObj->diff($compDateObj)->y : ($compYear - $birthYear);
}

// --- 4. HANDLE POST (SIMPAN DATA) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_entries') {
    if ($isLocked) { die("AKSES DITOLAK: Pendaftaran sedang dikunci."); }

    try {
        $swimmerId = $_POST['swimmer_id'];
        $entries   = $_POST['entries'] ?? [];
        
        $stmtCekSw = $pdo->prepare("SELECT id FROM swimmers WHERE id = ? AND user_id = ?");
        $stmtCekSw->execute([$swimmerId, $uid]);
        if (!$stmtCekSw->fetch()) { die("Error: Atlet tidak valid."); }

        $stmtC = $pdo->prepare("SELECT id FROM clubs WHERE user_id = ? LIMIT 1");
        $stmtC->execute([$uid]);
        $clubRow = $stmtC->fetch();
        $clubId = $clubRow['id'] ?? $uid; 

        $stmtValidCats = $pdo->prepare("SELECT id FROM event_numbers WHERE organizer_id = ?"); 
        $stmtValidCats->execute([$targetEventId]);
        $validCategoryIds = $stmtValidCats->fetchAll(PDO::FETCH_COLUMN);

        $pdo->beginTransaction(); 
        foreach ($entries as $catId => $time) {
            $catId = (int)$catId;
            $time = trim($time);
            if (!in_array($catId, $validCategoryIds)) continue;
            
            $stmtCek = $pdo->prepare("SELECT id FROM event_entries WHERE user_id=? AND event_id=? AND swimmer_id=? AND category_id=?");
            $stmtCek->execute([$uid, $targetEventId, $swimmerId, $catId]);
            $exist = $stmtCek->fetch();

            if ($time === '' || $time === '00.00.00' || $time === 'DELETE') {
                if ($exist) { $pdo->prepare("DELETE FROM event_entries WHERE id=?")->execute([$exist['id']]); }
            } else {
                if ($exist) {
                    $pdo->prepare("UPDATE event_entries SET entry_time=?, club_id=? WHERE id=?")
                        ->execute([$time, $clubId, $exist['id']]);
                } else {
                    $pdo->prepare("INSERT INTO event_entries (user_id, event_id, club_id, swimmer_id, category_id, entry_time, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())")
                        ->execute([$uid, $targetEventId, $clubId, $swimmerId, $catId, $time]);
                }
            }
        }
        $pdo->commit();
        header("Location: register_event.php?event_id=" . $targetEventId); exit;
    } catch (Exception $e) { if($pdo->inTransaction()) $pdo->rollBack(); die("Gagal: " . $e->getMessage()); }
}

// --- 5. DATA FETCHING ---
$stmtGroups = $pdo->prepare("SELECT id, min_age, max_age, group_name FROM event_age_groups WHERE event_id = ?");
$stmtGroups->execute([$targetEventId]);
$ageRules = $stmtGroups->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);

$stmtEn = $pdo->prepare("SELECT * FROM event_numbers WHERE organizer_id = ? ORDER BY distance ASC, stroke ASC");
$stmtEn->execute([$targetEventId]);
$allEvents = $stmtEn->fetchAll(PDO::FETCH_ASSOC);

$stmtSw = $pdo->prepare("SELECT * FROM swimmers WHERE user_id = ? ORDER BY nama_atlet ASC");
$stmtSw->execute([$uid]);
$allSwimmers = $stmtSw->fetchAll(PDO::FETCH_ASSOC);

if (!isset($_SESSION['matrix_list'][$targetEventId])) $_SESSION['matrix_list'][$targetEventId] = [];
$stmtSync = $pdo->prepare("SELECT DISTINCT swimmer_id FROM event_entries WHERE user_id = ? AND event_id = ?");
$stmtSync->execute([$uid, $targetEventId]);
$registeredSwimmers = $stmtSync->fetchAll(PDO::FETCH_COLUMN);

foreach ($registeredSwimmers as $regId) {
    if (!in_array($regId, $_SESSION['matrix_list'][$targetEventId])) {
        $_SESSION['matrix_list'][$targetEventId][] = (int)$regId;
    }
}

if (isset($_GET['add_swimmer'])) {
    if ($isLocked) { header("Location: register_event.php?event_id=$targetEventId"); exit; } 
    $addId = (int)$_GET['add_swimmer'];
    $validSw = false; foreach($allSwimmers as $s) { if($s['id'] == $addId) $validSw = true; }
    if ($validSw && !in_array($addId, $_SESSION['matrix_list'][$targetEventId])) { $_SESSION['matrix_list'][$targetEventId][] = $addId; }
    header("Location: register_event.php?event_id=$targetEventId"); exit;
}
$visibleSwimmers = array_filter($allSwimmers, fn($s) => in_array($s['id'], $_SESSION['matrix_list'][$targetEventId] ?? []));

// AMBIL DATA YANG SUDAH TERDAFTAR (UNTUK MATRIX)
$savedData = [];
$stmtEnt = $pdo->prepare("SELECT swimmer_id, category_id, entry_time FROM event_entries WHERE user_id = ? AND event_id = ?");
$stmtEnt->execute([$uid, $targetEventId]);
while($row = $stmtEnt->fetch(PDO::FETCH_ASSOC)) { $savedData[$row['swimmer_id']][$row['category_id']] = $row['entry_time']; }

$recordMap = [];
if (!empty($visibleSwimmers)) {
    $swIds = array_column($visibleSwimmers, 'id');
    $p = implode(',', array_fill(0, count($swIds), '?'));
    $stmtRec = $pdo->prepare("SELECT swimmer_id, nomor_lomba, waktu_terbaik FROM athlete_records WHERE swimmer_id IN ($p)");
    $stmtRec->execute($swIds);
    while($rec = $stmtRec->fetch(PDO::FETCH_ASSOC)) {
        if (preg_match('/^(\d+)m\s+(.+)$/i', $rec['nomor_lomba'], $m)) {
            $recordMap[$rec['swimmer_id']][(int)$m[1]][strtoupper(str_replace(['GAYA ', 'Gaya '], '', $m[2]))] = str_replace(':', '.', $rec['waktu_terbaik']);
        }
    }
}

// --- 6. LOGIKA FILTERING & STRUKTUR TABEL ---
$tableStructure = []; 
foreach ($allEvents as $ev) { $tableStructure[$ev['distance'] . 'M'][$ev['stroke']][] = $ev; }
uksort($tableStructure, fn($a, $b) => (int)$a - (int)$b);

$jsonData = [];
foreach ($visibleSwimmers as $sw) {
    $sid = $sw['id'];
    $age = hitungUmur($sw['tanggal_lahir'], $calcType, $compYear, $compDateObj);
    $gender = ($sw['jenis_kelamin'] == 'L') ? 'L' : 'P';
    $myEvents = [];

    foreach ($allEvents as $ev) {
        $jarak = (int)$ev['distance'];
        $eGen = (in_array($ev['jenis_kelamin'], ['Putra', 'L'])) ? 'L' : ((in_array($ev['jenis_kelamin'], ['Putri', 'P'])) ? 'P' : 'MIX');
        if ($eGen !== 'MIX' && $eGen !== $gender) continue;
        if (($age <= 7 && $jarak >= 100) || ($age <= 9 && $jarak >= 200)) continue;

        $isAgeFit = false;
        $kuIds = !empty($ev['selected_ku_ids']) ? explode(',', $ev['selected_ku_ids']) : [];
        if (!empty($kuIds)) {
            foreach ($kuIds as $kid) { if (isset($ageRules[$kid]) && $age >= (int)$ageRules[$kid]['min_age'] && $age <= (int)$ageRules[$kid]['max_age']) { $isAgeFit = true; break; } }
        } else {
            $min = (int)($ev['age_min'] ?? 0); $max = (int)($ev['age_max'] ?? 99);
            if ($age >= $min && ($max == 0 || $age <= $max)) $isAgeFit = true;
        }
        if (!$isAgeFit) continue; 

        $normS = strtoupper(str_replace(['Gaya ', 'GAYA '], '', $ev['stroke']));
        $myEvents[] = [
            'id' => $ev['id'], 'name' => "{$ev['distance']}M " . $normS, 'group' => $ev['age_group'],
            'time' => $savedData[$sid][$ev['id']] ?? '', 'best_time' => $recordMap[$sid][$ev['distance']][$normS] ?? null
        ];
    }
    $jsonData[$sid] = ['name' => $sw['nama_atlet'], 'info' => ($gender == 'L' ? 'PUTRA' : 'PUTRI') . " - " . date('Y', strtotime($sw['tanggal_lahir'])) . " ($age Th)", 'events' => $myEvents];
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    .matrix-container { max-height: 70vh; overflow: auto; border-radius: 15px; border: 1px solid #e2e8f0; background: white; }
    .sticky-top-1 { position: sticky; top: 0; z-index: 20; background: #f8fafc; }
    .sticky-top-2 { position: sticky; top: 38px; z-index: 20; background: #fff; border-bottom: 2px solid #e2e8f0; }
    .sticky-col-1 { position: sticky; left: 0; z-index: 30; background: #f8fafc; border-right: 1px solid #e2e8f0; }
    .sticky-col-2 { position: sticky; left: 40px; z-index: 30; background: #fff; border-right: 2px solid #cbd5e1; min-width: 180px; }
    .cell-blocked { background: #f8fafc; cursor: not-allowed; } 
    .cell-empty { background: #fff; cursor: pointer; transition: background 0.2s; } 
    .cell-empty:hover { background: #eff6ff; }
    .cell-filled { background: #dcfce7 !important; color: #166534; font-weight: bold; cursor: pointer; border: 1px solid #bbf7d0; }
</style>

<div class="p-4 sm:ml-64 pt-20 bg-slate-50 min-h-screen">
    <div class="flex justify-between items-center mb-6 bg-white p-6 rounded-2xl shadow-sm border">
        <div>
            <h1 class="text-2xl font-black text-slate-800 uppercase italic leading-none">Matrix Pendaftaran</h1>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[3px] mt-2"><?= htmlspecialchars($namaEventDisplay) ?></p>
        </div>
        <div class="flex gap-3">
            <?php if ($isLocked): ?>
                <div class="bg-red-100 border border-red-200 text-red-700 px-4 py-2 rounded-xl text-xs font-bold flex items-center gap-2"><span>🔒</span> <?= $lockMessage ?></div>
                <a href="checkout.php?event_id=<?= $targetEventId ?>" class="bg-slate-900 text-white px-6 py-3 rounded-xl font-bold text-xs shadow-lg">LIHAT STATUS</a>
            <?php else: ?>
                <button onclick="document.getElementById('modalAdd').classList.remove('hidden')" class="bg-blue-600 text-white px-6 py-3 rounded-xl font-bold text-xs shadow-lg hover:bg-blue-700">+ ATLET</button>
                <a href="checkout.php?event_id=<?= $targetEventId ?>" class="bg-slate-900 text-white px-6 py-3 rounded-xl font-bold text-xs shadow-lg">SELESAI / BAYAR</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="matrix-container shadow-2xl relative">
        <table class="w-full text-left border-collapse text-[11px]">
            <thead>
                <tr>
                    <th class="sticky-top-1 sticky-col-1 p-3 text-center text-slate-400 font-bold">#</th>
                    <th class="sticky-top-1 sticky-col-2 p-3 font-bold text-slate-700">NAMA ATLET</th>
                    <?php foreach($tableStructure as $dist => $strokes): ?>
                        <th colspan="<?= count($strokes) ?>" class="sticky-top-1 text-center py-2 border-l border-slate-200 bg-slate-100 font-black text-slate-600 uppercase italic"><?= $dist ?></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <th class="sticky-top-2 sticky-col-1 bg-white"></th>
                    <th class="sticky-top-2 sticky-col-2 bg-white"></th>
                    <?php foreach($tableStructure as $d => $ss): foreach($ss as $sName => $evs): ?>
                        <th class="sticky-top-2 text-center py-2 px-1 border-l border-slate-100 text-slate-400 font-bold min-w-[70px] uppercase"><?= str_replace(['Gaya ', 'GAYA '], '', $sName) ?></th>
                    <?php endforeach; endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($visibleSwimmers as $sw): $sid = $sw['id']; ?>
                <tr class="group">
                    <td class="sticky-col-1 bg-white border-r text-center py-4">
                        <button onclick="<?= $isLocked ? "alert('Terkunci')" : "openModal($sid)" ?>" class="hover:scale-125 transition-transform"><?= $isLocked ? '🔒' : '✏️' ?></button>
                    </td>
                    <td onclick="<?= $isLocked ? "alert('Terkunci')" : "openModal($sid)" ?>" class="sticky-col-2 bg-white border-r px-4 py-4 cursor-pointer">
                        <div class="font-bold text-slate-800 uppercase"><?= $sw['nama_atlet'] ?></div>
                        <div class="text-[9px] text-slate-400 font-bold uppercase"><?= $jsonData[$sid]['info'] ?></div>
                    </td>
                    <?php foreach($tableStructure as $d => $ss): foreach($ss as $sName => $evs): 
                        $time = ''; $eligible = false;
                        foreach($evs as $e) {
                            if(isset($savedData[$sid][$e['id']])) { $time = $savedData[$sid][$e['id']]; $eligible = true; break; }
                            foreach($jsonData[$sid]['events'] as $me) { if($me['id'] == $e['id']) { $eligible = true; break; } }
                        }
                        $css = $eligible ? ($time ? 'cell-filled' : 'cell-empty') : 'cell-blocked';
                    ?>
                        <td onclick="<?= ($eligible && !$isLocked) ? "openModal($sid)" : "" ?>" class="border-l border-slate-50 text-center h-12 transition-all <?= $css ?>">
                            <?= htmlspecialchars($time) ?>
                        </td>
                    <?php endforeach; endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalEntry" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden flex flex-col max-h-[90vh]">
        <div class="bg-slate-800 p-6 text-white flex justify-between items-center">
            <div><h2 class="text-xl font-black italic uppercase tracking-tighter" id="mName">ATLET</h2><p class="text-[10px] font-bold text-blue-400 uppercase mt-1" id="mInfo">INFO</p></div>
            <button onclick="closeModal()" class="text-3xl hover:text-red-400">&times;</button>
        </div>
        <form method="POST" action="register_event.php?event_id=<?= $targetEventId ?>" class="flex flex-col flex-1 overflow-hidden">
            <input type="hidden" name="action" value="save_entries">
            <input type="hidden" name="swimmer_id" id="mSwimmerId">
            <div class="flex-1 overflow-y-auto p-6 space-y-4 bg-slate-50" id="mBody"></div>
            <?php if(!$isLocked): ?>
            <div class="p-4 bg-white border-t space-y-2 shadow-inner">
                <button type="button" onclick="fillAllBestTimes()" class="w-full text-[10px] font-bold text-blue-600 bg-blue-50 py-2 rounded-xl border border-blue-200 hover:bg-blue-100">⚡ ISI SEMUA BEST TIME</button>
                <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-xl font-black text-xs shadow-xl hover:bg-blue-700">SIMPAN PERUBAHAN</button>
            </div>
            <?php else: ?><div class="p-4 bg-red-50 text-center font-bold text-red-500 text-xs">🔒 DATA TERKUNCI</div><?php endif; ?>
        </form>
    </div>
</div>

<div id="modalAdd" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl p-6 w-80 shadow-2xl text-center">
        <h3 class="font-black text-slate-800 mb-4 border-b pb-2 uppercase italic">Pilih Atlet</h3>
        <div class="max-h-60 overflow-y-auto space-y-1">
            <?php foreach($allSwimmers as $sw): if(in_array($sw['id'], $_SESSION['matrix_list'][$targetEventId] ?? [])) continue; ?>
                <a href="?event_id=<?= $targetEventId ?>&add_swimmer=<?= $sw['id'] ?>" class="block p-3 hover:bg-blue-50 rounded-xl font-bold text-slate-600 text-sm uppercase"><?= $sw['nama_atlet'] ?></a>
            <?php endforeach; ?>
        </div>
        <button onclick="document.getElementById('modalAdd').classList.add('hidden')" class="mt-4 text-slate-400 font-bold text-[10px] uppercase">Tutup</button>
    </div>
</div>

<script>
const DATA = <?= json_encode($jsonData) ?>;
const IS_LOCKED = <?= json_encode($isLocked) ?>;
let currentSwimmerData = null; 

function openModal(sid) {
    if (IS_LOCKED) return;
    const s = DATA[sid]; if(!s) return;
    currentSwimmerData = s.events; 
    document.getElementById('mName').innerText = s.name;
    document.getElementById('mInfo').innerText = s.info;
    document.getElementById('mSwimmerId').value = sid; 
    const body = document.getElementById('mBody');
    body.innerHTML = s.events.length === 0 ? '<div class="text-center py-10 text-slate-400 text-xs font-bold">Tidak ada nomor lomba.</div>' : '';

    s.events.forEach(ev => {
        let btnRec = ev.best_time ? `<button type="button" onclick="copyTime('${ev.best_time}', '${ev.id}')" class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg text-[10px] font-bold">📋 REKOR: ${ev.best_time}</button>` : `<span class="text-[10px] text-slate-300 font-bold italic">Belum ada rekor</span>`;
        body.insertAdjacentHTML('beforeend', `
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex justify-between items-start mb-2"><div><div class="font-black text-slate-800 text-sm italic uppercase">${ev.name}</div><div class="text-[10px] font-bold text-blue-500 uppercase">${ev.group}</div></div>${btnRec}</div>
                <input type="text" id="input_${ev.id}" name="entries[${ev.id}]" value="${ev.time}" placeholder="00.00.00" maxlength="8" oninput="handleTimeInput(this)" class="w-full text-center font-mono font-bold text-2xl bg-slate-50 border rounded-xl py-3 shadow-inner">
            </div>
        `);
    });
    document.getElementById('modalEntry').classList.remove('hidden');
}

function handleTimeInput(el) {
    let v = el.value.replace(/\D/g, '').substring(0, 6);
    let f = ""; if (v.length > 0) f += v.substring(0, 2); if (v.length > 2) f += "." + v.substring(2, 4); if (v.length > 4) f += "." + v.substring(4, 6);
    el.value = f;
}

function copyTime(t, id) { const el = document.getElementById('input_' + id); if(el) el.value = t; }
function fillAllBestTimes() {
    if(!currentSwimmerData) return;
    currentSwimmerData.forEach(ev => { if(ev.best_time) { const el = document.getElementById('input_' + ev.id); if(el && el.value === '') el.value = ev.best_time; } });
}
function closeModal() { document.getElementById('modalEntry').classList.add('hidden'); }
</script>