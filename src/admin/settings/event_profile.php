<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

// --- HANDLE SIMPAN DATA ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. Update Info Utama & Teknis
        $sql = "UPDATE users SET 
                nama_lengkap = ?, location = ?, venue_name = ?, 
                event_start_date = ?, event_end_date = ?,
                lane_count = ?, competition_system = ?, age_calculation_type = ?
                WHERE id = ?";
        $pdo->prepare($sql)->execute([
            $_POST['nama_lengkap'], $_POST['location'], $_POST['venue_name'], 
            $_POST['event_start_date'], $_POST['event_end_date'],
            $_POST['lane_count'], $_POST['competition_system'], $_POST['age_calculation_type'], $uid
        ]);

        // 2. Update Kelompok Umur (Hapus lama, masukkan baru)
        $pdo->prepare("DELETE FROM event_age_groups WHERE event_id = ?")->execute([$uid]);
        if (!empty($_POST['ku_name'])) {
            $insKU = $pdo->prepare("INSERT INTO event_age_groups (event_id, group_name, min_age, max_age) VALUES (?, ?, ?, ?)");
            foreach ($_POST['ku_name'] as $i => $name) {
                if (!empty(trim($name))) {
                    $insKU->execute([$uid, $name, $_POST['ku_min'][$i], $_POST['ku_max'][$i]]);
                }
            }
        }

        // 3. Handle Banner
        if (!empty($_FILES['banner']['name'])) {
            $targetDir = __DIR__ . "/../../../public/uploads/banners/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $ext = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
            $fileName = "banner_" . $uid . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['banner']['tmp_name'], $targetDir . $fileName)) {
                $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?")->execute(["uploads/banners/" . $fileName, $uid]);
            }
        }

        $pdo->commit();
        $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Pengaturan Berhasil Disimpan!';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['toast_type'] = 'error'; $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage();
    }
    header("Location: event_profile.php"); exit;
}

// Ambil Data Existing
$row = $pdo->query("SELECT * FROM users WHERE id = $uid")->fetch();
$ageGroups = $pdo->query("SELECT * FROM event_age_groups WHERE event_id = $uid ORDER BY min_age DESC")->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="flex justify-between items-center mb-10">
        <div>
            <h1 class="text-4xl font-black uppercase tracking-tighter italic">Event Settings</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest">Konfigurasi Teknis & Standar World Aquatics</p>
        </div>
        <a href="../../../public/index.php" target="_blank" class="bg-white border-2 border-slate-200 text-slate-700 px-6 py-3 rounded-2xl font-black text-xs uppercase hover:bg-slate-50 transition shadow-sm flex items-center gap-2">
            <span>👁️</span> Cek Tampilan
        </a>
    </div>

    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-3 gap-10">
        
        <div class="xl:col-span-2 space-y-10">
            
            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-10">
                <h3 class="font-black uppercase text-sm mb-8 flex items-center gap-3 text-blue-600">
                    <span class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center text-xl">📝</span> Detail Publik
                </h3>
                <div class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Nama Kejuaraan Resmi</label>
                        <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($row['nama_lengkap']) ?>" class="w-full px-6 py-4 border-2 border-slate-50 rounded-2xl font-black bg-slate-50 focus:bg-white focus:border-blue-500 transition outline-none text-xl uppercase italic">
                    </div>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Tanggal Mulai</label>
                            <input type="date" name="event_start_date" value="<?= $row['event_start_date'] ?>" class="w-full px-6 py-4 border-2 border-slate-50 rounded-2xl font-bold bg-slate-50 focus:bg-white transition outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Tanggal Selesai</label>
                            <input type="date" name="event_end_date" value="<?= $row['event_end_date'] ?>" class="w-full px-6 py-4 border-2 border-slate-50 rounded-2xl font-bold bg-slate-50 focus:bg-white transition outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-6">
                        <input type="text" name="location" value="<?= $row['location'] ?>" placeholder="Kota (Cth: Jakarta)" class="w-full px-6 py-4 border-2 border-slate-50 rounded-2xl font-bold bg-slate-50 focus:bg-white transition outline-none">
                        <input type="text" name="venue_name" value="<?= $row['venue_name'] ?>" placeholder="Nama Kolam (Cth: Akuatik GBK)" class="w-full px-6 py-4 border-2 border-slate-50 rounded-2xl font-bold bg-slate-50 focus:bg-white transition outline-none">
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-10">
                <h3 class="font-black uppercase text-sm mb-8 flex items-center gap-3 text-orange-600">
                    <span class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center text-xl">⚙️</span> Sistem & Seeding
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-4 ml-1">Jumlah Lintasan Kolam</label>
                        <div class="flex items-center gap-6 bg-slate-50 p-3 rounded-3xl w-fit border-2 border-slate-100">
                            <button type="button" onclick="changeLane(-1)" class="w-12 h-12 rounded-2xl bg-white shadow-md flex items-center justify-center font-black text-2xl hover:bg-red-500 hover:text-white transition transform active:scale-90">-</button>
                            <input type="number" id="lane_count" name="lane_count" value="<?= $row['lane_count'] ?: 8 ?>" class="w-16 text-center bg-transparent font-black text-3xl outline-none" readonly>
                            <button type="button" onclick="changeLane(1)" class="w-12 h-12 rounded-2xl bg-white shadow-md flex items-center justify-center font-black text-2xl hover:bg-blue-600 hover:text-white transition transform active:scale-90">+</button>
                        </div>
                        <p class="text-[9px] text-slate-400 mt-4 font-bold italic">* Min: 4, Max: 10 Lintasan</p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-4 ml-1">Sistem Pertandingan</label>
                        <select name="competition_system" class="w-full p-5 border-2 border-slate-50 rounded-3xl bg-slate-50 font-black text-xs uppercase tracking-widest outline-none focus:border-blue-500 focus:bg-white transition cursor-pointer">
                            <option value="Langsung Final" <?= $row['competition_system'] == 'Langsung Final' ? 'selected' : '' ?>>Timed Final (Linear Seeding)</option>
                            <option value="Penyisihan" <?= $row['competition_system'] == 'Penyisihan' ? 'selected' : '' ?>>Prelims (WA Serpentine Seeding)</option>
                        </select>
                        <div class="mt-4 p-4 bg-blue-50/50 rounded-2xl border border-blue-100">
                            <p class="text-[9px] text-blue-600 leading-relaxed font-bold">
                                ℹ️ <span class="uppercase">Penyisihan:</span> Menggunakan aturan distribusi unggulan zig-zag ke 3 seri terakhir (WA Rule 3.1.1).
                            </p>
                        </div>
                    </div>

                    <div class="col-span-full border-t border-slate-100 pt-8 mt-4">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-4 ml-1">Basis Perhitungan Usia Atlet</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label class="relative cursor-pointer">
                                <input type="radio" name="age_calculation_type" value="Dec 31" <?= $row['age_calculation_type'] == 'Dec 31' ? 'checked' : '' ?> class="hidden peer">
                                <div class="p-5 border-2 border-slate-50 bg-slate-50 rounded-3xl peer-checked:border-blue-600 peer-checked:bg-blue-50 transition flex items-center gap-4">
                                    <span class="w-8 h-8 rounded-full bg-white flex items-center justify-center border peer-checked:border-blue-600">📅</span>
                                    <div class="font-black text-[10px] uppercase tracking-wider">Per 31 Des (Tahun Berjalan)</div>
                                </div>
                            </label>
                            <label class="relative cursor-pointer">
                                <input type="radio" name="age_calculation_type" value="Meet Start" <?= $row['age_calculation_type'] == 'Meet Start' ? 'checked' : '' ?> class="hidden peer">
                                <div class="p-5 border-2 border-slate-50 bg-slate-50 rounded-3xl peer-checked:border-blue-600 peer-checked:bg-blue-50 transition flex items-center gap-4">
                                    <span class="w-8 h-8 rounded-full bg-white flex items-center justify-center border peer-checked:border-blue-600">🏊</span>
                                    <div class="font-black text-[10px] uppercase tracking-wider">Per Hari Pertama Lomba</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-10">
                <div class="flex justify-between items-center mb-8">
                    <h3 class="font-black uppercase text-sm flex items-center gap-3 text-purple-600">
                        <span class="w-10 h-10 bg-purple-50 rounded-xl flex items-center justify-center text-xl">👥</span> Kelompok Umur
                    </h3>
                    <button type="button" onclick="addKURow()" class="bg-slate-900 text-white px-6 py-3 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-purple-600 transition shadow-lg">+ Tambah KU</button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-[10px] font-black uppercase text-slate-400 border-b-2 border-slate-50">
                                <th class="pb-4 text-left px-2">Label Kelompok</th>
                                <th class="pb-4 text-center w-28">Min Usia</th>
                                <th class="pb-4 text-center w-28">Max Usia</th>
                                <th class="pb-4 text-right w-16">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="ku-container" class="divide-y divide-slate-50">
                            <?php if(empty($ageGroups)): ?>
                                <tr class="ku-row">
                                    <td class="py-4 px-2"><input type="text" name="ku_name[]" placeholder="Cth: KU 1" class="w-full p-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-sm outline-none focus:border-purple-500 transition"></td>
                                    <td class="py-4 px-2"><input type="number" name="ku_min[]" placeholder="16" class="w-full text-center p-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-sm outline-none focus:border-purple-500 transition"></td>
                                    <td class="py-4 px-2"><input type="number" name="ku_max[]" placeholder="18" class="w-full text-center p-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-sm outline-none focus:border-purple-500 transition"></td>
                                    <td class="py-4 text-right"><button type="button" onclick="this.closest('tr').remove()" class="w-10 h-10 text-red-400 hover:bg-red-50 rounded-xl transition">✕</button></td>
                                </tr>
                            <?php else: foreach($ageGroups as $ku): ?>
                                <tr class="ku-row">
                                    <td class="py-4 px-2"><input type="text" name="ku_name[]" value="<?= $ku['group_name'] ?>" class="w-full p-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-sm outline-none focus:border-purple-500 transition"></td>
                                    <td class="py-4 px-2"><input type="number" name="ku_min[]" value="<?= $ku['min_age'] ?>" class="w-full text-center p-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-sm outline-none focus:border-purple-500 transition"></td>
                                    <td class="py-4 px-2"><input type="number" name="ku_max[]" value="<?= $ku['max_age'] ?>" class="w-full text-center p-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-sm outline-none focus:border-purple-500 transition"></td>
                                    <td class="py-4 text-right"><button type="button" onclick="this.closest('tr').remove()" class="w-10 h-10 text-red-400 hover:bg-red-50 rounded-xl transition">✕</button></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-8">
            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-8 sticky top-28">
                <h3 class="font-black uppercase text-sm mb-6 text-slate-800 tracking-tighter italic underline decoration-blue-500 decoration-4">Poster Championship</h3>
                <div class="aspect-[3/4] bg-slate-100 rounded-[2rem] overflow-hidden border-2 border-dashed border-slate-200 relative group mb-6">
                    <?php $banner = (!empty($row['profile_image'])) ? "../../../public/" . $row['profile_image'] : ''; ?>
                    <img id="preview" src="<?= $banner ?>?t=<?= time() ?>" class="w-full h-full object-cover <?= empty($banner)?'hidden':'' ?>">
                    <div id="placeholder" class="absolute inset-0 flex flex-col items-center justify-center text-slate-300 <?= !empty($banner)?'hidden':'' ?>">
                        <span class="text-5xl mb-4">📸</span>
                        <span class="text-[10px] font-black uppercase tracking-widest">Unggah Poster</span>
                    </div>
                </div>
                <div class="space-y-4">
                    <input type="file" name="banner" class="text-[10px] w-full file:bg-blue-600 file:text-white file:border-0 file:px-6 file:py-3 file:rounded-2xl file:font-black file:uppercase file:cursor-pointer" onchange="previewImage(this)">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-5 rounded-3xl shadow-xl shadow-blue-100 transition transform hover:-translate-y-1 uppercase tracking-[0.2em] text-xs">
                        💾 SIMPAN SEMUA DATA
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>

<script>
function changeLane(val) {
    const input = document.getElementById('lane_count');
    let current = parseInt(input.value);
    if(current + val >= 4 && current + val <= 10) { input.value = current + val; }
}

function addKURow() {
    const container = document.getElementById('ku-container');
    const tr = document.createElement('tr');
    tr.className = "ku-row";
    tr.innerHTML = `
        <td class="py-4 px-2"><input type="text" name="ku_name[]" placeholder="Nama KU" class="w-full p-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-sm outline-none focus:border-purple-500 transition"></td>
        <td class="py-4 px-2"><input type="number" name="ku_min[]" placeholder="0" class="w-full text-center p-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-sm outline-none focus:border-purple-500 transition"></td>
        <td class="py-4 px-2"><input type="number" name="ku_max[]" placeholder="0" class="w-full text-center p-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-sm outline-none focus:border-purple-500 transition"></td>
        <td class="py-4 text-right"><button type="button" onclick="this.closest('tr').remove()" class="w-10 h-10 text-red-400 hover:bg-red-50 rounded-xl transition">✕</button></td>
    `;
    container.appendChild(tr);
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = document.getElementById('preview');
            img.src = e.target.result;
            img.classList.remove('hidden');
            document.getElementById('placeholder').classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>