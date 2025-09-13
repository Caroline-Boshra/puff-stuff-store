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


$cart_id  = intval($_POST['cart_id'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 1);

if ($cart_id <= 0) msg("Invalid cart id", 400);
if ($quantity <= 0) msg("Quantity must be at least 1", 400);


$check = $conn->prepare("SELECT * FROM cart WHERE id = ? AND user_id = ?");
$check->bind_param("ii", $cart_id, $user_id);
$check->execute();
$res = $check->get_result();
if ($res->num_rows == 0) {
    msg("Cart item not found for this user", 404);
}


$update = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
$update->bind_param("iii", $quantity, $cart_id, $user_id);
$update->execute();

if ($update->affected_rows > 0) {
    $stmt = $conn->prepare("
        SELECT 
            c.id as cart_id,
            c.quantity,
            p.id as product_id,
            p.name,
            p.description,
            p.price,
            p.stock
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.id = ? AND c.user_id = ?
    ");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $item = $res->fetch_assoc();

    msg("Cart updated successfully", 200, $item);
} else {
    msg("Nothing changed (maybe same quantity)", 200);
}