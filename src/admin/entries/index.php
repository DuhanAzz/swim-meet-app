<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$selectedCatId = $_GET['category_id'] ?? 0;
$search = $_GET['q'] ?? '';

// 1. Ambil List Kategori untuk Filter
$stmtCat = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY age_group ASC, gender DESC");
$stmtCat->execute([$uid]);
$categories = $stmtCat->fetchAll();

// 2. Ambil Statistik
$countClubs = $pdo->prepare("SELECT COUNT(DISTINCT club_id) FROM event_entries WHERE event_id = ?");
$countClubs->execute([$uid]);
$totalClubs = $countClubs->fetchColumn();

$countEntries = $pdo->prepare("SELECT COUNT(*) FROM event_entries WHERE event_id = ?");
$countEntries->execute([$uid]);
$totalEntries = $countEntries->fetchColumn();

// 3. Query Data Peserta (MENGGUNAKAN tanggal_lahir)
$sql = "SELECT ee.*, s.nama_atlet, s.tanggal_lahir, u.nama_lengkap as nama_klub, 
               ec.age_group, ec.distance, ec.style, ec.gender
        FROM event_entries ee
        JOIN swimmers s ON ee.swimmer_id = s.id
        JOIN users u ON ee.club_id = u.id
        JOIN event_categories ec ON ee.category_id = ec.id
        WHERE ee.event_id = ?";

$params = [$uid];
if ($selectedCatId) {
    $sql .= " AND ee.category_id = ?";
    $params[] = $selectedCatId;
}
if ($search) {
    $sql .= " AND (s.nama_atlet LIKE ? OR u.nama_lengkap LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY ec.age_group ASC, s.nama_atlet ASC";
$stmtEntries = $pdo->prepare($sql);
$stmtEntries->execute($params);
$entries = $stmtEntries->fetchAll();

// 4. Handle Hapus
if (isset($_POST['delete_entry'])) {
    $pdo->prepare("DELETE FROM event_entries WHERE id = ?")->execute([$_POST['entry_id']]);
    header("Location: index.php?category_id=$selectedCatId&msg=deleted"); exit;
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6">
        <div>
            <h1 class="text-3xl font-black uppercase tracking-tighter italic text-slate-900">Participant Data</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest">Manajemen Database Pendaftaran</p>
        </div>
        
        <div class="flex gap-4">
            <div class="bg-white p-5 rounded-[2rem] border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-2xl">🏰</div>
                <div>
                    <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Klub</div>
                    <div class="font-black text-xl text-slate-900"><?= $totalClubs ?></div>
                </div>
            </div>
            <div class="bg-white p-5 rounded-[2rem] border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 bg-orange-50 rounded-2xl flex items-center justify-center text-2xl">🏊</div>
                <div>
                    <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Entries</div>
                    <div class="font-black text-xl text-slate-900"><?= $totalEntries ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-[2.5rem] border border-slate-200 shadow-sm mb-10">
        <form method="GET" class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-end">
            <div class="lg:col-span-1">
                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Filter Nomor Lomba</label>
                <select name="category_id" onchange="this.form.submit()" class="w-full p-4 border-2 border-slate-50 rounded-2xl font-black text-slate-700 uppercase text-xs focus:border-blue-500 transition outline-none bg-slate-50">
                    <option value="">-- SEMUA NOMOR --</option>
                    <?php foreach($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selectedCatId == $c['id'] ? 'selected' : '' ?>>
                            <?= $c['age_group'] ?> - <?= $c['distance'] ?>m <?= $c['style'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lg:col-span-1">
                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Cari Atlet / Klub</label>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Ketik nama..." class="w-full p-4 border-2 border-slate-50 rounded-2xl font-bold text-xs focus:border-blue-500 transition outline-none bg-slate-50">
            </div>
            <div class="lg:col-span-1">
                <button type="submit" class="w-full bg-slate-900 text-white font-black py-4 rounded-2xl text-[10px] uppercase tracking-widest hover:bg-blue-600 transition shadow-lg">Terapkan Filter</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-[2.5rem] border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest">
                    <tr>
                        <th class="px-8 py-5">Atlet & Tgl Lahir</th>
                        <th class="px-8 py-5">Klub / Sekolah</th>
                        <th class="px-8 py-5 text-center">Nomor Lomba</th>
                        <th class="px-8 py-5 text-center">Entry Time</th>
                        <th class="px-8 py-5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($entries)): ?>
                        <tr><td colspan="5" class="px-8 py-20 text-center font-black text-slate-300 uppercase text-xs">Data tidak ditemukan</td></tr>
                    <?php else: foreach($entries as $e): ?>
                        <tr class="hover:bg-blue-50/30 transition h-20">
                            <td class="px-8 py-4">
                                <div class="font-black uppercase text-slate-800 text-sm"><?= htmlspecialchars($e['nama_atlet']) ?></div>
                                <div class="text-[9px] font-bold text-slate-400 uppercase mt-1"><?= ($e['tanggal_lahir']) ? date('d M Y', strtotime($e['tanggal_lahir'])) : '-' ?></div>
                            </td>
                            <td class="px-8 py-4">
                                <span class="bg-slate-100 px-4 py-1.5 rounded-full text-[9px] font-black text-slate-600 uppercase tracking-tighter">
                                    🏰 <?= htmlspecialchars($e['nama_klub']) ?>
                                </span>
                            </td>
                            <td class="px-8 py-4 text-center">
                                <div class="font-bold text-slate-700"><?= $e['distance'] ?>m <?= $e['style'] ?></div>
                                <div class="text-[9px] font-black text-blue-500 uppercase"><?= $e['age_group'] ?></div>
                            </td>
                            <td class="px-8 py-4 text-center font-mono font-black text-blue-600 text-base"><?= $e['entry_time'] ?></td>
                            <td class="px-8 py-4 text-right">
                                <form method="POST" onsubmit="return confirm('Hapus pendaftaran ini?')">
                                    <input type="hidden" name="entry_id" value="<?= $e['id'] ?>">
                                    <input type="hidden" name="delete_entry" value="1">
                                    <button class="w-10 h-10 bg-red-50 text-red-500 rounded-xl hover:bg-red-500 hover:text-white transition shadow-sm font-black text-xs">✕</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>