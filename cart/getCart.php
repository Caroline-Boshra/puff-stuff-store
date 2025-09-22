<?php
require_once '../inc/connection.php';
require_once '../inc/function.php';
require_once '../inc/header.php';
require_once '../config/config.php'; 
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if (!isset($_SERVER['HTTP_AUTHORIZATION'])) msg("Authorization token missing", 401);
$authHeader = $_SERVER['HTTP_AUTHORIZATION'];
$token = str_replace("Bearer ", "", $authHeader);

try {
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $user_id = $decoded->data->id;
} catch (Exception $e) {
    msg("Invalid token", 401);
}

$query = "
    SELECT c.id, c.quantity, p.id as product_id, p.name, p.price, p.image
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
$total = 0;


$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
$projectRoot = dirname(dirname($scriptName)); 
$baseUrl = rtrim($protocol . "://" . $_SERVER['HTTP_HOST'] . $projectRoot, '/') . '/uploads/';

while ($row = $res->fetch_assoc()) {
    $row['image'] = $row['image'] ? $baseUrl . $row['image'] : null;
    $row['subtotal'] = $row['quantity'] * $row['price'];
    $total += $row['subtotal'];
    $items[] = $row;
}


if (empty($items)) {
    msg("Cart is empty", 200);
} else {
    msg("Cart fetched", 200, [
        "is_empty" => false,
        "items"    => $items,
        "total"    => $total
    ]);
}