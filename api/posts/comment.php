<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mobile_error('Method not allowed.', 405);
}

$user = require_mobile_user();
$postId = (int) mobile_body_value('post_id', 0);
$comment = trim((string) mobile_body_value('comment', ''));

if ($postId <= 0 || $comment === '') {
    mobile_error('Post and comment are required.');
}

$postStmt = $pdo->prepare("SELECT id, user_id, title FROM posts WHERE id = ? LIMIT 1");
$postStmt->execute([$postId]);
$post = $postStmt->fetch(PDO::FETCH_ASSOC);
if (!$post) {
    mobile_error('Post not found.', 404);
}

$stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, comment) VALUES (?, ?, ?)");
$stmt->execute([$postId, (int) $user['id'], $comment]);

if ((int) $post['user_id'] !== (int) $user['id']) {
    notify_user((int) $post['user_id'], (int) $user['id'], $postId, 'new_comment', $user['name'] . ' commented on your post: ' . $post['title']);
}

mobile_json([
    'ok' => true,
    'comment' => [
        'id' => (int) $pdo->lastInsertId(),
        'comment' => $comment,
        'created_at' => date('Y-m-d H:i:s'),
        'user' => [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'area' => $user['area'],
        ],
    ],
], 201);
