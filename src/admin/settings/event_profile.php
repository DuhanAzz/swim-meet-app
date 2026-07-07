<?php
// FILE: src/admin/settings/event_profile.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// CEK OTORITAS
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}
$uid = $_SESSION['user_id'];

// --- 🚀 AUTO-UPDATE DATABASE (HANYA UNTUK DEVELOPMENT) ---
try {
    $stmtCekPoster = $pdo->query("SHOW COLUMNS FROM events LIKE 'poster_image'");
    if ($stmtCekPoster->rowCount() == 0) {
        $pdo->exec("ALTER TABLE events ADD COLUMN poster_image VARCHAR(255) NULL AFTER event_location");
    }
    
    $stmtCekPublish = $pdo->query("SHOW COLUMNS FROM event_numbers LIKE 'is_published'");
    if ($stmtCekPublish->rowCount() == 0) {
        $pdo->exec("ALTER TABLE event_numbers ADD COLUMN is_published TINYINT(1) DEFAULT 0 AFTER event_id");
    }

    $pdo->exec("ALTER TABLE documents MODIFY COLUMN kategori ENUM('buku_acara','buku_hasil','lainnya','JUKNIS','FORMULIR')");
} catch (PDOException $e) {
    error_log("Gagal auto-update DB: " . $e->getMessage()); 
}


// --- 1. LOGIKA MENCARI EVENT ---
$eventId = $_GET['event_id'] ?? 0;

if ($eventId == 0) {
    $stmtFind = $pdo->prepare("SELECT id FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtFind->execute([$uid]);
    $lastEvent = $stmtFind->fetch();
    if ($lastEvent) $eventId = $lastEvent['id'];
}

// Menggunakan absolute path yang presisi naik 3 tingkat ke root
$targetDir = __DIR__ . "/../../../public/uploads/logos/";
$posterDir = __DIR__ . "/../../../public/uploads/posters/";
$docDir    = __DIR__ . "/../../../public/uploads/documents/";

// --- 2. FITUR HAPUS SPONSOR ---
if (isset($_GET['del_sponsor']) && $eventId > 0) {
    $sponsorId = $_GET['del_sponsor'];
    $stmt = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE id = ? AND event_id = ?");
    $stmt->execute([$sponsorId, $eventId]);
    $img = $stmt->fetch();
    
    if ($img) {
        // Bersihkan path untuk menghapus file fisik
        $cleanPath = ltrim(preg_replace('/^(\.\.\/)+/', '', $img['image_path']), '/');
        if (strpos($cleanPath, 'swim-meet/') === 0) $cleanPath = substr($cleanPath, 10);
        $fullPath = __DIR__ . "/../../../" . $cleanPath;
        
        if (file_exists($fullPath)) unlink($fullPath); 
        $pdo->prepare("DELETE FROM event_sponsors WHERE id = ?")->execute([$sponsorId]);
        $_SESSION['swal_type'] = "success";
        $_SESSION['swal_msg']  = "Logo sponsor berhasil dihapus";
    }
    header("Location: event_profile.php?event_id=" . $eventId); exit;
}

// --- 2B. FITUR HAPUS GAMBAR UTAMA (Logo/Poster) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete_image' && isset($_GET['type']) && $eventId > 0) {
    $type = $_GET['type'];
    $allowedTypes = ['logo_left', 'logo_right', 'poster_image'];
    
    if (in_array($type, $allowedTypes)) {
        // Fetch current image path
        $stmt = $pdo->prepare("SELECT `$type` FROM events WHERE id = ? AND user_id = ?");
        $stmt->execute([$eventId, $uid]);
        $rowImg = $stmt->fetch();
        
        if ($rowImg && !empty($rowImg[$type])) {
            $dbPath = $rowImg[$type];
            $cleanPath = ltrim(preg_replace('/^(\.\.\/)+/', '', $dbPath), '/');
            if (strpos($cleanPath, 'swim-meet/') === 0) $cleanPath = substr($cleanPath, 10);
            $fullPath = __DIR__ . "/../../../public/" . $cleanPath;
            
            if (file_exists($fullPath)) unlink($fullPath);
            
            $pdo->prepare("UPDATE events SET `$type` = NULL WHERE id = ? AND user_id = ?")->execute([$eventId, $uid]);
            
            $_SESSION['swal_type'] = "success";
            $namaLabel = strtoupper(str_replace('_', ' ', $type));
            $_SESSION['swal_msg']  = "Gambar $namaLabel berhasil dihapus";
        }
    }
    header("Location: event_profile.php?event_id=" . $eventId); exit;
}

// --- 3. HANDLE SIMPAN DATA (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // --- DEBUGGING SEMENTARA ---
    $filesToCheck = ['logo_left', 'logo_right', 'poster_file', 'juknis_file', 'form_file'];
    foreach ($filesToCheck as $fk) {
        if (isset($_FILES[$fk]) && $_FILES[$fk]['error'] !== UPLOAD_ERR_OK && $_FILES[$fk]['error'] !== UPLOAD_ERR_NO_FILE) {
            die("Error Code PHP Upload ($fk): " . $_FILES[$fk]['error']);
        }
    }
    if (isset($_FILES['sponsor_files']['error'][0]) && $_FILES['sponsor_files']['error'][0] !== UPLOAD_ERR_OK && $_FILES['sponsor_files']['error'][0] !== UPLOAD_ERR_NO_FILE) {
        die("Error Code PHP Upload (sponsor_files): " . $_FILES['sponsor_files']['error'][0]);
    }
    // ---------------------------

    try {
        $pdo->beginTransaction();
        
        // Buat folder secara paksa jika belum terbentuk (0755 untuk keamanan shared hosting)
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        if (!is_writable($targetDir)) { @chmod($targetDir, 0755); if(!is_writable($targetDir)) die("Error: Direktori logos tidak writeable."); }
        
        if (!is_dir($posterDir)) mkdir($posterDir, 0755, true);
        if (!is_writable($posterDir)) { @chmod($posterDir, 0755); if(!is_writable($posterDir)) die("Error: Direktori posters tidak writeable."); }
        
        if (!is_dir($docDir)) mkdir($docDir, 0755, true);
        if (!is_writable($docDir)) { @chmod($docDir, 0755); if(!is_writable($docDir)) die("Error: Direktori documents tidak writeable."); }

        // MAPPING INPUT
        $eventName   = $_POST['nama_event'] ?? '';
        $eventLoc    = $_POST['lokasi'] ?? '';
        $eventCity   = $_POST['kota'] ?? '';
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

        $recordPackageId = !empty($_POST['record_package_id']) ? (int)$_POST['record_package_id'] : NULL;

        if ($eventId == 0) {
            $sql = "INSERT INTO events (
                        user_id, event_name, event_location, event_city, event_date_start, event_date_end, 
                        lane_count, pool_type, age_calculation_type, participation_type, event_status,
                        bank_name, bank_account_number, bank_account_name, record_package_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; 
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$uid, $eventName, $eventLoc, $eventCity, $dateStart, $dateEnd, $laneCount, $poolType, $ageCalc, $partType, $status, $bankName, $bankRek, $bankAtas, $recordPackageId]);
            $eventId = $pdo->lastInsertId(); 
        } else {
            $sql = "UPDATE events SET 
                    event_name = ?, event_location = ?, event_city = ?, event_date_start = ?, event_date_end = ?, 
                    lane_count = ?, pool_type = ?, age_calculation_type = ?, participation_type = ?, event_status = ?,
                    bank_name = ?, bank_account_number = ?, bank_account_name = ?, record_package_id = ?
                    WHERE user_id = ? AND id = ?"; 
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$eventName, $eventLoc, $eventCity, $dateStart, $dateEnd, $laneCount, $poolType, $ageCalc, $partType, $status, $bankName, $bankRek, $bankAtas, $recordPackageId, $uid, $eventId]);
        }

        // --- HANDLE UPLOAD LOGO & BRANDING ---
        if (!empty($_FILES['logo_left']['name'])) {
            $ext = strtolower(pathinfo($_FILES['logo_left']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $fn = "LOGO_L_" . $eventId . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                if(compressImage($_FILES['logo_left']['tmp_name'], $targetDir . $fn, 80, 2)) {
                    $pdo->prepare("UPDATE events SET logo_left = ? WHERE id = ?")->execute(["uploads/logos/" . $fn, $eventId]);
                }
            } else {
                throw new Exception("Ekstensi logo kiri tidak valid. Gunakan JPG/PNG.");
            }
        }
        if (!empty($_FILES['logo_right']['name'])) {
            $ext = strtolower(pathinfo($_FILES['logo_right']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $fn = "LOGO_R_" . $eventId . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                if(compressImage($_FILES['logo_right']['tmp_name'], $targetDir . $fn, 80, 2)) {
                    $pdo->prepare("UPDATE events SET logo_right = ? WHERE id = ?")->execute(["uploads/logos/" . $fn, $eventId]);
                }
            } else {
                throw new Exception("Ekstensi logo kanan tidak valid. Gunakan JPG/PNG.");
            }
        }

        // --- HANDLE SPONSORS ---
        if (!empty($_FILES['sponsor_files']['name'][0])) {
            $totalFiles = count($_FILES['sponsor_files']['name']);
            $stmtSponsor = $pdo->prepare("INSERT INTO event_sponsors (event_id, image_path) VALUES (?, ?)");
            for($i=0; $i<$totalFiles; $i++) {
                if ($_FILES['sponsor_files']['tmp_name'][$i] != "") {
                    $ext = strtolower(pathinfo($_FILES['sponsor_files']['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                        $newFileName = "SPONSOR_" . $eventId . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                        if(compressImage($_FILES['sponsor_files']['tmp_name'][$i], $targetDir . $newFileName, 80, 2)) {
                            $stmtSponsor->execute([$eventId, "uploads/logos/" . $newFileName]);
                        }
                    }
                }
            }
        }

        // --- 🚀 HANDLE POSTER (Fisik + URL Akurat) ---
        if (!empty($_FILES['poster_file']['name'])) {
            $ext = strtolower(pathinfo($_FILES['poster_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $fn = "POSTER_" . $eventId . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                if(compressImage($_FILES['poster_file']['tmp_name'], $posterDir . $fn, 80, 2)) {
                    $pdo->prepare("UPDATE events SET poster_image = ? WHERE id = ?")->execute(["uploads/posters/" . $fn, $eventId]);
                }
            } else {
                throw new Exception("Ekstensi poster tidak valid. Gunakan JPG/PNG.");
            }
        }

        // Fungsi Bantuan untuk Upload Juknis & Form ke tabel `documents`
        function handleDocUpload($fileInput, $kategori, $judulPrefix, $eventId, $uid, $eventName, $pdo, $docDir) {
            if (!empty($_FILES[$fileInput]['name'])) {
                // Validasi ukuran max 5MB
                if ($_FILES[$fileInput]['size'] > 5 * 1024 * 1024) {
                    throw new Exception("File dokumen terlalu besar. Maksimal 5MB.");
                }
                $ext = strtolower(pathinfo($_FILES[$fileInput]['name'], PATHINFO_EXTENSION));
                $allowedDocExts = ['pdf', 'xls', 'xlsx', 'doc', 'docx'];
                if (!in_array($ext, $allowedDocExts)) {
                    throw new Exception("Ekstensi dokumen tidak valid.");
                }

                $fn = $kategori . "_" . $eventId . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                $dest = $docDir . $fn;
                
                if(move_uploaded_file($_FILES[$fileInput]['tmp_name'], $dest)) {
                    chmod($dest, 0644); // Amankan file
                    $filePath = "uploads/documents/" . $fn;
                    $judulFile = $judulPrefix . " " . $eventName;
                    
                    $stmtCek = $pdo->prepare("SELECT id FROM documents WHERE event_id = ? AND kategori = ?");
                    $stmtCek->execute([$eventId, $kategori]);
                    $exist = $stmtCek->fetch();

                    if ($exist) {
                        $pdo->prepare("UPDATE documents SET file_path = ?, judul_file = ?, created_at = NOW() WHERE id = ?")->execute([$filePath, $judulFile, $exist['id']]);
                    } else {
                        $pdo->prepare("INSERT INTO documents (user_id, event_id, judul_file, file_path, kategori) VALUES (?, ?, ?, ?, ?)")->execute([$uid, $eventId, $judulFile, $filePath, $kategori]);
                    }
                } else {
                    die("Gagal memindahkan file dokumen ke target fisik: " . $dest);
                }
            }
        }

        // 2. Upload Juknis (PDF)
        handleDocUpload('juknis_file', 'JUKNIS', 'Buku Panduan', $eventId, $uid, $eventName, $pdo, $docDir);
        // 3. Upload Form A3 (Excel)
        handleDocUpload('form_file', 'FORMULIR', 'Formulir Pendaftaran', $eventId, $uid, $eventName, $pdo, $docDir);


        $pdo->commit();
        $_SESSION['swal_type'] = "success";
        $_SESSION['swal_msg']  = "Perubahan Berhasil Disimpan!";
        header("Location: event_profile.php?event_id=" . $eventId); exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['swal_type'] = "error";
        $_SESSION['swal_msg']  = "Gagal: " . $e->getMessage();
        header("Location: event_profile.php?event_id=" . $eventId); exit;
    }
}

// --- 4. AMBIL DATA ---
$row = []; 
$sponsors = [];
$docJuknis = null;
$docForm = null;

if ($eventId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND user_id = ?");
    $stmt->execute([$eventId, $uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmtS = $pdo->prepare("SELECT * FROM event_sponsors WHERE event_id = ? ORDER BY id DESC");
    $stmtS->execute([$eventId]);
    $sponsors = $stmtS->fetchAll(PDO::FETCH_ASSOC);

    $stmtDoc = $pdo->prepare("SELECT * FROM documents WHERE event_id = ?");
    $stmtDoc->execute([$eventId]);
    $docs = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);
    foreach($docs as $d) {
        if($d['kategori'] === 'JUKNIS') $docJuknis = $d;
        if($d['kategori'] === 'FORMULIR') $docForm = $d;
    }
}

$allPackages = $pdo->query("SELECT * FROM record_packages ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

function val($data, $key, $default = '') { return isset($data[$key]) ? htmlspecialchars($data[$key]) : $default; }

// --- FUNGSI BANTUAN URL PREVIEW ---
function getUrlPreview($dbPath) {
    if (empty($dbPath)) return '';
    if (strpos($dbPath, 'http') === 0) return $dbPath;
    $cleanPath = ltrim(preg_replace('/^(\.\.\/)+/', '', $dbPath), '/');
    if (strpos($cleanPath, 'swim-meet/') === 0) $cleanPath = substr($cleanPath, 10);
    return '../../../public/' . $cleanPath;
}

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
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="label-text">Lokasi (Nama Kolam)</label>
                            <input type="text" name="lokasi" value="<?= val($row, 'event_location') ?>" class="input-field" placeholder="Contoh: Stadion Akuatik GBK">
                        </div>
                        <div>
                            <label class="label-text">Kabupaten / Kota</label>
                            <input type="text" name="kota" value="<?= val($row, 'event_city') ?>" class="input-field" placeholder="Contoh: Jakarta Pusat">
                        </div>
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
                    <div class="md:col-span-2">
                        <label class="text-[10px] font-bold text-indigo-400 uppercase mb-2 block">Acuan Rekor Event (Pecah Rekor)</label>
                        <select name="record_package_id" class="w-full px-4 py-3 rounded-xl border border-indigo-200 font-bold text-slate-700">
                            <option value="">-- Tidak Menggunakan Rekor Event Tambahan --</option>
                            <?php foreach($allPackages as $pkg): ?>
                                <option value="<?= $pkg['id'] ?>" <?= (val($row, 'record_package_id') == $pkg['id']) ? 'selected' : '' ?>>
                                    Paket: <?= htmlspecialchars($pkg['package_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[10px] text-indigo-500 mt-1 italic">Paket ini dikelola oleh Master Admin dan berfungsi sebagai baseline rekor lomba selain Rekor Nasional.</p>
                    </div>
                </div>
            </div>

            <div class="bg-amber-50 rounded-[2rem] shadow-sm border border-amber-200 p-8">
                <h3 class="font-black text-amber-900 uppercase italic text-xs tracking-widest mb-6 border-b border-amber-200 pb-2">📂 Kelengkapan Dokumen Publikasi</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div class="col-span-1 md:col-span-2 border border-dashed border-amber-300 bg-white p-5 rounded-2xl">
                        <label class="label-text text-amber-700">1. Poster Event (JPG/PNG)</label>
                        <p class="text-[9px] text-amber-600 mb-3 font-medium">Poster yang akan tampil di halaman utama / explore lomba.</p>
                        <div class="flex items-center gap-4">
                            <?php if(!empty($row['poster_image'])): ?>
                                <div class="flex flex-col items-center gap-2">
                                    <a href="<?= getUrlPreview($row['poster_image']) ?>" target="_blank" class="shrink-0 h-16 w-16 bg-slate-100 rounded-xl border border-slate-200 overflow-hidden flex items-center justify-center">
                                        <img src="<?= getUrlPreview($row['poster_image']) ?>" class="max-h-full max-w-full object-cover">
                                    </a>
                                    <a href="?event_id=<?= $eventId ?>&action=delete_image&type=poster_image" onclick="return confirm('Apakah Anda yakin ingin menghapus gambar ini?');" class="bg-red-600 hover:bg-red-700 text-white text-[9px] px-2 py-1 rounded-md font-bold text-center w-full block">Hapus</a>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="poster_file" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200 transition">
                        </div>
                    </div>

                    <div class="border border-dashed border-amber-300 bg-white p-5 rounded-2xl">
                        <label class="label-text text-amber-700">2. Buku Panduan / Juknis (PDF)</label>
                        <p class="text-[9px] text-amber-600 mb-3 font-medium">Buku panduan teknis untuk dibaca oleh klub pendaftar.</p>
                        
                        <?php if($docJuknis): ?>
                            <div class="mb-3 flex items-center justify-between bg-green-50 px-3 py-2 rounded-lg border border-green-200">
                                <span class="text-[10px] font-bold text-green-700">✅ Terunggah</span>
                                <a href="<?= getUrlPreview($docJuknis['file_path']) ?>" target="_blank" class="text-[10px] font-black text-blue-600 hover:underline">Lihat File</a>
                            </div>
                        <?php endif; ?>

                        <input type="file" name="juknis_file" accept=".pdf" class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 transition cursor-pointer">
                        <span class="text-[8px] text-slate-400 mt-1 block italic">*Upload ulang untuk menimpa Juknis lama.</span>
                    </div>

                    <div class="border border-dashed border-amber-300 bg-white p-5 rounded-2xl">
                        <label class="label-text text-amber-700">3. Form Pendaftaran (Opsional)</label>
                        <p class="text-[9px] text-amber-600 mb-3 font-medium">Formulir pendaftaran manual format Excel / Spreadsheet.</p>
                        
                        <?php if($docForm): ?>
                            <div class="mb-3 flex items-center justify-between bg-green-50 px-3 py-2 rounded-lg border border-green-200">
                                <span class="text-[10px] font-bold text-green-700">✅ Terunggah</span>
                                <a href="<?= getUrlPreview($docForm['file_path']) ?>" target="_blank" class="text-[10px] font-black text-blue-600 hover:underline">Lihat File</a>
                            </div>
                        <?php endif; ?>

                        <input type="file" name="form_file" accept=".xls,.xlsx" class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 transition cursor-pointer">
                        <span class="text-[8px] text-slate-400 mt-1 block italic">*Upload ulang untuk menimpa form lama.</span>
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
                            <div class="flex flex-col items-center gap-1">
                                <img src="<?= getUrlPreview($row['logo_left']) ?>" class="h-12 w-12 object-contain bg-slate-50 rounded-lg border">
                                <a href="?event_id=<?= $eventId ?>&action=delete_image&type=logo_left" onclick="return confirm('Apakah Anda yakin ingin menghapus gambar ini?');" class="bg-red-600 hover:bg-red-700 text-white text-[9px] px-2 py-0.5 rounded flex-shrink-0 font-bold block text-center w-full">Hapus</a>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="logo_left" class="block w-full text-[10px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                </div>

                <div class="mb-4">
                    <p class="text-[10px] font-bold text-slate-400 uppercase mb-2">Logo Kanan</p>
                    <div class="flex items-center gap-3">
                        <?php if(!empty($row['logo_right'])): ?>
                            <div class="flex flex-col items-center gap-1">
                                <img src="<?= getUrlPreview($row['logo_right']) ?>" class="h-12 w-12 object-contain bg-slate-50 rounded-lg border">
                                <a href="?event_id=<?= $eventId ?>&action=delete_image&type=logo_right" onclick="return confirm('Apakah Anda yakin ingin menghapus gambar ini?');" class="bg-red-600 hover:bg-red-700 text-white text-[9px] px-2 py-0.5 rounded flex-shrink-0 font-bold block text-center w-full">Hapus</a>
                            </div>
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
                                    <img src="<?= getUrlPreview($sp['image_path']) ?>" class="max-h-full max-w-full p-1 object-contain">
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