<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

// Helper: Konversi Waktu ke Milidetik untuk Sorting
function timeToMs($time) {
    if ($time == 'NT' || empty($time) || $time == '99.99.99' || $time == '-') return 999999999;
    $parts = preg_split('/[:.]/', str_replace(',', '.', $time));
    if (count($parts) == 3) {
        return ($parts[0] * 60000) + ($parts[1] * 1000) + ($parts[2] * 10);
    }
    if (count($parts) == 2) {
        return ($parts[0] * 1000) + ($parts[1] * 10);
    }
    return 999999999;
}

// Helper: Pola Lintasan (Zig-Zag / Center Out)
function getLaneOrder($laneCount) {
    // Array urutan berdasarkan ranking (0 = tercepat)
    // Contoh 8 Lintasan: Ranking 1 dpt lintasan 4, Ranking 2 dpt lintasan 5, dst.
    $orders = [
        10 => [5, 6, 4, 7, 3, 8, 2, 9, 1, 10], 
        8  => [4, 5, 3, 6, 2, 7, 1, 8],
        6  => [3, 4, 2, 5, 1, 6],
        4  => [2, 3, 1, 4]
    ];
    return $orders[$laneCount] ?? [3, 4, 2, 5, 1, 6]; // Default 6 lane
}

if (isset($_GET['category_id'])) {
    try {
        $catId = $_GET['category_id'];
        $uid = $_SESSION['user_id'];
        
        // 1. AMBIL KONFIGURASI KOLAM
        $stmtSet = $pdo->prepare("SELECT lane_count FROM users WHERE id = ?");
        $stmtSet->execute([$uid]);
        $config = $stmtSet->fetch();
        $LANE_COUNT = (int)($config['lane_count'] ?: 8);

        // 2. AMBIL DATA ENTRIES (Hanya Paid)
        $sqlEntries = "SELECT ee.id, ee.entry_time 
                       FROM event_entries ee 
                       WHERE ee.category_id = ? 
                       AND EXISTS (
                           SELECT 1 FROM payments p 
                           WHERE p.user_id = ee.user_id 
                           AND p.event_id = ee.event_id 
                           AND p.status = 'Paid'
                       )";
        
        $stmt = $pdo->prepare($sqlEntries);
        $stmt->execute([$catId]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($entries)) {
            header("Location: index.php?msg=empty_data"); exit;
        }

        // 3. SORTING DATA (TERCEPAT -> TERLAMBAT)
        usort($entries, function($a, $b) { 
            return timeToMs($a['entry_time']) - timeToMs($b['entry_time']); 
        });

        // ==========================================================
        // 4. LOGIKA PEMBAGIAN SERI (MINIMAL 3 PESERTA PER SERI)
        // ==========================================================
        
        // A. Bagi menjadi chunks normal dulu (Fastest -> Slowest)
        // Chunk 0 = Seri Tercepat (Biasanya Full)
        // Chunk Terakhir = Seri Terlambat (Bisa sisa 1 atau 2)
        $chunks = array_chunk($entries, $LANE_COUNT);
        
        $totalChunks = count($chunks);

        // B. Cek Seri Terlambat (Chunk Terakhir)
        if ($totalChunks > 1) {
            $lastIndex = $totalChunks - 1;
            $countLast = count($chunks[$lastIndex]);

            // Jika seri terakhir isinya < 3 orang
            if ($countLast < 3) {
                // Hitung berapa yang perlu dicuri dari seri sebelumnya
                $needed = 3 - $countLast;

                // Ambil dari seri sebelumnya (Seri yang lebih cepat tepat di atasnya)
                // Kita ambil dari BAGIAN BELAKANG seri sebelumnya (yang paling lambat di seri itu)
                for ($i = 0; $i < $needed; $i++) {
                    // Ambil 1 atlet paling lambat dari chunk sebelumnya
                    $stolenSwimmer = array_pop($chunks[$lastIndex - 1]);
                    
                    // Masukkan ke chunk terakhir (taruh di depan antrian seri lambat agar urutan terjaga saat disort nanti)
                    array_unshift($chunks[$lastIndex], $stolenSwimmer);
                }
            }
        }

        // C. Atur Urutan Seri (Heat 1 = Slowest, Heat Terakhir = Fastest)
        // Saat ini $chunks[0] adalah Fastest. Kita balik urutannya.
        $heatsData = array_reverse($chunks);

        // ==========================================================
        // 5. UPDATE DATABASE
        // ==========================================================
        
        $laneOrderPattern = getLaneOrder($LANE_COUNT);

        $pdo->beginTransaction();

        // Reset data lama
        $stmtReset = $pdo->prepare("UPDATE event_entries SET heat = NULL, lane = NULL WHERE category_id = ?");
        $stmtReset->execute([$catId]);

        $stmtUpdate = $pdo->prepare("UPDATE event_entries SET heat = ?, lane = ? WHERE id = ?");

        foreach ($heatsData as $index => $swimmersInHeat) {
            $heatNumber = $index + 1; // Heat 1, 2, 3...
            
            // PENTING: Sort ulang per Heat agar ranking lokal (1, 2, 3...) benar untuk penempatan lintasan
            usort($swimmersInHeat, function($a, $b) { 
                return timeToMs($a['entry_time']) - timeToMs($b['entry_time']); 
            });

            // Assign Lintasan
            foreach ($swimmersInHeat as $k => $swimmer) {
                if (isset($laneOrderPattern[$k])) {
                    $assignedLane = $laneOrderPattern[$k];
                    $stmtUpdate->execute([$heatNumber, $assignedLane, $swimmer['id']]);
                }
            }
        }

        $pdo->commit();
        
        header("Location: view_startlist.php?category_id=" . $catId . "&status=success");
        exit;

    } catch (Exception $e) { 
        if ($pdo->inTransaction()) $pdo->rollBack(); 
        die("Error Logic: " . $e->getMessage()); 
    }
} else {
    header("Location: index.php"); exit;
}
?>