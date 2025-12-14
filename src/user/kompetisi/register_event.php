<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$eventId = $_GET['event_id'] ?? 0;

// 1. Ambil Info Event
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([$eventId]);
$event = $stmt->fetch();
if (!$event) die("Event tidak ditemukan.");

// 2. Ambil Atlet Saya
$mySwimmers = $pdo->prepare("SELECT * FROM swimmers WHERE user_id = ? ORDER BY nama_atlet ASC");
$mySwimmers->execute([$uid]);
$swimmers = $mySwimmers->fetchAll();

// 3. Ambil Kategori & Buat Header Matriks
$allCats = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY distance ASC, style ASC");
$allCats->execute([$eventId]);
$categories = $allCats->fetchAll();

$headers = [];
foreach ($categories as $cat) {
    $headers[$cat['distance']][$cat['style']] = true;
}

// 4. Ambil Data yang Sudah Disimpan & Hitung Biaya
$stmtEntries = $pdo->prepare("SELECT ee.*, ec.price FROM event_entries ee JOIN event_categories ec ON ee.category_id = ec.id WHERE ee.event_id = ? AND ee.club_id = ?");
$stmtEntries->execute([$eventId, $uid]);
$savedEntries = $stmtEntries->fetchAll();

$saved = [];
$totalEntriesCount = 0;
$totalFee = 0;

foreach($savedEntries as $row) { 
    $saved[$row['swimmer_id']][$row['category_id']] = $row['entry_time']; 
    $totalEntriesCount++;
    $totalFee += $row['price'];
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
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
    
    <div class="bg-gradient-to-r from-blue-900 to-slate-900 rounded-2xl p-6 text-white shadow-xl flex flex-col md:flex-row justify-between items-center gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-black uppercase tracking-tight italic"><?= htmlspecialchars($event['nama_lengkap']) ?></h1>
            <p class="text-blue-200 text-[10px] font-bold opacity-80 uppercase tracking-widest">Matriks Pendaftaran Peserta</p>
        </div>
        <div class="flex gap-3">
            <a href="explore.php" class="bg-white/10 text-white px-5 py-2 rounded-xl font-bold text-[10px] uppercase border border-white/20 hover:bg-white/20 transition">Kembali</a>
            <a href="../pembayaran.php" class="bg-yellow-400 text-slate-900 px-5 py-2 rounded-xl font-black text-[10px] uppercase shadow-lg transition">Status Bayar</a>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-[2rem] shadow-sm overflow-hidden mb-10">
        <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <div class="relative w-72">
                <input type="text" id="tableSearch" onkeyup="filterTable()" class="block w-full p-2.5 pl-10 text-[11px] font-bold border border-slate-200 rounded-xl focus:ring-blue-500 bg-white" placeholder="Cari nama atlet...">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">🔍</div>
            </div>
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">Klik tombol ✏️ untuk mengisi waktu/entry time</div>
        </div>

        <?php if(empty($swimmers)): ?>
            <div class="text-center py-32">
                <p class="text-slate-300 font-black uppercase text-xs">Belum ada atlet di klub Anda.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto max-h-[60vh] custom-scrollbar">
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
                                <button type="button" onclick="openEntryModal(<?= $s['id'] ?>)" class="w-7 h-7 flex items-center justify-center rounded-lg text-orange-500 hover:bg-orange-50 transition mx-auto">✏️</button>
                            </td>
                            <td class="sticky-col sticky-2 px-6 font-bold text-slate-800 text-[11px] truncate max-w-[200px] border-r border-slate-100 uppercase"><?= htmlspecialchars($s['nama_atlet']) ?></td>
                            <?php foreach($headers as $dist => $styles): foreach($styles as $style => $v): 
                                $matchId = null;
                                foreach($categories as $cat) {
                                    if($cat['distance'] == $dist && $cat['style'] == $style && ($cat['gender'] == $s['jenis_kelamin'] || $cat['gender'] == 'Mixed')) { $matchId = $cat['id']; break; }
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

    <div class="bg-slate-900 rounded-[2.5rem] p-8 shadow-2xl border border-white/5">
        <div class="flex flex-col lg:flex-row justify-between items-center gap-10">
            
            <div class="flex flex-wrap justify-center lg:justify-start items-center gap-12">
                <div class="flex flex-col">
                    <span class="text-slate-500 text-[10px] font-black uppercase tracking-[0.2em] mb-2">Total Pendaftaran</span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-white font-black text-4xl"><?= $totalEntriesCount ?></span>
                        <span class="text-blue-400 font-bold text-xs uppercase italic">Splash</span>
                    </div>
                </div>
                
                <div class="hidden md:block w-px h-16 bg-white/10"></div>
                
                <div class="flex flex-col">
                    <span class="text-slate-500 text-[10px] font-black uppercase tracking-[0.2em] mb-2">Total Tagihan</span>
                    <span class="text-emerald-400 font-black text-4xl italic">Rp<?= number_format($totalFee, 0, ',', '.') ?></span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-6">
                <div class="text-center sm:text-right">
                    <p class="text-white font-black text-xs uppercase tracking-wider mb-1">Siap Berkompetisi?</p>
                    <p class="text-slate-500 text-[10px] uppercase font-bold tracking-tighter">Lanjutkan ke tahap upload berkas & bayar</p>
                </div>
                
                <a href="checkout.php?event_id=<?= $eventId ?>" class="group bg-blue-600 hover:bg-blue-500 text-white font-black px-12 py-5 rounded-[2rem] shadow-xl shadow-blue-900/40 transition transform hover:-translate-y-1 active:scale-95 flex items-center gap-4">
                    <span class="text-sm uppercase tracking-widest">FINALISASI & BAYAR</span>
                    <span class="text-2xl group-hover:translate-x-1 transition-transform">➔</span>
                </a>
            </div>

        </div>
    </div>

    <div class="mt-10 text-center pb-20">
        <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">SwimMeet Registration Engine &copy; 2025</p>
    </div>

</div>

<div id="entryModal" class="fixed inset-0 z-[100] hidden bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4">
    <div class="w-full max-w-lg transform transition-all" id="modalContent"></div>
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
    content.innerHTML = '<div class="p-20 bg-white rounded-[3rem] text-center font-black text-slate-400 animate-pulse uppercase tracking-widest text-xs italic">Memuat Data Atlet...</div>';

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