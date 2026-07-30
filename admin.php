<?php
require 'inc/config.php';
require 'inc/auth.php';
$user = current_user();
if (!$user || $user['role'] !== 'admin') {
    die('Access denied. You must be admin.');
}
$post_id = $_GET['id'] ?? null;
if (!$post_id) {
    header('Location: community.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_post'])) {
    $stmt = $pdo->prepare("SELECT image FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $postImage = $stmt->fetchColumn();
    if ($postImage) {
        $imagePath = __DIR__ . '/assets/uploads/' . $postImage;
        if (is_file($imagePath)) {
            @unlink($imagePath);
        }
    }
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    header('Location: community.php?deleted=1');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['status'])) {
    $status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE posts SET status = ? WHERE id = ?");
    $stmt->execute([$status, $post_id]);
    header('Location: admin.php?id=' . $post_id);
    exit;
}
$stmt = $pdo->prepare("SELECT p.*, u.name FROM posts p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
$stmt->execute([$post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$post) { echo "Post not found"; exit; }
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin - Manage Post</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="p-6">
  <a href="community.php" class="text-blue-600">&larr; Back</a>
  <div class="max-w-2xl mx-auto mt-4 bg-white p-4 rounded shadow">
    <h2 class="text-xl font-semibold">Manage Post</h2>
    <p class="text-sm text-gray-600">Title: <?php echo e($post['title']); ?></p>
    <p class="mt-3"><?php echo e($post['description']); ?></p>
    <form method="post" class="mt-4">
      <label class="block mb-2">Status
        <select name="status" class="border p-2 rounded">
          <option value="pending" <?php if($post['status']=='pending') echo 'selected'; ?>>Pending</option>
          <option value="in_progress" <?php if($post['status']=='in_progress') echo 'selected'; ?>>In Progress</option>
          <option value="solved" <?php if($post['status']=='solved') echo 'selected'; ?>>Solved</option>
        </select>
      </label>
      <button class="bg-green-600 text-white px-3 py-1 rounded">Update</button>
    </form>
    <form method="post" class="mt-4" onsubmit="return confirm('Are you sure you want to delete this post?');">
      <input type="hidden" name="delete_post" value="1">
      <button class="bg-red-600 text-white px-3 py-1 rounded">Delete Post</button>
    </form>
  </div>
  <?php include __DIR__ . '/inc/chatbot_widget.php'; ?>
</body>
</html>
