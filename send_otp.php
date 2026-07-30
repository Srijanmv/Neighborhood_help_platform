<?php
session_start();
include("config.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$email = $_POST['email'];
$otp = rand(100000,999999);

$_SESSION['email'] = $email;

mysqli_query($conn,"UPDATE users SET otp='$otp', is_verified=0 WHERE email='$email'");

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'malviyasrijan156@gmail';
    $mail->Password = 'uqhaeufqyzuthmig';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('malviyasrijan156@gmail', 'Neighborhood Help');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Your OTP Code';
    $mail->Body = "Your OTP is: <b>$otp</b>";

    $mail->send();

    header("Location: verify.html");
} catch (Exception $e) {
    echo "OTP not sent. Error: {$mail->ErrorInfo}";
}
?>