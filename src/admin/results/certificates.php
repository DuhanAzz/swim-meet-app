<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$selectedCatId = $_GET['category_id'] ?? 0;

// 1. Ambil Menu Kategori
$stmtCat = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY age_group ASC, gender DESC");
$stmtCat->execute([$uid]);
$categories = $stmtCat->fetchAll();

// 2. Ambil Juara (Rank 1, 2, 3)
$winners = [];
if ($selectedCatId) {
    $sql = "SELECT rl.*, s.nama_atlet, u.nama_lengkap as nama_klub, 
                   ec.distance, ec.style, ec.age_group, ec.gender
            FROM race_lines rl
            JOIN race_heats rh ON rl.heat_id = rh.id
            JOIN swimmers s ON rl.swimmer_id = s.id
            JOIN event_categories ec ON rh.category_id = ec.id
            JOIN event_entries ee ON (rl.swimmer_id = ee.swimmer_id AND ec.id = ee.category_id)
            JOIN users u ON ee.club_id = u.id
            WHERE ec.id = ? AND rl.rank IN (1, 2, 3)
            AND (rh.stage = 'Final' OR (rh.stage = 'Prelims' AND NOT EXISTS (
                SELECT 1 FROM race_heats rh2 WHERE rh2.category_id = rh.category_id AND rh2.stage = 'Final'
            )))
            ORDER BY rl.rank ASC";
    $stmtW = $pdo->prepare($sql);
    $stmtW->execute([$selectedCatId]);
    $winners = $stmtW->fetchAll();
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="mb-10">
        <h1 class="text-3xl font-black uppercase italic tracking-tight text-slate-800">E-Certificate Manager</h1>
        <p class="text-sm text-slate-500 font-bold uppercase tracking-widest">Cetak sertifikat juara otomatis</p>
    </div>

    <div class="bg-white p-6 rounded-[2.5rem] border border-slate-200 shadow-sm mb-10">
        <label class="block text-[10px] font-black text-slate-400 uppercase mb-3 ml-1">Pilih Nomor Lomba</label>
        <select onchange="window.location.href='certificates.php?category_id='+this.value" class="w-full p-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-slate-700 uppercase text-sm focus:bg-white focus:border-blue-500 transition outline-none cursor-pointer">
            <option value="">-- PILIH NOMOR UNTUK CETAK SERTIFIKAT --</option>
            <?php foreach($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $selectedCatId == $c['id'] ? 'selected' : '' ?>>
                    <?= $c['age_group'] ?> - <?= $c['distance'] ?>m <?= $c['style'] ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($selectedCatId): ?>
    <div class="grid grid-cols-1 gap-6">
        <?php if (empty($winners)): ?>
            <div class="text-center py-20 bg-white rounded-[3rem] border-2 border-dashed border-slate-200">
                <p class="text-slate-400 font-black uppercase text-xs">Belum ada pemenang yang ditentukan di nomor ini.</p>
            </div>
        <?php else: ?>
            <?php foreach($winners as $w): ?>
                <div class="bg-white p-8 rounded-[2.5rem] border border-slate-200 shadow-sm flex flex-col md:flex-row justify-between items-center gap-6">
                    <div class="flex items-center gap-6">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center text-3xl shadow-inner
                            <?= $w['rank'] == 1 ? 'bg-yellow-100 text-yellow-600' : ($w['rank'] == 2 ? 'bg-slate-100 text-slate-500' : 'bg-amber-100 text-amber-700') ?>">
                            <?= $w['rank'] == 1 ? '🥇' : ($w['rank'] == 2 ? '🥈' : '🥉') ?>
                        </div>
                        <div>
                            <div class="text-[10px] font-black text-blue-600 uppercase tracking-widest mb-1">Peringkat <?= $w['rank'] ?></div>
                            <h3 class="font-black text-xl text-slate-800 uppercase italic"><?= htmlspecialchars($w['nama_atlet']) ?></h3>
                            <p class="text-xs font-bold text-slate-400 uppercase"><?= htmlspecialchars($w['nama_klub']) ?> • <?= $w['result_time'] ?></p>
                        </div>
                    </div>
                    
                    <a href="print_certificate.php?id=<?= $w['id'] ?>" target="_blank" class="bg-slate-900 text-white font-black px-10 py-4 rounded-2xl text-[10px] uppercase tracking-widest hover:bg-blue-600 transition shadow-xl transform active:scale-95 flex items-center gap-3">
                        <span>📜</span> Cetak Sertifikat
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>