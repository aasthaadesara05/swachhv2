<?php
session_start();
require_once "../db.php";

// Simple admin authentication (in production, use proper authentication)
$admin_password = "admin123"; // Change this in production
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Handle logout for any request
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    $is_admin = false;
}

// Handle admin login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    if ($_POST['admin_password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        $is_admin = true;
    } else {
        $error = "Invalid admin password";
    }
}

// Show login form if not admin
if (!$is_admin) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - Swachh</title>
        <link rel="stylesheet" href="../css/style.css">
    </head>
    <body>
        <div class="auth-container">
            <div class="auth-box">
                <div class="auth-header">
                    <h2>Admin Login</h2>
                    <p>Swachh Management System</p>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="error-messages">
                        <div class="error"><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="admin_password">Admin Password</label>
                        <input type="password" id="admin_password" name="admin_password" required>
                    </div>
                    <button type="submit" class="btn-primary">Login</button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Handle admin actions
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Monthly Challenges actions
    if (isset($_POST['add_challenge'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $reward = intval($_POST['reward']);
        $month = intval($_POST['month']);
        $year = intval($_POST['year']);
        $stmt = $pdo->prepare("INSERT INTO monthly_challenges (title, description, reward, month, year, active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$title, $description, $reward, $month, $year]);
        $success = "Challenge added successfully.";
    } elseif (isset($_POST['edit_id'])) {
        $id = intval($_POST['edit_id']);
        $title = $_POST['title'];
        $description = $_POST['description'];
        $reward = intval($_POST['reward']);
        $stmt = $pdo->prepare("UPDATE monthly_challenges SET title=?, description=?, reward=? WHERE id=?");
        $stmt->execute([$title, $description, $reward, $id]);
        $success = "Challenge updated successfully.";
    } elseif (isset($_POST['toggle_id'])) {
        $id = intval($_POST['toggle_id']);
        $stmt = $pdo->prepare("UPDATE monthly_challenges SET active = 1 - active WHERE id=?");
        $stmt->execute([$id]);
        $success = "Challenge status changed.";
    } elseif (isset($_POST['delete_id'])) {
        $id = intval($_POST['delete_id']);
        $stmt = $pdo->prepare("DELETE FROM monthly_challenges WHERE id=?");
        $stmt->execute([$id]);
        $success = "Challenge deleted successfully.";
    }
    // After any action, reload the page to reflect changes
    if (isset($success)) {
        echo "<script>location.href=location.href;</script>";
        exit();
    }
    // Other admin actions
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_user':
                    $name = trim($_POST['name'] ?? '');
                    $email = trim($_POST['email'] ?? '');
                    $role = $_POST['role'] ?? '';
                    $password = $_POST['password'] ?? '';
                    if ($name && filter_var($email, FILTER_VALIDATE_EMAIL) && in_array($role, ['resident','worker'])) {
                        $exists = $pdo->prepare("SELECT id FROM users WHERE email=?");
                        $exists->execute([$email]);
                        if (!$exists->fetch()) {
                            $hash = password_hash($password ?: '12345', PASSWORD_DEFAULT);
                            $ins = $pdo->prepare("INSERT INTO users (name,email,password,role,credits) VALUES (?,?,?,?,?)");
                            $ins->execute([$name,$email,$hash,$role, $role==='resident'?100:0]);
                        }
                    }
                    break;
                case 'add_society':
                    $name = trim($_POST['name'] ?? '');
                    if ($name) {
                        $pdo->prepare("INSERT INTO societies (name) VALUES (?)")->execute([$name]);
                    }
                    break;
                case 'add_block':
                    $society_id = (int)($_POST['society_id'] ?? 0);
                    $name = trim($_POST['name'] ?? '');
                    if ($society_id && $name) {
                        $pdo->prepare("INSERT INTO blocks (society_id, name) VALUES (?,?)")->execute([$society_id, $name]);
                    }
                    break;
                case 'add_apartment':
                    $block_id = (int)($_POST['block_id'] ?? 0);
                    $apt_number = trim($_POST['apt_number'] ?? '');
                    if ($block_id && $apt_number) {
                        $pdo->prepare("INSERT INTO apartments (block_id, apt_number, resident_id) VALUES (?,?,NULL)")->execute([$block_id, $apt_number]);
                    }
                    break;
                case 'assign_apartment':
                    $apartment_id = (int)($_POST['apartment_id'] ?? 0);
                    $resident_id = (int)($_POST['resident_id'] ?? 0);
                    if ($apartment_id && $resident_id) {
                        $pdo->prepare("UPDATE apartments SET resident_id=? WHERE id=?")->execute([$resident_id, $apartment_id]);
                    }
                    break;
                case 'mark_penalty_paid':
                $penalty_id = (int)($_POST['penalty_id'] ?? 0);
                if ($penalty_id) {
                    $pdo->prepare("UPDATE penalties SET status='paid', paid_at=NOW() WHERE id=?")->execute([$penalty_id]);
                }
                break;
            case 'send_notification':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $message = trim($_POST['message'] ?? '');
                $type = $_POST['type'] ?? 'info';
                if (!in_array($type, ['info','warning','success','error'])) { $type = 'info'; }
                if ($user_id && $title && $message) {
                    $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)")
                        ->execute([$user_id, $title, $message, $type]);
                }
                break;
            }
        } catch (PDOException $e) {
            // In production, log error
        }
    }
}

// Get statistics and datasets
try {
    $stats = [];
    
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $stats['total_users'] = $stmt->fetchColumn();
    
    // Total residents
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'resident'");
    $stats['total_residents'] = $stmt->fetchColumn();
    
    // Total workers
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'worker'");
    $stats['total_workers'] = $stmt->fetchColumn();
    
    // Total societies
    $stmt = $pdo->query("SELECT COUNT(*) FROM societies");
    $stats['total_societies'] = $stmt->fetchColumn();
    
    // Total apartments
    $stmt = $pdo->query("SELECT COUNT(*) FROM apartments");
    $stats['total_apartments'] = $stmt->fetchColumn();
    
    // Total reports this month
    $stmt = $pdo->query("SELECT COUNT(*) FROM segregation_reports WHERE MONTH(report_date) = MONTH(CURDATE()) AND YEAR(report_date) = YEAR(CURDATE())");
    $stats['reports_this_month'] = $stmt->fetchColumn();
    
    // Average credit score
    $stmt = $pdo->query("SELECT AVG(credits) FROM users WHERE role = 'resident'");
    $stats['avg_credits'] = round($stmt->fetchColumn(), 1);
    
    // Recent reports (order by date then id)
    $stmt = $pdo->query("
        SELECT sr.*, a.apt_number, b.name as block_name, s.name as society_name, u.name as resident_name
        FROM segregation_reports sr
        JOIN apartments a ON sr.apartment_id = a.id
        JOIN blocks b ON a.block_id = b.id
        JOIN societies s ON b.society_id = s.id
        LEFT JOIN users u ON a.resident_id = u.id
        ORDER BY sr.report_date DESC, sr.id DESC
        LIMIT 10
    ");
    $recent_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Low credit users
    $stmt = $pdo->query("SELECT id, name, email, credits FROM users WHERE role = 'resident' AND credits < 50 ORDER BY credits ASC LIMIT 10");
    $low_credit_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Datasets for panels
    $all_users = $pdo->query("SELECT id, name, email, role, credits FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $residents = $pdo->query("SELECT id, name, email FROM users WHERE role='resident' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $workers = $pdo->query("SELECT id, name, email FROM users WHERE role='worker' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $societies = $pdo->query("SELECT id, name FROM societies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $blocks = $pdo->query("SELECT b.id, b.name, b.society_id, s.name as society_name FROM blocks b JOIN societies s ON b.society_id = s.id ORDER BY s.name, b.name")->fetchAll(PDO::FETCH_ASSOC);
    $apartments = $pdo->query("
        SELECT a.id, a.apt_number, b.name as block_name, s.name as society_name, u.name as resident_name, u.id as resident_id
        FROM apartments a
        JOIN blocks b ON a.block_id = b.id
        JOIN societies s ON b.society_id = s.id
        LEFT JOIN users u ON a.resident_id = u.id
        ORDER BY s.name, b.name, a.apt_number
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Penalties (if table exists)
    try {
        $penalties = $pdo->query("SELECT p.*, u.name as user_name, u.email FROM penalties p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $t) {
        $penalties = [];
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Swachh</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Swachh Admin</h3>
                <p>System Management</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="#" class="active">Dashboard</a></li>
                <li><a href="#users">Manage Users</a></li>
                <li><a href="#societies">Manage Societies</a></li>
                <li><a href="#apartments">Apartments</a></li>
                <li><a href="#penalties">Penalties</a></li>
                <li><a href="#monthly-challenges">Manage Monthly Challenges</a></li>
                <li><a href="#notifications">Notifications</a></li>
                <li><a href="?logout=1">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Admin Dashboard</h1>
                <div class="user-info">
                    <div class="user-avatar">A</div>
                    <a href="?logout=1" class="logout-btn">Logout</a>
                </div>
            </div>


            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🏠</div>
                    <div class="stat-value"><?php echo $stats['total_residents']; ?></div>
                    <div class="stat-label">Residents</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👷</div>
                    <div class="stat-value"><?php echo $stats['total_workers']; ?></div>
                    <div class="stat-label">Workers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🏘️</div>
                    <div class="stat-value"><?php echo $stats['total_societies']; ?></div>
                    <div class="stat-label">Societies</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🏢</div>
                    <div class="stat-value"><?php echo $stats['total_apartments']; ?></div>
                    <div class="stat-label">Apartments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-value"><?php echo $stats['reports_this_month']; ?></div>
                    <div class="stat-label">Reports This Month</div>
                </div>
            </div>
            

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                <!-- Recent Reports -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Reports</h3>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Apartment</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_reports as $report): ?>
                                <tr>
                                    <td><?php echo date('M d', strtotime($report['report_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($report['society_name'] . ' / ' . $report['block_name'] . ' / ' . $report['apt_number']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $report['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>



                <!-- Low Credit Users -->
                <div class="card">
                    <div class="card-header">
                        <h3>Low Credit Alert</h3>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Resident</th>
                                    <th>Credits</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_credit_users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo $user['credits']; ?></td>
                                    <td>
                                        <?php if ($user['credits'] < 20): ?>
                                            <span class="status-badge" style="background: #e74c3c; color: white;">Critical</span>
                                        <?php else: ?>
                                            <span class="status-badge" style="background: #f39c12; color: white;">Warning</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            



            <!-- Manage Users -->
            <div id="users" class="card">
                <div class="card-header">
                    <h3>Manage Users</h3>
                </div>
                <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px;">
                    <input type="hidden" name="action" value="add_user">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Name</label>
                        <input class="form-control" name="name" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Email</label>
                        <input class="form-control" name="email" type="email" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Password</label>
                        <input class="form-control" name="password" type="text" placeholder="default 12345">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Role</label>
                        <select class="form-control" name="role">
                            <option value="resident">Resident</option>
                            <option value="worker">Worker</option>
                        </select>
                    </div>
                    <div style="align-self: end;">
                        <button class="btn btn-success" type="submit">Add User</button>
                    </div>
                </form>
                <div class="table-container" style="margin-top: 15px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Credits</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_users as $u): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['name']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><?php echo htmlspecialchars($u['role']); ?></td>
                                <td><?php echo (int)$u['credits']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Manage Societies & Blocks -->
            <div id="societies" class="card">
                <div class="card-header">
                    <h3>Manage Societies & Blocks</h3>
                </div>
                <form method="POST" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <input type="hidden" name="action" value="add_society">
                    <div class="form-group" style="margin-bottom: 0; min-width: 240px;">
                        <label>New Society Name</label>
                        <input class="form-control" name="name" required>
                    </div>
                    <button class="btn btn-success" type="submit" style="align-self: end;">Add Society</button>
                </form>
                <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; margin-top: 10px;">
                    <input type="hidden" name="action" value="add_block">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Society</label>
                        <select class="form-control" name="society_id">
                            <?php foreach ($societies as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Block Name</label>
                        <input class="form-control" name="name" required>
                    </div>
                    <div style="align-self: end;">
                        <button class="btn btn-success" type="submit">Add Block</button>
                    </div>
                </form>
                <div class="table-container" style="margin-top: 15px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Society</th>
                                <th>Block</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blocks as $b): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($b['society_name']); ?></td>
                                <td><?php echo htmlspecialchars($b['name']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Apartments & Assignment -->
            <div id="apartments" class="card">
                <div class="card-header">
                    <h3>Apartments & Assignment</h3>
                </div>
                <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px;">
                    <input type="hidden" name="action" value="add_apartment">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Block</label>
                        <select class="form-control" name="block_id">
                            <?php foreach ($blocks as $b): ?>
                                <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['society_name'] . ' / ' . $b['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Apartment Number</label>
                        <input class="form-control" name="apt_number" required>
                    </div>
                    <div style="align-self: end;">
                        <button class="btn btn-success" type="submit">Add Apartment</button>
                    </div>
                </form>
                <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; margin-top: 12px;">
                    <input type="hidden" name="action" value="assign_apartment">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Apartment</label>
                        <select class="form-control" name="apartment_id">
                            <?php foreach ($apartments as $a): ?>
                                <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['society_name'] . ' / ' . $a['block_name'] . ' / ' . $a['apt_number']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Assign Resident</label>
                        <select class="form-control" name="resident_id">
                            <?php foreach ($residents as $r): ?>
                                <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name'] . ' (' . $r['email'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="align-self: end;">
                        <button class="btn btn-success" type="submit">Assign</button>
                    </div>
                </form>
                <div class="table-container" style="margin-top: 15px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Society</th>
                                <th>Block</th>
                                <th>Apartment</th>
                                <th>Resident</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($apartments as $a): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($a['society_name']); ?></td>
                                <td><?php echo htmlspecialchars($a['block_name']); ?></td>
                                <td><?php echo htmlspecialchars($a['apt_number']); ?></td>
                                <td><?php echo $a['resident_name'] ? htmlspecialchars($a['resident_name']) : 'Unassigned'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Penalties -->
            <div id="penalties" class="card">
                <div class="card-header">
                    <h3>Penalties</h3>
                </div>
                <?php if (empty($penalties)): ?>
                    <div style="padding: 10px; color: #666;">No penalty data yet.</div>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Amount</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Due Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($penalties as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['user_name'] . ' (' . $p['email'] . ')'); ?></td>
                                <td>₹<?php echo number_format((float)$p['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($p['reason']); ?></td>
                                <td><?php echo htmlspecialchars($p['status']); ?></td>
                                <td><?php echo htmlspecialchars($p['due_date']); ?></td>
                                <td>
                                    <?php if ($p['status'] === 'pending'): ?>
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="action" value="mark_penalty_paid">
                                        <input type="hidden" name="penalty_id" value="<?php echo $p['id']; ?>">
                                        <button class="btn btn-success" type="submit">Mark Paid</button>
                                    </form>
                                    <?php else: ?>
                                    <span class="status-badge" style="background:#27ae60; color:white;">Resolved</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

                        <!-- Manage Monthly Challenges (Embedded) -->
            <div id="monthly-challenges" class="card">
                <div class="card-header">
                    <h3>Manage Monthly Challenges</h3>
                </div>
                    <form method="post" style="margin-bottom: 30px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                        <input type="text" name="title" placeholder="Title" required class="form-control" style="width: 180px; padding: 10px 14px; border-radius: 6px; border: 1px solid #ccc; font-size: 1rem;">
                        <input type="text" name="description" placeholder="Description" required class="form-control" style="width: 320px; padding: 10px 14px; border-radius: 6px; border: 1px solid #ccc; font-size: 1rem;">
                        <input type="number" name="reward" placeholder="Reward Credits" required min="1" class="form-control" style="width: 120px; padding: 10px 14px; border-radius: 6px; border: 1px solid #ccc; font-size: 1rem;">
                        <select name="month" required class="form-control" style="width: 100px; padding: 10px 14px; border-radius: 6px; border: 1px solid #ccc; font-size: 1rem;">
                            <?php for ($m=1; $m<=12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo ($m == date('n')) ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                            <?php endfor; ?>
                        </select>
                        <input type="number" name="year" value="<?php echo date('Y'); ?>" required class="form-control" style="width: 80px; padding: 10px 14px; border-radius: 6px; border: 1px solid #ccc; font-size: 1rem;">
                        <div style="flex:1; text-align:right;">
                            <button type="submit" name="add_challenge" class="btn btn-primary" style="padding: 10px 24px; border-radius: 6px; font-size: 1rem;">Add Challenge</button>
                        </div>
                    </form>
                <?php
                $stmt = $pdo->query("SELECT * FROM monthly_challenges ORDER BY id DESC");
                $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <table class="challenge-table" style="width:100%; border-collapse:collapse; margin-bottom:30px;">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Reward</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($challenges as $c): ?>
                        <tr>
                            <form method="post" style="display:contents;">
                                <td><input type="text" name="title" value="<?php echo htmlspecialchars($c['title']); ?>" class="form-control" style="width:140px; padding:8px 12px; border-radius:6px; border:1px solid #ccc; font-size:1rem;"></td>
                                <td><input type="text" name="description" value="<?php echo htmlspecialchars($c['description']); ?>" class="form-control" style="width:260px; padding:8px 12px; border-radius:6px; border:1px solid #ccc; font-size:1rem;"></td>
                                <td><input type="number" name="reward" value="<?php echo $c['reward']; ?>" class="form-control" style="width:80px; padding:8px 12px; border-radius:6px; border:1px solid #ccc; font-size:1rem;"></td>
                                <td><?php echo $c['active'] ? '<span style="color:green;">Active</span>' : '<span style="color:#999;">Inactive</span>'; ?></td>
                                <td>
                                    <input type="hidden" name="challenge_id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" name="edit_id" value="<?php echo $c['id']; ?>" class="btn btn-action">Save</button>
                                    <button type="submit" name="toggle_id" value="<?php echo $c['id']; ?>" class="btn btn-action"><?php echo $c['active'] ? 'Deactivate' : 'Activate'; ?></button>
                                    <button type="submit" name="delete_id" value="<?php echo $c['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this challenge?');">Delete</button>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Notifications -->
            <div id="notifications" class="card">
                <div class="card-header">
                    <h3>Send Notification</h3>
                </div>
                <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px;">
                    <input type="hidden" name="action" value="send_notification">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>User</label>
                        <select class="form-control" name="user_id">
                            <?php foreach ($all_users as $u): ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name'] . ' (' . $u['email'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Title</label>
                        <input class="form-control" name="title" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Message</label>
                        <input class="form-control" name="message" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Type</label>
                        <select class="form-control" name="type">
                            <option value="info">Info</option>
                            <option value="success">Success</option>
                            <option value="warning">Warning</option>
                            <option value="error">Error</option>
                        </select>
                    </div>
                    <div style="align-self: end;">
                        <button class="btn btn-success" type="submit">Send</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
