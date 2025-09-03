<?php

require_once '../inc/connection.php';
require_once '../inc/function.php';
require_once '../inc/header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    msg("Method Not Allowed", 405);
}


$product_id = intval($_GET['id'] ?? 0);
if (!$product_id) {
    msg("Product ID is required", 400);
}


$stmt = $conn->prepare("
    SELECT p.id,p.name,p.description,p.image,p.price,p.stock,c.name AS category_name,c.image AS category_image,b.name AS brand_name
    FROM products p LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id WHERE p.id = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    msg("Product not found", 404);
}

$row = $res->fetch_assoc();

$product = [
    "product_id"     => $row['id'],
    "name"           => $row['name'],
    "description"    => $row['description'],
    "image"          => $row['image'],
    "price"          => $row['price'],
    "stock"          => $row['stock'],
    "category_name"  => $row['category_name'],
    "category_image" => $row['category_image'],
    "brand_name"     => $row['brand_name'],
];


if (strtolower($row['category_name']) === 'liquide') {
    $variantStmt = $conn->prepare("SELECT flavor, size, nicotine FROM product_variants WHERE product_id = ?");
    $variantStmt->bind_param("i", $row['id']);
    $variantStmt->execute();
    $variantRes = $variantStmt->get_result();

    $variants = [];
    while ($v = $variantRes->fetch_assoc()) {
        $variants[] = $v;
    }

    $product['is_liquide'] = true;
    $product['variants'] = $variants;
}

msg("Product fetched successfully", 200, $product);