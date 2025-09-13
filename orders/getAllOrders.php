<?php

require_once '../inc/connection.php';
require_once '../inc/function.php';
require_once '../config/config.php';
require_once '../inc/header.php';
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    msg("Method Not Allowed", 405);
}

if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
    msg("Authorization token missing", 401);
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'];
$token = str_replace("Bearer ", "", $authHeader);

try {
    $decoded = JWT::decode($token, new Key($key, 'HS256'));

    // ✅ استخدمنا data->id بدل user_id
    $user_id = $decoded->data->id;

} catch (Exception $e) {
    msg("Invalid token", 401);
}

$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($order = $result->fetch_assoc()) {
    
    $detailsStmt = $conn->prepare("
        SELECT od.product_id, od.quantity, od.price, p.name AS product_name 
        FROM order_details od 
        JOIN products p ON od.product_id = p.id
        WHERE od.order_id = ?
    ");
    $detailsStmt->bind_param("i", $order['id']);
    $detailsStmt->execute();
    $detailsResult = $detailsStmt->get_result();

    $order['products'] = [];
    while ($detail = $detailsResult->fetch_assoc()) {
        $order['products'][] = $detail;
    }

    $orders[] = $order;
}

msg("Orders retrieved successfully", 200, $orders);