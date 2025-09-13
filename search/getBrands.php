<?php

require_once '../inc/connection.php';
require_once '../inc/function.php';
require_once '../config/config.php';
require_once '../inc/header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    msg("Method Not Allowed", 405);
}

$brands = [];
$brandStmt = $conn->prepare("SELECT id, name FROM brands ORDER BY name ASC");
$brandStmt->execute();
$brandRes = $brandStmt->get_result();
while ($brand = $brandRes->fetch_assoc()) {
    $brands[] = $brand;
}

msg("Brands fetched successfully", 200, $brands);

?>