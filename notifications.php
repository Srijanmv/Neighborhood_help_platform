<?php
require 'inc/config.php';
require 'inc/auth.php';

require_login();

$user = current_user();
ensure_notifications_table();
mark_notifications_read($user['id']);
$notifications = get_notifications_for_user($user['id'], 50);
$unreadMessageCount = get_unread_message_count($user['id']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notifications - Neighborhood Help</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --surface: rgba(255, 255, 255, 0.76);
      --ink: #1f2937;
      --muted: #5b6472;
      --brand: #14532d;
      --line: rgba(20, 83, 45, 0.12);
      --shadow: 0 24px 60px rgba(31, 41, 55, 0.12);
      --radius-xl: 30px;
      --max: 980px;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: 'Manrope', sans-serif;
      color: var(--ink);
      background:
        radial-gradient(circle at top left, rgba(249, 115, 22, 0.16), transparent 28%),
        radial-gradient(circle at 85% 10%, rgba(34, 197, 94, 0.14), transparent 24%),
        linear-gradient(180deg, #fbf7f0 0%, #f6efe2 45%, #f8f4ec 100%);
    }
    a { color: inherit; text-decoration: none; }
    .container {
      width: min(calc(100% - 32px), var(--max));
      margin: 0 auto;
      padding: 24px 0 36px;
    }
    .topbar, .panel, .notification-card {
      background: var(--surface);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.74);
      box-shadow: var(--shadow);
      border-radius: var(--radius-xl);
    }
    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      flex-wrap: wrap;
      padding: 16px 18px;
      margin-bottom: 20px;
    }
    .panel { padding: 26px; }
    .title-row h1 {
      margin: 0 0 8px;
      font-size: clamp(2rem, 4vw, 3rem);
      line-height: 1;
      letter-spacing: -0.04em;
    }
    .title-row p, .notification-meta, .empty-copy { color: var(--muted); }
    .back-link, .action-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 11px 16px;
      border-radius: 999px;
      font-weight: 700;
    }
    .back-link {
      background: rgba(217, 249, 157, 0.58);
      color: var(--brand);
    }
    .action-link {
      background: linear-gradient(135deg, #14532d, #22c55e);
      color: #fff;
    }
    .notification-list {
      display: grid;
      gap: 16px;
      margin-top: 22px;
    }
    .notification-card {
      padding: 20px;
      border-radius: 24px;
    }
    .notification-card strong {
      display: block;
      margin-bottom: 8px;
      font-size: 1rem;
    }
    .notification-meta {
      font-size: 0.92rem;
      line-height: 1.7;
    }
    .empty-state {
      padding: 34px 20px;
      text-align: center;
      border-radius: 24px;
      background: rgba(255, 255, 255, 0.72);
      border: 1px solid var(--line);
    }
    @media (max-width: 640px) {
      .container { width: min(calc(100% - 20px), var(--max)); }
      .panel, .topbar, .notification-card { border-radius: 24px; }
      .panel { padding: 22px; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="topbar">
      <a href="community.php" class="back-link">&larr; Back to Feed</a>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="messages.php" class="back-link">Messages<?php if ($unreadMessageCount > 0): ?> (<?php echo e((string) $unreadMessageCount); ?>)<?php endif; ?></a>
        <a href="new_post.php" class="action-link">Create Post</a>
      </div>
    </div>

    <section class="panel">
      <div class="title-row">
        <h1>Notifications</h1>
        <p>Here you can see alerts for new posts and comments from other users.</p>
      </div>

      <?php if (empty($notifications)): ?>
        <div class="empty-state">
          <div class="empty-copy">No notifications yet.</div>
        </div>
      <?php else: ?>
        <div class="notification-list">
          <?php foreach ($notifications as $notification): ?>
            <?php
              $notificationLink = 'community.php';
              if (($notification['type'] ?? '') === 'new_message') {
                  $notificationLink = 'messages.php';
              } elseif (!empty($notification['post_id'])) {
                  $notificationLink = 'view_post.php?id=' . (int) $notification['post_id'];
              }
            ?>
            <a class="notification-card" href="<?php echo e($notificationLink); ?>">
              <strong><?php echo e($notification['message']); ?></strong>
              <div class="notification-meta">
                Type: <?php echo e(ucwords(str_replace('_', ' ', $notification['type']))); ?><br>
                Time: <?php echo e($notification['created_at']); ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
  <?php include __DIR__ . '/inc/chatbot_widget.php'; ?>
</body>
</html>
