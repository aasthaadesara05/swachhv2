<?php
// api/get_societies.php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

$stmt = $pdo->query("SELECT id, name FROM societies ORDER BY name");
$rows = $stmt->fetchAll();
echo json_encode($rows);
