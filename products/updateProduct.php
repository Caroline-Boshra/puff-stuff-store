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



$product_id     = intval($_POST['product_id'] ?? 0);
$name           = trim(htmlspecialchars($_POST['name'] ?? ''));
$description    = trim(htmlspecialchars($_POST['description'] ?? ''));
$price          = trim(htmlspecialchars($_POST['price'] ?? ''));
$stock          = trim(htmlspecialchars($_POST['stock'] ?? ''));
$category_name  = trim(htmlspecialchars($_POST['category_name'] ?? ''));
$brand_name     = trim(htmlspecialchars($_POST['brand_name'] ?? ''));


if (!$product_id) msg("Product ID is required", 400);

$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    msg("Product not found", 404);
}
$currentProduct = $res->fetch_assoc();
$image = $currentProduct['image'];


if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['product_image'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed)) msg("Invalid product image format", 400);

    $newImg = uniqid() . '_' . basename($file['name']);
    if (move_uploaded_file($file['tmp_name'], '../uploads/' . $newImg)) {
        if ($image && file_exists('../uploads/' . $image)) {
            unlink('../uploads/' . $image);
        }
        $image = $newImg;
    }
}


$brand_id = $currentProduct['brand_id'];
if (!empty($brand_name)) {
    $stmt = $conn->prepare("SELECT id FROM brands WHERE name = ?");
    $stmt->bind_param("s", $brand_name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $brand_id = $res->fetch_assoc()['id'];
    } else {
        $stmt = $conn->prepare("INSERT INTO brands (name) VALUES (?)");
        $stmt->bind_param("s", $brand_name);
        $stmt->execute();
        $brand_id = $stmt->insert_id;
    }
}


$category_id = $currentProduct['category_id'];
$category_image_name = null;
if (!empty($category_name)) {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE name = ?");
    $stmt->bind_param("s", $category_name);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $category_id = $row['id'];
        $category_image_name = $row['image'];

        if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] === UPLOAD_ERR_OK) {
            $catImg = $_FILES['category_image'];
            $ext = strtolower(pathinfo($catImg['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allowed)) msg("Invalid category image format", 400);

            $newCatImg = uniqid() . '_' . basename($catImg['name']);
            if (move_uploaded_file($catImg['tmp_name'], '../uploads/' . $newCatImg)) {
                if ($category_image_name && file_exists('../uploads/' . $category_image_name)) {
                    unlink('../uploads/' . $category_image_name);
                }

                $stmtUpdateCatImg = $conn->prepare("UPDATE categories SET image = ? WHERE id = ?");
                $stmtUpdateCatImg->bind_param("si", $newCatImg, $category_id);
                $stmtUpdateCatImg->execute();

                $category_image_name = $newCatImg;
            }
        }

    } else {
        
        if (!isset($_FILES['category_image']) || $_FILES['category_image']['error'] !== UPLOAD_ERR_OK) {
            msg("Category image is required for new category", 400);
        }

        $catImg = $_FILES['category_image'];
        $ext = strtolower(pathinfo($catImg['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed)) msg("Invalid category image format", 400);

        $newCatImg = uniqid() . '_' . basename($catImg['name']);
        if (!move_uploaded_file($catImg['tmp_name'], '../uploads/' . $newCatImg)) {
            msg("Failed to upload category image", 500);
        }

        $stmt = $conn->prepare("INSERT INTO categories (name, image) VALUES (?, ?)");
        $stmt->bind_param("ss", $category_name, $newCatImg);
        $stmt->execute();
        $category_id = $stmt->insert_id;
        $category_image_name = $newCatImg;
    }
}

$name = $name ?: $currentProduct['name'];
$description = $description ?: $currentProduct['description'];
$price = $price ?: $currentProduct['price'];
$stock = $stock ?: $currentProduct['stock'];


$stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, image = ?, price = ?, stock = ?, category_id = ?, brand_id = ? WHERE id = ?");
$stmt->bind_param("sssiiiii", $name, $description, $image, $price, $stock, $category_id, $brand_id, $product_id);
$stmt->execute();


$stmtCheckCat = $conn->prepare("SELECT name FROM categories WHERE id = ?");
$stmtCheckCat->bind_param("i", $category_id);
$stmtCheckCat->execute();
$resCatName = $stmtCheckCat->get_result();
$catName = $resCatName->fetch_assoc()['name'];
$isLiquide = strtolower($catName) === 'liquide';


$response = [
    "product_id"     => $product_id,
    "updated_name"   => $name,
    "updated_price"  => $price,
    "updated_stock"  => $stock,
    "updated_image"  => $image,
    "category_name"  => $category_name,
    "brand_name"     => $brand_name,
    "category_image" => $category_image_name
];

if ($isLiquide) {
    $flavor   = trim(htmlspecialchars($_POST['flavor'] ?? ''));
    $size     = trim(htmlspecialchars($_POST['size'] ?? ''));
    $nicotine = trim(htmlspecialchars($_POST['nicotine'] ?? ''));

    $stmtVarCheck = $conn->prepare("SELECT id FROM product_variants WHERE product_id = ?");
    $stmtVarCheck->bind_param("i", $product_id);
    $stmtVarCheck->execute();
    $resVar = $stmtVarCheck->get_result();

    if ($resVar->num_rows > 0) {
        $variantId = $resVar->fetch_assoc()['id'];
        $stmtUpdateVar = $conn->prepare("UPDATE product_variants SET flavor = ?, size = ?, nicotine = ? WHERE id = ?");
        $stmtUpdateVar->bind_param("sssi", $flavor, $size, $nicotine, $variantId);
        $stmtUpdateVar->execute();
    } else {
        $stmtAddVar = $conn->prepare("INSERT INTO product_variants (product_id, flavor, size, nicotine) VALUES (?, ?, ?, ?)");
        $stmtAddVar->bind_param("isss", $product_id, $flavor, $size, $nicotine);
        $stmtAddVar->execute();
    }

    $response['variant'] = [
        "flavor"   => $flavor,
        "size"     => $size,
        "nicotine" => $nicotine
    ];
}


msg("Product and Category updated successfully", 200, $response);