<?php
// FILE: get_races.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
ini_set('display_errors', 0);
error_reporting(E_ALL);

$host = 'localhost';
$db   = 'swim_meet';
$user = 'root';
$pass = ''; 

// Ambil ID Kejuaraan dari parameter URL
$event_id = isset($_GET['event_id']) ? $_GET['event_id'] : '';

if(empty($event_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Event ID required']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ambil Nomor Lomba dari tabel 'event_numbers'
    // Kita urutkan berdasarkan event_number agar rapi (1, 2, 3...)
    $sql = "SELECT id, event_number, event_name, gender, age_group 
            FROM event_numbers 
            WHERE event_id = :eid 
            ORDER BY CAST(event_number AS UNSIGNED) ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['eid' => $event_id]);
    $races = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $races]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>
