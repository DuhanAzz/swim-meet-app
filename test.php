<?php require 'src/config/database.php'; print_r($pdo->query('SELECT id, event_name, record_package_id FROM events WHERE id = 26')->fetch(PDO::FETCH_ASSOC)); ?>
