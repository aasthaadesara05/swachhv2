<?php
session_start();
require_once "../db.php";

// Check if user is logged in as worker
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'worker') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if society_id is provided
if (!isset($_GET['society_id']) || !is_numeric($_GET['society_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid society ID']);
    exit();
}

$society_id = (int)$_GET['society_id'];

try {
    // Get blocks for the selected society
    $stmt = $pdo->prepare("SELECT * FROM blocks WHERE society_id = ? ORDER BY name");
    $stmt->execute([$society_id]);
    $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return blocks as JSON
    header('Content-Type: application/json');
    echo json_encode(['blocks' => $blocks]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
