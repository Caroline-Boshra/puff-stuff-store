<?php

require_once '../inc/connection.php';
require_once '../inc/function.php';
require_once '../config/config.php';
require_once '../inc/header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    msg("Method Not Allowed", 405);
}

$categories = [];
$catStmt = $conn->prepare("SELECT id, name, image FROM categories ORDER BY name ASC");
$catStmt->execute();
$catRes = $catStmt->get_result();
while ($cat = $catRes->fetch_assoc()) {
    $categories[] = $cat;
}

msg("Categories fetched successfully", 200, $categories);

?>