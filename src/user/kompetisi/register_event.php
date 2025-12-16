<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// --- 1. CEK LOGIN USER ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$organizerId = $_GET['event_id'] ?? 0; // ID Admin (Organizer)

// --- 2. AMBIL INFO ADMIN / ORGANIZER ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([$organizerId]);
$organizer = $stmt->fetch();

if (!$organizer) {
    die("<div class='p-10 text-center font-sans'>
            <h1 class='text-2xl font-bold text-red-600'>🚫 Organizer Tidak Ditemukan</h1>
            <p>ID Admin ($organizerId) tidak ada di tabel users.</p>
            <a href='explore.php' class='text-blue-500 underline'>Kembali</a>
         </div>");
}

// --- 3. AMBIL DATA ATLET SAYA ---
$mySwimmers = $pdo->prepare("SELECT * FROM swimmers WHERE user_id = ? ORDER BY nama_atlet ASC");
$mySwimmers->execute([$uid]);
$swimmers = $mySwimmers->fetchAll();

// --- 4. AMBIL NOMOR LOMBA (EVENTS) ---
// Perbaikan: Kita ambil data, nanti kita filter manual jika query WHERE user_id gagal
// Hal ini untuk mengantisipasi jika saat create event, user_id tidak tersimpan
$allCats = $pdo->prepare("SELECT * FROM events ORDER BY nomor_acara ASC");
$allCats->execute();
$rawEvents = $allCats->fetchAll();

$categories = [];
foreach ($rawEvents as $row) {
    // FILTER: Hanya ambil event milik Organizer ID ini
    // Jika kolom user_id di tabel events NULL/0, kita anggap milik admin yang sedang aktif (bypass sementara)
    // ATAU jika user_id sesuai dengan ID di URL
    if ($row['user_id'] == $organizerId || $row['user_id'] == 0 || $row['user_id'] == NULL) {
        
        // LOGIKA PARSING NAMA (PENTING!)
        // Jika kolom distance/style kosong, kita ambil dari nama_event
        // Contoh nama: "101 - 50M GAYA BEBAS - KU 19-99"
        
        $jarak = $row['distance'] ?? '';
        $gaya  = $row['style'] ?? '';
        $gender = $row['gender'] ?? 'Mixed';

        if (empty($jarak) || empty($gaya)) {
            $nama = strtoupper($row['nama_event'] ?? $row['nama_acara'] ?? ''); // Cek nama_event atau nama_acara
            
            // Coba tebak Jarak
            if (strpos($nama, '50M') !== false) $jarak = '50';
            elseif (strpos($nama, '100M') !== false) $jarak = '100';
            elseif (strpos($nama, '200M') !== false) $jarak = '200';
            elseif (strpos($nama, '400M') !== false) $jarak = '400';
            else $jarak = 'Umum';

            // Coba tebak Gaya
            if (strpos($nama, 'BEBAS') !== false) $gaya = 'Free';
            elseif (strpos($nama, 'DADA') !== false) $gaya = 'Breast';
            elseif (strpos($nama, 'PUNGGUNG') !== false) $gaya = 'Back';
            elseif (strpos($nama, 'KUPU') !== false) $gaya = 'Fly';
            elseif (strpos($nama, 'GANTI') !== false) $gaya = 'IM';
            else $gaya = 'Lainnya';
        }

        // Simpan data yang sudah dirapikan ke array baru
        $row['parsed_distance'] = $jarak;
        $row['parsed_style'] = $gaya;
        $row['parsed_gender'] = $gender; // Pastikan kolom gender ada di tabel events
        
        $categories[] = $row;
    }
}

// --- 5. SIAPKAN HEADER TABEL (MATRIX) ---
$headers = [];
foreach ($categories as $cat) {
    $d = $cat['parsed_distance'] . 'm';
    $s = $cat['parsed_style'];
    $headers[$d][$s] = true;
}
// Sort header jarak biar rapi (50m, 100m, dst)
ksort($headers);

// --- 6. AMBIL DATA ENTRY (YANG SUDAH DAFTAR) ---
$saved = [];
$totalEntriesCount = 0;
$totalFee = 0;

try {
    $stmtEntries = $pdo->prepare("
        SELECT ee.*, e.price 
        FROM event_entries ee 
        JOIN events e ON ee.category_id = e.id 
        WHERE ee.club_id = ?
    ");
    $stmtEntries->execute([$uid]);
    $savedEntries = $stmtEntries->fetchAll();

    foreach($savedEntries as $row) { 
        $saved[$row['swimmer_id']][$row['category_id']] = $row['entry_time']; 
        $totalEntriesCount++;
        $totalFee += $row['price'];
    }
} catch (Exception $e) { /* Silent fail */ }

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    .sticky-col { position: sticky; background: #fff; z-index: 10; border-right: 1px solid #e2e8f0; }
    .sticky-header { position: sticky; top: 0; z-index: 20; background: #f8fafc; }
    .sticky-1 { left: 0; width: 50px; }
    .sticky-2 { left: 50px; min-width: 200px; }
    .cell-input { width: 100%; height: 40px; text-align: center; border: none; background: transparent; font-family: monospace; font-size: 11px; outline: none; }
    .cell-input.filled { font-weight: bold; color: #2563eb; background: #eff6ff; }
    /* Scrollbar Tipis */
    .custom-scrollbar::-webkit-scrollbar { height: 8px; width: 8px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="bg-gradient-to-r from-blue-900 to-slate-800 rounded-3xl p-8 text-white shadow-xl flex flex-col md:flex-row justify-between items-center gap-6 mb-8 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full blur-3xl -mr-16 -mt-16 pointer-events-none"></div>
        
        <div class="relative z-10">
            <div class="flex items-center gap-4 mb-2">
                <div class="bg-blue-500/20 p-2 rounded-lg backdrop-blur-sm border border-blue-400/30">
                    <span class="text-2xl">🏊‍♂️</span>
                </div>
                <div>
                    <h1 class="text-2xl font-black uppercase tracking-tight italic">
                        <?= htmlspecialchars($organizer['nama_lengkap']) ?>
                    </h1>
                    <div class="flex gap-3 text-[10px] font-bold text-blue-200 uppercase tracking-widest mt-1">
                        <span>📍 <?= htmlspecialchars($organizer['location'] ?? 'Indonesia') ?></span>
                        <span>•</span>
                        <span>📅 <?= date('d M Y') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex gap-3 relative z-10">
            <a href="explore.php" class="bg-white/10 hover:bg-white/20 text-white px-6 py-3 rounded-2xl font-bold text-xs uppercase border border-white/10 transition backdrop-blur-sm flex items-center gap-2">
                <span>↩</span> Kembali
            </a>
            <a href="../pembayaran.php" class="bg-emerald-400 hover:bg-emerald-300 text-slate-900 px-6 py-3 rounded-2xl font-black text-xs uppercase shadow-lg shadow-emerald-900/20 transition flex items-center gap-2">
                <span>💰</span> Status Bayar
            </a>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-[2.5rem] shadow-sm overflow-hidden mb-24 relative">
        
        <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-slate-50/50">
            <div class="relative w-full md:w-80 group">
                <input type="text" id="tableSearch" onkeyup="filterTable()" class="block w-full py-3 pl-12 pr-4 text-xs font-bold border border-slate-200 rounded-2xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-white transition group-hover:border-slate-300" placeholder="Cari nama atlet...">
                <div class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>
            
            <div class="flex items-center gap-2 text-[10px] font-bold text-slate-500 bg-white px-4 py-2 rounded-xl border border-slate-200 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-blue-100 border border-blue-500 block"></span>
                <span>TERDAFTAR</span>
                <span class="w-px h-3 bg-slate-200 mx-2"></span>
                <span class="w-2 h-2 rounded-full bg-slate-100 border border-slate-300 block"></span>
                <span>KOSONG</span>
            </div>
        </div>

        <?php if(empty($swimmers)): ?>
            <div class="flex flex-col items-center justify-center py-32 text-center">
                <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mb-4 text-4xl grayscale opacity-50">👶</div>
                <h3 class="text-slate-800 font-black text-lg">Belum Ada Atlet</h3>
                <p class="text-slate-500 text-sm max-w-xs mx-auto mt-2 mb-6">Database klub Anda masih kosong. Tambahkan atlet terlebih dahulu.</p>
                <a href="../atlet/index.php" class="bg-blue-600 text-white px-6 py-3 rounded-xl font-bold text-xs shadow-lg shadow-blue-200 hover:bg-blue-700 transition">Tambah Atlet Sekarang</a>
            </div>

        <?php elseif(empty($categories)): ?>
            <div class="flex flex-col items-center justify-center py-20 text-center bg-red-50 m-10 rounded-3xl border border-red-100">
                <div class="text-4xl mb-4">🐞</div>
                <h3 class="text-red-800 font-black text-lg">Nomor Lomba Tidak Muncul?</h3>
                <div class="text-left bg-white p-6 rounded-xl border border-red-100 shadow-sm mt-4 text-xs font-mono text-slate-600">
                    <p><strong>Status Debugging:</strong></p>
                    <ul class="list-disc pl-4 space-y-1 mt-2">
                        <li>ID Organizer di URL: <span class="text-red-600 font-bold"><?= $organizerId ?></span></li>
                        <li>Total Events di DB: <strong><?= count($rawEvents) ?></strong> baris.</li>
                        <li>Sampel Data Event Pertama (jika ada):<br> 
                            <?php if(!empty($rawEvents[0])): ?>
                                Nama: <?= $rawEvents[0]['nama_event'] ?? $rawEvents[0]['nama_acara'] ?><br>
                                User ID Pembuat: <span class="bg-yellow-200 px-1"><?= $rawEvents[0]['user_id'] ?></span>
                            <?php else: ?>
                                (Tabel events kosong)
                            <?php endif; ?>
                        </li>
                    </ul>
                    <p class="mt-4 text-slate-500 italic">
                        Jika User ID Pembuat (blok kuning) berbeda dengan ID URL (<?= $organizerId ?>),<br> 
                        berarti link yang Anda klik di 'Explore' salah, atau data Admin tidak sinkron.
                    </p>
                </div>
            </div>

        <?php else: ?>
            <div class="overflow-x-auto max-h-[60vh] custom-scrollbar">
                <table class="w-full text-sm text-left border-collapse" id="entryTable">
                    <thead class="text-[10px] text-slate-700 uppercase">
                        <tr>
                            <th class="w-16 px-2 py-4 sticky-header sticky-col sticky-1 text-center z-50 bg-slate-50 border-b border-slate-200">Aksi</th>
                            <th class="w-64 px-6 py-4 sticky-header sticky-col sticky-2 z-50 bg-slate-50 border-b border-slate-200 font-black text-left">Nama Atlet</th>
                            <?php foreach($headers as $dist => $styles): ?>
                                <th class="px-2 py-3 text-center border-l border-slate-200 bg-slate-100 text-slate-800 font-black sticky-header border-b border-slate-300" colspan="<?= count($styles) ?>"><?= $dist ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th class="sticky-col sticky-1 top-[48px] z-40 bg-white border-b border-slate-200 h-10"></th>
                            <th class="sticky-col sticky-2 top-[48px] z-40 bg-white border-b border-slate-200 h-10"></th>
                            <?php foreach($headers as $dist => $styles): foreach($styles as $style => $v): ?>
                                <th class="px-1 py-2 text-center border-l border-slate-100 min-w-[80px] bg-white text-[9px] font-bold text-slate-400 border-b border-slate-200 sticky-header top-[48px] h-10 align-middle">
                                    <?= $style ?>
                                </th>
                            <?php endforeach; endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($swimmers as $s): ?>
                        <tr class="hover:bg-blue-50/30 border-b border-slate-50 h-12 swimmer-row transition-colors group">
                            
                            <td class="sticky-col sticky-1 text-center bg-white group-hover:bg-blue-50/30 border-r border-slate-100">
                                <button type="button" onclick="openEntryModal(<?= $s['id'] ?>)" class="w-8 h-8 flex items-center justify-center rounded-xl bg-orange-50 text-orange-500 hover:bg-orange-500 hover:text-white transition mx-auto shadow-sm border border-orange-100">
                                    ✏️
                                </button>
                            </td>
                            
                            <td class="sticky-col sticky-2 px-6 font-bold text-slate-700 text-[11px] truncate max-w-[200px] border-r border-slate-100 bg-white group-hover:bg-blue-50/30 uppercase">
                                <?= htmlspecialchars($s['nama_atlet']) ?>
                                <div class="text-[9px] text-slate-400 font-normal mt-0.5"><?= $s['jenis_kelamin'] ?> • <?= date('Y') - date('Y', strtotime($s['tanggal_lahir'])) ?> Th</div>
                            </td>

                            <?php foreach($headers as $dist => $styles): foreach($styles as $style => $v): 
                                $matchId = null;
                                // LOGIKA MATCHING YANG LEBIH KUAT
                                foreach($categories as $cat) {
                                    $cDist = $cat['parsed_distance'] . 'm'; // Tambah 'm' biar sama formatnya
                                    $cStyle = $cat['parsed_style'];
                                    
                                    // Cek Kecocokan: Jarak, Gaya, dan Gender
                                    if($cDist == $dist && $cStyle == $style) {
                                        // Cek Gender: Jika Event Mixed, semua boleh. Jika tidak, harus sama.
                                        $catGender = strtolower($cat['parsed_gender']);
                                        $swimGender = strtolower($s['jenis_kelamin']); // 'pria'/'wanita' atau 'L'/'P'
                                        
                                        // Normalisasi Gender (Sesuaikan dengan DB Anda: L/P atau Putra/Putri)
                                        $isGenderMatch = false;
                                        if ($catGender == 'mixed' || $catGender == 'campuran') $isGenderMatch = true;
                                        elseif (strpos($catGender, 'putra') !== false && ($swimGender == 'l' || $swimGender == 'pria' || strpos($swimGender, 'putra')!==false)) $isGenderMatch = true;
                                        elseif (strpos($catGender, 'putri') !== false && ($swimGender == 'p' || $swimGender == 'wanita' || strpos($swimGender, 'putri')!==false)) $isGenderMatch = true;
                                        
                                        if($isGenderMatch) {
                                            $matchId = $cat['id']; 
                                            break; 
                                        }
                                    }
                                }
                                $val = ($matchId && isset($saved[$s['id']][$matchId])) ? $saved[$s['id']][$matchId] : '';
                            ?>
                                <td class="p-0 border-l border-slate-50 h-12 align-middle text-center relative">
                                    <?php if($matchId): ?>
                                        <div class="absolute inset-0 flex items-center justify-center p-1">
                                            <div class="w-full h-full rounded-lg flex items-center justify-center font-mono text-[10px] transition-all <?= $val ? 'bg-blue-100 text-blue-700 font-bold border border-blue-200' : 'bg-transparent text-slate-300' ?>">
                                                <?= $val ? $val : '-' ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-full h-full bg-slate-100/50 flex items-center justify-center text-slate-200 text-[8px] cursor-not-allowed pattern-diagonal-lines">✕</div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="fixed bottom-8 left-0 right-0 z-50 flex justify-center px-4 pointer-events-none">
        <div class="bg-slate-900 text-white p-4 rounded-3xl shadow-2xl shadow-slate-900/50 flex items-center gap-6 border border-white/10 pointer-events-auto transform transition hover:scale-105 duration-300">
            <div class="pl-4">
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Total Tagihan</p>
                <p class="text-2xl font-black text-emerald-400">Rp<?= number_format($totalFee, 0, ',', '.') ?></p>
            </div>
            <div class="h-10 w-px bg-white/20"></div>
            <div class="pr-2">
                <a href="checkout.php?event_id=<?= $organizerId ?>" class="bg-blue-600 hover:bg-blue-500 text-white px-8 py-3 rounded-2xl font-black text-xs uppercase shadow-lg shadow-blue-600/40 flex items-center gap-2 transition">
                    <span>Finalisasi</span>
                    <span class="bg-white/20 px-2 py-0.5 rounded text-[10px]"><?= $totalEntriesCount ?></span>
                </a>
            </div>
        </div>
    </div>

</div>

<div id="entryModal" class="fixed inset-0 z-[100] hidden bg-slate-900/60 backdrop-blur-md flex justify-center items-center p-4 opacity-0 transition-opacity duration-300">
    <div class="w-full max-w-lg transform scale-95 transition-transform duration-300" id="modalContent"></div>
    <div class="absolute inset-0 -z-10" onclick="closeEntryModal()"></div>
</div>

<script>
function filterTable() {
    const filter = document.getElementById("tableSearch").value.toUpperCase();
    const rows = document.getElementById("entryTable").getElementsByClassName("swimmer-row");
    for (let i = 0; i < rows.length; i++) {
        const nameCol = rows[i].getElementsByTagName("td")[1];
        if (nameCol) rows[i].style.display = (nameCol.textContent).toUpperCase().indexOf(filter) > -1 ? "" : "none";
    }
}

function openEntryModal(swimmerId) {
    const modal = document.getElementById('entryModal');
    const content = document.getElementById('modalContent');
    
    modal.classList.remove('hidden');
    // Micro-delay for animation
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
    }, 10);

    content.innerHTML = `
        <div class="bg-white rounded-[2rem] p-12 flex flex-col items-center justify-center shadow-2xl">
            <div class="animate-spin w-10 h-10 border-4 border-blue-200 border-t-blue-600 rounded-full mb-4"></div>
            <p class="font-black text-slate-300 uppercase tracking-widest text-xs animate-pulse">Memuat Data...</p>
        </div>
    `;

    fetch(`edit_entry.php?event_id=<?= $organizerId ?>&swimmer_id=${swimmerId}&ajax=1`)
        .then(res => res.text())
        .then(html => {
            content.innerHTML = html;
            const scripts = content.querySelectorAll("script");
            scripts.forEach(s => { const n = document.createElement("script"); n.text = s.text; document.body.appendChild(n).parentNode.removeChild(n); });
        });
}

function closeEntryModal() { 
    const modal = document.getElementById('entryModal');
    const content = document.getElementById('modalContent');
    
    modal.classList.add('opacity-0');
    content.classList.remove('scale-100');
    content.classList.add('scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}
</script>