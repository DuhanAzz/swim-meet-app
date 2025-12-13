<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

function getLaneOrder($laneCount) {
    if ($laneCount == 10) return [4, 5, 3, 6, 2, 7, 1, 8, 0, 9];
    if ($laneCount == 8)  return [4, 5, 3, 6, 2, 7, 1, 8];
    return [3, 4, 2, 5, 1, 6];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['category_id'])) {
    try {
        $catId = $_POST['category_id'];
        $uid = $_SESSION['user_id'];

        // 1. Ambil Lane Count
        $stmtL = $pdo->prepare("SELECT lane_count FROM users WHERE id = ?");
        $stmtL->execute([$uid]);
        $L = (int)($stmtL->fetchColumn() ?: 8);

        // 2. Ambil Top Qualifiers (Sama seperti query di tampilan)
        $sql = "SELECT rl.swimmer_id, rl.result_time FROM race_lines rl
                JOIN race_heats rh ON rl.heat_id = rh.id
                WHERE rh.category_id = ? AND rh.stage = 'Prelims' 
                AND rl.result_time IS NOT NULL AND rl.result_time != '' AND rl.result_time != 'NT'
                ORDER BY rl.result_time ASC LIMIT $L";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$catId]);
        $finalists = $stmt->fetchAll();

        if (empty($finalists)) throw new Exception("Belum ada hasil prelims yang valid.");

        $pdo->beginTransaction();

        // 3. Hapus Final lama untuk kategori ini jika ada
        $pdo->prepare("DELETE FROM race_heats WHERE category_id = ? AND stage = 'Final'")->execute([$catId]);

        // 4. Buat Heat Final Baru
        $insH = $pdo->prepare("INSERT INTO race_heats (category_id, heat_number, stage) VALUES (?, 1, 'Final')");
        $insH->execute([$catId]);
        $heatId = $pdo->lastInsertId();

        // 5. Susun Spearhead
        $laneOrder = getLaneOrder($L);
        foreach ($finalists as $index => $f) {
            $lane = $laneOrder[$index];
            $insLine = $pdo->prepare("INSERT INTO race_lines (heat_id, lane_number, swimmer_id, entry_time) VALUES (?, ?, ?, ?)");
            $insLine->execute([$heatId, $lane, $f['swimmer_id'], $f['result_time']]);
        }

        $pdo->commit();
        header("Location: final.php?category_id=$catId&msg=final_success");

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}