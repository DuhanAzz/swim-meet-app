<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Validasi Role
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'master')) {
    die("Akses Ditolak.");
}

$event_id = $_GET['event_id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Cek Event & Kepemilikan (Security Check)
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?"); // Admin boleh akses event manapun, tambahkan AND user_id = ? jika ingin strict
$stmt->execute([$event_id]);
$event = $stmt->fetch();
if (!$event) { header("Location: index.php"); exit; }

// FUNGSI BANTUAN: Konversi String Waktu ke Angka (Milidetik) untuk Sorting Akurat
function convertTimeToMs($timeStr) {
    if (empty($timeStr)) return 999999999; // Sangat lambat
    // Ganti koma dengan titik (antisipasi admin salah ketik)
    $timeStr = str_replace(',', '.', $timeStr);
    
    $parts = preg_split('/[:]/', $timeStr);
    $totalMs = 0;
    
    if (count($parts) == 2) { 
        // Format Menit:Detik.ms (Contoh 01:05.50)
        $menit = (float)$parts[0];
        $detik = (float)$parts[1];
        $totalMs = ($menit * 60000) + ($detik * 1000);
    } elseif (count($parts) == 1) {
        // Format Detik.ms (Contoh 59.50)
        $totalMs = (float)$parts[0] * 1000;
    }
    return $totalMs;
}

// --- HANDLE SIMPAN HASIL ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        // 1. SIMPAN DATA MENTAH (Waktu String & Status)
        if (isset($_POST['entries']) && is_array($_POST['entries'])) {
            foreach ($_POST['entries'] as $entryId => $data) {
                // Sanitasi input: ganti koma jadi titik, hapus spasi
                $rawTime = trim(str_replace(',', '.', $data['time']));
                $status = $data['status']; // OK, DQ, DNS, DNF
                
                // Jika Status bukan OK, waktu harus kosong atau dianggap tidak valid untuk ranking
                if ($status !== 'OK') {
                    $rank = null; 
                }
                
                $stmt = $pdo->prepare("UPDATE heat_entries SET final_time = ?, status = ? WHERE id = ?");
                $stmt->execute([$rawTime, $status, $entryId]);
            }
            
            // 2. HITUNG RANKING ULANG (Logika PHP)
            // Ambil semua hasil 'OK' di event ini
            $stmtAll = $pdo->prepare("
                SELECT id, final_time FROM heat_entries 
                WHERE heat_id IN (SELECT id FROM heats WHERE event_id = ?) 
                AND status = 'OK' 
                AND final_time IS NOT NULL 
                AND final_time != ''
            ");
            $stmtAll->execute([$event_id]);
            $results = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

            // Tambahkan nilai milidetik untuk sorting
            foreach ($results as $key => $row) {
                $results[$key]['ms'] = convertTimeToMs($row['final_time']);
            }

            // Sorting Array berdasarkan MS (Tercepat ke Terlambat)
            usort($results, function($a, $b) {
                if ($a['ms'] == $b['ms']) return 0;
                return ($a['ms'] < $b['ms']) ? -1 : 1;
            });

            // Reset Rank lama dulu
            $pdo->prepare("UPDATE heat_entries SET rank = NULL WHERE heat_id IN (SELECT id FROM heats WHERE event_id = ?)")->execute([$event_id]);

            // Update Rank Baru ke Database
            $rankCounter = 1;
            foreach ($results as $res) {
                $pdo->prepare("UPDATE heat_entries SET rank = ? WHERE id = ?")->execute([$rankCounter, $res['id']]);
                $rankCounter++;
            }
        }

        $pdo->commit();
        $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Hasil disimpan & Ranking diperbarui!';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['toast_type'] = 'error'; $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage();
    }
    // Refresh halaman agar data tampil
    header("Location: input_result.php?event_id=$event_id"); exit;
}

// --- AMBIL DATA UNTUK TAMPILAN ---
$stmt = $pdo->prepare("SELECT he.id, he.lane_number, he.final_time, he.status, he.rank, s.nama_atlet, c.nama_klub, h.heat_number 
                       FROM heat_entries he
                       JOIN heats h ON he.heat_id = h.id
                       JOIN swimmers s ON he.swimmer_id = s.id
                       LEFT JOIN clubs c ON s.club_id = c.id
                       WHERE h.event_id = ?
                       ORDER BY h.heat_number ASC, he.lane_number ASC");
$stmt->execute([$event_id]);
$entries = $stmt->fetchAll(PDO::FETCH_GROUP); 

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<?php if(isset($_SESSION['toast_message'])): ?>
    <?php $type = $_SESSION['toast_type'] ?? 'success'; $bgColor = ($type == 'success') ? 'bg-green-500' : 'bg-red-500'; ?>
    <div id="toast" class="fixed top-24 right-5 z-50 p-4 text-white <?= $bgColor ?> rounded shadow-lg transition-opacity duration-500 flex items-center gap-3">
        <span><?= ($type == 'success') ? '✅' : '⚠️' ?></span>
        <span class="font-bold"><?= htmlspecialchars($_SESSION['toast_message']) ?></span>
    </div>
    <script>
        setTimeout(() => { 
            const t = document.getElementById('toast'); 
            if(t) { t.style.opacity = '0'; setTimeout(() => t.remove(), 500); }
        }, 3000);
    </script>
    <?php unset($_SESSION['toast_message']); unset($_SESSION['toast_type']); ?>
<?php endif; ?>

<div class="p-6 sm:ml-64 mt-16 bg-slate-50 min-h-screen font-sans pb-32">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800 tracking-tight uppercase">Input Hasil</h1>
            <p class="text-sm text-slate-500 font-bold text-blue-600"><?= htmlspecialchars($event['nama_event']) ?></p>
        </div>
        <div class="flex gap-2">
            <a href="../events/index.php" class="bg-white border border-slate-300 text-slate-600 px-4 py-2 rounded-lg font-bold text-sm hover:bg-slate-50 transition">Kembali</a>
            <a href="print_startlist.php?event_id=<?= $event_id ?>" target="_blank" class="bg-slate-800 text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-slate-900 shadow-lg flex items-center gap-2">Start List</a>
            <a href="print_result.php?event_id=<?= $event_id ?>" target="_blank" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-blue-700 shadow-lg flex items-center gap-2">Cetak Hasil</a>
        </div>
    </div>

    <form method="POST" id="resultForm">
        
        <?php if(empty($entries)): ?>
            <div class="bg-white p-12 rounded-xl border-2 border-dashed border-slate-300 text-center shadow-sm">
                <div class="text-5xl mb-4">⚠️</div>
                <h3 class="text-xl font-bold text-slate-700">Belum Ada Peserta di Lintasan</h3>
                <p class="text-slate-500 mb-6 mt-2">Lakukan Seeding terlebih dahulu.</p>
                <a href="../events/seeding.php?id=<?= $event_id ?>" class="inline-block bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700 transition">Ke Menu Seeding</a>
            </div>
        <?php else: ?>
            
            <?php foreach($entries as $heatNo => $swimmers): ?>
            <div class="bg-white rounded-xl shadow-md border border-slate-200 overflow-hidden mb-8">
                <div class="bg-slate-800 px-6 py-3 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="font-bold text-white uppercase tracking-wider">HEAT <?= $heatNo ?></h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs text-slate-500 uppercase bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3 w-16 text-center border-r">Ln</th>
                                <th class="px-6 py-3">Atlet</th>
                                <th class="px-6 py-3 w-48 text-center bg-blue-50/50 border-x border-blue-100 font-black text-blue-800">WAKTU</th>
                                <th class="px-6 py-3 w-32 text-center">Status</th>
                                <th class="px-6 py-3 w-20 text-center border-l">Rank</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($swimmers as $s): ?>
                            <?php $rowClass = ($s['status'] != 'OK') ? 'bg-red-50' : 'hover:bg-blue-50/30'; ?>
                            
                            <tr class="<?= $rowClass ?> transition group">
                                <td class="px-6 py-4 text-center font-black text-slate-600 border-r bg-slate-50"><?= $s['lane_number'] ?></td>
                                
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-800 text-base"><?= htmlspecialchars($s['nama_atlet']) ?></div>
                                    <div class="text-xs text-slate-500 font-medium"><?= htmlspecialchars($s['nama_klub'] ?? 'Unattached') ?></div>
                                </td>

                                <td class="px-4 py-3 bg-blue-50/30 border-x border-blue-100">
                                    <input type="text" 
                                           name="entries[<?= $s['id'] ?>][time]" 
                                           value="<?= htmlspecialchars($s['final_time'] ?? '') ?>" 
                                           class="w-full text-center font-mono font-bold text-lg text-blue-900 border-2 border-blue-200 rounded-lg px-3 py-2 focus:border-blue-600 focus:ring-4 focus:ring-blue-200 transition placeholder-blue-200 bg-white" 
                                           placeholder="00:00.00"
                                           autocomplete="off">
                                </td>

                                <td class="px-4 py-3 text-center">
                                    <select name="entries[<?= $s['id'] ?>][status]" 
                                            class="w-full border border-slate-300 rounded-lg px-2 py-2 text-xs font-bold text-slate-700 text-center focus:ring-2 focus:ring-blue-500 cursor-pointer 
                                            <?= ($s['status']=='DQ')?'bg-red-100 text-red-800':'' ?>">
                                        <option value="OK" <?= $s['status']=='OK'?'selected':'' ?>>✅ OK</option>
                                        <option value="DQ" <?= $s['status']=='DQ'?'selected':'' ?>>❌ DQ</option>
                                        <option value="DNS" <?= $s['status']=='DNS'?'selected':'' ?>>🚫 DNS</option>
                                        <option value="DNF" <?= $s['status']=='DNF'?'selected':'' ?>>⚠️ DNF</option>
                                    </select>
                                </td>

                                <td class="px-6 py-4 text-center border-l bg-slate-50">
                                    <?php if($s['rank'] && $s['status'] == 'OK'): ?>
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full font-black text-sm shadow-sm
                                            <?= ($s['rank']==1)?'bg-yellow-400 text-white ring-2 ring-yellow-200':
                                               (($s['rank']==2)?'bg-slate-300 text-slate-800':
                                               (($s['rank']==3)?'bg-orange-400 text-white':'text-slate-400 bg-white border border-slate-200')) ?>">
                                            <?= $s['rank'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-300 font-bold">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="fixed bottom-0 right-0 w-full md:w-[calc(100%-16rem)] bg-white/90 backdrop-blur-md border-t border-slate-200 p-4 shadow-[0_-4px_20px_-5px_rgba(0,0,0,0.1)] flex justify-between items-center z-40">
                <span class="text-xs text-slate-500 font-bold ml-4 hidden md:block">
                    💡 Tips: Gunakan format <b>00:00.00</b> (Titik untuk pecahan detik).
                </span>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-blue-600/30 transition transform hover:-translate-y-1 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                    SIMPAN & HITUNG PERINGKAT
                </button>
            </div>
        <?php endif; ?>
    </form>
</div>