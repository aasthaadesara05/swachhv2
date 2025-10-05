<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "resident") {
    header("Location: ../index.php");
    exit();
}

$resident_id = $_SESSION["user_id"];
$resident_name = $_SESSION["user_name"];

// Get current credits
$stmt = $pdo->prepare("SELECT credits FROM users WHERE id=?");
$stmt->execute([$resident_id]);
$current_credits = $stmt->fetchColumn();

// Get apartment info
$stmt = $pdo->prepare("
    SELECT a.id, a.apt_number, b.name AS block_name, s.name AS society_name
    FROM apartments a
    JOIN blocks b ON a.block_id = b.id
    JOIN societies s ON b.society_id = s.id
    WHERE a.resident_id = ?
");
$stmt->execute([$resident_id]);
$apartment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all reports for credit calculation
// Load reports only if apartment is assigned
$reports = [];
if ($apartment) {
    $stmt = $pdo->prepare("SELECT status, report_date FROM segregation_reports WHERE apartment_id=? ORDER BY report_date DESC");
    $stmt->execute([$apartment["id"]]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate credit trends
$monthly_reports = array_filter($reports, function($r) {
    $date = new DateTime($r["report_date"]);
    $days = (new DateTime())->diff($date)->days;
    return $days <= 30;
});

$weekly_reports = array_filter($reports, function($r) {
    $date = new DateTime($r["report_date"]);
    $days = (new DateTime())->diff($date)->days;
    return $days <= 7;
});

// Calculate potential credits earned/lost
$credits_earned_this_month = 0;
$credits_lost_this_month = 0;

foreach ($monthly_reports as $report) {
    switch ($report['status']) {
        case 'segregated':
            $credits_earned_this_month += 5;
            break;
        case 'partial':
            $credits_earned_this_month += 2;
            break;
        case 'not':
            $credits_lost_this_month += 3;
            break;
        case 'no_waste':
            $credits_earned_this_month += 3;
            break;
    }
}

// Calculate credit score (0-100)
$total_reports = count($monthly_reports);
$credit_score = 0;
if ($total_reports > 0) {
    $segregated_count = count(array_filter($monthly_reports, fn($r) => $r["status"] === "segregated"));
    $partial_count = count(array_filter($monthly_reports, fn($r) => $r["status"] === "partial"));
    $no_waste_count = count(array_filter($monthly_reports, fn($r) => $r["status"] === "no_waste"));
    
    $credit_score = (($segregated_count * 100) + ($partial_count * 50) + ($no_waste_count * 75)) / $total_reports;
}

// Determine credit status
$credit_status = "good";
$status_message = "Great job! Keep up the excellent work.";
$status_color = "#27ae60";

if ($current_credits < 20) {
    $credit_status = "critical";
    $status_message = "Critical: Your credits are very low. Immediate action required to avoid penalties.";
    $status_color = "#e74c3c";
} elseif ($current_credits < 50) {
    $credit_status = "warning";
    $status_message = "Warning: Your credits are low. Please improve your waste segregation.";
    $status_color = "#f39c12";
} elseif ($current_credits < 75) {
    $credit_status = "moderate";
    $status_message = "Good progress. Continue maintaining your waste segregation habits.";
    $status_color = "#3498db";
}

// Penalty information
$penalty_threshold = 20;
$penalty_amount = 500; // in rupees
$penalty_applied = $current_credits < $penalty_threshold;

// Credit goals and tips
$credit_goals = [
    "Segregate waste properly" => "+5 credits per report",
    "Partial segregation" => "+2 credits per report", 
    "No waste generated" => "+3 credits per report",
    "Poor segregation" => "-3 credits per report"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credits & Penalties - Swachh</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/app.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Swachh Resident</h3>
                <p>Namaste, <?php echo htmlspecialchars($resident_name); ?></p>
            </div>
            <ul class="sidebar-menu">
				<li><a href="dashboard.php">Dashboard</a></li>
				<li><a href="history.php">History</a></li>
				<li><a href="analytics.php">Analytics</a></li>
				<li><a href="#" class="active">Credits & Penalties</a></li>
				<li><a href="redeem.php">Reward Redemption</a></li>
				<li><a href="../api/logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Credits & Penalties</h1>
                <div class="user-info">
                    <a href="notifications.php" class="notif-bell" title="Notifications">
                        🔔
                        <span id="notifBadgeResident" class="notif-badge">0</span>
                    </a>
                    <div class="user-avatar"><?php echo strtoupper(substr($resident_name, 0, 1)); ?></div>
                    <a href="../api/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <!-- Current Credit Status -->
            <div class="card" style="border-left: 4px solid <?php echo $status_color; ?>;">
                <div class="card-header">
                    <h3>Current Credit Status</h3>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px; align-items: center;">
                    <div style="text-align: center;">
                        <div class="stat-value" style="font-size: 3rem; color: <?php echo $status_color; ?>;"><?php echo $current_credits; ?></div>
                        <div class="stat-label">Current Credits</div>
                        <div style="margin-top: 15px;">
                            <div style="width: 200px; height: 8px; background: #e9ecef; border-radius: 4px; margin: 0 auto;">
                                <div style="width: <?php echo min(100, ($current_credits / 100) * 100); ?>%; height: 100%; background: <?php echo $status_color; ?>; border-radius: 4px;"></div>
                            </div>
                            <small style="color: #666; margin-top: 5px; display: block;">Out of 100 credits</small>
                        </div>
                    </div>
                    <div>
                        <h4 style="color: <?php echo $status_color; ?>; margin-bottom: 10px;"><?php echo ucfirst($credit_status); ?> Status</h4>
                        <p style="margin-bottom: 15px;"><?php echo $status_message; ?></p>
                        
                        <?php if ($penalty_applied): ?>
                        <div class="error-messages">
                            <div class="error">
                                <strong>⚠️ Penalty Applied!</strong> 
                                Your credits have fallen below <?php echo $penalty_threshold; ?>. 
                                A penalty of ₹<?php echo $penalty_amount; ?> has been applied to your municipal account.
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Credit Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📈</div>
                    <div class="stat-value" style="color: #27ae60;">+<?php echo $credits_earned_this_month; ?></div>
                    <div class="stat-label">Credits Earned (This Month)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📉</div>
                    <div class="stat-value" style="color: #e74c3c;">-<?php echo $credits_lost_this_month; ?></div>
                    <div class="stat-label">Credits Lost (This Month)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🎯</div>
                    <div class="stat-value"><?php echo round($credit_score, 1); ?>%</div>
                    <div class="stat-label">Credit Score</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-value"><?php echo count($monthly_reports); ?></div>
                    <div class="stat-label">Reports This Month</div>
                </div>
            </div>
            
            <!-- How Credits Work -->
            <div class="card">
                <div class="card-header">
                    <h3>How Credits Work</h3>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <?php foreach ($credit_goals as $action => $points): ?>
                    <div style="padding: 15px; background: #f8f9fa; border-radius: var(--border-radius); border-left: 4px solid var(--primary-color);">
                        <h4 style="margin-bottom: 8px; color: var(--text-color);"><?php echo $action; ?></h4>
                        <p style="color: #666; margin: 0;"><?php echo $points; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Penalty Information -->
            <div class="card">
                <div class="card-header">
                    <h3>Penalty Information</h3>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div style="text-align: center; padding: 20px; background: #fff3cd; border-radius: var(--border-radius);">
                        <div class="stat-value" style="color: #856404; font-size: 2rem;"><?php echo $penalty_threshold; ?></div>
                        <div class="stat-label" style="color: #856404;">Penalty Threshold</div>
                        <p style="color: #856404; margin: 10px 0 0; font-size: 0.9rem;">Credits below this amount trigger penalties</p>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #f8d7da; border-radius: var(--border-radius);">
                        <div class="stat-value" style="color: #721c24; font-size: 2rem;">₹<?php echo $penalty_amount; ?></div>
                        <div class="stat-label" style="color: #721c24;">Penalty Amount</div>
                        <p style="color: #721c24; margin: 10px 0 0; font-size: 0.9rem;">Charged to your municipal account</p>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #d1ecf1; border-radius: var(--border-radius);">
                        <div class="stat-value" style="color: #0c5460; font-size: 2rem;">Monthly</div>
                        <div class="stat-label" style="color: #0c5460;">Review Period</div>
                        <p style="color: #0c5460; margin: 10px 0 0; font-size: 0.9rem;">Credits and penalties reviewed monthly</p>
                    </div>
                </div>
            </div>
            
            <!-- Tips for Improvement -->
            <div class="card">
                <div class="card-header">
                    <h3>Tips to Improve Your Credits</h3>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div style="padding: 20px; background: #f8f9fa; border-radius: var(--border-radius);">
                        <h4 style="color: var(--primary-color); margin-bottom: 10px;">🗂️ Proper Segregation</h4>
                        <p style="color: #666; margin: 0;">Separate your waste into wet, dry, and hazardous categories. Use different bins for each type.</p>
                    </div>
                    <div style="padding: 20px; background: #f8f9fa; border-radius: var(--border-radius);">
                        <h4 style="color: var(--primary-color); margin-bottom: 10px;">♻️ Reduce Waste</h4>
                        <p style="color: #666; margin: 0;">Minimize waste generation by reusing items and choosing products with less packaging.</p>
                    </div>
                    <div style="padding: 20px; background: #f8f9fa; border-radius: var(--border-radius);">
                        <h4 style="color: var(--primary-color); margin-bottom: 10px;">📅 Consistent Habits</h4>
                        <p style="color: #666; margin: 0;">Make waste segregation a daily habit. Consistency is key to maintaining high credits.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        if (window.initNotificationBell) {
            window.initNotificationBell('../api/get_notifications.php', 'notifBadgeResident', 15000);
        }
    </script>
</body>
</html>
