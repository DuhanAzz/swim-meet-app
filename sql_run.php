<?php
require 'src/config/database.php';
try {
    // 2. CREATE TABLE relay_entries (without explicit FK constraints to avoid 150 errors if referenced tables lack PKs)
    $sql = "CREATE TABLE IF NOT EXISTS relay_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        category_id INT NOT NULL,
        club_id INT NOT NULL,
        team_name VARCHAR(100) NOT NULL,
        seed_time VARCHAR(20) NULL,
        swimmer_1_id INT NULL,
        swimmer_2_id INT NULL,
        swimmer_3_id INT NULL,
        swimmer_4_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    
    $pdo->exec($sql);
    echo "Created relay_entries table successfully.\n";
} catch (PDOException $e) {
    echo "Error creating relay_entries: " . $e->getMessage() . "\n";
}
