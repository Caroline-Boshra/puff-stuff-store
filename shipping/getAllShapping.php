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
    $type = $decoded->data->type ?? '';

    if ($type !== 'admin') {
        msg("Unauthorized, admin only", 403);
    }

} catch (Exception $e) {
    msg("Invalid token", 401);
}

$result = $conn->query("SELECT * FROM shipping_fees ORDER BY region ASC");
$fees = [];

while ($row = $result->fetch_assoc()) {
    $fees[] = $row;
}

msg("Shipping fees list", 200, $fees);