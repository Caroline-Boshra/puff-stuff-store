<?php

require_once '../inc/connection.php';
require_once '../inc/function.php';
require_once '../config/config.php';
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
    $user_id = $decoded->data->id ?? null;
    if (!$user_id) {
        msg("Invalid token payload", 401);
    }
} catch (Exception $e) {
    msg("Invalid token", 401);
}


$order_id         = $_POST['order_id'] ?? null;
$shipping_address = trim($_POST['shipping_address'] ?? '');
$shipping_city    = trim($_POST['shipping_city'] ?? '');
$shipping_region  = trim($_POST['shipping_region'] ?? '');
$shipping_notes   = trim($_POST['shipping_notes'] ?? '');

if (!$order_id) {
    msg("Order ID is required", 400);
}


$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    msg("Order not found or not yours", 403);
}

if ($order['status'] !== 'Pending') {
    msg("You cannot edit this order. Current status: {$order['status']}", 403);
}


$new_shipping_address = !empty($shipping_address) ? $shipping_address : $order['shipping_address'];
$new_shipping_city    = !empty($shipping_city) ? $shipping_city : $order['shipping_city'];
$new_shipping_region  = !empty($shipping_region) ? $shipping_region : $order['shipping_region'];
$new_shipping_notes   = !empty($shipping_notes) ? $shipping_notes : $order['shipping_notes'];


$updateStmt = $conn->prepare("
    UPDATE orders 
    SET shipping_address = ?, shipping_city = ?, shipping_region = ?, shipping_notes = ? 
    WHERE id = ?
");
$updateStmt->bind_param("ssssi", $new_shipping_address, $new_shipping_city, $new_shipping_region, $new_shipping_notes, $order_id);

if (!$updateStmt->execute()) {
    msg("Failed to update order", 500);
}


$shipping_fee = 0;
$stmtFee = $conn->prepare("SELECT fee FROM shipping_fees WHERE LOWER(region) = LOWER(?)");
$stmtFee->bind_param("s", $new_shipping_region);
$stmtFee->execute();
$feeRes = $stmtFee->get_result()->fetch_assoc();
if ($feeRes) {
    $shipping_fee = floatval($feeRes['fee']);
}


$userStmt = $conn->prepare("SELECT id, name, email, phone FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

$detailsStmt = $conn->prepare("
    SELECT od.product_id, od.quantity, od.price, p.name AS product_name 
    FROM order_details od 
    JOIN products p ON od.product_id = p.id
    WHERE od.order_id = ?
");
$detailsStmt->bind_param("i", $order_id);
$detailsStmt->execute();
$detailsResult = $detailsStmt->get_result();

$products = [];
$total_price = 0;
$productList = "";

while ($detail = $detailsResult->fetch_assoc()) {
    $subtotal = $detail['quantity'] * $detail['price'];
    $products[] = [
        "product_id"   => $detail['product_id'],
        "quantity"     => $detail['quantity'],
        "price"        => $detail['price'],
        "product_name" => $detail['product_name'],
        "subtotal"     => $subtotal
    ];
    $total_price += $subtotal;
    $productList .= "<li>{$detail['product_name']} - Qty: {$detail['quantity']} - Price: {$subtotal} EGP</li>";
}

$finalData = [
    "order_id"                 => $order_id,
    "shipping_address"         => $new_shipping_address,
    "shipping_city"            => $new_shipping_city,
    "shipping_region"          => $new_shipping_region,
    "shipping_notes"           => $new_shipping_notes,
    "shipping_fee"             => $shipping_fee,
    "status"                   => $order['status'],
    "products"                 => $products,
    "total_price_without_shipping" => $total_price,
    "total_price_with_shipping"    => $total_price + $shipping_fee
];


// $emailContent = "
//     <h2>Order Updated</h2>
//     <p><strong>Order ID:</strong> {$order_id}</p>
//     <p><strong>Customer:</strong> {$user['name']} ({$user['email']} - {$user['phone']})</p>
//     <p><strong>Shipping Address:</strong> {$new_shipping_address}, {$new_shipping_city}, {$new_shipping_region}</p>
//     <p><strong>Notes:</strong> {$new_shipping_notes}</p>
//     <p><strong>Shipping Fee:</strong> {$shipping_fee} EGP</p>
//     <h3>Products:</h3>
//     <ul>{$productList}</ul>
//     <p><strong>Status:</strong> {$order['status']}</p>
// ";
// sendEmail($user['email'], "Your Order #{$order_id}", $emailContent);
// sendEmail("karolingeorge2011@gmail.com", "Order Updated #{$order_id}", $emailContent);


msg("Order updated successfully", 200, $finalData);

?>
