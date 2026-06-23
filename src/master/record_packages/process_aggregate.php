<?php
// FILE: src/master/record_packages/process_aggregate.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Proteksi akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php"); exit;
}

$packageName = trim($_POST['package_name'] ?? '');
$sourceIds = $_POST['source_event_ids'] ?? [];

if (empty($packageName) || empty($sourceIds)) {
    $_SESSION['flash_message'] = "Nama paket dan minimal 1 event historis wajib diisi!";
    $_SESSION['flash_type'] = "error";
    header("Location: create.php"); exit;
}

function timeToMs($time) {
    $time = trim($time);
    if (empty($time) || $time == 'NT' || $time == '99:99.99' || $time == '-') return 9999999999; 
    $parts = preg_split('/[:.]/', $time);
    $menit = 0; $detik = 0; $ms = 0;
    if (count($parts) == 3) { $menit = (int)$parts[0]; $detik = (int)$parts[1]; $ms = (int)$parts[2]; } 
    elseif (count($parts) == 2) { $detik = (int)$parts[0]; $ms = (int)$parts[1]; } 
    elseif (count($parts) == 1) { $detik = (int)$parts[0]; }
    return ($menit * 60000) + ($detik * 1000) + ($ms * 10);
}

try {
    $pdo->beginTransaction();

    // 1. Buat Header Paket
    $stmtPkg = $pdo->prepare("INSERT INTO record_packages (package_name) VALUES (?)");
    $stmtPkg->execute([$packageName]);
    $packageId = $pdo->lastInsertId();

    // Siapkan placeholder untuk IN()
    $inQuery = implode(',', array_fill(0, count($sourceIds), '?'));

    // 2. Tarik semua catatan waktu valid dari event-event yang dipilih
    $sqlTimes = "
        SELECT 
            en.distance, en.stroke, en.jenis_kelamin, en.age_group,
            s.nama_atlet, es.time_final,
            e.id as source_event_id
        FROM event_seeding es
        JOIN event_entries ee ON es.entry_id = ee.id
        JOIN event_numbers en ON ee.category_id = en.id
        JOIN events e ON en.event_id = e.id
        JOIN swimmers s ON ee.swimmer_id = s.id
        WHERE en.event_id IN ($inQuery) 
          AND (es.is_dq_final = 0 OR es.is_dq_final IS NULL)
          AND es.time_final IS NOT NULL
          AND es.time_final != ''
          AND es.time_final != 'NT'
    ";
    
    $stmtTimes = $pdo->prepare($sqlTimes);
    $stmtTimes->execute($sourceIds);
    $allResults = $stmtTimes->fetchAll(PDO::FETCH_ASSOC);

    // 3. Kalkulasi MS di PHP dan Filter yang tercepat
    $bestRecords = [];
    foreach ($allResults as $row) {
        $ms = timeToMs($row['time_final']);
        $row['time_final_ms'] = $ms;
        $key = $row['distance'] . '_' . $row['stroke'] . '_' . $row['jenis_kelamin'] . '_' . $row['age_group'];
        
        if (!isset($bestRecords[$key])) {
            $bestRecords[$key] = $row;
        } else {
            if ($ms < $bestRecords[$key]['time_final_ms']) {
                $bestRecords[$key] = $row; // Timpa jika ada yang lebih cepat
            }
        }
    }

    // 4. Proteksi jika tidak ada data sama sekali
    if (empty($bestRecords)) {
        throw new Exception("Event yang dipilih tidak memiliki satupun atlet dengan catatan waktu final yang valid. Paket tidak dibuat.");
    }

    // 5. Insert agregasi ke event_historical_records
    $sqlInsert = "INSERT INTO event_historical_records 
        (package_id, source_event_id, distance, stroke, jenis_kelamin, age_group, holder_name, record_time, record_time_ms) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtInsert = $pdo->prepare($sqlInsert);

    foreach ($bestRecords as $rec) {
        $stmtInsert->execute([
            $packageId,
            $rec['source_event_id'],
            $rec['distance'],
            $rec['stroke'],
            $rec['jenis_kelamin'],
            $rec['age_group'],
            $rec['nama_atlet'],
            $rec['time_final'],
            $rec['time_final_ms']
        ]);
    }

    $pdo->commit();

    $_SESSION['flash_message'] = "Paket Rekor berhasil dibuat! Total " . count($bestRecords) . " rekor diagregasi.";
    $_SESSION['flash_type'] = "success";
    header("Location: index.php"); exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = "Terjadi kesalahan sistem: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
    header("Location: create.php"); exit;
}
