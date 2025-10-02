<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resident Login - Swachh</title>
</head>
<body>
    <h2>Resident Login</h2>
    <form method="post" action="../api/login.php">
        <input type="hidden" name="role" value="resident">
        <label>Email:</label><br>
        <input type="email" name="email" required><br><br>
        <label>Password:</label><br>
        <input type="password" name="password" required><br><br>
        <button type="submit">Login</button>
    </form>
</body>
</html>
