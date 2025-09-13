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


$name = isset($_POST['name']) ? trim(htmlspecialchars($_POST['name'])) : '';
$email = isset($_POST['email']) ? trim(htmlspecialchars($_POST['email'])) : '';
$password = isset($_POST['password']) ? trim(htmlspecialchars($_POST['password'])) : '';


if (empty($name)) {
    msg("Please enter your name", 403);
}

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


$check = $conn->prepare("SELECT id FROM admins WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    msg("Email already registered", 409);
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$query = "INSERT INTO admins (name, email, password) VALUES (?, ?, ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $name, $email, $hashedPassword);

if ($stmt->execute()) {
    $user_id = $stmt->insert_id;

   
   $payload = [
        "iss" => $iss,
        "aud" => $aud,
        "iat" => $iat,
        "exp" => $exp,
        "data" => [
            "id"    => $user_id,
            "name"  => $name,
            "email" => $email
        ]
    ];

    $token = JWT::encode($payload, $key, 'HS256');

   
    msg("admin registered successfully", 200, [
        "token" => $token,
        "admin" => [
            "id" => $user_id,
            "name" => $name,
            "email" => $email
        ]
    ]);
} else {
    msg("Something went wrong", 500);
}