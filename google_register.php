<?php
require 'inc/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

if (!$google_button_enabled) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Google sign-in is not configured yet.'
    ]);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$credential = trim($payload['credential'] ?? '');
$area = trim($payload['area'] ?? '');
$phone = trim($payload['phone'] ?? '');
$allowedAreas = ['Central', 'North', 'South', 'East', 'West'];

if (!$credential) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing Google credential.'
    ]);
    exit;
}

function google_http_get_json($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status >= 400) {
            throw new RuntimeException($error ?: 'HTTP request failed.');
        }

        return json_decode($body, true);
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 15,
            'header' => "Accept: application/json\r\n"
        ]
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        throw new RuntimeException('HTTP request failed.');
    }

    return json_decode($body, true);
}

try {
    $tokenInfo = google_http_get_json(
        'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($credential)
    );

    if (!is_array($tokenInfo)) {
        throw new RuntimeException('Invalid token response.');
    }

    $audience = $tokenInfo['aud'] ?? '';
    $email = trim($tokenInfo['email'] ?? '');
    $name = trim($tokenInfo['name'] ?? '');
    $googleSub = trim($tokenInfo['sub'] ?? '');
    $emailVerified = ($tokenInfo['email_verified'] ?? '') === 'true';

    if ($audience !== $google_client_id) {
        throw new RuntimeException('Google client mismatch.');
    }

    if (!$email || !$googleSub || !$emailVerified) {
        throw new RuntimeException('Google account details could not be verified.');
    }

    $stmt = $pdo->prepare("SELECT id, name, email, phone, area FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (!$user['area'] && !in_array($area, $allowedAreas, true)) {
            throw new RuntimeException('Please choose a valid area to finish your account setup.');
        }

        $updates = [];
        $values = [];

        if (db_column_exists('users', 'is_verified')) {
          $updates[] = 'is_verified = 1';
        }

        if (db_column_exists('users', 'otp')) {
          $updates[] = 'otp = NULL';
        }

        if (!$user['phone'] && $phone !== '') {
          $updates[] = 'phone = ?';
          $values[] = $phone;
        }

        if (!$user['area']) {
          $updates[] = 'area = ?';
          $values[] = $area;
        }

        if ($updates) {
          $values[] = $user['id'];
          $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?");
          $stmt->execute($values);
        }

        $_SESSION['user_id'] = $user['id'];
    } else {
        if (!in_array($area, $allowedAreas, true)) {
            throw new RuntimeException('Please choose a valid area first.');
        }

        $columns = ['name', 'email', 'phone', 'area', 'password'];
        $values = [
            $name !== '' ? $name : 'Google User',
            $email,
            $phone,
            $area,
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)
        ];

        if (db_column_exists('users', 'otp')) {
            $columns[] = 'otp';
            $values[] = null;
        }

        if (db_column_exists('users', 'is_verified')) {
            $columns[] = 'is_verified';
            $values[] = 1;
        }

        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $stmt = $pdo->prepare(
            "INSERT INTO users (" . implode(',', $columns) . ") VALUES ($placeholders)"
        );
        $stmt->execute($values);
        $_SESSION['user_id'] = (int) $pdo->lastInsertId();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Google sign-in completed successfully.',
        'redirect' => 'community.php'
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
