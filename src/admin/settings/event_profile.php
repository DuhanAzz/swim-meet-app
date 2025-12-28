<?php
// src/admin/settings/event_profile.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Otoritas
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

// --- LOGIKA ID EVENT ---
// Kita coba ambil ID dari URL, jika tidak ada, ambil event terakhir milik user ini.
$eventId = $_GET['event_id'] ?? 0;

if ($eventId == 0) {
    $stmtFind = $pdo->prepare("SELECT id FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtFind->execute([$uid]);
    $lastEvent = $stmtFind->fetch();
    if ($lastEvent) {
        $eventId = $lastEvent['id'];
    } else {
        die("Anda belum memiliki Event. Silakan buat event baru terlebih dahulu.");
    }
}

// --- 1. HANDLE SEMUA PROSES UPDATE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        $targetDir = __DIR__ . "/../../../public/uploads/logos/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        // A. UPDATE INFO UTAMA & SPESIFIKASI KE TABEL EVENTS
        $sql = "UPDATE events SET 
                nama_event = ?, lokasi = ?, venue_name = ?, 
                event_start_date = ?, event_end_date = ?, tanggal_pelaksanaan = ?,
                lane_count = ?, pool_type = ?, age_calculation_type = ?, event_type = ?, status = ?,
                bank_name = ?, bank_account_number = ?, bank_account_name = ?
                WHERE id = ? AND user_id = ?";
        
        // Kita samakan tanggal_pelaksanaan dengan start_date agar konsisten
        $pdo->prepare($sql)->execute([
            $_POST['nama_event'] ?? '', 
            $_POST['lokasi'] ?? '', 
            $_POST['venue_name'] ?? '', 
            $_POST['event_start_date'] ?? NULL, 
            $_POST['event_end_date'] ?? NULL,
            $_POST['event_start_date'] ?? NULL, // Update juga kolom tanggal_pelaksanaan utama
            (int)($_POST['lane_count'] ?? 8), 
            $_POST['pool_type'] ?? 'LCM',
            $_POST['age_calculation_type'] ?? 'Dec 31', 
            $_POST['event_type'] ?? 'Standard', 
            $_POST['status'] ?? 'upcoming',
            $_POST['bank_name'] ?? '', 
            $_POST['bank_account_number'] ?? '', 
            $_POST['bank_account_name'] ?? '',
            $eventId,
            $uid
        ]);

        // B. HANDLE LOGO KIRI & KANAN
        // Rename file pakai event_id biar tidak tertukar
        if (!empty($_FILES['logo_left']['name'])) {
            $ext = pathinfo($_FILES['logo_left']['name'], PATHINFO_EXTENSION);
            $fn = "LOGO_L_" . $eventId . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['logo_left']['tmp_name'], $targetDir . $fn)) {
                $pdo->prepare("UPDATE events SET logo_left = ? WHERE id = ?")->execute(["uploads/logos/" . $fn, $eventId]);
            }
        }
        if (!empty($_FILES['logo_right']['name'])) {
            $ext = pathinfo($_FILES['logo_right']['name'], PATHINFO_EXTENSION);
            $fn = "LOGO_R_" . $eventId . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['logo_right']['tmp_name'], $targetDir . $fn)) {
                $pdo->prepare("UPDATE events SET logo_right = ? WHERE id = ?")->execute(["uploads/logos/" . $fn, $eventId]);
            }
        }

        // C. HANDLE MULTIPLE FOOTER SPONSORS (Tabel: event_sponsors)
        if (!empty($_FILES['footer_logos']['name'][0])) {
            $insFooter = $pdo->prepare("INSERT INTO event_sponsors (event_id, image_path) VALUES (?, ?)");
            foreach ($_FILES['footer_logos']['name'] as $key => $name) {
                if ($_FILES['footer_logos']['error'][$key] === 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $fn = "SPONSOR_" . $eventId . "_" . time() . "_" . $key . "." . $ext;
                    if(move_uploaded_file($_FILES['footer_logos']['tmp_name'][$key], $targetDir . $fn)) {
                        $insFooter->execute([$eventId, "uploads/logos/" . $fn]);
                    }
                }
            }
        }

        // D. HAPUS LOGO FOOTER TERTENTU
        if (isset($_POST['delete_footer_id'])) {
            $pdo->prepare("DELETE FROM event_sponsors WHERE id = ? AND event_id = ?")->execute([$_POST['delete_footer_id'], $eventId]);
        }

        $pdo->commit();
        $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Konfigurasi Event Berhasil Disimpan!';
        
        // Refresh halaman dengan ID yang benar
        header("Location: event_profile.php?event_id=" . $eventId); exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['toast_type'] = 'error'; $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage();
    }
}

// --- 2. AMBIL DATA DARI TABEL EVENTS ---
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND user_id = ?");
$stmt->execute([$eventId, $uid]);
$row = $stmt->fetch();

if (!$row) die("Data Event tidak ditemukan.");

// Ambil Sponsors
$footerLogos = $pdo->prepare("SELECT * FROM event_sponsors WHERE event_id = ?");
$footerLogos->execute([$eventId]);
$footerLogos = $footerLogos->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="max-w-5xl mx-auto mb-10 flex justify-between items-end">
        <div>
            <a href="../kompetisi/manage_entries.php?event_id=<?= $eventId ?>" class="text-xs font-bold text-slate-400 hover:text-blue-600 uppercase mb-2 block">← Kembali ke Dashboard</a>
            
            <h1 class="text-4xl font-black uppercase italic text-slate-900 leading-none">Event Settings</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Identitas, Spesifikasi & Status</p>
        </div>
        <div class="flex gap-2">
            <div class="bg-white px-5 py-2 rounded-2xl border border-slate-200 shadow-sm text-[10px] font-black uppercase text-blue-600">
                <?= htmlspecialchars($row['event_type'] ?? 'Standard') ?>
            </div>
        </div>
    </div>

    <?php if(isset($_SESSION['toast_message'])): ?>
        <div class="max-w-5xl mx-auto mb-6 p-4 rounded-xl font-bold text-sm shadow-sm flex items-center gap-3 <?= $_SESSION['toast_type'] == 'success' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-red-100 text-red-800 border border-red-300' ?>">
            <span><?= $_SESSION['toast_type'] == 'success' ? '✅' : '⚠️' ?></span>
            <?= $_SESSION['toast_message'] ?>
        </div>
        <?php unset($_SESSION['toast_message'], $_SESSION['toast_type']); ?>
    <?php endif; ?>

    <form method="POST" action="?event_id=<?= $eventId ?>" enctype="multipart/form-data" class="max-w-5xl mx-auto space-y-8 pb-32">
        
        <div class="bg-slate-900 rounded-[2.5rem] shadow-2xl p-8 text-white relative overflow-hidden">
            <div class="absolute top-0 right-0 p-10 opacity-10 text-9xl rotate-12">📡</div>
            <h3 class="font-black uppercase text-sm mb-6 flex items-center gap-3 italic relative z-10">Status Event</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 relative z-10">
                <?php 
                $statuses = [
                    'upcoming' => '📅 Upcoming', 
                    'open' => '🟢 Open Registration', 
                    'closed' => '🔴 Closed / Running', 
                    'done' => '🏁 Finished'
                ];
                foreach($statuses as $val => $label): 
                ?>
                <label class="cursor-pointer group">
                    <input type="radio" name="status" value="<?= $val ?>" class="hidden peer" <?= ($row['status']??'upcoming') == $val ? 'checked' : '' ?>>
                    <div class="p-4 rounded-2xl border border-white/10 bg-white/5 peer-checked:bg-blue-600 peer-checked:border-blue-500 text-center transition hover:bg-white/10">
                        <span class="font-bold uppercase text-xs tracking-wider"><?= $label ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-10">
            <h3 class="font-black uppercase text-sm mb-8 text-blue-600 italic">📝 Info Utama</h3>
            <div class="grid gap-6">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nama Lengkap Event</label>
                    <input type="text" name="nama_event" value="<?= htmlspecialchars($row['nama_event'] ?? '') ?>" placeholder="Contoh: KEJURKAB RENANG 2025" class="w-full px-6 py-4 border-2 border-slate-100 bg-slate-50 rounded-2xl font-black text-lg uppercase outline-none focus:bg-white focus:border-blue-500 transition">
                </div>
                
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tanggal Mulai</label>
                        <input type="date" name="event_start_date" value="<?= $row['event_start_date'] ?? '' ?>" class="w-full px-4 py-3 border border-slate-200 rounded-xl font-bold text-slate-700">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tanggal Selesai</label>
                        <input type="date" name="event_end_date" value="<?= $row['event_end_date'] ?? '' ?>" class="w-full px-4 py-3 border border-slate-200 rounded-xl font-bold text-slate-700">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Kota / Lokasi</label>
                        <input type="text" name="lokasi" value="<?= htmlspecialchars($row['lokasi'] ?? '') ?>" placeholder="Contoh: JAKARTA" class="w-full px-4 py-3 border border-slate-200 rounded-xl font-bold uppercase">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nama Kolam (Venue)</label>
                        <input type="text" name="venue_name" value="<?= htmlspecialchars($row['venue_name'] ?? '') ?>" placeholder="Contoh: STADION AQUATIC GBK" class="w-full px-4 py-3 border border-slate-200 rounded-xl font-bold uppercase">
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-indigo-50 rounded-[2.5rem] shadow-sm border border-indigo-200 p-10 relative overflow-hidden">
             <div class="absolute top-0 right-0 p-10 opacity-5 text-9xl">🏊</div>
            <h3 class="font-black uppercase text-sm mb-8 text-indigo-700 italic flex items-center gap-2 relative z-10">
                <span>⚙️</span> Spesifikasi Teknis & Kolam
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10">
                
                <div class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Jumlah Lintasan (Lanes)</label>
                        <div class="flex items-center gap-3">
                            <input type="number" name="lane_count" value="<?= $row['lane_count'] ?: 8 ?>" min="4" max="10" class="w-24 text-center font-black text-2xl border-2 border-indigo-200 rounded-xl py-2 focus:border-indigo-600 focus:ring-0 text-indigo-900">
                            <span class="text-xs font-bold text-indigo-400 uppercase">Lintasan Aktif</span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Perhitungan Umur Peserta</label>
                        <select name="age_calculation_type" class="w-full px-4 py-3 border border-indigo-200 rounded-xl text-sm font-bold text-slate-700">
                            <option value="Dec 31" <?= ($row['age_calculation_type']=='Dec 31')?'selected':'' ?>>Per 31 Desember (Tahun Berjalan)</option>
                            <option value="Meet Start" <?= ($row['age_calculation_type']=='Meet Start')?'selected':'' ?>>Per Hari H Lomba (Actual Age)</option>
                        </select>
                    </div>
                    
                    <input type="hidden" name="event_type" value="<?= htmlspecialchars($row['event_type']) ?>">
                </div>

                <div class="bg-white p-6 rounded-2xl border border-indigo-100 shadow-sm">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-4">Tipe & Panjang Kolam (Pool Type)</label>
                    
                    <div class="flex flex-col gap-3">
                        <label class="relative flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all <?= ($row['pool_type'] == 'SCM') ? 'border-indigo-600 bg-indigo-50' : 'border-slate-100 hover:border-slate-300' ?>">
                            <input type="radio" name="pool_type" value="SCM" class="hidden peer" <?= ($row['pool_type'] == 'SCM') ? 'checked' : '' ?>>
                            <div class="w-5 h-5 rounded-full border-2 border-slate-300 peer-checked:border-indigo-600 peer-checked:bg-indigo-600 flex items-center justify-center">
                                <div class="w-2 h-2 bg-white rounded-full"></div>
                            </div>
                            <div>
                                <span class="block font-black text-slate-800 text-sm">25m (Short Course)</span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">SCM • Semi-Olympic</span>
                            </div>
                        </label>

                        <label class="relative flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all <?= ($row['pool_type'] == 'LCM' || !$row['pool_type']) ? 'border-indigo-600 bg-indigo-50' : 'border-slate-100 hover:border-slate-300' ?>">
                            <input type="radio" name="pool_type" value="LCM" class="hidden peer" <?= ($row['pool_type'] == 'LCM' || !$row['pool_type']) ? 'checked' : '' ?>>
                            <div class="w-5 h-5 rounded-full border-2 border-slate-300 peer-checked:border-indigo-600 peer-checked:bg-indigo-600 flex items-center justify-center">
                                <div class="w-2 h-2 bg-white rounded-full"></div>
                            </div>
                            <div>
                                <span class="block font-black text-slate-800 text-sm">50m (Long Course)</span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">LCM • Olympic Standard</span>
                            </div>
                        </label>
                    </div>
                </div>

            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-10">
            <h3 class="font-black uppercase text-sm mb-8 text-emerald-600 italic">💳 Info Pembayaran</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nama Bank</label>
                    <input type="text" name="bank_name" value="<?= htmlspecialchars($row['bank_name'] ?? '') ?>" placeholder="BCA / MANDIRI" class="w-full px-4 py-3 border border-slate-200 rounded-xl font-bold">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nomor Rekening</label>
                    <input type="text" name="bank_account_number" value="<?= htmlspecialchars($row['bank_account_number'] ?? '') ?>" placeholder="123xxxxx" class="w-full px-4 py-3 border border-slate-200 rounded-xl font-bold font-mono">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Atas Nama</label>
                    <input type="text" name="bank_account_name" value="<?= htmlspecialchars($row['bank_account_name'] ?? '') ?>" placeholder="Nama Pemilik Rekening" class="w-full px-4 py-3 border border-slate-200 rounded-xl font-bold">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-10">
            <h3 class="font-black uppercase text-sm mb-8 text-slate-800 italic flex items-center gap-2">
                <span>🖼️</span> Branding & Layout
            </h3>
            
            <div class="grid grid-cols-2 gap-8 mb-10 border-b border-dashed border-slate-200 pb-10">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Header Kiri (Logo)</label>
                    <div class="h-32 bg-slate-50 rounded-2xl border-2 border-dashed flex items-center justify-center overflow-hidden mb-2 relative group">
                        <?php if(!empty($row['logo_left'])): ?>
                            <img src="../../../public/<?= $row['logo_left'] ?>" class="h-24 object-contain">
                        <?php else: ?>
                            <span class="text-xs text-slate-300 font-bold">Upload Logo</span>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="logo_left" class="text-[10px] w-full file:mr-2 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Header Kanan (Logo)</label>
                    <div class="h-32 bg-slate-50 rounded-2xl border-2 border-dashed flex items-center justify-center overflow-hidden mb-2 relative group">
                        <?php if(!empty($row['logo_right'])): ?>
                            <img src="../../../public/<?= $row['logo_right'] ?>" class="h-24 object-contain">
                        <?php else: ?>
                            <span class="text-xs text-slate-300 font-bold">Upload Logo</span>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="logo_right" class="text-[10px] w-full file:mr-2 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Footer Sponsors (Bottom Page)</label>
                
                <?php if(!empty($footerLogos)): ?>
                <div class="flex flex-wrap gap-4 mb-4">
                    <?php foreach($footerLogos as $fl): ?>
                    <div class="relative group w-24 h-16 bg-white border border-slate-200 rounded-lg flex items-center justify-center p-2 shadow-sm hover:shadow-md transition">
                        <img src="../../../public/<?= $fl['image_path'] ?>" class="max-h-full max-w-full object-contain">
                        <button type="submit" name="delete_footer_id" value="<?= $fl['id'] ?>" class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition shadow-md hover:bg-red-600 font-bold" onclick="return confirm('Hapus logo ini?')">×</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="flex items-center gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                    <div class="flex-1">
                        <input type="file" name="footer_logos[]" multiple class="w-full text-xs file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                        <p class="text-[10px] text-slate-400 mt-2 italic flex items-center gap-1">
                            <span>💡</span> Tahan tombol <strong>CTRL / Command</strong> saat memilih file untuk mengupload banyak logo sekaligus.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky bottom-6 z-50">
            <button type="submit" class="w-full bg-slate-900 text-white font-black py-5 rounded-2xl shadow-2xl hover:bg-blue-700 transition transform hover:-translate-y-1 uppercase tracking-widest text-sm flex items-center justify-center gap-3 border-2 border-slate-800 hover:border-blue-500">
                <span>💾</span> Simpan Konfigurasi
            </button>
        </div>

    </form>
</div>