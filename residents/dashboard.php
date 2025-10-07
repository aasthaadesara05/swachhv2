<!-- Streak Bonuses
7-day streak: +10 bonus credits
30-day streak: +50 bonus credits + "Consistency Champion" badge
90-day streak: +150 bonus credits + special reward unlock

Monthly Challenges
"Zero Waste Weekend" - Generate no waste on Saturday/Sunday: +25 credits
"Education Champion" - Share segregation tips with 5 neighbors: +30 credits
"Perfect Month" - 100% proper segregation: +100 bonus credits -->

<?php
session_start();

require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "resident") {
    header("Location: ../login.php");
    exit();
}

$resident_id = $_SESSION["user_id"];
$resident_name = $_SESSION["user_name"];

// Get monthly challenges for current month/year
$current_month = (int)date('n');
$current_year = (int)date('Y');
$stmt = $pdo->prepare("SELECT title, description, reward FROM monthly_challenges WHERE active=1 AND month=? AND year=? ORDER BY id DESC");
$stmt->execute([$current_month, $current_year]);
$monthly_challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// If apartment not assigned, prepare safe defaults; else load reports
$reports = [];
if ($apartment) {
    $stmt = $pdo->prepare("SELECT status, report_date FROM segregation_reports WHERE apartment_id=? ORDER BY report_date DESC");
    $stmt->execute([$apartment["id"]]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Calculate monthly stats for credit score
$monthly = ["segregated"=>0,"partial"=>0,"not"=>0,"no_waste"=>0];
$today = new DateTime();

foreach ($reports as $r) {
    $date = new DateTime($r["report_date"]);
    $days = $today->diff($date)->days;
    if ($days <= 30) $monthly[$r["status"]]++;
}

// --- Streak Calculation ---
$streak = 0;
$last_date = null;
foreach ($reports as $r) {
    if ($r['status'] === 'segregated') {
        $date = new DateTime($r['report_date']);
        if ($last_date === null) {
            $streak = 1;
        } else {
            $diff = $last_date->diff($date)->days;
            if ($diff === 1) {
                $streak++;
            } else {
                break;
            }
        }
        $last_date = $date;
    } else {
        break;
    }
}

// --- Streak Bonuses ---
$streak_bonus = null;
if ($streak >= 90) {
    $streak_bonus = [
        'credits' => 150,
        'badge' => 'Special Reward Unlock',
        'label' => '90-day streak!'
    ];
} elseif ($streak >= 30) {
    $streak_bonus = [
        'credits' => 50,
        'badge' => 'Consistency Champion',
        'label' => '30-day streak!'
    ];
} elseif ($streak >= 7) {
    $streak_bonus = [
        'credits' => 10,
        'badge' => null,
        'label' => '7-day streak!'
    ];
}

// Get current credits
$stmt = $pdo->prepare("SELECT credits FROM users WHERE id=?");
$stmt->execute([$resident_id]);
$credits = $stmt->fetchColumn();

// Calculate credit score (0-100)
$total_reports = array_sum($monthly);
$credit_score = 0;
if ($total_reports > 0) {
    $credit_score = (($monthly["segregated"] * 100) + ($monthly["partial"] * 50) + ($monthly["no_waste"] * 75)) / $total_reports;
}

// Get recent reports (last 10)
$recent_reports = array_slice($reports, 0, 10);

// Calculate trends
$last_week_reports = array_filter($reports, function($r) {
    $date = new DateTime($r["report_date"]);
    $days = (new DateTime())->diff($date)->days;
    return $days <= 7;
});

$previous_week_reports = array_filter($reports, function($r) {
    $date = new DateTime($r["report_date"]);
    $days = (new DateTime())->diff($date)->days;
    return $days > 7 && $days <= 14;
});

$trend = "stable";
if (count($last_week_reports) > 0 && count($previous_week_reports) > 0) {
    $last_week_segregated = count(array_filter($last_week_reports, fn($r) => $r["status"] === "segregated"));
    $prev_week_segregated = count(array_filter($previous_week_reports, fn($r) => $r["status"] === "segregated"));
    
    if ($last_week_segregated > $prev_week_segregated) {
        $trend = "improving";
    } elseif ($last_week_segregated < $prev_week_segregated) {
        $trend = "declining";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - Swachh</title>
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
				<li><a href="#" class="active">Dashboard</a></li>
				<li><a href="history.php">History</a></li>
				<li><a href="analytics.php">Analytics</a></li>
				<li><a href="credits.php">Credits & Penalties</a></li>
                
				<li><a href="redeem.php">Reward Redemption</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            
            <div class="content-header">
                <h1>Your Waste Segregation Dashboard</h1>
                <div class="user-info">
                    <a href="notifications.php" class="notif-bell" title="Notifications">
                        🔔
                        <span id="notifBadgeResident" class="notif-badge">0</span>
                    </a>
                    <div class="user-avatar"><?php echo strtoupper(substr($resident_name, 0, 1)); ?></div>
                    <a href="../api/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <!-- Apartment Info -->
            <div class="card">
                <div class="card-header">
                    <h3>Your Apartment</h3>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <?php if ($apartment): ?>
                        <h4><?php echo htmlspecialchars($apartment["society_name"]); ?></h4>
                        <p><?php echo htmlspecialchars($apartment["block_name"] . " - " . $apartment["apt_number"]); ?></p>
                        <?php else: ?>
                        <h4>Not Assigned</h4>
                        <p>Apartment assignment pending</p>
                        <?php endif; ?>
                    </div>
                    <div style="text-align: right;">
                        <div class="stat-value" style="font-size: 2rem; color: var(--primary-color);"><?php echo round($credit_score, 1); ?>%</div>
                        <div class="stat-label">Credit Score</div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-value"><?php echo $credits; ?></div>
                    <div class="stat-label">Current Credits</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🔥</div>
                    <div class="stat-value"><?php echo $streak; ?></div>
                    <div class="stat-label">Current Streak</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🏅</div>
                    <div class="stat-value"><?php echo $monthly["segregated"]; ?></div>
                    <div class="stat-label">Segregated This Month</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📈</div>
                    <div class="stat-value"><?php echo ucfirst($trend); ?></div>
                    <div class="stat-label">Weekly Trend</div>
                </div>
            </div>

            <!-- Streak Display Card -->
            <div class="card" style="border-left: 4px solid var(--primary-color); background: #f8fff4; margin-bottom: 20px;">
                <div class="card-header">
                    <h3>🔥 Your Current Streak</h3>
                </div>
                <div style="font-size: 1.2rem; color: #333;">
                    <strong><?php echo $streak; ?> days</strong> of consecutive proper segregation!
                </div>
                <div style="margin-top: 8px; color: #666;">
                    <?php if ($streak < 7): ?>
                        Keep going! Next bonus at <strong>7 days</strong>.
                    <?php elseif ($streak < 30): ?>
                        Great job! Next bonus at <strong>30 days</strong>.
                    <?php elseif ($streak < 90): ?>
                        Amazing! Next bonus at <strong>90 days</strong>.
                    <?php else: ?>
                        You're a streak legend!
                    <?php endif; ?>
                </div>
            </div>

            <!-- Monthly Challenges -->
            <div class="card" style="border-left: 4px solid var(--primary-color); background: #f4faff; margin-bottom: 20px;">
                <div class="card-header">
                    <h3>🏆 Monthly Challenges</h3>
                </div>
                <ul style="list-style: none; padding-left: 0;">
                    <?php if (!empty($monthly_challenges)): ?>
                        <?php foreach ($monthly_challenges as $challenge): ?>
                        <li style="margin-bottom: 12px;">
                            <strong><?php echo htmlspecialchars($challenge['title']); ?></strong>: 
                            <span><?php echo htmlspecialchars($challenge['description']); ?></span>
                            <span style="color: var(--primary-color); font-weight: bold;">+<?php echo htmlspecialchars($challenge['reward']); ?> credits</span>
                        </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No monthly challenges available right now.</li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php if ($streak_bonus): ?>
            <div class="card" style="border-left: 4px solid var(--primary-color); background: #f8fff4;">
                <div class="card-header">
                    <h3>🎉 Streak Bonus: <?php echo $streak_bonus['label']; ?></h3>
                </div>
                <p style="color:#333; font-size:1.1rem;">
                    You earned <strong><?php echo $streak_bonus['credits']; ?> bonus credits</strong>
                    <?php if ($streak_bonus['badge']): ?> and the <strong><?php echo $streak_bonus['badge']; ?></strong> badge!<?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Quick Analytics Link -->
            <?php if ($apartment): ?>
            <div class="card" style="border-left: 4px solid var(--primary-color);">
                <div class="card-header">
                    <h3>📊 View Detailed Analytics</h3>
                </div>
                <p style="color: #666; margin-bottom: 15px;">
                    Get detailed insights into your waste segregation performance with interactive charts and trends.
                </p>
                <a href="analytics.php" class="btn btn-action btn-primary">View Analytics</a>
            </div>
            <?php else: ?>
            <div class="card" style="border-left: 4px solid var(--warning-color);">
                <div class="card-header">
                    <h3>Apartment Assignment Pending</h3>
                </div>
                <p style="color: #666;">Your resident account is active, but no apartment is assigned yet. Please contact your society administrator to link your apartment. You can still view your current credits below.</p>
            </div>
            <?php endif; ?>
            
            <!-- Recent Reports -->
            <div class="card">
                <div class="card-header">
                    <h3>Recent Reports</h3>
                </div>
                <div class="table-container">
                    <?php if (!empty($recent_reports)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Days Ago</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_reports as $report): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($report['report_date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $report['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $days_ago = (new DateTime())->diff(new DateTime($report['report_date']))->days;
                                    echo $days_ago == 0 ? 'Today' : ($days_ago == 1 ? 'Yesterday' : $days_ago . ' days ago');
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <h3><?php echo $apartment ? 'No Reports Yet' : 'No Apartment Assigned'; ?></h3>
                        <p>
                            <?php if ($apartment): ?>
                                Your waste segregation reports will appear here once workers start monitoring your apartment.
                            <?php else: ?>
                                Once your apartment is assigned by admin, your reports will appear here.
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Credit Warning -->
            <?php if ($credits < 50): ?>
            <div class="card" style="border-left: 4px solid var(--danger-color);">
                <div class="error-messages">
                    <div class="error">
                        <strong>⚠️ Credit Warning!</strong> Your credits are below 50. 
                        Please improve your waste segregation to avoid penalties.
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Initialize notifications badge
        if (window.initNotificationBell) {
            window.initNotificationBell('../api/get_notifications.php', 'notifBadgeResident', 15000);
        }
    </script>
</body>
</html>