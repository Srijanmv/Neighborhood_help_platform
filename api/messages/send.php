<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mobile_error('Method not allowed.', 405);
}

$user = require_mobile_user();
$conversationId = (int) mobile_body_value('conversation_id', 0);
$message = trim((string) mobile_body_value('message', ''));

if ($conversationId <= 0 || $message === '') {
    mobile_error('Conversation and message are required.');
}

$conversation = get_conversation_for_user($conversationId, (int) $user['id']);
if (!$conversation) {
    mobile_error('Conversation not found.', 404);
}

send_conversation_message($conversationId, (int) $user['id'], $message);
notify_user(
    (int) $conversation['other_user_id'],
    (int) $user['id'],
    !empty($conversation['post_id']) ? (int) $conversation['post_id'] : null,
    'new_message',
    $user['name'] . ' sent you a message'
);

mobile_json([
    'ok' => true,
    'message' => [
        'id' => null,
        'conversation_id' => $conversationId,
        'sender_user_id' => (int) $user['id'],
        'sender_name' => $user['name'],
        'message' => $message,
        'is_read' => false,
        'created_at' => date('Y-m-d H:i:s'),
    ],
], 201);
