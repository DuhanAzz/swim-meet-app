<?php
// FILE: src/admin/settings/event_profile.php
session_start();

// --- 1. CONFIG PATH ---
require_once __DIR__ . '/../../../src/config/database.php';

// CEK OTORITAS
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}
$uid = $_SESSION['user_id'];

// --- 2. LOGIKA MENCARI EVENT ---
$eventId = $_GET['event_id'] ?? 0;

if ($eventId == 0) {
    // [FIX] Menggunakan 'user_id' sesuai database lama (bukan created_by)
    $stmtFind = $pdo->prepare("SELECT id FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtFind->execute([$uid]);
    $lastEvent = $stmtFind->fetch();
    if ($lastEvent) {
        $eventId = $lastEvent['id'];
    }
}

// --- 3. HANDLE SIMPAN DATA (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        $targetDir = __DIR__ . "/../../../public/uploads/logos/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        // Parameter Input
        $params = [
            $_POST['nama_event'] ?? '', 
            $_POST['lokasi'] ?? '', 
            $_POST['venue_name'] ?? '', 
            !empty($_POST['event_start_date']) ? $_POST['event_start_date'] : NULL, 
            !empty($_POST['event_end_date']) ? $_POST['event_end_date'] : NULL,
            !empty($_POST['event_start_date']) ? $_POST['event_start_date'] : NULL, // tanggal_pelaksanaan
            (int)($_POST['lane_count'] ?? 8), 
            $_POST['pool_type'] ?? 'LCM',
            $_POST['age_calculation_type'] ?? 'Dec 31', 
            $_POST['event_type'] ?? 'Standard',
            $_POST['participation_type'] ?? 'club',
            $_POST['status'] ?? 'upcoming',
            $_POST['bank_name'] ?? '', 
            $_POST['bank_account_number'] ?? '', 
            $_POST['bank_account_name'] ?? '',
            $uid // [FIX] user_id
        ];

        if ($eventId == 0) {
            // INSERT BARU (Menggunakan user_id)
            $sql = "INSERT INTO events (
                        nama_event, lokasi, venue_name, 
                        event_start_date, event_end_date, tanggal_pelaksanaan,
                        lane_count, pool_type, age_calculation_type, event_type, 
                        participation_type, status,
                        bank_name, bank_account_number, bank_account_name,
                        user_id, nomor_acara 
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '0')"; 
            $pdo->prepare($sql)->execute($params);
            $eventId = $pdo->lastInsertId(); 
        } else {
            // UPDATE (Menggunakan user_id)
            $params[] = $eventId; 
            $sql = "UPDATE events SET 
                    nama_event = ?, lokasi = ?, venue_name = ?, 
                    event_start_date = ?, event_end_date = ?, tanggal_pelaksanaan = ?,
                    lane_count = ?, pool_type = ?, age_calculation_type = ?, event_type = ?, 
                    participation_type = ?, status = ?,
                    bank_name = ?, bank_account_number = ?, bank_account_name = ?
                    WHERE user_id = ? AND id = ?"; 
            $pdo->prepare($sql)->execute($params);
        }

        // HANDLE LOGO KIRI
        if (!empty($_FILES['logo_left']['name'])) {
            $ext = pathinfo($_FILES['logo_left']['name'], PATHINFO_EXTENSION);
            $fn = "LOGO_L_" . $eventId . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['logo_left']['tmp_name'], $targetDir . $fn)) {
                $pdo->prepare("UPDATE events SET logo_left = ? WHERE id = ?")->execute(["uploads/logos/" . $fn, $eventId]);
            }
        }
        // HANDLE LOGO KANAN
        if (!empty($_FILES['logo_right']['name'])) {
            $ext = pathinfo($_FILES['logo_right']['name'], PATHINFO_EXTENSION);
            $fn = "LOGO_R_" . $eventId . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['logo_right']['tmp_name'], $targetDir . $fn)) {
                $pdo->prepare("UPDATE events SET logo_right = ? WHERE id = ?")->execute(["uploads/logos/" . $fn, $eventId]);
            }
        }

        // HANDLE FOOTER SPONSORS
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

        // DELETE SPONSOR
        if (isset($_POST['delete_footer_id'])) {
            $pdo->prepare("DELETE FROM event_sponsors WHERE id = ? AND event_id = ?")->execute([$_POST['delete_footer_id'], $eventId]);
        }

        $pdo->commit();
        $_SESSION['msg'] = "Berhasil menyimpan data event!";
        $_SESSION['msg_type'] = "success";
        header("Location: event_profile.php?event_id=" . $eventId); exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['msg'] = "Gagal: " . $e->getMessage();
        $_SESSION['msg_type'] = "error";
    }
}

// --- 4. AMBIL DATA ---
$row = []; 
$footerLogos = [];

if ($eventId > 0) {
    // [FIX] Select menggunakan user_id
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND user_id = ?");
    $stmt->execute([$eventId, $uid]);
    $row = $stmt->fetch();
    
    if ($row) {
        $stmtFooter = $pdo->prepare("SELECT * FROM event_sponsors WHERE event_id = ?");
        $stmtFooter->execute([$eventId]);
        $footerLogos = $stmtFooter->fetchAll();
    } else {
        $eventId = 0; 
    }
}

// VIEW
include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter">
                <?= ($eventId > 0) ? 'Edit Event Profile' : 'Buat Event Baru' ?>
            </h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-1">
                Pengaturan Identitas & Status Kompetisi
            </p>
        </div>
        <?php if($eventId > 0): ?>
            <span class="px-4 py-2 bg-slate-200 text-slate-600 rounded-lg text-[10px] font-black uppercase tracking-widest border border-slate-300">
                ID: #<?= $eventId ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if(isset($_SESSION['msg'])): ?>
        <div class="p-4 mb-6 rounded-xl text-sm font-bold border flex items-center gap-3 shadow-sm 
            <?= $_SESSION['msg_type'] == 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
            <?= htmlspecialchars($_SESSION['msg']) ?>
        </div>
        <?php unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-8">
            
            <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 p-8">
                <h3 class="font-black text-slate-800 uppercase italic text-xs tracking-widest mb-6 flex items-center gap-2">
                    <span class="w-2 h-6 bg-blue-600 rounded-full"></span> Informasi Kompetisi
                </h3>
                
                <div class="space-y-6">
                    <div>
                        <label class="block text-[11px] font-black uppercase text-slate-400 tracking-widest mb-2">Nama Event</label>
                        <input type="text" name="nama_event" required value="<?= htmlspecialchars($row['nama_event'] ?? '') ?>" 
                            class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500 font-bold text-slate-800 placeholder-slate-300" 
                            placeholder="Contoh: KEJUARAAN RENANG 2026">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[11px] font-black uppercase text-slate-400 tracking-widest mb-2">Tgl Mulai</label>
                            <input type="date" name="event_start_date" value="<?= $row['event_start_date'] ?? ($row['tanggal_pelaksanaan'] ?? '') ?>" class="w-full px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-700">
                        </div>
                        <div>
                            <label class="block text-[11px] font-black uppercase text-slate-400 tracking-widest mb-2">Tgl Selesai</label>
                            <input type="date" name="event_end_date" value="<?= $row['event_end_date'] ?? '' ?>" class="w-full px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-700">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[11px] font-black uppercase text-slate-400 tracking-widest mb-2">Lokasi / Kota</label>
                            <input type="text" name="lokasi" required value="<?= htmlspecialchars($row['lokasi'] ?? '') ?>" class="w-full px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-700" placeholder="Contoh: JAKARTA">
                        </div>
                        <div>
                            <label class="block text-[11px] font-black uppercase text-slate-400 tracking-widest mb-2">Nama Venue</label>
                            <input type="text" name="venue_name" value="<?= htmlspecialchars($row['venue_name'] ?? '') ?>" class="w-full px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-700" placeholder="Contoh: STADION AQUATIC GBK">
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-indigo-50 rounded-[2rem] shadow-sm border border-indigo-100 p-8">
                <h3 class="font-black text-indigo-900 uppercase italic text-xs tracking-widest mb-6 flex items-center gap-2">
                    <span class="w-2 h-6 bg-indigo-600 rounded-full"></span> Spesifikasi Teknis
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-bold text-indigo-400 uppercase mb-2">Jumlah Lintasan</label>
                        <input type="number" name="lane_count" value="<?= $row['lane_count'] ?? 8 ?>" min="4" max="10" class="w-full px-4 py-3 rounded-xl border border-indigo-200 font-black text-indigo-900">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-indigo-400 uppercase mb-2">Hitung Umur Per</label>
                        <select name="age_calculation_type" class="w-full px-4 py-3 rounded-xl border border-indigo-200 font-bold text-slate-700">
                            <option value="Dec 31" <?= (($row['age_calculation_type'] ?? 'Dec 31') =='Dec 31')?'selected':'' ?>>31 Desember</option>
                            <option value="Meet Start" <?= (($row['age_calculation_type'] ?? '') =='Meet Start')?'selected':'' ?>>Hari H Lomba</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-indigo-400 uppercase mb-2">Tipe Kolam</label>
                        <select name="pool_type" class="w-full px-4 py-3 rounded-xl border border-indigo-200 font-bold text-slate-700">
                            <option value="LCM" <?= ($row['pool_type'] ?? 'LCM') == 'LCM' ? 'selected' : '' ?>>50 Meter (LCM)</option>
                            <option value="SCM" <?= ($row['pool_type'] ?? '') == 'SCM' ? 'selected' : '' ?>>25 Meter (SCM)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-indigo-400 uppercase mb-2">Tipe Partisipasi</label>
                        <select name="participation_type" class="w-full px-4 py-3 rounded-xl border border-indigo-200 font-bold text-slate-700">
                            <option value="club" <?= ($row['participation_type'] ?? 'club') == 'club' ? 'selected' : '' ?>>Antar Club</option>
                            <option value="school" <?= ($row['participation_type'] ?? '') == 'school' ? 'selected' : '' ?>>Antar Sekolah</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 p-8">
                <h3 class="font-black text-slate-800 uppercase italic text-xs tracking-widest mb-6 flex items-center gap-2">
                    <span class="w-2 h-6 bg-emerald-500 rounded-full"></span> Info Pembayaran
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nama Bank</label>
                        <input type="text" name="bank_name" value="<?= htmlspecialchars($row['bank_name'] ?? '') ?>" class="w-full px-4 py-3 border border-slate-200 rounded-xl font-bold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">No Rekening</label>
                        <input type="text" name="bank_account_number" value="<?= htmlspecialchars($row['bank_account_number'] ?? '') ?>" class="w-full px-4 py-3 border border-slate-200 rounded-xl font-bold font-mono">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Atas Nama</label>
                        <input type="text" name="bank_account_name" value="<?= htmlspecialchars($row['bank_account_name'] ?? '') ?>" class="w-full px-4 py-3 border border-slate-200 rounded-xl font-bold">
                    </div>
                </div>
            </div>

        </div>

        <div class="space-y-8">
            
            <div class="bg-slate-800 text-white rounded-[2rem] shadow-xl p-8 h-fit">
                <h3 class="font-black uppercase italic text-xs tracking-widest mb-6 text-slate-400">Status Event</h3>
                <div class="space-y-4">
                    <?php 
                    $statuses = [
                        'upcoming' => ['label' => 'Upcoming / Draft', 'color' => 'text-slate-300'],
                        'open'     => ['label' => 'Open Registration', 'color' => 'text-blue-400'],
                        'closed'   => ['label' => 'Closed / Running', 'color' => 'text-emerald-400'],
                        'done'     => ['label' => 'Finished', 'color' => 'text-red-400']
                    ];
                    // Mapping data lama ke baru jika perlu
                    $currentStatus = $row['status'] ?? 'upcoming';
                    if($currentStatus == 'Draft') $currentStatus = 'upcoming';
                    if($currentStatus == 'Registration') $currentStatus = 'open';
                    if($currentStatus == 'Finished') $currentStatus = 'done';

                    foreach($statuses as $val => $info): 
                    ?>
                    <label class="flex items-center gap-3 p-3 bg-slate-700 rounded-xl cursor-pointer hover:bg-slate-600 transition">
                        <input type="radio" name="status" value="<?= $val ?>" class="w-5 h-5 bg-slate-900 border-none focus:ring-0" 
                            <?= $currentStatus == $val ? 'checked' : '' ?>>
                        <span class="block text-xs font-black uppercase <?= $info['color'] ?>"><?= $info['label'] ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="mt-8 pt-8 border-t border-slate-700">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-black py-4 rounded-xl uppercase tracking-widest text-xs shadow-lg transition transform hover:scale-105">
                        <?= ($eventId > 0) ? 'Simpan Perubahan' : 'Buat Event' ?>
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 p-8 h-fit">
                <h3 class="font-black text-slate-800 uppercase italic text-xs tracking-widest mb-6">🖼️ Branding</h3>
                
                <div class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Logo Kiri</label>
                        <?php if(!empty($row['logo_left'])): ?>
                            <div class="bg-slate-50 p-2 rounded-lg border border-dashed border-slate-300 mb-2 flex justify-center">
                                <img src="../../../public/<?= $row['logo_left'] ?>" class="h-16 object-contain">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="logo_left" class="text-[10px] w-full text-slate-500">
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Logo Kanan</label>
                        <?php if(!empty($row['logo_right'])): ?>
                            <div class="bg-slate-50 p-2 rounded-lg border border-dashed border-slate-300 mb-2 flex justify-center">
                                <img src="../../../public/<?= $row['logo_right'] ?>" class="h-16 object-contain">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="logo_right" class="text-[10px] w-full text-slate-500">
                    </div>

                    <div class="border-t pt-4 mt-4">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Footer Sponsors</label>
                        <?php if(!empty($footerLogos)): ?>
                            <div class="flex flex-wrap gap-3 mb-4">
                            <?php foreach($footerLogos as $fl): ?>
                                <div class="relative w-20 h-14 bg-white border rounded-lg flex items-center justify-center p-1 shadow-sm">
                                    <img src="../../../public/<?= $fl['image_path'] ?>" class="max-h-full max-w-full object-contain">
                                    <button type="submit" name="delete_footer_id" value="<?= $fl['id'] ?>" class="absolute -top-2 -right-2 bg-red-500 text-white w-5 h-5 rounded-full text-[10px] flex items-center justify-center font-bold hover:bg-red-600 shadow-md" onclick="return confirm('Hapus logo ini?')">×</button>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="footer_logos[]" multiple class="text-[10px] w-full text-slate-500">
                        <p class="text-[9px] text-slate-400 mt-2 italic">Ctrl/Cmd + Klik untuk pilih banyak.</p>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>