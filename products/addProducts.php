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


if (!isset($decoded->data->type) || $decoded->data->type !== "admin") {
    msg("Admins only", 403);
}

$name        = trim(htmlspecialchars($_POST['name'] ?? ''));
$description = trim(htmlspecialchars($_POST['description'] ?? ''));
$category    = trim(htmlspecialchars($_POST['category'] ?? ''));
$brand       = trim(htmlspecialchars($_POST['brand'] ?? ''));
$flavor      = trim(htmlspecialchars($_POST['flavor'] ?? ''));
$size        = trim(htmlspecialchars($_POST['size'] ?? ''));
$nicotine    = trim(htmlspecialchars($_POST['nicotine'] ?? ''));
$price       = trim(htmlspecialchars($_POST['price'] ?? ''));
$stock       = trim(htmlspecialchars($_POST['stock'] ?? ''));

if (!$name) msg("Please enter product name", 400);
if (!$description) msg("Please enter product description", 400);
if (!$category) msg("Please enter product category", 400);
if (!$brand) msg("Please enter product brand", 400);

$isLiquide = strtolower($category) === 'liquide';

if ($isLiquide) {
    if (!$flavor) msg("Flavor is required for liquide", 400);
    if (!$size) msg("Size is required for liquide", 400);
    if (!$nicotine) msg("Nicotine is required for liquide", 400);
}
if (!is_numeric($price) || $price <= 0) msg("Invalid price", 400);
if (!is_numeric($stock) || $stock < 0) msg("Invalid stock", 400);

$uploadDir = '../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (!isset($_FILES['image'])) {
    msg("Product image is required", 400);
}
$image = $_FILES['image'];
$imgName = uniqid() . '_' . basename($image['name']);
$imgPath = $uploadDir . $imgName;
if (!move_uploaded_file($image['tmp_name'], $imgPath)) {
    msg("Failed to upload product image", 500);
}

$categoryImgName = null;
if (isset($_FILES['category_image'])) {
    $catImg = $_FILES['category_image'];
    $categoryImgName = uniqid() . '_' . basename($catImg['name']);
    $catImgPath = $uploadDir . $categoryImgName;
    if (!move_uploaded_file($catImg['tmp_name'], $catImgPath)) {
        msg("Failed to upload category image", 500);
    }
}

$stmtCat = $conn->prepare("SELECT id, image FROM categories WHERE name = ?");
$stmtCat->bind_param("s", $category);
$stmtCat->execute();
$resCat = $stmtCat->get_result();
if ($resCat->num_rows > 0) {
    $row = $resCat->fetch_assoc();
    $category_id = $row['id'];
    $finalCategoryImage = $row['image'];
    if ($categoryImgName) {
        $stmtUpdateCatImg = $conn->prepare("UPDATE categories SET image = ? WHERE id = ?");
        $stmtUpdateCatImg->bind_param("si", $categoryImgName, $category_id);
        $stmtUpdateCatImg->execute();
        $finalCategoryImage = $categoryImgName;
    }
} else {
    if (!$categoryImgName) msg("Category image is required for new category", 400);
    $stmtInsCat = $conn->prepare("INSERT INTO categories (name, image) VALUES (?, ?)");
    $stmtInsCat->bind_param("ss", $category, $categoryImgName);
    $stmtInsCat->execute();
    $category_id = $stmtInsCat->insert_id;
    $finalCategoryImage = $categoryImgName;
}

$stmtBrand = $conn->prepare("SELECT id FROM brands WHERE name = ?");
$stmtBrand->bind_param("s", $brand);
$stmtBrand->execute();
$resBrand = $stmtBrand->get_result();
if ($resBrand->num_rows > 0) {
    $brand_id = $resBrand->fetch_assoc()['id'];
} else {
    $stmtInsBrand = $conn->prepare("INSERT INTO brands (name) VALUES (?)");
    $stmtInsBrand->bind_param("s", $brand);
    $stmtInsBrand->execute();
    $brand_id = $stmtInsBrand->insert_id;
}

$stmtProd = $conn->prepare("INSERT INTO products (name, description, image, category_id, brand_id, price, stock) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmtProd->bind_param("sssiiii", $name, $description, $imgName, $category_id, $brand_id, $price, $stock);
$stmtProd->execute();
$product_id = $stmtProd->insert_id;

if ($isLiquide) {
    $stmtVar = $conn->prepare("INSERT INTO product_variants (product_id, flavor, size, nicotine) VALUES (?, ?, ?, ?)");
    $stmtVar->bind_param("isss", $product_id, $flavor, $size, $nicotine);
    $stmtVar->execute();
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
$projectRoot = dirname(dirname($scriptName)); 
$baseUrl = rtrim($protocol . "://" . $_SERVER['HTTP_HOST'] . $projectRoot, '/') . '/uploads/';

$response = [
    "product_id"     => $product_id,
    "name"           => $name,
    "description"    => $description,
    "category"       => $category,
    "brand"          => $brand,
    "image"          => $baseUrl . $imgName,              
    "category_image" => $baseUrl . $finalCategoryImage,  
    "price"          => $price,
    "stock"          => $stock,
];

if ($isLiquide) {
    $response['is_liquide'] = true;
    $response['variant'] = [
        "flavor"   => $flavor,
        "size"     => $size,
        "nicotine" => $nicotine
    ];
}

msg("Product added successfully", 200, $response);