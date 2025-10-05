<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "resident") {
	header("Location: ../index.php");
	exit();
}

$resident_id = $_SESSION["user_id"];
$resident_name = $_SESSION["user_name"];

// Current credits
$stmt = $pdo->prepare("SELECT credits FROM users WHERE id=?");
$stmt->execute([$resident_id]);
$current_credits = (int)$stmt->fetchColumn();

// Ensure reward_redemptions table exists (idempotent)
$pdo->exec(
	"CREATE TABLE IF NOT EXISTS reward_redemptions (
		id INT AUTO_INCREMENT PRIMARY KEY,
		user_id INT NOT NULL,
		tier VARCHAR(20) NOT NULL,
		cost INT NOT NULL,
		reward_description VARCHAR(255) NOT NULL,
		qr_code VARCHAR(255) DEFAULT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'issued',
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		UNIQUE KEY user_tier_once (user_id, tier)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// Fetch existing redemptions for the user
$stmt = $pdo->prepare("SELECT tier, status, created_at FROM reward_redemptions WHERE user_id=? ORDER BY created_at DESC");
$stmt->execute([$resident_id]);
$my_redemptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper to check if tier already redeemed
function hasRedeemedTier(array $redemptions, string $tier): bool {
	foreach ($redemptions as $r) {
		if (strcasecmp($r['tier'], $tier) === 0) return true;
	}
	return false;
}

// Define tiers and thresholds
$tiers = [
	["id"=>"bronze","name"=>"Bronze","threshold"=>300,"desc"=>"10% discount coupons for local grocery stores once (QR verified)","badge"=>"Bronze badge"],
	["id"=>"silver","name"=>"Silver","threshold"=>600,"desc"=>"20% discount on electronics/appliances once","badge"=>"Silver badge"],
	["id"=>"gold","name"=>"Gold","threshold"=>1000,"desc"=>"₹500 bill payment credit (any utility)","badge"=>"Gold badge"],
	["id"=>"platinum","name"=>"Platinum","threshold"=>1500,"desc"=>"₹1000 bill payment credit","badge"=>"Platinum badge"],
	["id"=>"diamond","name"=>"Diamond","threshold"=>2500,"desc"=>"₹2000+ bill payment options; Tree planted with plaque","badge"=>"Diamond badge"],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Reward Redemption - Swachh</title>
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
				<li><a href="credits.php">Credits & Penalties</a></li>
				<li><a href="#" class="active">Reward Redemption</a></li>
				<li><a href="../api/logout.php">Logout</a></li>
			</ul>
		</div>

		<div class="main-content">
			<div class="content-header">
				<h1>Reward Redemption</h1>
				<div class="user-info">
					<a href="notifications.php" class="notif-bell" title="Notifications">
						🔔
						<span id="notifBadgeResident" class="notif-badge">0</span>
					</a>
					<div class="user-avatar"><?php echo strtoupper(substr($resident_name, 0, 1)); ?></div>
					<a href="../api/logout.php" class="logout-btn">Logout</a>
				</div>
			</div>

			<div class="card" style="border-left: 4px solid var(--primary-color);">
				<div class="card-header">
					<h3>Your Credits</h3>
				</div>
				<div style="display:flex; align-items:center; justify-content:space-between;">
					<div>
						<div class="stat-value" style="font-size:2rem; color: var(--primary-color);"><?php echo $current_credits; ?></div>
						<div class="stat-label">Available Credits</div>
					</div>
					<div style="color:#666;">
						Redemptions are one-time per tier.
					</div>
				</div>
			</div>

			<div class="card">
				<div class="card-header">
					<h3>Available Tiers</h3>
				</div>
				   <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
					   <?php foreach ($tiers as $t): 
						   $meets = $current_credits >= $t['threshold'];
						   $already = hasRedeemedTier($my_redemptions, $t['id']);
					   ?>
					<div class="card reward-card" id="reward-<?php echo $t['id']; ?>" style="margin:0; border-left: 4px solid <?php echo $meets ? 'var(--primary-color)' : '#ccc'; ?>; opacity: <?php echo $already ? '0.7' : '1'; ?>;">
						<div class="card-header">
							<h3><?php echo $t['name']; ?></h3>
							<div style="font-size:1rem; color:#888;"><?php echo $t['threshold']; ?> Credits</div>
						</div>
						<p style="color:#555; margin: 0 16px 12px;"><?php echo htmlspecialchars($t['desc']); ?></p>
						<p style="color:#777; margin: 0 16px 12px;"><strong>Badge:</strong> <?php echo htmlspecialchars($t['badge']); ?></p>
						<div style="display:flex; align-items:center; justify-content: space-between; padding: 0 16px 16px;">
							<?php if ($already): ?>
								<span class="status-badge" style="background:#6c757d; color:#fff;">Already Redeemed</span>
							<?php elseif (!$meets): ?>
								<span class="status-badge" style="background:#e0e0e0; color:#666;" >Need <?php echo $t['threshold'] - $current_credits; ?> more</span>
							<?php else: ?>
								<button class="btn btn-action btn-primary redeem-btn" data-tier="<?php echo $t['id']; ?>" data-name="<?php echo htmlspecialchars($t['name']); ?>" data-desc="<?php echo htmlspecialchars($t['desc']); ?>" data-badge="<?php echo htmlspecialchars($t['badge']); ?>">Redeem</button>
							<?php endif; ?>
						</div>
					</div>
					<?php endforeach; ?>
					<div class="card" style="margin:0; border-left: 4px solid #ccc; opacity:1;">
						<div class="card-header"><h3>More Exciting Rewards Coming Your Way!</h3></div>
						<p style="color:#555; margin: 0 16px 16px;">Stay tuned for exciting new rewards and redemption options soon.</p>
					</div>
				</div>
			</div>

			<div class="card">
				<div class="card-header">
					<h3>Your Redemptions</h3>
				</div>
				<div class="table-container">
					<table id="redemptions-table">
						<thead>
							<tr>
								<th>Tier</th>
								<th>Status</th>
								<th>Date</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($my_redemptions as $r): ?>
							<tr>
								<td><?php echo ucfirst($r['tier']); ?></td>
								<td><span class="status-badge" style="text-transform:capitalize; background: #3498db; color: #fff;"><?php echo htmlspecialchars($r['status']); ?></span></td>
								<td><?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<div id="no-redemptions-msg" style="text-align:center; color:#666; padding: 24px; display: <?php echo empty($my_redemptions) ? 'block' : 'none'; ?>;">No redemptions yet.</div>
				</div>
			</div>
		</div>
	</div>
	<script>
		if (window.initNotificationBell) {
			window.initNotificationBell('../api/get_notifications.php', 'notifBadgeResident', 15000);
		}

		// Reward redemption logic
		document.querySelectorAll('.redeem-btn').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var tier = btn.getAttribute('data-tier');
				var name = btn.getAttribute('data-name');
				var desc = btn.getAttribute('data-desc');
				var badge = btn.getAttribute('data-badge');
				btn.disabled = true;
				btn.textContent = 'Processing...';

				fetch('../api/redeem_reward.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'tier=' + encodeURIComponent(tier)
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						// Hide the card from available tiers
						var card = document.getElementById('reward-' + tier);
						if (card) card.style.display = 'none';

						// Add to redemptions table
						var table = document.getElementById('redemptions-table').getElementsByTagName('tbody')[0];
						var row = document.createElement('tr');
						row.innerHTML = '<td>' + name + '</td>' +
							'<td><span class="status-badge" style="text-transform:capitalize; background: #3498db; color: #fff;">issued</span><br>' + (data.reward ? data.reward : '') + '</td>' +
							'<td>' + (data.date ? data.date : new Date().toLocaleString()) + '</td>';
						table.appendChild(row);
						document.getElementById('no-redemptions-msg').style.display = 'none';
					} else {
						btn.disabled = false;
						btn.textContent = 'Redeem';
						alert(data.error || 'Redemption failed.');
					}
				})
				.catch(() => {
					btn.disabled = false;
					btn.textContent = 'Redeem';
					alert('Network error. Please try again.');
				});
			});
		});
	</script>
</body>
</html>


