<?php
// src/events/index.php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../../public/login.php"); exit; 
}

$adminId = $_SESSION['user_id'];

// --- AMBIL CONFIG POOL (SCM/LCM) ---
$stmtUser = $pdo->prepare("SELECT pool_type FROM users WHERE id = ?");
$stmtUser->execute([$adminId]);
$userConfig = $stmtUser->fetch();
$poolLabel = ($userConfig['pool_type'] === 'SCM') ? 'SCM' : 'LCM'; // Default LCM jika null

// ==========================================
// HANDLE POST REQUESTS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // --- A. TAMBAH KELOMPOK UMUR (MASTER DATA) ---
    if (isset($_POST['action']) && $_POST['action'] === 'add_ku') {
        try {
            $stmt = $pdo->prepare("INSERT INTO event_age_groups (event_id, group_name, min_age, max_age) VALUES (?, ?, ?, ?)");
            $stmt->execute([$adminId, strtoupper($_POST['group_name']), $_POST['min_age'], $_POST['max_age']]);
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Kelompok Umur Berhasil Ditambahkan!'];
        } catch (Exception $e) {
            $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Gagal: ' . $e->getMessage()];
        }
        header("Location: index.php"); exit;
    }

    // --- B. HAPUS KELOMPOK UMUR ---
    if (isset($_POST['action']) && $_POST['action'] === 'delete_ku') {
        $pdo->prepare("DELETE FROM event_age_groups WHERE id = ? AND event_id = ?")->execute([$_POST['id'], $adminId]);
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Kelompok Umur Dihapus'];
        header("Location: index.php"); exit;
    }

    // --- C. TAMBAH NOMOR LOMBA (EVENT NUMBER) ---
    if (isset($_POST['action']) && $_POST['action'] === 'add_event') {
        try {
            // 1. Ambil Data Form
            $nomor  = $_POST['nomor_acara'];
            $jarak  = $_POST['jarak'];
            $gaya   = $_POST['gaya'];
            $jk     = $_POST['jenis_kelamin'];
            $harga  = $_POST['biaya_pendaftaran'] ?? 50000;
            
            // 2. Proses KU yang Dipilih (Array of IDs)
            $selected_kus = $_POST['selected_kus'] ?? []; // Array ID KU
            
            if(empty($selected_kus)) {
                throw new Exception("Pilih minimal satu Kelompok Umur!");
            }

            // Ambil detail KU dari DB untuk menghitung Min/Max global & Label Nama
            $placeholders = str_repeat('?,', count($selected_kus) - 1) . '?';
            $stmtKU = $pdo->prepare("SELECT * FROM event_age_groups WHERE id IN ($placeholders)");
            $stmtKU->execute($selected_kus);
            $kuData = $stmtKU->fetchAll();

            // Hitung Min/Max Age Global untuk nomor ini (untuk validasi pendaftaran nanti)
            $globalMin = 999; 
            $globalMax = 0;
            $kuNames   = [];

            foreach($kuData as $k) {
                if($k['min_age'] < $globalMin) $globalMin = $k['min_age'];
                if($k['max_age'] > $globalMax) $globalMax = $k['max_age'];
                $kuNames[] = $k['group_name'];
            }
            
            // Gabungkan Nama KU (Misal: "KU 1, KU 2")
            $ageGroupString = implode(", ", $kuNames);
            $selectedIdsString = implode(",", $selected_kus); // Simpan "1,2,5"

            // 3. Buat Nama Event (Format Baru dengan SCM/LCM)
            // Contoh: "50 M GAYA BEBAS PUTRA - SCM"
            $labelJK = ($jk == 'L') ? 'PUTRA' : (($jk == 'P') ? 'PUTRI' : 'MIXED');
            $eventName = "$jarak M " . strtoupper($gaya) . " $labelJK - $poolLabel";

            // 4. Insert ke DB
            $sql = "INSERT INTO event_numbers 
                    (organizer_id, event_number, event_name, distance, stroke, jenis_kelamin, 
                    age_group, age_min, age_max, selected_ku_ids, price, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $adminId, $nomor, $eventName, $jarak, $gaya, $jk, 
                $ageGroupString, $globalMin, $globalMax, $selectedIdsString, $harga
            ]);

            $_SESSION['toast'] = ['type' => 'success', 'msg' => "Nomor $nomor Berhasil Dibuat!"];
            
        } catch (Exception $e) {
            $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Gagal: ' . $e->getMessage()];
        }
        header("Location: index.php"); exit;
    }

    // --- D. HAPUS NOMOR LOMBA ---
    if (isset($_POST['action']) && $_POST['action'] === 'delete_event') {
        $pdo->prepare("DELETE FROM event_numbers WHERE id = ? AND organizer_id = ?")->execute([$_POST['id'], $adminId]);
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Nomor Lomba Dihapus'];
        header("Location: index.php"); exit;
    }
}

// ==========================================
// GET DATA FOR VIEW
// ==========================================

// 1. Ambil Daftar KU (Master Data)
$kus = $pdo->prepare("SELECT * FROM event_age_groups WHERE event_id = ? ORDER BY min_age ASC");
$kus->execute([$adminId]);
$listKU = $kus->fetchAll();

// 2. Ambil Daftar Nomor Lomba
$events = $pdo->prepare("SELECT * FROM event_numbers WHERE organizer_id = ? ORDER BY event_number ASC");
$events->execute([$adminId]);
$listEvents = $events->fetchAll();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="max-w-7xl mx-auto mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-black uppercase italic text-slate-900 leading-none">Manajemen Lomba</h1>
            <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-2">
                Kolam: <span class="text-blue-600 bg-blue-100 px-2 rounded"><?= $poolLabel ?></span>
            </p>
        </div>
        
        <?php if(isset($_SESSION['toast'])): ?>
            <div class="px-4 py-2 rounded-lg text-xs font-bold shadow-lg animate-bounce 
                <?= $_SESSION['toast']['type'] == 'success' ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white' ?>">
                <?= $_SESSION['toast']['msg'] ?>
            </div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    </div>

    <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <div class="lg:col-span-4 space-y-6">
            
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200">
                <h3 class="font-black uppercase text-xs text-slate-400 mb-4 tracking-widest">1. Buat Kelompok Umur</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_ku">
                    
                    <div>
                        <input type="text" name="group_name" placeholder="Nama Group (e.g., KU 1)" class="w-full font-bold text-sm border-b-2 border-slate-200 focus:border-blue-600 outline-none py-2 uppercase" required>
                    </div>
                    <div class="flex gap-2">
                        <input type="number" name="min_age" placeholder="Min" class="w-full font-bold text-sm border-b-2 border-slate-200 focus:border-blue-600 outline-none py-2" required>
                        <input type="number" name="max_age" placeholder="Max" class="w-full font-bold text-sm border-b-2 border-slate-200 focus:border-blue-600 outline-none py-2" required>
                    </div>
                    <button type="submit" class="w-full py-3 bg-slate-800 text-white text-xs font-bold uppercase rounded-xl hover:bg-slate-900 transition">
                        + Simpan KU
                    </button>
                </form>
            </div>

            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200 max-h-[500px] overflow-y-auto">
                <h3 class="font-black uppercase text-xs text-slate-400 mb-4 tracking-widest">Daftar KU Tersedia</h3>
                
                <?php if(empty($listKU)): ?>
                    <p class="text-xs text-slate-300 italic text-center py-4">Belum ada data KU</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach($listKU as $ku): ?>
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100 group hover:border-blue-200 transition">
                            <div>
                                <h4 class="font-black text-sm text-slate-700"><?= htmlspecialchars($ku['group_name']) ?></h4>
                                <p class="text-[10px] font-bold text-slate-400">Umur: <?= $ku['min_age'] ?> - <?= $ku['max_age'] ?> Th</p>
                            </div>
                            <form method="POST" onsubmit="return confirm('Hapus KU ini?');">
                                <input type="hidden" name="action" value="delete_ku">
                                <input type="hidden" name="id" value="<?= $ku['id'] ?>">
                                <button type="submit" class="text-slate-300 hover:text-red-500 font-bold text-lg px-2">&times;</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <div class="lg:col-span-8 space-y-8">

            <div class="bg-white p-8 rounded-[2.5rem] shadow-xl shadow-blue-900/5 border border-slate-200 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-5 text-8xl rotate-12">🏊</div>
                
                <h3 class="font-black uppercase text-xs text-blue-600 mb-6 tracking-widest relative z-10">2. Buat Nomor Lomba Baru</h3>
                
                <form method="POST" class="relative z-10">
                    <input type="hidden" name="action" value="add_event">
                    
                    <div class="grid grid-cols-12 gap-4 mb-4">
                        <div class="col-span-3">
                            <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">No. Acara</label>
                            <input type="text" name="nomor_acara" placeholder="101" class="w-full bg-slate-50 border-2 border-slate-100 rounded-xl px-4 py-3 font-black text-xl text-center focus:border-blue-500 outline-none" required>
                        </div>
                        <div class="col-span-3">
                            <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Jarak</label>
                            <select name="jarak" class="w-full bg-slate-50 border-2 border-slate-100 rounded-xl px-3 py-3 font-bold text-sm outline-none">
                                <option value="25">25 m</option>
                                <option value="50" selected>50 m</option>
                                <option value="100">100 m</option>
                                <option value="200">200 m</option>
                                <option value="400">400 m</option>
                                <option value="800">800 m</option>
                                <option value="1500">1500 m</option>
                            </select>
                        </div>
                        <div class="col-span-6">
                            <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Gaya</label>
                            <select name="gaya" class="w-full bg-slate-50 border-2 border-slate-100 rounded-xl px-3 py-3 font-bold text-sm outline-none uppercase">
                                <option value="Gaya Bebas">Gaya Bebas</option>
                                <option value="Gaya Dada">Gaya Dada</option>
                                <option value="Gaya Punggung">Gaya Punggung</option>
                                <option value="Gaya Kupu-kupu">Gaya Kupu-kupu</option>
                                <option value="Gaya Ganti">Gaya Ganti</option>
                                <option value="Kick Bebas">Kick Bebas</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Jenis Kelamin</label>
                            <div class="flex gap-2">
                                <label class="cursor-pointer">
                                    <input type="radio" name="jenis_kelamin" value="L" class="peer sr-only" checked>
                                    <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold text-slate-500 peer-checked:bg-blue-600 peer-checked:text-white transition">Putra</div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="jenis_kelamin" value="P" class="peer sr-only">
                                    <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold text-slate-500 peer-checked:bg-pink-500 peer-checked:text-white transition">Putri</div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="jenis_kelamin" value="Mixed" class="peer sr-only">
                                    <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold text-slate-500 peer-checked:bg-purple-600 peer-checked:text-white transition">Mixed</div>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Biaya (Rp)</label>
                            <input type="number" name="biaya_pendaftaran" value="50000" class="w-full bg-slate-50 border-2 border-slate-100 rounded-xl px-4 py-2 font-bold text-sm outline-none">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-[9px] font-bold text-slate-400 uppercase mb-2">Pilih Kelompok Umur Yang Berlomba (Bisa Lebih Dari 1)</label>
                        
                        <?php if(empty($listKU)): ?>
                            <div class="p-4 bg-red-50 text-red-600 text-xs font-bold rounded-xl border border-red-100 flex items-center gap-2">
                                ⚠️ Buat Kelompok Umur (KU) di kolom sebelah kiri dulu!
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                <?php foreach($listKU as $ku): ?>
                                <label class="cursor-pointer relative group">
                                    <input type="checkbox" name="selected_kus[]" value="<?= $ku['id'] ?>" class="peer sr-only">
                                    <div class="p-3 bg-slate-50 border-2 border-slate-100 rounded-xl text-center hover:bg-white hover:shadow-sm transition peer-checked:border-blue-600 peer-checked:bg-blue-50">
                                        <span class="block text-xs font-black text-slate-700 peer-checked:text-blue-700"><?= $ku['group_name'] ?></span>
                                        <span class="text-[9px] text-slate-400 font-bold peer-checked:text-blue-400"><?= $ku['min_age'] ?>-<?= $ku['max_age'] ?> Th</span>
                                    </div>
                                    <div class="absolute top-1 right-1 w-2 h-2 bg-blue-600 rounded-full opacity-0 peer-checked:opacity-100 transition"></div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="w-full py-4 bg-blue-600 text-white font-black uppercase tracking-widest rounded-2xl shadow-lg shadow-blue-200 hover:bg-blue-700 hover:-translate-y-1 transition transform text-sm">
                        Simpan Nomor Lomba
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="font-black uppercase text-xs text-slate-500 tracking-widest">Database Nomor Lomba</h3>
                </div>
                
                <?php if(empty($listEvents)): ?>
                    <div class="p-10 text-center">
                        <p class="text-slate-300 font-bold text-sm italic">Belum ada nomor lomba dibuat.</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100">
                        <?php foreach($listEvents as $ev): 
                             // Logic Warna Badge
                             $bgBadge = ($ev['jenis_kelamin'] == 'L') ? 'bg-blue-100 text-blue-700' : 
                                       (($ev['jenis_kelamin'] == 'P') ? 'bg-pink-100 text-pink-700' : 'bg-purple-100 text-purple-700');
                        ?>
                        <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50 transition">
                            
                            <div class="flex items-center gap-4">
                                <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center font-black text-xl text-slate-700 italic border border-slate-200 shadow-sm">
                                    <?= $ev['event_number'] ?>
                                </div>
                                <div>
                                    <h4 class="font-black text-sm text-slate-800 uppercase tracking-tight">
                                        <?= htmlspecialchars($ev['event_name']) ?>
                                    </h4>
                                    <div class="flex flex-wrap gap-2 mt-1">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded <?= $bgBadge ?>">
                                            <?= $ev['jenis_kelamin'] == 'L' ? 'PUTRA' : ($ev['jenis_kelamin'] == 'P' ? 'PUTRI' : 'MIXED') ?>
                                        </span>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-200 text-slate-600">
                                            <?= $ev['age_group'] ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <form method="POST" onsubmit="return confirm('Hapus Nomor <?= $ev['event_number'] ?>?');">
                                <input type="hidden" name="action" value="delete_event">
                                <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                                <button type="submit" class="text-slate-300 hover:text-red-500 font-bold text-sm bg-white border border-slate-200 hover:bg-red-50 hover:border-red-200 px-3 py-2 rounded-xl transition">
                                    Hapus
                                </button>
                            </form>

                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>
</div>