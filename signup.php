<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Swachh</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h2>Join Swachh</h2>
                <p>Create your account to start monitoring waste segregation</p>
            </div>
            
            <div class="role-selector">
                <button type="button" class="role-btn active" data-role="resident">Resident</button>
                <button type="button" class="role-btn" data-role="worker">Worker</button>
            </div>
            
            <form id="signupForm" class="auth-form">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <input type="hidden" name="role" id="role" value="resident">
                
                <button type="submit" class="btn-primary">Create Account</button>
                
                <div class="auth-footer">
                    <p>Already have an account? <a href="index.php">Login here</a></p>
                </div>
            </form>
            
            <div id="errorMessages" class="error-messages"></div>
        </div>
    </div>
    
    <script>
        // Role selection
        document.querySelectorAll('.role-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById('role').value = btn.dataset.role;
            });
        });
        
        // Form submission
        document.getElementById('signupForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const errorDiv = document.getElementById('errorMessages');
            errorDiv.innerHTML = '';
            
            try {
                const response = await fetch('api/signup.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success === false) {
                    errorDiv.innerHTML = result.errors.map(error => `<div class="error">${error}</div>`).join('');
                } else {
                    // Redirect will be handled by the server response
                    window.location.reload();
                }
            } catch (error) {
                errorDiv.innerHTML = '<div class="error">Something went wrong. Please try again.</div>';
            }
        });
    </script>
</body>
</html>
