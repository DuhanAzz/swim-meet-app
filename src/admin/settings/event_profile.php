<?php
// FILE: src/admin/settings/event_profile.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// CEK OTORITAS
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}
$uid = $_SESSION['user_id'];

// --- 1. LOGIKA MENCARI EVENT ---
$eventId = $_GET['event_id'] ?? 0;

if ($eventId == 0) {
    $stmtFind = $pdo->prepare("SELECT id FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtFind->execute([$uid]);
    $lastEvent = $stmtFind->fetch();
    if ($lastEvent) $eventId = $lastEvent['id'];
}

// --- 2. FITUR HAPUS SPONSOR ---
if (isset($_GET['del_sponsor']) && $eventId > 0) {
    $sponsorId = $_GET['del_sponsor'];
    $stmt = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE id = ? AND event_id = ?");
    $stmt->execute([$sponsorId, $eventId]);
    $img = $stmt->fetch();
    
    if ($img) {
        $fullPath = __DIR__ . "/../../../public/" . $img['image_path'];
        if (file_exists($fullPath)) unlink($fullPath); 
        $pdo->prepare("DELETE FROM event_sponsors WHERE id = ?")->execute([$sponsorId]);
        $_SESSION['swal_type'] = "success";
        $_SESSION['swal_msg']  = "Logo sponsor berhasil dihapus";
    }
    header("Location: event_profile.php?event_id=" . $eventId); exit;
}

// --- 3. HANDLE SIMPAN DATA (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        $targetDir = __DIR__ . "/../../../public/uploads/logos/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        // MAPPING INPUT
        $eventName   = $_POST['nama_event'] ?? '';
        $eventLoc    = $_POST['lokasi'] ?? '';
        $dateStart   = !empty($_POST['event_start_date']) ? $_POST['event_start_date'] : NULL;
        $dateEnd     = !empty($_POST['event_end_date']) ? $_POST['event_end_date'] : NULL;
        $laneCount   = (int)($_POST['lane_count'] ?? 8);
        $poolType    = $_POST['pool_type'] ?? '50m';
        $ageCalc     = $_POST['age_calculation_type'] ?? 'Dec 31';
        $partType    = $_POST['participation_type'] ?? 'club';
        $status      = $_POST['status'] ?? 'upcoming'; 
        
        $bankName    = $_POST['bank_name'] ?? '';
        $bankRek     = $_POST['bank_account_number'] ?? '';
        $bankAtas    = $_POST['bank_account_name'] ?? '';

        if ($eventId == 0) {
            $sql = "INSERT INTO events (
                        user_id, event_name, event_location, event_date_start, event_date_end, 
                        lane_count, pool_type, age_calculation_type, participation_type, event_status,
                        bank_name, bank_account_number, bank_account_name
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; 
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$uid, $eventName, $eventLoc, $dateStart, $dateEnd, $laneCount, $poolType, $ageCalc, $partType, $status, $bankName, $bankRek, $bankAtas]);
            $eventId = $pdo->lastInsertId(); 
        } else {
            $sql = "UPDATE events SET 
                    event_name = ?, event_location = ?, event_date_start = ?, event_date_end = ?, 
                    lane_count = ?, pool_type = ?, age_calculation_type = ?, participation_type = ?, event_status = ?,
                    bank_name = ?, bank_account_number = ?, bank_account_name = ?
                    WHERE user_id = ? AND id = ?"; 
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$eventName, $eventLoc, $dateStart, $dateEnd, $laneCount, $poolType, $ageCalc, $partType, $status, $bankName, $bankRek, $bankAtas, $uid, $eventId]);
        }

        // --- HANDLE LOGO ---
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

        // --- HANDLE SPONSORS ---
        if (!empty($_FILES['sponsor_files']['name'][0])) {
            $totalFiles = count($_FILES['sponsor_files']['name']);
            $stmtSponsor = $pdo->prepare("INSERT INTO event_sponsors (event_id, image_path) VALUES (?, ?)");
            for($i=0; $i<$totalFiles; $i++) {
                if ($_FILES['sponsor_files']['tmp_name'][$i] != "") {
                    $ext = pathinfo($_FILES['sponsor_files']['name'][$i], PATHINFO_EXTENSION);
                    $newFileName = "SPONSOR_" . $eventId . "_" . time() . "_$i." . $ext;
                    if(move_uploaded_file($_FILES['sponsor_files']['tmp_name'][$i], $targetDir . $newFileName)) {
                        $stmtSponsor->execute([$eventId, "uploads/logos/" . $newFileName]);
                    }
                }
            }
        }

        $pdo->commit();
        $_SESSION['swal_type'] = "success";
        $_SESSION['swal_msg']  = "Perubahan Berhasil Disimpan!";
        header("Location: event_profile.php?event_id=" . $eventId); exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['swal_type'] = "error";
        $_SESSION['swal_msg']  = "Gagal: " . $e->getMessage();
    }
}

// --- 4. AMBIL DATA ---
$row = []; 
$sponsors = [];
if ($eventId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND user_id = ?");
    $stmt->execute([$eventId, $uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmtS = $pdo->prepare("SELECT * FROM event_sponsors WHERE event_id = ? ORDER BY id DESC");
    $stmtS->execute([$eventId]);
    $sponsors = $stmtS->fetchAll(PDO::FETCH_ASSOC);
}

function val($data, $key, $default = '') { return isset($data[$key]) ? htmlspecialchars($data[$key]) : $default; }

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
                ID Event: #<?= $eventId ?>
            </p>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-8">
            
            <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 p-8">
                <h3 class="font-black text-slate-800 uppercase italic text-xs tracking-widest mb-6 border-b pb-2">Informasi Utama</h3>
                <div class="space-y-6">
                    <div>
                        <label class="label-text">Nama Event</label>
                        <input type="text" name="nama_event" value="<?= val($row, 'event_name') ?>" class="input-field" required placeholder="Contoh: KEJUARAAN RENANG 2026">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="label-text">Tgl Mulai</label>
                            <input type="date" name="event_start_date" value="<?= val($row, 'event_date_start') ?>" class="input-field">
                        </div>
                        <div>
                            <label class="label-text">Tgl Selesai</label>
                            <input type="date" name="event_end_date" value="<?= val($row, 'event_date_end') ?>" class="input-field">
                        </div>
                    </div>
                    <div>
                        <label class="label-text">Lokasi (Nama Kolam & Kota)</label>
                        <input type="text" name="lokasi" value="<?= val($row, 'event_location') ?>" class="input-field" placeholder="Contoh: Stadion Akuatik GBK, Jakarta">
                    </div>
                </div>
            </div>

            <div class="bg-indigo-50 rounded-[2rem] shadow-sm border border-indigo-100 p-8">
                <h3 class="font-black text-indigo-900 uppercase italic text-xs tracking-widest mb-6 border-b border-indigo-200 pb-2">Spesifikasi Teknis</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-[10px] font-bold text-indigo-400 uppercase mb-2 block">Jumlah Lintasan</label>
                        <input type="number" name="lane_count" value="<?= val($row, 'lane_count', 8) ?>" class="w-full px-4 py-3 rounded-xl border border-indigo-200 font-bold text-indigo-900">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-indigo-400 uppercase mb-2 block">Hitung Umur Per</label>
                        <select name="age_calculation_type" class="w-full px-4 py-3 rounded-xl border border-indigo-200 font-bold text-slate-700">
                            <?php $ac = val($row, 'age_calculation_type', 'Dec 31'); ?>
                            <option value="Dec 31" <?= $ac == 'Dec 31' ? 'selected' : '' ?>>31 Desember</option>
                            <option value="Meet Start" <?= $ac == 'Meet Start' ? 'selected' : '' ?>>Hari H Lomba</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-indigo-400 uppercase mb-2 block">Tipe Kolam</label>
                        <select name="pool_type" class="w-full px-4 py-3 rounded-xl border border-indigo-200 font-bold text-slate-700">
                            <?php $pt = val($row, 'pool_type', '50m'); ?>
                            <option value="50m" <?= $pt == '50m' ? 'selected' : '' ?>>50 Meter (Olimpik)</option>
                            <option value="25m" <?= $pt == '25m' ? 'selected' : '' ?>>25 Meter (Short Course)</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-indigo-400 uppercase mb-2 block">Partisipasi</label>
                        <select name="participation_type" class="w-full px-4 py-3 rounded-xl border border-indigo-200 font-bold text-slate-700">
                            <?php $pp = val($row, 'participation_type', 'club'); ?>
                            <option value="club" <?= $pp == 'club' ? 'selected' : '' ?>>Antar Club</option>
                            <option value="school" <?= $pp == 'school' ? 'selected' : '' ?>>Antar Sekolah</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 p-8">
                <h3 class="font-black text-slate-800 uppercase italic text-xs tracking-widest mb-6 border-b pb-2">Rekening Pembayaran</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="label-text">Nama Bank</label>
                        <input type="text" name="bank_name" value="<?= val($row, 'bank_name') ?>" class="input-field" placeholder="BCA">
                    </div>
                    <div>
                        <label class="label-text">No Rekening</label>
                        <input type="text" name="bank_account_number" value="<?= val($row, 'bank_account_number') ?>" class="input-field" placeholder="123xxx">
                    </div>
                    <div>
                        <label class="label-text">Atas Nama</label>
                        <input type="text" name="bank_account_name" value="<?= val($row, 'bank_account_name') ?>" class="input-field" placeholder="Panitia">
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-6"> 
            
            <div class="bg-slate-900 rounded-[2rem] shadow-xl p-6 relative overflow-hidden text-white"> 
                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500 rounded-full mix-blend-overlay filter blur-3xl opacity-20 -translate-y-1/2 translate-x-1/2"></div>
                
                <h3 class="font-black uppercase italic text-xs tracking-widest mb-6 text-slate-400 relative z-10">Status Event</h3>
                <div class="space-y-3 relative z-10">
                    <?php 
                    $statuses = [
                        'upcoming' => ['Draft / Upcoming', 'border-slate-600', 'text-slate-400'],
                        'open'     => ['Open Registration', 'border-blue-500', 'text-blue-400'],
                        'closed'   => ['Closed (Running)', 'border-emerald-500', 'text-emerald-400'],
                        'done'     => ['Finished', 'border-red-500', 'text-red-400']
                    ];
                    $curStat = val($row, 'event_status', 'upcoming');
                    
                    foreach($statuses as $key => $val):
                        $active = ($curStat == $key);
                    ?>
                    <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer hover:bg-slate-800 transition <?= $active ? $val[1].' bg-slate-800 ring-1 ring-offset-0 ring-'.$val[1] : 'border-slate-700' ?>">
                        <input type="radio" name="status" value="<?= $key ?>" <?= $active ? 'checked' : '' ?> class="accent-blue-500 w-4 h-4 bg-slate-700 border-slate-500">
                        <span class="text-xs font-bold uppercase <?= $active ? 'text-white' : 'text-slate-400' ?>"><?= $val[0] ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                
                <hr class="my-6 border-slate-700 relative z-10">
                
                <button type="submit" class="relative z-10 w-full bg-blue-600 hover:bg-blue-500 text-white font-black py-4 rounded-xl uppercase tracking-widest text-xs shadow-lg shadow-blue-900/50 transition transform hover:-translate-y-1">
                    Simpan Perubahan
                </button>
            </div>

            <div class="bg-white rounded-[2rem] shadow-lg p-6 border border-slate-200">
                <h3 class="font-black text-slate-800 uppercase italic text-xs tracking-widest mb-4">Logo & Branding</h3>
                
                <div class="mb-4">
                    <p class="text-[10px] font-bold text-slate-400 uppercase mb-2">Logo Kiri (Utama)</p>
                    <div class="flex items-center gap-3">
                        <?php if(!empty($row['logo_left'])): ?>
                            <img src="../../../public/<?= $row['logo_left'] ?>" class="h-12 w-12 object-contain bg-slate-50 rounded-lg border">
                        <?php endif; ?>
                        <input type="file" name="logo_left" class="block w-full text-[10px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                </div>

                <div class="mb-4">
                    <p class="text-[10px] font-bold text-slate-400 uppercase mb-2">Logo Kanan</p>
                    <div class="flex items-center gap-3">
                        <?php if(!empty($row['logo_right'])): ?>
                            <img src="../../../public/<?= $row['logo_right'] ?>" class="h-12 w-12 object-contain bg-slate-50 rounded-lg border">
                        <?php endif; ?>
                        <input type="file" name="logo_right" class="block w-full text-[10px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                </div>

                <hr class="my-4 border-slate-100">

                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase mb-2">Sponsor (Bisa Banyak)</p>
                    <input type="file" name="sponsor_files[]" multiple class="block w-full text-[10px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100 mb-3">
                    
                    <?php if(count($sponsors) > 0): ?>
                        <div class="grid grid-cols-3 gap-2">
                            <?php foreach($sponsors as $sp): ?>
                                <div class="relative group bg-slate-50 border rounded-md h-12 flex items-center justify-center overflow-hidden">
                                    <img src="../../../public/<?= $sp['image_path'] ?>" class="max-h-full max-w-full p-1 object-contain">
                                    <a href="?event_id=<?= $eventId ?>&del_sponsor=<?= $sp['id'] ?>" onclick="return confirm('Hapus?')" class="absolute inset-0 bg-red-500/80 text-white flex items-center justify-center text-xs font-bold opacity-0 group-hover:opacity-100 transition cursor-pointer">×</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </form>
</div>

<style>
    .label-text { display: block; font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.05em; }
    .input-field { width: 100%; padding: 10px 15px; border-radius: 12px; border: 1px solid #e2e8f0; font-weight: 700; color: #334155; font-size: 14px; transition: all 0.2s; }
    .input-field:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php if(isset($_SESSION['swal_type'])): ?>
        Swal.fire({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
            icon: '<?= $_SESSION['swal_type'] ?>', title: '<?= $_SESSION['swal_msg'] ?>'
        });
        <?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); ?>
    <?php endif; ?>
</script>