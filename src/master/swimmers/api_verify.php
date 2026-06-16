<?php
// FILE: src/master/swimmers/api_verify.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Proteksi Session
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Tangkap input JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$id = $input['id'];
$status = $input['status'];

// Validasi Status agar tidak diisi sembarangan
if (!in_array($status, ['pending', 'verified'])) {
    echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE swimmers SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}