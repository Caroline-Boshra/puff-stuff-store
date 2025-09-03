<?php

require_once '../inc/connection.php';
require_once '../inc/function.php';
require_once '../config/config.php';
require_once '../inc/header.php';
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    msg("Method Not Allowed", 405);
}



if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(["status" => 401, "message" => "Authorization token missing"]);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'];
$token = str_replace("Bearer ", "", $authHeader);

try {
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["status" => 401, "message" => "Invalid token"]);
    exit;
}



$order_id = $_POST['order_id'] ?? null;
$product_name = trim($_POST['product_name'] ?? '');
$address = trim($_POST['address'] ?? '');

if (!$order_id) {
    msg("Order ID is required", 400);
}


$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    msg("Order not found or you do not have permission to edit this order", 403);
}


$editable_statuses = ['pending', 'processing'];
if (!in_array($order['status'], $editable_statuses)) {
    msg("You cannot edit this order. Current status: {$order['status']}", 403);
}


$newProduct = $product_name ?: $order['product_name'];
$newAddress = $address ?: $order['address'];

$updateStmt = $conn->prepare("UPDATE orders SET product_name = ?, address = ? WHERE id = ?");
$updateStmt->execute([$newProduct, $newAddress, $order_id]);

msg("Order updated successfully", 200, [
    "order_id" => $order_id,
    "product_name" => $newProduct,
    "address" => $newAddress,
    "status" => $order['status']
]);