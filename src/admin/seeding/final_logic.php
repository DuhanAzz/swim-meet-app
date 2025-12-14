<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Fungsi bantu harus identik dengan logic.php
function getLaneOrder($laneCount) {
    $orders = [
        10 => [4, 5, 3, 6, 2, 7, 1, 8, 0, 9],
        8  => [4, 5, 3, 6, 2, 7, 1, 8],
        6  => [3, 4, 2, 5, 1, 6],
        4  => [2, 3, 1, 4]
    ];
    return $orders[$laneCount] ?? [3, 4, 2, 5, 1, 6];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['category_id'])) {
    try {
        $catId = $_POST['category_id'];
        $uid = $_SESSION['user_id'];

        $L = (int)($pdo->query("SELECT lane_count FROM users WHERE id = $uid")->fetchColumn() ?: 8);

        // PEMANGGILAN DATA: Mengambil hasil TERBAIK dari babak Prelims
        $sql = "SELECT rl.swimmer_id, rl.result_time FROM race_lines rl 
                JOIN race_heats rh ON rl.heat_id = rh.id
                WHERE rh.category_id = ? AND rh.stage = 'Prelims' 
                AND rl.result_time IS NOT NULL AND rl.result_time != '' AND rl.result_time != 'NT'
                ORDER BY rl.result_time ASC LIMIT $L";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$catId]);
        $finalists = $stmt->fetchAll();

        if (empty($finalists)) throw new Exception("Tidak ada hasil Prelims yang valid.");

        $pdo->beginTransaction();
        
        // Hapus final lama jika ada (Re-seed Final)
        $pdo->prepare("DELETE rl FROM race_lines rl JOIN race_heats rh ON rl.heat_id = rh.id WHERE rh.category_id = ? AND rh.stage = 'Final'")->execute([$catId]);
        $pdo->prepare("DELETE FROM race_heats WHERE category_id = ? AND stage = 'Final'")->execute([$catId]);

        // Buat seri Final (Selalu Heat 1)
        $insH = $pdo->prepare("INSERT INTO race_heats (category_id, heat_number, stage) VALUES (?, 1, 'Final')");
        $insH->execute([$catId]);
        $heatId = $pdo->lastInsertId();

        $laneOrder = getLaneOrder($L);
        
        // Seeding Spearhead: Perenang tercepat di Prelims masuk ke lintasan tengah Final
        foreach ($finalists as $index => $f) {
            if (isset($laneOrder[$index])) {
                $lane = $laneOrder[$index];
                $pdo->prepare("INSERT INTO race_lines (heat_id, lane_number, swimmer_id, entry_time) VALUES (?, ?, ?, ?)")
                    ->execute([$heatId, $lane, $f['swimmer_id'], $f['result_time']]);
            }
        }

        $pdo->commit();
        header("Location: final.php?msg=final_success");
    } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); die("Error Final: " . $e->getMessage()); }
}