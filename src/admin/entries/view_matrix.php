<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$clubId = $_GET['club_id'] ?? 0;
$eventId = $_SESSION['user_id']; // ID Admin EO

// 1. Ambil Info Klub
$stmtClub = $pdo->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
$stmtClub->execute([$clubId]);
$clubName = $stmtClub->fetchColumn();
if (!$clubName) die("Data klub tidak ditemukan.");

// 2. Ambil Atlet dari Klub ini
$stmtSwimmers = $pdo->prepare("SELECT * FROM swimmers WHERE user_id = ? ORDER BY nama_atlet ASC");
$stmtSwimmers->execute([$clubId]);
$swimmers = $stmtSwimmers->fetchAll();

// 3. Ambil Kategori & Header Matriks
$allCats = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY distance ASC, style ASC");
$allCats->execute([$eventId]);
$categories = $allCats->fetchAll();

$headers = [];
foreach ($categories as $cat) { $headers[$cat['distance']][$cat['style']] = true; }

// 4. Ambil Data Pendaftaran
$stmtEntries = $pdo->prepare("SELECT swimmer_id, category_id, entry_time FROM event_entries WHERE event_id = ? AND club_id = ?");
$stmtEntries->execute([$eventId, $clubId]);
$saved = [];
foreach($stmtEntries->fetchAll() as $row) { $saved[$row['swimmer_id']][$row['category_id']] = $row['entry_time']; }

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    .sticky-col { position: sticky; background: #fff; z-index: 10; border-right: 1px solid #e2e8f0; }
    .sticky-header { position: sticky; top: 0; z-index: 20; background: #f8fafc; }
    .sticky-1 { left: 0; width: 60px; }
    .sticky-2 { left: 60px; min-width: 220px; }
    .cell-data { width: 100%; height: 45px; display: flex; align-items: center; justify-content: center; font-family: monospace; font-size: 11px; }
    .cell-data.filled { font-weight: 900; color: #2563eb; background: #eff6ff; }
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    <div class="bg-slate-900 rounded-[2.5rem] p-10 text-white shadow-2xl flex flex-col md:flex-row justify-between items-center gap-6 mb-10 relative overflow-hidden">
        <div class="relative z-10">
            <h1 class="text-3xl font-black uppercase tracking-tighter italic leading-none mb-2">Club Entry Matrix</h1>
            <p class="text-blue-400 text-xs font-bold uppercase tracking-[0.2em]"><?= htmlspecialchars($clubName) ?></p>
        </div>
        <a href="index.php" class="relative z-10 bg-white/10 text-white px-8 py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest border border-white/10 hover:bg-white/20 transition">← Kembali</a>
    </div>

    <div class="bg-white border border-slate-200 rounded-[3rem] shadow-sm overflow-hidden mb-20">
        <div class="overflow-x-auto max-h-[70vh] custom-scrollbar">
            <table class="w-full text-sm text-left border-collapse" id="entryTable">
                <thead>
                    <tr class="bg-slate-100">
                        <th class="px-2 py-4 sticky-header sticky-col sticky-1 text-center z-50 border-b border-slate-200">Edit</th>
                        <th class="px-6 py-4 sticky-header sticky-col sticky-2 z-50 border-b border-slate-200 font-black text-[10px] uppercase tracking-widest">Athlete Name</th>
                        <?php foreach($headers as $dist => $styles): ?>
                            <th class="px-2 py-3 text-center border-l border-slate-300 bg-slate-800 text-white font-black text-[11px] sticky-header border-b border-slate-300" colspan="<?= count($styles) ?>"><?= $dist ?>m</th>
                        <?php endforeach; ?>
                    </tr>
                    <tr class="bg-white">
                        <th class="sticky-col sticky-1 top-[52px] z-40 bg-white border-b border-slate-200"></th>
                        <th class="sticky-col sticky-2 top-[52px] z-40 bg-white border-b border-slate-200"></th>
                        <?php foreach($headers as $dist => $styles): foreach($styles as $style => $v): ?>
                            <th class="px-1 py-3 text-center border-l border-slate-100 min-w-[110px] bg-slate-50 text-[8px] font-black text-slate-400 border-b border-slate-200 sticky-header top-[52px] italic"><?= str_replace('Gaya ', '', $style) ?></th>
                        <?php endforeach; endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($swimmers as $s): ?>
                    <tr class="hover:bg-blue-50/50 border-b border-slate-50 h-12 swimmer-row">
                        <td class="sticky-col sticky-1 text-center bg-white border-r border-slate-100">
                            <button type="button" onclick="openAdminEditModal(<?= $s['id'] ?>)" class="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-50 text-orange-500 hover:bg-orange-500 hover:text-white transition mx-auto">✏️</button>
                        </td>
                        <td class="sticky-col sticky-2 px-6 font-black text-slate-700 text-[11px] truncate max-w-[220px] border-r border-slate-100 uppercase italic"><?= htmlspecialchars($s['nama_atlet']) ?></td>
                        <?php foreach($headers as $dist => $styles): foreach($styles as $style => $v): 
                            $matchId = null;
                            foreach($categories as $cat) {
                                if($cat['distance'] == $dist && $cat['style'] == $style && ($cat['gender'] == $s['jenis_kelamin'] || $cat['gender'] == 'Mixed')) { $matchId = $cat['id']; break; }
                            }
                            $val = $saved[$s['id']][$matchId] ?? '';
                        ?>
                            <td class="p-0 border-l border-slate-50 text-center">
                                <?php if($matchId): ?>
                                    <div class="cell-data <?= $val ? 'filled' : '' ?>"><?= $val ?: '-' ?></div>
                                <?php else: ?>
                                    <div class="w-full h-full bg-slate-50/50 flex items-center justify-center text-slate-100 text-[10px]">✕</div>
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

<div id="editModal" class="fixed inset-0 z-[100] hidden bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4">
    <div class="w-full max-w-lg transform transition-all" id="modalContent"></div>
    <div class="absolute inset-0 -z-10" onclick="closeModal()"></div>
</div>

<script>
function openAdminEditModal(swimmerId) {
    const modal = document.getElementById('editModal');
    const content = document.getElementById('modalContent');
    modal.classList.remove('hidden');
    content.innerHTML = '<div class="p-20 bg-white rounded-[3rem] text-center shadow-2xl font-black text-slate-300 animate-pulse uppercase text-xs tracking-widest">Opening Correction Form...</div>';
    fetch(`../../user/kompetisi/edit_entry.php?event_id=<?= $eventId ?>&swimmer_id=${swimmerId}&ajax=1`)
        .then(res => res.text()).then(html => {
            content.innerHTML = html;
            const scripts = content.querySelectorAll("script");
            scripts.forEach(s => { const n = document.createElement("script"); n.text = s.text; document.body.appendChild(n).parentNode.removeChild(n); });
        });
}
function closeModal() { document.getElementById('editModal').classList.add('hidden'); }
</script>