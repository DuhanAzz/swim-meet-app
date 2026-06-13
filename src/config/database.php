<?php
ob_start(); // Tahan output agar tidak bocor
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (
    $_SERVER['SERVER_NAME'] == 'localhost' ||
    $_SERVER['SERVER_NAME'] == '127.0.0.1' ||
    str_contains($_SERVER['SERVER_NAME'], 'ngrok')
) {
    // ==========================================
    // KONDISI LOCAL (Termasuk Ngrok)
    // ==========================================
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    $host = '127.0.0.1';
    $dbname = 'swim_meet';
    $username = 'root';
    $password = '';
} else {
// ==========================================
    // KONDISI HOSTING (Production)
    // ==========================================
    ini_set('display_errors', 1);          
    ini_set('display_startup_errors', 1);
    
    // UBAH BARIS INI DARI 0 MENJADI E_ALL
    error_reporting(E_ALL); 

    $host = 'localhost';
    $dbname = 'u381696286_setsystem';
    $username = 'u381696286_setsystem';
    $password = 'iV6|2KG^';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Jika lokal tampilkan error detail, jika hosting tampilkan pesan umum
    if (ini_get('display_errors') == 1) {
        die("DB Error: " . $e->getMessage());
    } else {
        die("Maaf, terjadi gangguan pada sistem. Silakan coba beberapa saat lagi.");
    }
}

// END OF FILE - DO NOT ADD CLOSING TAG

/**
 * Fungsi untuk mencatat log aktivitas sistem
 */
function writeLog($pdo, $userId, $action, $targetId, $desc) {
    try {
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action_type, target_id, description, ip_address) VALUES (?, ?, ?, ?, ?)");
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt->execute([$userId, $action, $targetId, $desc, $ip]);
    } catch (Exception $e) {
        // Silent fail: Jangan sampai error log mengganggu fungsi utama aplikasi
    }
}