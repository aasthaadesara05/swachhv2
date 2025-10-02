<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "worker") {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $apartment_id = $_POST["apartment_id"];
    $status = $_POST["status"];
    $worker_id = $_SESSION["user_id"];

    $stmt = $pdo->prepare("INSERT INTO segregation_reports (apartment_id, worker_id, status, report_date) VALUES (?, ?, ?, CURRENT_DATE)");
    $stmt->execute([$apartment_id, $worker_id, $status]);

    // update credits for resident
    $stmt = $pdo->prepare("SELECT resident_id FROM apartments WHERE id=?");
    $stmt->execute([$apartment_id]);
    $resident_id = $stmt->fetchColumn();

    if ($resident_id) {
        $delta = 0;
        if ($status === "segregated") $delta = 2;
        if ($status === "partial") $delta = 1;
        if ($status === "not") $delta = -3;

        $u = $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id=?");
        $u->execute([$delta, $resident_id]);
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
    exit();
}
