<?php
// FILE: src/events/index.php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../../public/login.php"); exit; 
}

$adminId = $_SESSION['user_id'];

// --- 0. AMBIL DATA EVENT TERAKHIR (AKTIF) ---
// Kita ambil event terakhir yang dibuat oleh admin ini
$stmtEvent = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmtEvent->execute([$adminId]);
$activeEvent = $stmtEvent->fetch();

// Jika belum ada event sama sekali, set eventId = 0
$eventId = $activeEvent['id'] ?? 0;

// Config Pool (Label)
$poolLabel = ($activeEvent['pool_type'] ?? 'LCM') === 'SCM' ? 'SCM' : 'LCM';

// ==========================================
// HANDLE POST REQUESTS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Pastikan ada event yang aktif sebelum menyimpan data
    if ($eventId == 0) {
        $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Buat Event Dulu di Menu Settings!'];
        header("Location: index.php"); exit;
    }

    // --- A. UPDATE KONFIGURASI HARGA ---
    if (isset($_POST['action']) && $_POST['action'] === 'update_pricing') {
        try {
            $mode = $_POST['pricing_mode']; 
            $pkgPrice = !empty($_POST['package_price']) ? $_POST['package_price'] : 0;
            $pkgLimit = !empty($_POST['package_limit']) ? $_POST['package_limit'] : 0;
            $pkgExtra = !empty($_POST['extra_price']) ? $_POST['extra_price'] : 0;

            $sql = "UPDATE events SET 
                    pricing_mode = ?, 
                    package_price = ?, 
                    package_limit = ?, 
                    extra_price = ? 
                    WHERE id = ? AND user_id = ?";
            
            $pdo->prepare($sql)->execute([$mode, $pkgPrice, $pkgLimit, $pkgExtra, $eventId, $adminId]);
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Aturan Harga Berhasil Disimpan!'];
        } catch (Exception $e) {
            $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Gagal Update Harga: ' . $e->getMessage()];
        }
        header("Location: index.php"); exit;
    }

    // --- B. TAMBAH KELOMPOK UMUR ---
    if (isset($_POST['action']) && $_POST['action'] === 'add_ku') {
        try {
            // [FIX] Menggunakan $eventId agar KU terikat ke Event spesifik, bukan cuma ke User
            $stmt = $pdo->prepare("INSERT INTO event_age_groups (event_id, group_name, min_age, max_age) VALUES (?, ?, ?, ?)");
            $stmt->execute([$eventId, strtoupper($_POST['group_name']), $_POST['min_age'], $_POST['max_age']]);
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Kelompok Umur Berhasil Ditambahkan!'];
        } catch (Exception $e) {
            $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Gagal: ' . $e->getMessage()];
        }
        header("Location: index.php"); exit;
    }

    // --- C. HAPUS KELOMPOK UMUR ---
    if (isset($_POST['action']) && $_POST['action'] === 'delete_ku') {
        // [FIX] Hapus berdasarkan ID dan Event ID agar aman
        $pdo->prepare("DELETE FROM event_age_groups WHERE id = ? AND event_id = ?")->execute([$_POST['id'], $eventId]);
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Kelompok Umur Dihapus'];
        header("Location: index.php"); exit;
    }

    // --- D. TAMBAH NOMOR LOMBA ---
    if (isset($_POST['action']) && $_POST['action'] === 'add_event') {
        try {
            // 1. Ambil Data Form
            $nomor  = $_POST['nomor_acara'];
            $jarak  = $_POST['jarak'];
            $gaya   = $_POST['gaya'];
            $jk     = $_POST['jenis_kelamin'];
            $harga  = (float)($_POST['biaya_pendaftaran'] ?? 0);
            
            // 2. Validasi KU
            $selected_kus = $_POST['selected_kus'] ?? []; 
            if(empty($selected_kus)) throw new Exception("Pilih minimal satu Kelompok Umur!");

            // 3. Ambil detail KU untuk digabung namanya
            $placeholders = str_repeat('?,', count($selected_kus) - 1) . '?';
            $stmtKU = $pdo->prepare("SELECT * FROM event_age_groups WHERE id IN ($placeholders)");
            $stmtKU->execute($selected_kus);
            $kuData = $stmtKU->fetchAll();

            $globalMin = 999; $globalMax = 0; $kuNames = [];
            foreach($kuData as $k) {
                if($k['min_age'] < $globalMin) $globalMin = $k['min_age'];
                if($k['max_age'] > $globalMax) $globalMax = $k['max_age'];
                $kuNames[] = $k['group_name'];
            }
            
            $ageGroupString = implode(", ", $kuNames);
            $selectedIdsString = implode(",", $selected_kus);

            // 4. Buat Nama Event Otomatis
            $labelJK = ($jk == 'L') ? 'PUTRA' : (($jk == 'P') ? 'PUTRI' : 'MIXED');
            $eventName = "$jarak M " . strtoupper($gaya) . " $labelJK - $poolLabel";

            // 5. Insert Database
            // [FIX] Kita isi kolom 'event_id' supaya data tidak NULL lagi
            $sql = "INSERT INTO event_numbers 
                    (organizer_id, event_id, event_number, event_name, distance, stroke, jenis_kelamin, 
                    age_group, age_min, age_max, selected_ku_ids, price, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $adminId, $eventId, $nomor, $eventName, $jarak, $gaya, $jk, 
                $ageGroupString, $globalMin, $globalMax, $selectedIdsString, $harga
            ]);

            $_SESSION['toast'] = ['type' => 'success', 'msg' => "Nomor $nomor Berhasil Dibuat!"];
            
        } catch (Exception $e) {
            $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Gagal: ' . $e->getMessage()];
        }
        header("Location: index.php"); exit;
    }

    // --- E. HAPUS NOMOR LOMBA ---
    if (isset($_POST['action']) && $_POST['action'] === 'delete_event') {
        // [FIX] Hapus juga memastikan event_id cocok (opsional tapi lebih aman)
        $pdo->prepare("DELETE FROM event_numbers WHERE id = ? AND organizer_id = ?")->execute([$_POST['id'], $adminId]);
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Nomor Lomba Dihapus'];
        header("Location: index.php"); exit;
    }
}

// ==========================================
// GET DATA FOR VIEW (READ)
// ==========================================

// 1. Ambil KU khusus untuk Event ID ini saja
$kus = $pdo->prepare("SELECT * FROM event_age_groups WHERE event_id = ? ORDER BY min_age ASC");
$kus->execute([$eventId]);
$listKU = $kus->fetchAll();

// 2. Ambil Nomor Lomba (Prioritas filter by event_id, backup organizer_id untuk data lama)
// Logika: Tampilkan jika event_id nya cocok.
// CAST(event_number AS UNSIGNED) agar sorting angka benar (1, 2, 10 bukan 1, 10, 2)
$events = $pdo->prepare("SELECT * FROM event_numbers WHERE (event_id = ? OR (event_id IS NULL AND organizer_id = ?)) ORDER BY CAST(event_number AS UNSIGNED) ASC");
$events->execute([$eventId, $adminId]);
$listEvents = $events->fetchAll();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<script>
function togglePricingMode(mode) {
    const packageConfig = document.getElementById('packageConfig');
    if (mode === 'package') {
        packageConfig.classList.remove('hidden');
    } else {
        packageConfig.classList.add('hidden');
    }
}
</script>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="max-w-7xl mx-auto mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-black uppercase italic text-slate-900 leading-none">Manajemen Lomba</h1>
            <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-2">
                Event Aktif: <span class="text-blue-600"><?= htmlspecialchars($activeEvent['nama_event'] ?? 'Belum Ada Event') ?></span> 
                <span class="text-slate-300 mx-2">|</span> ID: #<?= $eventId ?>
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

    <?php if($eventId == 0): ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-8">
            <div class="flex">
                <div class="ml-3">
                    <p class="text-sm text-yellow-700 font-bold">
                        Anda belum membuat Event Profile. Silakan ke menu <a href="../admin/settings/event_profile.php" class="underline">Event Profile</a> terlebih dahulu.
                    </p>
                </div>
            </div>
        </div>
    <?php else: ?>

    <div class="max-w-7xl mx-auto space-y-8">
        
        <div class="bg-indigo-900 text-white p-8 rounded-[2rem] shadow-xl relative overflow-hidden">
            <div class="absolute top-0 right-0 p-6 opacity-10 text-9xl">💰</div>
            <h3 class="font-black uppercase text-sm text-indigo-300 mb-6 tracking-widest relative z-10">
                ⚙️ Aturan Biaya Pendaftaran
            </h3>
            
            <form method="POST" class="relative z-10">
                <input type="hidden" name="action" value="update_pricing">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                    <div>
                        <label class="block text-[10px] font-bold text-indigo-300 uppercase mb-3">Metode Perhitungan</label>
                        <div class="flex gap-4">
                            <label class="cursor-pointer">
                                <input type="radio" name="pricing_mode" value="per_item" class="peer sr-only" 
                                    <?= ($activeEvent['pricing_mode'] ?? 'per_item') == 'per_item' ? 'checked' : '' ?> 
                                    onchange="togglePricingMode('per_item')">
                                <div class="px-5 py-3 rounded-xl bg-indigo-800 border-2 border-transparent peer-checked:bg-white peer-checked:text-indigo-900 peer-checked:border-indigo-400 transition font-bold text-sm">
                                    Satuan (Per Nomor)
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="pricing_mode" value="package" class="peer sr-only" 
                                    <?= ($activeEvent['pricing_mode'] ?? '') == 'package' ? 'checked' : '' ?>
                                    onchange="togglePricingMode('package')">
                                <div class="px-5 py-3 rounded-xl bg-indigo-800 border-2 border-transparent peer-checked:bg-emerald-400 peer-checked:text-emerald-900 peer-checked:border-emerald-200 transition font-bold text-sm flex items-center gap-2">
                                    <span>📦</span> Sistem Paket
                                </div>
                            </label>
                        </div>
                    </div>

                    <div id="packageConfig" class="<?= ($activeEvent['pricing_mode'] ?? 'per_item') == 'package' ? '' : 'hidden' ?> bg-indigo-800/50 p-4 rounded-xl border border-indigo-700">
                        <label class="block text-[10px] font-bold text-emerald-300 uppercase mb-3">Konfigurasi Paket</label>
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-bold w-24">Biaya Paket:</span>
                                <input type="number" name="package_price" value="<?= $activeEvent['package_price'] ?? 0 ?>" placeholder="Rp 130.000" class="flex-1 bg-indigo-900/50 border border-indigo-600 rounded px-3 py-1 text-sm font-bold focus:border-emerald-400 outline-none">
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-bold w-24">Dapat Jumlah:</span>
                                <input type="number" name="package_limit" value="<?= $activeEvent['package_limit'] ?? 0 ?>" placeholder="3" class="w-20 bg-indigo-900/50 border border-indigo-600 rounded px-3 py-1 text-sm font-bold focus:border-emerald-400 outline-none">
                                <span class="text-[10px]">Nomor Lomba</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-bold w-24">Biaya Tambahan:</span>
                                <input type="number" name="extra_price" value="<?= $activeEvent['extra_price'] ?? 0 ?>" placeholder="Rp 35.000" class="flex-1 bg-indigo-900/50 border border-indigo-600 rounded px-3 py-1 text-sm font-bold focus:border-emerald-400 outline-none">
                                <span class="text-[10px]">/ Nomor</span>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="bg-emerald-500 hover:bg-emerald-400 text-emerald-900 font-black px-6 py-3 rounded-xl text-xs uppercase tracking-widest shadow-lg transition">
                    Simpan Aturan Harga
                </button>
            </form>
        </div>


        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
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
                    <h3 class="font-black uppercase text-xs text-slate-400 mb-4 tracking-widest">Daftar KU (Event #<?= $eventId ?>)</h3>
                    <?php if(empty($listKU)): ?>
                        <p class="text-xs text-slate-300 italic text-center py-4">Belum ada KU untuk event ini</p>
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
                                <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Biaya (Khusus nomor ini)</label>
                                <input type="number" name="biaya_pendaftaran" value="50000" class="w-full bg-slate-50 border-2 border-slate-100 rounded-xl px-4 py-2 font-bold text-sm outline-none placeholder:text-slate-300">
                                <p class="text-[9px] text-slate-400 mt-1 italic">
                                    <?php if(($activeEvent['pricing_mode'] ?? '') == 'package'): ?>
                                        ⚠ Mode Paket Aktif. Biaya ini mungkin diabaikan sistem.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block text-[9px] font-bold text-slate-400 uppercase mb-2">Pilih Kelompok Umur Yang Berlomba</label>
                            
                            <?php if(empty($listKU)): ?>
                                <div class="p-4 bg-red-50 text-red-600 text-xs font-bold rounded-xl border border-red-100 flex items-center gap-2">
                                    ⚠️ Buat Kelompok Umur (KU) di kolom kiri dulu!
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                    <?php foreach($listKU as $ku): ?>
                                    <label class="cursor-pointer relative group">
                                        <input type="checkbox" name="selected_kus[]" value="<?= $ku['id'] ?>" class="peer sr-only">
                                        <div class="p-3 bg-slate-50 border-2 border-slate-100 rounded-xl text-center hover:bg-white hover:shadow-sm transition peer-checked:border-blue-600 peer-checked:bg-blue-50">
                                            <span class="block text-xs font-black text-slate-700 peer-checked:text-blue-700"><?= htmlspecialchars($ku['group_name']) ?></span>
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
                            <p class="text-slate-300 font-bold text-sm italic">Belum ada nomor lomba untuk event ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-slate-100">
                            <?php foreach($listEvents as $ev): 
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

                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-bold text-slate-400">Rp <?= number_format($ev['price']) ?></span>
                                    <form method="POST" onsubmit="return confirm('Hapus Nomor <?= $ev['event_number'] ?>?');">
                                        <input type="hidden" name="action" value="delete_event">
                                        <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                                        <button type="submit" class="text-slate-300 hover:text-red-500 font-bold text-sm bg-white border border-slate-200 hover:bg-red-50 hover:border-red-200 px-3 py-2 rounded-xl transition">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>
    <?php endif; ?>
</div>