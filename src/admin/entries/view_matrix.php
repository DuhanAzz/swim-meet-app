<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$clubId = $_GET['club_id'] ?? 0;
$adminId = $_SESSION['user_id']; // ID EO/Admin

// 1. Ambil Info Klub
$stmtClub = $pdo->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
$stmtClub->execute([$clubId]);
$clubName = $stmtClub->fetchColumn();
if (!$clubName) die("Data klub tidak ditemukan.");

// 2. Ambil Atlet dari Klub ini
$stmtSwimmers = $pdo->prepare("SELECT * FROM swimmers WHERE user_id = ? ORDER BY nama_atlet ASC");
$stmtSwimmers->execute([$clubId]);
$swimmers = $stmtSwimmers->fetchAll();

// 3. Ambil Kategori & Header Matriks (Harus identik dengan milik admin/EO)
$allCats = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY distance ASC, style ASC");
$allCats->execute([$adminId]);
$categories = $allCats->fetchAll();

$headers = [];
foreach ($categories as $cat) { 
    $headers[$cat['distance']][$cat['style']] = true; 
}

// 4. Ambil Data Pendaftaran yang sudah ada
$stmtEntries = $pdo->prepare("SELECT swimmer_id, category_id, entry_time FROM event_entries WHERE event_id = ? AND club_id = ?");
$stmtEntries->execute([$adminId, $clubId]);
$saved = [];
foreach($stmtEntries->fetchAll() as $row) { 
    $saved[$row['swimmer_id']][$row['category_id']] = $row['entry_time']; 
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    /* Styling Identik dengan register_event.php */
    .sticky-col { position: sticky; background: #fff; z-index: 10; border-right: 1px solid #e2e8f0; }
    .sticky-header { position: sticky; top: 0; z-index: 20; background: #f8fafc; }
    .sticky-1 { left: 0; width: 50px; }
    .sticky-2 { left: 50px; min-width: 200px; }
    .cell-input { width: 100%; height: 40px; text-align: center; border: none; background: transparent; font-family: monospace; font-size: 11px; outline: none; }
    .cell-input.filled { font-weight: bold; color: #2563eb; background: #eff6ff; }
    .custom-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="bg-gradient-to-r from-slate-900 to-blue-900 rounded-2xl p-6 text-white shadow-xl flex flex-col md:flex-row justify-between items-center gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-black uppercase tracking-tight italic">Matrix Pendaftaran Klub</h1>
            <p class="text-blue-200 text-[10px] font-bold opacity-80 uppercase tracking-widest">Klub: <?= htmlspecialchars($clubName) ?></p>
        </div>
        <div class="flex gap-3">
            <a href="index.php" class="bg-white/10 text-white px-5 py-2 rounded-xl font-bold text-[10px] uppercase border border-white/20 hover:bg-white/20 transition">Kembali</a>
            <button onclick="location.reload()" class="bg-blue-500 text-white px-5 py-2 rounded-xl font-black text-[10px] uppercase shadow-lg transition">🔄 Refresh Data</button>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-[2rem] shadow-sm overflow-hidden mb-10">
        <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <div class="relative w-72">
                <input type="text" id="tableSearch" onkeyup="filterTable()" class="block w-full p-2.5 pl-10 text-[11px] font-bold border border-slate-200 rounded-xl focus:ring-blue-500 bg-white" placeholder="Cari nama atlet...">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">🔍</div>
            </div>
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">Admin Mode: Koreksi Entry Time melalui tombol ✏️</div>
        </div>

        <?php if(empty($swimmers)): ?>
            <div class="text-center py-32">
                <p class="text-slate-300 font-black uppercase text-xs">Belum ada atlet di klub ini.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto max-h-[65vh] custom-scrollbar">
                <table class="w-full text-sm text-left border-collapse" id="entryTable">
                    <thead class="text-[10px] text-slate-700 uppercase">
                        <tr>
                            <th class="px-2 py-4 sticky-header sticky-col sticky-1 text-center z-50 bg-slate-100 border-b border-slate-200">Edit</th>
                            <th class="px-6 py-4 sticky-header sticky-col sticky-2 z-50 bg-slate-100 border-b border-slate-200 font-black">Nama Atlet</th>
                            <?php foreach($headers as $dist => $styles): ?>
                                <th class="px-2 py-3 text-center border-l border-slate-300 bg-slate-200 text-slate-800 font-black sticky-header border-b border-slate-300" colspan="<?= count($styles) ?>"><?= $dist ?>m</th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th class="sticky-col sticky-1 top-[48px] z-40 bg-white border-b border-slate-200"></th>
                            <th class="sticky-col sticky-2 top-[48px] z-40 bg-white border-b border-slate-200"></th>
                            <?php foreach($headers as $dist => $styles): foreach($styles as $style => $v): ?>
                                <th class="px-1 py-3 text-center border-l border-slate-100 min-w-[100px] bg-slate-50 text-[8px] font-black text-slate-400 border-b border-slate-200 sticky-header top-[48px]"><?= str_replace('Gaya ', '', $style) ?></th>
                            <?php endforeach; endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($swimmers as $s): ?>
                        <tr class="hover:bg-blue-50/50 border-b border-slate-50 h-10 swimmer-row transition-colors">
                            <td class="sticky-col sticky-1 text-center bg-white border-r border-slate-100">
                                <button type="button" onclick="openAdminEditModal(<?= $s['id'] ?>, '<?= addslashes($s['nama_atlet']) ?>')" class="w-7 h-7 flex items-center justify-center rounded-lg text-orange-500 hover:bg-orange-50 transition mx-auto">✏️</button>
                            </td>
                            <td class="sticky-col sticky-2 px-6 font-bold text-slate-800 text-[11px] truncate max-w-[200px] border-r border-slate-100 uppercase italic"><?= htmlspecialchars($s['nama_atlet']) ?></td>
                            <?php foreach($headers as $dist => $styles): foreach($styles as $style => $v): 
                                $matchId = null;
                                foreach($categories as $cat) {
                                    if($cat['distance'] == $dist && $cat['style'] == $style && ($cat['gender'] == $s['jenis_kelamin'] || $cat['gender'] == 'Mixed')) { 
                                        $matchId = $cat['id']; break; 
                                    }
                                }
                                $val = $saved[$s['id']][$matchId] ?? '';
                            ?>
                                <td class="p-0 border-l border-slate-50 h-10 align-middle text-center">
                                    <?php if($matchId): ?>
                                        <div class="cell-input <?= $val ? 'filled' : '' ?> flex items-center justify-center font-mono text-[10px]">
                                            <?= $val ? $val : '-' ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-full h-full bg-slate-50/30 flex items-center justify-center text-slate-200 text-[10px]">✕</div>
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

<div id="editModal" class="fixed inset-0 z-[100] hidden bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4">
    <div class="w-full max-w-lg transform transition-all relative">
        <button onclick="closeModal()" class="absolute -top-12 right-0 text-white font-black text-xs uppercase tracking-widest flex items-center gap-2 hover:text-red-400 transition">Tutup Panel ✕</button>
        <div class="bg-white rounded-[2rem] shadow-2xl overflow-hidden" id="modalContent"></div>
    </div>
    <div class="absolute inset-0 -z-10" onclick="closeModal()"></div>
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

function openAdminEditModal(swimmerId, name) {
    const modal = document.getElementById('editModal');
    const content = document.getElementById('modalContent');
    modal.classList.remove('hidden');
    content.innerHTML = `<div class="p-20 text-center"><div class="inline-block w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mb-4"></div><p class="font-black text-slate-400 uppercase text-[10px] tracking-widest animate-pulse">Memuat Data ${name}...</p></div>`;

    fetch(`../../user/kompetisi/edit_entry.php?event_id=<?= $adminId ?>&swimmer_id=${swimmerId}&ajax=1`)
        .then(res => res.text())
        .then(html => {
            content.innerHTML = html;
            // Jalankan script yang ada di dalam modal
            const scripts = content.querySelectorAll("script");
            scripts.forEach(oldScript => {
                const newScript = document.createElement("script");
                Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });
        });
}

function closeModal() { document.getElementById('editModal').classList.add('hidden'); }
</script>