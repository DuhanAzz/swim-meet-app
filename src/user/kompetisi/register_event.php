<?php
// FILE: src/user/kompetisi/register_event.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// --- 1. KONFIGURASI TAHUN ---
// Sebaiknya ini dinamis dari tahun pelaksanaan event, namun sementara kita pakai 2025 sesuai kode Anda
$competitionYear = 2025; 

// --- 2. HELPER: ATURAN TEKNIS (MATRIX) ---
function cekAturanMatrix($tahunLahir, $jarak, $gaya) {
    $gaya = strtoupper($gaya); 
    $gaya = str_replace(['GAYA ', 'KICK '], '', $gaya); 
    $jarak = (int)$jarak;
    $tahunLahir = (int)$tahunLahir;
    
    $isPapan = (preg_match('/PAPAN|KICK|KAKI/', $gaya) || strpos($gaya, 'KICK') !== false);

    if ($tahunLahir >= 2018) { // KU 2018 & 2019
        if ($jarak >= 100) return false; 
        return true; 
    }
    if ($tahunLahir == 2016 || $tahunLahir == 2017) {
        if ($isPapan) return false;
        if ($jarak == 25 || $jarak == 50) return true;
        return ($jarak == 100 && strpos($gaya, 'BEBAS') !== false);
    }
    if ($tahunLahir <= 2015) { // KU 2015, KU 3, KU 2+
        if ($isPapan || $jarak == 25) return false;
        return true; 
    }
    return true;
}

// --- 3. AUTH & POST HANDLER ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}
$uid = $_SESSION['user_id'];
$organizerId = $_GET['event_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_entries') {
    $swimmerId = $_POST['swimmer_id'];
    $entries   = $_POST['entries'] ?? [];
    $stmtC = $pdo->prepare("SELECT id FROM clubs WHERE user_id = ? LIMIT 1");
    $stmtC->execute([$uid]);
    $clubId = $stmtC->fetch()['id'] ?? 0;

    foreach ($entries as $eventId => $time) {
        $time = trim($time);
        $stmtCek = $pdo->prepare("SELECT id FROM event_entries WHERE user_id=? AND event_id=? AND swimmer_id=? AND category_id=?");
        $stmtCek->execute([$uid, $organizerId, $swimmerId, $eventId]);
        $exist = $stmtCek->fetch();

        if ($time === '' || $time === '00.00.00' || $time === '00:00.00') {
            if ($exist) $pdo->prepare("DELETE FROM event_entries WHERE id=?")->execute([$exist['id']]);
        } else {
            if ($exist) {
                $pdo->prepare("UPDATE event_entries SET entry_time=? WHERE id=?")->execute([$time, $exist['id']]);
            } else {
                $pdo->prepare("INSERT INTO event_entries (user_id, event_id, club_id, swimmer_id, category_id, entry_time) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$uid, $organizerId, $clubId, $swimmerId, $eventId, $time]);
            }
        }
    }
    header("Location: register_event.php?event_id=" . $organizerId); exit;
}

// --- 4. DATA FETCHING ---
// Ambil aturan KU dari database
$stmtGroups = $pdo->prepare("SELECT id, min_age, max_age, group_name FROM event_age_groups WHERE event_id = ?");
$stmtGroups->execute([$organizerId]);
$ageRules = $stmtGroups->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);

// Ambil semua nomor lomba
$stmtEn = $pdo->prepare("SELECT * FROM event_numbers WHERE organizer_id = ? ORDER BY distance ASC, stroke ASC");
$stmtEn->execute([$organizerId]);
$allEvents = $stmtEn->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua atlet milik user
$stmtSw = $pdo->prepare("SELECT *, YEAR(tanggal_lahir) as birth_year FROM swimmers WHERE user_id = ? ORDER BY nama_atlet ASC");
$stmtSw->execute([$uid]);
$allSwimmers = $stmtSw->fetchAll(PDO::FETCH_ASSOC);

if (!isset($_SESSION['matrix_list'][$organizerId])) $_SESSION['matrix_list'][$organizerId] = [];
if (isset($_GET['add_swimmer'])) {
    if (!in_array($_GET['add_swimmer'], $_SESSION['matrix_list'][$organizerId])) $_SESSION['matrix_list'][$organizerId][] = (int)$_GET['add_swimmer'];
    header("Location: register_event.php?event_id=$organizerId"); exit;
}
$visibleSwimmers = array_filter($allSwimmers, fn($s) => in_array($s['id'], $_SESSION['matrix_list'][$organizerId]));

$savedData = [];
$stmtEnt = $pdo->prepare("SELECT swimmer_id, category_id, entry_time FROM event_entries WHERE user_id = ? AND event_id = ?");
$stmtEnt->execute([$uid, $organizerId]);
while($row = $stmtEnt->fetch(PDO::FETCH_ASSOC)) $savedData[$row['swimmer_id']][$row['category_id']] = $row['entry_time'];

// Data Rekor
$recordMap = [];
if (!empty($visibleSwimmers)) {
    $swimmerIds = array_column($visibleSwimmers, 'id');
    $placeholders = implode(',', array_fill(0, count($swimmerIds), '?'));
    $stmtRec = $pdo->prepare("SELECT swimmer_id, distance, stroke, time_record FROM swimmer_records WHERE swimmer_id IN ($placeholders)");
    $stmtRec->execute($swimmerIds);
    while($rec = $stmtRec->fetch(PDO::FETCH_ASSOC)) {
        $normStroke = strtoupper(str_replace(['Gaya ', 'GAYA '], '', $rec['stroke']));
        $recordMap[$rec['swimmer_id']][$rec['distance']][$normStroke] = $rec['time_record'];
    }
}

// --- 5. STRUKTUR TABLE & FILTERING JSON ---
$tableStructure = []; 
foreach ($allEvents as $ev) { 
    $tableStructure[$ev['distance'] . 'M'][$ev['stroke']][] = $ev; 
}
uksort($tableStructure, fn($a, $b) => (int)$a - (int)$b);

$jsonData = [];
foreach ($visibleSwimmers as $sw) {
    $sid = $sw['id'];
    $birthYear = (int)$sw['birth_year'];
    $age = $competitionYear - $birthYear; 
    $gender = ($sw['jenis_kelamin'] == 'L') ? 'L' : 'P';
    $myEvents = [];

    foreach ($allEvents as $ev) {
        // 1. Cek Gender
        $eGen = ($ev['jenis_kelamin'] == 'Putra' || $ev['jenis_kelamin'] == 'L') ? 'L' : (($ev['jenis_kelamin'] == 'Putri' || $ev['jenis_kelamin'] == 'P') ? 'P' : 'MIX');
        if ($eGen !== 'MIX' && $eGen !== $gender) continue;

        // 2. Cek Kelompok Umur dari Database
        $isAgeFit = false;
        $kuIds = !empty($ev['selected_ku_ids']) ? explode(',', $ev['selected_ku_ids']) : [];
        
        if (!empty($kuIds)) {
            foreach ($kuIds as $kid) {
                if (isset($ageRules[$kid])) {
                    $min = (int)$ageRules[$kid]['min_age'];
                    $max = (int)$ageRules[$kid]['max_age'];
                    if ($age >= $min && $age <= $max) { $isAgeFit = true; break; }
                }
            }
        } else {
            // Jika tidak ada KU terpilih, cek manual age_min/max di tabel event_numbers
            if ($age >= $ev['age_min'] && $age <= $ev['age_max']) $isAgeFit = true;
        }
        
        if (!$isAgeFit) continue; 

        // 3. Cek Aturan Matrix Teknis
        if (cekAturanMatrix($birthYear, $ev['distance'], $ev['stroke'])) {
            $normStroke = strtoupper(str_replace(['Gaya ', 'GAYA '], '', $ev['stroke']));
            $best = $recordMap[$sid][$ev['distance']][$normStroke] ?? null;
            
            $myEvents[] = [
                'id' => $ev['id'],
                'name' => "{$ev['distance']}M " . $normStroke,
                'group' => $ev['age_group'],
                'time' => $savedData[$sid][$ev['id']] ?? '',
                'best_time' => $best
            ];
        }
    }
    $jsonData[$sid] = [
        'name' => $sw['nama_atlet'],
        'info' => ($gender == 'L' ? 'PUTRA' : 'PUTRI') . " - $birthYear ($age Th)",
        'events' => $myEvents
    ];
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
    .cell-blocked { background: #f1f5f9; cursor: not-allowed; opacity: 0.5; color: #cbd5e1; }
    .cell-empty { background: #fff; cursor: pointer; color: #3b82f6; font-weight: bold; }
    .cell-filled { background: #dcfce7 !important; color: #166534; font-weight: bold; cursor: pointer; border: 1px solid #bbf7d0; }
    .best-time-badge { background: #ecfdf5; color: #059669; border: 1px solid #10b981; padding: 2px 6px; border-radius: 6px; font-size: 9px; cursor: copy; }
</style>

<div class="p-4 sm:ml-64 pt-20 bg-slate-50 min-h-screen">
    <div class="flex justify-between items-center mb-6 bg-white p-6 rounded-2xl shadow-sm border">
        <div>
            <h1 class="text-2xl font-black text-slate-800 uppercase italic leading-none">Matrix Pendaftaran</h1>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[3px] mt-2">Filter Otomatis: Kelompok Umur <?= $competitionYear ?></p>
        </div>
        <div class="flex gap-3">
            <button onclick="document.getElementById('modalAdd').classList.remove('hidden')" class="bg-blue-600 text-white px-6 py-3 rounded-xl font-bold text-xs shadow-lg shadow-blue-100 hover:bg-blue-700">+ ATLET</button>
            <a href="checkout.php?event_id=<?= $organizerId ?>" class="bg-slate-900 text-white px-6 py-3 rounded-xl font-bold text-xs shadow-lg">SELESAI</a>
        </div>
    </div>

    <div class="matrix-container shadow-2xl">
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
                <tr class="hover:bg-blue-50/50 group transition-colors">
                    <td class="sticky-col-1 bg-white border-r text-center py-4"><button onclick="openModal(<?= $sid ?>)" class="hover:scale-125 transition-transform">✏️</button></td>
                    <td onclick="openModal(<?= $sid ?>)" class="sticky-col-2 bg-white border-r px-4 py-4 cursor-pointer">
                        <div class="font-bold text-slate-800 group-hover:text-blue-600 uppercase"><?= $sw['nama_atlet'] ?></div>
                        <div class="text-[9px] text-slate-400 font-bold uppercase"><?= $jsonData[$sid]['info'] ?></div>
                    </td>
                    <?php foreach($tableStructure as $d => $ss): foreach($ss as $sName => $evs): 
                        $isEligible = false; 
                        $time = '';
                        // Cek apakah ada nomor lomba di kolom ini yang cocok dengan atlet ini
                        foreach($evs as $eRef) {
                            foreach($jsonData[$sid]['events'] as $myEv) {
                                if($myEv['id'] == $eRef['id']) { 
                                    $isEligible = true; 
                                    $time = $myEv['time']; 
                                    break 2; 
                                }
                            }
                        }
                        $css = $isEligible ? ($time ? 'cell-filled' : 'cell-empty') : 'cell-blocked';
                    ?>
                        <td onclick="<?= $isEligible ? "openModal($sid)" : "alert('Atlet ini tidak masuk kualifikasi Kelompok Umur untuk nomor ini.')" ?>" 
                            class="border-l border-slate-50 text-center h-12 transition-all <?= $css ?>">
                            <?= $time ?: ($isEligible ? '+' : '—') ?>
                        </td>
                    <?php endforeach; endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalEntry" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
        <div class="bg-slate-800 p-6 text-white flex justify-between items-center">
            <div>
                <h2 class="text-xl font-black italic uppercase tracking-tighter leading-none" id="mName">ATLET</h2>
                <p class="text-[10px] font-bold text-blue-400 uppercase tracking-widest mt-1" id="mInfo">INFO</p>
            </div>
            <button onclick="closeModal()" class="text-3xl font-light hover:text-red-400 transition-colors">&times;</button>
        </div>
        
        <form method="POST" class="flex flex-col flex-1 overflow-hidden">
            <input type="hidden" name="action" value="save_entries">
            <input type="hidden" name="swimmer_id" id="mSwimmerId">
            <div class="flex-1 overflow-y-auto p-6 space-y-3 bg-slate-50" id="mBody"></div>
            <div class="p-6 bg-white border-t flex justify-between items-center shadow-inner">
                <p class="text-[9px] text-slate-400 font-bold italic w-1/2">* Kosongkan waktu untuk menghapus pendaftaran.</p>
                <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-2xl font-black text-xs shadow-xl hover:bg-blue-700">SIMPAN DATA</button>
            </div>
        </form>
    </div>
</div>

<div id="modalAdd" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl p-6 w-80 shadow-2xl text-center">
        <h3 class="font-black text-slate-800 mb-4 border-b pb-2 uppercase italic">Pilih Atlet</h3>
        <div class="max-h-60 overflow-y-auto space-y-1">
            <?php foreach($allSwimmers as $sw): if(in_array($sw['id'], $_SESSION['matrix_list'][$organizerId])) continue; ?>
                <a href="?event_id=<?= $organizerId ?>&add_swimmer=<?= $sw['id'] ?>" class="block p-3 hover:bg-blue-50 rounded-xl font-bold text-slate-600 text-sm uppercase"><?= $sw['nama_atlet'] ?></a>
            <?php endforeach; ?>
        </div>
        <button onclick="document.getElementById('modalAdd').classList.add('hidden')" class="mt-4 text-slate-400 font-bold text-[10px] uppercase hover:text-red-500">Tutup</button>
    </div>
</div>

<script>
const DATA = <?= json_encode($jsonData) ?>;

function openModal(sid) {
    const s = DATA[sid];
    if(!s) return;

    document.getElementById('mName').innerText = s.name;
    document.getElementById('mInfo').innerText = s.info;
    document.getElementById('mSwimmerId').value = sid;

    const body = document.getElementById('mBody');
    body.innerHTML = '';

    if(s.events.length === 0) {
        body.innerHTML = '<div class="text-center py-10 font-bold text-slate-400 uppercase italic">Tidak ada nomor lomba yang sesuai untuk atlet ini.</div>';
    } else {
        s.events.forEach(ev => {
            const hasVal = ev.time !== '';
            const cardStyle = hasVal ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500' : 'border-slate-200 bg-white';
            
            let bestTimeBtn = ev.best_time ? 
                `<button type="button" onclick="copyTime('${ev.best_time}', '${ev.id}')" class="best-time-badge mt-1 hover:bg-emerald-200">REKOR: ${ev.best_time} 📋</button>` : 
                '<span class="text-[9px] text-slate-300 font-bold uppercase mt-1">Belum ada rekor</span>';

            body.insertAdjacentHTML('beforeend', `
                <div class="flex items-center justify-between p-4 rounded-2xl border transition-all ${cardStyle}">
                    <div class="flex-1">
                        <div class="font-black text-slate-800 text-sm italic uppercase tracking-tighter">${ev.name}</div>
                        <div class="flex flex-col items-start">
                            <span class="text-[9px] font-bold text-blue-500 uppercase tracking-widest">${ev.group}</span>
                            ${bestTimeBtn}
                        </div>
                    </div>
                    <input type="text" id="input_${ev.id}" name="entries[${ev.id}]" value="${ev.time}" placeholder="00.00.00" 
                           class="w-24 text-center font-mono font-bold text-lg bg-slate-100 border-none rounded-xl py-2 focus:ring-2 focus:ring-blue-500 transition-all">
                </div>
            `);
        });
    }
    document.getElementById('modalEntry').classList.remove('hidden');
}

function copyTime(time, evId) {
    const input = document.getElementById('input_' + evId);
    if(input) { input.value = time; input.focus(); }
}

function closeModal() { document.getElementById('modalEntry').classList.add('hidden'); }
</script>