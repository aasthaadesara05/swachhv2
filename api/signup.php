<?php
session_start();
require_once "../db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $role = $_POST["role"];
    
    // Validation
    $errors = [];
    
    if (empty($name) || strlen($name) < 2) {
        $errors[] = "Name must be at least 2 characters long";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    if (strlen($password) < 5) {
        $errors[] = "Password must be at least 5 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (!in_array($role, ['worker', 'resident'])) {
        $errors[] = "Invalid role selected";
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = "Email already registered";
    }
    
    if (empty($errors)) {
        try {
            // Insert new user with plaintext password (as requested)
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, credits) VALUES (?, ?, ?, ?, ?)");
            $initial_credits = ($role === 'resident') ? 100 : 0; // Residents start with 100 credits
            $stmt->execute([$name, $email, $password, $role, $initial_credits]);
            
            // Auto-login after successful signup
            $user_id = $pdo->lastInsertId();
            $_SESSION["user_id"] = $user_id;
            $_SESSION["user_name"] = $name;
            $_SESSION["user_role"] = $role;
            
            $redirect_url = ($role === 'worker') ? '../workers/dashboard.php' : '../residents/dashboard.php';
            header("Location: $redirect_url");
            exit();
            
        } catch (PDOException $e) {
            $errors[] = "Database error. Please try again.";
        }
    }
    
    // Return errors as JSON for AJAX handling
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit();
    }
}
?>
