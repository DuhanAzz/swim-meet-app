<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Fungsi konversi MM:SS.ms ke milidetik untuk sorting ranking
function timeToMs($time) {
    if (empty($time) || $time == 'NT' || $time == '99.99.99' || $time == '-') return 999999999;
    $parts = preg_split('/[:.]/', $time);
    if (count($parts) == 3) {
        return ($parts[0] * 60000) + ($parts[1] * 1000) + ($parts[2] * 10);
    }
    return 999999999;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_results'])) {
    try {
        $catId = $_POST['category_id'];
        $stage = $_POST['stage'] ?? 'Prelims';
        $results = $_POST['result']; // Array [lane_id => time]

        $pdo->beginTransaction();

        // 1. Simpan semua waktu finis
        foreach ($results as $laneId => $time) {
            $stmt = $pdo->prepare("UPDATE race_lines SET result_time = ? WHERE id = ?");
            $stmt->execute([strtoupper(trim($time)), $laneId]);
        }

        // 2. Kalkulasi Ranking Otomatis untuk kategori dan stage ini
        $sql = "SELECT rl.id, rl.result_time FROM race_lines rl
                JOIN race_heats rh ON rl.heat_id = rh.id
                WHERE rh.category_id = ? AND rh.stage = ?
                AND rl.result_time IS NOT NULL AND rl.result_time != '' AND rl.result_time != 'NT'";
        
        $stmtRank = $pdo->prepare($sql);
        $stmtRank->execute([$catId, $stage]);
        $rows = $stmtRank->fetchAll();

        // Sorting berdasarkan waktu tercepat
        usort($rows, function($a, $b) {
            return timeToMs($a['result_time']) - timeToMs($b['result_time']);
        });

        // Update rank ke database
        foreach ($rows as $index => $row) {
            $rank = $index + 1;
            $pdo->prepare("UPDATE race_lines SET rank = ? WHERE id = ?")->execute([$rank, $row['id']]);
        }

        $pdo->commit();
        header("Location: index.php?category_id=$catId&stage=$stage&msg=success");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Gagal simpan: " . $e->getMessage());
    }
}