<?php
require 'inc/config.php';
require 'inc/auth.php';

require_login();

$user = current_user();
$conversationId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($conversationId <= 0 && !empty($_GET['user_id'])) {
    $targetUserId = (int) $_GET['user_id'];
    $targetPostId = !empty($_GET['post_id']) ? (int) $_GET['post_id'] : null;

    if ($targetUserId > 0 && $targetUserId !== (int) $user['id']) {
        $conversationId = get_or_create_conversation($targetPostId, (int) $user['id'], $targetUserId) ?: 0;
    }
}

$conversation = get_conversation_for_user($conversationId, $user['id']);

if (!$conversation) {
    header('Location: messages.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');

    if ($message === '') {
        $errors[] = 'Please type a message.';
    } else {
        send_conversation_message($conversationId, $user['id'], $message);
        notify_user(
            (int) $conversation['other_user_id'],
            (int) $user['id'],
            !empty($conversation['post_id']) ? (int) $conversation['post_id'] : null,
            'new_message',
            $user['name'] . ' sent you a message'
        );
        header('Location: message_thread.php?id=' . $conversationId);
        exit;
    }
}

mark_conversation_read($conversationId, $user['id']);
$messages = get_messages_for_conversation($conversationId);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chat with <?php echo e($conversation['other_user_name']); ?> - Neighborhood Help</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --surface: rgba(255, 255, 255, 0.8);
      --ink: #172033;
      --muted: #607086;
      --brand: #14532d;
      --line: rgba(20, 83, 45, 0.12);
      --shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
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
    .chat-shell {
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
      gap: 14px;
      flex-wrap: wrap;
      padding: 16px 18px;
      margin-bottom: 20px;
    }
    .back-link,
    .view-post-link,
    .send-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 11px 16px;
      border-radius: 999px;
      font-weight: 700;
      border: 0;
      font: inherit;
      cursor: pointer;
    }
    .back-link {
      background: rgba(217, 249, 157, 0.58);
      color: var(--brand);
    }
    .view-post-link,
    .send-button {
      background: linear-gradient(135deg, #14532d, #22c55e);
      color: #fff;
    }
    .chat-shell {
      padding: 24px;
      display: grid;
      gap: 18px;
    }
    .chat-head h1 {
      margin: 0 0 8px;
      font-size: clamp(1.9rem, 4vw, 2.6rem);
      line-height: 1;
      letter-spacing: -0.04em;
    }
    .chat-head p,
    .bubble-meta {
      color: var(--muted);
      line-height: 1.7;
    }
    .context-chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 9px 14px;
      border-radius: 999px;
      background: rgba(20, 83, 45, 0.08);
      color: var(--brand);
      font-size: 0.84rem;
      font-weight: 800;
    }
    .messages {
      display: grid;
      gap: 14px;
      max-height: 56vh;
      overflow-y: auto;
      padding-right: 4px;
    }
    .message-row {
      display: flex;
    }
    .message-row.mine {
      justify-content: flex-end;
    }
    .message-bubble {
      width: min(100%, 540px);
      padding: 14px 16px;
      border-radius: 22px;
      background: rgba(255, 255, 255, 0.92);
      border: 1px solid var(--line);
      box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
    }
    .message-row.mine .message-bubble {
      background: linear-gradient(135deg, rgba(20, 83, 45, 0.96), rgba(34, 197, 94, 0.92));
      color: #fff;
      border-color: transparent;
    }
    .message-body {
      line-height: 1.8;
      white-space: pre-wrap;
      word-break: break-word;
    }
    .message-row.mine .bubble-meta {
      color: rgba(255, 255, 255, 0.84);
    }
    .composer {
      display: grid;
      gap: 12px;
    }
    textarea {
      width: 100%;
      min-height: 110px;
      border: 1px solid var(--line);
      border-radius: 20px;
      padding: 14px 15px;
      font: inherit;
      outline: none;
      resize: vertical;
      background: rgba(255, 255, 255, 0.94);
      color: var(--ink);
    }
    .error-box {
      padding: 14px 16px;
      border-radius: 18px;
      background: rgba(254, 226, 226, 0.86);
      color: #b91c1c;
      border: 1px solid rgba(220, 38, 38, 0.14);
    }
    .composer-actions {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      align-items: center;
    }
    .composer-actions span {
      color: var(--muted);
      font-size: 0.92rem;
    }
    @media (max-width: 640px) {
      .container { width: min(calc(100% - 20px), var(--max)); }
      .topbar, .chat-shell { border-radius: 24px; }
      .chat-shell { padding: 20px; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="topbar">
      <a href="messages.php" class="back-link">&larr; All Chats</a>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <?php if (!empty($conversation['post_id'])): ?>
          <a href="view_post.php?id=<?php echo (int) $conversation['post_id']; ?>" class="view-post-link">View Post</a>
        <?php endif; ?>
      </div>
    </div>

    <section class="chat-shell">
      <div class="chat-head">
        <h1><?php echo e($conversation['other_user_name']); ?></h1>
        <p>Direct coordination chat for neighborhood help. Aap dono yahan details, timing aur support discuss kar sakte ho.</p>
      </div>

      <?php if (!empty($conversation['post_title'])): ?>
        <div class="context-chip">Post context: <?php echo e($conversation['post_title']); ?></div>
      <?php endif; ?>

      <div class="messages">
        <?php foreach ($messages as $message): ?>
          <div class="message-row <?php echo (int) $message['sender_user_id'] === (int) $user['id'] ? 'mine' : ''; ?>">
            <div class="message-bubble">
              <div class="message-body"><?php echo e($message['message']); ?></div>
              <div class="bubble-meta">
                <?php echo e($message['sender_name']); ?> • <?php echo e($message['created_at']); ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <form method="post" class="composer">
        <?php if ($errors): ?>
          <div class="error-box"><?php echo e(implode(' ', $errors)); ?></div>
        <?php endif; ?>
        <textarea name="message" placeholder="Type your message here..." required></textarea>
        <div class="composer-actions">
          <span>Helpful tip: timing, landmark, phone availability ya exact support type mention karo.</span>
          <button type="submit" class="send-button">Send Message</button>
        </div>
      </form>
    </section>
  </div>
  <?php include __DIR__ . '/inc/chatbot_widget.php'; ?>
</body>
</html>
