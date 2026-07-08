<?php
require_once __DIR__ . '/src/config/database.php';
try {
    $pdo->beginTransaction();
    $role = 'admin';
    $namaAkun = 'Test Admin';
    $email = 'testadmin1@example.com';
    $phone = '12345';
    $username = $email;
    $pass = 'password';
    $namaEntitas = 'Test Event';
    $compSystem = 'Langsung Final';
    $location = 'Pool';
    $city = 'City';
    $eventDate = date('Y-m-d');

    $pdo->prepare("INSERT INTO users (nama_lengkap, email, phone, username, password, role, account_status) VALUES (?, ?, ?, ?, ?, ?, 'active')")
        ->execute([$namaAkun, $email, $phone, $username, password_hash($pass, PASSWORD_DEFAULT), $role]);
    $newUserId = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO events (user_id, event_name, competition_system, event_location, event_city, event_date_start, event_status, event_type, lane_count, pool_type) VALUES (?, ?, ?, ?, ?, ?, 'Upcoming', 'Standard', 8, '50m')")
        ->execute([$newUserId, $namaEntitas, $compSystem, $location, $city, $eventDate]);

    $pdo->commit();
    echo "Success";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
