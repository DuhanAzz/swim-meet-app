<?php
// FILE: src/admin/results/calculate_best_swimmer.php

function getBestSwimmerRanking($pdo, $event_id, $gender = 'all', $ku_id = 'all') {
    $stmtYear = $pdo->prepare("SELECT YEAR(event_date_start) FROM events WHERE id = ?");
    $stmtYear->execute([$event_id]);
    $eventYear = $stmtYear->fetchColumn() ?: date('Y');

    $valid_birth_years = [];
    if ($ku_id !== 'all') {
        $stmtKU = $pdo->prepare("SELECT min_age, max_age FROM event_age_groups WHERE id = ?");
        $stmtKU->execute([$ku_id]);
        $ku = $stmtKU->fetch(PDO::FETCH_ASSOC);
        if ($ku) {
            for ($y = ($eventYear - $ku['max_age']); $y <= ($eventYear - $ku['min_age']); $y++) {
                $valid_birth_years[] = $y;
            }
        }
    }

    $whereClauses = ["en.event_id = ?", "es.rank_final IN (1,2,3)", "(es.is_dq_final = 0 OR es.is_dq_final IS NULL)"];
    $params = [$event_id];

    if ($gender !== 'all') {
        $whereClauses[] = "s.jenis_kelamin = ?";
        $params[] = $gender;
    }

    if (!empty($valid_birth_years)) {
        $placeholders = implode(',', array_fill(0, count($valid_birth_years), '?'));
        $whereClauses[] = "YEAR(s.tanggal_lahir) IN ($placeholders)";
        $params = array_merge($params, $valid_birth_years);
    }

    $whereSql = implode(" AND ", $whereClauses);

    $sql = "SELECT s.id AS swimmer_id, s.nama_atlet, s.jenis_kelamin,
                SUM(CASE WHEN es.rank_final = 1 THEN 1 ELSE 0 END) as emas,
                SUM(CASE WHEN es.rank_final = 2 THEN 1 ELSE 0 END) as perak,
                SUM(CASE WHEN es.rank_final = 3 THEN 1 ELSE 0 END) as perunggu
            FROM swimmers s
            JOIN event_entries ee ON s.id = ee.swimmer_id
            JOIN event_numbers en ON ee.category_id = en.id
            JOIN event_seeding es ON ee.id = es.entry_id
            WHERE $whereSql GROUP BY s.id";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($athletes as &$athlete) {
        $athlete['total_sharpness'] = calculateTotalSharpness($pdo, $event_id, $athlete['swimmer_id']);
    }
    unset($athlete);

    // SORTING PERBAIKAN: Emas > Perak > Perunggu > Persentase TERBESAR (Makin tinggi makin baik)
    usort($athletes, function($a, $b) {
        if ($a['emas'] != $b['emas']) return $b['emas'] - $a['emas'];
        if ($a['perak'] != $b['perak']) return $b['perak'] - $a['perak'];
        if ($a['perunggu'] != $b['perunggu']) return $b['perunggu'] - $a['perunggu'];
        if ($a['total_sharpness'] == $b['total_sharpness']) return 0;
        return ($a['total_sharpness'] > $b['total_sharpness']) ? -1 : 1; // TERBESAR MENANG
    });

    return $athletes;
}

function timeToSeconds($timeStr) {
    if (empty($timeStr) || in_array(strtoupper(trim($timeStr)), ['NT', 'DQ'])) return 0;
    $parts = explode(':', trim($timeStr));
    if (count($parts) == 2) { return ((float)$parts[0] * 60) + (float)$parts[1]; }
    return (float)$parts[0];
}

function calculateTotalSharpness($pdo, $event_id, $swimmer_id) {
    // TAMBAHKAN: AND es.rank_final IN (1, 2, 3)
    $sql = "SELECT en.distance, en.stroke, en.jenis_kelamin, en.age_group, es.time_final
            FROM event_entries ee
            JOIN event_numbers en ON ee.category_id = en.id
            JOIN event_seeding es ON ee.id = es.entry_id
            WHERE en.event_id = ? 
              AND ee.swimmer_id = ? 
              AND es.rank_final IN (1, 2, 3) 
              AND es.is_dq_final = 0 
              AND es.time_final IS NOT NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$event_id, $swimmer_id]);
    $races = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $maxPercentage = 0; 
    
    // ... (kode perulangan foreach di bawahnya tetap sama seperti sebelumnya) ...
    foreach ($races as $race) {
        $stmtRec = $pdo->prepare("SELECT record_time FROM master_records 
                                  WHERE record_type = 'rekornas' AND distance = ? 
                                    AND LOWER(REPLACE(stroke, ' ', '')) = LOWER(REPLACE(?, ' ', '')) 
                                    AND jenis_kelamin = ? AND LOWER(REPLACE(age_group, ' ', '')) = LOWER(REPLACE(?, ' ', '')) 
                                  ORDER BY record_time_ms ASC LIMIT 1");
        $stmtRec->execute([$race['distance'], $race['stroke'], $race['jenis_kelamin'], $race['age_group']]);
        $masterRecord = $stmtRec->fetchColumn();
        
        if (!$masterRecord) {
            $stmtRecFallback = $pdo->prepare("SELECT record_time FROM master_records 
                                      WHERE record_type = 'rekornas' AND distance = ? 
                                        AND LOWER(REPLACE(stroke, ' ', '')) = LOWER(REPLACE(?, ' ', '')) 
                                        AND jenis_kelamin = ? 
                                      ORDER BY record_time_ms ASC LIMIT 1");
            $stmtRecFallback->execute([$race['distance'], $race['stroke'], $race['jenis_kelamin']]);
            $masterRecord = $stmtRecFallback->fetchColumn();
        }
        
        if ($masterRecord) {
            $recSec = timeToSeconds($masterRecord);
            $athSec = timeToSeconds($race['time_final']);
            
            if ($recSec > 0 && $athSec > 0) {
                $pct = ($recSec / $athSec) * 100;
                if ($pct > $maxPercentage) {
                    $maxPercentage = $pct;
                }
            }
        }
    }
    return round($maxPercentage, 2);
}
?>