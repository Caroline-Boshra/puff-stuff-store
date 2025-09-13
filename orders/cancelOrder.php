<?php

require_once '../inc/connection.php';
require_once '../inc/function.php';
require_once '../config/config.php';
require_once '../inc/header.php';
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    msg("Method Not Allowed", 405);
}

if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
    msg("Authorization token missing", 401);
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'];
$token = str_replace("Bearer ", "", $authHeader);

try {
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $user_id = $decoded->user_id ?? $decoded->data->id ?? null;
    if (!$user_id) {
        msg("Invalid token payload", 401);
    }

} catch (Exception $e) {
    msg("Invalid token", 401);
}


$data = json_decode(file_get_contents("php://input"), true);
$order_id = $data['order_id'] ?? $_POST['order_id'] ?? null;

if (!$order_id) {
    msg("Order ID is required", 400);
}



$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    msg("Order not found or not yours", 403);
}


if ($order['status'] !== 'Pending') {
    msg("You cannot cancel this order. Current status: {$order['status']}", 403);
}


$order_time = strtotime($order['created_at']);
$current_time = time();
$diff = $current_time - $order_time;

if ($diff > 7200) {
    msg("Cancellation time expired. Orders can only be cancelled within 2 hours.", 403);
}


$updateStmt = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ?");
$updateStmt->bind_param("i", $order_id);

if ($updateStmt->execute()) {
    msg("Order cancelled successfully", 200, [
        "order_id" => $order_id,
        "status" => "Cancelled"
    ]);
} else {
    msg("Failed to cancel order", 500);
}