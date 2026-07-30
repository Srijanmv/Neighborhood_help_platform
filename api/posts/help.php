<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mobile_error('Method not allowed.', 405);
}

$user = require_mobile_user();
$postId = (int) mobile_body_value('post_id', 0);

if ($postId <= 0) {
    mobile_error('Post id is required.');
}

$stmt = $pdo->prepare("SELECT id, user_id, title FROM posts WHERE id = ? LIMIT 1");
$stmt->execute([$postId]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    mobile_error('Post not found.', 404);
}

if ((int) $post['user_id'] === (int) $user['id']) {
    mobile_error('You cannot offer help on your own post.', 422);
}

$alreadyHelping = has_user_offered_help($postId, (int) $user['id']);
offer_post_help($postId, (int) $user['id']);
$conversationId = get_or_create_conversation($postId, (int) $user['id'], (int) $post['user_id']);

if (!$alreadyHelping) {
    notify_user((int) $post['user_id'], (int) $user['id'], $postId, 'helper_joined', $user['name'] . ' offered help on your post: ' . $post['title']);
}

mobile_json([
    'ok' => true,
    'conversation_id' => $conversationId,
    'already_helping' => $alreadyHelping,
]);
