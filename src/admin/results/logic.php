<?php
require_once __DIR__ . '/../../config/database.php';

// Fungsi konversi waktu ke milidetik (untuk sorting juara)
function timeToMs($time) {
    // Format: MM:SS.ms (00:30.50)
    // Jika DQ (Diskualifikasi) atau DNS (Did Not Start), beri nilai sangat besar
    if (in_array(strtoupper($time), ['DQ', 'DSQ'])) return 888888888;
    if (in_array(strtoupper($time), ['DNS', 'NS'])) return 999999999;
    if (empty($time) || $time == '-') return 999999999;

    $parts = preg_split('/[:.]/', $time);
    if (count($parts) == 3) {
        return ($parts[0] * 60000) + ($parts[1] * 1000) + ($parts[2] * 10);
    }
    return 999999999;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_results'])) {
    try {
        $catId = $_POST['category_id'];
        $results = $_POST['result'] ?? []; // Array [line_id] => "00:29.15"

        $pdo->beginTransaction();

        // 1. Simpan Waktu Finis
        $upd = $pdo->prepare("UPDATE race_lines SET result_time = ? WHERE id = ?");
        foreach ($results as $lineId => $time) {
            $time = trim(strtoupper($time)); // Biar DQ/DNS jadi huruf besar
            $upd->execute([$time, $lineId]);
        }

        // 2. Hitung Peringkat (Ranking) Otomatis
        // Ambil semua hasil di kategori ini yang valid (ada waktunya)
        $stmt = $pdo->prepare("
            SELECT rl.id, rl.result_time 
            FROM race_lines rl 
            JOIN race_heats rh ON rl.heat_id = rh.id 
            WHERE rh.category_id = ?
        ");
        $stmt->execute([$catId]);
        $allSwimmers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Sort berdasarkan waktu tercepat
        usort($allSwimmers, function($a, $b) {
            return timeToMs($a['result_time']) - timeToMs($b['result_time']);
        });

        // Update Rank ke Database
        $rankUpd = $pdo->prepare("UPDATE race_lines SET rank = ? WHERE id = ?");
        $rank = 1;
        foreach ($allSwimmers as $s) {
            $ms = timeToMs($s['result_time']);
            // Jika valid time (bukan DNS/kosong), beri ranking
            if ($ms < 800000000) { 
                $rankUpd->execute([$rank, $s['id']]);
                $rank++;
            } else {
                // Jika DQ/DNS, rank dikosongkan/null
                $rankUpd->execute([null, $s['id']]);
            }
        }

        $pdo->commit();
        header("Location: index.php?category_id=" . $catId . "&msg=success");

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: index.php?category_id=" . $catId . "&msg=error");
    }
}
?>
