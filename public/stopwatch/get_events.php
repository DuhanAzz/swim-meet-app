<?php
// get_events.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

// 1. Matikan error display agar tidak merusak JSON
ini_set('display_errors', 0); 
error_reporting(E_ALL);

$host = 'localhost';
$db   = 'swim_meet';
$user = 'root';
$pass = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // QUERY DIPERBAIKI: Menggunakan kolom 'event_name' sesuai skema DB
    $sql = "SELECT id, event_name FROM events ORDER BY id DESC";
    
    $stmt = $pdo->query($sql);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $events]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>