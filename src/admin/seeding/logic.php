<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

// Ambil ID (Support 'event_id' dari link baru atau 'category_id' dari link lama)
$cat_id = $_GET['event_id'] ?? ($_GET['category_id'] ?? 0);

if ($cat_id == 0) {
    die("Event ID / Category ID tidak valid.");
}

try {
    $uid = $_SESSION['user_id'];
    
    // 1. AMBIL KONFIGURASI KOLAM
    $stmtSet = $pdo->prepare("SELECT lane_count FROM users WHERE id = ?");
    $stmtSet->execute([$uid]);
    $config = $stmtSet->fetch();
    $total_lanes = (int)($config['lane_count'] ?: 8); // Default 8 lintasan

    // 2. AMBIL DATA ENTRIES (HANYA YANG LUNAS)
    $sql = "SELECT ee.id, ee.entry_time 
            FROM event_entries ee 
            LEFT JOIN payments p ON p.user_id = ee.club_id
            WHERE ee.category_id = ? 
            AND (p.status = 'Paid' OR p.status = 'Verified')
            ORDER BY 
                -- Prioritaskan yang punya waktu valid (ASC)
                -- NT/Kosong/99:99.99 taruh paling belakang
                CASE 
                    WHEN ee.entry_time IS NULL OR ee.entry_time = '' OR ee.entry_time = '99:99.99' OR ee.entry_time = 'NT' THEN 1 
                    ELSE 0 
                END ASC,
                ee.entry_time ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cat_id]);
    $swimmers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_swimmers = count($swimmers);

    if ($total_swimmers == 0) {
        $pdo->prepare("UPDATE event_entries SET heat = NULL, lane = NULL WHERE category_id = ?")->execute([$cat_id]);
        header("Location: index.php?msg=empty_data"); exit;
    }

    // ==========================================================
    // 3. LOGIKA PEMBAGIAN SERI (DISTRIBUSI MERATA / FINA RULE)
    // ==========================================================
    
    // Hitung jumlah seri yang dibutuhkan
    $num_heats = ceil($total_swimmers / $total_lanes);
    
    // Siapkan array untuk menampung jumlah atlet per seri
    $heats_distribution = array_fill(0, $num_heats, 0);

    // LOGIKA PERBAIKAN:
    // Bagi rata jumlah atlet ke semua seri.
    // Sisa pembagian (modulo) akan ditambahkan ke seri-seri terakhir (tercepat).
    
    $base_count = floor($total_swimmers / $num_heats); // Jumlah dasar per seri
    $remainder  = $total_swimmers % $num_heats;        // Sisa yang harus disebar
    
    // Loop mengisi setiap seri
    for ($i = 0; $i < $num_heats; $i++) {
        $count = $base_count;
        
        // Distribusikan sisa ke seri-seri TERAKHIR (Seri Cepat)
        // Contoh: 3 Seri, Sisa 2. Maka Seri 2 dan Seri 3 dapat tambahan +1. Seri 1 tidak.
        // Index $i dimulai dari 0 (Seri 1).
        if ($i >= ($num_heats - $remainder)) {
            $count++;
        }
        
        $heats_distribution[$i] = $count;
    }

    // ==========================================================
    // 4. GENERATE POLA LINTASAN (SPEARHEAD / ZIG-ZAG)
    // ==========================================================
    
    $mid = ceil($total_lanes / 2); // Tengah (misal 8 lane -> 4)
    $lane_order = [];
    $lane_order[] = $mid; // Seed 1 (Tercepat)
    
    for ($i = 1; $i < $total_lanes; $i++) {
        if ($i % 2 != 0) {
            $lane_order[] = $mid + ceil($i/2); // Kanan
        } else {
            $lane_order[] = $mid - ($i/2); // Kiri
        }
    }
    // Filter lane valid
    $lane_order = array_values(array_filter($lane_order, function($l) use ($total_lanes) {
        return $l > 0 && $l <= $total_lanes;
    }));

    // ==========================================================
    // 5. UPDATE DATABASE
    // ==========================================================
    
    $pdo->beginTransaction();

    // Reset Data Lama
    $stmtReset = $pdo->prepare("UPDATE event_entries SET heat = NULL, lane = NULL WHERE category_id = ?");
    $stmtReset->execute([$cat_id]);

    $stmtUpdate = $pdo->prepare("UPDATE event_entries SET heat = ?, lane = ? WHERE id = ?");

    // $swimmers[0] adalah Tercepat.
    // Kita isi mulai dari Seri Tercepat (Heat Terakhir) mundur ke Seri 1
    
    $swimmer_idx = 0;

    // Loop dari Heat Terakhir (Final) ke Heat 1
    // Index array $heats_distribution: 0 = Seri 1, (N-1) = Seri Final
    for ($h = $num_heats - 1; $h >= 0; $h--) {
        $heat_number = $h + 1; // Konversi index 0 jadi Heat 1
        $count_needed = $heats_distribution[$h]; // Berapa orang di seri ini

        // Ambil atlet untuk seri ini
        $batch = array_slice($swimmers, $swimmer_idx, $count_needed);
        $swimmer_idx += $count_needed;

        // Assign Lintasan (Zig-Zag)
        foreach ($batch as $k => $atlet) {
            if (isset($lane_order[$k])) {
                $assigned_lane = $lane_order[$k];
                $stmtUpdate->execute([$heat_number, $assigned_lane, $atlet['id']]);
            }
        }
    }

    $pdo->commit();
    
    header("Location: view_startlist.php?event_id=" . $cat_id . "&msg=seeded");
    exit;

} catch (Exception $e) { 
    if ($pdo->inTransaction()) $pdo->rollBack(); 
    die("Error Logic: " . $e->getMessage()); 
}
?>