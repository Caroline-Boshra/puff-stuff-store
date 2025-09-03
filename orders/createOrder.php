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


$shipping_address = trim(htmlspecialchars($_POST['shipping_address'] ?? ''));
$shipping_city    = trim(htmlspecialchars($_POST['shipping_city'] ?? ''));
$shipping_region  = trim(htmlspecialchars($_POST['shipping_region'] ?? ''));
$shipping_notes   = trim(htmlentities($_POST['shipping_notes'] ?? ''));
$payment_method   = trim(htmlspecialchars($_POST['payment_method'] ?? 'Cash on Delivery'));
$status           = 'Pending';


if (empty($shipping_address) || empty($shipping_city) || empty($shipping_region)) {
    msg("Please complete the shipping address details", 422);
}


$products = [];
$total_price = 0;

foreach ($_POST as $key => $value) {
    if (str_starts_with($key, 'product_')) {
        $item = json_decode($value, true);

        if (!isset($item['product_id'], $item['quantity'], $item['price'])) {
            msg("Each product must include: product_id, quantity, and price", 422);
        }

        $product_id = intval($item['product_id']);
        $quantity   = intval($item['quantity']);
        $price      = floatval($item['price']);

        
        $check = $conn->prepare("SELECT id FROM products WHERE id = ?");
        $check->bind_param("i", $product_id);
        $check->execute();
        $result = $check->get_result();
        if ($result->num_rows === 0) {
            msg("Product with ID $product_id not found", 400);
        }

        $products[] = [
            'product_id' => $product_id,
            'quantity'   => $quantity,
            'price'      => $price
        ];

        $total_price += $quantity * $price;
    }
}

if (empty($products)) {
    msg("No products found in order", 400);
}


$stmt = $conn->prepare("INSERT INTO orders (user_id, total_price, status, payment_method, shipping_address, shipping_city, shipping_region, shipping_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("idssssss", $user_id, $total_price, $status, $payment_method, $shipping_address, $shipping_city, $shipping_region, $shipping_notes);

if (!$stmt->execute()) {
    msg("Failed to place order", 500);
}

$order_id = $stmt->insert_id;


foreach ($products as $item) {
    $stmtDetail = $conn->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    $stmtDetail->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
    $stmtDetail->execute();
}


msg("Order created successfully", 200, [
    "order_id"    => $order_id,
    "total_price" => $total_price,
    "products"    => $products
]);