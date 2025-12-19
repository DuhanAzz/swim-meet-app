<?php
// MENGAKTIFKAN LAPORAN ERROR (Untuk Debugging)
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../../config/database.php';

// Cek Login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') exit('Akses ditolak');

$uid = $_SESSION['user_id'];
$organizerId = $_GET['event_id'] ?? 0;
$swimmerId = $_GET['swimmer_id'] ?? 0;

// Default waktu untuk No Time (NT)
$defaultNoTime = '99:99.99'; 

// --- 1. AMBIL DATA ATLET ---
$stmtS = $pdo->prepare("SELECT * FROM swimmers WHERE id = ? AND user_id = ?");
$stmtS->execute([$swimmerId, $uid]);
$swimmer = $stmtS->fetch();

if (!$swimmer) exit('<div class="p-6 text-red-500 text-center font-bold">Data atlet tidak ditemukan.</div>');

// --- 2. DETEKSI GENDER ---
$g = strtoupper($swimmer['jenis_kelamin']);
$targetGender = [];
if (in_array($g, ['L', 'M', 'MALE', 'LAKI-LAKI', 'PUTRA', 'PRIA'])) {
    $targetGender = ['L', 'M', 'MALE', 'PUTRA', 'MIXED', 'MIX', 'CAMPURAN'];
    $genderLabel = 'PUTRA';
} elseif (in_array($g, ['P', 'F', 'FEMALE', 'PEREMPUAN', 'PUTRI', 'WANITA'])) {
    $targetGender = ['P', 'F', 'FEMALE', 'PUTRI', 'MIXED', 'MIX', 'CAMPURAN'];
    $genderLabel = 'PUTRI';
} else {
    $targetGender = ['MIXED', 'MIX', 'CAMPURAN'];
    $genderLabel = 'UMUM';
}

// --- 3. PROSES SIMPAN DATA (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // A. Hapus entry lama atlet ini di event ini (Reset clean)
        $sqlDelete = "DELETE ee FROM event_entries ee 
                      JOIN event_numbers en ON ee.category_id = en.id 
                      WHERE ee.swimmer_id = ? AND en.organizer_id = ?";
        $stmtDel = $pdo->prepare($sqlDelete);
        $stmtDel->execute([$swimmerId, $organizerId]);

        // B. Masukkan Entry Baru (Jika ada yang dicentang)
        if (!empty($_POST['selected_events'])) {
            $stmtInsert = $pdo->prepare("INSERT INTO event_entries 
                (user_id, event_id, club_id, swimmer_id, category_id, entry_time, seed_time, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
            
            foreach ($_POST['selected_events'] as $catId) {
                // Ambil waktu dari input terpisah
                $timeInputName = 'time_' . $catId;
                $rawTime = $_POST[$timeInputName] ?? '';
                
                // LOGIC FIX: Jika kosong atau 00:00.00, simpan sebagai 99:99.99 (NT)
                if (empty($rawTime) || $rawTime === '00:00.00' || trim($rawTime) === '') {
                    $finalTime = $defaultNoTime;
                } else {
                    $finalTime = strtoupper(trim($rawTime));
                }
                
                // Simpan ke database
                $stmtInsert->execute([$uid, $organizerId, $uid, $swimmerId, $catId, $finalTime, $finalTime]);
            }
        }

        $pdo->commit();

        // C. LOGIKA PENGALIHAN HALAMAN
        header("Location: register_event.php?event_id=" . $organizerId);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative'>Error: " . $e->getMessage() . "</div>";
        exit;
    }
}

// --- 4. DATA UNTUK FORM (GET) ---

// A. Ambil Nomor Lomba yang Tersedia sesuai Gender
$availableEvents = [];
if(!empty($targetGender)) {
    $placeholders = implode(',', array_fill(0, count($targetGender), '?'));
    $sqlEvents = "SELECT * FROM event_numbers 
                  WHERE organizer_id = ? 
                  AND UPPER(jenis_kelamin) IN ($placeholders) 
                  ORDER BY distance ASC, stroke ASC";
    $stmtEv = $pdo->prepare($sqlEvents);
    $stmtEv->execute(array_merge([$organizerId], $targetGender));
    $availableEvents = $stmtEv->fetchAll();
}

// B. Ambil Entry yang sedang dipilih (Draft)
$sqlExist = "SELECT ee.category_id, ee.entry_time 
             FROM event_entries ee 
             JOIN event_numbers en ON ee.category_id = en.id
             WHERE ee.swimmer_id = ? AND en.organizer_id = ?";
$stmtEx = $pdo->prepare($sqlExist);
$stmtEx->execute([$swimmerId, $organizerId]);
$currentDraft = $stmtEx->fetchAll(PDO::FETCH_KEY_PAIR);

// C. AMBIL TRACK RECORD MANUAL
$manualRecords = [];
try {
    $stmtMan = $pdo->prepare("SELECT nomor_lomba, waktu_terbaik FROM athlete_records WHERE swimmer_id = ?");
    $stmtMan->execute([$swimmerId]);
    $manualRecords = $stmtMan->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) { /* Ignore */ }

// D. Ambil History Lomba Lama (Best Time Logic)
$historyMap = [];
try {
    $sqlHistory = "
        SELECT en.distance, en.stroke, MIN(ee.entry_time) as best_time
        FROM event_entries ee
        JOIN event_numbers en ON ee.category_id = en.id
        WHERE ee.swimmer_id = ? 
        AND ee.entry_time NOT IN ('00:00.00', '99:99.99', '', 'NT')
        GROUP BY en.distance, en.stroke
    ";
    $stmtHist = $pdo->prepare($sqlHistory);
    $stmtHist->execute([$swimmerId]);
    $historyData = $stmtHist->fetchAll();
    foreach($historyData as $h) {
        $historyMap[$h['distance'] . '-' . $h['stroke']] = $h['best_time'];
    }
} catch (Exception $e) { /* Ignore */ }

// Hitung Umur
$age = '-';
if (!empty($swimmer['tanggal_lahir'])) {
    $age = (date('Y') - date('Y', strtotime($swimmer['tanggal_lahir']))) . ' TH';
}
?>

<div class="bg-white w-full max-h-[90vh] flex flex-col h-full rounded-2xl relative">
    
    <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-900 text-white rounded-t-2xl shrink-0">
        <div>
            <h3 class="font-black text-xl uppercase italic tracking-wider">
                ENTRY FORM
            </h3>
            <div class="flex items-center gap-3 text-[10px] font-bold mt-1 text-blue-200 opacity-90">
                <span class="text-white uppercase"><?= htmlspecialchars($swimmer['nama_atlet']) ?></span>
                <span class="w-1 h-1 bg-blue-500 rounded-full"></span>
                <span><?= $genderLabel ?></span>
                <span class="w-1 h-1 bg-blue-500 rounded-full"></span>
                <span><?= $age ?></span>
            </div>
        </div>
        <button type="button" onclick="closeEntryModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-red-500 transition font-bold">✕</button>
    </div>

    <form id="entryForm" method="POST" action="edit_entry.php?event_id=<?= $organizerId ?>&swimmer_id=<?= $swimmerId ?>" class="flex-1 overflow-y-auto custom-scrollbar p-6 bg-slate-50">
        
        <?php if(empty($availableEvents)): ?>
            <div class="flex flex-col items-center justify-center h-48 text-center text-slate-400">
                <div class="text-4xl mb-2">📭</div>
                <h4 class="font-bold text-slate-600">Tidak Ada Nomor Lomba</h4>
                <p class="text-xs max-w-xs mt-1">Tidak ditemukan nomor lomba yang sesuai dengan gender/kelompok umur atlet ini.</p>
            </div>
        <?php else: ?>
            <p class="text-xs font-bold text-slate-500 mb-4 uppercase tracking-wide flex justify-between items-center">
                <span>Pilih Nomor Lomba:</span>
                <span class="text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded">Auto-Fill Aktif</span>
            </p>
            
            <div class="grid grid-cols-1 gap-3 pb-20"> <?php foreach($availableEvents as $ev): 
                    $catId = $ev['id'];
                    $isRegistered = isset($currentDraft[$catId]);
                    
                    // LOGIKA PRIORITY WAKTU (Saved > Manual > History)
                    $displayTime = '';
                    $sourceLabel = 'SEED TIME'; 
                    $sourceClass = 'text-slate-400';

                    $recordKey = $ev['distance'] . 'm ' . ucwords($ev['stroke']);
                    $historyKey = $ev['distance'] . '-' . $ev['stroke'];
                    
                    if ($isRegistered && !in_array($currentDraft[$catId], ['00:00.00', '99:99.99', ''])) {
                        $displayTime = $currentDraft[$catId];
                        $sourceLabel = 'TERSIMPAN'; $sourceClass = 'text-blue-500';
                    } elseif (isset($manualRecords[$recordKey])) {
                        $displayTime = $manualRecords[$recordKey];
                        $sourceLabel = 'BEST TIME'; $sourceClass = 'text-emerald-500';
                    } elseif (isset($historyMap[$historyKey])) {
                        $displayTime = $historyMap[$historyKey];
                        $sourceLabel = 'HISTORY'; $sourceClass = 'text-orange-500';
                    }
                ?>
                <label class="flex items-center gap-4 p-3 rounded-xl border transition-all cursor-pointer bg-white shadow-sm hover:shadow-md <?= $isRegistered ? 'border-blue-500 ring-1 ring-blue-500 bg-blue-50/30' : 'border-slate-200' ?>">
                    
                    <div class="relative flex items-center pl-2">
                        <input type="checkbox" 
                               name="selected_events[]" 
                               value="<?= $catId ?>" 
                               class="peer w-5 h-5 border-2 border-slate-300 rounded text-blue-600 focus:ring-blue-500 cursor-pointer"
                               <?= $isRegistered ? 'checked' : '' ?>
                               onchange="toggleInput(this)">
                    </div>

                    <div class="flex-1 px-2">
                        <div class="font-black text-sm text-slate-700">
                            <?= $ev['distance'] ?>M <?= strtoupper($ev['stroke']) ?>
                        </div>
                        <div class="text-[10px] text-slate-400 uppercase font-bold mt-0.5">
                            <?= $ev['age_group'] ?> • <?= $ev['jenis_kelamin'] ?>
                        </div>
                    </div>

                    <div class="<?= ($isRegistered || $displayTime != '') ? '' : 'opacity-40 pointer-events-none grayscale' ?> transition-opacity text-center min-w-[100px]" id="time-container-<?= $catId ?>">
                        <span class="text-[9px] font-black block mb-1 uppercase tracking-wider <?= $sourceClass ?>"><?= $sourceLabel ?></span>
                        <input type="text" 
                               name="time_<?= $catId ?>"
                               value="<?= htmlspecialchars($displayTime) ?>" 
                               placeholder="99:99.99" 
                               class="w-24 px-2 py-1.5 text-sm font-mono font-bold border border-slate-300 rounded focus:border-blue-500 outline-none text-center bg-white text-slate-700 placeholder:text-slate-300 uppercase">
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="absolute bottom-0 left-0 right-0 bg-white border-t border-slate-200 p-4 flex justify-between items-center gap-3 rounded-b-2xl z-20">
            
            <a href="register_event.php?event_id=<?= $organizerId ?>&remove_swimmer=<?= $swimmerId ?>" 
               onclick="return confirm('Yakin ingin menghapus atlet ini dari list pendaftaran? Data nomor yang sudah dicentang akan hilang.')"
               class="flex items-center gap-2 text-red-500 hover:text-red-700 hover:bg-red-50 px-4 py-3 rounded-xl font-bold text-xs transition border border-transparent hover:border-red-100">
                <span>🗑️</span> <span class="hidden sm:inline">Hapus Atlet</span>
            </a>

            <div class="flex gap-3 flex-1 justify-end">
                <button type="button" onclick="closeEntryModal()" class="px-6 py-3 rounded-xl font-bold text-slate-500 hover:bg-slate-100 border border-transparent transition">Batal</button>
                <button type="submit" class="px-6 py-3 rounded-xl font-bold bg-blue-600 text-white hover:bg-blue-700 shadow-lg shadow-blue-200 transition transform active:scale-95">
                    Simpan & Daftar
                </button>
            </div>
        </div>

    </form>
</div>

<script>
function toggleInput(checkbox) {
    const label = checkbox.closest('label');
    const timeContainer = label.querySelector('[id^="time-container-"]');
    const textInput = timeContainer.querySelector('input[type="text"]');

    if (checkbox.checked) {
        timeContainer.classList.remove('opacity-40', 'pointer-events-none', 'grayscale');
        // Jika kosong, isi default NT (99:99.99) agar user sadar
        if(textInput.value === '') {
            textInput.value = '99:99.99';
        }
        textInput.focus();
        label.classList.add('border-blue-500', 'ring-1', 'ring-blue-500', 'bg-blue-50/30');
        label.classList.remove('border-slate-200');
    } else {
        timeContainer.classList.add('opacity-40', 'pointer-events-none', 'grayscale');
        label.classList.remove('border-blue-500', 'ring-1', 'ring-blue-500', 'bg-blue-50/30');
        label.classList.add('border-slate-200');
    }
}
</script>