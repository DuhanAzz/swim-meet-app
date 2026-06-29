<?php
require_once __DIR__ . '/src/config/database.php';

// Simulate what sync.php does
$testData = [
    ['id' => 10, 'time' => '01:23.45'], // Assume entry_id 10 exists
];

// Let's first find a valid entry_id from the database
$stmt = $pdo->query("SELECT entry_id FROM event_seeding LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $entryId = $row['entry_id'];
    $testData[0]['id'] = $entryId;
    
    echo "Testing update for entry_id: $entryId\n";
    
    // Simulate the exact code in update_results
    $sql = "UPDATE event_seeding SET time_final = :waktu WHERE entry_id = :id";
    $stmtUpd = $pdo->prepare($sql);
    $count = 0;
    foreach ($testData as $r) {
        if (!empty($r['id']) && !empty($r['time'])) {
            $timeStr = trim($r['time']);
            $standardTimeStr = str_replace(':', '.', $timeStr);
            $firstColonPos = strpos($timeStr, ':');
            if ($firstColonPos !== false) {
                $standardTimeStr = substr_replace($standardTimeStr, ':', $firstColonPos, 1);
            }
            
            if ($stmtUpd->execute(['waktu' => $standardTimeStr, 'id' => $r['id']])) {
                $count++;
            }
        }
    }
    
    echo "Update executed. Count: $count\n";
    
    // Verify in database
    $stmtVer = $pdo->prepare("SELECT time_final FROM event_seeding WHERE entry_id = ?");
    $stmtVer->execute([$entryId]);
    $verRow = $stmtVer->fetch(PDO::FETCH_ASSOC);
    echo "Value in database after update: " . ($verRow['time_final'] ?? 'NULL') . "\n";
} else {
    echo "No data in event_seeding table.\n";
}
