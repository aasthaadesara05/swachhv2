<?php
session_start();
require_once "../db.php";

// Check if user is logged in as worker
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'worker') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['apartment_id']) || !isset($input['status']) || !isset($input['date'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

$apartment_id = (int)$input['apartment_id'];
$status = $input['status'];
$date = $input['date'];
$worker_id = $_SESSION['user_id'];

// Validate status
$valid_statuses = ['segregated', 'partial', 'not', 'no_waste'];
if (!in_array($status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status']);
    exit();
}

try {
    // Check if report already exists
    $check_stmt = $pdo->prepare("SELECT id FROM segregation_reports WHERE apartment_id = ? AND report_date = ? AND worker_id = ?");
    $check_stmt->execute([$apartment_id, $date, $worker_id]);
    $existing_report = $check_stmt->fetch();
    
    if ($existing_report) {
        // Update existing report
        $update_stmt = $pdo->prepare("UPDATE segregation_reports SET status = ? WHERE id = ?");
        $update_stmt->execute([$status, $existing_report['id']]);
        $message = 'Report updated successfully';
    } else {
        // Insert new report
        $insert_stmt = $pdo->prepare("INSERT INTO segregation_reports (apartment_id, worker_id, status, report_date) VALUES (?, ?, ?, ?)");
        $insert_stmt->execute([$apartment_id, $worker_id, $status, $date]);
        $message = 'Report saved successfully';
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
