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




$product_id = intval($_POST['product_id'] ?? 0);
if (!$product_id) msg("Product ID is required", 400);


$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    msg("Product not found", 404);
}
$product = $res->fetch_assoc();


$stmtVar = $conn->prepare("DELETE FROM product_variants WHERE product_id = ?");
$stmtVar->bind_param("i", $product_id);
$stmtVar->execute();


$imagePath = '../uploads/' . $product['image'];
if ($product['image'] && file_exists($imagePath)) {
    unlink($imagePath);
}


$stmtDel = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmtDel->bind_param("i", $product_id);
if ($stmtDel->execute()) {
    msg("Product deleted successfully", 200);
} else {
    msg("Failed to delete product", 500);
}