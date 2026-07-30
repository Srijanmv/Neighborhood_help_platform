<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mobile_error('Method not allowed.', 405);
}

$email = trim((string) mobile_body_value('email', ''));
$otp = trim((string) mobile_body_value('otp', ''));
$deviceName = trim((string) mobile_body_value('device_name', 'mobile'));

if ($email === '' || $otp === '') {
    mobile_error('Email and OTP are required.');
}

$user = mobile_find_user_by_email($email);
if (!$user) {
    mobile_error('User not found.', 404);
}

if (($user['otp'] ?? '') !== $otp) {
    mobile_error('Invalid OTP.', 422);
}

$stmt = $pdo->prepare("UPDATE users SET is_verified = 1, otp = NULL WHERE id = ?");
$stmt->execute([(int) $user['id']]);

$refreshedUser = mobile_find_user_by_email($email);
$token = create_api_token((int) $user['id'], $deviceName);

mobile_json([
    'ok' => true,
    'token' => $token,
    'user' => mobile_user_payload($refreshedUser ?: $user),
]);
