<?php
try {
    require_once "db.php";
    echo "Database connected successfully!\n";
    
    // Test if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "Users table exists.\n";
        
        // Check if we have test users
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Found " . $result['count'] . " users in database.\n";
    } else {
        echo "Users table does not exist!\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
