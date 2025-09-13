<?php 

require_once '../../inc/connection.php';
require_once '../../inc/function.php';
require_once '../../inc/header.php';
require_once '../../config/config.php';
require_once '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;



if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    msg("Method Not Allowed", 405);
}


$email = isset($_POST['email']) ? trim(htmlspecialchars($_POST['email'])) : '';
$password = isset($_POST['password']) ? trim(htmlspecialchars($_POST['password'])) : '';


if (empty($email)) {
    msg("Please enter your email", 403);
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    msg("Invalid email format", 400);
}

if (empty($password)) {
    msg("Please enter your password", 403);
} elseif (strlen($password) < 8) {
    msg("Password must be at least 8 characters", 403);
}


$stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();


if ($result->num_rows == 0) {
    msg("Email not found", 404);
}

$admin = $result->fetch_assoc();


if (!password_verify($password, $admin['password'])) {
    msg("Wrong password", 401);
}

$payload = [
    "iss" => $iss,
    "aud" => $aud,
    "iat" => $iat,
    "exp" => $exp,
    "data" => [
        "id"    => $admin['id'],
        "name"  => $admin['name'],
        "email" => $admin['email'],
        "type"  => "admin"
    ]
];




$token = JWT::encode($payload, $key, 'HS256');


msg("Login successful", 200, [
    "token" => $token,
    "admin" => [
        "id" => $admin['id'],
        "name" => $admin['name'],
        "email" => $admin['email']
    ]
]);