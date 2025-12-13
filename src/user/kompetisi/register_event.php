<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$eventId = $_GET['event_id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([$eventId]);
$event = $stmt->fetch();
if (!$event) die("Event tidak ditemukan.");

$mySwimmers = $pdo->prepare("SELECT * FROM swimmers WHERE user_id = ? ORDER BY nama_atlet ASC");
$mySwimmers->execute([$uid]);
$swimmers = $mySwimmers->fetchAll();

$allCats = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY distance ASC, style ASC");
$allCats->execute([$eventId]);
$categories = $allCats->fetchAll();

$headers = [];
$catLookup = [];
foreach ($categories as $cat) {
    $headers[$cat['distance']][$cat['style']] = true;
    $catLookup[$cat['distance'] . '-' . $cat['style'] . '-' . $cat['gender']] = $cat;
    if($cat['gender'] == 'Mixed') $catLookup[$cat['distance'] . '-' . $cat['style'] . '-Mixed'] = $cat;
}

$stmtEntries = $pdo->prepare("SELECT swimmer_id, category_id, entry_time FROM event_entries WHERE event_id = ? AND club_id = ?");
$stmtEntries->execute([$eventId, $uid]);
$saved = [];
foreach($stmtEntries->fetchAll() as $row) { $saved[$row['swimmer_id']][$row['category_id']] = $row['entry_time']; }

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    .sticky-col { position: sticky; background: #fff; z-index: 10; border-right: 1px solid #e2e8f0; }
    .sticky-header { position: sticky; top: 0; z-index: 20; background: #f8fafc; }
    .sticky-1 { left: 0; width: 50px; }
    .sticky-2 { left: 50px; min-width: 200px; }
    .cell-input { width: 100%; height: 40px; text-align: center; border: none; background: transparent; font-family: monospace; font-size: 11px; outline: none; cursor: default; }
    .cell-input.filled { font-weight: bold; color: #2563eb; background: #eff6ff; }
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="bg-gradient-to-r from-blue-900 to-slate-900 rounded-t-2xl p-6 text-white shadow-xl flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-black uppercase tracking-tight"><?= htmlspecialchars($event['nama_lengkap']) ?></h1>
            <p class="text-blue-200 text-xs font-bold opacity-80 uppercase tracking-widest">Matriks Pendaftaran Peserta</p>
        </div>
        <a href="../pembayaran.php" class="bg-yellow-400 text-slate-900 px-6 py-2.5 rounded-full font-bold text-xs shadow-lg transform hover:scale-105 transition flex items-center gap-2">
            <span>💳</span> STATUS PEMBAYARAN
        </a>
    </div>

    <div class="bg-white border-x border-b border-slate-200 p-4 rounded-b-2xl shadow-sm">
        <div class="flex justify-between items-center mb-6">
            <div class="relative w-80">
                <input type="text" id="tableSearch" onkeyup="filterTable()" class="block w-full p-2.5 pl-10 text-xs font-bold border border-slate-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 bg-slate-50" placeholder="Cari nama atlet...">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 text-sm">🔍</div>
            </div>
            <div class="text-[10px] text-slate-400 font-bold uppercase">Gunakan tombol ✏️ untuk edit nomor</div>
        </div>

        <?php if(empty($swimmers)): ?>
            <div class="text-center py-20 border-2 border-dashed border-slate-200 rounded-2xl">
                <p class="text-slate-400 text-sm font-bold">Belum ada atlet di klub Anda.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto border border-slate-200 rounded-xl shadow-inner max-h-[70vh]">
                <table class="w-full text-sm text-left border-collapse" id="entryTable">
                    <thead class="text-xs text-slate-700 uppercase bg-slate-100">
                        <tr>
                            <th class="px-2 py-4 sticky-header sticky-col sticky-1 text-center z-50 bg-slate-100 border-b border-slate-200">Edit</th>
                            <th class="px-4 py-4 sticky-header sticky-col sticky-2 z-50 bg-slate-100 border-b border-slate-200">Nama Atlet</th>
                            <?php foreach($headers as $dist => $styles): ?>
                                <th class="px-2 py-2 text-center border-l border-slate-300 bg-slate-200 text-slate-800 font-black sticky-header border-b border-slate-300" colspan="<?= count($styles) ?>"><?= $dist ?>m</th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th class="sticky-col sticky-1 top-[48px] z-40 bg-slate-50 border-b border-slate-200"></th>
                            <th class="sticky-col sticky-2 top-[48px] z-40 bg-slate-50 border-b border-slate-200"></th>
                            <?php foreach($headers as $dist => $styles): foreach($styles as $style => $v): ?>
                                <th class="px-1 py-2 text-center border-l border-slate-200 min-w-[100px] bg-white text-[9px] font-black text-slate-500 border-b border-slate-200 sticky-header top-[48px]"><?= str_replace('Gaya ', '', $style) ?></th>
                            <?php endforeach; endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($swimmers as $s): ?>
                        <tr class="hover:bg-blue-50/30 border-b border-slate-100 h-10 swimmer-row">
                            <td class="sticky-col sticky-1 text-center bg-white group-hover:bg-blue-50 border-r border-slate-200">
                                <button type="button" onclick="openEntryModal(<?= $s['id'] ?>)" class="w-7 h-7 flex items-center justify-center rounded-lg text-orange-500 hover:bg-orange-50 transition mx-auto">✏️</button>
                            </td>
                            <td class="sticky-col sticky-2 px-4 font-bold text-slate-800 text-[11px] truncate max-w-[200px] border-r border-slate-200 uppercase"><?= htmlspecialchars($s['nama_atlet']) ?></td>
                            <?php foreach($headers as $dist => $styles): foreach($styles as $style => $v): 
                                $matchId = null;
                                foreach($categories as $cat) {
                                    if($cat['distance'] == $dist && $cat['style'] == $style && ($cat['gender'] == $s['jenis_kelamin'] || $cat['gender'] == 'Mixed')) { $matchId = $cat['id']; break; }
                                }
                                $val = $saved[$s['id']][$matchId] ?? '';
                            ?>
                                <td class="p-0 border-l border-slate-100 h-10 align-middle text-center">
                                    <?php if($matchId): ?>
                                        <input type="text" value="<?= $val ?>" class="cell-input <?= $val ? 'filled' : '' ?>" readonly placeholder="-">
                                    <?php else: ?>
                                        <div class="w-full h-full bg-slate-50 flex items-center justify-center text-slate-200 text-[10px]">✕</div>
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

<div id="entryModal" class="fixed inset-0 z-[100] hidden bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4 transition-opacity">
    <div class="w-full max-w-lg transform scale-95 transition-all" id="modalContent"></div>
    <div class="absolute inset-0 -z-10" onclick="closeEntryModal()"></div>
</div>

<script>
function filterTable() {
    const filter = document.getElementById("tableSearch").value.toUpperCase();
    const rows = document.getElementById("entryTable").getElementsByClassName("swimmer-row");
    for (let i = 0; i < rows.length; i++) {
        const nameCol = rows[i].getElementsByTagName("td")[1];
        if (nameCol) rows[i].style.display = (nameCol.textContent).toUpperCase().indexOf(filter) > -1 ? "" : "none";
    }
}

function openEntryModal(swimmerId) {
    const modal = document.getElementById('entryModal');
    const content = document.getElementById('modalContent');
    modal.classList.remove('hidden');
    content.innerHTML = '<div class="p-10 bg-white rounded-2xl text-center font-bold text-slate-400">Loading...</div>';

    fetch(`edit_entry.php?event_id=<?= $eventId ?>&swimmer_id=${swimmerId}&ajax=1`)
        .then(res => res.text())
        .then(html => {
            content.innerHTML = html;
            const scripts = content.querySelectorAll("script");
            scripts.forEach(s => { const n = document.createElement("script"); n.text = s.text; document.body.appendChild(n).parentNode.removeChild(n); });
        });
}

function closeEntryModal() { document.getElementById('entryModal').classList.add('hidden'); }
</script>