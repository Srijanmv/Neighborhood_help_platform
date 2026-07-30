<?php
require 'inc/config.php';
require 'inc/auth.php';

require_login();

header('Content-Type: application/json; charset=UTF-8');

$user = current_user();

if (!$user) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required.',
    ]);
    exit;
}

ensure_notifications_table();

$afterId = max(0, (int) ($_GET['after_id'] ?? 0));
$recentLimit = max(1, min(6, (int) ($_GET['recent_limit'] ?? 6)));

function notification_link_for_item(array $notification): string
{
    if (($notification['type'] ?? '') === 'new_message') {
        return 'messages.php';
    }

    if (!empty($notification['post_id'])) {
        return 'view_post.php?id=' . (int) $notification['post_id'];
    }

    return 'notifications.php';
}

$newStmt = $pdo->prepare(
    "SELECT id, post_id, type, message, created_at
     FROM notifications
     WHERE user_id = ? AND id > ?
     ORDER BY id ASC"
);
$newStmt->execute([(int) $user['id'], $afterId]);
$newItems = $newStmt->fetchAll(PDO::FETCH_ASSOC);

$latestIdStmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) FROM notifications WHERE user_id = ?");
$latestIdStmt->execute([(int) $user['id']]);
$latestId = (int) $latestIdStmt->fetchColumn();

$recentItems = get_notifications_for_user((int) $user['id'], $recentLimit);
$unreadCount = get_unread_notification_count((int) $user['id']);

$normalize = static function (array $item): array {
    return [
        'id' => (int) $item['id'],
        'post_id' => !empty($item['post_id']) ? (int) $item['post_id'] : null,
        'type' => (string) ($item['type'] ?? ''),
        'message' => (string) ($item['message'] ?? ''),
        'created_at' => (string) ($item['created_at'] ?? ''),
        'link' => notification_link_for_item($item),
    ];
};

echo json_encode([
    'success' => true,
    'unread_count' => $unreadCount,
    'latest_id' => $latestId,
    'items' => array_map($normalize, $newItems),
    'recent' => array_map($normalize, $recentItems),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
