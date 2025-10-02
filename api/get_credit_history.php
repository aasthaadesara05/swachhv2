<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'resident') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$resident_id = $_SESSION['user_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

try {
    // Get credit transactions
    $stmt = $pdo->prepare("
        SELECT 
            ct.*,
            sr.report_date,
            a.apt_number,
            b.name as block_name,
            s.name as society_name
        FROM credit_transactions ct
        LEFT JOIN segregation_reports sr ON ct.report_id = sr.id
        LEFT JOIN apartments a ON sr.apartment_id = a.id
        LEFT JOIN blocks b ON a.block_id = b.id
        LEFT JOIN societies s ON b.society_id = s.id
        WHERE ct.user_id = ?
        ORDER BY ct.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$resident_id, $limit, $offset]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM credit_transactions WHERE user_id = ?");
    $count_stmt->execute([$resident_id]);
    $total_count = $count_stmt->fetchColumn();
    
    // Get current credits
    $credits_stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
    $credits_stmt->execute([$resident_id]);
    $current_credits = $credits_stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'current_credits' => $current_credits,
        'total_count' => $total_count,
        'has_more' => ($offset + $limit) < $total_count
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
