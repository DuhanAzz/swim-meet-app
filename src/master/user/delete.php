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
$userModel->delete($id);

header("Location: index.php");
exit;
