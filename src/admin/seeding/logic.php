<?php
// src/admin/seeding/process_seeding.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// CEK KEAMANAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$catId = $_GET['category_id'] ?? 0;
if ($catId == 0) die("Error: Kategori lomba tidak ditemukan.");

try {
    $pdo->beginTransaction();

    // 1. AMBIL JUMLAH LINTASAN DARI TABEL EVENTS
    $stmtConfig = $pdo->prepare("
        SELECT e.lane_count 
        FROM event_numbers en
        JOIN events e ON en.organizer_id = e.id
        WHERE en.id = ?
    ");
    $stmtConfig->execute([$catId]);
    $config = $stmtConfig->fetch(PDO::FETCH_ASSOC);
    $LANE_COUNT = !empty($config['lane_count']) ? (int)$config['lane_count'] : 8;

    // 2. TENTUKAN URUTAN PRIORITAS LINTASAN (SPEARHEAD)
    $lanePriority = [];
    switch ($LANE_COUNT) {
        case 4: $lanePriority = [2, 3, 1, 4]; break;
        case 5: $lanePriority = [3, 2, 4, 1, 5]; break;
        case 6: $lanePriority = [3, 4, 2, 5, 1, 6]; break;
        case 8: $lanePriority = [4, 5, 3, 6, 2, 7, 1, 8]; break;
        case 10: $lanePriority = [5, 6, 4, 7, 3, 8, 2, 9, 1, 10]; break;
        default: 
            // Default 8 lintasan jika aneh
            $lanePriority = [4, 5, 3, 6, 2, 7, 1, 8]; 
    }

    // 3. RESET SEEDING LAMA
    $pdo->prepare("UPDATE event_entries SET heat = NULL, lane = NULL WHERE category_id = ?")->execute([$catId]);

    // 4. AMBIL DATA PESERTA (URUTKAN: TERCEPAT -> TERLAMBAT)
    // Penting: NT/Null ditaruh di paling belakang
    $sqlSwimmers = "
        SELECT id, entry_time 
        FROM event_entries 
        WHERE category_id = ?
        ORDER BY 
            CASE 
                WHEN entry_time IS NULL OR entry_time = '' OR entry_time = '00:00.00' OR entry_time = '99:99.99' THEN 1 
                ELSE 0 
            END ASC,
            entry_time ASC
    ";
    $stmt = $pdo->prepare($sqlSwimmers);
    $stmt->execute([$catId]);
    $swimmers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalSwimmers = count($swimmers);

    if ($totalSwimmers > 0) {
        
        // 5. BAGI KE DALAM SERI (CHUNKS) AWAL
        // Chunk 0 = Tercepat, Chunk Terakhir = Terlambat
        $chunks = array_chunk($swimmers, $LANE_COUNT);
        
        // 6. LOGIKA BARU: RE-BALANCE JIKA SERI TERAKHIR (TERLAMBAT) KURANG DARI 3
        // Kita hanya melakukan ini jika jumlah seri lebih dari 1
        $totalHeats = count($chunks);
        
        if ($totalHeats > 1) {
            $lastChunkIndex = $totalHeats - 1; // Index seri terlambat (Seri 1)
            $countLast = count($chunks[$lastChunkIndex]);
            
            // Jika seri terakhir isinya < 3 orang
            if ($countLast < 3) {
                // Hitung berapa yang perlu ditarik (agar jadi 3)
                $needed = 3 - $countLast; 
                
                // Ambil dari seri sebelumnya (Seri 2 / Next Fastest)
                // Kita ambil dari bagian "belakang" seri sebelumnya (karena itu yang paling lambat di grup cepat)
                $donorIndex = $lastChunkIndex - 1;
                
                // Pastikan donor punya cukup orang (minimal sisa 3 juga, atau ambil secukupnya)
                // Tapi aturan mainnya biasanya kita paksa tarik biar Seri 1 jadi 3.
                
                $movers = array_splice($chunks[$donorIndex], -$needed);
                
                // Masukkan ke DEPAN seri terakhir (karena movers lebih cepat dari yang ada di seri terakhir)
                $chunks[$lastChunkIndex] = array_merge($movers, $chunks[$lastChunkIndex]);
            }
        }

        // 7. INPUT KE DATABASE
        // Loop chunks yang sudah diperbaiki posisinya
        $stmtUpdate = $pdo->prepare("UPDATE event_entries SET heat = ?, lane = ? WHERE id = ?");

        foreach ($chunks as $i => $batchSwimmers) {
            // Hitung Nomor Seri
            // Chunk 0 (Tercepat) -> Dapat Heat Tertinggi (Misal Heat 3)
            // Chunk Terakhir (Terlambat) -> Dapat Heat 1
            $heatNumber = $totalHeats - $i; 

            // Assign Lintasan (Spearhead)
            foreach ($batchSwimmers as $rank => $swimmer) {
                $lane = $lanePriority[$rank] ?? 0;
                if ($lane > 0) {
                    $stmtUpdate->execute([$heatNumber, $lane, $swimmer['id']]);
                }
            }
        }
    }

    $pdo->commit();
    header("Location: view_startlist.php?event_id=" . $catId . "&msg=success");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error: " . $e->getMessage());
}
?>