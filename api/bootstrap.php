<?php
require_once __DIR__ . '/../inc/config.php';

ensure_users_verification_columns();
ensure_posts_category_column();
ensure_notifications_table();
ensure_post_helpers_table();
ensure_chat_tables();

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function mobile_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mobile_error(string $message, int $status = 400, array $extra = []): void
{
    mobile_json(array_merge([
        'ok' => false,
        'message' => $message,
    ], $extra), $status);
}

function mobile_input(): array
{
    static $input = null;

    if ($input !== null) {
        return $input;
    }

    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '[]', true);
    $input = is_array($decoded) ? $decoded : [];

    return $input;
}

function mobile_body_value(string $key, $default = '')
{
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }

    $input = mobile_input();
    return $input[$key] ?? $default;
}

function ensure_api_tokens_table(): bool
{
    global $pdo;
    static $initialized = null;

    if ($initialized !== null) {
        return $initialized;
    }

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS api_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_hash CHAR(64) NOT NULL UNIQUE,
                device_name VARCHAR(120) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_used_at TIMESTAMP NULL DEFAULT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        $initialized = true;
    } catch (Throwable $e) {
        $initialized = false;
    }

    return $initialized;
}

function create_api_token(int $userId, string $deviceName = 'mobile'): ?string
{
    global $pdo;

    if ($userId <= 0 || !ensure_api_tokens_table()) {
        return null;
    }

    $plainToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $plainToken);

    $stmt = $pdo->prepare(
        "INSERT INTO api_tokens (user_id, token_hash, device_name)
         VALUES (?, ?, ?)"
    );
    $stmt->execute([$userId, $tokenHash, trim($deviceName) ?: 'mobile']);

    return $plainToken;
}

function revoke_api_token(string $plainToken): void
{
    global $pdo;

    if ($plainToken === '' || !ensure_api_tokens_table()) {
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM api_tokens WHERE token_hash = ?");
    $stmt->execute([hash('sha256', $plainToken)]);
}

function token_from_request(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (stripos($header, 'Bearer ') === 0) {
        return trim(substr($header, 7));
    }

    return trim((string) ($_GET['token'] ?? mobile_body_value('token', '')));
}

function mobile_user_from_token(): ?array
{
    global $pdo;

    $token = token_from_request();
    if ($token === '' || !ensure_api_tokens_table()) {
        return null;
    }

    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        "SELECT u.id, u.name, u.email, u.phone, u.area, u.role, u.created_at
         FROM api_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = ?
         LIMIT 1"
    );
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $update = $pdo->prepare("UPDATE api_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE token_hash = ?");
        $update->execute([$tokenHash]);
    }

    return $user ?: null;
}

function require_mobile_user(): array
{
    $user = mobile_user_from_token();
    if (!$user) {
        mobile_error('Authentication required.', 401);
    }

    return $user;
}

function mobile_find_user_by_email(string $email): ?array
{
    global $pdo;

    $fields = db_column_exists('users', 'is_verified')
        ? 'id, name, email, phone, area, password, role, is_verified, otp'
        : 'id, name, email, phone, area, password, role';

    $stmt = $pdo->prepare("SELECT $fields FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mobile_user_payload(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'phone' => $user['phone'] ?? '',
        'area' => $user['area'] ?? '',
        'role' => $user['role'] ?? 'user',
        'unread_messages' => get_unread_message_count((int) $user['id']),
        'unread_notifications' => get_unread_notification_count((int) $user['id']),
    ];
}

function mobile_post_payload(array $post, ?int $viewerId = null): array
{
    $postId = (int) $post['id'];
    $ownerId = (int) $post['user_id'];
    $helpers = get_post_helpers($postId);
    $comments = [];

    if (!empty($post['include_comments'])) {
        global $pdo;
        $stmt = $pdo->prepare(
            "SELECT c.id, c.comment, c.created_at, u.id AS user_id, u.name, u.area
             FROM comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.post_id = ?
             ORDER BY c.created_at ASC, c.id ASC"
        );
        $stmt->execute([$postId]);
        $comments = array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'comment' => $row['comment'],
                'created_at' => $row['created_at'],
                'user' => [
                    'id' => (int) $row['user_id'],
                    'name' => $row['name'],
                    'area' => $row['area'],
                ],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    return [
        'id' => $postId,
        'user_id' => $ownerId,
        'user_name' => $post['user_name'] ?? $post['name'] ?? '',
        'title' => $post['title'],
        'description' => $post['description'] ?? '',
        'category' => $post['category'] ?: detect_post_category($post['title'] ?? '', $post['description'] ?? ''),
        'status' => $post['status'] ?? 'pending',
        'area' => $post['area'] ?? '',
        'location' => $post['location'] ?? '',
        'lat' => isset($post['lat']) ? (float) $post['lat'] : null,
        'lng' => isset($post['lng']) ? (float) $post['lng'] : null,
        'image' => $post['image'] ?? null,
        'created_at' => $post['created_at'] ?? '',
        'helper_count' => count($helpers),
        'comment_count' => isset($post['comment_count']) ? (int) $post['comment_count'] : count($comments),
        'is_owner' => $viewerId !== null && $viewerId === $ownerId,
        'is_helping' => $viewerId !== null ? has_user_offered_help($postId, $viewerId) : false,
        'helpers' => array_map(static function (array $helper): array {
            return [
                'id' => (int) $helper['helper_user_id'],
                'name' => $helper['name'],
                'area' => $helper['area'],
                'email' => $helper['email'],
                'helper_note' => $helper['helper_note'],
            ];
        }, $helpers),
        'comments' => $comments,
    ];
}

function mobile_conversation_payload(array $conversation): array
{
    return [
        'id' => (int) $conversation['id'],
        'post_id' => isset($conversation['post_id']) ? (int) $conversation['post_id'] : null,
        'post_title' => $conversation['post_title'] ?? null,
        'other_user_id' => (int) $conversation['other_user_id'],
        'other_user_name' => $conversation['other_user_name'],
        'other_user_area' => $conversation['other_user_area'] ?? '',
        'last_message' => $conversation['last_message'] ?? null,
        'last_message_created_at' => $conversation['last_message_created_at'] ?? ($conversation['last_message_at'] ?? null),
        'unread_count' => isset($conversation['unread_count']) ? (int) $conversation['unread_count'] : 0,
    ];
}
