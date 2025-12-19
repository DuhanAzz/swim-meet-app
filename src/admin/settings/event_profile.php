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

        // A. UPDATE INFO UTAMA
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

        $_SESSION['event_type'] = $_POST['event_type'];

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

        // C. HANDLE LOGO KIRI & KANAN (HEADER)
        if (!empty($_FILES['logo_left']['name'])) {
            $ext = pathinfo($_FILES['logo_left']['name'], PATHINFO_EXTENSION);
            $fn = "l_left_" . $uid . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['logo_left']['tmp_name'], $targetDir . $fn)) {
                $pdo->prepare("UPDATE users SET logo_left = ? WHERE id = ?")->execute(["uploads/logos/" . $fn, $uid]);
            }
        }
        if (!empty($_FILES['logo_right']['name'])) {
            $ext = pathinfo($_FILES['logo_right']['name'], PATHINFO_EXTENSION);
            $fn = "l_right_" . $uid . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['logo_right']['tmp_name'], $targetDir . $fn)) {
                $pdo->prepare("UPDATE users SET logo_right = ? WHERE id = ?")->execute(["uploads/logos/" . $fn, $uid]);
            }
        }

        // --- BARU: D. HANDLE MULTIPLE FOOTER SPONSORS ---
        if (!empty($_FILES['footer_logos']['name'][0])) {
            $insFooter = $pdo->prepare("INSERT INTO event_footer_logos (user_id, image_path) VALUES (?, ?)");
            foreach ($_FILES['footer_logos']['name'] as $key => $name) {
                if ($_FILES['footer_logos']['error'][$key] === 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $fn = "footer_" . $uid . "_" . time() . "_" . $key . "." . $ext;
                    if(move_uploaded_file($_FILES['footer_logos']['tmp_name'][$key], $targetDir . $fn)) {
                        $insFooter->execute([$uid, "uploads/logos/" . $fn]);
                    }
                }
            }
        }

        // E. HAPUS LOGO FOOTER TERTENTU
        if (isset($_POST['delete_footer_id'])) {
            $pdo->prepare("DELETE FROM event_footer_logos WHERE id = ? AND user_id = ?")->execute([$_POST['delete_footer_id'], $uid]);
        }

        // F. HANDLE EVENT POSTER
        if (!empty($_FILES['banner']['name'])) {
            $ext = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
            $fn = "poster_" . $uid . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['banner']['tmp_name'], $targetDir . $fn)) {
                $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?")->execute(["uploads/logos/" . $fn, $uid]);
            }
        }

        $pdo->commit();
        $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Perubahan tersimpan!';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['toast_type'] = 'error'; $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage();
    }
    header("Location: event_profile.php"); exit;
}

// --- 2. AMBIL DATA ---
$row = $pdo->query("SELECT * FROM users WHERE id = $uid")->fetch();
$footerLogos = $pdo->query("SELECT * FROM event_footer_logos WHERE user_id = $uid")->fetchAll(); // Ambil list logo footer

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    <div class="max-w-5xl mx-auto mb-10 flex justify-between items-end">
        <div>
            <h1 class="text-4xl font-black uppercase italic text-slate-900 leading-none">Event Settings</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Identitas & Status Operasional</p>
        </div>
        <div class="bg-white px-5 py-2 rounded-2xl border border-slate-200 shadow-sm text-[10px] font-black uppercase text-blue-600">
            <?= htmlspecialchars($row['event_type'] ?? 'Standard') ?>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" class="max-w-5xl mx-auto space-y-8 pb-32">
        
        <div class="bg-slate-900 rounded-[2.5rem] shadow-2xl p-8 text-white">
            <h3 class="font-black uppercase text-sm mb-6 flex items-center gap-3 italic">📡 Status Event</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php 
                $statuses = ['Registration' => '📝 Pendaftaran', 'Running' => '🏊 Live / Jalan', 'Finished' => '🏁 Selesai'];
                foreach($statuses as $val => $label): 
                ?>
                <label class="cursor-pointer group">
                    <input type="radio" name="event_status" value="<?= $val ?>" class="hidden peer" <?= ($row['event_status']??'') == $val ? 'checked' : '' ?>>
                    <div class="p-4 rounded-2xl border border-white/10 bg-white/5 peer-checked:bg-blue-600 peer-checked:border-blue-500 text-center transition">
                        <span class="font-bold uppercase text-xs"><?= $label ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-10">
            <h3 class="font-black uppercase text-sm mb-8 text-blue-600 italic">📝 Info Utama</h3>
            <div class="grid gap-6">
                <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($row['nama_lengkap'] ?? '') ?>" placeholder="Nama Event" class="w-full px-6 py-4 border-2 border-slate-100 bg-slate-50 rounded-2xl font-black text-lg uppercase outline-none focus:bg-white focus:border-blue-500 transition">
                <div class="grid grid-cols-2 gap-6">
                    <input type="date" name="event_start_date" value="<?= $row['event_start_date'] ?? '' ?>" class="w-full px-4 py-3 border border-slate-200 rounded-xl">
                    <input type="date" name="event_end_date" value="<?= $row['event_end_date'] ?? '' ?>" class="w-full px-4 py-3 border border-slate-200 rounded-xl">
                </div>
                <div class="grid grid-cols-2 gap-6">
                    <input type="text" name="location" value="<?= htmlspecialchars($row['location'] ?? '') ?>" placeholder="Kota" class="w-full px-4 py-3 border border-slate-200 rounded-xl font-bold uppercase">
                    <input type="text" name="venue_name" value="<?= htmlspecialchars($row['venue_name'] ?? '') ?>" placeholder="Kolam Renang" class="w-full px-4 py-3 border border-slate-200 rounded-xl font-bold uppercase">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-10">
            <h3 class="font-black uppercase text-sm mb-8 text-orange-600 italic">⚙️ Teknis</h3>
            <div class="grid grid-cols-3 gap-6">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Lintasan</label>
                    <input type="number" name="lane_count" value="<?= $row['lane_count'] ?: 8 ?>" min="4" max="10" class="w-full px-4 py-3 border-2 border-slate-100 bg-slate-50 rounded-xl font-black text-center">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Usia Per</label>
                    <select name="age_calculation_type" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm"><option value="Dec 31">31 Des</option><option value="Meet Start">Hari H</option></select>
                </div>
                <div>
                     <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Sistem</label>
                     <input type="text" readonly value="<?= htmlspecialchars($row['event_type']) ?>" class="w-full px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl text-xs font-bold text-slate-500 uppercase">
                     <input type="hidden" name="event_type" value="<?= htmlspecialchars($row['event_type']) ?>">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-10">
            <h3 class="font-black uppercase text-sm mb-8 text-slate-800 italic flex items-center gap-2">
                <span>🖼️</span> Layout Buku Acara
            </h3>
            
            <div class="grid grid-cols-2 gap-8 mb-10 border-b border-dashed border-slate-200 pb-10">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Header Kiri (Logo)</label>
                    <div class="h-32 bg-slate-50 rounded-2xl border-2 border-dashed flex items-center justify-center overflow-hidden mb-2">
                        <?php if(!empty($row['logo_left'])): ?><img src="../../../public/<?= $row['logo_left'] ?>" class="h-24 object-contain"><?php else: ?><span class="text-xs text-slate-300">Kosong</span><?php endif; ?>
                    </div>
                    <input type="file" name="logo_left" class="text-[10px] w-full file:mr-2 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-slate-100 file:text-slate-700">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Header Kanan (Logo)</label>
                    <div class="h-32 bg-slate-50 rounded-2xl border-2 border-dashed flex items-center justify-center overflow-hidden mb-2">
                        <?php if(!empty($row['logo_right'])): ?><img src="../../../public/<?= $row['logo_right'] ?>" class="h-24 object-contain"><?php else: ?><span class="text-xs text-slate-300">Kosong</span><?php endif; ?>
                    </div>
                    <input type="file" name="logo_right" class="text-[10px] w-full file:mr-2 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-slate-100 file:text-slate-700">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Footer Sponsors (Bisa Banyak)</label>
                
                <?php if(!empty($footerLogos)): ?>
                <div class="flex flex-wrap gap-4 mb-4">
                    <?php foreach($footerLogos as $fl): ?>
                    <div class="relative group w-24 h-16 bg-white border border-slate-200 rounded-lg flex items-center justify-center p-2 shadow-sm">
                        <img src="../../../public/<?= $fl['image_path'] ?>" class="max-h-full max-w-full object-contain">
                        <button type="submit" name="delete_footer_id" value="<?= $fl['id'] ?>" class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition shadow-md" onclick="return confirm('Hapus logo ini?')">×</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="flex items-center gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                    <div class="flex-1">
                        <input type="file" name="footer_logos[]" multiple class="w-full text-xs file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-[10px] text-slate-400 mt-1 italic">Tahan tombol CTRL / Command untuk memilih banyak gambar sekaligus.</p>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="w-full bg-slate-900 text-white font-bold py-6 rounded-3xl shadow-lg hover:bg-slate-800 transition uppercase tracking-widest text-sm">Simpan Pengaturan</button>
    </form>
</div>