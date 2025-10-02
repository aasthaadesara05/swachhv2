<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

try {
    $sql = "SELECT * FROM notifications WHERE user_id = ?";
    $params = [$user_id];
    
    if ($unread_only) {
        $sql .= " AND is_read = FALSE";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $count_stmt->execute([$user_id]);
    $unread_count = $count_stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
