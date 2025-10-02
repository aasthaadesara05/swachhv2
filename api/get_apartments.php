<?php
session_start();
require_once "../db.php";

// Check if user is logged in as worker
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'worker') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check required parameters
if (!isset($_GET['block_id']) || !is_numeric($_GET['block_id']) || !isset($_GET['date'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters. Block ID and date are required.']);
    exit();
}

$block_id = (int)$_GET['block_id'];
$date = $_GET['date'];
$worker_id = $_SESSION['user_id'];

try {
    // Validate date format
    $date_obj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
        throw new Exception('Invalid date format');
    }
    
    // Get apartments for the selected block with reports
    $stmt = $pdo->prepare("
        SELECT a.*, u.name as resident_name, sr.status, sr.id as report_id,
               b.name as block_name, s.name as society_name
        FROM apartments a 
        LEFT JOIN users u ON a.resident_id = u.id
        LEFT JOIN segregation_reports sr ON a.id = sr.apartment_id AND sr.report_date = ? AND sr.worker_id = ?
        LEFT JOIN blocks b ON a.block_id = b.id
        LEFT JOIN societies s ON b.society_id = s.id
        WHERE a.block_id = ?
        ORDER BY a.apt_number
    ");
    $stmt->execute([$date, $worker_id, $block_id]);
    $apartments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return apartments as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'apartments' => $apartments,
        'date' => $date,
        'block_info' => !empty($apartments) ? [
            'block_name' => $apartments[0]['block_name'],
            'society_name' => $apartments[0]['society_name']
        ] : null
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>
