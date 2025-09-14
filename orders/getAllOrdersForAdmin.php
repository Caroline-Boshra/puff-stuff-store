<?php
require_once '../config/config.php';
require_once '../inc/connection.php';
require_once '../inc/function.php';
require_once '../inc/header.php';
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if (!$_SERVER['REQUEST_METHOD'] === 'GET') {
     msg("Method Not Allowed", 405);
}
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    msg("Authorization header missing", 401);
}

$jwt = str_replace('Bearer ', '', $headers['Authorization']);
try {
    $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
    $user_id = $decoded->data->id;
    $role = $decoded->data->type ?? 'user';  
} catch (Exception $e) {
    msg("Invalid token", 401);
}

    
if ($role !== 'admin') {
    msg("Access denied, admin only", 403);
}

    
$stmt = $conn->prepare("SELECT * FROM orders ORDER BY id DESC");
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

msg("Orders retrieved successfully", 200, $orders);


   