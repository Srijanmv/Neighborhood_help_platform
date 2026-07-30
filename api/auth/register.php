<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mobile_error('Method not allowed.', 405);
}

$areas = ['Central', 'North', 'South', 'East', 'West'];
$name = trim((string) mobile_body_value('name', ''));
$email = trim((string) mobile_body_value('email', ''));
$phone = trim((string) mobile_body_value('phone', ''));
$area = trim((string) mobile_body_value('area', ''));
$password = (string) mobile_body_value('password', '');

if ($name === '' || $email === '' || $area === '' || $password === '') {
    mobile_error('Name, email, area, and password are required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    mobile_error('Enter a valid email address.');
}

if (!in_array($area, $areas, true)) {
    mobile_error('Please choose a valid area.');
}

if (strlen($password) < 6) {
    mobile_error('Password must be at least 6 characters long.');
}

$existingUser = mobile_find_user_by_email($email);
if ($existingUser) {
    if ((int) ($existingUser['is_verified'] ?? 1) === 1) {
        mobile_error('Email already registered.', 409);
    }

    $otp = (string) random_int(100000, 999999);
    $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, area = ?, password = ?, otp = ?, is_verified = 0 WHERE id = ?");
    $stmt->execute([
        $name,
        $phone,
        $area,
        password_hash($password, PASSWORD_DEFAULT),
        $otp,
        (int) $existingUser['id'],
    ]);

    $sent = send_verification_otp_email($email, $otp);
    mobile_json([
        'ok' => true,
        'requires_verification' => true,
        'email' => $email,
        'message' => $sent ? 'OTP sent to your email.' : 'Account updated, but OTP email could not be sent.',
        'mail_error' => $sent ? null : otp_mail_last_error(),
    ], 201);
}

$otp = (string) random_int(100000, 999999);
$stmt = $pdo->prepare(
    "INSERT INTO users (name, email, phone, area, password, otp, is_verified)
     VALUES (?, ?, ?, ?, ?, ?, 0)"
);
$stmt->execute([
    $name,
    $email,
    $phone,
    $area,
    password_hash($password, PASSWORD_DEFAULT),
    $otp,
]);

$sent = send_verification_otp_email($email, $otp);
mobile_json([
    'ok' => true,
    'requires_verification' => true,
    'email' => $email,
    'message' => $sent ? 'Account created. OTP sent to your email.' : 'Account created, but OTP email could not be sent.',
    'mail_error' => $sent ? null : otp_mail_last_error(),
], 201);
