<?php
require_once '../config/config.php';
require_once '../inc/connection.php';
require_once '../inc/function.php';
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
    $user_id = $decoded->user_id ?? $decoded->data->id ?? null;
    if (!$user_id) {
        msg("Invalid token payload", 401);
    }
} catch (Exception $e) {
    msg("Invalid token", 401);
}


$stmt = $conn->prepare("
    SELECT w.id as wishlist_id, p.id as product_id, p.name, p.price, p.image
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    WHERE w.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

$wishlist = [];
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$baseUrl .= "://" . $_SERVER['HTTP_HOST'] . "/traffic/uploads/";

while ($row = $res->fetch_assoc()) {
    $wishlist[] = [
        "wishlist_id" => $row['wishlist_id'],
        "product_id"  => $row['product_id'],
        "name"        => $row['name'],
        "price"       => $row['price'],
        "image"       => !empty($row['image']) ? $baseUrl . $row['image'] : null
    ];
}

msg("Wishlist fetched successfully", 200, $wishlist);