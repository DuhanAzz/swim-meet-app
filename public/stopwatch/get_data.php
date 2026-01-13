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

$event_id = isset($_GET['event_id']) ? $_GET['event_id'] : '';
$heat = isset($_GET['heat']) ? $_GET['heat'] : '';

if ($event_id && $heat) {
    try {
        // QUERY CANGGIH:
        // Mengambil data entry DAN Nama Atlet dari tabel 'swimmers'
        // Kita hubungkan (JOIN) event_entries.swimmer_id dengan swimmers.id
        
        $sql = "SELECT 
                    ee.id, 
                    ee.swimmer_id, 
                    ee.lane, 
                    ee.event_id, 
                    ee.heat,
                    s.name as swimmer_name,   -- Ambil nama atlet
                    c.name as club_name       -- (Opsional) Ambil nama klub jika ada relasi
                FROM event_entries ee
                LEFT JOIN swimmers s ON ee.swimmer_id = s.id
                LEFT JOIN clubs c ON ee.club_id = c.id
                WHERE ee.event_id = ? AND ee.heat = ?";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$event_id, $heat]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $results]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Parameter kurang']);
}
?>