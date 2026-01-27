<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ob_start(); // Tahan output agar tidak bocor
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$host = 'localhost';
$dbname = 'swim_meet';
$username = 'root';
$password = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
// END OF FILE - DO NOT ADD CLOSING TAG
// Tambahkan fungsi ini di paling bawah file config/database.php

function writeLog($pdo, $userId, $action, $targetId, $desc) {
    try {
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action_type, target_id, description, ip_address) VALUES (?, ?, ?, ?, ?)");
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt->execute([$userId, $action, $targetId, $desc, $ip]);
    } catch (Exception $e) {
        // Silent fail: Jangan sampai error log mengganggu fungsi utama
    }
}