<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// --- 1. PROTEKSI AKSES (USER & ADMIN) ---
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'user' && $_SESSION['role'] !== 'admin')) {
    exit("Akses ditolak");
}

$eventId = $_GET['event_id'] ?? 0;
$swimmerId = $_GET['swimmer_id'] ?? 0;
$uid = $_SESSION['user_id']; // ID User yang sedang login
$isAjax = isset($_GET['ajax']); 
$isAdmin = ($_SESSION['role'] === 'admin');

// --- 2. AMBIL DATA ATLET ---
// Jika Admin, kita tidak mengecek user_id (karena admin lintas klub)
if ($isAdmin) {
    $stmtS = $pdo->prepare("SELECT * FROM swimmers WHERE id = ?");
    $stmtS->execute([$swimmerId]);
} else {
    $stmtS = $pdo->prepare("SELECT * FROM swimmers WHERE id = ? AND user_id = ?");
    $stmtS->execute([$swimmerId, $uid]);
}

$s = $stmtS->fetch();
if (!$s) exit("Atlet tidak ditemukan.");

// Simpan ID Klub asli pemilik atlet (penting untuk kolom club_id di tabel event_entries)
$actualClubId = $s['user_id'];

// --- 3. HANDLE SIMPAN (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Hapus pendaftaran lama untuk atlet ini di event ini
        $pdo->prepare("DELETE FROM event_entries WHERE event_id = ? AND swimmer_id = ?")->execute([$eventId, $swimmerId]);

        if (!empty($_POST['categories'])) {
            $ins = $pdo->prepare("INSERT INTO event_entries (event_id, club_id, swimmer_id, category_id, entry_time) VALUES (?, ?, ?, ?, ?)");
            foreach ($_POST['categories'] as $catId) {
                $timeInput = trim($_POST['times'][$catId] ?? '');
                // Jika waktu kosong, set default 99.99.99 (No Time)
                $finalTime = (empty($timeInput) || $timeInput == '-') ? '99.99.99' : $timeInput;
                
                // Gunakan $actualClubId agar data tetap di klub yang benar meskipun Admin yang input
                $ins->execute([$eventId, $actualClubId, $swimmerId, $catId, $finalTime]);
            }
        }
        
        $pdo->commit();
        if ($isAjax) { echo "OK_RELOAD"; exit; }
        
        // Redirect normal jika bukan AJAX
        $redirect = $isAdmin ? "../../admin/entries/view_matrix.php?club_id=$actualClubId" : "register_event.php?event_id=$eventId";
        header("Location: $redirect"); exit;
        
    } catch (Exception $e) { 
        $pdo->rollBack(); 
        echo "Error: " . $e->getMessage(); exit; 
    }
}

// --- 4. AMBIL DATA UNTUK FORM ---
// Ambil kategori acara
$stmtCat = $pdo->prepare("SELECT * FROM event_categories WHERE user_id = ? AND (gender = ? OR gender = 'Mixed') ORDER BY distance ASC, style ASC");
$stmtCat->execute([$eventId, $s['jenis_kelamin']]);
$categories = $stmtCat->fetchAll();

// Ambil PB (Personal Best)
$stmtPB = $pdo->prepare("SELECT nomor_lomba, waktu_terbaik FROM athlete_records WHERE swimmer_id = ?");
$stmtPB->execute([$swimmerId]);
$pbRaw = $stmtPB->fetchAll(PDO::FETCH_KEY_PAIR);

// Ambil pendaftaran yang sudah ada
$stmtEntry = $pdo->prepare("SELECT category_id, entry_time FROM event_entries WHERE event_id = ? AND swimmer_id = ?");
$stmtEntry->execute([$eventId, $swimmerId]);
$existingEntries = $stmtEntry->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="bg-white rounded-2xl overflow-hidden shadow-2xl border border-slate-200">
    <div class="bg-slate-900 p-5 text-white flex justify-between items-center">
        <div>
            <h1 class="text-base font-black uppercase tracking-tighter italic">Setting Nomor Lomba</h1>
            <p class="text-[10px] text-blue-400 font-bold uppercase tracking-widest"><?= htmlspecialchars($s['nama_atlet']) ?></p>
        </div>
        <?php if($isAjax): ?>
            <button type="button" onclick="closeModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-red-500 transition">&times;</button>
        <?php endif; ?>
    </div>

    <form id="formEditEntry" class="p-5 bg-slate-50/50">
        <div class="space-y-2 max-h-[55vh] overflow-y-auto pr-2 custom-scrollbar">
            <?php if(empty($categories)): ?>
                <div class="p-10 text-center text-slate-400 text-xs italic font-bold uppercase">Kategori tidak tersedia untuk gender ini.</div>
            <?php endif; ?>

            <?php foreach($categories as $c): 
                $catName = $c['distance'] . "m " . $c['style'];
                $pbValue = $pbRaw[$catName] ?? '99.99.99';
                $isRegistered = isset($existingEntries[$c['id']]);
                $currentTime = $isRegistered ? $existingEntries[$c['id']] : $pbValue;
            ?>
            <div class="flex items-center justify-between p-4 rounded-2xl border-2 transition-all duration-300 <?= $isRegistered ? 'border-blue-600 bg-white shadow-md' : 'border-slate-200 bg-white opacity-60' ?>">
                <div class="flex items-center gap-4">
                    <div class="relative flex items-center">
                        <input type="checkbox" name="categories[]" value="<?= $c['id'] ?>" 
                               class="w-6 h-6 rounded-lg border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer transition-transform active:scale-90"
                               <?= $isRegistered ? 'checked' : '' ?>
                               onchange="toggleRow(this, 'time-<?= $c['id'] ?>')">
                    </div>
                    <div>
                        <div class="font-black text-slate-800 text-[12px] uppercase leading-none mb-1"><?= $catName ?></div>
                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter"><?= $c['age_group'] ?></div>
                    </div>
                </div>
                
                <div class="flex flex-col items-end">
                    <label class="text-[8px] font-black text-slate-300 uppercase mb-1">Entry Time</label>
                    <input type="text" name="times[<?= $c['id'] ?>]" id="time-<?= $c['id'] ?>" 
                           value="<?= $currentTime ?>" 
                           placeholder="00.00.00"
                           class="w-24 px-3 py-2 rounded-xl border-2 border-slate-100 text-center font-mono text-xs font-black focus:border-blue-600 focus:ring-0 outline-none transition-all <?= $isRegistered ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-400' ?>"
                           <?= !$isRegistered ? 'readonly' : '' ?>>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-8 flex gap-3">
            <button type="button" onclick="closeModal()" class="flex-1 bg-white border-2 border-slate-200 text-slate-400 font-black py-4 rounded-2xl text-[10px] uppercase hover:bg-slate-100 transition">Batal</button>
            <button type="button" onclick="submitEntryForm()" id="btnSave" class="flex-[2] bg-blue-600 hover:bg-blue-700 text-white font-black py-4 px-8 rounded-2xl shadow-xl shadow-blue-200 transition transform active:scale-95 text-[10px] uppercase tracking-widest">Simpan Perubahan</button>
        </div>
    </form>
</div>

<script>
// Menangani tampilan baris saat checkbox diklik
function toggleRow(checkbox, inputId) {
    const input = document.getElementById(inputId);
    const container = checkbox.closest('.rounded-2xl');
    if(checkbox.checked) {
        input.removeAttribute('readonly');
        input.classList.remove('bg-slate-100', 'text-slate-400');
        input.classList.add('bg-blue-50', 'text-blue-700');
        container.classList.remove('opacity-60', 'border-slate-200');
        container.classList.add('border-blue-600', 'shadow-md');
        input.focus();
    } else {
        input.setAttribute('readonly', 'true');
        input.classList.add('bg-slate-100', 'text-slate-400');
        input.classList.remove('bg-blue-50', 'text-blue-700');
        container.classList.add('opacity-60', 'border-slate-200');
        container.classList.remove('border-blue-600', 'shadow-md');
    }
}

// Kirim data via AJAX
function submitEntryForm() {
    const btn = document.getElementById('btnSave');
    const form = document.getElementById('formEditEntry');
    const formData = new FormData(form);
    
    btn.disabled = true; 
    btn.innerHTML = '<span class="inline-block animate-spin mr-2">⌛</span> Memproses...';

    // Gunakan URL yang fleksibel (bisa diakses dari Admin Matrix maupun User Register)
    fetch(`../../user/kompetisi/edit_entry.php?event_id=<?= $eventId ?>&swimmer_id=<?= $swimmerId ?>&ajax=1`, {
        method: 'POST', 
        body: formData
    })
    .then(res => res.text())
    .then(data => {
        if(data.trim() === 'OK_RELOAD') { 
            // Jika sukses, reload halaman utama
            window.location.reload(); 
        } else { 
            alert(data); 
            btn.disabled = false; 
            btn.innerText = 'SIMPAN PERUBAHAN'; 
        }
    })
    .catch(err => {
        alert("Terjadi kesalahan jaringan.");
        btn.disabled = false;
        btn.innerText = 'SIMPAN PERUBAHAN';
    });
}
</script>