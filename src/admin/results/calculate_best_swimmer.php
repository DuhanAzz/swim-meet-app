<?php
// FILE: src/admin/results/calculate_best_swimmer.php

function getBestSwimmerRanking($pdo, $event_id) {
    // 1. Ambil semua atlet yang mendapatkan medali di event ini
    $sql = "SELECT 
                s.id AS swimmer_id, 
                s.nama_atlet,
                SUM(CASE WHEN es.rank_final = 1 THEN 1 ELSE 0 END) as emas,
                SUM(CASE WHEN es.rank_final = 2 THEN 1 ELSE 0 END) as perak,
                SUM(CASE WHEN es.rank_final = 3 THEN 1 ELSE 0 END) as perunggu
            FROM swimmers s
            JOIN event_entries ee ON s.id = ee.swimmer_id
            JOIN event_numbers en ON ee.category_id = en.id
            JOIN event_seeding es ON ee.id = es.entry_id
            WHERE en.event_id = ? AND es.rank_final IN (1,2,3) AND es.is_dq_final = 0
            GROUP BY s.id
            ORDER BY emas DESC, perak DESC, perunggu DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$event_id]);
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Evaluasi aturan ketajaman rekor jika medali sama persis
    for ($i = 0; $i < count($athletes) - 1; $i++) {
        for ($j = $i + 1; $j < count($athletes); $j++) {
            
            if ($athletes[$i]['emas'] == $athletes[$j]['emas'] && 
                $athletes[$i]['perak'] == $athletes[$j]['perak'] && 
                $athletes[$i]['perunggu'] == $athletes[$j]['perunggu']) {
                
                $ketajamanI = calculateTotalSharpness($pdo, $event_id, $athletes[$i]['swimmer_id']);
                $ketajamanJ = calculateTotalSharpness($pdo, $event_id, $athletes[$j]['swimmer_id']);
                
                if ($ketajamanJ > $ketajamanI) {
                    $temp = $athletes[$i];
                    $athletes[$i] = $athletes[$j];
                    $athletes[$j] = $temp;
                }
            }
        }
    }
    return $athletes;
}

function timeToSeconds($timeStr) {
    if (empty($timeStr) || $timeStr === 'NT' || $timeStr === 'DQ') return 0;
    $parts = explode(':', $timeStr);
    if (count($parts) == 2) {
        return ((float)$parts[0] * 60) + (float)$parts[1];
    } else {
        return (float)$timeStr;
    }
}

function calculateTotalSharpness($pdo, $event_id, $swimmer_id) {
    $sql = "SELECT en.distance, en.stroke, en.jenis_kelamin, en.age_group, es.time_final
            FROM event_entries ee
            JOIN event_numbers en ON ee.category_id = en.id
            JOIN event_seeding es ON ee.id = es.entry_id
            WHERE en.event_id = ? AND ee.swimmer_id = ? AND es.is_dq_final = 0 AND es.time_final IS NOT NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$event_id, $swimmer_id]);
    $races = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalSharpness = 0;
    
    foreach ($races as $race) {
        $stmtRec = $pdo->prepare("SELECT record_time FROM master_records 
                                  WHERE distance = ? AND stroke = ? AND jenis_kelamin = ? AND age_group = ? 
                                  ORDER BY record_time_ms ASC LIMIT 1");
        $stmtRec->execute([$race['distance'], $race['stroke'], $race['jenis_kelamin'], $race['age_group']]);
        $masterRecord = $stmtRec->fetchColumn();
        
        if ($masterRecord) {
            $waktuRekorDetik = timeToSeconds($masterRecord);
            $waktuAtletDetik = timeToSeconds($race['time_final']);
            
            if ($waktuRekorDetik > 0 && $waktuAtletDetik > 0 && $waktuAtletDetik < $waktuRekorDetik) {
                $persentaseKetajaman = (($waktuRekorDetik - $waktuAtletDetik) / $waktuRekorDetik) * 100;
                $sharpnessScore = $persentaseKetajaman * $race['distance'];
                $totalSharpness += $sharpnessScore;
            }
        }
    }
    return $totalSharpness;
}
?>