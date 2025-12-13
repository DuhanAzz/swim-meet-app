<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'master')) die("Akses Ditolak.");

$event_id = $_GET['event_id'];

// Ambil Nama Event
$stmtEv = $pdo->prepare("SELECT * FROM events WHERE id = ?"); 
$stmtEv->execute([$event_id]); 
$ev = $stmtEv->fetch();

// QUERY AMBIL DATA (Termasuk kolom status)
$sql = "SELECT heats.heat_number, 
               heat_entries.id as entry_id, 
               heat_entries.lane_number, 
               heat_entries.final_time, 
               heat_entries.status, 
               swimmers.nama_atlet, 
               clubs.nama_klub
        FROM heat_entries 
        JOIN heats ON heat_entries.heat_id = heats.id 
        JOIN swimmers ON heat_entries.swimmer_id = swimmers.id 
        JOIN clubs ON swimmers.club_id = clubs.id
        WHERE heats.event_id = ? 
        ORDER BY heats.heat_number ASC, heat_entries.lane_number ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$event_id]);
$raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grouping Manual
$participants = [];
foreach ($raw_data as $row) {
    $participants[$row['heat_number']][] = $row;
}

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<?php if(isset($_SESSION['toast_message'])): ?>
    <?php $type = $_SESSION['toast_type'] ?? 'success'; $bgColor = ($type == 'success') ? 'bg-green-500' : 'bg-red-500'; ?>
    <div id="toast" class="fixed top-24 right-5 z-50 p-4 text-white <?= $bgColor ?> rounded shadow-lg transition-opacity duration-500">
        <?= htmlspecialchars($_SESSION['toast_message']) ?>
    </div>
    <script>setTimeout(() => { document.getElementById('toast').style.opacity = '0'; setTimeout(() => document.getElementById('toast').remove(), 500); }, 3000);</script>
    <?php unset($_SESSION['toast_message']); ?>
<?php endif; ?>

<div class="p-6 sm:ml-64 mt-16 pb-24">
    <div class="flex justify-between items-center mb-6">
        <div>
            <a href="index.php" class="text-blue-600 hover:underline mb-1 block">&larr; Kembali</a>
            <h1 class="text-2xl font-bold text-gray-800">Input Hasil: <?= htmlspecialchars($ev['nama_event']) ?></h1>
        </div>
    </div>

    <form action="store.php" method="POST">
        <input type="hidden" name="event_id" value="<?= $event_id ?>">
        
        <?php foreach($participants as $heatNum => $entries): ?>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8 overflow-hidden">
                <div class="bg-blue-50 px-6 py-3 border-b border-blue-100 font-bold text-blue-800">
                    SERI <?= $heatNum ?>
                </div>
                
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-2 text-center w-12">LINT</th>
                            <th class="px-4 py-2">ATLET</th>
                            <th class="px-4 py-2 text-center w-32">STATUS</th>
                            <th class="px-4 py-2 text-center w-40">WAKTU</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach($entries as $p): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-2 text-center font-bold text-gray-500 bg-gray-50 border-r"><?= $p['lane_number'] ?></td>
                            <td class="px-4 py-2">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($p['nama_atlet']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($p['nama_klub']) ?></div>
                            </td>
                            <td class="px-4 py-2 text-center">
                                <select name="status[<?= $p['entry_id'] ?>]" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded focus:ring-blue-500 focus:border-blue-500 p-2 w-full text-center font-bold">
                                    <option value="OK" <?= $p['status'] == 'OK' ? 'selected' : '' ?> class="text-green-600">OK (Sah)</option>
                                    <option value="DQ" <?= $p['status'] == 'DQ' ? 'selected' : '' ?> class="text-red-600">DQ (Gagal)</option>
                                    <option value="DNS" <?= $p['status'] == 'DNS' ? 'selected' : '' ?> class="text-gray-400">DNS (Absen)</option>
                                    <option value="DNF" <?= $p['status'] == 'DNF' ? 'selected' : '' ?> class="text-orange-500">DNF (Cedera)</option>
                                </select>
                            </td>
                            <td class="px-4 py-2">
                                <input type="text" 
                                       name="results[<?= $p['entry_id'] ?>]" 
                                       value="<?= htmlspecialchars($p['final_time'] ?? '') ?>" 
                                       class="bg-white border border-gray-300 text-gray-900 text-lg rounded focus:ring-blue-500 focus:border-blue-500 block w-full p-2 font-mono text-center tracking-wider" 
                                       placeholder="00:00.00">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

        <div class="fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 p-4 shadow-[0_-5px_15px_rgba(0,0,0,0.1)] sm:pl-72 flex justify-end z-40">
            <button type="submit" class="text-white bg-green-600 hover:bg-green-700 font-bold rounded-lg text-sm px-8 py-3 shadow-lg flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                SIMPAN HASIL
            </button>
        </div>
    </form>
</div>
