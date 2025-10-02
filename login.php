<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Swachh</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h2>Welcome to Swachh</h2>
                <p>Smart Waste Segregation Monitoring System</p>
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: var(--border-radius); border-left: 4px solid var(--primary-color);">
                    <h4 style="margin-bottom: 10px; color: var(--primary-color);">🌱 Join the Green Revolution</h4>
                    <p style="margin: 0; color: #666; font-size: 0.9rem;">
                        Monitor waste segregation, earn credits, and contribute to a cleaner environment. 
                        Track your progress and avoid penalties with our smart monitoring system.
                    </p>
                </div>
            </div>
            
            <form action="api/login.php" method="post" class="auth-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="role">Login As</label>
                    <select id="role" name="role" class="form-control">
                        <option value="worker">Waste Collection Worker</option>
                        <option value="resident">Resident</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary">Login</button>
                
                <div class="auth-footer">
                    <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
                    <p style="margin-top:8px; font-size: 0.9rem;">Admin? <a href="admin/index.php">Go to Admin Login</a></p>
                </div>
            </form>
            
            <?php if (isset($_SESSION['login_error'])): ?>
                <div class="error-messages">
                    <div class="error"><?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>



