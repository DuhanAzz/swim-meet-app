<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_role(['master']);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$role = trim($_POST['role'] ?? 'user');
$password = $_POST['password'] ?? '12345678';

if ($name === '' || $email === '') {
    $_SESSION['error'] = "Name & Email wajib diisi.";
    header("Location: create.php");
    exit;
}

$db = (new Database())->connect();
$userModel = new User($db);

// hash password
$hash = password_hash($password, PASSWORD_DEFAULT);

$ok = $userModel->create([
    'name' => $name,
    'email' => $email,
    'password' => $hash,
    'role' => $role,
    'phone' => $phone
]);

if ($ok) {
    header("Location: index.php");
    exit;
} else {
    $_SESSION['error'] = "Gagal menyimpan user.";
    header("Location: create.php");
    exit;
}
