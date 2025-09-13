<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function msg($msg, $code, $data = null) {
    header("Content-Type: application/json");
    http_response_code($code);

    if (isset($GLOBALS['stmt']) && $GLOBALS['stmt']) {
        $GLOBALS['stmt']->close();
    }

    if (isset($GLOBALS['conn']) && $GLOBALS['conn']) {
        $GLOBALS['conn']->close();
    }

    echo json_encode([
        "status" => $code >= 200 && $code < 300,
        "message" => $msg,
        "data" => $data
    ]);
    exit;
}

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'karolingeorge2011@gmail.com';  
        $mail->Password   = 'hlxmdiiivpivgyyi';     
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        
        $mail->setFrom('karolingeorge2011@gmail.com', 'Your Store');
        $mail->addAddress($to);

      
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}