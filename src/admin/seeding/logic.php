<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$cat_id = $_GET['category_id'] ?? 0;
$admin_id = $_SESSION['user_id'];

try {
    // 1. AMBIL KONFIGURASI DAN EVENT ID
    $stmtEvent = $pdo->prepare("SELECT organizer_id FROM event_numbers WHERE id = ?");
    $stmtEvent->execute([$cat_id]);
    $eventInfo = $stmtEvent->fetch();
    $parentEventId = $eventInfo['organizer_id'] ?? 0;

    // Ambil konfigurasi jumlah lintasan admin
    $stmtSet = $pdo->prepare("SELECT lane_count FROM users WHERE id = ?");
    $stmtSet->execute([$admin_id]);
    $config = $stmtSet->fetch();
    $total_lanes = (int)($config['lane_count'] ?: 8);

    // 2. AMBIL DATA ENTRIES DENGAN PENGAMAN PEMBAYARAN
    // Revisi: JOIN harus spesifik pada user_id DAN event_id
    $sql = "SELECT ee.id, ee.entry_time, s.nama_atlet 
            FROM event_entries ee 
            JOIN swimmers s ON ee.swimmer_id = s.id
            LEFT JOIN payments p ON p.user_id = ee.club_id AND p.event_id = ee.event_id
            WHERE ee.category_id = ? 
            AND ee.event_id = ?
            AND (p.status = 'Paid' OR p.status = 'Verified') -- PENGAMAN UTAMA
            ORDER BY 
                CASE 
                    WHEN ee.entry_time IS NULL OR ee.entry_time = '' OR ee.entry_time = '99:99.99' OR ee.entry_time = 'NT' THEN 1 
                    ELSE 0 
                END ASC,
                ee.entry_time ASC,
                RAND() -- Jika waktu sama, acak (Draw)
            ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cat_id, $parentEventId]);
    $swimmers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_swimmers = count($swimmers);

    // Jika tidak ada data (atau tidak ada yang lunas), kembalikan error
    if ($total_swimmers == 0) {
        // Reset dulu biar bersih jika sebelumnya ada data
        $pdo->prepare("UPDATE event_entries SET heat = NULL, lane = NULL WHERE category_id = ?")->execute([$cat_id]);
        header("Location: view_startlist.php?event_id=" . $cat_id . "&msg=empty_or_unpaid"); 
        exit;
    }

    // 3. HITUNG JUMLAH SERI
    $num_heats = ceil($total_swimmers / $total_lanes);
    
    // Inisialisasi array seeding
    $final_seeding = [];
    for($h=1; $h<=$num_heats; $h++) { $final_seeding[$h] = []; }

    // Pola Lintasan (Spearhead)
    $lane_pattern = ($total_lanes == 8) ? [4, 5, 3, 6, 2, 7, 1, 8] : [3, 4, 2, 5, 1, 6]; 
    if ($total_lanes == 10) $lane_pattern = [4, 5, 3, 6, 2, 7, 1, 8, 0, 9];

    // 4. LOGIKA DISTRIBUSI FINA
    if ($num_heats <= 1) {
        // Satu Seri (Final Langsung)
        foreach ($swimmers as $k => $s) {
            $final_seeding[1][$lane_pattern[$k]] = $s['id'];
        }
    } else {
        $swimmer_idx = 0;
        $heats_to_circular = min($num_heats, 3);
        
        // Isi seri lambat dulu (jika lebih dari 3 seri)
        if ($num_heats > 3) {
            $slow_heats_count = $num_heats - 3;
            for ($h = 1; $h <= $slow_heats_count; $h++) {
                // Isi heat lambat
                for ($l = 0; $l < $total_lanes && $swimmer_idx < ($total_swimmers - ($heats_to_circular * 3)); $l++) {
                    $final_seeding[$h][$lane_pattern[$l]] = $swimmers[$total_swimmers - 1 - $swimmer_idx]['id'];
                    $swimmer_idx++;
                }
            }
            // Potong array swimmers untuk menyisakan yang Circular
            $swimmers = array_slice($swimmers, 0, $total_swimmers - $swimmer_idx);
        }

        // Circular Seeding (3 Seri Tercepat)
        $target_heats = [];
        for ($i = $num_heats; $i > ($num_heats - $heats_to_circular); $i--) {
            $target_heats[] = $i;
        }

        $lane_indices = array_fill(0, $num_heats + 1, 0); // Reset index lintasan per heat
        foreach ($swimmers as $k => $s) {
            $heat_idx = $k % $heats_to_circular;
            $current_heat = $target_heats[$heat_idx];
            
            // Pastikan tidak melebihi kapasitas lintasan
            if ($lane_indices[$current_heat] < count($lane_pattern)) {
                $l_idx = $lane_indices[$current_heat];
                $final_seeding[$current_heat][$lane_pattern[$l_idx]] = $s['id'];
                $lane_indices[$current_heat]++;
            }
        }
    }

    // 5. SIMPAN KE DATABASE
    $pdo->beginTransaction();
    
    // Reset Data Lama untuk Event ini saja
    $pdo->prepare("UPDATE event_entries SET heat = NULL, lane = NULL WHERE category_id = ? AND event_id = ?")
        ->execute([$cat_id, $parentEventId]);

    $stmtUpdate = $pdo->prepare("UPDATE event_entries SET heat = ?, lane = ? WHERE id = ?");
    
    foreach ($final_seeding as $h => $lanes) {
        foreach ($lanes as $l => $sid) {
            $stmtUpdate->execute([$h, $l, $sid]);
        }
    }
    
    $pdo->commit();

    header("Location: view_startlist.php?event_id=" . $cat_id . "&msg=seeded");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Error Processing Seeding: " . $e->getMessage());
}
?>