<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// --- 1. CONFIG & VALIDASI ---
$organizerId = $_GET['event_id'] ?? 0; // ID Kompetisi (Admin ID)
$swimmerId   = $_GET['swimmer_id'] ?? 0;
$uid         = $_SESSION['user_id'] ?? 0; 
$isAjax      = isset($_GET['ajax']); 
$isAdmin     = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

if (!$uid && !$isAdmin) exit("Akses ditolak");

// --- 2. AMBIL DATA ATLET ---
// Kita butuh ID Klub (user_id) pemilik atlet untuk disimpan di tabel entries
if ($isAdmin) {
    $stmtS = $pdo->prepare("SELECT * FROM swimmers WHERE id = ?");
    $stmtS->execute([$swimmerId]);
} else {
    $stmtS = $pdo->prepare("SELECT * FROM swimmers WHERE id = ? AND user_id = ?");
    $stmtS->execute([$swimmerId, $uid]);
}
$s = $stmtS->fetch();
if (!$s) exit("Data atlet tidak ditemukan/Akses ditolak.");

$actualClubId = $s['user_id']; // ID Klub Asli
$swimmerGender = strtoupper($s['jenis_kelamin']); // L/P

// --- 3. PROSES SIMPAN DATA (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        // A. Hapus entry lama untuk atlet ini di event ini
        //    PENTING: Kita hapus berdasarkan event_id (Organizer) dan swimmer_id
        $del = $pdo->prepare("DELETE FROM event_entries WHERE event_id = ? AND swimmer_id = ?");
        $del->execute([$organizerId, $swimmerId]);

        // B. Insert entry baru
        if (!empty($_POST['categories'])) {
            $ins = $pdo->prepare("INSERT INTO event_entries (event_id, club_id, swimmer_id, category_id, entry_time) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($_POST['categories'] as $catId) {
                // Ambil waktu dari input. Jika kosong/strip, set default 'NT'
                $timeVal = trim($_POST['times'][$catId] ?? '');
                if (empty($timeVal) || $timeVal == '-') $timeVal = 'NT';
                
                // PENTING:
                // event_id    = ID Kompetisi ($organizerId)
                // category_id = ID Nomor Lomba ($catId) dari tabel events
                $ins->execute([$organizerId, $actualClubId, $swimmerId, $catId, $timeVal]);
            }
        }
        
        $pdo->commit();
        if ($isAjax) { echo "OK_RELOAD"; exit; }
        header("Location: register_event.php?event_id=$organizerId"); exit;
        
    } catch (Exception $e) { 
        $pdo->rollBack(); 
        if ($isAjax) { echo "Error Database: " . $e->getMessage(); exit; }
        echo "Error: " . $e->getMessage(); exit; 
    }
}

// --- 4. AMBIL DATA UNTUK TAMPILAN ---

// A. Ambil Daftar Nomor Lomba (Events)
$stmtEv = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY nomor_acara ASC");
$stmtEv->execute([$organizerId]);
$allEvents = $stmtEv->fetchAll();

// B. Ambil Entry yang SUDAH ADA (Data yang baru saja disimpan)
$stmtEntry = $pdo->prepare("SELECT category_id, entry_time FROM event_entries WHERE event_id = ? AND swimmer_id = ?");
$stmtEntry->execute([$organizerId, $swimmerId]);
$myEntries = $stmtEntry->fetchAll(PDO::FETCH_KEY_PAIR); // Hasil: [ID_LOMBA => WAKTU]

// C. Ambil PERSONAL BEST (Track Record) dari tabel records (JIKA ADA)
//    Ini mengembalikan kode lama Anda supaya "Track Record" terbaca
$pbRecords = [];
try {
    $stmtPB = $pdo->prepare("SELECT nomor_lomba, waktu_terbaik FROM athlete_records WHERE swimmer_id = ?");
    $stmtPB->execute([$swimmerId]);
    $pbRaw = $stmtPB->fetchAll(); // Kita olah manual nanti
    
    // Normalisasi array agar mudah dicari: [ "50M GAYA BEBAS" => "00.25.00" ]
    foreach($pbRaw as $rec) {
        $key = strtoupper(trim($rec['nomor_lomba'])); // Pastikan uppercase
        $pbRecords[$key] = $rec['waktu_terbaik'];
    }
} catch (Exception $e) {
    // Jika tabel athlete_records belum ada, abaikan error ini
    $pbRecords = [];
}

?>

<div class="bg-white rounded-2xl overflow-hidden shadow-2xl border border-slate-200 text-left font-sans">
    
    <div class="bg-slate-900 p-6 text-white flex justify-between items-start">
        <div>
            <h1 class="text-xs font-black uppercase tracking-widest italic text-blue-400 mb-1">Entry Form</h1>
            <h2 class="text-lg font-bold uppercase leading-none"><?= htmlspecialchars($s['nama_atlet']) ?></h2>
            <div class="flex items-center gap-2 mt-2 text-[10px] font-bold text-slate-400 bg-slate-800 py-1 px-3 rounded-full w-fit">
                <span><?= $swimmerGender ?></span>
                <span>•</span>
                <span><?= isset($s['tanggal_lahir']) ? (date('Y') - date('Y', strtotime($s['tanggal_lahir']))) : '-' ?> TH</span>
            </div>
        </div>
        <?php if($isAjax): ?>
            <button type="button" onclick="closeModal()" class="text-slate-500 hover:text-white transition text-2xl leading-none">&times;</button>
        <?php endif; ?>
    </div>

    <form id="formEditEntry" class="p-6 bg-slate-50/50">
        <div class="space-y-3 max-h-[50vh] overflow-y-auto pr-2 custom-scrollbar">
            
            <?php if(empty($allEvents)): ?>
                <div class="p-8 text-center border-2 border-dashed border-slate-300 rounded-xl">
                    <p class="text-slate-400 font-bold text-xs uppercase">Event belum tersedia.</p>
                </div>
            <?php endif; ?>

            <?php foreach($allEvents as $evt): 
                // --- 1. LOGIKA GENDER ---
                $evtGender = strtoupper($evt['gender'] ?? 'MIXED'); 
                $isEligible = false;
                
                // Normalisasi string untuk pencocokan yang lebih luas
                if ($evtGender == 'MIXED' || $evtGender == 'CAMPURAN') {
                    $isEligible = true;
                } elseif ((strpos($evtGender, 'PUTRA') !== false || $evtGender == 'L' || $evtGender == 'MALE') && in_array($swimmerGender, ['L', 'PRIA', 'MALE', 'PUTRA'])) {
                    $isEligible = true;
                } elseif ((strpos($evtGender, 'PUTRI') !== false || $evtGender == 'P' || $evtGender == 'FEMALE') && in_array($swimmerGender, ['P', 'WANITA', 'FEMALE', 'PUTRI'])) {
                    $isEligible = true;
                }

                // --- 2. TENTUKAN WAKTU (PRIORITAS: Sudah Daftar > Personal Best > NT) ---
                $catId = $evt['id'];
                $isRegistered = isset($myEntries[$catId]);
                
                // Cari nama event bersih untuk mencocokkan dengan PB (misal: "101 - 50m Free" -> kita cari "50m Free" nya)
                $cleanName = strtoupper($evt['nama_event'] ?? $evt['nama_acara']);
                // Coba cari PB yang mengandung kata kunci jarak & gaya (Logika sederhana)
                $pbTime = '';
                // (Opsi: Jika nama event di PB persis sama dengan nama acara)
                if (isset($pbRecords[$cleanName])) {
                    $pbTime = $pbRecords[$cleanName];
                }

                // Value input: Jika sudah daftar pakai entry_time, jika belum pakai PB, jika gak ada PB pakai ''
                $inputValue = $isRegistered ? $myEntries[$catId] : $pbTime;
                
                // Visual variables
                $nomor = $evt['nomor_acara'] ?? '-';
                $nama  = $evt['nama_event'] ?? $evt['nama_acara'];
                $harga = number_format($evt['price'] ?? 0, 0, ',', '.');
                
                // Styling
                $opacityClass = $isEligible ? 'opacity-100' : 'opacity-40 grayscale bg-slate-100';
                $borderClass  = $isRegistered ? 'border-blue-600 ring-1 ring-blue-100 bg-white shadow-md' : 'border-slate-200 bg-white hover:border-blue-300';
            ?>

            <div class="flex items-center justify-between p-4 rounded-xl border-2 transition-all duration-200 group <?= $borderClass ?> <?= $opacityClass ?>">
                
                <div class="flex items-center gap-4 overflow-hidden">
                    <div class="relative flex items-center justify-center">
                        <input type="checkbox" 
                               name="categories[]" 
                               value="<?= $catId ?>" 
                               id="chk_<?= $catId ?>"
                               class="w-6 h-6 rounded-lg border-2 border-slate-300 text-blue-600 focus:ring-offset-0 focus:ring-0 cursor-pointer transition-transform active:scale-90"
                               <?= $isRegistered ? 'checked' : '' ?>
                               <?= !$isEligible ? 'disabled' : '' ?>
                               onchange="toggleInput('<?= $catId ?>')">
                    </div>

                    <div class="flex flex-col min-w-0 cursor-pointer" onclick="<?= $isEligible ? "document.getElementById('chk_$catId').click()" : '' ?>">
                        <label class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-0.5 pointer-events-none">
                            NO. <?= $nomor ?> 
                            <?php if(!$isEligible): ?><span class="text-red-500 ml-1 font-bold">(Beda Gender)</span><?php endif; ?>
                        </label>
                        <h3 class="font-bold text-slate-800 text-xs uppercase truncate w-full pr-4"><?= htmlspecialchars($nama) ?></h3>
                        <p class="text-[9px] text-slate-400 font-bold mt-1">IDR <?= $harga ?></p>
                    </div>
                </div>
                
                <div class="flex flex-col items-end pl-2">
                    <label class="text-[8px] font-bold text-slate-300 uppercase mb-1 tracking-wider">Entry Time</label>
                    <input type="text" 
                           name="times[<?= $catId ?>]" 
                           id="inp_<?= $catId ?>"
                           value="<?= $inputValue ?>"
                           placeholder="NT"
                           <?= (!$isRegistered) ? 'readonly' : '' // Kunci jika belum dicentang ?>
                           class="w-24 h-9 rounded-lg border-2 text-center font-mono text-xs font-bold uppercase focus:outline-none transition-colors 
                                  <?= $isRegistered ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-100 bg-slate-100 text-slate-400' ?>">
                    <?php if(!empty($pbTime) && !$isRegistered): ?>
                        <span class="text-[8px] text-green-600 font-bold mt-1">PB: <?= $pbTime ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-6 flex gap-3 pt-4 border-t border-slate-100">
            <button type="button" onclick="closeModal()" class="flex-1 py-3.5 rounded-xl border-2 border-slate-200 text-slate-500 font-bold text-[10px] uppercase hover:bg-slate-50 transition">
                Batal
            </button>
            <button type="button" onclick="submitEntryForm()" id="btnSave" class="flex-[2] py-3.5 rounded-xl bg-blue-600 text-white font-bold text-[10px] uppercase shadow-lg shadow-blue-200 hover:bg-blue-700 active:scale-95 transition tracking-widest">
                Simpan Perubahan
            </button>
        </div>
    </form>
</div>

<script>
// Logic Toggle UI saat Checkbox diklik
function toggleInput(id) {
    const chk = document.getElementById('chk_' + id);
    const inp = document.getElementById('inp_' + id);
    const container = chk.closest('.border-2'); 

    if (chk.checked) {
        // Mode: DIPILIH
        inp.readOnly = false;
        // Ubah warna input jadi biru
        inp.classList.remove('bg-slate-100', 'text-slate-400', 'border-slate-100');
        inp.classList.add('bg-blue-50', 'text-blue-700', 'border-blue-200');
        
        // Ubah container jadi highlight
        container.classList.remove('border-slate-200');
        container.classList.add('border-blue-600', 'bg-white', 'shadow-md');
        
        // Auto isi NT jika kosong
        if(inp.value.trim() === '') inp.value = 'NT';
        inp.focus();
    } else {
        // Mode: BATAL PILIH
        inp.readOnly = true;
        // Reset warna input jadi abu
        inp.classList.add('bg-slate-100', 'text-slate-400', 'border-slate-100');
        inp.classList.remove('bg-blue-50', 'text-blue-700', 'border-blue-200');

        // Reset container
        container.classList.add('border-slate-200');
        container.classList.remove('border-blue-600', 'bg-white', 'shadow-md');
        
        // Jangan hapus value total, agar jika user salah klik, angkanya masih ada (UX)
        // Tapi secara visual terlihat disabled
    }
}

// Logic Submit AJAX
function submitEntryForm() {
    const btn = document.getElementById('btnSave');
    const form = document.getElementById('formEditEntry');
    const formData = new FormData(form);
    
    // Validasi sederhana: Cek apakah ada checkbox yang dicentang
    // (Opsional: Kalau mau allow hapus semua, hapus validasi ini)
    
    btn.disabled = true; 
    btn.innerHTML = '⏳ MENYIMPAN...';

    fetch(`../../user/kompetisi/edit_entry.php?event_id=<?= $organizerId ?>&swimmer_id=<?= $swimmerId ?>&ajax=1`, {
        method: 'POST', 
        body: formData
    })
    .then(res => res.text())
    .then(data => {
        if(data.trim() === 'OK_RELOAD') { 
            // Sukses!
            window.location.reload(); 
        } else { 
            // Gagal
            alert('Gagal menyimpan: ' + data); 
            console.error('Server Error:', data); // Cek console inspect element
            btn.disabled = false; 
            btn.innerText = 'SIMPAN PERUBAHAN'; 
        }
    })
    .catch(err => {
        alert("Terjadi kesalahan koneksi.");
        console.error(err);
        btn.disabled = false;
        btn.innerText = 'SIMPAN PERUBAHAN';
    });
}

function closeModal() {
    if(typeof window.closeEntryModal === 'function') {
        window.closeEntryModal();
    } else {
        history.back();
    }
}
</script>