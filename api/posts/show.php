<?php
require_once __DIR__ . '/../bootstrap.php';

$postId = (int) ($_GET['id'] ?? 0);
if ($postId <= 0) {
    mobile_error('Post id is required.');
}

$viewer = mobile_user_from_token();
$viewerId = $viewer ? (int) $viewer['id'] : null;

$stmt = $pdo->prepare(
    "SELECT p.*, u.name AS user_name
     FROM posts p
     JOIN users u ON u.id = p.user_id
     WHERE p.id = ?
     LIMIT 1"
);
$stmt->execute([$postId]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    mobile_error('Post not found.', 404);
}

$post['include_comments'] = true;

mobile_json([
    'ok' => true,
    'post' => mobile_post_payload($post, $viewerId),
]);
