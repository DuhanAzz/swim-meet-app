<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$clubId = $_GET['club_id'] ?? 0;
$eventId = $_SESSION['user_id']; // ID Admin EO

// 1. Ambil Nama Klub
$stmtClub = $pdo->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
$stmtClub->execute([$clubId]);
$clubName = $stmtClub->fetchColumn();

// 2. Ambil Atlet dari Klub ini
$stmtSwimmers = $pdo->prepare("SELECT * FROM swimmers WHERE user_id = ? ORDER BY nama_atlet ASC");
$stmtSwimmers->execute([$clubId]);
$swimmers = $stmtSwimmers->fetchAll();

// 3. Ambil Kategori & Buat Header
$allCats = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY distance ASC, style ASC");
$allCats->execute([$eventId]);
$categories = $allCats->fetchAll();

$headers = [];
foreach ($categories as $cat) {
    $headers[$cat['distance']][$cat['style']] = true;
}

// 4. Ambil Data Pendaftaran
$stmtEntries = $pdo->prepare("SELECT swimmer_id, category_id, entry_time FROM event_entries WHERE event_id = ? AND club_id = ?");
$stmtEntries->execute([$eventId, $clubId]);
$saved = [];
foreach($stmtEntries->fetchAll() as $row) { $saved[$row['swimmer_id']][$row['category_id']] = $row['entry_time']; }
?>

<div class="bg-slate-900 p-8 text-white flex justify-between items-center">
    <div>
        <h3 class="font-black uppercase tracking-widest italic text-lg">Matriks Pendaftaran Atlet</h3>
        <p class="text-blue-400 text-[10px] font-bold uppercase tracking-[0.2em] mt-1"><?= htmlspecialchars($clubName) ?></p>
    </div>
    <button onclick="closeModal()" class="text-slate-400 hover:text-white text-xl">✕</button>
</div>

<div class="p-4 overflow-x-auto max-h-[70vh] custom-scrollbar">
    <table class="w-full text-sm text-left border-collapse border border-slate-200">
        <thead>
            <tr class="bg-slate-100">
                <th class="px-6 py-4 sticky left-0 z-20 bg-slate-100 border-b border-slate-200 font-black text-[10px] uppercase tracking-widest min-w-[200px]">Nama Atlet</th>
                <?php foreach($headers as $dist => $styles): ?>
                    <th class="px-2 py-3 text-center border-l border-slate-300 bg-slate-800 text-white font-black text-[11px] border-b border-slate-300" colspan="<?= count($styles) ?>"><?= $dist ?>m</th>
                <?php endforeach; ?>
            </tr>
            <tr class="bg-white">
                <th class="sticky left-0 z-20 bg-white border-b border-slate-200 border-r"></th>
                <?php foreach($headers as $dist => $styles): foreach($styles as $style => $v): ?>
                    <th class="px-1 py-3 text-center border-l border-slate-100 min-w-[100px] bg-slate-50 text-[8px] font-black text-slate-400 border-b border-slate-200 italic"><?= str_replace('Gaya ', '', $style) ?></th>
                <?php endforeach; endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach($swimmers as $s): ?>
            <tr class="hover:bg-blue-50/50 border-b border-slate-50 h-10 transition-colors">
                <td class="sticky left-0 z-10 bg-white px-6 font-bold text-slate-700 text-[11px] border-r border-slate-100 uppercase truncate italic"><?= htmlspecialchars($s['nama_atlet']) ?></td>
                <?php foreach($headers as $dist => $styles): foreach($styles as $style => $v): 
                    $matchId = null;
                    foreach($categories as $cat) {
                        if($cat['distance'] == $dist && $cat['style'] == $style && ($cat['gender'] == $s['jenis_kelamin'] || $cat['gender'] == 'Mixed')) { $matchId = $cat['id']; break; }
                    }
                    $val = $saved[$s['id']][$matchId] ?? '';
                ?>
                    <td class="p-0 border-l border-slate-50 h-10 align-middle text-center">
                        <?php if($matchId): ?>
                            <div class="flex items-center justify-center font-mono text-[10px] <?= $val ? 'text-blue-600 font-black bg-blue-50/50 h-full' : 'text-slate-200' ?>">
                                <?= $val ? $val : '-' ?>
                            </div>
                        <?php else: ?>
                            <div class="w-full h-full bg-slate-50/30 flex items-center justify-center text-slate-100 text-[10px]">✕</div>
                        <?php endif; ?>
                    </td>
                <?php endforeach; endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="p-6 bg-slate-50 border-t border-slate-100 text-center">
    <button onclick="closeModal()" class="bg-slate-900 text-white font-black px-12 py-3 rounded-2xl text-[10px] uppercase tracking-widest shadow-xl hover:bg-blue-600 transition">Tutup Matriks</button>
</div>