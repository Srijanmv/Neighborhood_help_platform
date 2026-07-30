<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mobile_error('Method not allowed.', 405);
}

$email = trim((string) mobile_body_value('email', ''));
$password = (string) mobile_body_value('password', '');
$deviceName = trim((string) mobile_body_value('device_name', 'mobile'));

if ($email === '' || $password === '') {
    mobile_error('Email and password are required.');
}

$user = mobile_find_user_by_email($email);
if (!$user || !password_verify($password, $user['password'])) {
    mobile_error('Invalid credentials.', 401);
}

if (array_key_exists('is_verified', $user) && (int) $user['is_verified'] === 0) {
    mobile_error('Please verify your email first.', 403, [
        'requires_verification' => true,
        'email' => $email,
    ]);
}

$token = create_api_token((int) $user['id'], $deviceName);
if (!$token) {
    mobile_error('Could not create login session.', 500);
}

mobile_json([
    'ok' => true,
    'token' => $token,
    'user' => mobile_user_payload($user),
]);
