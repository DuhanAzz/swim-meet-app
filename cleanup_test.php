<?php
require_once __DIR__ . '/src/config/database.php';
$pdo->exec("DELETE FROM users WHERE email = 'testadmin1@example.com'");
