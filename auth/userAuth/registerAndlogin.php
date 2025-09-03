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


$name     = trim(htmlspecialchars($_POST['name'] ?? ''));
$email    = trim(htmlspecialchars($_POST['email'] ?? ''));
$password = trim(htmlspecialchars($_POST['password'] ?? ''));
$phone    = trim(htmlspecialchars($_POST['phone'] ?? ''));


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
if (empty($phone)) {
    msg("Please enter your phone", 403);
} elseif (!is_numeric($phone)) {
    msg("Phone must contain only numbers", 403);
} elseif (strlen($phone) != 11) {
    msg("Phone must be exactly 11 digits", 403);
}



$check = $conn->prepare("SELECT * FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    if (!password_verify($password, $user['password'])) {
        msg("Wrong password for existing account", 401);
    }

    $payload = [
        "iss" => $iss,
        "aud" => $aud,
        "iat" => $iat,
        "exp" => $exp,
        "user_id" => $user['id']
    ];

    $token = JWT::encode($payload, $key, 'HS256');

    msg("User logged in", 200, [
        "token" => $token,
        "user" => [
            "id" => $user['id'],
            "name" => $user['name'],
            "email" => $user['email'],
            "phone" => $user['phone']
        ]
    ]);
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$query = "INSERT INTO users (name, email, password, phone) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("ssss", $name, $email, $hashedPassword, $phone);

if ($stmt->execute()) {
    $user_id = $stmt->insert_id;

    $payload = [
        "iss" => $iss,
        "aud" => $aud,
        "iat" => $iat,
        "exp" => $exp,
        "user_id" => $user_id
    ];

    $token = JWT::encode($payload, $key, 'HS256');

    msg("Register successful", 201, [
        "token" => $token,
        "user" => [
            "id" => $user_id,
            "name" => $name,
            "email" => $email,
            "phone" => $phone
        ]
    ]);
} else {
    msg("Something went wrong", 500);
}