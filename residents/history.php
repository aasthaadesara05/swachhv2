<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "resident") {
    header("Location: ../index.php");
    exit();
}

$resident_id = $_SESSION["user_id"];
$resident_name = $_SESSION["user_name"];

// Get apartment info
$stmt = $pdo->prepare(
    "
    SELECT a.id, a.apt_number, b.name AS block_name, s.name AS society_name
    FROM apartments a
    JOIN blocks b ON a.block_id = b.id
    JOIN societies s ON b.society_id = s.id
    WHERE a.resident_id = ?
"
);
$stmt->execute([$resident_id]);
$apartment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get date range parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today

// Get all reports for this apartment in the date range (guard for no apartment)
$reports = [];
if ($apartment) {
    $stmt = $pdo->prepare(
        "
        SELECT sr.*, u.name as worker_name
        FROM segregation_reports sr
        LEFT JOIN users u ON sr.worker_id = u.id
        WHERE sr.apartment_id = ? AND sr.report_date BETWEEN ? AND ?
        ORDER BY sr.report_date DESC
    "
    );
    $stmt->execute([$apartment["id"], $start_date, $end_date]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate summary statistics
$total_reports = count($reports);
$segregated_count = count(array_filter($reports, fn($r) => $r['status'] === 'segregated'));
$partial_count = count(array_filter($reports, fn($r) => $r['status'] === 'partial'));
$not_segregated_count = count(array_filter($reports, fn($r) => $r['status'] === 'not'));
$no_waste_count = count(array_filter($reports, fn($r) => $r['status'] === 'no_waste'));

// Calculate performance percentage
$performance_percentage = $total_reports > 0 ? (($segregated_count + ($partial_count * 0.5) + ($no_waste_count * 0.75)) / $total_reports) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - Swachh</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/app.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Swachh Resident</h3>
                <p>Welcome, <?php echo htmlspecialchars($resident_name); ?></p>
            </div>
            <ul class="sidebar-menu">
				<li><a href="dashboard.php">Dashboard</a></li>
				<li><a href="#" class="active">History</a></li>
				<li><a href="analytics.php">Analytics</a></li>
				<li><a href="credits.php">Credits & Penalties</a></li>
				<li><a href="../api/logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Waste Segregation History</h1>
                <div class="user-info">
                    <a href="notifications.php" class="notif-bell" title="Notifications">
                        🔔
                        <span id="notifBadgeResident" class="notif-badge">0</span>
                    </a>
                    <div class="user-avatar"><?php echo strtoupper(substr($resident_name, 0, 1)); ?></div>
                    <a href="../api/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <!-- Date Range Filter -->
            <div class="card">
                <div class="card-header">
                    <h3>Filter History</h3>
                </div>
                <form method="GET" class="form-inline">
                    <div class="form-group" >
                        <label>Start Date:</label>
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="form-control">
                    </div>
                    <div class="form-group" >
                        <label>End Date:</label>
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-action btn-primary">Filter</button>
                </form>
            </div>
            
            <!-- Summary Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-value"><?php echo $total_reports; ?></div>
                    <div class="stat-label">Total Reports</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🎯</div>
                    <div class="stat-value"><?php echo round($performance_percentage, 1); ?>%</div>
                    <div class="stat-label">Performance</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value"><?php echo $segregated_count; ?></div>
                    <div class="stat-label">Segregated</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-value"><?php echo $partial_count; ?></div>
                    <div class="stat-label">Partial</div>
                </div>
            </div>
            
            <!-- History Table or Empty State -->
            <div class="card">
                <div class="card-header">
                    <h3>Detailed History</h3>
                    <span>Showing reports from <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></span>
                </div>
                <div class="table-container">
                    <?php if (!empty($reports)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Reported By</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                            <?php
                            $status_scores = [
                                'segregated' => 100,
                                'partial' => 50,
                                'not' => 0,
                                'no_waste' => 75
                            ];
                            $score = $status_scores[$report['status']] ?? 0;
                            ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($report['report_date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $report['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo $report['worker_name'] ? htmlspecialchars($report['worker_name']) : 'Unknown Worker'; ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="flex: 1; background: #e9ecef; height: 8px; border-radius: 4px; overflow: hidden;">
                                            <div style="width: <?php echo $score; ?>%; height: 100%; background: <?php echo $score >= 75 ? '#27ae60' : ($score >= 50 ? '#f39c12' : '#e74c3c'); ?>;"></div>
                                        </div>
                                        <span style="font-weight: 500; color: <?php echo $score >= 75 ? '#27ae60' : ($score >= 50 ? '#f39c12' : '#e74c3c'); ?>;"><?php echo $score; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <h3><?php echo $apartment ? 'No History Found' : 'No Apartment Assigned'; ?></h3>
                        <p>
                            <?php if ($apartment): ?>
                                No reports found for the selected date range.
                            <?php else: ?>
                                Your account is active, but no apartment is linked yet. Contact your society admin.
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>
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
