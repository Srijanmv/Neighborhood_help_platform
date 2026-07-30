<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $viewer = mobile_user_from_token();
    $viewerId = $viewer ? (int) $viewer['id'] : null;
    $area = trim((string) ($_GET['area'] ?? 'all'));
    $category = trim((string) ($_GET['category'] ?? 'all'));

    $sql = "SELECT p.*, u.name AS user_name,
                   (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
            FROM posts p
            JOIN users u ON u.id = p.user_id";
    $params = [];
    $conditions = [];

    if ($area !== '' && $area !== 'all') {
        $conditions[] = 'p.area = ?';
        $params[] = $area;
    }

    if ($category !== '' && $category !== 'all') {
        $conditions[] = 'p.category = ?';
        $params[] = $category;
    }

    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY p.created_at DESC, p.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    mobile_json([
        'ok' => true,
        'posts' => array_map(static function (array $post) use ($viewerId): array {
            return mobile_post_payload($post, $viewerId);
        }, $posts),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_mobile_user();
    $title = trim((string) mobile_body_value('title', ''));
    $description = trim((string) mobile_body_value('description', ''));
    $area = trim((string) mobile_body_value('area', $user['area'] ?? ''));
    $location = trim((string) mobile_body_value('location', ''));
    $lat = trim((string) mobile_body_value('lat', ''));
    $lng = trim((string) mobile_body_value('lng', ''));

    if ($title === '' || $area === '') {
        mobile_error('Title and area are required.');
    }

    if ($lat === '' || $lng === '') {
        mobile_error('Latitude and longitude are required.');
    }

    $category = detect_post_category($title, $description);
    $stmt = $pdo->prepare(
        "INSERT INTO posts (user_id, title, description, category, image, area, location, lat, lng)
         VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?)"
    );
    $stmt->execute([
        (int) $user['id'],
        $title,
        $description,
        $category,
        $area,
        $location,
        $lat,
        $lng,
    ]);

    $postId = (int) $pdo->lastInsertId();
    broadcast_notification((int) $user['id'], $postId, 'new_post', $user['name'] . ' created a new post: ' . $title);

    $lookup = $pdo->prepare("SELECT p.*, u.name AS user_name FROM posts p JOIN users u ON u.id = p.user_id WHERE p.id = ?");
    $lookup->execute([$postId]);
    $post = $lookup->fetch(PDO::FETCH_ASSOC);

    mobile_json([
        'ok' => true,
        'post' => mobile_post_payload($post ?: [
            'id' => $postId,
            'user_id' => $user['id'],
            'user_name' => $user['name'],
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'area' => $area,
            'location' => $location,
            'lat' => $lat,
            'lng' => $lng,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ], (int) $user['id']),
    ], 201);
}

mobile_error('Method not allowed.', 405);
