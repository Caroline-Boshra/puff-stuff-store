<?php
require_once '../config/config.php';
require_once '../inc/connection.php';
require_once '../inc/function.php';
require_once '../inc/header.php';
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    msg("Invalid token: " . $e->getMessage(), 401);
}

if ($role !== 'admin') {
    msg("Access denied, admin only", 403);
}

// ✅ يدعم JSON + form-data + x-www-form-urlencoded
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!$data) {
    parse_str($input, $data);
}

$order_id = intval($_POST['order_id'] ?? ($data['order_id'] ?? 0));
$status   = trim($_POST['status'] ?? ($data['status'] ?? ''));

$allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

if ($order_id <= 0 || $status === '') {
    msg("order_id and status are required", 400);
}

if (!in_array($status, $allowed_statuses)) {
    msg("Invalid status value", 400, ["allowed" => $allowed_statuses]);
}

$stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $order_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    msg("Order status updated successfully", 200, [
        "order_id" => $order_id,
        "new_status" => $status
    ]);
} else {
    msg("Failed to update order status", 500);
}