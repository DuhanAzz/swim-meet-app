<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    try {
        $gender = $_POST['gender'];
        $distance = $_POST['distance'];
        $style = $_POST['style'];
        $age = $_POST['age_group'];
        $price = $_POST['price'];

        $sql = "INSERT INTO event_categories (user_id, gender, distance, style, age_group, price) VALUES (?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$uid, $gender, $distance, $style, $age, $price]);

        $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Nomor lomba ditambahkan!';
    } catch (Exception $e) {
        $_SESSION['toast_type'] = 'error'; $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage();
    }
    header("Location: index.php"); exit;
}

if (isset($_POST['delete_id'])) {
    $pdo->prepare("DELETE FROM event_categories WHERE id = ? AND user_id = ?")->execute([$_POST['delete_id'], $uid]);
    $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Nomor dihapus.';
    header("Location: index.php"); exit;
}

$items = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? ORDER BY age_group ASC, gender DESC, style ASC");
$items->execute([$uid]);
$categories = $items->fetchAll();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Kelola Nomor Lomba</h1>
            <p class="text-sm text-slate-500">Tentukan nomor-nomor pertandingan yang dibuka untuk pendaftaran.</p>
        </div>
        <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg font-bold text-xs">
            Total Nomor: <?= count($categories) ?>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        
        <div class="xl:col-span-1">
            <div class="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden sticky top-24">
                <div class="bg-slate-800 px-6 py-4 border-b border-slate-700">
                    <h3 class="text-white font-bold text-sm uppercase tracking-wider">➕ Tambah Nomor Baru</h3>
                </div>
                <form method="POST" class="p-6 space-y-5">
                    <input type="hidden" name="add_category" value="1">
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Kelompok Umur (KU)</label>
                        <select name="age_group" class="w-full px-4 py-2 border rounded-lg text-sm font-bold text-slate-700 focus:ring-2 focus:ring-blue-500" required>
                            <option value="">-- Pilih KU --</option>
                            <option value="Senior (19+ Th)">Senior (19+ Th)</option>
                            <option value="Grup 1 (16-18 Th)">Grup 1 (16-18 Th)</option>
                            <option value="Grup 2 (14-15 Th)">Grup 2 (14-15 Th)</option>
                            <option value="Grup 3 (12-13 Th)">Grup 3 (12-13 Th)</option>
                            <option value="Grup 4 (10-11 Th)">Grup 4 (10-11 Th)</option>
                            <option value="Grup 5 (< 10 Th)">Grup 5 (< 10 Th)</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Gender</label>
                            <select name="gender" class="w-full px-4 py-2 border rounded-lg text-sm" required>
                                <option value="Male">Putra 🚹</option>
                                <option value="Female">Putri 🚺</option>
                                <option value="Mixed">Campuran 🚻</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Jarak (Meter)</label>
                            <select name="distance" class="w-full px-4 py-2 border rounded-lg text-sm" required>
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
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Gaya Renang</label>
                        <select name="style" class="w-full px-4 py-2 border rounded-lg text-sm" required>
                            <option value="Gaya Bebas">Gaya Bebas (Freestyle)</option>
                            <option value="Gaya Dada">Gaya Dada (Breaststroke)</option>
                            <option value="Gaya Punggung">Gaya Punggung (Backstroke)</option>
                            <option value="Gaya Kupu-kupu">Gaya Kupu (Butterfly)</option>
                            <option value="Gaya Ganti">Gaya Ganti (Medley)</option>
                            <option value="Estafet Bebas">Estafet Bebas</option>
                            <option value="Estafet Ganti">Estafet Ganti</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Biaya Pendaftaran (Rp)</label>
                        <input type="number" name="price" class="w-full px-4 py-2 border rounded-lg text-sm" value="125000" required>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg shadow-lg transition transform hover:-translate-y-0.5">
                        Simpan Nomor
                    </button>
                </form>
            </div>
        </div>

        <div class="xl:col-span-2">
            <div class="bg-white rounded-xl shadow-md border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider">Daftar Nomor Pertandingan</h3>
                </div>
                
                <?php if(empty($categories)): ?>
                    <div class="p-12 text-center">
                        <span class="text-4xl block mb-2 opacity-30">🏊</span>
                        <p class="text-slate-400 font-bold">Belum ada nomor lomba dibuat.</p>
                        <p class="text-xs text-slate-400">Silakan input di form sebelah kiri.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-100 text-slate-500 font-bold uppercase text-xs border-b border-slate-200">
                                <tr>
                                    <th class="px-6 py-3">Kelompok Umur</th>
                                    <th class="px-6 py-3">Nomor Lomba</th>
                                    <th class="px-6 py-3">Gender</th>
                                    <th class="px-6 py-3">Biaya</th>
                                    <th class="px-6 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($categories as $c): ?>
                                <tr class="hover:bg-blue-50 transition">
                                    <td class="px-6 py-3 font-bold text-slate-700 text-xs">
                                        <?= htmlspecialchars($c['age_group']) ?>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="font-bold text-slate-800">
                                            <?= $c['distance'] ?>m <?= htmlspecialchars($c['style']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <?php if($c['gender'] == 'Male'): ?>
                                            <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-[10px] font-bold uppercase">Putra</span>
                                        <?php elseif($c['gender'] == 'Female'): ?>
                                            <span class="bg-pink-100 text-pink-700 px-2 py-1 rounded text-[10px] font-bold uppercase">Putri</span>
                                        <?php else: ?>
                                            <span class="bg-purple-100 text-purple-700 px-2 py-1 rounded text-[10px] font-bold uppercase">Mix</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-3 font-mono text-slate-600 text-xs">
                                        Rp <?= number_format($c['price'], 0, ',', '.') ?>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <form method="POST" onsubmit="return confirm('Hapus nomor ini?')">
                                            <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                                            <button class="text-red-400 hover:text-red-600 font-bold px-2 py-1 hover:bg-red-50 rounded transition">✕</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
