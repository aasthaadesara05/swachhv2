<?php
// setup.php — run once: http://localhost/Swachh/setup.php
require_once __DIR__ . '/db.php';

try {
    // 1) Ensure schema exists: if any base table is missing, load schema.sql
    $needsInit = false;
    try {
        $pdo->query("SELECT 1 FROM users LIMIT 1");
        $pdo->query("SELECT 1 FROM societies LIMIT 1");
        $pdo->query("SELECT 1 FROM blocks LIMIT 1");
        $pdo->query("SELECT 1 FROM apartments LIMIT 1");
        $pdo->query("SELECT 1 FROM segregation_reports LIMIT 1");
    } catch (Exception $e) {
        $needsInit = true;
    }

    if ($needsInit) {
        $schemaPath = __DIR__ . '/schema.sql';
        if (!file_exists($schemaPath)) {
            throw new Exception('schema.sql not found at ' . $schemaPath);
        }
        $sql = file_get_contents($schemaPath);
        // split on semicolons that end statements; naive but OK for our file
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmtSql) {
            if ($stmtSql === '' || strpos($stmtSql, '--') === 0) continue;
            $pdo->exec($stmtSql);
        }
        echo "Database schema initialized from schema.sql<br>";
    }

    // Add sample society
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM societies");
    $count = $stmt->fetch()['c'];
    if ($count == 0) {
        $pdo->prepare("INSERT INTO societies (name) VALUES (?)")
            ->execute(['Green Meadows']);
        echo "Inserted society.<br>";
    } else {
        echo "Societies exist already.<br>";
    }

    // Get society id
    $soc = $pdo->query("SELECT id FROM societies LIMIT 1")->fetch();
    $society_id = $soc['id'];

    // Add blocks
    $stmt = $pdo->prepare("INSERT INTO blocks (society_id, name) VALUES (?,?)");
    $stmt->execute([$society_id, 'A']);
    $stmt->execute([$society_id, 'B']);
    echo "Inserted blocks.<br>";

    // Add sample users (worker and resident)
    // worker@example.com / 12345
    // resident@example.com / 12345
    $users = [
        ['Ram','worker@example.com','12345','worker'],
        ['Ravi Kumar','resident@example.com','12345','resident']
    ];
    $ins = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $add = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
    foreach ($users as $u) {
        $ins->execute([$u[1]]);
        if ($ins->fetch()) {
            echo "{$u[1]} already exists.<br>";
            continue;
        }
        // $hash = password_hash($u[2], PASSWORD_DEFAULT);
        $add->execute([$u[0], $u[1], $u[2], $u[3]]);
        echo "Inserted user {$u[1]}.<br>";
    }

    // Create a few apartments and attach one to resident
    $block = $pdo->query("SELECT id FROM blocks WHERE name='A' LIMIT 1")->fetch();
    $block_id = $block['id'];

    // get resident id
    $resid = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $resid->execute(['resident@example.com']);
    $resident_row = $resid->fetch();
    $resident_id = $resident_row ? $resident_row['id'] : null;

    $ap = $pdo->prepare("INSERT INTO apartments (block_id, apt_number, resident_id) VALUES (?,?,?)");
    $ap->execute([$block_id, 'A-101', $resident_id]);
    $ap->execute([$block_id, 'A-102', null]);
    $ap->execute([$block_id, 'A-201', null]);

    echo "Inserted sample apartments.<br>";

    // Ensure notifications table exists (for systems where database_updates.sql wasn't applied)
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        user_id INT NOT NULL,\n        title VARCHAR(255) NOT NULL,\n        message TEXT NOT NULL,\n        type ENUM('info','warning','success','error') DEFAULT 'info',\n        is_read BOOLEAN DEFAULT FALSE,\n        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Seed sample notifications for resident and worker if none exist
    $hasNotifs = $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
    if ((int)$hasNotifs === 0) {
        // get resident and worker ids
        $residentId = $pdo->query("SELECT id FROM users WHERE role='resident' ORDER BY id ASC LIMIT 1")->fetchColumn();
        $workerId = $pdo->query("SELECT id FROM users WHERE role='worker' ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($residentId) {
            $insN = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)");
            $insN->execute([$residentId, 'Welcome to Swachh!', 'You will receive updates and alerts here.', 'info']);
            $insN->execute([$residentId, 'Monthly Summary', 'Your monthly segregation summary is ready.', 'success']);
        }
        if ($workerId) {
            $insN = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)");
            $insN->execute([$workerId, 'Shift Reminder', 'Please ensure today\'s reports are completed.', 'warning']);
        }
        echo "Seeded sample notifications.<br>";
    }

    // Seed sample segregation reports for resident's apartment to power charts
    if ($resident_id) {
        // Get the resident's apartment id (A-101)
        $aptStmt = $pdo->prepare("SELECT id FROM apartments WHERE resident_id = ? LIMIT 1");
        $aptStmt->execute([$resident_id]);
        $resident_apartment = $aptStmt->fetch();

        if ($resident_apartment) {
            $apartment_id = $resident_apartment['id'];

            // Avoid duplicate seeding: check if there are recent reports already
            $check = $pdo->prepare("SELECT COUNT(*) FROM segregation_reports WHERE apartment_id = ? AND report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
            $check->execute([$apartment_id]);
            $existing_count = (int)$check->fetchColumn();

            if ($existing_count === 0) {
                // Get a worker id (any worker)
                $workerStmt = $pdo->prepare("SELECT id FROM users WHERE role='worker' LIMIT 1");
                $workerStmt->execute();
                $worker_row = $workerStmt->fetch();
                $worker_id = $worker_row ? $worker_row['id'] : null;

                if ($worker_id) {
                    $insert = $pdo->prepare("INSERT INTO segregation_reports (apartment_id, worker_id, status, report_date) VALUES (?,?,?,?)");

                    // Create reports for last 30 days with varied statuses
                    $statuses = ['segregated','partial','not','no_waste','segregated','segregated','partial'];
                    for ($i = 0; $i < 30; $i++) {
                        $date = (new DateTime())->modify("-{$i} day")->format('Y-m-d');
                        $status = $statuses[$i % count($statuses)];
                        $insert->execute([$apartment_id, $worker_id, $status, $date]);
                    }
                    echo "Seeded 30 days of sample segregation reports for resident apartment.<br>";
                } else {
                    echo "Warning: No worker found to attach sample reports.<br>";
                }
            } else {
                echo "Recent reports already exist for resident apartment; skipped seeding.<br>";
            }
        } else {
            echo "Warning: Resident has no apartment; skipped report seeding.<br>";
        }
    }

    echo "<br><b>Setup complete.</b><br>";
    echo "Worker: worker@example.com / 12345 <br>";
    echo "Resident: resident@example.com / 12345 <br>";
    echo "Now delete or protect setup.php for security.";
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
