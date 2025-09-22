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

$product_id = intval($_POST['product_id'] ?? 0);
if ($product_id <= 0) {
    msg("Invalid product_id", 400);
}


$stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    msg("Product not found", 404);
}


$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$baseUrl .= "://" . $_SERVER['HTTP_HOST'] . "/uploads/";
$product['image'] = !empty($product['image']) ? $baseUrl . $product['image'] : null;


$check = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
$check->bind_param("ii", $user_id, $product_id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    msg("Product already in wishlist", 409);
}

$stmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
$stmt->bind_param("ii", $user_id, $product_id);

if ($stmt->execute()) {
    msg("Product added to wishlist", 200, [
        "wishlist_id" => $stmt->insert_id,
        "user_id"     => $user_id,
        "product_id"  => $product_id,
        "name"        => $product['name'],
        "price"       => $product['price'],
        "image"       => $product['image']
    ]);
} else {
    msg("Failed to add product to wishlist", 500);
}