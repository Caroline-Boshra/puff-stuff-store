<?php
require_once '../inc/connection.php';
require_once '../inc/function.php';
require_once '../inc/header.php';
require_once '../config/config.php';
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    msg("Method Not Allowed", 405);
}

if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
    msg("Authorization token missing", 401);
}
$authHeader = $_SERVER['HTTP_AUTHORIZATION'];
$token = str_replace("Bearer ", "", $authHeader);

try {
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
} catch (Exception $e) {
    msg("Invalid token", 401);
}

if (!isset($decoded->data->type) || $decoded->data->type !== "user") {
    msg("Users only", 403);
}

$user_id    = $decoded->data->id;
$product_id = intval($_POST['product_id'] ?? 0);
$quantity   = intval($_POST['quantity'] ?? 1);

if ($product_id <= 0) msg("Product ID is required", 400);
if ($quantity <= 0) msg("Quantity must be greater than 0", 400);

$stmt = $conn->prepare("SELECT id, name, description, price, stock, image FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    msg("Product not found", 404);
}
$product = $res->fetch_assoc();

if ($product['stock'] < $quantity) {
    msg("Not enough stock", 400);
}

$stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
$stmt->bind_param("ii", $user_id, $product_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $newQty = $row['quantity'] + $quantity;
    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
    $stmt->bind_param("ii", $newQty, $row['id']);
    $stmt->execute();
    $product['quantity'] = $newQty;
} else {
    $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $user_id, $product_id, $quantity);
    $stmt->execute();
    $product['quantity'] = $quantity;
}


$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
$projectRoot = dirname(dirname($scriptName)); 
$baseUrl = rtrim($protocol . "://" . $_SERVER['HTTP_HOST'] . $projectRoot, '/') . '/uploads/';

$product['image'] = $product['image'] ? $baseUrl . $product['image'] : null;

msg("Product added to cart", 200, [
    "product" => $product
]);