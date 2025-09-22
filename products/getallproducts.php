<?php

require_once '../inc/connection.php';
require_once '../inc/function.php';
require_once '../inc/header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    msg("Method Not Allowed", 405);
}

$sql = "SELECT p.id,p.name,p.description,p.image,p.price,p.stock,
               c.name AS category_name,c.image AS category_image,
               b.name AS brand_name
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN brands b ON p.brand_id = b.id
        ORDER BY p.created_at DESC";

$result = $conn->query($sql);

$products = [];

// تجهيز baseUrl للصور
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
$projectRoot = dirname(dirname($scriptName)); 
$baseUrl = rtrim($protocol . "://" . $_SERVER['HTTP_HOST'] . $projectRoot, '/') . '/uploads/';

while ($row = $result->fetch_assoc()) {
    $product = [
        "product_id"     => $row['id'],
        "name"           => $row['name'],
        "description"    => $row['description'],
        "image"          => $row['image'] ? $baseUrl . $row['image'] : null,
        "price"          => $row['price'],
        "stock"          => $row['stock'],
        "category_name"  => $row['category_name'],
        "category_image" => $row['category_image'] ? $baseUrl . $row['category_image'] : null,
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

    $products[] = $product;
}

if (empty($products)) {
    msg("No data found", 200, []);
}

msg("All products fetched successfully", 200, $products);