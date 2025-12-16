<?php
// src/entries/create.php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../public/login.php"); exit;
}

$event_id = $_GET['event_id'] ?? 0;
$club_user_id = $_SESSION['user_id'];

// 1. AMBIL INFO EVENT (NOMOR LOMBA)
// Kita perlu tahu Jarak, Gaya, dan Gender event ini untuk filter atlet
$stmtEv = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtEv->execute([$event_id]);
$event = $stmtEv->fetch();

if (!$event) die("Event tidak ditemukan.");

// Validasi Status Event
if ($event['status'] == 'tutup') {
    die("Pendaftaran untuk event ini sudah ditutup.");
}

// 2. PROSES PENDAFTARAN (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        $count = 0;
        if (isset($_POST['entries']) && is_array($_POST['entries'])) {
            foreach ($_POST['entries'] as $swimmer_id => $data) {
                // Jika checkbox dicentang
                if (isset($data['selected'])) {
                    $seed_time = $data['seed_time'];
                    
                    // Cek duplikasi (jangan sampai daftar 2x di nomor yang sama)
                    $cek = $pdo->prepare("SELECT id FROM entries WHERE event_id = ? AND swimmer_id = ?");
                    $cek->execute([$event_id, $swimmer_id]);
                    
                    if ($cek->rowCount() == 0) {
                        $ins = $pdo->prepare("INSERT INTO entries (event_id, swimmer_id, seed_time) VALUES (?, ?, ?)");
                        $ins->execute([$event_id, $swimmer_id, $seed_time]);
                        $count++;
                    }
                }
            }
        }
        
        $pdo->commit();
        // Redirect dengan pesan sukses
        header("Location: ../user/kompetisi/index.php?msg=registered&count=$count");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Gagal mendaftar: " . $e->getMessage();
    }
}

// 3. AMBIL ATLET YANG SESUAI KUALIFIKASI (Putra/Putri)
// Filter otomatis: Jika event Putra, hanya tampilkan atlet Male.
$genderFilter = ($event['jenis_kelamin'] == 'L') ? 'Male' : 'Female'; // Sesuaikan kode di DB (L/P atau Male/Female)
// Note: Sesuaikan logika di atas dengan isi kolom jenis_kelamin di tabel events Anda. 
// Jika di tabel events isinya 'L', tapi di swimmers isinya 'Male', perlu mapping seperti di atas.

// Query Canggih: Join dengan athlete_records untuk ambil Magic Seed Time
// Kita cari apakah atlet ini punya best time di jarak & gaya yang sama dengan event ini
$sqlSwimmers = "
    SELECT s.*, 
           (SELECT waktu_terbaik FROM athlete_records ar 
            WHERE ar.swimmer_id = s.id 
            AND ar.nomor_lomba LIKE ? 
            ORDER BY ar.created_at DESC LIMIT 1) as magic_time
    FROM swimmers s 
    WHERE s.user_id = ? 
    AND (s.jenis_kelamin = ? OR ? = 'Mixed') -- Handle Mixed Relay jika ada
    ORDER BY s.nama_atlet ASC
";

$searchPattern = $event['jarak'] . '%' . $event['gaya'] . '%'; // Contoh: "50m%Gaya Bebas%"
$stmtS = $pdo->prepare($sqlSwimmers);
// Asumsi: $event['jenis_kelamin'] di tabel Anda isinya 'L' atau 'P'. 
// Mapping: L -> Male, P -> Female (Sesuaikan dengan data swimmers Anda)
$genderDb = ($event['jenis_kelamin'] == 'L' || $event['jenis_kelamin'] == 'Male') ? 'Male' : 'Female';

$stmtS->execute([$searchPattern, $club_user_id, $genderDb, $event['jenis_kelamin']]);
$eligibleSwimmers = $stmtS->fetchAll();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans pb-32">
    
    <div class="max-w-4xl mx-auto">
        <a href="../user/kompetisi/index.php" class="text-slate-500 hover:text-blue-600 font-bold text-xs uppercase mb-4 inline-block">&larr; Batal & Kembali</a>
        
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-6 flex justify-between items-center">
            <div>
                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded text-[10px] font-black uppercase tracking-wider mb-2 inline-block">Form Pendaftaran</span>
                <h1 class="text-2xl font-black text-slate-800 uppercase leading-none"><?= htmlspecialchars($event['nama_event']) ?></h1>
                <p class="text-slate-500 mt-2 font-medium text-sm">
                    Kategori: <span class="text-slate-800 font-bold"><?= $event['jenis_kelamin']=='L'?'Putra':'Putri' ?></span> | 
                    Jarak: <span class="text-slate-800 font-bold"><?= $event['jarak'] ?>m <?= $event['gaya'] ?></span>
                </p>
            </div>
            <div class="text-right hidden sm:block">
                <div class="text-4xl font-black text-slate-200">ENTRY</div>
            </div>
        </div>

        <?php if(isset($error)): ?>
            <div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 font-bold text-sm">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="entryForm">
            <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="font-bold text-slate-700">Pilih Atlet</h3>
                    <span class="text-xs font-bold text-slate-400 uppercase">Hanya atlet <?= $genderDb ?> yg muncul</span>
                </div>
                
                <?php if(empty($eligibleSwimmers)): ?>
                    <div class="p-10 text-center">
                        <p class="text-slate-500 font-medium mb-4">Tidak ada atlet yang memenuhi syarat (Gender/Umur) untuk nomor ini.</p>
                        <a href="../user/atlet/create.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-bold text-sm">Tambah Atlet Baru</a>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100">
                        <?php foreach($eligibleSwimmers as $s): 
                            $hasMagicTime = !empty($s['magic_time']);
                            // Format Magic Time: Jika ada, pakai. Jika tidak, NT.
                            $defaultTime = $hasMagicTime ? $s['magic_time'] : 'NT';
                        ?>
                        <label class="flex items-center gap-4 p-4 hover:bg-blue-50 transition cursor-pointer group">
                            <div class="relative flex items-center">
                                <input type="checkbox" name="entries[<?= $s['id'] ?>][selected]" value="1" 
                                       class="peer w-6 h-6 border-2 border-slate-300 rounded focus:ring-blue-500 cursor-pointer text-blue-600"
                                       onchange="toggleInput(this, <?= $s['id'] ?>)">
                            </div>
                            
                            <div class="flex-1">
                                <div class="font-bold text-slate-800 uppercase group-hover:text-blue-700"><?= htmlspecialchars($s['nama_atlet']) ?></div>
                                <div class="text-xs text-slate-400 font-mono">Lahir: <?= date('d/m/Y', strtotime($s['tanggal_lahir'])) ?></div>
                            </div>

                            <div class="text-right">
                                <span class="text-[10px] font-bold uppercase text-slate-400 block mb-1">Entry Time</span>
                                <input type="text" 
                                       name="entries[<?= $s['id'] ?>][seed_time]" 
                                       id="time_<?= $s['id'] ?>"
                                       value="<?= $defaultTime ?>" 
                                       class="w-32 text-right font-mono font-bold text-slate-700 border border-slate-300 rounded px-2 py-1 focus:border-blue-500 outline-none disabled:bg-slate-100 disabled:text-slate-300 transition"
                                       <?= $hasMagicTime ? '' : 'placeholder="00:00.00"' ?>
                                       disabled> <?php if($hasMagicTime): ?>
                                    <div class="text-[10px] text-green-600 font-bold mt-1 flex justify-end items-center gap-1">
                                        <span>⚡ Auto-fill Best Time</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="fixed bottom-0 left-0 w-full bg-white border-t border-slate-200 p-4 shadow-[0_-5px_15px_rgba(0,0,0,0.1)] sm:ml-64 sm:w-[calc(100%-16rem)] flex justify-between items-center z-40 transition-transform transform translate-y-full" id="floatingBar">
                <div class="text-sm font-bold text-slate-600 ml-4">
                    <span id="selectedCount">0</span> Atlet Dipilih
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg transition flex items-center gap-2">
                    <span>📝</span> Konfirmasi Pendaftaran
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleInput(checkbox, id) {
    const input = document.getElementById('time_' + id);
    const floatBar = document.getElementById('floatingBar');
    
    if (checkbox.checked) {
        input.disabled = false;
        input.focus();
        input.classList.add('border-blue-500', 'ring-1', 'ring-blue-500');
    } else {
        input.disabled = true;
        input.classList.remove('border-blue-500', 'ring-1', 'ring-blue-500');
    }

    // Update Counter
    const checkedBoxes = document.querySelectorAll('input[type="checkbox"]:checked');
    document.getElementById('selectedCount').innerText = checkedBoxes.length;

    // Show/Hide Floating Bar
    if (checkedBoxes.length > 0) {
        floatBar.classList.remove('translate-y-full');
    } else {
        floatBar.classList.add('translate-y-full');
    }
}
</script>