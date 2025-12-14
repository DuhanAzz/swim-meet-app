<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

// --- 1. HANDLE SEMUA PROSES UPDATE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        $targetDir = "../../../public/uploads/logos/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        // A. UPDATE INFO UTAMA & REKENING
        $sql = "UPDATE users SET 
                nama_lengkap = ?, location = ?, venue_name = ?, 
                event_start_date = ?, event_end_date = ?,
                lane_count = ?, age_calculation_type = ?,
                bank_name = ?, bank_account_number = ?, bank_account_name = ?
                WHERE id = ?";
        $pdo->prepare($sql)->execute([
            $_POST['nama_lengkap'], $_POST['location'], $_POST['venue_name'], 
            $_POST['event_start_date'], $_POST['event_end_date'],
            (int)$_POST['lane_count'], $_POST['age_calculation_type'],
            $_POST['bank_name'], $_POST['bank_account_number'], $_POST['bank_account_name'],
            $uid
        ]);

        // B. UPDATE KELOMPOK UMUR
        $pdo->prepare("DELETE FROM event_age_groups WHERE event_id = ?")->execute([$uid]);
        if (!empty($_POST['ku_name'])) {
            $insKU = $pdo->prepare("INSERT INTO event_age_groups (event_id, group_name, min_age, max_age) VALUES (?, ?, ?, ?)");
            foreach ($_POST['ku_name'] as $i => $name) {
                if (!empty(trim($name))) {
                    $insKU->execute([$uid, trim($name), (int)$_POST['ku_min'][$i], (int)$_POST['ku_max'][$i]]);
                }
            }
        }

        // C. HANDLE LOGO KIRI (Header)
        if (!empty($_FILES['logo_left']['name'])) {
            $ext = pathinfo($_FILES['logo_left']['name'], PATHINFO_EXTENSION);
            $fn = "l_left_" . $uid . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['logo_left']['tmp_name'], $targetDir . $fn)) {
                $pdo->prepare("UPDATE users SET logo_left = ? WHERE id = ?")->execute(["uploads/logos/" . $fn, $uid]);
            }
        }

        // D. HANDLE LOGO KANAN (Header)
        if (!empty($_FILES['logo_right']['name'])) {
            $ext = pathinfo($_FILES['logo_right']['name'], PATHINFO_EXTENSION);
            $fn = "l_right_" . $uid . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['logo_right']['tmp_name'], $targetDir . $fn)) {
                $pdo->prepare("UPDATE users SET logo_right = ? WHERE id = ?")->execute(["uploads/logos/" . $fn, $uid]);
            }
        }

        // E. HANDLE SPONSOR (Bisa Tambah Baru)
        if (!empty($_FILES['new_sponsor']['name'])) {
            $ext = pathinfo($_FILES['new_sponsor']['name'], PATHINFO_EXTENSION);
            $fn = "sp_" . $uid . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['new_sponsor']['tmp_name'], $targetDir . $fn)) {
                $pdo->prepare("INSERT INTO event_sponsors (user_id, image_path) VALUES (?, ?)")->execute([$uid, "uploads/logos/" . $fn]);
            }
        }

        // F. HANDLE HAPUS SPONSOR
        if (isset($_POST['delete_sponsor_id'])) {
            $pdo->prepare("DELETE FROM event_sponsors WHERE id = ? AND user_id = ?")->execute([$_POST['delete_sponsor_id'], $uid]);
        }

        // G. HANDLE EVENT POSTER
        if (!empty($_FILES['banner']['name'])) {
            $ext = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
            $fn = "poster_" . $uid . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['banner']['tmp_name'], $targetDir . $fn)) {
                $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?")->execute(["uploads/logos/" . $fn, $uid]);
            }
        }

        $pdo->commit();
        $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Semua Perubahan Berhasil Disimpan!';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['toast_type'] = 'error'; $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage();
    }
    header("Location: event_profile.php"); exit;
}

// --- 2. AMBIL DATA TERBARU ---
$row = $pdo->query("SELECT * FROM users WHERE id = $uid")->fetch();
$ageGroups = $pdo->query("SELECT * FROM event_age_groups WHERE event_id = $uid ORDER BY min_age DESC")->fetchAll();
$sponsors = $pdo->query("SELECT * FROM event_sponsors WHERE user_id = $uid")->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="max-w-5xl mx-auto mb-10 flex justify-between items-end">
        <div>
            <h1 class="text-4xl font-black uppercase tracking-tighter italic text-slate-900 leading-none">Event Settings</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Pusat Konfigurasi Identitas & Branding Lomba</p>
        </div>
        <div class="bg-white px-5 py-2 rounded-2xl border border-slate-200 shadow-sm text-[10px] font-black uppercase text-blue-600">
            System: <?= $_SESSION['event_type'] ?? 'Standard' ?>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" class="max-w-5xl mx-auto space-y-8 pb-32">
        
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-10">
            <h3 class="font-black uppercase text-sm mb-8 flex items-center gap-3 text-blue-600 italic">
                <span class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center text-lg">📝</span> Detail Informasi Kejuaraan
            </h3>
            <div class="grid grid-cols-1 gap-6">
                <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($row['nama_lengkap'] ?? '') ?>" placeholder="Nama Resmi Kejuaraan" class="w-full px-6 py-5 border-2 border-slate-50 bg-slate-50 rounded-3xl font-black text-xl italic uppercase focus:bg-white focus:border-blue-500 transition outline-none">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Tanggal Mulai</label>
                        <input type="date" name="event_start_date" value="<?= $row['event_start_date'] ?? '' ?>" class="w-full px-6 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-bold focus:bg-white focus:border-blue-500 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Tanggal Selesai</label>
                        <input type="date" name="event_end_date" value="<?= $row['event_end_date'] ?? '' ?>" class="w-full px-6 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-bold focus:bg-white focus:border-blue-500 outline-none transition">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <input type="text" name="location" value="<?= htmlspecialchars($row['location'] ?? '') ?>" placeholder="Kota / Provinsi" class="w-full px-6 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-bold focus:bg-white focus:border-blue-500 transition outline-none">
                    <input type="text" name="venue_name" value="<?= htmlspecialchars($row['venue_name'] ?? '') ?>" placeholder="Nama Kolam Renang" class="w-full px-6 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-bold focus:bg-white focus:border-blue-500 transition outline-none">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-10">
            <h3 class="font-black uppercase text-sm mb-8 flex items-center gap-3 text-emerald-600 italic">
                <span class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center text-lg">💳</span> Informasi Rekening (Checkout)
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Nama Bank</label>
                    <input type="text" name="bank_name" value="<?= htmlspecialchars($row['bank_name'] ?? '') ?>" placeholder="MANDIRI / BCA / BNI" class="w-full px-5 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-[11px] uppercase focus:bg-white focus:border-emerald-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">No. Rekening</label>
                    <input type="text" name="bank_account_number" value="<?= htmlspecialchars($row['bank_account_number'] ?? '') ?>" placeholder="00000000000" class="w-full px-5 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-[11px] focus:bg-white focus:border-emerald-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Atas Nama (A/N)</label>
                    <input type="text" name="bank_account_name" value="<?= htmlspecialchars($row['bank_account_name'] ?? '') ?>" placeholder="Nama Pemilik Akun" class="w-full px-5 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-[11px] uppercase focus:bg-white focus:border-emerald-500 outline-none transition">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-10">
            <h3 class="font-black uppercase text-sm mb-8 flex items-center gap-3 text-slate-800 italic">
                <span class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-lg">🖼️</span> Header Logos (Buku Acara)
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                <div class="space-y-4">
                    <label class="block text-[10px] font-black text-slate-400 uppercase ml-1">Logo Kiri (Instansi/Univ)</label>
                    <div class="w-full h-40 bg-slate-50 rounded-[2rem] border-2 border-dashed border-slate-200 flex items-center justify-center overflow-hidden">
                        <?php if(!empty($row['logo_left'])): ?>
                            <img src="../../../public/<?= $row['logo_left'] ?>" class="max-h-32 object-contain p-2">
                        <?php else: ?><span class="text-slate-300 font-bold text-xs uppercase">Belum ada logo</span><?php endif; ?>
                    </div>
                    <input type="file" name="logo_left" class="text-[10px] w-full file:bg-slate-900 file:text-white file:border-0 file:px-4 file:py-2 file:rounded-xl file:font-black">
                </div>
                <div class="space-y-4">
                    <label class="block text-[10px] font-black text-slate-400 uppercase ml-1">Logo Kanan (Federasi/PRSI)</label>
                    <div class="w-full h-40 bg-slate-50 rounded-[2rem] border-2 border-dashed border-slate-200 flex items-center justify-center overflow-hidden">
                        <?php if(!empty($row['logo_right'])): ?>
                            <img src="../../../public/<?= $row['logo_right'] ?>" class="max-h-32 object-contain p-2">
                        <?php else: ?><span class="text-slate-300 font-bold text-xs uppercase">Belum ada logo</span><?php endif; ?>
                    </div>
                    <input type="file" name="logo_right" class="text-[10px] w-full file:bg-slate-900 file:text-white file:border-0 file:px-4 file:py-2 file:rounded-xl file:font-black">
                </div>
            </div>
        </div>

        <div class="bg-slate-900 rounded-[2.5rem] shadow-2xl p-10 text-white">
            <h3 class="font-black uppercase text-sm mb-8 flex items-center gap-3 italic">
                <span class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center text-lg">🤝</span> Sponsor & Partners (Footer)
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                <div class="space-y-4">
                    <p class="text-[10px] font-bold text-slate-400 uppercase leading-relaxed">Tambahkan logo sponsor yang akan tampil di bagian bawah setiap halaman Buku Acara.</p>
                    <input type="file" name="new_sponsor" class="text-[10px] w-full file:bg-blue-600 file:text-white file:border-0 file:px-6 file:py-3 file:rounded-2xl file:font-black file:uppercase">
                    <p class="text-[9px] text-slate-500 font-bold">* Tip: Gunakan logo dengan background transparan (PNG).</p>
                </div>

                <div class="bg-white/5 rounded-3xl p-6 min-h-[150px]">
                    <p class="text-[9px] font-black text-slate-500 uppercase mb-4 tracking-widest">Sponsor Saat Ini:</p>
                    <div class="flex flex-wrap gap-4">
                        <?php if(empty($sponsors)): ?>
                            <p class="text-xs text-slate-600 italic">Belum ada sponsor diunggah.</p>
                        <?php else: foreach($sponsors as $sp): ?>
                            <div class="relative group w-20 h-20 bg-white rounded-2xl flex items-center justify-center shadow-lg overflow-hidden border border-white/10">
                                <img src="../../../public/<?= $sp['image_path'] ?>" class="w-full h-full object-contain p-2">
                                <button type="submit" name="delete_sponsor_id" value="<?= $sp['id'] ?>" class="absolute inset-0 bg-red-600/90 text-white opacity-0 group-hover:opacity-100 transition flex items-center justify-center font-black text-[10px] uppercase">Hapus</button>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-10">
            <h3 class="font-black uppercase text-sm mb-8 flex items-center gap-3 text-orange-600 italic">
                <span class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center text-lg">⚙️</span> Technical Specification
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-4 ml-1">Jumlah Lintasan Kolam</label>
                    <div class="flex items-center gap-6 bg-slate-50 p-3 rounded-3xl w-fit border-2 border-slate-100">
                        <button type="button" onclick="changeLane(-1)" class="w-12 h-12 rounded-2xl bg-white shadow-md flex items-center justify-center font-black text-2xl hover:bg-red-500 hover:text-white transition transform active:scale-90">-</button>
                        <input type="number" id="lane_count" name="lane_count" value="<?= $row['lane_count'] ?: 8 ?>" class="w-16 text-center bg-transparent font-black text-3xl outline-none" readonly>
                        <button type="button" onclick="changeLane(1)" class="w-12 h-12 rounded-2xl bg-white shadow-md flex items-center justify-center font-black text-2xl hover:bg-blue-600 hover:text-white transition transform active:scale-90">+</button>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-4 ml-1">Metode Perhitungan Usia</label>
                    <select name="age_calculation_type" class="w-full p-5 border-2 border-slate-50 bg-slate-50 rounded-3xl font-black text-xs uppercase focus:bg-white transition outline-none cursor-pointer">
                        <option value="Dec 31" <?= $row['age_calculation_type'] == 'Dec 31' ? 'selected' : '' ?>>Per 31 Des (Tahun Berjalan)</option>
                        <option value="Meet Start" <?= $row['age_calculation_type'] == 'Meet Start' ? 'selected' : '' ?>>Per Hari Pertama Lomba</option>
                    </select>
                </div>
            </div>

            <div class="mt-12 pt-10 border-t border-slate-50">
                <div class="flex justify-between items-center mb-6">
                    <h4 class="font-black uppercase text-xs text-purple-600 italic">Daftar Kelompok Umur (KU)</h4>
                    <button type="button" onclick="addKURow()" class="bg-slate-900 text-white px-5 py-2 rounded-xl font-black text-[9px] uppercase tracking-widest hover:bg-purple-600 transition">+ Tambah KU</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="text-[10px] font-black text-slate-400 uppercase">
                            <tr><th class="pb-4 px-2">Label KU</th><th class="pb-4 text-center w-32">Min Usia</th><th class="pb-4 text-center w-32">Max Usia</th><th class="pb-4 text-right w-20">Aksi</th></tr>
                        </thead>
                        <tbody id="ku-container" class="divide-y divide-slate-50">
                            <?php foreach($ageGroups as $ku): ?>
                                <tr>
                                    <td class="py-3 px-2"><input type="text" name="ku_name[]" value="<?= htmlspecialchars($ku['group_name']) ?>" class="w-full p-3 border-2 border-slate-50 bg-slate-50 rounded-xl font-black text-xs outline-none focus:bg-white transition"></td>
                                    <td class="py-3 px-2"><input type="number" name="ku_min[]" value="<?= $ku['min_age'] ?>" class="w-full text-center p-3 border-2 border-slate-50 bg-slate-50 rounded-xl font-black text-xs outline-none"></td>
                                    <td class="py-3 px-2"><input type="number" name="ku_max[]" value="<?= $ku['max_age'] ?>" class="w-full text-center p-3 border-2 border-slate-50 bg-slate-50 rounded-xl font-black text-xs outline-none"></td>
                                    <td class="py-3 text-right"><button type="button" onclick="this.closest('tr').remove()" class="text-red-400 hover:text-red-600 font-bold px-4">✕</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-10">
            <h3 class="font-black uppercase text-sm mb-4 flex items-center gap-3 text-slate-800 italic">
                <span class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-lg">📸</span> Event Poster (Public Page)
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-10 items-center">
                <div class="aspect-[3/4] max-w-sm bg-slate-100 rounded-[2.5rem] overflow-hidden border-2 border-dashed border-slate-200 relative group">
                    <?php 
                        $banner = $row['profile_image'] ?? '';
                        if ($banner && strpos($banner, 'http') !== 0) $banner = "../../../public/" . $banner;
                    ?>
                    <img id="preview" src="<?= !empty($banner) ? $banner . '?t=' . time() : '' ?>" class="w-full h-full object-cover <?= empty($banner) ? 'hidden' : '' ?>">
                    <div id="placeholder" class="absolute inset-0 flex flex-col items-center justify-center text-slate-300 <?= !empty($banner) ? 'hidden' : '' ?>">
                        <span class="text-5xl mb-4">🖼️</span>
                        <span class="text-[10px] font-black uppercase tracking-widest text-center px-4">Upload Poster Rasio 3:4</span>
                    </div>
                </div>
                <div class="space-y-6">
                    <p class="text-xs text-slate-400 font-bold uppercase italic leading-relaxed">Poster ini akan tampil di landing page pendaftaran publik. Pastikan resolusi tinggi (1200x1600px).</p>
                    <input type="file" name="banner" class="text-[10px] w-full file:bg-slate-900 file:text-white file:border-0 file:px-6 file:py-3 file:rounded-2xl file:font-black file:uppercase transition hover:file:bg-blue-600" onchange="previewImage(this)">
                </div>
            </div>
        </div>

        <div class="pt-10">
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-7 rounded-[2.5rem] shadow-2xl shadow-blue-100 transition transform hover:-translate-y-1 uppercase tracking-[0.2em] text-sm">
                💾 SIMPAN PERUBAHAN PROFILE EVENT
            </button>
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
    tr.innerHTML = `
        <td class="py-3 px-2"><input type="text" name="ku_name[]" placeholder="Nama KU" class="w-full p-3 border-2 border-slate-50 bg-slate-50 rounded-xl font-black text-xs outline-none"></td>
        <td class="py-3 px-2"><input type="number" name="ku_min[]" value="0" class="w-full text-center p-3 border-2 border-slate-50 bg-slate-50 rounded-xl font-black text-xs outline-none"></td>
        <td class="py-3 px-2"><input type="number" name="ku_max[]" value="0" class="w-full text-center p-3 border-2 border-slate-50 bg-slate-50 rounded-xl font-black text-xs outline-none"></td>
        <td class="py-3 text-right"><button type="button" onclick="this.closest('tr').remove()" class="text-red-400 hover:text-red-600 font-bold px-4">✕</button></td>
    `;
    container.appendChild(tr);
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
            document.getElementById('preview').src = e.target.result;
            document.getElementById('preview').classList.remove('hidden');
            document.getElementById('placeholder').classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>