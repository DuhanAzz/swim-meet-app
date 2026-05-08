<?php
// FILE: src/admin/seeding/logic.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    die("Akses Ditolak"); 
}

$eventId = $_GET['category_id'] ?? 0;
if ($eventId == 0) die("Error: ID Kategori tidak ditemukan.");


// =====================================================
// 🔧 FUNGSI KONVERSI WAKTU
// =====================================================
function timeToMs($timeStr) {
    $cleanStr = str_replace([':', ' '], '.', trim($timeStr));
    
    if (empty($cleanStr) || strpos($cleanStr, '99') === 0 || strtoupper($cleanStr) == 'NT') {
        return 999999999;
    }

    $parts = explode('.', $cleanStr);
    $menit = 0; $detik = 0; $mili = 0;
    
    if (count($parts) >= 3) {
        $menit = (int)$parts[0]; 
        $detik = (int)$parts[1]; 
        $mili = (int)$parts[2];
    } elseif (count($parts) == 2) {
        $detik = (int)$parts[0]; 
        $mili = (int)$parts[1];
    } else {
        $detik = (int)$parts[0];
    }

    return ($menit * 60000) + ($detik * 1000) + ($mili * 10); 
}


// =====================================================
// 🏊 FUNGSI URUTAN LANE (ZIG-ZAG STANDARD)
// =====================================================
function getLaneOrder($total_lane) {
    $center = ceil($total_lane / 2);
    $lanes = [$center];

    for ($i = 1; count($lanes) < $total_lane; $i++) {
        if ($i % 2 == 1) {
            $next = $center + ceil($i / 2);
        } else {
            $next = $center - ($i / 2);
        }

        if ($next >= 1 && $next <= $total_lane) {
            $lanes[] = $next;
        }
    }

    return $lanes;
}


try {
    $pdo->beginTransaction();

    // =====================================================
    // 1. AMBIL INFO EVENT
    // =====================================================
    $stmtCheck = $pdo->prepare("
        SELECT en.id, en.age_group, e.lane_count 
        FROM event_numbers en
        JOIN events e ON en.event_id = e.id
        WHERE en.id = ?
    ");
    $stmtCheck->execute([$eventId]);
    $info = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$info) throw new Exception("Data nomor lomba tidak valid");

    $LANE_COUNT = !empty($info['lane_count']) ? (int)$info['lane_count'] : 8;

    if ($LANE_COUNT <= 0) {
        throw new Exception("Lane count tidak valid");
    }

    $isOpenCategory = (stripos($info['age_group'], 'OPEN') !== false);

    // =====================================================
    // 2. GENERATE LANE PRIORITY DINAMIS
    // =====================================================
    $lanePriority = getLaneOrder($LANE_COUNT);


    // =====================================================
    // 3. AMBIL DATA ATLET
    // =====================================================
    $stmt = $pdo->prepare("
        SELECT ee.id, ee.entry_time, s.tanggal_lahir 
        FROM event_entries ee
        JOIN swimmers s ON ee.swimmer_id = s.id
        WHERE ee.category_id = ?
    ");
    $stmt->execute([$eventId]);
    $swimmers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalSwimmers = count($swimmers);

    if ($totalSwimmers > 0) {

        // =====================================================
        // 4. HITUNG MS
        // =====================================================
        foreach ($swimmers as &$s) {
            $s['ms'] = timeToMs($s['entry_time']);
        }
        unset($s);

        // =====================================================
        // 5. SORTING (FASTEST FIRST)
        // =====================================================
        usort($swimmers, function($a, $b) use ($isOpenCategory) {
            if ($a['ms'] != $b['ms']) {
                return ($a['ms'] < $b['ms']) ? -1 : 1;
            }

            if ($isOpenCategory && $a['ms'] == 999999999) {
                if ($a['tanggal_lahir'] != $b['tanggal_lahir']) {
                    return ($a['tanggal_lahir'] < $b['tanggal_lahir']) ? -1 : 1;
                }
            }

            return 0;
        });

        // =====================================================
        // 🔥 6. HITUNG HEAT IDEAL (ANTI 1-2 ORANG)
        // =====================================================
        $totalHeats = ceil($totalSwimmers / $LANE_COUNT);
        $idealPerHeat = ceil($totalSwimmers / $totalHeats);

        $chunks = [];
        $index = 0;

        for ($i = 0; $i < $totalHeats; $i++) {
            $chunks[$i] = array_slice($swimmers, $index, $idealPerHeat);
            $index += $idealPerHeat;
        }

        // =====================================================
        // 🔥 7. SAFETY: MINIMAL 3 ORANG DI HEAT TERAKHIR
        // =====================================================
        if ($totalHeats > 1) {
            $lastIndex = $totalHeats - 1;
            if (count($chunks[$lastIndex]) < 3) {
                $need = 3 - count($chunks[$lastIndex]);
                $donor = $lastIndex - 1;

                $move = array_splice($chunks[$donor], -$need);
                $chunks[$lastIndex] = array_merge($move, $chunks[$lastIndex]);
            }
        }

        // =====================================================
        // 🔥 8. ASSIGN LANE + SIMPAN
        // =====================================================
        foreach ($chunks as $i => $batchSwimmers) {

            // heat dibalik → fastest di heat terakhir
            $heatNumber = $totalHeats - $i;

            // 🔥 hanya ambil lane sesuai jumlah peserta (centered)
            $usedLane = array_slice($lanePriority, 0, count($batchSwimmers));

            foreach ($batchSwimmers as $rank => $swimmer) {

                $lane = $usedLane[$rank] ?? 0;

                if ($lane > 0) {

                    $chk = $pdo->prepare("SELECT id FROM event_seeding WHERE entry_id = ?");
                    $chk->execute([$swimmer['id']]);
                    
                    if ($chk->rowCount() > 0) {

                        $upd = $pdo->prepare("
                            UPDATE event_seeding 
                            SET heat_prelim = ?, lane_prelim = ?, time_prelim = ?, time_prelim_ms = ? 
                            WHERE entry_id = ?
                        ");

                        $upd->execute([
                            $heatNumber, 
                            $lane, 
                            $swimmer['entry_time'], 
                            $swimmer['ms'], 
                            $swimmer['id']
                        ]);

                    } else {

                        $ins = $pdo->prepare("
                            INSERT INTO event_seeding 
                            (entry_id, heat_prelim, lane_prelim, time_prelim, time_prelim_ms) 
                            VALUES (?, ?, ?, ?, ?)
                        ");

                        $ins->execute([
                            $swimmer['id'], 
                            $heatNumber, 
                            $lane, 
                            $swimmer['entry_time'], 
                            $swimmer['ms']
                        ]);
                    }
                }
            }
        }
    }

    $pdo->commit();

    header("Location: view_startlist.php?category_id=" . $eventId);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error Database Seeding: " . $e->getMessage());
}
?>