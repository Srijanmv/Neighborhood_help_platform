<?php
require_once __DIR__ . '/../bootstrap.php';

$user = require_mobile_user();
$conversationId = (int) ($_GET['id'] ?? 0);

if ($conversationId <= 0 && !empty($_GET['user_id'])) {
    $targetUserId = (int) $_GET['user_id'];
    $postId = !empty($_GET['post_id']) ? (int) $_GET['post_id'] : null;
    if ($targetUserId > 0 && $targetUserId !== (int) $user['id']) {
        $conversationId = get_or_create_conversation($postId, (int) $user['id'], $targetUserId) ?: 0;
    }
}

$conversation = get_conversation_for_user($conversationId, (int) $user['id']);
if (!$conversation) {
    mobile_error('Conversation not found.', 404);
}

mark_conversation_read($conversationId, (int) $user['id']);
$messages = get_messages_for_conversation($conversationId);

mobile_json([
    'ok' => true,
    'conversation' => mobile_conversation_payload($conversation),
    'messages' => array_map(static function (array $message): array {
        return [
            'id' => (int) $message['id'],
            'conversation_id' => (int) $message['conversation_id'],
            'sender_user_id' => (int) $message['sender_user_id'],
            'sender_name' => $message['sender_name'],
            'message' => $message['message'],
            'is_read' => (int) $message['is_read'] === 1,
            'created_at' => $message['created_at'],
        ];
    }, $messages),
]);
