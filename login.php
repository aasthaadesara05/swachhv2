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
                    <h4 style="margin-bottom: 10px; color: var(--primary-color);">🌱 Ek Kadam Swachhta Ki Aur</h4>
                    <p style="margin: 0; color: #666; font-size: 0.9rem;">
                        Segregate waste, get responsible, earn credits, and help keep India clean.
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
                        <div style="display: flex; gap: 12px; margin-bottom: 10px;">
                            <button type="button" id="workerBtn" class="form-control btn-primary" value="worker">Worker</button>
                            <button type="button" id="residentBtn" class="form-control btn-primary" value="resident">Resident</button>
                        </div>
                        <input type="hidden" id="role" name="role" value="resident">

                        <script>
                            document.getElementById('workerBtn').onclick = function() {
                                document.getElementById('role').value = 'worker';
                                this.classList.add('selected');
                                document.getElementById('residentBtn').classList.remove('selected');
                            };
                            document.getElementById('residentBtn').onclick = function() {
                                document.getElementById('role').value = 'resident';
                                this.classList.add('selected');
                                document.getElementById('workerBtn').classList.remove('selected');
                            };
                        </script>
                        <style>
                            #workerBtn, #residentBtn {
                                    min-width: 120px;
                                    border-radius: 8px;
                                    font-size: 0.95rem;
                                    font-weight: 500;
                                    padding: 8px 0;
                                    transition: box-shadow 0.2s, border 0.2s;
                                    box-shadow: 0 4px 16px rgba(46,204,113,0.18);
                                    cursor: pointer;
                                }
                            #workerBtn.selected, #residentBtn.selected {
                                background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
                                color: #fff;
                                border: 2px solid var(--primary-color);
                                box-shadow: 0 4px 16px rgba(46,204,113,0.18);
                            }
                            #workerBtn:not(.selected), #residentBtn:not(.selected) {
                                background-color: #f8f9fa;
                                color: var(--primary-dark);
                                border: 2px solid #e0e0e0;
                            }
                        </style>
                        <!-- </select> -->
                    </div>
                    
                
                <button type="submit" class="btn-primary">Login</button>
                
                <div class="auth-footer">
                    <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
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



