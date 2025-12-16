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

        // A. UPDATE INFO UTAMA, REKENING, SISTEM, & STATUS (REVISI)
        $sql = "UPDATE users SET 
                nama_lengkap = ?, location = ?, venue_name = ?, 
                event_start_date = ?, event_end_date = ?,
                lane_count = ?, age_calculation_type = ?, event_type = ?, event_status = ?,
                bank_name = ?, bank_account_number = ?, bank_account_name = ?
                WHERE id = ?";
        
        $pdo->prepare($sql)->execute([
            $_POST['nama_lengkap'] ?? '', $_POST['location'] ?? '', $_POST['venue_name'] ?? '', 
            $_POST['event_start_date'] ?? '', $_POST['event_end_date'] ?? '',
            (int)($_POST['lane_count'] ?? 8), $_POST['age_calculation_type'] ?? 'Dec 31', 
            $_POST['event_type'] ?? 'Langsung Final', $_POST['event_status'] ?? 'Registration',
            $_POST['bank_name'] ?? '', $_POST['bank_account_number'] ?? '', $_POST['bank_account_name'] ?? '',
            $uid
        ]);

        // Sync Session
        $_SESSION['event_type'] = $_POST['event_type'];

        // B. UPDATE KELOMPOK UMUR (DELETE & RE-INSERT)
        $pdo->prepare("DELETE FROM event_age_groups WHERE event_id = ?")->execute([$uid]);
        if (!empty($_POST['ku_name'])) {
            $insKU = $pdo->prepare("INSERT INTO event_age_groups (event_id, group_name, min_age, max_age) VALUES (?, ?, ?, ?)");
            foreach ($_POST['ku_name'] as $i => $name) {
                if (!empty(trim($name))) {
                    $insKU->execute([$uid, trim($name), (int)$_POST['ku_min'][$i], (int)$_POST['ku_max'][$i]]);
                }
            }
        }

        // C. HANDLE LOGO KIRI
        if (!empty($_FILES['logo_left']['name'])) {
            $ext = pathinfo($_FILES['logo_left']['name'], PATHINFO_EXTENSION);
            $fn = "l_left_" . $uid . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['logo_left']['tmp_name'], $targetDir . $fn)) {
                $pdo->prepare("UPDATE users SET logo_left = ? WHERE id = ?")->execute(["uploads/logos/" . $fn, $uid]);
            }
        }

        // D. HANDLE LOGO KANAN
        if (!empty($_FILES['logo_right']['name'])) {
            $ext = pathinfo($_FILES['logo_right']['name'], PATHINFO_EXTENSION);
            $fn = "l_right_" . $uid . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['logo_right']['tmp_name'], $targetDir . $fn)) {
                $pdo->prepare("UPDATE users SET logo_right = ? WHERE id = ?")->execute(["uploads/logos/" . $fn, $uid]);
            }
        }

        // E. HANDLE SPONSOR
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
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Identitas & Status Operasional Lomba</p>
        </div>
        <div class="bg-white px-5 py-2 rounded-2xl border border-slate-200 shadow-sm text-[10px] font-black uppercase text-blue-600">
            Active System: <?= htmlspecialchars($row['event_type'] ?? 'Standard') ?>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" class="max-w-5xl mx-auto space-y-8 pb-32">
        
        <div class="bg-slate-900 rounded-[2.5rem] shadow-2xl p-10 text-white relative overflow-hidden">
            <div class="absolute right-0 top-0 p-8 opacity-10">
                <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"></path></svg>
            </div>
            <h3 class="font-black uppercase text-sm mb-6 flex items-center gap-3 italic">
                <span class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center text-lg">📡</span> Live Event Status
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="cursor-pointer group">
                    <input type="radio" name="event_status" value="Registration" class="hidden peer" <?= ($row['event_status'] ?? 'Registration') == 'Registration' ? 'checked' : '' ?>>
                    <div class="p-6 rounded-[2rem] border-2 border-white/10 bg-white/5 transition-all peer-checked:bg-emerald-500 peer-checked:border-emerald-400 group-hover:border-white/30 text-center">
                        <span class="text-2xl block mb-2">📝</span>
                        <span class="block font-black uppercase tracking-widest text-[11px]">Registration</span>
                        <span class="block text-[8px] font-bold text-white/50 uppercase mt-1">Pendaftaran Dibuka</span>
                    </div>
                </label>
                
                <label class="cursor-pointer group">
                    <input type="radio" name="event_status" value="Running" class="hidden peer" <?= ($row['event_status'] ?? '') == 'Running' ? 'checked' : '' ?>>
                    <div class="p-6 rounded-[2rem] border-2 border-white/10 bg-white/5 transition-all peer-checked:bg-blue-600 peer-checked:border-blue-400 group-hover:border-white/30 text-center">
                        <span class="text-2xl block mb-2">🏊</span>
                        <span class="block font-black uppercase tracking-widest text-[11px]">Running</span>
                        <span class="block text-[8px] font-bold text-white/50 uppercase mt-1">Lomba Berjalan (Live)</span>
                    </div>
                </label>
                
                <label class="cursor-pointer group">
                    <input type="radio" name="event_status" value="Finished" class="hidden peer" <?= ($row['event_status'] ?? '') == 'Finished' ? 'checked' : '' ?>>
                    <div class="p-6 rounded-[2rem] border-2 border-white/10 bg-white/5 transition-all peer-checked:bg-slate-700 peer-checked:border-slate-500 group-hover:border-white/30 text-center">
                        <span class="text-2xl block mb-2">🏁</span>
                        <span class="block font-black uppercase tracking-widest text-[11px]">Finished</span>
                        <span class="block text-[8px] font-bold text-white/50 uppercase mt-1">Selesai / Hasil Final</span>
                    </div>
                </label>
            </div>
            <p class="text-[9px] text-white/40 mt-6 font-bold uppercase italic">* Status ini akan mempengaruhi tampilan menu pendaftaran dan hasil di halaman publik.</p>
        </div>

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
            <h3 class="font-black uppercase text-sm mb-8 flex items-center gap-3 text-orange-600 italic">
                <span class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center text-lg">⚙️</span> Technical Specification
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-4 ml-1">Jumlah Lintasan Kolam</label>
                    <div class="flex items-center gap-6 bg-slate-50 p-3 rounded-3xl w-fit border-2 border-slate-100">
                        <button type="button" onclick="changeLane(-1)" class="w-12 h-12 rounded-2xl bg-white shadow-md flex items-center justify-center font-black text-2xl hover:bg-red-500 hover:text-white transition transform active:scale-90">-</button>
                        <input type="number" id="lane_count" name="lane_count" value="<?= $row['lane_count'] ?: 8 ?>" class="w-16 text-center bg-transparent font-black text-3xl outline-none" readonly>
                        <button type="button" onclick="changeLane(1)" class="w-12 h-12 rounded-2xl bg-white shadow-md flex items-center justify-center font-black text-2xl hover:bg-blue-600 hover:text-white transition transform active:scale-90">+</button>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-4 ml-1">Metode Usia</label>
                    <select name="age_calculation_type" class="w-full p-5 border-2 border-slate-50 bg-slate-50 rounded-3xl font-black text-xs uppercase focus:bg-white transition outline-none cursor-pointer">
                        <option value="Dec 31" <?= $row['age_calculation_type'] == 'Dec 31' ? 'selected' : '' ?>>Per 31 Des</option>
                        <option value="Meet Start" <?= $row['age_calculation_type'] == 'Meet Start' ? 'selected' : '' ?>>Per Hari H</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-4 ml-1">Sistem Pertandingan</label>
                    <?php if ($_SESSION['role'] === 'master'): ?>
                        <select name="event_type" class="w-full p-5 border-2 border-blue-100 bg-blue-50/30 rounded-3xl font-black text-xs uppercase focus:bg-white transition outline-none cursor-pointer text-blue-600">
                            <option value="Langsung Final" <?= ($row['event_type'] ?? '') == 'Langsung Final' ? 'selected' : '' ?>>Timed Final</option>
                            <option value="Babak Penyisihan" <?= ($row['event_type'] ?? '') == 'Babak Penyisihan' ? 'selected' : '' ?>>Heats & Finals</option>
                        </select>
                    <?php else: ?>
                        <div class="w-full p-5 border-2 border-slate-100 bg-slate-100 rounded-3xl font-black text-xs uppercase text-slate-500 flex items-center justify-between">
                            <span><?= htmlspecialchars($row['event_type'] ?? 'Langsung Final') ?></span>
                            <span class="text-[8px] bg-white px-2 py-1 rounded-lg border">🔒 LOCKED</span>
                        </div>
                        <input type="hidden" name="event_type" value="<?= htmlspecialchars($row['event_type']) ?>">
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-10">
            <h3 class="font-black uppercase text-sm mb-8 flex items-center gap-3 text-emerald-600 italic">
                <span class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center text-lg">💳</span> Informasi Rekening
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <input type="text" name="bank_name" value="<?= htmlspecialchars($row['bank_name'] ?? '') ?>" placeholder="Nama Bank" class="w-full px-5 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-[11px] uppercase outline-none focus:bg-white transition">
                <input type="text" name="bank_account_number" value="<?= htmlspecialchars($row['bank_account_number'] ?? '') ?>" placeholder="No. Rekening" class="w-full px-5 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-[11px] outline-none focus:bg-white transition">
                <input type="text" name="bank_account_name" value="<?= htmlspecialchars($row['bank_account_name'] ?? '') ?>" placeholder="Atas Nama" class="w-full px-5 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-[11px] outline-none focus:bg-white transition">
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-10">
            <h3 class="font-black uppercase text-sm mb-8 flex items-center gap-3 text-slate-800 italic">
                <span class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-lg">🖼️</span> Header Logos (Buku Acara)
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                <div class="space-y-4">
                    <label class="block text-[10px] font-black text-slate-400 uppercase ml-1">Logo Kiri</label>
                    <div class="w-full h-40 bg-slate-50 rounded-[2rem] border-2 border-dashed border-slate-200 flex items-center justify-center overflow-hidden">
                        <?php if(!empty($row['logo_left'])): ?>
                            <img src="../../../public/<?= $row['logo_left'] ?>" class="max-h-32 object-contain p-2">
                        <?php else: ?><span class="text-slate-300 font-bold text-xs">Belum ada logo</span><?php endif; ?>
                    </div>
                    <input type="file" name="logo_left" class="text-[10px] w-full file:bg-slate-900 file:text-white file:border-0 file:px-4 file:py-2 file:rounded-xl">
                </div>
                <div class="space-y-4">
                    <label class="block text-[10px] font-black text-slate-400 uppercase ml-1">Logo Kanan</label>
                    <div class="w-full h-40 bg-slate-50 rounded-[2rem] border-2 border-dashed border-slate-200 flex items-center justify-center overflow-hidden">
                        <?php if(!empty($row['logo_right'])): ?>
                            <img src="../../../public/<?= $row['logo_right'] ?>" class="max-h-32 object-contain p-2">
                        <?php else: ?><span class="text-slate-300 font-bold text-xs">Belum ada logo</span><?php endif; ?>
                    </div>
                    <input type="file" name="logo_right" class="text-[10px] w-full file:bg-slate-900 file:text-white file:border-0 file:px-4 file:py-2 file:rounded-xl">
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
</script>