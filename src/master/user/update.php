<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_role(['master']);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';

$id = intval($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$role = trim($_POST['role'] ?? 'user');

if ($id <= 0 || $name === '' || $email === '') {
    $_SESSION['error'] = "Data tidak valid.";
    header("Location: edit.php?id={$id}");
    exit;
}

$db = (new Database())->connect();
$userModel = new User($db);

$ok = $userModel->update($id, [
    'name' => $name,
    'email' => $email,
    'role' => $role,
    'phone' => $phone
]);

if ($ok) {
    header("Location: index.php");
    exit;
} else {
    $_SESSION['error'] = "Gagal mengupdate.";
    header("Location: edit.php?id={$id}");
    exit;
}
