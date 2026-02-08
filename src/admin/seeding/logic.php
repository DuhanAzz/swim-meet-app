<?php
// FILE: src/admin/seeding/logic.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { die("Akses Ditolak"); }

$eventId = $_GET['category_id'] ?? 0;
if ($eventId == 0) die("Error: ID Kategori tidak ditemukan.");

// --- FUNGSI BANTUAN KONVERSI WAKTU ---
function timeToMs($timeStr) {
    $cleanStr = str_replace([':', ' '], '.', trim($timeStr));
    if (empty($cleanStr) || strpos($cleanStr, '99') === 0 || strtoupper($cleanStr) == 'NT') {
        return 999999999; // NT = Angka Besar
    }
    $parts = explode('.', $cleanStr);
    $menit = 0; $detik = 0; $mili = 0;
    
    if (count($parts) >= 3) {
        $menit = (int)$parts[0]; $detik = (int)$parts[1]; $mili = (int)$parts[2];
    } elseif (count($parts) == 2) {
        $detik = (int)$parts[0]; $mili = (int)$parts[1];
    } else {
        $detik = (int)$parts[0];
    }
    return ($menit * 60000) + ($detik * 1000) + ($mili * 10); 
}

try {
    $pdo->beginTransaction();

    // 1. AMBIL INFO EVENT & LANE COUNT
    // Kita perlu tahu apakah ini kategori "OPEN" atau tidak dari nama age_group
    $stmtCheck = $pdo->prepare("
        SELECT en.id, en.age_group, e.lane_count 
        FROM event_numbers en
        JOIN events e ON en.organizer_id = e.id
        WHERE en.id = ?
    ");
    $stmtCheck->execute([$eventId]);
    $info = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$info) throw new Exception("Data nomor lomba tidak valid");

    $LANE_COUNT = !empty($info['lane_count']) ? (int)$info['lane_count'] : 8;
    
    // Cek apakah kategori OPEN? (Case Insensitive)
    // Jika nama grup mengandung kata 'OPEN', flag true
    $isOpenCategory = (stripos($info['age_group'], 'OPEN') !== false);

    // 2. PRIORITAS LINTASAN (SPEARHEAD)
    $lanePriority = ($LANE_COUNT == 6) ? [3, 4, 2, 5, 1, 6] : [4, 5, 3, 6, 2, 7, 1, 8];

    // 3. AMBIL DATA ATLET
    // Kita butuh 'tanggal_lahir' untuk logika sorting OPEN
    $stmt = $pdo->prepare("
        SELECT ee.id, ee.entry_time, s.tanggal_lahir 
        FROM event_entries ee
        JOIN swimmers s ON ee.swimmer_id = s.id
        WHERE ee.category_id = ? AND ee.status = 'Approved'
    ");
    $stmt->execute([$eventId]);
    $swimmers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalSwimmers = count($swimmers);

    if ($totalSwimmers > 0) {
        
        // 4. HITUNG MS
        foreach ($swimmers as &$s) {
            $s['ms'] = timeToMs($s['entry_time']);
        }
        unset($s);

        // 5. SORTING (LOGIKA UTAMA)
        usort($swimmers, function($a, $b) use ($isOpenCategory) {
            // A. Cek Waktu Dulu
            if ($a['ms'] != $b['ms']) {
                return ($a['ms'] < $b['ms']) ? -1 : 1; // Waktu Kecil (Cepat) -> Diatas
            }

            // B. Jika Waktu SAMA (Biasanya kasus sesama NT/999999999)
            // Dan Kategori adalah OPEN
            if ($isOpenCategory && $a['ms'] == 999999999) {
                // Urutkan berdasarkan UMUR (TUA ditaruh lebih atas/mendekati seri cepat)
                // Tanggal lahir LEBIH KECIL = LEBIH TUA
                if ($a['tanggal_lahir'] != $b['tanggal_lahir']) {
                    return ($a['tanggal_lahir'] < $b['tanggal_lahir']) ? -1 : 1;
                }
            }

            return 0;
        });

        // 6. BAGI SERI (CHUNKS)
        $chunks = array_chunk($swimmers, $LANE_COUNT);
        $totalHeats = count($chunks);

        // 7. RE-BALANCE (Jika seri terakhir < 3 orang)
        if ($totalHeats > 1) {
            $lastChunkIndex = $totalHeats - 1; 
            $countLast = count($chunks[$lastChunkIndex]);
            
            if ($countLast < 3) {
                $needed = 3 - $countLast;
                $donorIndex = $lastChunkIndex - 1;
                // Ambil dari belakang donor (paling lambat di grup cepat)
                $movers = array_splice($chunks[$donorIndex], -$needed);
                // Taruh di depan recipient (paling cepat di grup lambat)
                $chunks[$lastChunkIndex] = array_merge($movers, $chunks[$lastChunkIndex]);
            }
        }

        // 8. SIMPAN HASIL KE TABEL event_seeding
        foreach ($chunks as $i => $batchSwimmers) {
            // Chunk 0 (Tercepat/Tertua di NT) -> Heat Terbesar
            $heatNumber = $totalHeats - $i; 

            foreach ($batchSwimmers as $rank => $swimmer) {
                $lane = $lanePriority[$rank] ?? 0;
                
                if ($lane > 0) {
                    // Cek Insert/Update
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
    echo "Sukses: $totalSwimmers atlet diproses.";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
?>