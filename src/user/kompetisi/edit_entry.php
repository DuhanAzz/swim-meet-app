<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') exit("Akses ditolak");

$eventId = $_GET['event_id'] ?? 0;
$swimmerId = $_GET['swimmer_id'] ?? 0;
$uid = $_SESSION['user_id'];
$isAjax = isset($_GET['ajax']); 

// 1. Ambil Data Atlet
$stmtS = $pdo->prepare("SELECT * FROM swimmers WHERE id = ? AND user_id = ?");
$stmtS->execute([$swimmerId, $uid]);
$s = $stmtS->fetch();
if (!$s) exit("Atlet tidak ditemukan.");

// 2. Handle Simpan (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM event_entries WHERE event_id = ? AND swimmer_id = ?")->execute([$eventId, $swimmerId]);

        if (!empty($_POST['categories'])) {
            $ins = $pdo->prepare("INSERT INTO event_entries (event_id, club_id, swimmer_id, category_id, entry_time) VALUES (?, ?, ?, ?, ?)");
            foreach ($_POST['categories'] as $catId) {
                $timeInput = trim($_POST['times'][$catId] ?? '');
                $finalTime = (empty($timeInput) || $timeInput == '-') ? '99.99.99' : $timeInput;
                $ins->execute([$eventId, $uid, $swimmerId, $catId, $finalTime]);
            }
        }
        $pdo->commit();
        if ($isAjax) { echo "OK_RELOAD"; exit; }
        header("Location: register_event.php?event_id=$eventId"); exit;
    } catch (Exception $e) { $pdo->rollBack(); echo "Error: " . $e->getMessage(); exit; }
}

// 3. Ambil Kategori & PB
$stmtCat = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? AND (gender = ? OR gender = 'Mixed') ORDER BY distance ASC, style ASC");
$stmtCat->execute([$eventId, $s['jenis_kelamin']]);
$categories = $stmtCat->fetchAll();

$stmtPB = $pdo->prepare("SELECT nomor_lomba, waktu_terbaik FROM athlete_records WHERE swimmer_id = ?");
$stmtPB->execute([$swimmerId]);
$pbRaw = $stmtPB->fetchAll(PDO::FETCH_KEY_PAIR);

$stmtEntry = $pdo->prepare("SELECT category_id, entry_time FROM event_entries WHERE event_id = ? AND swimmer_id = ?");
$stmtEntry->execute([$eventId, $swimmerId]);
$existingEntries = $stmtEntry->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="bg-white rounded-2xl overflow-hidden shadow-2xl">
    <div class="bg-slate-900 p-5 text-white flex justify-between items-center">
        <div>
            <h1 class="text-base font-black uppercase tracking-tighter">Setting Nomor Lomba</h1>
            <p class="text-[10px] text-blue-400 font-bold uppercase"><?= htmlspecialchars($s['nama_atlet']) ?></p>
        </div>
        <button type="button" onclick="closeEntryModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition">&times;</button>
    </div>

    <form id="formEditEntry" class="p-5">
        <div class="space-y-2 max-h-[50vh] overflow-y-auto pr-2 custom-scrollbar">
            <?php foreach($categories as $c): 
                $catName = $c['distance'] . "m " . $c['style'];
                $pbValue = $pbRaw[$catName] ?? '99.99.99';
                $isRegistered = isset($existingEntries[$c['id']]);
                $currentTime = $isRegistered ? $existingEntries[$c['id']] : $pbValue;
            ?>
            <div class="flex items-center justify-between p-3 rounded-xl border-2 transition-all <?= $isRegistered ? 'border-blue-500 bg-blue-50' : 'border-slate-100 bg-white' ?>">
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="categories[]" value="<?= $c['id'] ?>" 
                           class="w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                           <?= $isRegistered ? 'checked' : '' ?>
                           onchange="toggleRow(this, 'time-<?= $c['id'] ?>')">
                    <div>
                        <div class="font-black text-slate-800 text-[11px] uppercase"><?= $catName ?></div>
                        <div class="text-[9px] font-bold text-slate-400 uppercase"><?= $c['age_group'] ?></div>
                    </div>
                </div>
                
                <div class="flex flex-col items-end">
                    <input type="text" name="times[<?= $c['id'] ?>]" id="time-<?= $c['id'] ?>" 
                           value="<?= $currentTime ?>" 
                           class="w-24 px-2 py-1 rounded-lg border border-slate-300 text-center font-mono text-xs font-bold focus:ring-2 focus:ring-blue-500 outline-none <?= $isRegistered ? 'bg-white text-blue-600' : 'bg-slate-50 text-slate-400' ?>"
                           <?= !$isRegistered ? 'readonly' : '' ?>>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="button" onclick="closeEntryModal()" class="flex-1 bg-slate-100 text-slate-500 font-bold py-3 rounded-xl text-xs uppercase hover:bg-slate-200 transition">Batal</button>
            <button type="button" onclick="submitEntryForm()" id="btnSave" class="flex-2 bg-blue-600 hover:bg-blue-700 text-white font-black py-3 px-8 rounded-xl shadow-lg shadow-blue-200 transition transform hover:-translate-y-0.5 text-xs uppercase">Simpan Data</button>
        </div>
    </form>
</div>

<script>
function toggleRow(checkbox, inputId) {
    const input = document.getElementById(inputId);
    const container = checkbox.closest('.rounded-xl');
    if(checkbox.checked) {
        input.removeAttribute('readonly');
        input.classList.remove('bg-slate-50', 'text-slate-400');
        input.classList.add('bg-white', 'text-blue-600');
        container.classList.remove('border-slate-100', 'bg-white');
        container.classList.add('border-blue-500', 'bg-blue-50');
        input.focus();
    } else {
        input.setAttribute('readonly', 'true');
        input.classList.add('bg-slate-50', 'text-slate-400');
        input.classList.remove('bg-white', 'text-blue-600');
        container.classList.add('border-slate-100', 'bg-white');
        container.classList.remove('border-blue-500', 'bg-blue-50');
    }
}

function submitEntryForm() {
    const btn = document.getElementById('btnSave');
    const form = document.getElementById('formEditEntry');
    const formData = new FormData(form);
    btn.disabled = true; btn.innerText = 'WAIT...';

    fetch(`edit_entry.php?event_id=<?= $eventId ?>&swimmer_id=<?= $swimmerId ?>&ajax=1`, {
        method: 'POST', body: formData
    })
    .then(res => res.text())
    .then(data => {
        if(data.trim() === 'OK_RELOAD') { window.location.reload(); }
        else { alert(data); btn.disabled = false; btn.innerText = 'SIMPAN DATA'; }
    });
}
</script>