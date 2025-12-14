<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

// Proteksi Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

// --- 1. HANDLE TAMBAH NOMOR LOMBA ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    try {
        $event_no = $_POST['event_no'];
        $event_date = $_POST['event_date'];
        $gender = $_POST['gender'];
        $distance = $_POST['distance'];
        $style = $_POST['style'];
        $age = $_POST['age_group'];
        $price = $_POST['price'];

        $sql = "INSERT INTO event_categories (user_id, event_no, event_date, gender, distance, style, age_group, price) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$uid, $event_no, $event_date, $gender, $distance, $style, $age, $price]);

        $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Acara #' . $event_no . ' berhasil ditambahkan!';
    } catch (Exception $e) {
        $_SESSION['toast_type'] = 'error'; $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage();
    }
    header("Location: index.php"); exit;
}

// --- 2. HANDLE HAPUS ---
if (isset($_POST['delete_id'])) {
    $pdo->prepare("DELETE FROM event_categories WHERE id = ? AND user_id = ?")->execute([$_POST['delete_id'], $uid]);
    $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Nomor lomba telah dihapus.';
    header("Location: index.php"); exit;
}

// --- 3. AMBIL DATA PENDUKUNG ---
// Ambil Tanggal Event dari profil untuk limitasi input
$eventInfo = $pdo->query("SELECT event_start_date, event_end_date FROM users WHERE id = $uid")->fetch();

// Ambil KU yang tersedia
$stmtKU = $pdo->prepare("SELECT group_name FROM event_age_groups WHERE event_id = ? ORDER BY min_age DESC");
$stmtKU->execute([$uid]);
$availableKU = $stmtKU->fetchAll();

// Ambil Daftar Nomor (Urut berdasarkan Nomor Acara)
$items = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY event_no ASC");
$items->execute([$uid]);
$categories = $items->fetchAll();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter italic">Order of Events</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest">Susunan Nomor Acara Pertandingan</p>
        </div>
        <div class="bg-white border-2 border-slate-200 px-6 py-3 rounded-2xl font-black text-xs uppercase tracking-widest text-slate-600 shadow-sm">
            Total: <?= count($categories) ?> Acara
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-10">
        
        <div class="xl:col-span-1">
            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden sticky top-28">
                <div class="bg-slate-900 px-8 py-6 text-white">
                    <h3 class="font-black text-xs uppercase tracking-[0.2em] italic">➕ Buat Acara Baru</h3>
                </div>
                
                <form method="POST" class="p-8 space-y-5">
                    <input type="hidden" name="add_category" value="1">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">No. Acara</label>
                            <input type="number" name="event_no" placeholder="Contoh: 1" class="w-full px-5 py-3 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-lg focus:bg-white focus:border-blue-500 transition outline-none" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Tanggal</label>
                            <input type="date" name="event_date" value="<?= $eventInfo['event_start_date'] ?>" class="w-full px-4 py-3 border-2 border-slate-50 bg-slate-50 rounded-2xl font-bold text-xs focus:bg-white focus:border-blue-500 transition outline-none" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Kelompok Umur (KU)</label>
                        <select name="age_group" class="w-full px-5 py-3 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-xs uppercase tracking-widest focus:bg-white focus:border-blue-500 transition outline-none cursor-pointer" required>
                            <option value="">-- PILIH KU --</option>
                            <?php foreach($availableKU as $ku): ?>
                                <option value="<?= htmlspecialchars($ku['group_name']) ?>"><?= htmlspecialchars($ku['group_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Gender</label>
                            <select name="gender" class="w-full px-5 py-3 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-[10px] uppercase tracking-widest focus:bg-white focus:border-blue-500 transition outline-none cursor-pointer" required>
                                <option value="Male">Putra</option>
                                <option value="Female">Putri</option>
                                <option value="Mixed">Mixed</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Jarak</label>
                            <select name="distance" class="w-full px-5 py-3 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-xs uppercase focus:bg-white focus:border-blue-500 transition outline-none cursor-pointer" required>
                                <option value="50">50m</option>
                                <option value="100">100m</option>
                                <option value="200">200m</option>
                                <option value="400">400m</option>
                                <option value="800">800m</option>
                                <option value="1500">1500m</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Gaya Renang</label>
                        <select name="style" class="w-full px-5 py-3 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-[10px] uppercase tracking-widest focus:bg-white focus:border-blue-500 transition outline-none cursor-pointer" required>
                            <option value="Gaya Bebas">Gaya Bebas</option>
                            <option value="Gaya Dada">Gaya Dada</option>
                            <option value="Gaya Punggung">Gaya Punggung</option>
                            <option value="Gaya Kupu-kupu">Gaya Kupu-kupu</option>
                            <option value="Gaya Ganti">Gaya Ganti</option>
                            <option value="Estafet Bebas">Estafet Bebas</option>
                            <option value="Estafet Ganti">Estafet Ganti</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Biaya (Rp)</label>
                        <input type="number" name="price" class="w-full px-5 py-3 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-sm focus:bg-white focus:border-blue-500 transition outline-none" value="125000" required>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-3xl shadow-xl shadow-blue-100 transition transform hover:-translate-y-1 uppercase tracking-widest text-xs mt-4">
                        🚀 Simpan Acara
                    </button>
                </form>
            </div>
        </div>

        <div class="xl:col-span-2">
            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-8 py-5 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="font-black text-slate-400 text-[10px] uppercase tracking-[0.2em] italic">Event Schedule List</h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-400 border-b border-slate-100">
                            <tr>
                                <th class="px-8 py-4 text-center w-16">#</th>
                                <th class="px-8 py-4">Tanggal / Sesi</th>
                                <th class="px-8 py-4">KU</th>
                                <th class="px-8 py-4">Pertandingan</th>
                                <th class="px-8 py-4">Gender</th>
                                <th class="px-8 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if(empty($categories)): ?>
                                <tr><td colspan="6" class="p-20 text-center text-slate-300 font-black uppercase text-xs">Belum ada acara.</td></tr>
                            <?php else: foreach($categories as $c): ?>
                                <tr class="hover:bg-blue-50/50 transition">
                                    <td class="px-8 py-5 text-center font-black text-slate-900 bg-slate-50/50">
                                        <?= $c['event_no'] ?>
                                    </td>
                                    <td class="px-8 py-5">
                                        <div class="text-[10px] font-black text-slate-700 uppercase">
                                            <?= ($c['event_date']) ? date('d M Y', strtotime($c['event_date'])) : '-' ?>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5 font-black text-slate-800 text-[11px] italic">
                                        <?= htmlspecialchars($c['age_group']) ?>
                                    </td>
                                    <td class="px-8 py-5">
                                        <div class="font-black text-slate-900 text-sm uppercase italic tracking-tighter">
                                            <?= $c['distance'] ?>m <?= htmlspecialchars($c['style']) ?>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <span class="px-3 py-1 rounded-xl text-[9px] font-black uppercase border <?= $c['gender'] == 'Male' ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-pink-50 text-pink-600 border-pink-100' ?>">
                                            <?= $c['gender'] == 'Male' ? 'Putra' : ($c['gender'] == 'Female' ? 'Putri' : 'Mixed') ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-5 text-right">
                                        <form method="POST" onsubmit="return confirm('Hapus acara ini?')">
                                            <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                                            <button class="w-10 h-10 bg-red-50 text-red-400 hover:bg-red-500 hover:text-white rounded-xl transition flex items-center justify-center mx-auto">✕</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>