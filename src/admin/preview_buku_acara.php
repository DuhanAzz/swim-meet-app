<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') die("Akses Ditolak.");
$user_id = $_SESSION['user_id'];

// 1. AMBIL SEMUA EVENT MILIK ADMIN
$stmt = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY tanggal_lomba ASC, jam_lomba ASC");
$stmt->execute([$user_id]);
$events = $stmt->fetchAll();

// Fungsi Helper untuk Ambil Entries per Event
// PERBAIKAN: Menghapus JOIN ke swimmer_events yang tidak ada
function getEntries($pdo, $event_id) {
    $stmt = $pdo->prepare("SELECT 
        h.heat_number, he.lane_number, s.nama_atlet, c.nama_klub
        FROM heat_entries he
        JOIN heats h ON he.heat_id = h.id
        JOIN swimmers s ON he.swimmer_id = s.id
        JOIN clubs c ON s.club_id = c.id
        WHERE h.event_id = ?
        ORDER BY h.heat_number ASC, he.lane_number ASC");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll(PDO::FETCH_GROUP); // Group by Heat
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Preview Buku Acara</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #525659; font-family: 'Times New Roman', serif; }
        
        /* Simulasi Kertas A4 */
        .page {
            background: white;
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 15mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            position: relative;
        }

        /* Styling Tabel Start List */
        .event-header { 
            border-bottom: 2px solid black; 
            margin-bottom: 10px; 
            padding-bottom: 5px; 
            margin-top: 20px;
        }
        .heat-header {
            background-color: #f3f3f3;
            font-weight: bold;
            padding: 4px;
            border: 1px solid #000;
            font-size: 12px;
            margin-top: 10px;
        }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #000; padding: 3px 6px; }
        .text-center { text-align: center; }
        
        /* Toolbar Atas (Tidak ikut dicetak) */
        .toolbar {
            position: fixed; top: 0; left: 0; width: 100%;
            background: #333; color: white; padding: 10px;
            display: flex; justify-content: center; gap: 20px;
            z-index: 1000; box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }

        /* Mode Cetak */
        @media print {
            body { background: white; margin: 0; }
            .toolbar { display: none; }
            .page { width: 100%; margin: 0; box-shadow: none; min-height: auto; padding: 0; }
            .break-after { page-break-after: always; }
        }
    </style>
</head>
<body>

    <div class="toolbar">
        <div class="flex items-center gap-4">
            <span class="font-bold text-sm text-gray-300">PREVIEW MODE</span>
            <button onclick="window.close()" class="bg-gray-600 hover:bg-gray-500 px-4 py-1 rounded text-sm font-bold transition">
                &times; Tutup
            </button>
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-500 px-6 py-1 rounded text-sm font-bold shadow-lg flex items-center gap-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Cetak / Simpan PDF
            </button>
        </div>
    </div>

    <div class="page">
        
        <div class="text-center mb-8 border-b-4 border-black pb-4">
            <h1 class="text-2xl font-black uppercase">BUKU ACARA (START LIST)</h1>
            <h2 class="text-lg"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></h2> 
            <p class="text-sm">Generated on: <?= date('d F Y H:i') ?></p>
        </div>

        <?php 
        $counter = 0;
        foreach($events as $e): 
            $entries = getEntries($pdo, $e['id']);
            $counter++;
        ?>
            
            <div class="event-section" style="page-break-inside: avoid;">
                <div class="event-header">
                    <div class="flex justify-between items-end">
                        <div>
                            <span class="font-bold text-lg">NOMOR ACARA <?= $e['nomor_acara'] ?? $counter ?></span><br>
                            <span class="uppercase font-bold"><?= $e['nama_event'] ?></span>
                        </div>
                        <div class="text-right text-xs">
                            <?= $e['jarak'] ?>M <?= $e['gaya'] ?> <br>
                            <?= $e['jenis_kelamin']=='L'?'PUTRA':'PUTRI' ?> (KU <?= $e['batas_umur_bawah'] ?>-<?= $e['batas_umur_atas'] ?>)
                        </div>
                    </div>
                </div>

                <?php if(empty($entries)): ?>
                    <p class="text-xs italic text-center py-2">-- Belum ada peserta (Lakukan Seeding) --</p>
                <?php else: ?>
                    <?php foreach($entries as $heatNo => $swimmers): ?>
                        <div class="heat-wrapper" style="page-break-inside: avoid;">
                            <div class="heat-header">SERI <?= $heatNo ?> (Heat <?= $heatNo ?>)</div>
                            <table>
                                <thead>
                                    <tr>
                                        <th class="w-10 text-center">Ln</th>
                                        <th>Nama Atlet</th>
                                        <th>Klub / Sekolah</th>
                                        <th class="w-20 text-right">Entry Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($swimmers as $s): ?>
                                    <tr>
                                        <td class="text-center font-bold"><?= $s['lane_number'] ?></td>
                                        <td><?= htmlspecialchars($s['nama_atlet']) ?></td>
                                        <td><?= htmlspecialchars($s['nama_klub']) ?></td>
                                        <td class="text-right text-xs font-mono">
                                            NT </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="mb-6"></div> <?php endforeach; ?>

        <div class="absolute bottom-5 left-0 w-full text-center text-[10px] text-gray-500">
            Dokumen ini digenerate otomatis oleh SET System.
        </div>

    </div>

</body>
</html>
