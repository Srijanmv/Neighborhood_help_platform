<?php
require 'inc/config.php';
require 'inc/auth.php';

$user = current_user();
$post_id = $_GET['id'] ?? null;
$categoryColumnAvailable = ensure_posts_category_column();
$sosColumnAvailable = ensure_posts_sos_column();
$urgencyVotesAvailable = ensure_post_urgency_votes_table();
$helperPointsAvailable = ensure_helper_points_tables();

if (!$post_id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT p.*, u.name, u.area as user_area FROM posts p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
$stmt->execute([$post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    echo 'Post not found';
    exit;
}

$resolvedCategory = $categoryColumnAvailable && !empty($post['category'])
    ? $post['category']
    : detect_post_category($post['title'] ?? '', $post['description'] ?? '');

$canDeletePost = $user && ($user['id'] == $post['user_id'] || $user['role'] === 'admin');
$hasOfferedHelp = $user ? has_user_offered_help((int) $post_id, (int) $user['id']) : false;
$helperList = get_post_helpers((int) $post_id);
$urgencySummary = get_post_urgency_summary((int) $post_id, $user ? (int) $user['id'] : null);
$currentUserPoints = $user ? get_user_helper_points((int) $user['id']) : 0;
$chatLink = null;
if ($user && (int) $user['id'] !== (int) $post['user_id']) {
    $chatLink = 'message_thread.php?post_id=' . (int) $post_id . '&user_id=' . (int) $post['user_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['delete_post']) && $canDeletePost) {
        if (!empty($post['image'])) {
            $imagePath = __DIR__ . '/assets/uploads/' . $post['image'];
            if (is_file($imagePath)) {
                @unlink($imagePath);
            }
        }
        $deleteStmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $deleteStmt->execute([$post_id]);
        header('Location: community.php?deleted=1');
        exit;
    }

    if ($user && isset($_POST['comment'])) {
        $comment = trim($_POST['comment']);
        if ($comment !== '') {
            $stmt = $pdo->prepare("INSERT INTO comments (post_id,user_id,comment) VALUES (?,?,?)");
            $stmt->execute([$post_id, $user['id'], $comment]);
            broadcast_notification($user['id'], (int) $post_id, 'new_comment', $user['name'] . ' commented on: ' . $post['title']);
            header('Location: view_post.php?id=' . $post_id);
            exit;
        }
    }

    if ($user && !empty($_POST['offer_help']) && (int) $user['id'] !== (int) $post['user_id']) {
        $alreadyHelping = has_user_offered_help((int) $post_id, (int) $user['id']);
        offer_post_help((int) $post_id, (int) $user['id']);
        if (!$alreadyHelping) {
            award_helper_points((int) $user['id'], 10, 'Helped on a community post', (int) $post_id, 'offer_help:' . (int) $post_id . ':' . (int) $user['id']);
        }
        $conversationId = get_or_create_conversation((int) $post_id, (int) $user['id'], (int) $post['user_id']);

        if (!$alreadyHelping) {
            notify_user(
                (int) $post['user_id'],
                (int) $user['id'],
                (int) $post_id,
                'helper_joined',
                $user['name'] . ' offered help on your post: ' . $post['title']
            );
        }

        if ($conversationId) {
            header('Location: message_thread.php?id=' . $conversationId);
            exit;
        }

        header('Location: view_post.php?id=' . (int) $post_id);
        exit;
    }

    if ($user && isset($_POST['urgency_vote']) && (int) $user['id'] !== (int) $post['user_id']) {
        cast_post_urgency_vote((int) $post_id, (int) $user['id'], (int) $_POST['urgency_vote']);
        header('Location: view_post.php?id=' . (int) $post_id);
        exit;
    }
}

$cmts = $pdo->prepare("SELECT c.*, u.name FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = ? ORDER BY c.created_at DESC");
$cmts->execute([$post_id]);
$comments = $cmts->fetchAll(PDO::FETCH_ASSOC);

$statusClass = 'pending';
if ($post['status'] === 'solved') {
    $statusClass = 'solved';
} elseif ($post['status'] === 'in_progress') {
    $statusClass = 'in-progress';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($post['title']); ?> - Neighborhood Help</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --surface: rgba(255, 255, 255, 0.72);
      --ink: #1f2937;
      --muted: #5b6472;
      --brand: #14532d;
      --line: rgba(20, 83, 45, 0.12);
      --shadow: 0 24px 60px rgba(31, 41, 55, 0.12);
      --radius-xl: 32px;
      --max: 1120px;
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
    .page-shell { position: relative; overflow: hidden; min-height: 100vh; }
    .page-shell::before,
    .page-shell::after {
      content: "";
      position: absolute;
      border-radius: 999px;
      filter: blur(10px);
      pointer-events: none;
      z-index: 0;
    }
    .page-shell::before {
      width: 280px;
      height: 280px;
      background: rgba(217, 249, 157, 0.48);
      top: 90px;
      left: -90px;
    }
    .page-shell::after {
      width: 240px;
      height: 240px;
      background: rgba(249, 115, 22, 0.16);
      bottom: 60px;
      right: -80px;
    }
    .container {
      width: min(calc(100% - 32px), var(--max));
      margin: 0 auto;
      position: relative;
      z-index: 1;
      padding: 24px 0 36px;
    }
    .top-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 18px;
      flex-wrap: wrap;
    }
    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 11px 16px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.72);
      border: 1px solid rgba(255, 255, 255, 0.7);
      box-shadow: 0 10px 30px rgba(20, 83, 45, 0.08);
      color: var(--brand);
      font-weight: 700;
    }
    .top-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .notif-menu,
    .secondary-link,
    .danger-button {
      position: relative;
    }
    .notif-trigger,
    .secondary-link,
    .danger-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 11px 16px;
      border-radius: 999px;
      font-weight: 700;
      font: inherit;
    }
    .notif-trigger,
    .secondary-link {
      border: 0;
      cursor: pointer;
      background: rgba(217, 249, 157, 0.56);
      color: var(--brand);
    }
    .chat-cta {
      background: linear-gradient(135deg, #0f766e, #14b8a6);
      color: #fff;
    }
    .danger-button {
      border: 0;
      cursor: pointer;
      background: rgba(239, 68, 68, 0.12);
      color: #b91c1c;
    }
    .notif-count {
      min-width: 24px;
      height: 24px;
      padding: 0 7px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: #f97316;
      color: #fff;
      font-size: 0.78rem;
      font-weight: 800;
    }
    .notif-dropdown {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      width: min(360px, calc(100vw - 32px));
      padding: 14px;
      border-radius: 24px;
      background: rgba(255, 255, 255, 0.96);
      border: 1px solid rgba(20, 83, 45, 0.12);
      box-shadow: 0 22px 42px rgba(31, 41, 55, 0.16);
      display: none;
      z-index: 30;
    }
    .notif-menu.open .notif-dropdown { display: block; }
    .notif-dropdown-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 12px;
    }
    .notif-dropdown-head strong { font-size: 0.98rem; }
    .notif-dropdown-head a {
      color: var(--brand);
      font-size: 0.88rem;
      font-weight: 700;
    }
    .notif-list { display: grid; gap: 10px; }
    .notif-item,
    .notif-empty {
      display: grid;
      gap: 6px;
      padding: 12px 14px;
      border-radius: 18px;
      background: rgba(248, 250, 252, 0.95);
      border: 1px solid var(--line);
    }
    .notif-item strong { font-size: 0.92rem; line-height: 1.5; }
    .notif-item span,
    .notif-empty {
      color: var(--muted);
      font-size: 0.84rem;
      line-height: 1.5;
    }
    .login-link { color: var(--brand); font-weight: 700; }
    .layout {
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(280px, 0.82fr);
      gap: 24px;
      align-items: start;
    }
    .post-card,
    .comments-card,
    .sidebar-card {
      background: var(--surface);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.7);
      box-shadow: var(--shadow);
      border-radius: var(--radius-xl);
    }
    .post-card,
    .comments-card { padding: 28px; }
    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 9px 14px;
      border-radius: 999px;
      background: rgba(217, 249, 157, 0.56);
      color: var(--brand);
      font-size: 0.85rem;
      font-weight: 800;
      margin-bottom: 16px;
    }
    h1 {
      margin: 0;
      font-size: clamp(2rem, 4vw, 3.2rem);
      line-height: 1;
      letter-spacing: -0.04em;
    }
    .meta {
      margin-top: 12px;
      color: var(--muted);
      line-height: 1.7;
      font-size: 0.94rem;
    }
    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-top: 20px;
      padding: 9px 14px;
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 800;
      text-transform: uppercase;
    }
    .tag-row {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .category-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-top: 20px;
      padding: 9px 14px;
      border-radius: 999px;
      background: rgba(20, 83, 45, 0.08);
      color: var(--brand);
      font-size: 0.82rem;
      font-weight: 800;
      text-transform: uppercase;
    }
    .status-pill.pending { background: rgba(239, 68, 68, 0.12); color: #b91c1c; }
    .status-pill.in-progress { background: rgba(249, 115, 22, 0.14); color: #c2410c; }
    .status-pill.solved { background: rgba(22, 163, 74, 0.12); color: #15803d; }
    .status-pill.sos { background: rgba(185, 28, 28, 0.12); color: #b91c1c; }
    .post-description {
      margin-top: 20px;
      line-height: 1.8;
      color: #374151;
      font-size: 1rem;
    }
    .post-image {
      margin-top: 20px;
      width: 100%;
      border-radius: 24px;
      border: 1px solid rgba(20, 83, 45, 0.12);
      max-height: 460px;
      object-fit: cover;
    }
    .sidebar-stack { display: grid; gap: 18px; }
    .sidebar-card { padding: 22px; }
    .sidebar-card h3,
    .comments-card h2 { margin: 0 0 12px; font-size: 1.2rem; }
      .sidebar-card p,
    .sidebar-card li,
    .empty-state,
    .comment-meta { color: var(--muted); line-height: 1.7; font-size: 0.94rem; }
    .location-text { margin-bottom: 14px; }
    .map-shell {
      overflow: hidden;
      border-radius: 24px;
      border: 1px solid rgba(20, 83, 45, 0.12);
      background: #dbe7d7;
    }
    #map { height: 280px; width: 100%; }
    .comment-form { display: grid; gap: 12px; margin-bottom: 22px; }
    textarea {
      width: 100%;
      min-height: 120px;
      border: 1px solid rgba(20, 83, 45, 0.12);
      background: rgba(255, 255, 255, 0.9);
      color: var(--ink);
      border-radius: 18px;
      padding: 14px 15px;
      font: inherit;
      resize: vertical;
      outline: none;
    }
    .primary-button {
      width: fit-content;
      border: 0;
      cursor: pointer;
      padding: 11px 18px;
      border-radius: 999px;
      background: var(--brand);
      color: #fff;
      font: inherit;
      font-weight: 700;
    }
    .helper-list {
      margin: 14px 0 0;
      padding-left: 18px;
    }
    .helper-item + .helper-item {
      margin-top: 12px;
    }
    .urgency-panel {
      margin-top: 22px;
      padding: 18px;
      border-radius: 24px;
      background: rgba(248, 250, 252, 0.92);
      border: 1px solid var(--line);
      display: grid;
      gap: 14px;
    }
    .urgency-stats {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      color: var(--muted);
      font-size: 0.94rem;
    }
    .urgency-chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 999px;
      background: rgba(249, 115, 22, 0.12);
      color: #c2410c;
      font-weight: 800;
    }
    .vote-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    .vote-row form { margin: 0; }
    .vote-button {
      border: 0;
      cursor: pointer;
      padding: 11px 15px;
      border-radius: 999px;
      font: inherit;
      font-weight: 700;
      background: rgba(255, 255, 255, 0.94);
      color: var(--ink);
      border: 1px solid var(--line);
    }
    .vote-button.active-urgent {
      background: rgba(185, 28, 28, 0.12);
      color: #b91c1c;
      border-color: rgba(185, 28, 28, 0.2);
    }
    .vote-button.active-calm {
      background: rgba(20, 83, 45, 0.1);
      color: var(--brand);
      border-color: rgba(20, 83, 45, 0.18);
    }
    .points-card strong {
      display: block;
      margin-bottom: 6px;
      font-size: 2rem;
      line-height: 1;
      color: var(--brand);
    }
    .comments-list { display: grid; gap: 14px; }
    .comment-item {
      background: rgba(255, 255, 255, 0.72);
      border: 1px solid var(--line);
      border-radius: 20px;
      padding: 16px;
    }
    .comment-meta { margin-bottom: 8px; }
    .comment-body { line-height: 1.7; color: #374151; }
    @media (max-width: 920px) { .layout { grid-template-columns: 1fr; } }
    @media (max-width: 640px) {
      .container { width: min(calc(100% - 20px), var(--max)); }
      .post-card, .comments-card, .sidebar-card { border-radius: 24px; padding: 22px; }
      #map { height: 240px; }
    }
  </style>
</head>
<body>
  <div class="page-shell">
    <div class="container">
      <div class="top-row">
        <a href="community.php" class="back-link">&larr; Back to Feed</a>
        <div class="top-actions">
          <a href="<?php echo $user ? 'messages.php' : 'login.php'; ?>" class="secondary-link chat-cta">Open Chats</a>
          <?php if ($user): ?>
            <a href="messages.php" class="secondary-link">Messages<?php $threadUnread = get_unread_message_count($user['id']); if ($threadUnread > 0): ?> (<?php echo e((string) $threadUnread); ?>)<?php endif; ?></a>
          <?php endif; ?>
          <?php if ($user): ?>
            <?php include __DIR__ . '/inc/notification_dropdown.php'; ?>
          <?php endif; ?>
          <?php if ($canDeletePost): ?>
            <form method="post" onsubmit="return confirm('Are you sure you want to delete this post?');">
              <input type="hidden" name="delete_post" value="1">
              <button type="submit" class="danger-button">Delete Post</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <main class="layout">
        <section>
          <article class="post-card">
            <span class="eyebrow">Community request</span>
            <h1><?php echo e($post['title']); ?></h1>
            <div class="meta">
              By <?php echo e($post['name']); ?> from <?php echo e($post['area']); ?><br>
              Posted on <?php echo e($post['created_at']); ?>
            </div>
            <div class="tag-row">
              <div class="status-pill <?php echo e($statusClass); ?>">
                <?php echo e(str_replace('_', ' ', $post['status'])); ?>
              </div>
              <div class="category-pill"><?php echo e($resolvedCategory); ?> Issue</div>
              <?php if (!empty($post['is_sos'])): ?>
                <div class="status-pill sos">SOS Alert</div>
              <?php endif; ?>
            </div>
            <?php if ($user && (int) $user['id'] !== (int) $post['user_id']): ?>
              <div class="tag-row">
                <?php if ($chatLink): ?>
                  <a href="<?php echo e($chatLink); ?>" class="primary-button">Message <?php echo e($post['name']); ?></a>
                <?php endif; ?>
                <form method="post">
                  <input type="hidden" name="offer_help" value="1">
                  <button type="submit" class="secondary-link" style="border:0; cursor:pointer;">
                    <?php echo $hasOfferedHelp ? 'You are helping' : 'I Can Help'; ?>
                  </button>
                </form>
              </div>
            <?php endif; ?>
            <div class="urgency-panel">
              <div class="urgency-stats">
                <span class="urgency-chip">Priority <?php echo e((string) $urgencySummary['score']); ?></span>
                <span><?php echo e((string) $urgencySummary['urgent_yes']); ?> people marked it urgent</span>
                <span><?php echo e((string) $urgencySummary['not_urgent']); ?> voted not urgent</span>
              </div>
              <?php if ($user && (int) $user['id'] !== (int) $post['user_id']): ?>
                <div class="vote-row">
                  <form method="post">
                    <input type="hidden" name="urgency_vote" value="1">
                    <button type="submit" class="vote-button <?php echo (int) $urgencySummary['user_vote'] === 1 ? 'active-urgent' : ''; ?>">This problem is urgent</button>
                  </form>
                  <form method="post">
                    <input type="hidden" name="urgency_vote" value="0">
                    <button type="submit" class="vote-button <?php echo $urgencySummary['user_vote'] === 0 ? 'active-calm' : ''; ?>">This can wait</button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
            <div class="post-description"><?php echo nl2br(e($post['description'])); ?></div>
            <?php if ($post['image']): ?>
              <img src="assets/uploads/<?php echo e($post['image']); ?>" alt="" class="post-image">
            <?php endif; ?>
          </article>

          <section class="comments-card" style="margin-top:24px;">
            <h2>Comments</h2>
            <?php if ($user): ?>
              <form method="post" class="comment-form">
                <textarea name="comment" required></textarea>
                <button type="submit" class="primary-button">Add Comment</button>
              </form>
            <?php else: ?>
              <p class="empty-state">Please <a href="login.php" class="login-link">login</a> to comment.</p>
            <?php endif; ?>
            <div class="comments-list">
              <?php foreach ($comments as $comment): ?>
                <div class="comment-item">
                  <div class="comment-meta"><?php echo e($comment['name']); ?> | <?php echo e($comment['created_at']); ?></div>
                  <div class="comment-body"><?php echo e($comment['comment']); ?></div>
                </div>
              <?php endforeach; ?>
              <?php if (empty($comments)): ?>
                <div class="empty-state">No comments yet.</div>
              <?php endif; ?>
            </div>
          </section>
        </section>

        <aside class="sidebar-stack">
          <?php if (!empty($post['location'])): ?>
            <div class="sidebar-card">
              <h3>Location</h3>
              <p class="location-text"><?php echo e($post['location']); ?></p>
              <?php if (!empty($post['lat']) && !empty($post['lng'])): ?>
                <div class="map-shell"><div id="map"></div></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="sidebar-card">
            <h3>Why this map matters</h3>
            <p>Precise pins help nearby residents understand whether they can respond quickly, bring the right resources, or coordinate with others already helping.</p>
          </div>

          <div class="sidebar-card points-card">
            <h3>Your Helper Score</h3>
            <strong><?php echo e((string) $currentUserPoints); ?></strong>
            <p>Har first-time help join par points milte hain. Zyada meaningful help, zyada leaderboard momentum.</p>
          </div>

          <div class="sidebar-card">
            <h3>Helpers on this post</h3>
            <p><?php echo e((string) count($helperList)); ?> people have already volunteered to support this request.</p>
            <?php if (empty($helperList)): ?>
              <p>No helpers yet. First helper can jump in and start the chat.</p>
            <?php else: ?>
              <ul class="helper-list">
                <?php foreach ($helperList as $helper): ?>
                  <li class="helper-item">
                    <strong><?php echo e($helper['name']); ?></strong><br>
                    <?php echo e($helper['area']); ?> • Joined <?php echo e($helper['created_at']); ?>
                    <?php if ($user && (int) $user['id'] === (int) $post['user_id']): ?>
                      <br><a href="message_thread.php?post_id=<?php echo (int) $post_id; ?>&user_id=<?php echo (int) $helper['helper_user_id']; ?>" class="login-link">Message helper</a>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </aside>
      </main>
    </div>
  </div>

  <?php include __DIR__ . '/inc/chatbot_widget.php'; ?>

  <?php if (!empty($post['lat']) && !empty($post['lng'])): ?>
    <script>
      if (window.location.hostname === '127.0.0.1') {
        window.location.replace(window.location.href.replace('//127.0.0.1', '//localhost'));
      }

      function initViewPostMap() {
        const postLocation = {
          lat: <?php echo json_encode((float) $post['lat']); ?>,
          lng: <?php echo json_encode((float) $post['lng']); ?>
        };
        const map = new google.maps.Map(document.getElementById('map'), {
          center: postLocation,
          zoom: 15,
          mapTypeControl: false,
          streetViewControl: false,
          fullscreenControl: true
        });
        const marker = new google.maps.Marker({
          position: postLocation,
          map,
          title: <?php echo json_encode($post['title']); ?>,
          icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 10,
            strokeColor: '#14532d',
            strokeWeight: 3,
            fillColor: '#d9f99d',
            fillOpacity: 0.95
          }
        });
        const infoWindow = new google.maps.InfoWindow({
          content: <?php echo json_encode('<strong>' . e($post['title']) . '</strong>'); ?>
        });
        infoWindow.open({ anchor: marker, map });
        marker.addListener('click', () => infoWindow.open({ anchor: marker, map }));
      }

      document.querySelectorAll('.notif-menu').forEach((menu) => {
        const trigger = menu.querySelector('[data-notif-toggle]');
        if (!trigger) return;
        trigger.addEventListener('click', function (event) {
          event.stopPropagation();
          const isOpen = menu.classList.contains('open');
          document.querySelectorAll('.notif-menu.open').forEach((openMenu) => {
            openMenu.classList.remove('open');
            const btn = openMenu.querySelector('[data-notif-toggle]');
            if (btn) btn.setAttribute('aria-expanded', 'false');
          });
          menu.classList.toggle('open', !isOpen);
          trigger.setAttribute('aria-expanded', String(!isOpen));
        });
      });

      document.addEventListener('click', function (event) {
        document.querySelectorAll('.notif-menu.open').forEach((menu) => {
          if (!menu.contains(event.target)) {
            menu.classList.remove('open');
            const btn = menu.querySelector('[data-notif-toggle]');
            if (btn) btn.setAttribute('aria-expanded', 'false');
          }
        });
      });
    </script>
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo rawurlencode($google_maps_api_key); ?>&callback=initViewPostMap&loading=async"></script>
  <?php else: ?>
    <script>
      document.querySelectorAll('.notif-menu').forEach((menu) => {
        const trigger = menu.querySelector('[data-notif-toggle]');
        if (!trigger) return;
        trigger.addEventListener('click', function (event) {
          event.stopPropagation();
          const isOpen = menu.classList.contains('open');
          document.querySelectorAll('.notif-menu.open').forEach((openMenu) => {
            openMenu.classList.remove('open');
            const btn = openMenu.querySelector('[data-notif-toggle]');
            if (btn) btn.setAttribute('aria-expanded', 'false');
          });
          menu.classList.toggle('open', !isOpen);
          trigger.setAttribute('aria-expanded', String(!isOpen));
        });
      });

      document.addEventListener('click', function (event) {
        document.querySelectorAll('.notif-menu.open').forEach((menu) => {
          if (!menu.contains(event.target)) {
            menu.classList.remove('open');
            const btn = menu.querySelector('[data-notif-toggle]');
            if (btn) btn.setAttribute('aria-expanded', 'false');
          }
        });
      });
    </script>
  <?php endif; ?>
</body>
</html>
