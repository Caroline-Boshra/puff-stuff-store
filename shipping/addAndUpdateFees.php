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

    $type = $decoded->data->type ?? '';

    if ($type !== 'admin') {
        msg("Unauthorized, admin only", 403);
    }

} catch (Exception $e) {
    msg("Invalid token", 401);
}



$region = trim($_POST['region'] ?? '');
$fee    = floatval($_POST['fee'] ?? 0);

if (empty($region)) {
    msg("Region is required", 422);
}


$stmt = $conn->prepare("
    INSERT INTO shipping_fees (region, fee) 
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE fee=?
");
$stmt->bind_param("sdd", $region, $fee, $fee);

if ($stmt->execute()) {
    msg("Shipping fee added/updated successfully", 200, [
        "region" => $region,
        "fee"    => $fee
    ]);
} else {
    msg("Failed to add/update shipping fee", 500);
}