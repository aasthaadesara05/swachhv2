<?php
session_start();
header('Content-Type: application/json');
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'resident') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$resident_id = $_SESSION['user_id'];
$tier = isset($_POST['tier']) ? $_POST['tier'] : '';
if (!$tier) {
    echo json_encode(['success' => false, 'error' => 'No tier specified']);
    exit;
}

// Define tiers and thresholds
$tiers = [
    'bronze'   => ['threshold' => 300,  'desc' => '10% discount coupons for local grocery stores once (QR verified)'],
    'silver'   => ['threshold' => 600,  'desc' => '20% discount on electronics/appliances once'],
    'gold'     => ['threshold' => 1000, 'desc' => '₹500 bill payment credit (any utility)'],
    'platinum' => ['threshold' => 1500, 'desc' => '₹1000 bill payment credit'],
    'diamond'  => ['threshold' => 2500, 'desc' => '₹2000+ bill payment options; Tree planted with plaque'],
];

if (!isset($tiers[$tier])) {
    echo json_encode(['success' => false, 'error' => 'Invalid tier']);
    exit;
}

// Check credits
$stmt = $pdo->prepare('SELECT credits FROM users WHERE id=?');
$stmt->execute([$resident_id]);
$current_credits = (int)$stmt->fetchColumn();
if ($current_credits < $tiers[$tier]['threshold']) {
    echo json_encode(['success' => false, 'error' => 'Not enough credits']);
    exit;
}

// Check if already redeemed
$stmt = $pdo->prepare('SELECT COUNT(*) FROM reward_redemptions WHERE user_id=? AND tier=?');
$stmt->execute([$resident_id, $tier]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'error' => 'Already redeemed']);
    exit;
}


// Generate reward details
$reward = '';
if ($tier === 'bronze') {
    // Generate QR code (use Google Chart API for demo)
    $qr_data = 'BronzeReward-' . $resident_id . '-' . time();
    $qr_url = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode($qr_data);
    $reward = '<img src="' . $qr_url . '" alt="QR Code" style="height:60px;">';
    $reward_db = $qr_url;
} elseif ($tier === 'silver') {
    // Generate discount code
    $discount_code = 'SILVER-' . strtoupper(substr(md5($resident_id . time()), 0, 8));
    $reward = '<span style="font-weight:bold; color:#27ae60;">' . $discount_code . '</span>';
    $reward_db = $discount_code;
} else {
    $reward = '<span style="color:#888;">Reward will be processed by admin</span>';
    $reward_db = '';
}

// Insert redemption
$stmt = $pdo->prepare('INSERT INTO reward_redemptions (user_id, tier, cost, reward_description, qr_code, status) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute([
    $resident_id,
    $tier,
    $tiers[$tier]['threshold'],
    $tiers[$tier]['desc'],
    $reward_db,
    'issued'
]);

$date = date('M d, Y H:i');
echo json_encode(['success' => true, 'date' => $date, 'reward' => $reward]);
