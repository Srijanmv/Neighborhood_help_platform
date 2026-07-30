<?php
session_start();
$db_host = trim('127.0.0.1');
$db_user = 'root';
$db_pass = ''; 
$db_name = 'neighborhood_help';

$google_client_id_config = '430003014184-avvlevqciuv81vf2l4nu0ri36u43brrt.apps.googleusercontent.com';
$google_client_id_env = trim((string) getenv('NH_GOOGLE_CLIENT_ID'));
$google_client_id = $google_client_id_env !== '' ? $google_client_id_env : trim($google_client_id_config);
$google_button_enabled = $google_client_id !== '' && stripos($google_client_id, 'YOUR_GOOGLE_CLIENT_ID') === false;
$google_maps_api_key_config = 'AIzaSyCQgMW-ng1mUtLief4dSI5yOfmwD6tSwf4';
$google_maps_api_key_env = trim((string) getenv('NH_GOOGLE_MAPS_API_KEY'));
$google_maps_api_key = $google_maps_api_key_env !== '' ? $google_maps_api_key_env : trim($google_maps_api_key_config);

$apple_client_id_config = '';
$apple_redirect_uri_config = '';
$apple_client_id_env = trim((string) getenv('NH_APPLE_CLIENT_ID'));
$apple_redirect_uri_env = trim((string) getenv('NH_APPLE_REDIRECT_URI'));
$apple_client_id = $apple_client_id_env !== '' ? $apple_client_id_env : trim($apple_client_id_config);
$apple_redirect_uri = $apple_redirect_uri_env !== '' ? $apple_redirect_uri_env : trim($apple_redirect_uri_config);
$apple_button_enabled = $apple_client_id !== '' && $apple_redirect_uri !== '';
$llm_api_base = trim(getenv('NH_LLM_API_BASE') ?: 'https://openrouter.ai/api/v1/chat/completions');
$llm_api_key = trim(getenv('NH_LLM_API_KEY') ?: '$apiKey = "YOUR_API_KEY";');
$llm_model = trim(getenv('NH_LLM_MODEL') ?: 'openrouter/auto');
$llm_app_name = 'Neighborhood Help Chatbot';
$smtp_host = 'smtp.gmail.com';
$smtp_port = 587;
$smtp_encryption = 'tls';
$smtp_username = 'malviyasrijan156@gmail.com';
$smtp_password = 'uqhaeufqyzuthmig';
$smtp_from_email = 'malviyasrijan156@gmail.com';
$smtp_from_name = 'Neighborhood Help';
$otp_mail_last_error = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    if ($db_host === '127.0.0.1') {
        try {
            $db_host = 'localhost';
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
        } catch (Exception $fallbackException) {
            die('Database connection failed: ' . $fallbackException->getMessage());
        }
    } else {
        die('Database connection failed: ' . $e->getMessage());
    }
}


function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function current_user() {
    global $pdo;
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT id,name,email,area,role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return null;
}

function db_column_exists($table, $column) {
    global $pdo;
    static $cache = [];

    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    $stmt->execute([$table, $column]);
    $cache[$key] = (bool) $stmt->fetchColumn();

    return $cache[$key];
}

function ensure_posts_category_column() {
    global $pdo;

    if (db_column_exists('posts', 'category')) {
        return true;
    }

    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'General' AFTER description");
    } catch (Throwable $e) {
        return false;
    }

    return db_column_exists('posts', 'category');
}

function ensure_posts_sos_column() {
    global $pdo;

    if (db_column_exists('posts', 'is_sos')) {
        return true;
    }

    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN is_sos TINYINT(1) NOT NULL DEFAULT 0 AFTER category");
    } catch (Throwable $e) {
        return false;
    }

    return db_column_exists('posts', 'is_sos');
}

function ensure_post_urgency_votes_table() {
    global $pdo;
    static $initialized = null;

    if ($initialized !== null) {
        return $initialized;
    }

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS post_urgency_votes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL,
                user_id INT NOT NULL,
                vote TINYINT(1) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_post_vote (post_id, user_id),
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        $initialized = true;
    } catch (Throwable $e) {
        $initialized = false;
    }

    return $initialized;
}

function ensure_helper_points_tables() {
    global $pdo;
    static $initialized = null;

    if ($initialized !== null) {
        return $initialized;
    }

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS helper_points (
                user_id INT NOT NULL PRIMARY KEY,
                total_points INT NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS helper_point_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                post_id INT DEFAULT NULL,
                event_key VARCHAR(190) NOT NULL,
                reason VARCHAR(190) NOT NULL,
                points INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_helper_event (event_key),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );

        $initialized = true;
    } catch (Throwable $e) {
        $initialized = false;
    }

    return $initialized;
}

function ensure_notifications_table() {
    global $pdo;
    static $initialized = null;

    if ($initialized !== null) {
        return $initialized;
    }

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                actor_user_id INT DEFAULT NULL,
                post_id INT DEFAULT NULL,
                type VARCHAR(50) NOT NULL,
                message VARCHAR(255) NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        $initialized = true;
    } catch (Throwable $e) {
        $initialized = false;
    }

    return $initialized;
}

function broadcast_notification($actorUserId, $postId, $type, $message) {
    global $pdo;

    if (!ensure_notifications_table()) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id != ?");
    $stmt->execute([(int) $actorUserId]);
    $recipientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($recipientIds)) {
        return true;
    }

    $insert = $pdo->prepare(
        "INSERT INTO notifications (user_id, actor_user_id, post_id, type, message)
         VALUES (?, ?, ?, ?, ?)"
    );

    foreach ($recipientIds as $recipientId) {
        $insert->execute([(int) $recipientId, (int) $actorUserId, $postId ?: null, $type, $message]);
    }

    return true;
}

function get_unread_notification_count($userId) {
    global $pdo;

    if (!$userId || !ensure_notifications_table()) {
        return 0;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([(int) $userId]);

    return (int) $stmt->fetchColumn();
}

function get_notifications_for_user($userId, $limit = 30) {
    global $pdo;

    if (!$userId || !ensure_notifications_table()) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT n.*, u.name AS actor_name
         FROM notifications n
         LEFT JOIN users u ON u.id = n.actor_user_id
         WHERE n.user_id = ?
         ORDER BY n.created_at DESC
         LIMIT " . (int) $limit
    );
    $stmt->execute([(int) $userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mark_notifications_read($userId) {
    global $pdo;

    if (!$userId || !ensure_notifications_table()) {
        return false;
    }

    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    return $stmt->execute([(int) $userId]);
}

function notify_user($userId, $actorUserId, $postId, $type, $message) {
    global $pdo;

    if (!$userId || !ensure_notifications_table()) {
        return false;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO notifications (user_id, actor_user_id, post_id, type, message)
         VALUES (?, ?, ?, ?, ?)"
    );

    return $stmt->execute([
        (int) $userId,
        $actorUserId ? (int) $actorUserId : null,
        $postId ? (int) $postId : null,
        $type,
        $message,
    ]);
}

function ensure_post_helpers_table() {
    global $pdo;
    static $initialized = null;

    if ($initialized !== null) {
        return $initialized;
    }

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS post_helpers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL,
                helper_user_id INT NOT NULL,
                helper_note VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_post_helper (post_id, helper_user_id),
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY (helper_user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        $initialized = true;
    } catch (Throwable $e) {
        $initialized = false;
    }

    return $initialized;
}

function ensure_chat_tables() {
    global $pdo;
    static $initialized = null;

    if ($initialized !== null) {
        return $initialized;
    }

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS conversations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT DEFAULT NULL,
                user_one_id INT NOT NULL,
                user_two_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_conversation (post_id, user_one_id, user_two_id),
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                conversation_id INT NOT NULL,
                sender_user_id INT NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
                FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );

        $initialized = true;
    } catch (Throwable $e) {
        $initialized = false;
    }

    return $initialized;
}

function normalized_conversation_participants($userA, $userB) {
    $userA = (int) $userA;
    $userB = (int) $userB;

    return $userA < $userB ? [$userA, $userB] : [$userB, $userA];
}

function has_user_offered_help($postId, $userId) {
    global $pdo;

    if (!$postId || !$userId || !ensure_post_helpers_table()) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT 1 FROM post_helpers WHERE post_id = ? AND helper_user_id = ? LIMIT 1");
    $stmt->execute([(int) $postId, (int) $userId]);

    return (bool) $stmt->fetchColumn();
}

function offer_post_help($postId, $helperUserId, $helperNote = '') {
    global $pdo;

    if (!$postId || !$helperUserId || !ensure_post_helpers_table()) {
        return false;
    }

    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO post_helpers (post_id, helper_user_id, helper_note)
         VALUES (?, ?, ?)"
    );

    return $stmt->execute([(int) $postId, (int) $helperUserId, $helperNote ?: null]);
}

function award_helper_points($userId, $points, $reason, $postId = null, $eventKey = '') {
    global $pdo;

    if (!$userId || !$points || trim((string) $reason) === '' || !ensure_helper_points_tables()) {
        return false;
    }

    $normalizedEventKey = trim((string) $eventKey);
    if ($normalizedEventKey === '') {
        $normalizedEventKey = 'points:' . (int) $userId . ':' . md5($reason . ':' . (string) $postId . ':' . microtime(true));
    }

    try {
        $pdo->beginTransaction();

        $eventStmt = $pdo->prepare(
            "INSERT INTO helper_point_events (user_id, post_id, event_key, reason, points)
             VALUES (?, ?, ?, ?, ?)"
        );
        $eventStmt->execute([
            (int) $userId,
            $postId ? (int) $postId : null,
            $normalizedEventKey,
            trim((string) $reason),
            (int) $points,
        ]);

        $pointsStmt = $pdo->prepare(
            "INSERT INTO helper_points (user_id, total_points)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE total_points = total_points + VALUES(total_points)"
        );
        $pointsStmt->execute([(int) $userId, (int) $points]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

function get_post_helpers($postId) {
    global $pdo;

    if (!$postId || !ensure_post_helpers_table()) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT ph.*, u.name, u.area, u.email
         FROM post_helpers ph
         JOIN users u ON u.id = ph.helper_user_id
         WHERE ph.post_id = ?
         ORDER BY ph.created_at ASC"
    );
    $stmt->execute([(int) $postId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_user_helper_points($userId) {
    global $pdo;

    if (!$userId || !ensure_helper_points_tables()) {
        return 0;
    }

    $stmt = $pdo->prepare("SELECT total_points FROM helper_points WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int) $userId]);

    return (int) ($stmt->fetchColumn() ?: 0);
}

function get_helper_leaderboard($limit = 8) {
    global $pdo;

    if (!ensure_helper_points_tables()) {
        return [];
    }

    $stmt = $pdo->query(
        "SELECT u.id, u.name, u.area, COALESCE(hp.total_points, 0) AS total_points
         FROM users u
         JOIN helper_points hp ON hp.user_id = u.id
         ORDER BY hp.total_points DESC, u.name ASC
         LIMIT " . (int) $limit
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cast_post_urgency_vote($postId, $userId, $vote) {
    global $pdo;

    if (!$postId || !$userId || !ensure_post_urgency_votes_table()) {
        return false;
    }

    $normalizedVote = ((int) $vote === 1) ? 1 : 0;
    $stmt = $pdo->prepare(
        "INSERT INTO post_urgency_votes (post_id, user_id, vote)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE vote = VALUES(vote)"
    );

    return $stmt->execute([(int) $postId, (int) $userId, $normalizedVote]);
}

function get_post_urgency_summary($postId, $viewerUserId = null) {
    global $pdo;

    $summary = [
        'urgent_yes' => 0,
        'not_urgent' => 0,
        'score' => 0,
        'total' => 0,
        'user_vote' => null,
    ];

    if (!$postId || !ensure_post_urgency_votes_table()) {
        return $summary;
    }

    $stmt = $pdo->prepare(
        "SELECT
            SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END) AS urgent_yes,
            SUM(CASE WHEN vote = 0 THEN 1 ELSE 0 END) AS not_urgent,
            COUNT(*) AS total
         FROM post_urgency_votes
         WHERE post_id = ?"
    );
    $stmt->execute([(int) $postId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $summary['urgent_yes'] = (int) ($row['urgent_yes'] ?? 0);
    $summary['not_urgent'] = (int) ($row['not_urgent'] ?? 0);
    $summary['total'] = (int) ($row['total'] ?? 0);
    $summary['score'] = $summary['urgent_yes'] - $summary['not_urgent'];

    if ($viewerUserId) {
        $voteStmt = $pdo->prepare(
            "SELECT vote
             FROM post_urgency_votes
             WHERE post_id = ? AND user_id = ?
             LIMIT 1"
        );
        $voteStmt->execute([(int) $postId, (int) $viewerUserId]);
        $userVote = $voteStmt->fetchColumn();
        if ($userVote !== false) {
            $summary['user_vote'] = (int) $userVote;
        }
    }

    return $summary;
}

function get_post_priority_score(array $post) {
    $score = 0;

    if (!empty($post['is_sos'])) {
        $score += 1000;
    }

    $score += ((int) ($post['urgency_score'] ?? 0)) * 25;
    $score += ((int) ($post['urgent_yes'] ?? 0)) * 4;
    $score += min(20, (int) ($post['helper_count'] ?? 0) * 2);

    if (($post['status'] ?? '') === 'pending') {
        $score += 15;
    } elseif (($post['status'] ?? '') === 'in_progress') {
        $score += 8;
    }

    return $score;
}

function get_or_create_conversation($postId, $userA, $userB) {
    global $pdo;

    if (!$userA || !$userB || $userA == $userB || !ensure_chat_tables()) {
        return null;
    }

    [$userOneId, $userTwoId] = normalized_conversation_participants($userA, $userB);
    $normalizedPostId = $postId ? (int) $postId : null;

    $stmt = $pdo->prepare(
        "SELECT id
         FROM conversations
         WHERE post_id <=> ? AND user_one_id = ? AND user_two_id = ?
         LIMIT 1"
    );
    $stmt->execute([$normalizedPostId, $userOneId, $userTwoId]);
    $conversationId = $stmt->fetchColumn();

    if ($conversationId) {
        return (int) $conversationId;
    }

    $insert = $pdo->prepare(
        "INSERT INTO conversations (post_id, user_one_id, user_two_id)
         VALUES (?, ?, ?)"
    );

    try {
        $insert->execute([$normalizedPostId, $userOneId, $userTwoId]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        $stmt->execute([$normalizedPostId, $userOneId, $userTwoId]);
        $conversationId = $stmt->fetchColumn();
        return $conversationId ? (int) $conversationId : null;
    }
}

function get_conversation_for_user($conversationId, $userId) {
    global $pdo;

    if (!$conversationId || !$userId || !ensure_chat_tables()) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT c.*,
                p.title AS post_title,
                p.user_id AS post_owner_id,
                CASE WHEN c.user_one_id = ? THEN c.user_two_id ELSE c.user_one_id END AS other_user_id,
                other_user.name AS other_user_name,
                other_user.area AS other_user_area
         FROM conversations c
         LEFT JOIN posts p ON p.id = c.post_id
         JOIN users other_user
           ON other_user.id = CASE WHEN c.user_one_id = ? THEN c.user_two_id ELSE c.user_one_id END
         WHERE c.id = ?
           AND (? IN (c.user_one_id, c.user_two_id))
         LIMIT 1"
    );
    $stmt->execute([(int) $userId, (int) $userId, (int) $conversationId, (int) $userId]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function get_messages_for_conversation($conversationId) {
    global $pdo;

    if (!$conversationId || !ensure_chat_tables()) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT m.*, u.name AS sender_name
         FROM messages m
         JOIN users u ON u.id = m.sender_user_id
         WHERE m.conversation_id = ?
         ORDER BY m.created_at ASC, m.id ASC"
    );
    $stmt->execute([(int) $conversationId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mark_conversation_read($conversationId, $viewerUserId) {
    global $pdo;

    if (!$conversationId || !$viewerUserId || !ensure_chat_tables()) {
        return false;
    }

    $stmt = $pdo->prepare(
        "UPDATE messages
         SET is_read = 1
         WHERE conversation_id = ? AND sender_user_id != ? AND is_read = 0"
    );

    return $stmt->execute([(int) $conversationId, (int) $viewerUserId]);
}

function send_conversation_message($conversationId, $senderUserId, $messageBody) {
    global $pdo;

    if (!$conversationId || !$senderUserId || trim($messageBody) === '' || !ensure_chat_tables()) {
        return false;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO messages (conversation_id, sender_user_id, message)
         VALUES (?, ?, ?)"
    );
    $sent = $stmt->execute([(int) $conversationId, (int) $senderUserId, trim($messageBody)]);

    if ($sent) {
        $update = $pdo->prepare(
            "UPDATE conversations
             SET last_message_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $update->execute([(int) $conversationId]);
    }

    return $sent;
}

function get_conversations_for_user($userId) {
    global $pdo;

    if (!$userId || !ensure_chat_tables()) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT c.*,
                p.title AS post_title,
                other_user.id AS other_user_id,
                other_user.name AS other_user_name,
                other_user.area AS other_user_area,
                last_message.message AS last_message,
                last_message.created_at AS last_message_created_at,
                (
                    SELECT COUNT(*)
                    FROM messages unread
                    WHERE unread.conversation_id = c.id
                      AND unread.sender_user_id != ?
                      AND unread.is_read = 0
                ) AS unread_count
         FROM conversations c
         LEFT JOIN posts p ON p.id = c.post_id
         JOIN users other_user
           ON other_user.id = CASE WHEN c.user_one_id = ? THEN c.user_two_id ELSE c.user_one_id END
         LEFT JOIN messages last_message
           ON last_message.id = (
                SELECT m2.id
                FROM messages m2
                WHERE m2.conversation_id = c.id
                ORDER BY m2.created_at DESC, m2.id DESC
                LIMIT 1
           )
         WHERE ? IN (c.user_one_id, c.user_two_id)
         ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.id DESC"
    );
    $stmt->execute([(int) $userId, (int) $userId, (int) $userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_unread_message_count($userId) {
    global $pdo;

    if (!$userId || !ensure_chat_tables()) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM messages m
         JOIN conversations c ON c.id = m.conversation_id
         WHERE ? IN (c.user_one_id, c.user_two_id)
           AND m.sender_user_id != ?
           AND m.is_read = 0"
    );
    $stmt->execute([(int) $userId, (int) $userId]);

    return (int) $stmt->fetchColumn();
}

function detect_post_category($title, $description = '') {
    $combinedText = trim($title . ' ' . $description);
    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($combinedText, 'UTF-8');
    } else {
        $text = strtolower($combinedText);
    }

    if ($text === '') {
        return 'General';
    }

    $categories = [
        'Animal' => [
            'animal', 'dog', 'cat', 'cow', 'buffalo', 'monkey', 'pet', 'puppy', 'kitten',
            'stray', 'injured animal', 'bite', 'snake', 'bird', 'wildlife',
            'janwar', 'kutta', 'billi', 'gaay', 'bandar', 'saap', 'pashu'
        ],
        'Water' => [
            'water', 'leak', 'pipeline', 'pipe burst', 'drain overflow', 'sewage water',
            'no water', 'water supply', 'tap water', 'flooding', 'overflow', 'dirty water',
            'pani', 'paani', 'jal', 'nal', 'leakage', 'ganda pani', 'pani nahi'
        ],
        'Road' => [
            'road', 'street', 'pothole', 'traffic', 'footpath', 'sidewalk', 'divider',
            'accident', 'broken road', 'signal', 'parking issue',
            'sadak', 'gadda', 'khadda', 'raasta', 'rasta', 'footpath'
        ],
        'Electricity' => [
            'electricity', 'power cut', 'short circuit', 'transformer', 'wire', 'street light',
            'voltage', 'blackout', 'sparking', 'electric pole',
            'bijli', 'light chali gayi', 'current', 'tar', 'pole', 'streetlight'
        ],
        'Sanitation' => [
            'garbage', 'trash', 'waste', 'cleaning', 'sewer', 'drain', 'toilet', 'smell',
            'sanitation', 'dump', 'mosquito', 'dirty', 'filth',
            'kachra', 'gandagi', 'safai', 'naali', 'nali', 'badbu', 'machhar'
        ],
        'Safety' => [
            'theft', 'fight', 'unsafe', 'security', 'harassment', 'crime', 'suspicious',
            'emergency', 'fire', 'smoke', 'danger', 'police',
            'chori', 'jagda', 'suraksha', 'aag', 'dhua', 'khatra', 'police'
        ],
        'Medical' => [
            'medical', 'ambulance', 'injury', 'injured', 'hospital', 'blood', 'doctor',
            'emergency help', 'health', 'sick',
            'doctor', 'hospital', 'chot', 'beemar', 'bimaar', 'ambulance'
        ],
    ];

    $scores = [];
    foreach ($categories as $category => $keywords) {
        $scores[$category] = 0;
        foreach ($keywords as $keyword) {
            $position = function_exists('mb_strpos')
                ? mb_strpos($text, $keyword, 0, 'UTF-8')
                : strpos($text, $keyword);
            if ($position !== false) {
                $scores[$category]++;
            }
        }
    }

    arsort($scores);
    reset($scores);
    $bestCategory = key($scores);
    $bestScore = $scores[$bestCategory] ?? 0;

    return $bestScore > 0 ? $bestCategory : 'General';
}

function ensure_users_verification_columns() {
    global $pdo;

    $added = false;

    if (!db_column_exists('users', 'otp')) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN otp VARCHAR(6) NULL AFTER password");
            $added = true;
        } catch (Throwable $e) {
            // Keep flow alive even if schema change is not permitted.
        }
    }

    if (!db_column_exists('users', 'is_verified')) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER otp");
            $added = true;
        } catch (Throwable $e) {
            // Keep flow alive even if schema change is not permitted.
        }
    }

    return $added || (db_column_exists('users', 'otp') && db_column_exists('users', 'is_verified'));
}

function send_verification_otp_email($email, $otp) {
    global $smtp_host, $smtp_port, $smtp_encryption, $smtp_username, $smtp_password, $smtp_from_email, $smtp_from_name, $otp_mail_last_error;

    $subject = 'Neighborhood Help Email Verification OTP';
    $message = "Your Neighborhood Help OTP is: $otp\n\nThis code expires when you request a new OTP.";
    $otp_mail_last_error = '';

    $phpMailerBase = __DIR__ . '/../PHPMailer-master/src/';
    $phpMailerFiles = [
        $phpMailerBase . 'Exception.php',
        $phpMailerBase . 'PHPMailer.php',
        $phpMailerBase . 'SMTP.php'
    ];

    $hasPhpMailer = true;
    foreach ($phpMailerFiles as $file) {
        if (!is_file($file)) {
            $hasPhpMailer = false;
            break;
        }
    }

    if ($hasPhpMailer && $smtp_username !== '' && $smtp_password !== '') {
        require_once $phpMailerBase . 'Exception.php';
        require_once $phpMailerBase . 'PHPMailer.php';
        require_once $phpMailerBase . 'SMTP.php';

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_username;
            $mail->Password = $smtp_password;
            $mail->SMTPSecure = $smtp_encryption;
            $mail->Port = (int) $smtp_port;
            $mail->CharSet = 'UTF-8';

            $fromEmail = $smtp_from_email !== '' ? $smtp_from_email : $smtp_username;
            $fromName = $smtp_from_name !== '' ? $smtp_from_name : 'Neighborhood Help';

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->Body = $message;

            return $mail->send();
        } catch (\Throwable $e) {
            $otp_mail_last_error = $e->getMessage();
            return false;
        }
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: no-reply@neighborhood-help.local'
    ];

    $sent = @mail($email, $subject, $message, implode("\r\n", $headers));
    if (!$sent) {
        $otp_mail_last_error = 'SMTP not configured and PHP mail() send failed.';
    }

    return $sent;
}

function otp_mail_last_error() {
    global $otp_mail_last_error;
    return $otp_mail_last_error;
}
