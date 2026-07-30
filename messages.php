<?php
require 'inc/config.php';
require 'inc/auth.php';

require_login();

$user = current_user();
$conversations = get_conversations_for_user($user['id']);
$totalUnread = get_unread_message_count($user['id']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Messages - Neighborhood Help</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --surface: rgba(255, 255, 255, 0.76);
      --ink: #172033;
      --muted: #607086;
      --brand: #14532d;
      --line: rgba(20, 83, 45, 0.12);
      --shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
      --radius-xl: 30px;
      --max: 1040px;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: 'Manrope', sans-serif;
      color: var(--ink);
      background:
        radial-gradient(circle at top left, rgba(249, 115, 22, 0.18), transparent 26%),
        radial-gradient(circle at 86% 12%, rgba(34, 197, 94, 0.18), transparent 24%),
        linear-gradient(180deg, #fbf7ef 0%, #f7f1e7 100%);
    }
    a { color: inherit; text-decoration: none; }
    .container {
      width: min(calc(100% - 32px), var(--max));
      margin: 0 auto;
      padding: 24px 0 36px;
    }
    .topbar,
    .panel,
    .conversation-card {
      background: var(--surface);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.76);
      box-shadow: var(--shadow);
      border-radius: var(--radius-xl);
    }
    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      padding: 16px 18px;
      margin-bottom: 20px;
    }
    .back-link,
    .action-link,
    .count-chip {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 11px 16px;
      border-radius: 999px;
      font-weight: 700;
    }
    .back-link,
    .count-chip {
      background: rgba(217, 249, 157, 0.58);
      color: var(--brand);
    }
    .action-link {
      background: linear-gradient(135deg, #14532d, #22c55e);
      color: #fff;
    }
    .panel { padding: 26px; }
    .panel h1 {
      margin: 0 0 10px;
      font-size: clamp(2rem, 4vw, 3rem);
      line-height: 1;
      letter-spacing: -0.04em;
    }
    .panel p,
    .meta,
    .empty-state p {
      color: var(--muted);
      line-height: 1.7;
    }
    .conversation-list {
      display: grid;
      gap: 16px;
      margin-top: 24px;
    }
    .conversation-card {
      padding: 20px;
      display: grid;
      gap: 12px;
    }
    .conversation-head {
      display: flex;
      align-items: start;
      justify-content: space-between;
      gap: 14px;
      flex-wrap: wrap;
    }
    .conversation-head strong {
      display: block;
      margin-bottom: 6px;
      font-size: 1.08rem;
    }
    .post-chip,
    .unread-chip {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 8px 12px;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 800;
    }
    .post-chip {
      background: rgba(20, 83, 45, 0.08);
      color: var(--brand);
    }
    .unread-chip {
      background: rgba(249, 115, 22, 0.14);
      color: #c2410c;
    }
    .snippet {
      color: #334155;
      line-height: 1.7;
    }
    .empty-state {
      margin-top: 24px;
      padding: 32px 22px;
      text-align: center;
      border-radius: 24px;
      background: rgba(255, 255, 255, 0.72);
      border: 1px solid var(--line);
    }
    @media (max-width: 640px) {
      .container { width: min(calc(100% - 20px), var(--max)); }
      .topbar, .panel, .conversation-card { border-radius: 24px; }
      .panel { padding: 22px; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="topbar">
      <a href="community.php" class="back-link">&larr; Back to Feed</a>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <span class="count-chip"><?php echo e((string) $totalUnread); ?> unread</span>
        <a href="new_post.php" class="action-link">Create Post</a>
      </div>
    </div>

    <section class="panel">
      <h1>Your Messages</h1>
      <p>Use this inbox to chat directly with helpers and post owners. Each conversation stays linked to its related post.</p>

      <?php if (empty($conversations)): ?>
        <div class="empty-state">
          <h3>No chats yet</h3>
          <p>Kisi post par help offer karke ya kisi helper ko message karke pehli conversation start karo.</p>
        </div>
      <?php else: ?>
        <div class="conversation-list">
          <?php foreach ($conversations as $conversation): ?>
            <a class="conversation-card" href="message_thread.php?id=<?php echo (int) $conversation['id']; ?>">
              <div class="conversation-head">
                <div>
                  <strong><?php echo e($conversation['other_user_name']); ?></strong>
                  <div class="meta">
                    <?php echo e($conversation['other_user_area']); ?>
                    <?php if (!empty($conversation['last_message_created_at'])): ?>
                      • <?php echo e($conversation['last_message_created_at']); ?>
                    <?php endif; ?>
                  </div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                  <?php if (!empty($conversation['post_title'])): ?>
                    <span class="post-chip"><?php echo e($conversation['post_title']); ?></span>
                  <?php endif; ?>
                  <?php if ((int) $conversation['unread_count'] > 0): ?>
                    <span class="unread-chip"><?php echo e((string) $conversation['unread_count']); ?> new</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="snippet">
                <?php echo e($conversation['last_message'] ?: 'Conversation ready. Say hello to coordinate help.'); ?>
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
