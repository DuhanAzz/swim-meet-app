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
// 🎯 FUNGSI KONVERSI WAKTU
// =====================================================
function timeToMs($timeStr) {
    $cleanStr = str_replace([':', ' '], '.', trim($timeStr));
    
    if (empty($cleanStr) || strpos($cleanStr, '99') === 0 || strtoupper($cleanStr) == 'NT') {
        return 999999999; // NT / No Time ditaruh paling lambat
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
// 🎯 FUNGSI URUTAN LANE (ZIG-ZAG DINAMIS BERDASARKAN LINTASAN AKTIF)
// =====================================================
function getLaneOrder($activeLanes) {
    if (empty($activeLanes)) return [];
    
    // Pastikan array terurut nilainya dan indexnya berurutan dari 0
    sort($activeLanes);
    $activeLanes = array_values($activeLanes);
    $total_lane = count($activeLanes);
    
    $centerIdx = (int)ceil($total_lane / 2) - 1; // Index untuk center (0-based)
    
    // Lane pertama selalu yang di tengah
    $lanes = [$activeLanes[$centerIdx]];

    for ($i = 1; count($lanes) < $total_lane; $i++) {
        if ($i % 2 == 1) {
            $nextIdx = $centerIdx + (int)ceil($i / 2);
        } else {
            $nextIdx = $centerIdx - (int)($i / 2);
        }

        if (isset($activeLanes[$nextIdx])) {
            $lanes[] = $activeLanes[$nextIdx];
        }
    }

    return $lanes;
}


try {
    $pdo->beginTransaction();

    // =====================================================
    // 1. AMBIL INFO EVENT & LINTASAN AKTIF
    // =====================================================
    $stmtCheck = $pdo->prepare("
        SELECT en.id, en.age_group, e.lane_count, e.used_lanes 
        FROM event_numbers en
        JOIN events e ON en.event_id = e.id
        WHERE en.id = ?
    ");
    $stmtCheck->execute([$eventId]);
    $info = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$info) throw new Exception("Data nomor lomba tidak valid");

    $LANE_COUNT = !empty($info['lane_count']) ? (int)$info['lane_count'] : 8;
    
    // Logika active lanes
    $activeLanes = [];
    if (!empty($info['used_lanes'])) {
        $activeLanes = explode(',', $info['used_lanes']);
        // Bersihkan dan jadikan integer
        $activeLanes = array_map('trim', $activeLanes);
        $activeLanes = array_map('intval', $activeLanes);
    } else {
        // Fallback default 1 to lane_count
        for ($i = 1; $i <= $LANE_COUNT; $i++) {
            $activeLanes[] = $i;
        }
    }

    $active_lane_count = count($activeLanes);
    if ($active_lane_count <= 0) {
        throw new Exception("Tidak ada lintasan aktif untuk event ini");
    }

    $isOpenCategory = (stripos($info['age_group'], 'OPEN') !== false);

    // =====================================================
    // 2. GENERATE LANE PRIORITY DINAMIS
    // =====================================================
    $lanePriority = getLaneOrder($activeLanes);


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
        // 🚀 6. ALOKASI HEAT (STANDARD SEEDING RENANG)
        // =====================================================
        // Kapasitas setiap heat dibatasi oleh JUMLAH LINTASAN AKTIF
        $totalHeats = ceil($totalSwimmers / $active_lane_count);
        
        $heatSizes = [];
        $remaining = $totalSwimmers;
        
        // Isi heat dari yang tercepat (index 0 = Heat Final) sampai yang terlambat
        for ($i = 0; $i < $totalHeats; $i++) {
            if ($remaining >= $active_lane_count) {
                $heatSizes[] = $active_lane_count; // Isi penuh heat yang cepat
                $remaining -= $active_lane_count;
            } else {
                $heatSizes[] = $remaining; // Sisa perenang dibuang ke heat paling lambat
            }
        }

        // =====================================================
        // 🚀 7. SAFETY: MINIMAL 3 ORANG DI HEAT PERTAMA (TERLAMBAT)
        // =====================================================
        if ($totalHeats > 1) {
            $slowestIndex = $totalHeats - 1; // Index array untuk Heat ke-1
            $nextSlowestIndex = $totalHeats - 2; // Index array untuk Heat ke-2
            
            // Jika heat paling lambat isinya cuma 1 atau 2 orang, pinjam dari heat atasnya!
            if ($heatSizes[$slowestIndex] < 3 && $heatSizes[$slowestIndex] > 0) {
                $butuh = 3 - $heatSizes[$slowestIndex];
                
                // Pastikan setelah dipinjam, heat atasnya tetap punya minimal 3 orang
                if (($heatSizes[$nextSlowestIndex] - $butuh) >= 3) {
                    $heatSizes[$nextSlowestIndex] -= $butuh;
                    $heatSizes[$slowestIndex] += $butuh;
                }
            }
        }

        // Potong array perenang berdasarkan ukuran heat yang sudah dihitung
        $chunks = [];
        $offset = 0;
        foreach ($heatSizes as $size) {
            if ($size > 0) {
                $chunks[] = array_slice($swimmers, $offset, $size);
                $offset += $size;
            }
        }

        // =====================================================
        // 8. ASSIGN LANE + SIMPAN
        // =====================================================
        foreach ($chunks as $i => $batchSwimmers) {

            // Karena chunk[0] berisi atlet tercepat, dia dapat angka Heat paling besar (Seri Terakhir)
            $heatNumber = $totalHeats - $i;

            // Ambil urutan lane sebanyak jumlah perenang di heat tersebut (Center Out)
            $usedLane = array_slice($lanePriority, 0, count($batchSwimmers));

            foreach ($batchSwimmers as $rank => $swimmer) {

                $lane = $usedLane[$rank] ?? 0;

                if ($lane > 0) {

                    $chk = $pdo->prepare("SELECT id FROM event_seeding WHERE entry_id = ?");
                    $chk->execute([$swimmer['id']]);
                    
                    if ($chk->rowCount() > 0) {
                        $upd = $pdo->prepare("UPDATE event_seeding SET heat_prelim = ?, lane_prelim = ?, time_prelim = ?, time_prelim_ms = ? WHERE entry_id = ?");
                        $upd->execute([$heatNumber, $lane, $swimmer['entry_time'], $swimmer['ms'], $swimmer['id']]);
                    } else {
                        $ins = $pdo->prepare("INSERT INTO event_seeding (entry_id, heat_prelim, lane_prelim, time_prelim, time_prelim_ms) VALUES (?, ?, ?, ?, ?)");
                        $ins->execute([$swimmer['id'], $heatNumber, $lane, $swimmer['entry_time'], $swimmer['ms']]);
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