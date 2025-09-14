<?php
require_once '../config/config.php';
require_once '../inc/connection.php';
require_once '../inc/function.php';
require_once '../inc/header.php';
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if (!$_SERVER['REQUEST_METHOD'] === 'POST') {
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
$product_id  = intval($_POST['product_id'] ?? 0);

if ($product_id <= 0) {
    msg("product_id is required", 400);
}

$stmt = $conn->prepare("DELETE FROM wishlist WHERE product_id = ? AND user_id = ?");
$stmt->bind_param("ii", $product_id, $user_id);


if ($stmt->execute() && $stmt->affected_rows > 0) {
    msg("Product removed from wishlist", 200);
} else {
    msg("Failed to remove product from wishlist", 500);
}