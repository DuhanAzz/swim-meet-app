<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_role(['master']);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$db = (new Database())->connect();
$userModel = new User($db);

$default = '12345678';
$hash = password_hash($default, PASSWORD_DEFAULT);
$userModel->updatePassword($id, $hash);

header("Location: index.php");
exit;
