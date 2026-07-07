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

$creationMethod = $_POST['creation_method'] ?? 'aggregate';

if (empty($packageName)) {
    $_SESSION['flash_message'] = "Nama paket wajib diisi!";
    $_SESSION['flash_type'] = "error";
    header("Location: create.php"); exit;
}

if ($creationMethod === 'aggregate' && empty($sourceIds)) {
    $_SESSION['flash_message'] = "Minimal 1 event historis wajib diisi!";
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

    if ($creationMethod === 'csv') {
        // --- LOGIKA PARSING CSV ---
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File CSV gagal diunggah atau tidak ditemukan.");
        }
        $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            throw new Exception("Format file harus .csv");
        }

        $fileHandle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$fileHandle) {
            throw new Exception("Gagal membaca file CSV.");
        }

        $bestRecords = [];
        // State variables
        $currentDistance = '';
        $currentStroke = '';
        $currentGender = '';
        $currentAgeGroup = '';
        $inEventBlock = false;

        while (($row = fgetcsv($fileHandle, 1000, ",")) !== FALSE) {
            // Kolom teks biasanya ada di index 1 jika format ",Acara..."
            $cell = trim($row[1] ?? ($row[0] ?? ''));
            
            // 1. Deteksi Baris Header Acara
            if (stripos($cell, 'Acara') !== false && preg_match('/(\d+)\s*M\s*Gaya\s*([A-Za-z]+)\s*(Putra|Putri)\s*(.+)/i', $cell, $matches)) {
                $currentDistance = $matches[1]; // misal: 100
                $strokeRaw = strtoupper(trim($matches[2])); // misal: DADA
                
                // Normalisasi Gaya
                if ($strokeRaw == 'BEBAS') $currentStroke = 'Bebas';
                elseif ($strokeRaw == 'DADA') $currentStroke = 'Dada';
                elseif ($strokeRaw == 'KUPU' || $strokeRaw == 'KUPU-KUPU') $currentStroke = 'Kupu-kupu';
                elseif ($strokeRaw == 'PUNGGUNG') $currentStroke = 'Punggung';
                elseif ($strokeRaw == 'GANTI') $currentStroke = 'Ganti Ganti';
                else $currentStroke = ucfirst(strtolower($strokeRaw));

                $genderRaw = strtoupper(trim($matches[3]));
                $currentGender = ($genderRaw == 'PUTRA') ? 'M' : 'F';
                
                $currentAgeGroup = strtoupper(trim($matches[4])); // misal: SD, SMP

                $inEventBlock = true;
                continue;
            }

            // 2. Jika dalam blok Acara dan menemukan Rank 1
            if ($inEventBlock && (trim($row[1] ?? '') == '1' || trim($row[0] ?? '') == '1')) {
                // Sesuai contoh: ,Rank,Nama Atlet,Sekolah,Prestasi,Hasil
                // Index: 0=kosong, 1=Rank, 2=Nama, 3=Sekolah, 4=Prestasi, 5=Hasil
                // Jika format berbeda (tanpa koma awal), bisa jadi geser indexnya. Kita buat dinamis
                $rankIdx = (trim($row[1] ?? '') == '1') ? 1 : 0;
                $namaAtlet = trim($row[$rankIdx + 1] ?? '');
                $hasilWaktu = trim($row[$rankIdx + 4] ?? ''); // Sesuai urutan CSV

                if (!empty($namaAtlet) && !empty($hasilWaktu)) {
                    $ms = timeToMs($hasilWaktu);
                    
                    $bestRecords[] = [
                        'source_event_id' => NULL, // Tidak terikat ke event manapun karena dari CSV
                        'distance' => $currentDistance,
                        'stroke' => $currentStroke,
                        'jenis_kelamin' => $currentGender,
                        'age_group' => $currentAgeGroup,
                        'nama_atlet' => $namaAtlet,
                        'time_final' => $hasilWaktu,
                        'time_final_ms' => $ms
                    ];
                }
                
                // Setelah dapat rank 1, matikan block sampai ketemu acara berikutnya (opsional, tergantung asumsi ada rank 1 ganda/seri)
                $inEventBlock = false;
            }
        }
        fclose($fileHandle);

        if (empty($bestRecords)) {
            throw new Exception("Tidak ada rekor Rank 1 yang berhasil diekstrak dari file CSV.");
        }

    } elseif ($creationMethod === 'aggregate') {
        // --- LOGIKA AGREGASI HISTORIS LAMA ---
        // Siapkan placeholder untuk IN()
        $inQuery = implode(',', array_fill(0, count($sourceIds), '?'));

        // Tarik semua catatan waktu valid dari event-event yang dipilih
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

        // Kalkulasi MS di PHP dan Filter yang tercepat
        $bestRecordsDict = [];
        foreach ($allResults as $row) {
            $ms = timeToMs($row['time_final']);
            $row['time_final_ms'] = $ms;
            $key = $row['distance'] . '_' . $row['stroke'] . '_' . $row['jenis_kelamin'] . '_' . $row['age_group'];
            
            if (!isset($bestRecordsDict[$key])) {
                $bestRecordsDict[$key] = $row;
            } else {
                if ($ms < $bestRecordsDict[$key]['time_final_ms']) {
                    $bestRecordsDict[$key] = $row; // Timpa jika ada yang lebih cepat
                }
            }
        }
        $bestRecords = array_values($bestRecordsDict);

        // Proteksi jika tidak ada data sama sekali
        if (empty($bestRecords)) {
            throw new Exception("Event yang dipilih tidak memiliki satupun atlet dengan catatan waktu final yang valid. Paket tidak dibuat.");
        }
    } else {
        // Manual input
        $bestRecords = [];
    }

    // 5. Insert agregasi ke event_historical_records
    $sqlInsert = "INSERT INTO event_historical_records 
        (package_id, source_event_id, distance, stroke, jenis_kelamin, age_group, holder_name, record_time, record_time_ms) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtInsert = $pdo->prepare($sqlInsert);

    foreach ($bestRecords as $rec) {
        $stmtInsert->execute([
            $packageId,
            $rec['source_event_id'] ?? NULL,
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

    $_SESSION['flash_message'] = "Paket Rekor berhasil dibuat! Total " . count($bestRecords) . " rekor disimpan.";
    $_SESSION['flash_type'] = "success";
    
    // Redirect if manual
    if ($creationMethod === 'manual') {
        header("Location: view.php?id=" . $packageId); exit;
    } else {
        header("Location: index.php"); exit;
    }

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = "Terjadi kesalahan sistem: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
    header("Location: create.php"); exit;
}
