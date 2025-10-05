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
	"SELECT a.id, a.apt_number, b.name AS block_name, s.name AS society_name
	 FROM apartments a
	 JOIN blocks b ON a.block_id = b.id
	 JOIN societies s ON b.society_id = s.id
	 WHERE a.resident_id = ?"
);
$stmt->execute([$resident_id]);
$apartment = $stmt->fetch(PDO::FETCH_ASSOC);

// Date range (default: last 30 days)
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-29 days', strtotime($end_date)));

// Build list of dates in range
$period = new DatePeriod(new DateTime($start_date), new DateInterval('P1D'), (new DateTime($end_date))->modify('+1 day'));
$labels = [];
foreach ($period as $date) { $labels[] = $date->format('Y-m-d'); }

// Fetch reports grouped per day and status
// Query only if apartment assigned
$rows = [];
if ($apartment) {
    $stmt = $pdo->prepare(
		"SELECT report_date, status, COUNT(*) as cnt
		 FROM segregation_reports
		 WHERE apartment_id = ? AND report_date BETWEEN ? AND ?
		 GROUP BY report_date, status"
    );
    $stmt->execute([$apartment['id'], $start_date, $end_date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Prepare datasets by status
$statuses = ['segregated', 'partial', 'not', 'no_waste'];
$colors = [
	'segregated' => '#27ae60',
	'partial' => '#f39c12',
	'not' => '#e74c3c',
	'no_waste' => '#3498db'
];

$dailyData = [];
foreach ($statuses as $s) {
	$dailyData[$s] = array_fill(0, count($labels), 0);
}

foreach ($rows as $r) {
	$idx = array_search($r['report_date'], $labels, true);
	if ($idx !== false && isset($dailyData[$r['status']])) {
		$dailyData[$r['status']][$idx] = (int)$r['cnt'];
	}
}

// Totals for pie
$totals = array_fill_keys($statuses, 0);
foreach ($rows as $r) { $totals[$r['status']] += (int)$r['cnt']; }

// Calculate weekly and monthly stats for dashboard-style charts
$weekly = ["segregated"=>0,"partial"=>0,"not"=>0,"no_waste"=>0];
$monthly = ["segregated"=>0,"partial"=>0,"not"=>0,"no_waste"=>0];
$today = new DateTime();

if ($apartment) {
    // Get all reports for this apartment (not just in date range)
    $stmt = $pdo->prepare("SELECT status, report_date FROM segregation_reports WHERE apartment_id=? ORDER BY report_date DESC");
    $stmt->execute([$apartment["id"]]);
    $all_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_reports as $r) {
        $date = new DateTime($r["report_date"]);
        $days = $today->diff($date)->days;
        if ($days <= 7) $weekly[$r["status"]]++;
        if ($days <= 30) $monthly[$r["status"]]++;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Analytics - Swachh</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/app.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
				<li><a href="#" class="active">Analytics</a></li>
				<li><a href="credits.php">Credits & Penalties</a></li>
				<li><a href="redeem.php">Reward Redemption</a></li>
				<li><a href="../api/logout.php">Logout</a></li>
			</ul>
		</div>

		<div class="main-content">
			<div class="content-header">
				<h1>Analytics</h1>
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
					<h3>Filter</h3>
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
					<button type="submit" class="btn btn-action btn-primary">Apply</button>
				</form>
			</div>

			<!-- Charts or Empty State -->
			<?php if ($apartment): ?>
			<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px;">
				<div class="card">
					<div class="card-header">
						<h3>Daily Reports (Stacked)</h3>
					</div>
					<canvas id="dailyStacked"></canvas>
				</div>
				<div class="card">
					<div class="card-header">
						<h3>Status Distribution</h3>
					</div>
					<canvas id="statusPie"></canvas>
				</div>
			</div>

			<!-- Weekly and Monthly Performance Charts -->
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
				<!-- Weekly Chart -->
				<div class="card-charts">
					<div class="card-header">
						<h3>This Week's Performance</h3>
					</div>
					<canvas id="weeklyChart" height="400" width="400"></canvas>
				</div>
				
				<!-- Monthly Chart -->
				<div class="card-charts">
					<div class="card-header">
						<h3>This Month's Performance</h3>
					</div>
					<canvas id="monthlyChart" height="400" width="400"></canvas>
				</div>
			</div>
			<?php else: ?>
			<div class="card" style="border-left: 4px solid var(--warning-color);">
				<div class="card-header">
					<h3>Apartment Assignment Pending</h3>
				</div>
				<p style="color: #666;">Your resident account is active, but no apartment is assigned yet. Once assigned by your society admin, analytics will appear here.</p>
			</div>
			<?php endif; ?>

			<div class="card">
				<div class="card-header">
					<h3>Summary</h3>
				</div>
				<div class="stats-grid">
					<div class="stat-card"><div class="stat-icon">✅</div><div class="stat-value"><?php echo (int)$totals['segregated']; ?></div><div class="stat-label">Segregated</div></div>
					<div class="stat-card"><div class="stat-icon">⚠️</div><div class="stat-value"><?php echo (int)$totals['partial']; ?></div><div class="stat-label">Partial</div></div>
					<div class="stat-card"><div class="stat-icon">❌</div><div class="stat-value"><?php echo (int)$totals['not']; ?></div><div class="stat-label">Not Segregated</div></div>
					<div class="stat-card"><div class="stat-icon">🗑️</div><div class="stat-value"><?php echo (int)$totals['no_waste']; ?></div><div class="stat-label">No Waste</div></div>
				</div>
			</div>
		</div>
	</div>

	<?php if ($apartment): ?>
	<script>
	const labels = <?php echo json_encode($labels); ?>;
	const datasets = [
		<?php foreach ($statuses as $s): ?>
		{
			label: '<?php echo ucfirst(str_replace('_',' ', $s)); ?>',
			data: <?php echo json_encode(array_values($dailyData[$s])); ?>,
			backgroundColor: '<?php echo $colors[$s]; ?>'
		},
		<?php endforeach; ?>
	];

	const ctx1 = document.getElementById('dailyStacked').getContext('2d');
	new Chart(ctx1, {
		type: 'bar',
		data: { labels, datasets },
		options: {
			responsive: true,
			scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision:0 } } },
			plugins: { legend: { position: 'bottom' } }
		}
	});

	const ctx2 = document.getElementById('statusPie').getContext('2d');
	new Chart(ctx2, {
		type: 'doughnut',
		data: {
			labels: ['Segregated','Partial','Not Segregated','No Waste'],
			datasets: [{
				data: [<?php echo (int)$totals['segregated']; ?>, <?php echo (int)$totals['partial']; ?>, <?php echo (int)$totals['not']; ?>, <?php echo (int)$totals['no_waste']; ?>],
				backgroundColor: ['#27ae60','#f39c12','#e74c3c','#3498db']
			}]
		},
		options: { plugins: { legend: { position: 'bottom' } } }
	});

	// Weekly Chart
	const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
	new Chart(weeklyCtx, {
		type: 'doughnut',
		data: {
			labels: ['Segregated', 'Partial', 'Not Segregated', 'No Waste'],
			datasets: [{
				data: [<?php echo $weekly["segregated"]; ?>, <?php echo $weekly["partial"]; ?>, <?php echo $weekly["not"]; ?>, <?php echo $weekly["no_waste"]; ?>],
				backgroundColor: ['#27ae60', '#f39c12', '#e74c3c', '#3498db']
			}]
		},
		options: { plugins: { legend: { position: 'bottom' } } }
	});
	
	// Monthly Chart
	const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
	new Chart(monthlyCtx, {
		type: 'doughnut',
		data: {
			labels: ['Segregated', 'Partial', 'Not Segregated', 'No Waste'],
			datasets: [{
				data: [<?php echo $monthly["segregated"]; ?>, <?php echo $monthly["partial"]; ?>, <?php echo $monthly["not"]; ?>, <?php echo $monthly["no_waste"]; ?>],
				backgroundColor: ['#27ae60', '#f39c12', '#e74c3c', '#3498db']
			}]
		},
		options: { plugins: { legend: { position: 'bottom' } } }
	});
	</script>
	<?php endif; ?>
    <script>
        if (window.initNotificationBell) {
            window.initNotificationBell('../api/get_notifications.php', 'notifBadgeResident', 15000);
        }
    </script>
</body>
</html>


