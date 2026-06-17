<?php
// get_data.php
header('Content-Type: application/json');

$host = 'localhost';
$db   = 'swim_meet';
$user = 'root';
$pass = ''; 

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'DB Connection Failed']);
    exit;
}

// category_id = nomor lomba (event_numbers.id)
// heat = heat_prelim dari event_seeding
$category_id = isset($_GET['event_id']) ? $_GET['event_id'] : '';
$heat = isset($_GET['heat']) ? $_GET['heat'] : '';

if ($category_id && $heat) {
    try {
        // QUERY DIPERBAIKI:
        // - Kolom lane & heat ada di tabel event_seeding (lane_prelim, heat_prelim)
        // - Nama atlet: swimmers.nama_atlet
        // - Nama klub: clubs.nama_klub
        
        $sql = "SELECT 
                    ee.id, 
                    ee.swimmer_id, 
                    es.lane_prelim as lane, 
                    ee.category_id as event_id, 
                    es.heat_prelim as heat,
                    s.nama_atlet as swimmer_name,
                    c.nama_klub as club_name,
                    es.time_prelim as time_result
                FROM event_entries ee
                INNER JOIN event_seeding es ON es.entry_id = ee.id
                LEFT JOIN swimmers s ON ee.swimmer_id = s.id
                LEFT JOIN clubs c ON ee.club_id = c.id
                WHERE ee.category_id = ? AND es.heat_prelim = ?
                ORDER BY es.lane_prelim ASC";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$category_id, $heat]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $results]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Parameter kurang']);
}
?>