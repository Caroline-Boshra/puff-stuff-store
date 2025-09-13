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

$cart_id = intval($_POST['cart_id'] ?? 0);
if ($cart_id <= 0) msg("Invalid cart id", 400);

$delete = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
$delete->bind_param("ii", $cart_id, $user_id);

if ($delete->execute() && $delete->affected_rows > 0) {
    msg("Cart item removed", 200);
} else {
    msg("Cart item not found", 404);
}