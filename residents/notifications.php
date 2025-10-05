<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "resident") {
    header("Location: ../index.php");
    exit();
}

$resident_name = $_SESSION["user_name"];
$user_id = $_SESSION["user_id"];

// Fetch last 50 notifications and unread count
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Resident</title>
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
                <li><a href="#" class="active">Notifications</a></li>
                <li><a href="credits.php">Credits & Penalties</a></li>
                <li><a href="redeem.php">Reward Redemption</a></li>
                <li><a href="../api/logout.php">Logout</a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="content-header">
                <h1>Notifications</h1>
                <div class="user-info">
                    <a href="notifications.php" class="notif-bell" title="Notifications">
                        🔔
                        <span id="notifBadgeResident" class="notif-badge">0</span>
                    </a>
                    <div class="user-avatar"><?php echo strtoupper(substr($resident_name, 0, 1)); ?></div>
                    <a href="../api/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Recent</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Title</th>
                                <th>Message</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $n): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($n['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($n['title']); ?></td>
                                <td><?php echo htmlspecialchars($n['message']); ?></td>
                                <td><span class="status-badge" style="background: <?php echo $n['type']==='error'?'#e74c3c':($n['type']==='warning'?'#f39c12':($n['type']==='success'?'#27ae60':'#3498db')); ?>; color: white; text-transform: capitalize;"><?php echo htmlspecialchars($n['type']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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



