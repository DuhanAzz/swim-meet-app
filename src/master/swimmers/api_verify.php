<?php
// FILE: src/master/swimmers/api_verify.php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Tangkap input JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'message' => 'Data ID atau Status tidak dikirim']);
    exit;
}

$id = $input['id'];
$status = $input['status'];

try {
    // Pastikan kolom 'status' ada di tabel swimmers Anda
    $stmt = $pdo->prepare("UPDATE swimmers SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    // Tampilkan pesan error asli dari database untuk debugging
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>