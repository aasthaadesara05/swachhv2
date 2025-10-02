<?php
session_start();
require_once "../db.php";

// Check if user is logged in as worker
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'worker') {
    header("Location: ../index.php");
    exit();
}

$worker_id = $_SESSION['user_id'];
$worker_name = $_SESSION['user_name'];

// Get date range parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today

// Get all reports for the worker in the date range
$reports_stmt = $pdo->prepare("
    SELECT sr.*, a.apt_number, b.name as block_name, s.name as society_name, u.name as resident_name
    FROM segregation_reports sr
    JOIN apartments a ON sr.apartment_id = a.id
    JOIN blocks b ON a.block_id = b.id
    JOIN societies s ON b.society_id = s.id
    LEFT JOIN users u ON a.resident_id = u.id
    WHERE sr.worker_id = ? AND sr.report_date BETWEEN ? AND ?
    ORDER BY sr.report_date DESC, s.name, b.name, a.apt_number
");
$reports_stmt->execute([$worker_id, $start_date, $end_date]);
$reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_reports,
        SUM(CASE WHEN status = 'segregated' THEN 1 ELSE 0 END) as segregated_count,
        SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial_count,
        SUM(CASE WHEN status = 'not' THEN 1 ELSE 0 END) as not_segregated_count,
        SUM(CASE WHEN status = 'no_waste' THEN 1 ELSE 0 END) as no_waste_count
    FROM segregation_reports 
    WHERE worker_id = ? AND report_date BETWEEN ? AND ?
");
$stats_stmt->execute([$worker_id, $start_date, $end_date]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historical Reports - Swachh</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/app.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Swachh Worker</h3>
                <p>Welcome, <?php echo htmlspecialchars($worker_name); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="#" class="active">Historical Reports</a></li>
                <li><a href="../api/logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Historical Reports</h1>
                <div class="user-info">
                    <a href="notifications.php" class="notif-bell" title="Notifications">
                        🔔
                        <span id="notifBadgeWorker" class="notif-badge">0</span>
                    </a>
                    <div class="user-avatar"><?php echo strtoupper(substr($worker_name, 0, 1)); ?></div>
                    <a href="../api/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <!-- Date Range Filter -->
            <div class="card">
                <div class="card-header">
                    <h3>Filter Reports</h3>
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
                    <div class="stat-value"><?php echo $stats['total_reports']; ?></div>
                    <div class="stat-label">Total Reports</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value"><?php echo $stats['segregated_count']; ?></div>
                    <div class="stat-label">Segregated</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-value"><?php echo $stats['partial_count']; ?></div>
                    <div class="stat-label">Partial</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">❌</div>
                    <div class="stat-value"><?php echo $stats['not_segregated_count']; ?></div>
                    <div class="stat-label">Not Segregated</div>
                </div>
            </div>
            
            <!-- Reports Table -->
            <div class="card">
                <div class="card-header">
                    <h3>Detailed Reports</h3>
                    <span>Showing reports from <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></span>
                </div>
                <div class="table-container">
                    <?php if (!empty($reports)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Society</th>
                                <th>Block</th>
                                <th>Apartment</th>
                                <th>Resident</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($report['report_date'])); ?></td>
                                <td><?php echo htmlspecialchars($report['society_name']); ?></td>
                                <td><?php echo htmlspecialchars($report['block_name']); ?></td>
                                <td><strong><?php echo htmlspecialchars($report['apt_number']); ?></strong></td>
                                <td><?php echo $report['resident_name'] ? htmlspecialchars($report['resident_name']) : 'No Resident'; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $report['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <h3>No Reports Found</h3>
                        <p>No reports found for the selected date range.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        if (window.initNotificationBell) {
            window.initNotificationBell('../api/get_notifications.php', 'notifBadgeWorker', 15000);
        }
    </script>
</body>
</html>
