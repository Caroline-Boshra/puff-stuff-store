<?php
require_once '../inc/connection.php';
require_once '../inc/function.php';
require_once '../inc/header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    msg("Method Not Allowed", 405);
}

$where = [];
$params = [];
$types = "";



// بحث باسم الكاتيجوري
if (!empty($_GET['category'])) {
    $where[] = "c.name LIKE ?";
    $params[] = "%" . $_GET['category'] . "%";
    $types .= "s";
}

// بحث باسم البراند
if (!empty($_GET['brand'])) {
    $where[] = "b.name LIKE ?";
    $params[] = "%" . $_GET['brand'] . "%";
    $types .= "s";
}
if (isset($_GET['flavor'])) {
    $where[] = "v.flavor = ?";
    $params[] = $_GET['flavor'];
    $types .= "s";
}

if (isset($_GET['size'])) {
    $where[] = "v.size = ?";
    $params[] = $_GET['size'];
    $types .= "s";
}

if (isset($_GET['nicotine'])) {
    $where[] = "v.nicotine = ?";
    $params[] = $_GET['nicotine'];
    $types .= "s";
}

if (isset($_GET['min_price'])) {
    $where[] = "p.price >= ?";
    $params[] = $_GET['min_price'];
    $types .= "i";
}

if (isset($_GET['max_price'])) {
    $where[] = "p.price <= ?";
    $params[] = $_GET['max_price'];
    $types .= "i";
}

$whereSQL = "";
if ($where) {
    $whereSQL = "WHERE " . implode(" AND ", $where);
}

$sql = "
    SELECT p.id, p.name, p.description, p.price, p.stock, c.name AS category, b.name AS brand,
           v.flavor, v.size, v.nicotine
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN product_variants v ON p.id = v.product_id
    $whereSQL
";


$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

if (empty($products)) {
    msg("No products found matching your search", 404);
} else {
    msg("Products fetched successfully", 200, $products);
}