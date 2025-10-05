<?php
session_start();
require_once "../db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $role = $_POST["role"];

    // Case-insensitive email lookup to avoid casing mismatches
    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Allow both hashed and plaintext password comparisons to handle mismatched setups
    if ($user && (password_verify($password, $user["password"]) || $user["password"] === $password)) {
        if ($user["role"] !== $role) {
            $_SESSION['login_error'] = "Role mismatch! Please select the correct role.";
            header("Location: ../login.php");
            exit;
        }

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_name"] = $user["name"];
        $_SESSION["user_role"] = $user["role"];

        if ($role === "worker") {
            header("Location: ../workers/dashboard.php");
        } else {
            header("Location: ../residents/dashboard.php");
        }
        exit();
    } else {
        $_SESSION['login_error'] = "Invalid email or password. Please try again.";
        header("Location: ../login.php");
        exit();
    }
}
