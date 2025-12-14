<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$adminMode = $_SESSION['event_type'] ?? 'Langsung Final';
$selectedCatId = $_GET['category_id'] ?? 0;
$stage = $_GET['stage'] ?? 'Prelims'; 
$search = $_GET['q'] ?? '';

// --- 1. AMBIL SETTING KOLAM (Lane Count) ---
$stmtUser = $pdo->prepare("SELECT lane_count FROM users WHERE id = ?");
$stmtUser->execute([$uid]);
$laneCount = $stmtUser->fetchColumn() ?: 8;

// --- 2. LOGIKA SIMPAN HASIL & RANKING ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_results'])) {
    try {
        $pdo->beginTransaction();
        if (isset($_POST['result'])) {
            foreach ($_POST['result'] as $lineId => $time) {
                $status = $_POST['status'][$lineId] ?? 'OK';
                $finalTime = ($status !== 'OK') ? NULL : ($time ?: NULL);
                
                $stmt = $pdo->prepare("UPDATE race_lines SET result_time = ?, status = ? WHERE id = ?");
                $stmt->execute([$finalTime, $status, $lineId]);
            }
        }

        // AUTO-RANKING (Hanya untuk yang status OK)
        $sqlRank = "SELECT rl.id FROM race_lines rl 
                    JOIN race_heats rh ON rl.heat_id = rh.id 
                    WHERE rh.category_id = ? AND rh.stage = ? 
                    AND rl.status = 'OK' AND rl.result_time IS NOT NULL AND rl.result_time != ''
                    ORDER BY rl.result_time ASC";
        $stmtRank = $pdo->prepare($sqlRank);
        $stmtRank->execute([$selectedCatId, $stage]);
        $ranks = $stmtRank->fetchAll();
        
        $pdo->prepare("UPDATE race_lines rl JOIN race_heats rh ON rl.heat_id = rh.id SET rl.rank = NULL WHERE rh.category_id = ? AND rh.stage = ?")
            ->execute([$selectedCatId, $stage]);

        foreach ($ranks as $index => $r) {
            $pdo->prepare("UPDATE race_lines SET rank = ? WHERE id = ?")->execute([$index + 1, $r['id']]);
        }

        $pdo->commit();
        $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Hasil berhasil disimpan!';
        header("Location: index.php?category_id=$selectedCatId&stage=$stage&q=$search"); exit;
    } catch (Exception $e) { if($pdo->inTransaction()) $pdo->rollBack(); die("Error: " . $e->getMessage()); }
}

// --- 3. AMBIL DAFTAR NOMOR LOMBA ---
$sqlCat = "SELECT * FROM event_categories WHERE user_id = ?";
$paramsCat = [$uid];
if ($search) {
    $sqlCat .= " AND (event_no LIKE ? OR style LIKE ? OR age_group LIKE ?)";
    $paramsCat[] = "%$search%"; $paramsCat[] = "%$search%"; $paramsCat[] = "%$search%";
}
$sqlCat .= " ORDER BY event_no ASC";
$stmtCat = $pdo->prepare($sqlCat);
$stmtCat->execute($paramsCat);
$categories = $stmtCat->fetchAll();

// --- 4. AMBIL DATA SERI + LINTASAN ---
$heats = [];
if ($selectedCatId) {
    $stmtH = $pdo->prepare("SELECT * FROM race_heats WHERE category_id = ? AND stage = ? ORDER BY heat_number ASC");
    $stmtH->execute([$selectedCatId, $stage]);
    $rawHeats = $stmtH->fetchAll();

    foreach($rawHeats as $h) {
        $stmtL = $pdo->prepare("SELECT rl.*, s.nama_atlet, u.nama_lengkap as nama_klub 
                                FROM race_lines rl 
                                JOIN swimmers s ON rl.swimmer_id = s.id 
                                JOIN users u ON s.user_id = u.id
                                WHERE rl.heat_id = ? ORDER BY rl.lane_number ASC");
        $stmtL->execute([$h['id']]);
        $lines = $stmtL->fetchAll();
        
        // Mapping lintasan agar mudah dipanggil berdasarkan nomor LN (1-8)
        $mappedLines = [];
        foreach($lines as $l) { $mappedLines[$l['lane_number']] = $l; }
        
        $h['mapped_lanes'] = $mappedLines;
        $heats[] = $h;
    }
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    .paper-font { font-family: 'Courier New', Courier, monospace; }
    .double-line { border-top: 4px double #000; margin: 10px 0; }
    .event-btn.active { border-color: #000; background: #000; color: #fff; }
    .event-scroll::-webkit-scrollbar { height: 4px; }
    .event-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .status-btn { font-size: 9px; font-weight: 800; border: 1px solid #e2e8f0; padding: 4px 6px; border-radius: 4px; transition: all 0.2s; background: #fff; cursor: pointer; }
    .active-ns { background: #64748b; color: #fff; border-color: #64748b; }
    .active-nf { background: #f59e0b; color: #fff; border-color: #f59e0b; }
    .active-dq { background: #ef4444; color: #fff; border-color: #ef4444; }
    .input-time { border: 1px solid #e2e8f0; border-radius: 8px; text-align: center; font-weight: 800; font-family: monospace; }
    .input-time:focus { border-color: #3b82f6; outline: none; background: #f0f7ff; }
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen">
    
    <div class="flex flex-col xl:flex-row justify-between items-center mb-8 gap-6 bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-2xl font-black uppercase italic tracking-tighter text-slate-900 leading-none">Race Entry Manager</h1>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-2">Mode: <?= strtoupper($stage) ?></p>
        </div>
        
        <div class="flex flex-wrap justify-center gap-3">
            <form method="GET" class="relative">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari..." class="pl-8 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold w-32 focus:w-48 transition-all">
                <span class="absolute left-2.5 top-2.5 opacity-30 text-xs">🔍</span>
                <input type="hidden" name="category_id" value="<?= $selectedCatId ?>">
                <input type="hidden" name="stage" value="<?= $stage ?>">
            </form>

            <?php if($selectedCatId): ?>
                <a href="print_result.php?category_id=<?= $selectedCatId ?>&stage=<?= $stage ?>" target="_blank" class="bg-blue-50 text-blue-600 font-black px-6 py-3 rounded-2xl text-[10px] uppercase border border-blue-100 hover:bg-blue-600 hover:text-white transition">🖨️ Cetak</a>
                <button type="submit" form="formMaster" name="save_results" class="bg-slate-900 text-white font-black px-8 py-3 rounded-2xl text-[10px] uppercase shadow-lg hover:bg-blue-600 transition">💾 Simpan Hasil</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="flex gap-2 overflow-x-auto pb-4 mb-8 event-scroll">
        <?php foreach($categories as $c): ?>
            <a href="index.php?category_id=<?= $c['id'] ?>&stage=<?= $stage ?>&q=<?= $search ?>" 
               class="event-btn flex-none w-36 bg-white border border-slate-200 p-3 rounded-2xl text-center transition <?= $selectedCatId == $c['id'] ? 'active' : '' ?>">
                <div class="text-[9px] font-black <?= $selectedCatId == $c['id'] ? 'text-white/50' : 'text-slate-300' ?>">#<?= $c['event_no'] ?></div>
                <div class="text-[10px] font-black uppercase truncate"><?= $c['distance'] ?>M <?= $c['style'] ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if(!$selectedCatId): ?>
        <div class="text-center py-20 bg-white rounded-[2rem] border border-slate-200 border-dashed">
            <p class="font-black uppercase tracking-widest text-[10px] text-slate-300">Pilih nomor lomba di atas</p>
        </div>
    <?php else: ?>
        
        <form id="formMaster" method="POST">
            <input type="hidden" name="category_id" value="<?= $selectedCatId ?>">
            <input type="hidden" name="stage" value="<?= $stage ?>">
            <input type="hidden" name="save_results" value="1">

            <div class="space-y-12 pb-20">
                <?php foreach($heats as $h): ?>
                    <div class="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm relative">
                        <div class="flex justify-between items-end mb-2 font-black text-[11px] uppercase">
                            <span>SERI <?= $h['heat_number'] ?></span>
                            <span class="text-slate-400"><?= strtoupper($stage) ?></span>
                        </div>
                        <div class="double-line"></div>
                        
                        <div class="overflow-x-auto mt-4">
                            <table class="w-full text-left">
                                <thead class="text-[9px] font-black uppercase text-slate-400 border-b-2 border-black">
                                    <tr>
                                        <th class="py-3 w-12 text-center">LN</th>
                                        <th class="py-3">NAMA ATLET / KLUB</th>
                                        <th class="py-3 text-center">WAKTU FINISH</th>
                                        <th class="py-3 text-center">STATUS</th>
                                        <th class="py-3 text-center">RANK</th>
                                    </tr>
                                </thead>
                                <tbody class="paper-font">
                                    <?php for($i=1; $i<=$laneCount; $i++): 
                                        $l = $h['mapped_lanes'][$i] ?? null;
                                    ?>
                                    <tr class="border-b border-slate-50 h-16 <?= !$l ? 'opacity-20 bg-slate-50/50' : '' ?>">
                                        <td class="text-center font-bold text-xl text-slate-300 italic"><?= $i ?></td>
                                        <td>
                                            <?php if($l): ?>
                                                <div class="font-bold text-[13px] uppercase leading-tight"><?= htmlspecialchars($l['nama_atlet']) ?></div>
                                                <div class="text-[9px] text-slate-500 uppercase"><?= htmlspecialchars($l['nama_klub']) ?></div>
                                            <?php else: ?>
                                                <div class="text-[10px] text-slate-300 italic">-- LINTASAN KOSONG --</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if($l): ?>
                                                <input type="text" name="result[<?= $l['id'] ?>]" id="res-<?= $l['id'] ?>"
                                                       value="<?= htmlspecialchars($l['result_time'] ?? '') ?>"
                                                       class="input-time w-32 py-2 text-blue-600 text-lg"
                                                       placeholder="00:00.00" <?= ($l['status'] !== 'OK') ? 'readonly style="opacity:0.3"' : '' ?>>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if($l): ?>
                                                <div class="flex justify-center gap-1">
                                                    <input type="hidden" name="status[<?= $l['id'] ?>]" id="status-<?= $l['id'] ?>" value="<?= $l['status'] ?? 'OK' ?>">
                                                    <button type="button" onclick="toggleStatus(<?= $l['id'] ?>, 'NS')" id="btn-ns-<?= $l['id'] ?>" class="status-btn <?= $l['status']=='NS'?'active-ns':'' ?>">NS</button>
                                                    <button type="button" onclick="toggleStatus(<?= $l['id'] ?>, 'NF')" id="btn-nf-<?= $l['id'] ?>" class="status-btn <?= $l['status']=='NF'?'active-nf':'' ?>">NF</button>
                                                    <button type="button" onclick="toggleStatus(<?= $l['id'] ?>, 'DQ')" id="btn-dq-<?= $l['id'] ?>" class="status-btn <?= $l['status']=='DQ'?'active-dq':'' ?>">DQ</button>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center font-black text-slate-400">
                                            <?= ($l && $l['rank']) ? '#' . $l['rank'] : '-' ?>
                                        </td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
function toggleStatus(id, status) {
    const statusInput = document.getElementById('status-' + id);
    const timeInput = document.getElementById('res-' + id);
    const bNS = document.getElementById('btn-ns-' + id);
    const bNF = document.getElementById('btn-nf-' + id);
    const bDQ = document.getElementById('btn-dq-' + id);

    if (statusInput.value === status) {
        statusInput.value = 'OK';
        timeInput.readOnly = false;
        timeInput.style.opacity = '1';
        [bNS, bNF, bDQ].forEach(b => b.className = 'status-btn');
    } else {
        statusInput.value = status;
        timeInput.value = ''; 
        timeInput.readOnly = true;
        timeInput.style.opacity = '0.3';
        [bNS, bNF, bDQ].forEach(b => b.className = 'status-btn');
        document.getElementById('btn-' + status.toLowerCase() + '-' + id).classList.add('active-' + status.toLowerCase());
    }
}
</script>