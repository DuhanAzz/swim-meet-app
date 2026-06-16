<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// FUNGSI BANTUAN: Konversi Waktu String ke Milidetik (PENTING UNTUK SORTING)
function timeToMs($timeStr) {
    if ($timeStr == 'NT' || $timeStr == '00:00.00' || empty($timeStr)) return 999999999; // Anggap paling lambat
    
    // Format asumsi: MM:SS.ms (01:05.50) atau SS.ms (59.50)
    $parts = preg_split('/[:.]/', $timeStr);
    $totalMs = 0;
    
    if (count($parts) == 3) { // Format 01:05.50
        $totalMs = ($parts[0] * 60000) + ($parts[1] * 1000) + ($parts[2] * 10);
    } elseif (count($parts) == 2) { // Format 59.50
        $totalMs = ($parts[0] * 1000) + ($parts[1] * 10);
    }
    return $totalMs;
}

// FUNGSI BANTUAN: Generate Spearhead (Urutan Lintasan) Dinamis
function getSpearheadOrder($totalLanes) {
    // Contoh 8 lintasan: [4, 5, 3, 6, 2, 7, 1, 8]
    // Contoh 6 lintasan: [3, 4, 2, 5, 1, 6]
    $order = [];
    $center = ceil($totalLanes / 2);
    
    $order[] = $center; // Tercepat di tengah
    for ($i = 1; $i < $totalLanes; $i++) {
        if ($i % 2 != 0) { // Ganjil: Kanan
            $val = $center + ceil($i/2);
            if($val <= $totalLanes) $order[] = $val;
        } else { // Genap: Kiri
            $val = $center - ($i/2);
            if($val > 0) $order[] = $val;
        }
    }
    return $order;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $event_id = $_POST['event_id'];
    
    try {
        $pdo->beginTransaction(); // Mulai Transaksi (Safety)

        // 1. AMBIL KONFIGURASI KOLAM DARI USER PEMBUAT EVENT
        // Kita perlu tahu kolam ini punya berapa lintasan?
        // Asumsi: event -> created_by (user) -> lane_count
        // Jika tidak ada data, default ke 8
        $stmtConfig = $pdo->prepare("
            SELECT u.lane_count 
            FROM events e 
            JOIN users u ON e.created_by = u.id 
            WHERE e.id = ?
        ");
        $stmtConfig->execute([$event_id]);
        $lane_count = $stmtConfig->fetchColumn() ?: 8; // Default 8 jika null

        // 2. BERSIHKAN DATA LAMA (RESET)
        $pdo->prepare("DELETE FROM heats WHERE event_id = ?")->execute([$event_id]);

        // 3. AMBIL PENDAFTAR
        $stmt = $pdo->prepare("SELECT * FROM entries WHERE event_id = ?");
        $stmt->execute([$event_id]);
        $swimmers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($swimmers) == 0) throw new Exception("Belum ada pendaftar.");

        // 4. LOGIKA SORTING (DI LEVEL PHP)
        // Kita sort di PHP supaya bisa convert string waktu ke milidetik dengan akurat
        usort($swimmers, function($a, $b) {
            $tA = timeToMs($a['seed_time']);
            $tB = timeToMs($b['seed_time']);
            
            if ($tA == $tB) return 0;
            return ($tA < $tB) ? -1 : 1; // Tercepat (nilai kecil) di atas
        });

        // 5. DISTRIBUSI HEAT (Straight Seeding)
        // Membalik array chunk agar seri tercepat ada di Heat terakhir
        $heats_distribution = array_chunk($swimmers, $lane_count);
        $heats_distribution = array_reverse($heats_distribution);

        // 6. GENERATE URUTAN LINTASAN (Spearhead)
        $lane_order = getSpearheadOrder($lane_count);

        // 7. SIMPAN KE DATABASE
        foreach ($heats_distribution as $index => $heat_swimmers) {
            $heat_number = $index + 1; // Heat 1, Heat 2...

            // Create Heat
            $sqlHeat = "INSERT INTO heats (event_id, heat_number, status) VALUES (?, ?, 'pending')";
            $stmtHeat = $pdo->prepare($sqlHeat);
            $stmtHeat->execute([$event_id, $heat_number]);
            $heat_id = $pdo->lastInsertId();

            // Assign Lanes
            // Kita mapping urutan $key (0=tercepat di heat ini) ke $lane_order
            foreach ($heat_swimmers as $key => $entry) {
                if (isset($lane_order[$key])) {
                    $lane = $lane_order[$key];
                    
                    $sqlEntry = "INSERT INTO heat_entries (heat_id, swimmer_id, lane_number, seed_time) VALUES (?, ?, ?, ?)";
                    $pdo->prepare($sqlEntry)->execute([
                        $heat_id, 
                        $entry['swimmer_id'], 
                        $lane,
                        $entry['seed_time']
                    ]);
                }
            }
        }

        $pdo->commit(); // Simpan Permanen
        header("Location: view_startlist.php?event_id=" . $event_id . "&msg=success");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack(); // Batalkan semua perubahan jika error
        die("System Error: " . $e->getMessage());
    }
}
?>