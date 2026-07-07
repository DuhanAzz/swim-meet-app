<?php
ob_start(); // Tahan output agar tidak bocor
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set('Asia/Jakarta');

$envPath = __DIR__ . '/../../.env';
$env = [];
if (file_exists($envPath)) {
    $parsed = parse_ini_file($envPath);
    if ($parsed !== false) $env = $parsed;
}

$is_local = false;
if (isset($_SERVER['SERVER_NAME'])) {
    $sn = $_SERVER['SERVER_NAME'];
    $is_local = (
        $sn == 'localhost' ||
        $sn == '127.0.0.1' ||
        str_contains($sn, 'ngrok') ||
        str_starts_with($sn, '192.168.') ||
        str_starts_with($sn, '10.') ||
        str_starts_with($sn, '172.')
    );
}

if ($is_local) {
    // ==========================================
    // KONDISI LOCAL (Termasuk Ngrok)
    // ==========================================
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $dbname = $env['DB_NAME'] ?? 'swim_meet';
    $username = $env['DB_USER'] ?? 'root';
    $password = $env['DB_PASS'] ?? '';
    
    // Base URL untuk environment lokal
    define('BASE_URL', '/swim-meet');
} else {
// ==========================================
    // KONDISI HOSTING (Production)
    // ==========================================
    ini_set('display_errors', 1);          
    ini_set('display_startup_errors', 1);
    
    // UBAH BARIS INI DARI 0 MENJADI E_ALL
    error_reporting(E_ALL); 

    $host = $env['DB_HOST'] ?? 'localhost';
    $dbname = $env['DB_NAME'] ?? 'u381696286_setsystem';
    $username = $env['DB_USER'] ?? 'u381696286_setsystem';
    $password = $env['DB_PASS'] ?? 'iV6|2KG^';
    
    // Base URL untuk environment hosting
    define('BASE_URL', '');
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
/**
 * Fungsi untuk upload dan compress gambar (HD dengan ukuran optimal)
 */
function compressImage($source, $destination, $quality = 80, $maxSizeMb = 2) {
    // Cek batas ukuran
    $filesize = filesize($source);
    if ($filesize > ($maxSizeMb * 1024 * 1024)) {
        return false; // File terlalu besar
    }

    $info = getimagesize($source);
    if (!$info) return false;

    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
        imagejpeg($image, $destination, $quality);
        imagedestroy($image);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
        // Pertahankan transparansi PNG
        imageAlphaBlending($image, true);
        imageSaveAlpha($image, true);
        // Konversi kualitas (0-9 untuk PNG)
        $pngQuality = round((100 - $quality) / 10);
        imagepng($image, $destination, $pngQuality);
        imagedestroy($image);
    } else {
        // Jika format lain (seperti webp, gif), pindahkan saja tanpa kompresi
        move_uploaded_file($source, $destination);
    }
    
    // Amankan file untuk shared hosting
    if (file_exists($destination)) {
        chmod($destination, 0644);
    }

    return true;
}
