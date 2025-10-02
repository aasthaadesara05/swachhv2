<?php
session_start();
require_once "../db.php";

// Check if user is logged in as worker
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'worker') {
    header("Location: ../login.php");
    exit();
}

// Get worker info
$worker_id = $_SESSION['user_id'];
$worker_name = $_SESSION['user_name'];

// Get selected date (default to today)
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get all societies
$societies_stmt = $pdo->query("SELECT * FROM societies ORDER BY name");
$societies = $societies_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected society
$selected_society = isset($_GET['society_id']) ? (int)$_GET['society_id'] : null;

// Get blocks for selected society
$blocks = [];
if ($selected_society) {
    $blocks_stmt = $pdo->prepare("SELECT * FROM blocks WHERE society_id = ? ORDER BY name");
    $blocks_stmt->execute([$selected_society]);
    $blocks = $blocks_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get apartments for selected block
$apartments = [];
$selected_block = isset($_GET['block_id']) ? (int)$_GET['block_id'] : null;
if ($selected_block) {
    $apartments_stmt = $pdo->prepare("
        SELECT a.*, u.name as resident_name, sr.status, sr.id as report_id
        FROM apartments a 
        LEFT JOIN users u ON a.resident_id = u.id
        LEFT JOIN segregation_reports sr ON a.id = sr.apartment_id AND sr.report_date = ? AND sr.worker_id = ?
        WHERE a.block_id = ?
        ORDER BY a.apt_number
    ");
    $apartments_stmt->execute([$selected_date, $worker_id, $selected_block]);
    $apartments = $apartments_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_report') {
    $apartment_id = (int)$_POST['apartment_id'];
    $status = $_POST['status'];
    
    // Check if report already exists
    $check_stmt = $pdo->prepare("SELECT id FROM segregation_reports WHERE apartment_id = ? AND report_date = ? AND worker_id = ?");
    $check_stmt->execute([$apartment_id, $selected_date, $worker_id]);
    $existing_report = $check_stmt->fetch();
    
    if ($existing_report) {
        // Update existing report
        $update_stmt = $pdo->prepare("UPDATE segregation_reports SET status = ? WHERE id = ?");
        $update_stmt->execute([$status, $existing_report['id']]);
    } else {
        // Insert new report
        $insert_stmt = $pdo->prepare("INSERT INTO segregation_reports (apartment_id, worker_id, status, report_date) VALUES (?, ?, ?, ?)");
        $insert_stmt->execute([$apartment_id, $worker_id, $status, $selected_date]);
    }
    
    // Redirect to refresh the page
    header("Location: ?" . http_build_query($_GET));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker Dashboard - Swachh</title>
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
                <li><a href="#" class="active">Dashboard</a></li>
                <li><a href="reports.php">Historical Reports</a></li>
                <li><a href="../api/logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Waste Segregation Monitoring</h1>
                <div class="user-info">
                    <a href="notifications.php" class="notif-bell" title="Notifications">
                        🔔
                        <span id="notifBadgeWorker" class="notif-badge">0</span>
                    </a>
                    <div class="user-avatar"><?php echo strtoupper(substr($worker_name, 0, 1)); ?></div>
                    <a href="../api/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <!-- Date Selection -->
            <div class="card">
                <div class="card-header">
                    <h3>Select Date</h3>
                </div>
                <form method="GET" class="form-inline">
                    <div class="form-group">
                        <label>Date:</label>
                        <input type="date" name="date" value="<?php echo $selected_date; ?>" class="form-control">
                    </div>
                    <?php if ($selected_society): ?>
                        <input type="hidden" name="society_id" value="<?php echo $selected_society; ?>">
                    <?php endif; ?>
                    <?php if ($selected_block): ?>
                        <input type="hidden" name="block_id" value="<?php echo $selected_block; ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-action">Update</button>
                </form>
            </div>
            
            <!-- Society Selection -->
            <div class="card">
                <div class="card-header">
                    <h3>Select Society</h3>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 12px; justify-content: flex-start;">
                    <?php foreach ($societies as $society): ?>
                        <button type="button" 
                           class="btn btn-society <?php echo $selected_society == $society['id'] ? 'btn-primary' : 'btn-secondary'; ?> society-btn"
                           data-society-id="<?php echo $society['id']; ?>"
                           data-society-name="<?php echo htmlspecialchars($society['name']); ?>">
                            <?php echo htmlspecialchars($society['name']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Block Selection -->
            <div class="card" id="block-selection" style="display: none;">
                <div class="card-header">
                    <h3>Select Block for <span id="selected-society-name">Society</span></h3>
                </div>
                <div id="blocks-container" style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-start;">
                    <!-- Blocks will be loaded here dynamically -->
                </div>
            </div>
            
            <!-- Apartment Reports -->
            <div class="card" id="apartment-reports" style="display: none;">
                <div class="card-header">
                    <h3 id="apartment-reports-title">Apartment Reports</h3>
                </div>
                <div id="apartments-container">
                    <!-- Apartments will be loaded here dynamically -->
                </div>
            </div>
            
            
            <?php if ($selected_society && empty($blocks)): ?>
            <div class="card">
                <div class="error-messages">
                    <div class="error">No blocks found for the selected society.</div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!$selected_society): ?>
            <div class="card">
                <div style="text-align: center; padding: 40px; color: #666;">
                    <h3>Welcome to Swachh Worker Dashboard</h3>
                    <p>Select a society above to start monitoring waste segregation.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        if (window.initNotificationBell) {
            window.initNotificationBell('../api/get_notifications.php', 'notifBadgeWorker', 15000);
        }
        
        // Handle society selection
        document.addEventListener('DOMContentLoaded', function() {
            const societyButtons = document.querySelectorAll('.society-btn');
            const blockSelection = document.getElementById('block-selection');
            const blocksContainer = document.getElementById('blocks-container');
            const selectedSocietyName = document.getElementById('selected-society-name');
            
            societyButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const societyId = this.getAttribute('data-society-id');
                    const societyName = this.getAttribute('data-society-name');
                    
                    // Update active society button
                    societyButtons.forEach(btn => {
                        btn.classList.remove('btn-primary');
                        btn.classList.add('btn-secondary');
                    });
                    this.classList.remove('btn-secondary');
                    this.classList.add('btn-primary');
                    
                    // Update society name in block selection
                    selectedSocietyName.textContent = societyName;
                    
                    // Show loading state
                    blocksContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;">Loading blocks...</div>';
                    blockSelection.style.display = 'block';
                    
                    // Fetch blocks for selected society
                    fetch(`../api/get_blocks.php?society_id=${societyId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                blocksContainer.innerHTML = `<div style="text-align: center; padding: 20px; color: #e74c3c;">Error: ${data.error}</div>`;
                                return;
                            }
                            
                            if (data.blocks.length === 0) {
                                blocksContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;">No blocks found for this society.</div>';
                                return;
                            }
                            
                            // Render blocks
                            let blocksHtml = '';
                            data.blocks.forEach(block => {
                                blocksHtml += `
                                    <button type="button" 
                                           class="btn btn-block btn-secondary block-btn"
                                           data-block-id="${block.id}"
                                           data-society-id="${societyId}"
                                           data-block-name="${block.name}">
                                        ${block.name}
                                    </button>
                                `;
                            });
                            blocksContainer.innerHTML = blocksHtml;
                            
                            // Add click handlers to block buttons
                            const blockButtons = document.querySelectorAll('.block-btn');
                            blockButtons.forEach(blockBtn => {
                                blockBtn.addEventListener('click', function() {
                                    const blockId = this.getAttribute('data-block-id');
                                    const societyId = this.getAttribute('data-society-id');
                                    const blockName = this.getAttribute('data-block-name');
                                    const selectedDate = document.querySelector('input[name="date"]').value;
                                    
                                    // Update active block button
                                    blockButtons.forEach(btn => {
                                        btn.classList.remove('btn-primary');
                                        btn.classList.add('btn-secondary');
                                    });
                                    this.classList.remove('btn-secondary');
                                    this.classList.add('btn-primary');
                                    
                                    // Load apartments for selected block
                                    loadApartments(blockId, selectedDate, blockName);
                                });
                            });
                        })
                        .catch(error => {
                            console.error('Error fetching blocks:', error);
                            blocksContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #e74c3c;">Error loading blocks. Please try again.</div>';
                        });
                });
            });
            
            // Function to load apartments for a selected block
            window.loadApartments = function(blockId, selectedDate, blockName) {
                const apartmentReports = document.getElementById('apartment-reports');
                const apartmentsContainer = document.getElementById('apartments-container');
                const apartmentReportsTitle = document.getElementById('apartment-reports-title');
                
                // Show loading state
                apartmentReportsTitle.textContent = `Loading apartments for ${blockName}...`;
                apartmentsContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;">Loading apartments...</div>';
                apartmentReports.style.display = 'block';
                
                // Fetch apartments for selected block
                fetch(`../api/get_apartments.php?block_id=${blockId}&date=${selectedDate}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            apartmentsContainer.innerHTML = `<div style="text-align: center; padding: 20px; color: #e74c3c;">Error: ${data.error}</div>`;
                            return;
                        }
                        
                        if (!data.apartments || data.apartments.length === 0) {
                            apartmentsContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;">No apartments found for this block.</div>';
                            apartmentReportsTitle.textContent = `No Apartments in ${blockName}`;
                            return;
                        }
                        
                        // Update title
                        const dateFormatted = new Date(selectedDate).toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'short', 
                            day: 'numeric' 
                        });
                        apartmentReportsTitle.textContent = `Apartment Reports - ${blockName} - ${dateFormatted}`;
                        
                        // Build apartments table
                        let apartmentsHtml = `
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Apartment</th>
                                            <th>Resident</th>
                                            <th>Current Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        
                        data.apartments.forEach(apt => {
                            let statusBadge = '';
                            if (apt.status) {
                                statusBadge = `<span class="status-badge status-${apt.status}">${apt.status.charAt(0).toUpperCase() + apt.status.slice(1).replace('_', ' ')}</span>`;
                            } else {
                                statusBadge = '<span class="status-badge" style="background: #e9ecef; color: #6c757d;">Not Reported</span>';
                            }
                            
                            apartmentsHtml += `
                                <tr>
                                    <td><strong>${apt.apt_number}</strong></td>
                                    <td>${apt.resident_name || 'No Resident'}</td>
                                    <td>${statusBadge}</td>
                                    <td>
                                        <select class="form-control status-select" style="width: auto; display: inline-block; margin-right: 10px;" data-apartment-id="${apt.id}">
                                            <option value="segregated" ${apt.status === 'segregated' ? 'selected' : ''}>Segregated</option>
                                            <option value="partial" ${apt.status === 'partial' ? 'selected' : ''}>Partial</option>
                                            <option value="not" ${apt.status === 'not' ? 'selected' : ''}>Not Segregated</option>
                                            <option value="no_waste" ${apt.status === 'no_waste' ? 'selected' : ''}>No Waste</option>
                                        </select>
                                        <button type="button" class="btn btn-action btn-success save-report-btn" data-apartment-id="${apt.id}">Save</button>
                                    </td>
                                </tr>
                            `;
                        });
                        
                        apartmentsHtml += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                        
                        apartmentsContainer.innerHTML = apartmentsHtml;
                        
                        // Add event listeners to save buttons
                        const saveButtons = document.querySelectorAll('.save-report-btn');
                        saveButtons.forEach(btn => {
                            btn.addEventListener('click', function() {
                                const apartmentId = this.getAttribute('data-apartment-id');
                                const statusSelect = document.querySelector(`select[data-apartment-id="${apartmentId}"]`);
                                const status = statusSelect.value;
                                
                                saveReport(apartmentId, status, selectedDate, this);
                            });
                        });
                    })
                    .catch(error => {
                        console.error('Error fetching apartments:', error);
                        apartmentsContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #e74c3c;">Error loading apartments. Please try again.</div>';
                    });
            };
            
            // Function to save a report
            window.saveReport = function(apartmentId, status, date, buttonElement) {
                const originalText = buttonElement.textContent;
                buttonElement.textContent = 'Saving...';
                buttonElement.disabled = true;
                
                fetch('../api/save_report_ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        apartment_id: apartmentId,
                        status: status,
                        date: date
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        buttonElement.textContent = 'Saved!';
                        buttonElement.style.backgroundColor = '#27ae60';
                        
                        // Update the status badge in the same row
                        const row = buttonElement.closest('tr');
                        const statusCell = row.children[2]; // Third cell (Current Status)
                        const statusClass = 'status-' + status;
                        const statusText = status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ');
                        statusCell.innerHTML = `<span class="status-badge ${statusClass}">${statusText}</span>`;
                        
                        setTimeout(() => {
                            buttonElement.textContent = originalText;
                            buttonElement.style.backgroundColor = '';
                            buttonElement.disabled = false;
                        }, 2000);
                    } else {
                        buttonElement.textContent = 'Error';
                        buttonElement.style.backgroundColor = '#e74c3c';
                        setTimeout(() => {
                            buttonElement.textContent = originalText;
                            buttonElement.style.backgroundColor = '';
                            buttonElement.disabled = false;
                        }, 2000);
                    }
                })
                .catch(error => {
                    console.error('Error saving report:', error);
                    buttonElement.textContent = 'Error';
                    buttonElement.style.backgroundColor = '#e74c3c';
                    setTimeout(() => {
                        buttonElement.textContent = originalText;
                        buttonElement.style.backgroundColor = '';
                        buttonElement.disabled = false;
                    }, 2000);
                });
            };
        });
    </script>
</body>
</html>