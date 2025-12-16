<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

function timeToMs($time) {
    if ($time == 'NT' || empty($time) || $time == '99.99.99' || $time == '-') return 999999999;
    $parts = preg_split('/[:.]/', str_replace(',', '.', $time));
    if (count($parts) == 3) {
        return ($parts[0] * 60000) + ($parts[1] * 1000) + ($parts[2] * 10);
    }
    return 999999999;
}

function getLaneOrder($laneCount) {
    $orders = [
        10 => [4, 5, 3, 6, 2, 7, 1, 8, 0, 9],
        8  => [4, 5, 3, 6, 2, 7, 1, 8],
        6  => [3, 4, 2, 5, 1, 6],
        4  => [2, 3, 1, 4]
    ];
    return $orders[$laneCount] ?? [3, 4, 2, 5, 1, 6];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_startlist'])) {
    try {
        $catId = $_POST['category_id'];
        $uid = $_SESSION['user_id'];
        
        $stmtSet = $pdo->prepare("SELECT lane_count, event_type FROM users WHERE id = ?");
        $stmtSet->execute([$uid]);
        $config = $stmtSet->fetch();
        
        $L = (int)($config['lane_count'] ?: 8);
        $system = $config['event_type']; // 'Langsung Final' atau 'Babak Penyisihan'

        // TENTUKAN STAGE AWAL
        $stage = ($system == 'Babak Penyisihan') ? 'Prelims' : 'Final';

        $stmt = $pdo->prepare("SELECT swimmer_id, entry_time FROM event_entries WHERE category_id = ? AND status = 'Approved'");
        $stmt->execute([$catId]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($entries)) throw new Exception("Belum ada peserta yang di-approve.");

        usort($entries, function($a, $b) { 
            return timeToMs($a['entry_time']) - timeToMs($b['entry_time']); 
        });

        $N = count($entries);
        $H = ceil($N / $L);
        $heatsSwimmers = array_fill(1, $H, []);

        $tempEntries = $entries;
        for ($i = $H; $i >= 1; $i--) {
            $take = ($i == 1) ? count($tempEntries) : $L;
            $heatsSwimmers[$i] = array_splice($tempEntries, -$take);
        }

        $pdo->beginTransaction();
        // Reset hanya untuk stage yang sedang dibuat agar data tidak tumpang tindih
        $pdo->prepare("DELETE rl FROM race_lines rl JOIN race_heats rh ON rl.heat_id = rh.id WHERE rh.category_id = ? AND rh.stage = ?")->execute([$catId, $stage]);
        $pdo->prepare("DELETE FROM race_heats WHERE category_id = ? AND stage = ?")->execute([$catId, $stage]);

        $laneOrder = getLaneOrder($L);

        foreach ($heatsSwimmers as $heatNo => $swimmersInHeat) {
            if (empty($swimmersInHeat)) continue;
            usort($swimmersInHeat, function($a, $b) { return timeToMs($a['entry_time']) - timeToMs($b['entry_time']); });
            
            $insH = $pdo->prepare("INSERT INTO race_heats (category_id, heat_number, stage) VALUES (?, ?, ?)");
            $insH->execute([$catId, $heatNo, $stage]);
            $heatId = $pdo->lastInsertId();
            
            foreach ($swimmersInHeat as $k => $sw) {
                if (isset($laneOrder[$k])) {
                    $pdo->prepare("INSERT INTO race_lines (heat_id, lane_number, swimmer_id, entry_time) VALUES (?, ?, ?, ?)")
                        ->execute([$heatId, $laneOrder[$k], $sw['swimmer_id'], $sw['entry_time']]);
                }
            }
        }
        $pdo->commit();
        header("Location: index.php?msg=success");
    } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); die("Error: " . $e->getMessage()); }
}