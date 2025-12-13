<?php
require_once __DIR__ . '/../../config/database.php';

function timeToMs($time) {
    if ($time == 'NT' || empty($time) || $time == '-') return 999999999;
    $parts = preg_split('/[:.]/', $time);
    if (count($parts) == 3) {
        return ($parts[0] * 60000) + ($parts[1] * 1000) + ($parts[2] * 10);
    }
    return 999999999;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_startlist'])) {
    try {
        $catId = $_POST['category_id'];
        $lanesPerHeat = 8; 

        $stmt = $pdo->prepare("SELECT * FROM event_entries WHERE category_id = ?");
        $stmt->execute([$catId]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($entries)) {
            throw new Exception("Belum ada peserta mendaftar di nomor ini.");
        }

        usort($entries, function($a, $b) {
            return timeToMs($a['entry_time']) - timeToMs($b['entry_time']);
        });

        $pdo->beginTransaction();

        $del = $pdo->prepare("DELETE FROM race_heats WHERE category_id = ?");
        $del->execute([$catId]);
        
        $totalSwimmers = count($entries);
        $totalHeats = ceil($totalSwimmers / $lanesPerHeat);
        
        $laneOrder = [4, 5, 3, 6, 2, 7, 1, 8];

        $reversedEntries = array_reverse($entries);
        $chunks = array_chunk($reversedEntries, $lanesPerHeat); 
        
        foreach ($chunks as $index => $heatSwimmers) {
            $heatNo = $index + 1;
            
            $insHeat = $pdo->prepare("INSERT INTO race_heats (category_id, heat_number) VALUES (?, ?)");
            $insHeat->execute([$catId, $heatNo]);
            $heatId = $pdo->lastInsertId();

            usort($heatSwimmers, function($a, $b) {
                return timeToMs($a['entry_time']) - timeToMs($b['entry_time']);
            });

            foreach ($heatSwimmers as $k => $swimmer) {
                if (isset($laneOrder[$k])) {
                    $lane = $laneOrder[$k];
                    $insLine = $pdo->prepare("INSERT INTO race_lines (heat_id, lane_number, swimmer_id, entry_time) VALUES (?, ?, ?, ?)");
                    $insLine->execute([$heatId, $lane, $swimmer['swimmer_id'], $swimmer['entry_time']]);
                }
            }
        }

        $pdo->commit();
        header("Location: index.php?category_id=" . $catId . "&msg=success");

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: index.php?category_id=" . $catId . "&msg=error&info=" . urlencode($e->getMessage()));
    }
}
?>
