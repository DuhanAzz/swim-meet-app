<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

function timeToMs($time) {
    if ($time == 'NT' || empty($time) || $time == '99.99.99' || $time == '-') return 999999999;
    $parts = preg_split('/[:.]/', $time);
    if (count($parts) == 3) return ($parts[0] * 60000) + ($parts[1] * 1000) + ($parts[2] * 10);
    return 999999999;
}

function getLaneOrder($laneCount) {
    if ($laneCount == 10) return [4, 5, 3, 6, 2, 7, 1, 8, 0, 9];
    if ($laneCount == 8)  return [4, 5, 3, 6, 2, 7, 1, 8];
    if ($laneCount == 6)  return [3, 4, 2, 5, 1, 6];
    if ($laneCount == 4)  return [2, 3, 1, 4];
    return [3, 4, 2, 5, 1, 6];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_startlist'])) {
    try {
        $catId = $_POST['category_id'];
        
        // 1. Ambil Setting EO (Sinkron dengan Master)
        $stmtSet = $pdo->prepare("SELECT lane_count, event_type FROM users WHERE id = ?");
        $stmtSet->execute([$_SESSION['user_id']]);
        $config = $stmtSet->fetch();
        
        $L = (int)($config['lane_count'] ?: 8);
        $system = $config['event_type']; // 'Langsung Final' atau 'Babak Penyisihan'

        // 2. Ambil Peserta & Urutkan
        $stmt = $pdo->prepare("SELECT * FROM event_entries WHERE category_id = ?");
        $stmt->execute([$catId]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($entries)) throw new Exception("Belum ada peserta.");

        usort($entries, function($a, $b) { return timeToMs($a['entry_time']) - timeToMs($b['entry_time']); });

        $N = count($entries);
        $H = ceil($N / $L);
        $heatsSwimmers = array_fill(1, $H, []);

        // --- 3. CORE ENGINE LOGIC ---
        if ($system == 'Langsung Final') {
            // MODE: WATERFALL (Tercepat kumpul di Seri Akhir, Seri 1 Minimal 3)
            $counts = array_fill(1, $H, $L);
            $toRemove = ($H * $L) - $N;
            for ($i = 1; $i <= $H; $i++) {
                $canRemove = $counts[$i] - 3; 
                if ($canRemove > 0) { $remove = min($toRemove, $canRemove); $counts[$i] -= $remove; $toRemove -= $remove; }
                if ($toRemove <= 0) break;
            }
            if ($toRemove > 0) {
                for ($i = $H; $i >= 1; $i--) {
                    $remove = min($toRemove, $counts[$i] - 1);
                    $counts[$i] -= $remove; $toRemove -= $remove;
                    if ($toRemove <= 0) break;
                }
            }
            $offset = 0;
            for ($i = $H; $i >= 1; $i--) {
                $heatsSwimmers[$i] = array_slice($entries, $offset, $counts[$i]);
                $offset += $counts[$i];
            }
        } else {
            // MODE: PENYISIHAN (World Aquatics Serpentine Seeding)
            if ($H <= 1) {
                $heatsSwimmers[1] = $entries;
            } elseif ($H == 2) {
                foreach ($entries as $idx => $sw) ($idx % 2 == 0) ? $heatsSwimmers[2][]=$sw : $heatsSwimmers[1][]=$sw;
            } else {
                $hTrack = $H;
                foreach ($entries as $sw) {
                    $heatsSwimmers[$hTrack][] = $sw;
                    $hTrack--; if ($hTrack < ($H - 2)) $hTrack = $H;
                }
            }
        }

        // --- 4. SIMPAN KE DATABASE ---
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM race_heats WHERE category_id = ?")->execute([$catId]);
        $laneOrder = getLaneOrder($L);

        foreach ($heatsSwimmers as $heatNo => $swimmersInHeat) {
            if (empty($swimmersInHeat)) continue;
            usort($swimmersInHeat, function($a, $b) { return timeToMs($a['entry_time']) - timeToMs($b['entry_time']); });
            $insH = $pdo->prepare("INSERT INTO race_heats (category_id, heat_number) VALUES (?, ?)");
            $insH->execute([$catId, $heatNo]);
            $heatId = $pdo->lastInsertId();
            foreach ($swimmersInHeat as $k => $sw) {
                if (isset($laneOrder[$k])) {
                    $pdo->prepare("INSERT INTO race_lines (heat_id, lane_number, swimmer_id, entry_time) VALUES (?, ?, ?, ?)")
                        ->execute([$heatId, $laneOrder[$k], $sw['swimmer_id'], $sw['entry_time']]);
                }
            }
        }
        $pdo->commit();
        header("Location: index.php?category_id=$catId&msg=success");
    } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); die($e->getMessage()); }
}