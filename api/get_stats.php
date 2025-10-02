<?php
// api/get_stats.php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'resident') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$user_id = $_SESSION['user_id'];

// get apartments for resident
$stmt = $pdo->prepare('SELECT id FROM apartments WHERE resident_id = ?');
$stmt->execute([$user_id]);
$apts = array_column($stmt->fetchAll(), 'id');

if (empty($apts)) {
    echo json_encode(['weekly' => [], 'monthly' => [], 'points_week' => 0, 'points_month' => 0]);
    exit;
}

$ids_placeholder = implode(',', array_fill(0, count($apts), '?'));

// weekly (schema uses report_date DATE)
$sql_week = "SELECT status, COUNT(*) as cnt FROM segregation_reports WHERE apartment_id IN ($ids_placeholder) AND report_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY status";
$stmt = $pdo->prepare($sql_week);
$stmt->execute($apts);
$week = $stmt->fetchAll();

// monthly
$sql_month = "SELECT status, COUNT(*) as cnt FROM segregation_reports WHERE apartment_id IN ($ids_placeholder) AND report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY status";
$stmt = $pdo->prepare($sql_month);
$stmt->execute($apts);
$month = $stmt->fetchAll();

// simple points mapping consistent with save_report.php deltas
function calc_points_from_rows($rows){
    $pts = 0;
    foreach($rows as $r){
        if ($r['status'] === 'segregated') $pts += $r['cnt'] * 2;
        elseif ($r['status'] === 'partial') $pts += $r['cnt'] * 1;
        elseif ($r['status'] === 'not') $pts -= $r['cnt'] * 3;
    }
    return $pts;
}

$points_week = calc_points_from_rows($week);
$points_month = calc_points_from_rows($month);

echo json_encode([
    'weekly' => $week,
    'monthly' => $month,
    'points_week' => $points_week,
    'points_month' => $points_month
]);
