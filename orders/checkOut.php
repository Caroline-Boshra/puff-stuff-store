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
    $user_id = $decoded->user_id ?? $decoded->data->id ?? null;
    if (!$user_id) {
        msg("Invalid token payload", 401);
    }
} catch (Exception $e) {
    msg("Invalid token", 401);
}

$shipping_address = trim($_POST['shipping_address'] ?? '');
$shipping_city    = trim($_POST['shipping_city'] ?? '');
$shipping_region  = trim($_POST['shipping_region'] ?? '');
$shipping_notes   = trim($_POST['shipping_notes'] ?? '');
$payment_method   = trim($_POST['payment_method'] ?? 'Cash on Delivery');
$status           = 'Pending';

if (empty($shipping_address) || empty($shipping_city) || empty($shipping_region)) {
    msg("Please complete the shipping address details", 422);
}

$userStmt = $conn->prepare("SELECT id, name, email, phone FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

$products = [];
$total_price = 0;

$query = "
    SELECT c.product_id, c.quantity, p.name, p.price
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
";
$stmtCart = $conn->prepare($query);
$stmtCart->bind_param("i", $user_id);
$stmtCart->execute();
$res = $stmtCart->get_result();

while ($row = $res->fetch_assoc()) {
    $subtotal = $row['quantity'] * $row['price'];
    $products[] = [
        'product_id' => $row['product_id'],
        'name'       => $row['name'],
        'quantity'   => $row['quantity'],
        'price'      => $row['price'],
        'subtotal'   => $subtotal
    ];
    $total_price += $subtotal;
}

// تعديل الاستعلام ليصبح غير حساس لحالة الحروف
$shipping_fee = 0;
$stmtFee = $conn->prepare("SELECT fee FROM shipping_fees WHERE LOWER(region) = LOWER(?)");
$stmtFee->bind_param("s", $shipping_region);
$stmtFee->execute();
$feeRes = $stmtFee->get_result()->fetch_assoc();

if ($feeRes) {
    $shipping_fee = floatval($feeRes['fee']);
} else {
    msg("Shipping fee for selected region not found", 422);
}

$total_price += $shipping_fee;

if (empty($products)) {
    msg("Cart is empty", 400);
}

$stmt = $conn->prepare("INSERT INTO orders (user_id, total_price, status, payment_method, shipping_address, shipping_city, shipping_region, shipping_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("idssssss", $user_id, $total_price, $status, $payment_method, $shipping_address, $shipping_city, $shipping_region, $shipping_notes);
if (!$stmt->execute()) {
    msg("Failed to place order", 500);
}
$order_id = $stmt->insert_id;

$stmtAddress = $conn->prepare("
    INSERT INTO user_addresses (user_id, address, city, region, notes, is_default)
    VALUES (?, ?, ?, ?, ?, 1)
");
$stmtAddress->bind_param("issss", $user_id, $shipping_address, $shipping_city, $shipping_region, $shipping_notes);
$stmtAddress->execute();

foreach ($products as $item) {
    $stmtDetail = $conn->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    $stmtDetail->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
    $stmtDetail->execute();
}

$deleteCart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
$deleteCart->bind_param("i", $user_id);
$deleteCart->execute();

$productList = "";
foreach ($products as $p) {
    $productList .= "<li>{$p['name']} - Qty: {$p['quantity']} - Price: {$p['subtotal']} EGP</li>";
}

// $emailContent = "
//     <h2>Order Confirmation</h2>
//     <p><strong>Order ID:</strong> {$order_id}</p>
//     <p><strong>Customer:</strong> {$user['name']} ({$user['email']} - {$user['phone']})</p>
//     <p><strong>Shipping Address:</strong> {$shipping_address}, {$shipping_city}, {$shipping_region}</p>
//     <p><strong>Payment Method:</strong> {$payment_method}</p>
//     <p><strong>Total Price:</strong> {$total_price} EGP</p>
//     <h3>Products:</h3>
//     <ul>{$productList}</ul>
//     <p><strong>Notes:</strong> {$shipping_notes}</p>
// ";

// sendEmail($user['email'], "Your Order #{$order_id}", $emailContent);
// sendEmail("karolingeorge2011@gmail.com", "New Order #{$order_id}", $emailContent);

$total_price_with_shipping = $total_price;

msg("Order created successfully", 200, [
    "order_id"             => $order_id,
    "total_price"          => $total_price_with_shipping,
    "products"             => $products,
    "shipping_fee"         => $shipping_fee, 
    "subtotal_without_shipping" => $total_price - $shipping_fee, 
    "shipping"             => [
        "address" => $shipping_address,
        "city"    => $shipping_city,
        "region"  => $shipping_region,
        "notes"   => $shipping_notes
    ],
    "customer"             => $user
]);
?>
