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

$order_id = intval($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    msg("Invalid or missing order_id", 400);
}


$stmtOrder = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmtOrder->bind_param("ii", $order_id, $user_id);
$stmtOrder->execute();
$order = $stmtOrder->get_result()->fetch_assoc();

if (!$order) {
    msg("Order not found", 404);
}


$stmtDetails = $conn->prepare("
    SELECT od.product_id, od.quantity, od.price, p.name, p.image
    FROM order_details od
    JOIN products p ON od.product_id = p.id
    WHERE od.order_id = ?
");
$stmtDetails->bind_param("i", $order_id);
$stmtDetails->execute();
$resDetails = $stmtDetails->get_result();

$products = [];
$total_price = 0;

while ($row = $resDetails->fetch_assoc()) {
    $subtotal = $row['quantity'] * $row['price'];
    $products[] = [
        'product_id' => $row['product_id'],
        'name'       => $row['name'],
        'image'      => $row['image'],
        'quantity'   => $row['quantity'],
        'price'      => $row['price'],
        'subtotal'   => $subtotal
    ];
    $total_price += $subtotal;
}


$shipping_fee = 0;
$stmtFee = $conn->prepare("SELECT fee FROM shipping_fees WHERE LOWER(region) = LOWER(?)");
$stmtFee->bind_param("s", $order['shipping_region']);
$stmtFee->execute();
$feeRes = $stmtFee->get_result()->fetch_assoc();
if ($feeRes) {
    $shipping_fee = floatval($feeRes['fee']);
}

msg("Order confirmation", 200, [
    "order_id"      => $order['id'],
    "status"        => $order['status'],
    "payment_method"=> $order['payment_method'],
    "shipping"      => [
        "address" => $order['shipping_address'],
        "city"    => $order['shipping_city'],
        "region"  => $order['shipping_region'],
        "notes"   => $order['shipping_notes'],
        "fee"     => $shipping_fee
    ],
    "total_price_without_shipping" => $total_price,
    "total_price_with_shipping"    => $total_price + $shipping_fee,
    "products"      => $products,
    "created_at"    => $order['created_at']
]);