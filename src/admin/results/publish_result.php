<?php
// FILE: src/admin/results/publish_result.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// 1. CEK OTORITAS
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}
$uid = $_SESSION['user_id'];

// --- 🚀 HANDLER AJAX UNTUK SAKLAR (TOGGLE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Toggle per Nomor Acara
    if ($_POST['action'] === 'toggle_publish') {
        $numId = $_POST['event_number_id'] ?? 0;
        $status = $_POST['is_published'] ?? 0;
        try {
            $stmt = $pdo->prepare("UPDATE event_numbers SET is_published = ? WHERE id = ?");
            $stmt->execute([$status, $numId]);
            echo json_encode(['success' => true, 'message' => 'Status berhasil diubah!']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit; 
    }
    
    // 🆕 Toggle Global Event (is_result_published)
    if ($_POST['action'] === 'toggle_event_publish') {
        $evId = $_POST['event_id'] ?? 0;
        $status = $_POST['is_result_published'] ?? 0;
        try {
            $stmt = $pdo->prepare("UPDATE events SET is_result_published = ? WHERE id = ?");
            $stmt->execute([$status, $evId]);
            echo json_encode(['success' => true, 'message' => 'Status publikasi global berhasil diubah!']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
}
// ----------------------------------------------

// --- 🚀 HANDLER UPLOAD DOKUMEN (BUKU ACARA / HASIL) ---
$uploadMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc'])) {
    $evId = $_POST['event_id'];
    $kategori = $_POST['kategori'];
    $judul_file = $_POST['judul_file'];

    if (isset($_FILES['dokumen'])) {
        if ($_FILES['dokumen']['error'] === UPLOAD_ERR_INI_SIZE) {
            $uploadMsg = "<div class='mb-6 p-4 bg-red-100 text-red-800 rounded-xl font-bold text-sm border border-red-200'>❌ Ukuran file PDF terlalu besar untuk server Hostinger!</div>";
        } elseif ($_FILES['dokumen']['error'] === UPLOAD_ERR_OK) {
            
            // Menggunakan absolute path yang presisi naik 3 tingkat ke root
            $uploadDir = __DIR__ . '/../../../public/uploads/documents/';
            
            // Buat folder secara paksa jika belum terbentuk
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if (!is_writable($uploadDir)) @chmod($uploadDir, 0755);

            $ext = pathinfo($_FILES['dokumen']['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $uploadDir . $filename;
            
            // Jalur url yang disimpan ke database untuk kebutuhan tag <a href="..."> (TANPA PUBLIC dan SLASH DI DEPAN)
            $db_path = 'uploads/documents/' . $filename; 

            if (move_uploaded_file($_FILES['dokumen']['tmp_name'], $dest)) {
                chmod($dest, 0644); // Amankan file
                
                // Hapus dokumen lama untuk kategori yang sama agar tidak menumpuk
                $stmtDel = $pdo->prepare("DELETE FROM documents WHERE event_id = ? AND kategori = ?");
                $stmtDel->execute([$evId, $kategori]);

                // Simpan dokumen baru
                $stmtIns = $pdo->prepare("INSERT INTO documents (user_id, event_id, judul_file, file_path, kategori) VALUES (?, ?, ?, ?, ?)");
                $stmtIns->execute([$uid, $evId, $judul_file, $db_path, $kategori]);
                
                $uploadMsg = "<div class='mb-6 p-4 bg-emerald-100 text-emerald-800 rounded-xl font-bold text-sm border border-emerald-200'>✅ Dokumen berhasil diunggah!</div>";
            } else {
                die("Gagal memindahkan file ke target fisik: " . $dest);
            }
        }
    }
}
// ----------------------------------------------

// 2. Ambil Daftar Event milik Admin
$stmtEvents = $pdo->prepare("SELECT id, event_name FROM events WHERE user_id = ? ORDER BY event_date_start DESC");
$stmtEvents->execute([$uid]);
$myEvents = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);

$eventId = $_GET['event_id'] ?? ($_POST['event_id'] ?? 0);
if ($eventId == 0 && count($myEvents) > 0) { $eventId = $myEvents[0]['id']; }

// 🆕 Ambil data detail event aktif saat ini untuk mengecek status `is_result_published`
$currentEvent = null;
if ($eventId > 0) {
    $stmtCurr = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmtCurr->execute([$eventId]);
    $currentEvent = $stmtCurr->fetch(PDO::FETCH_ASSOC);
}

// 3. Ambil Daftar Nomor Perlombaan
$raceList = [];
if ($eventId > 0) {
    $sql = "SELECT en.*, 
            (SELECT COUNT(*) FROM event_entries ee 
             JOIN event_seeding es ON ee.id = es.entry_id 
             WHERE ee.category_id = en.id 
             AND (es.time_final IS NOT NULL OR es.is_dq_final = 1)) as count_results
            FROM event_numbers en 
            WHERE en.event_id = ? ORDER BY CAST(en.event_number AS UNSIGNED) ASC";
    $stmtRace = $pdo->prepare($sql);
    $stmtRace->execute([$eventId]);
    $raceList = $stmtRace->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    <div class="max-w-5xl mx-auto">
        <?= $uploadMsg ?>
        
        <div class="mb-8 flex flex-col lg:flex-row lg:items-end justify-between gap-6 border-b border-slate-200 pb-6">
            <div>
                <h1 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter">Publikasi Live Result</h1>
                <p class="text-sm text-slate-500 font-bold mt-1">Kelola dokumen dan Live Result untuk pengguna.</p>
            </div>
            
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 w-full lg:w-auto">
                <div class="w-full sm:w-64">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Pilih Event Aktif:</label>
                    <select id="eventSelector" onchange="window.location.href='publish_result.php?event_id='+this.value" class="w-full bg-white border border-slate-300 text-slate-700 text-xs font-bold rounded-xl px-4 py-2.5 shadow-sm cursor-pointer transition focus:border-blue-500 focus:outline-none">
                        <?php foreach($myEvents as $ev): ?>
                            <option value="<?= $ev['id'] ?>" <?= ($ev['id'] == $eventId) ? 'selected' : '' ?>><?= htmlspecialchars($ev['event_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($eventId > 0): ?>
                <div class="bg-white border border-slate-200 rounded-xl px-4 py-2 shadow-sm flex items-center justify-between gap-4 w-full sm:w-auto h-[42px] mt-auto">
                    <span class="text-[10px] font-black text-slate-500 uppercase tracking-wider">Publikasikan Hasil?</span>
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer" onchange="toggleEventPublish(this, <?= $eventId ?>)" <?= ($currentEvent && $currentEvent['is_result_published'] == 1) ? 'checked' : '' ?>>
                        <div class="relative w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500 shadow-inner"></div>
                    </label>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if($eventId > 0): ?>
        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200 mb-8">
            <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-4">📥 Unggah Dokumen Perlombaan</h2>
            <form action="" method="POST" enctype="multipart/form-data" class="flex flex-col md:flex-row items-end gap-4">
                <input type="hidden" name="event_id" value="<?= $eventId ?>">
                <input type="hidden" name="upload_doc" value="1">
                
                <div class="w-full md:w-1/4">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Kategori</label>
                    <select name="kategori" required class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs font-bold rounded-xl px-4 py-3">
                        <option value="buku_acara">Buku Acara (Startlist)</option>
                        <option value="buku_hasil">Buku Hasil (Result)</option>
                    </select>
                </div>
                
                <div class="w-full md:w-1/3">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Judul Tampilan</label>
                    <input type="text" name="judul_file" placeholder="Cth: Startlist Hari 1" required class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs font-bold rounded-xl px-4 py-3">
                </div>

                <div class="w-full md:w-1/3">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">File (PDF)</label>
                    <input type="file" name="dokumen" accept=".pdf" required class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-xl px-4 py-2.5 file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200">
                </div>

                <div class="w-full md:w-auto">
                    <button type="submit" class="w-full bg-slate-900 text-white px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-blue-600 transition shadow-lg">Upload</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if(empty($raceList)): ?>
            <div class="bg-white p-12 text-center rounded-[2rem] shadow-sm border border-slate-200 border-dashed">
                <span class="text-5xl block mb-4 opacity-50">📋</span>
                <h3 class="text-lg font-black text-slate-700 uppercase italic mb-2">Belum Ada Nomor Acara</h3>
                <p class="text-sm text-slate-500 font-medium">Buat nomor lomba terlebih dahulu di menu "Pengaturan Event".</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">
                            <th class="px-6 py-4 w-24 text-center">No.</th>
                            <th class="px-6 py-4">Nomor Lomba</th>
                            <th class="px-6 py-4 text-center w-32">Status Data</th>
                            <th class="px-6 py-4 text-right w-40">Live Result?</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($raceList as $race): 
                            $hasResult = ($race['count_results'] > 0);
                            $isPublished = ($race['is_published'] == 1);
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 text-center font-black text-slate-400 text-lg">#<?= htmlspecialchars($race['event_number']) ?></td>
                            <td class="px-6 py-4">
                                <div class="font-black text-slate-800 uppercase text-sm leading-tight mb-1">
                                    <?= htmlspecialchars($race['distance']) ?>M <?= htmlspecialchars($race['stroke']) ?> 
                                </div>
                                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                    <?= htmlspecialchars($race['age_group'] ?? 'OPEN') ?> • <?= strtoupper($race['jenis_kelamin'] ?? '') === 'L' ? 'PUTRA' : 'PUTRI' ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if($hasResult): ?>
                                    <span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-[9px] font-black uppercase tracking-widest">✅ Siap</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 bg-amber-100 text-amber-700 rounded text-[9px] font-black uppercase tracking-widest">⏳ Kosong</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <label class="inline-flex items-center cursor-pointer <?= !$hasResult ? 'opacity-50' : '' ?>" <?= !$hasResult ? 'title="Input hasil terlebih dahulu!"' : '' ?>>
                                    <input type="checkbox" class="sr-only peer" onchange="togglePublish(this, <?= $race['id'] ?>)" <?= $isPublished ? 'checked' : '' ?> <?= !$hasResult ? 'disabled' : '' ?>>
                                    <div class="relative w-14 h-7 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-blue-600 shadow-inner"></div>
                                </label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// 🆕 JAVASCRIPT: Fungsi AJAX untuk switch global event
function toggleEventPublish(checkboxElem, eventId) {
    const isChecked = checkboxElem.checked ? 1 : 0;
    const formData = new FormData();
    formData.append('action', 'toggle_event_publish');
    formData.append('event_id', eventId);
    formData.append('is_result_published', isChecked);

    fetch('publish_result.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            Swal.fire({ 
                toast: true, 
                position: 'bottom-end', 
                showConfirmButton: false, 
                timer: 2000, 
                icon: 'success', 
                title: isChecked ? 'Hasil Event Dipublikasikan!' : 'Hasil Event Disembunyikan.' 
            });
        } else {
            checkboxElem.checked = !isChecked; 
            alert("Gagal: " + data.message);
        }
    })
    .catch(error => { 
        checkboxElem.checked = !isChecked; 
        alert("Terjadi kesalahan jaringan."); 
    });
}

// Fungsi AJAX untuk per nomor lomba (bawaan lama)
function togglePublish(checkboxElem, raceId) {
    const isChecked = checkboxElem.checked ? 1 : 0;
    const formData = new FormData();
    formData.append('action', 'toggle_publish');
    formData.append('event_number_id', raceId);
    formData.append('is_published', isChecked);

    fetch('publish_result.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            Swal.fire({ toast: true, position: 'bottom-end', showConfirmButton: false, timer: 2000, icon: 'success', title: isChecked ? 'Diterbitkan!' : 'Ditarik.' });
        } else {
            checkboxElem.checked = !isChecked; alert("Gagal: " + data.message);
        }
    })
    .catch(error => { checkboxElem.checked = !isChecked; alert("Terjadi kesalahan jaringan."); });
}
</script>