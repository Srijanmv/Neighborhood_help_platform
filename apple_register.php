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

if (!$apple_button_enabled) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Apple sign-in is not configured yet.'
    ]);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$idToken = trim($payload['id_token'] ?? '');
$area = trim($payload['area'] ?? '');
$phone = trim($payload['phone'] ?? '');
$allowedAreas = ['Central', 'North', 'South', 'East', 'West'];

if ($idToken === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing Apple token.'
    ]);
    exit;
}

function base64url_decode_safe($data) {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(strtr($data, '-_', '+/'));
}

try {
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
        throw new RuntimeException('Invalid Apple token format.');
    }

    $claimsJson = base64url_decode_safe($parts[1]);
    $claims = json_decode($claimsJson, true);

    if (!is_array($claims)) {
        throw new RuntimeException('Could not decode Apple token.');
    }

    $issuer = trim($claims['iss'] ?? '');
    $audience = trim($claims['aud'] ?? '');
    $appleSub = trim($claims['sub'] ?? '');
    $email = trim($claims['email'] ?? '');
    $emailVerified = (string) ($claims['email_verified'] ?? '');
    $expiresAt = (int) ($claims['exp'] ?? 0);

    if ($issuer !== 'https://appleid.apple.com') {
        throw new RuntimeException('Invalid Apple issuer.');
    }

    if ($audience !== $apple_client_id) {
        throw new RuntimeException('Apple client mismatch.');
    }

    if ($expiresAt > 0 && $expiresAt < time()) {
        throw new RuntimeException('Apple token expired.');
    }

    if ($appleSub === '' || $email === '' || !in_array($emailVerified, ['true', '1'], true)) {
        throw new RuntimeException('Apple account details could not be verified.');
    }

    $stmt = $pdo->prepare('SELECT id, name, email, phone, area FROM users WHERE email = ?');
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
            $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
            $stmt->execute($values);
        }

        $_SESSION['user_id'] = $user['id'];
    } else {
        if (!in_array($area, $allowedAreas, true)) {
            throw new RuntimeException('Please choose a valid area first.');
        }

        $columns = ['name', 'email', 'phone', 'area', 'password'];
        $values = [
            'Apple User',
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
        $stmt = $pdo->prepare('INSERT INTO users (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')');
        $stmt->execute($values);

        $_SESSION['user_id'] = (int) $pdo->lastInsertId();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Apple sign-in completed successfully.',
        'redirect' => 'community.php'
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
