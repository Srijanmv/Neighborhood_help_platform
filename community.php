<?php
require 'inc/config.php';

$user = current_user();
$deleteSuccess = isset($_GET['deleted']) && $_GET['deleted'] === '1';
$filter_area = $_GET['area'] ?? 'all';
$filter_category = $_GET['category'] ?? 'all';
$filter_sos = isset($_GET['sos']) && $_GET['sos'] === '1' ? '1' : '0';
$categoryColumnAvailable = ensure_posts_category_column();
$sosColumnAvailable = ensure_posts_sos_column();
$urgencyVotesAvailable = ensure_post_urgency_votes_table();
$helperPointsAvailable = ensure_helper_points_tables();
$unreadMessageCount = $user ? get_unread_message_count($user['id']) : 0;
$currentUserPoints = $user ? get_user_helper_points((int) $user['id']) : 0;
$helperLeaderboard = get_helper_leaderboard(6);
$currentPath = 'community.php';
$queryParams = [];
if ($filter_area !== 'all') {
    $queryParams['area'] = $filter_area;
}
if ($filter_category !== 'all') {
    $queryParams['category'] = $filter_category;
}
if ($filter_sos === '1') {
    $queryParams['sos'] = '1';
}
$returnUrl = $currentPath . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');

if ($user && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['offer_help_post_id'])) {
    $targetPostId = (int) $_POST['offer_help_post_id'];
    $postLookup = $pdo->prepare("SELECT id, user_id, title FROM posts WHERE id = ?");
    $postLookup->execute([$targetPostId]);
    $targetPost = $postLookup->fetch(PDO::FETCH_ASSOC);

    if ($targetPost && (int) $targetPost['user_id'] !== (int) $user['id']) {
        $alreadyHelping = has_user_offered_help($targetPostId, $user['id']);
        offer_post_help($targetPostId, $user['id']);
        if (!$alreadyHelping) {
            award_helper_points((int) $user['id'], 10, 'Helped on a community post', $targetPostId, 'offer_help:' . $targetPostId . ':' . (int) $user['id']);
        }
        $conversationId = get_or_create_conversation($targetPostId, $user['id'], (int) $targetPost['user_id']);

        if (!$alreadyHelping) {
            notify_user(
                (int) $targetPost['user_id'],
                (int) $user['id'],
                $targetPostId,
                'helper_joined',
                $user['name'] . ' offered help on your post: ' . $targetPost['title']
            );
        }

        if ($conversationId) {
            header('Location: message_thread.php?id=' . $conversationId);
            exit;
        }

        header('Location: ' . $returnUrl);
        exit;
    }
}

if ($user && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['urgency_post_id'], $_POST['urgency_vote'])) {
    cast_post_urgency_vote((int) $_POST['urgency_post_id'], (int) $user['id'], (int) $_POST['urgency_vote']);
    header('Location: ' . $returnUrl . '#feed');
    exit;
}

if ($filter_area === 'all') {
    $stmt = $pdo->query("SELECT p.*, u.name FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC");
} else {
    $stmt = $pdo->prepare("SELECT p.*, u.name FROM posts p JOIN users u ON p.user_id = u.id WHERE p.area = ? ORDER BY p.created_at DESC");
    $stmt->execute([$filter_area]);
}

$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$categories = ['General', 'Animal', 'Water', 'Road', 'Electricity', 'Sanitation', 'Safety', 'Medical'];
$categoryThemes = [
    'General' => 'theme-general',
    'Animal' => 'theme-animal',
    'Water' => 'theme-water',
    'Road' => 'theme-road',
    'Electricity' => 'theme-electricity',
    'Sanitation' => 'theme-sanitation',
    'Safety' => 'theme-safety',
    'Medical' => 'theme-medical',
];

$processedPosts = [];
$totalPosts = count($posts);
$urgentPosts = 0;
$resolvedPosts = 0;
$sosPosts = 0;

foreach ($posts as $post) {
    $detectedCategory = $categoryColumnAvailable && !empty($post['category'])
        ? $post['category']
        : detect_post_category($post['title'] ?? '', $post['description'] ?? '');

    if ($filter_category !== 'all' && $detectedCategory !== $filter_category) {
        continue;
    }

    if (($post['status'] ?? '') === 'pending') {
        $urgentPosts++;
    }
    if (($post['status'] ?? '') === 'solved') {
        $resolvedPosts++;
    }
    if (!empty($post['is_sos'])) {
        $sosPosts++;
    }

    if ($filter_sos === '1' && empty($post['is_sos'])) {
        continue;
    }

    $post['resolved_category'] = $detectedCategory;
    $post['helper_count'] = count(get_post_helpers((int) $post['id']));
    $urgency = get_post_urgency_summary((int) $post['id'], $user ? (int) $user['id'] : null);
    $post['urgent_yes'] = $urgency['urgent_yes'];
    $post['not_urgent'] = $urgency['not_urgent'];
    $post['urgency_score'] = $urgency['score'];
    $post['urgency_total'] = $urgency['total'];
    $post['current_user_urgency_vote'] = $urgency['user_vote'];
    $post['current_user_helping'] = $user ? has_user_offered_help((int) $post['id'], (int) $user['id']) : false;
    $post['chat_link'] = null;
    if ($user && (int) $user['id'] !== (int) $post['user_id']) {
        $post['chat_link'] = 'message_thread.php?post_id=' . (int) $post['id'] . '&user_id=' . (int) $post['user_id'];
    }
    $post['priority_score'] = get_post_priority_score($post);
    $processedPosts[] = $post;
}

usort($processedPosts, static function (array $left, array $right): int {
    $priorityComparison = ($right['priority_score'] ?? 0) <=> ($left['priority_score'] ?? 0);
    if ($priorityComparison !== 0) {
        return $priorityComparison;
    }

    return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
});

$visiblePosts = count($processedPosts);
$topSosPost = null;
foreach ($processedPosts as $candidatePost) {
    if (!empty($candidatePost['is_sos'])) {
        $topSosPost = $candidatePost;
        break;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Neighborhood Help - Community Feed</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-top: #fff7ed;
      --bg-bottom: #f1f5f9;
      --surface: rgba(255, 255, 255, 0.76);
      --surface-strong: rgba(255, 255, 255, 0.92);
      --ink: #172033;
      --muted: #607086;
      --brand: #22c55e;
      --brand-dark: #14532d;
      --accent: #f97316;
      --line: rgba(34, 197, 94, 0.16);
      --shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
      --radius-xl: 30px;
      --radius-lg: 22px;
      --max: 1180px;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: 'Manrope', sans-serif;
      color: var(--ink);
      background:
        radial-gradient(circle at top left, rgba(249, 115, 22, 0.20), transparent 24%),
        radial-gradient(circle at 86% 9%, rgba(45, 212, 191, 0.20), transparent 22%),
        linear-gradient(180deg, var(--bg-top) 0%, #f8fafc 48%, var(--bg-bottom) 100%);
    }
    a { color: inherit; text-decoration: none; }
    .page-shell {
      position: relative;
      overflow: hidden;
      min-height: 100vh;
      padding-bottom: 40px;
    }
    .page-shell::before,
    .page-shell::after {
      content: "";
      position: absolute;
      border-radius: 999px;
      filter: blur(16px);
      z-index: 0;
      pointer-events: none;
    }
    .page-shell::before {
      width: 320px;
      height: 320px;
      background: rgba(45, 212, 191, 0.18);
      top: 90px;
      left: -110px;
    }
    .page-shell::after {
      width: 280px;
      height: 280px;
      background: rgba(249, 115, 22, 0.16);
      right: -90px;
      bottom: 90px;
    }
    .container {
      width: min(calc(100% - 32px), var(--max));
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }
    .navbar {
      padding: 22px 0 18px;
    }
    .nav-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
      padding: 14px 18px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.62);
      border: 1px solid rgba(255, 255, 255, 0.74);
      backdrop-filter: blur(16px);
      box-shadow: 0 12px 32px rgba(34, 197, 94, 0.12);
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .brand-mark {
      width: 46px;
      height: 46px;
      border-radius: 16px;
      display: grid;
      place-items: center;
      background: linear-gradient(135deg, #14532d, #22c55e);
      color: #fff;
      font-weight: 800;
    }
    .brand-copy strong {
      display: block;
      line-height: 1.1;
    }
    .brand-copy span,
    .meta-text,
    .hero-copy p,
    .stat-card span,
    .filter-intro p,
    .card-meta,
    .card-snippet,
    .empty-state p {
      color: var(--muted);
    }
    .nav-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .pill-link,
    .solid-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 11px 16px;
      border-radius: 999px;
      font-weight: 700;
    }
    .pill-link {
      background: rgba(34, 197, 94, 0.12);
      color: var(--brand-dark);
    }
    .solid-link {
      background: linear-gradient(135deg, #14532d, #22c55e);
      color: #fff;
      box-shadow: 0 16px 30px rgba(15, 118, 110, 0.18);
    }
    .chat-link {
      background: linear-gradient(135deg, #0f766e, #14b8a6);
      color: #fff;
      box-shadow: 0 16px 30px rgba(15, 118, 110, 0.18);
    }
    .user-chip {
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.82);
      border: 1px solid rgba(34, 197, 94, 0.14);
      font-size: 0.94rem;
    }
    .notif-menu {
      position: relative;
    }
    .notif-trigger {
      border: 0;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 11px 16px;
      border-radius: 999px;
      background: rgba(34, 197, 94, 0.12);
      color: var(--brand-dark);
      font: inherit;
      font-weight: 700;
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
      border: 1px solid rgba(34, 197, 94, 0.16);
      box-shadow: 0 22px 42px rgba(15, 23, 42, 0.16);
      display: none;
      z-index: 30;
    }
    .notif-menu.open .notif-dropdown {
      display: block;
    }
    .notif-dropdown-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 12px;
    }
    .notif-dropdown-head strong {
      font-size: 0.98rem;
    }
    .notif-dropdown-head a {
      color: var(--brand-dark);
      font-size: 0.88rem;
      font-weight: 700;
    }
    .notif-list {
      display: grid;
      gap: 10px;
    }
    .notif-item,
    .notif-empty {
      display: grid;
      gap: 6px;
      padding: 12px 14px;
      border-radius: 18px;
      background: rgba(248, 250, 252, 0.95);
      border: 1px solid var(--line);
    }
    .notif-item strong {
      font-size: 0.92rem;
      line-height: 1.5;
    }
    .notif-item span,
    .notif-empty {
      color: var(--muted);
      font-size: 0.84rem;
      line-height: 1.5;
    }
    .hero {
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(280px, 0.84fr);
      gap: 22px;
      align-items: stretch;
      margin-bottom: 22px;
    }
    .announcement-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
      margin-bottom: 18px;
      padding: 16px 20px;
      border-radius: 24px;
      background: linear-gradient(135deg, rgba(20, 83, 45, 0.95), rgba(34, 197, 94, 0.92));
      color: #fff;
      box-shadow: 0 18px 40px rgba(34, 197, 94, 0.22);
    }
    .success-banner {
      margin-bottom: 18px;
      padding: 14px 18px;
      border-radius: 22px;
      background: rgba(217, 249, 157, 0.72);
      color: var(--brand-dark);
      font-weight: 700;
      border: 1px solid rgba(34, 197, 94, 0.18);
    }
    .announcement-bar strong {
      display: block;
      font-size: 1rem;
      margin-bottom: 4px;
    }
    .announcement-bar span {
      color: rgba(255, 255, 255, 0.84);
      font-size: 0.93rem;
    }
    .announcement-pill {
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.18);
      border: 1px solid rgba(255, 255, 255, 0.22);
      font-weight: 800;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      font-size: 0.78rem;
    }
    .hero-panel,
    .stats-panel,
    .filters-panel,
    .feed-card,
    .empty-state {
      background: var(--surface);
      border: 1px solid rgba(255, 255, 255, 0.74);
      backdrop-filter: blur(16px);
      box-shadow: var(--shadow);
      border-radius: var(--radius-xl);
    }
    .hero-panel {
      padding: 30px;
      position: relative;
      overflow: hidden;
    }
    .hero-panel::after {
      content: "";
      position: absolute;
      width: 180px;
      height: 180px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(249, 115, 22, 0.26), transparent 68%);
      right: -40px;
      top: -30px;
      pointer-events: none;
    }
    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(217, 249, 157, 0.56);
      color: var(--brand-dark);
      font-size: 0.85rem;
      font-weight: 800;
    }
    .hero-copy {
      margin: 18px 0 24px;
      max-width: 680px;
    }
    .hero-copy h1 {
      margin: 0 0 12px;
      font-size: clamp(2.2rem, 5vw, 4rem);
      line-height: 0.98;
      letter-spacing: -0.05em;
    }
    .hero-copy p {
      margin: 0;
      font-size: 1.03rem;
      line-height: 1.8;
    }
    .hero-actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }
    .ghost-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 11px 16px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.85);
      border: 1px solid rgba(15, 118, 110, 0.10);
      color: var(--brand-dark);
      font-weight: 700;
    }
    .sos-link {
      background: linear-gradient(135deg, #7f1d1d, #dc2626);
      color: #fff;
      box-shadow: 0 18px 34px rgba(185, 28, 28, 0.22);
    }
    .sos-command-card {
      margin-bottom: 18px;
      padding: 18px 20px;
      border-radius: 28px;
      background:
        radial-gradient(circle at right top, rgba(252, 165, 165, 0.2), transparent 24%),
        linear-gradient(135deg, rgba(127, 29, 29, 0.96), rgba(185, 28, 28, 0.92));
      color: #fff;
      box-shadow: 0 24px 44px rgba(127, 29, 29, 0.2);
      display: grid;
      gap: 16px;
    }
    .sos-command-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }
    .sos-command-card strong {
      display: block;
      font-size: 1.1rem;
      margin-bottom: 6px;
    }
    .sos-command-card p,
    .sos-command-meta {
      margin: 0;
      color: rgba(255, 255, 255, 0.86);
      line-height: 1.7;
    }
    .sos-command-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    .sos-command-chip {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 54px;
      padding: 11px 14px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.14);
      border: 1px solid rgba(255, 255, 255, 0.16);
      color: #fff;
      font-weight: 800;
    }
    .sos-pulse-button {
      position: fixed;
      right: 20px;
      bottom: 110px;
      z-index: 45;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 14px 18px;
      border-radius: 999px;
      background: linear-gradient(135deg, #7f1d1d, #ef4444);
      color: #fff;
      font-weight: 900;
      letter-spacing: 0.02em;
      box-shadow: 0 20px 34px rgba(185, 28, 28, 0.28);
      animation: sosPulse 1.8s infinite;
    }
    @keyframes sosPulse {
      0% { transform: scale(1); box-shadow: 0 20px 34px rgba(185, 28, 28, 0.24); }
      50% { transform: scale(1.03); box-shadow: 0 22px 40px rgba(185, 28, 28, 0.34); }
      100% { transform: scale(1); box-shadow: 0 20px 34px rgba(185, 28, 28, 0.24); }
    }
    .stats-panel {
      padding: 22px;
      display: grid;
      gap: 14px;
      align-content: start;
    }
    .stats-panel h2,
    .filter-intro h2 {
      margin: 0;
      font-size: 1.15rem;
    }
    .stat-grid {
      display: grid;
      gap: 12px;
    }
    .stat-card {
      padding: 16px;
      border-radius: 22px;
      background: var(--surface-strong);
      border: 1px solid var(--line);
    }
    .stat-card strong {
      display: block;
      margin-bottom: 6px;
      font-size: 1.75rem;
      line-height: 1;
    }
    .filters-panel {
      padding: 22px;
      margin-bottom: 18px;
    }
    .filters-row {
      display: grid;
      grid-template-columns: minmax(220px, 250px) minmax(0, 1fr);
      gap: 18px;
      align-items: start;
    }
    .filter-intro p {
      margin: 10px 0 0;
      line-height: 1.7;
    }
    .filter-controls {
      display: grid;
      gap: 14px;
    }
    .select-row {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }
    .select-field {
      display: grid;
      gap: 8px;
      font-weight: 700;
    }
    select {
      width: 100%;
      border: 1px solid rgba(34, 197, 94, 0.18);
      background: rgba(255, 255, 255, 0.92);
      color: var(--ink);
      border-radius: 16px;
      padding: 14px 15px;
      font: inherit;
      outline: none;
    }
    .chip-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    .filter-chip {
      border: 0;
      cursor: pointer;
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(217, 249, 157, 0.58);
      color: var(--brand-dark);
      font: inherit;
      font-weight: 700;
      transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease;
    }
    .filter-chip:hover,
    .filter-chip.active {
      background: linear-gradient(135deg, #14532d, #22c55e);
      color: #fff;
      transform: translateY(-1px);
    }
    .feed-grid {
      display: grid;
      gap: 18px;
    }
    .highlights-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 14px;
      margin-bottom: 18px;
    }
    .highlight-card {
      padding: 18px;
      border-radius: 22px;
      color: #fff;
      box-shadow: 0 18px 34px rgba(15, 23, 42, 0.12);
    }
    .highlight-card strong {
      display: block;
      margin-bottom: 8px;
      font-size: 1rem;
    }
    .highlight-card span {
      display: block;
      font-size: 0.92rem;
      line-height: 1.6;
      color: rgba(255, 255, 255, 0.9);
    }
    .highlight-water { background: linear-gradient(135deg, #0284c7, #38bdf8); }
    .highlight-animal { background: linear-gradient(135deg, #a16207, #facc15); }
    .highlight-safety { background: linear-gradient(135deg, #b91c1c, #fb7185); }
    .highlight-general { background: linear-gradient(135deg, #475569, #94a3b8); }
    .feed-card {
      padding: 24px;
      position: relative;
      overflow: hidden;
    }
    .feed-card::before {
      content: "";
      position: absolute;
      inset: 0 auto 0 0;
      width: 6px;
      border-radius: 999px;
      background: linear-gradient(180deg, #14532d, #22c55e);
      opacity: 0.95;
    }
    .theme-general::before { background: linear-gradient(180deg, #64748b, #94a3b8); }
    .theme-animal::before { background: linear-gradient(180deg, #ca8a04, #eab308); }
    .theme-water::before { background: linear-gradient(180deg, #0284c7, #38bdf8); }
    .theme-road::before { background: linear-gradient(180deg, #475569, #94a3b8); }
    .theme-electricity::before { background: linear-gradient(180deg, #d97706, #facc15); }
    .theme-sanitation::before { background: linear-gradient(180deg, #166534, #86efac); }
    .theme-safety::before { background: linear-gradient(180deg, #b91c1c, #fb7185); }
    .theme-medical::before { background: linear-gradient(180deg, #be185d, #f472b6); }
    .card-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 14px;
      margin-bottom: 16px;
      flex-wrap: wrap;
    }
    .card-tags {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 10px;
    }
    .category-badge,
    .status-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 8px 12px;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .category-badge {
      background: rgba(217, 249, 157, 0.65);
      color: var(--brand-dark);
    }
    .status-badge.pending { background: rgba(239, 68, 68, 0.12); color: #b91c1c; }
    .status-badge.in_progress { background: rgba(249, 115, 22, 0.14); color: #c2410c; }
    .status-badge.solved { background: rgba(22, 163, 74, 0.12); color: #15803d; }
    .sos-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 999px;
      background: rgba(185, 28, 28, 0.12);
      color: #b91c1c;
      font-size: 0.78rem;
      font-weight: 800;
      letter-spacing: 0.02em;
      text-transform: uppercase;
    }
    .feed-card h3 {
      margin: 0 0 10px;
      font-size: clamp(1.2rem, 2vw, 1.45rem);
      line-height: 1.2;
    }
    .card-meta {
      font-size: 0.93rem;
      line-height: 1.7;
    }
    .card-snippet {
      margin: 0 0 18px;
      line-height: 1.8;
      font-size: 0.98rem;
    }
    .card-image {
      width: 100%;
      max-height: 320px;
      object-fit: cover;
      border-radius: 24px;
      border: 1px solid rgba(15, 118, 110, 0.12);
      margin-bottom: 18px;
    }
    .card-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }
    .card-actions-left {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    .card-bottom {
      margin-top: 18px;
      padding-top: 18px;
      border-top: 1px solid var(--line);
      display: grid;
      gap: 14px;
    }
    .urgency-strip {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      flex-wrap: wrap;
    }
    .urgency-metrics {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      color: var(--muted);
      font-size: 0.9rem;
    }
    .urgency-score {
      padding: 8px 12px;
      border-radius: 999px;
      background: rgba(249, 115, 22, 0.12);
      color: #c2410c;
      font-weight: 800;
    }
    .vote-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    .vote-actions form { margin: 0; }
    .vote-button {
      border: 0;
      cursor: pointer;
      padding: 10px 14px;
      border-radius: 999px;
      font: inherit;
      font-weight: 700;
      background: rgba(255, 255, 255, 0.9);
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
      color: var(--brand-dark);
      border-color: rgba(20, 83, 45, 0.18);
    }
    .leaderboard-list {
      display: grid;
      gap: 12px;
      margin-top: 16px;
    }
    .leaderboard-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 14px 16px;
      border-radius: 18px;
      background: var(--surface-strong);
      border: 1px solid var(--line);
    }
    .leaderboard-rank {
      width: 34px;
      height: 34px;
      border-radius: 12px;
      display: grid;
      place-items: center;
      font-weight: 800;
      background: rgba(20, 83, 45, 0.1);
      color: var(--brand-dark);
    }
    .leaderboard-meta {
      flex: 1;
      min-width: 0;
    }
    .leaderboard-meta strong {
      display: block;
      margin-bottom: 4px;
      font-size: 0.96rem;
    }
    .leaderboard-meta span {
      color: var(--muted);
      font-size: 0.86rem;
    }
    .leaderboard-points {
      font-weight: 800;
      color: var(--brand-dark);
      white-space: nowrap;
    }
    .action-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 10px 14px;
      border-radius: 999px;
      font-weight: 700;
    }
    .action-primary {
      background: linear-gradient(135deg, #14532d, #22c55e);
      color: #fff;
    }
    .action-secondary {
      background: rgba(217, 249, 157, 0.52);
      color: var(--brand-dark);
    }
    .ai-note {
      font-size: 0.84rem;
      color: var(--muted);
      font-weight: 700;
    }
    .empty-state {
      padding: 34px 26px;
      text-align: center;
    }
    .empty-state h3 {
      margin: 0 0 10px;
      font-size: 1.35rem;
    }
    .empty-state p {
      margin: 0 auto;
      max-width: 520px;
      line-height: 1.8;
    }
    @media (max-width: 960px) {
      .hero,
      .filters-row,
      .select-row,
      .highlights-grid {
        grid-template-columns: 1fr;
      }
    }
    @media (max-width: 640px) {
      .container {
        width: min(calc(100% - 20px), var(--max));
      }
      .nav-inner,
      .hero-panel,
      .stats-panel,
      .filters-panel,
      .feed-card,
      .empty-state {
        border-radius: 24px;
      }
      .hero-panel,
      .feed-card {
        padding: 22px;
      }
      .hero-copy h1 {
        font-size: 2.35rem;
      }
    }
  </style>
</head>
<body>
  <div class="page-shell">
    <div class="container">
      <header class="navbar">
        <div class="nav-inner">
          <div class="brand">
            <div class="brand-mark">NH</div>
            <div class="brand-copy">
              <strong>Neighborhood Help</strong>
              <span>Smart local problem feed</span>
            </div>
          </div>
          <div class="nav-actions">
            <a href="new_post.php" class="solid-link">New Post</a>
            <a href="map_posts.php" class="pill-link">Map View</a>
            <a href="<?php echo $user ? 'messages.php' : 'login.php'; ?>" class="solid-link chat-link">
              Open Chats<?php if ($user && $unreadMessageCount > 0): ?> (<?php echo e((string) $unreadMessageCount); ?>)<?php endif; ?>
            </a>
            <?php if ($user): ?>
              <?php include __DIR__ . '/inc/notification_dropdown.php'; ?>
              <span class="user-chip">Hi, <?php echo e($user['name']); ?> • <?php echo e($user['area']); ?></span>
              <a href="logout.php" class="pill-link">Logout</a>
            <?php else: ?>
              <a href="login.php" class="pill-link">Login</a>
              <a href="register.php" class="solid-link">Register</a>
            <?php endif; ?>
          </div>
        </div>
      </header>

      <section class="announcement-bar">
        <div>
          <strong>Community Feed upgraded with smart issue detection</strong>
          <span>Ab system title aur description se automatic category identify karta hai, SOS alerts highlight hote hain, aur important issues votes ke basis par upar aate hain.</span>
        </div>
        <div class="announcement-pill">AI Smart Feed</div>
      </section>

      <?php if ($deleteSuccess): ?>
        <div class="success-banner">Post successfully deleted.</div>
      <?php endif; ?>

      <section class="sos-command-card">
        <div class="sos-command-top">
          <div>
            <strong>Emergency SOS Command</strong>
            <p>Real emergency hai to direct SOS mode me jao. Alert ko stronger visibility milegi aur urgent notification broadcast hoga.</p>
          </div>
          <div class="sos-command-chip"><?php echo e((string) $sosPosts); ?> SOS</div>
        </div>
        <div class="sos-command-actions">
          <a href="new_post.php?mode=sos" class="solid-link sos-link">Raise Emergency SOS</a>
          <a href="community.php?area=<?php echo e($filter_area); ?>&category=<?php echo e($filter_category); ?>&sos=1#feed" class="ghost-link">View SOS Only</a>
          <?php if ($topSosPost): ?>
            <a href="view_post.php?id=<?php echo (int) $topSosPost['id']; ?>" class="ghost-link">Open Latest SOS</a>
          <?php endif; ?>
        </div>
        <?php if ($topSosPost): ?>
          <div class="sos-command-meta">
            Latest SOS: <?php echo e($topSosPost['title']); ?> from <?php echo e($topSosPost['area']); ?> by <?php echo e($topSosPost['name']); ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="hero">
        <div class="hero-panel">
          <span class="eyebrow">Community Feed with AI detection</span>
          <div class="hero-copy">
            <h1>Local issues, clearer signals, faster action.</h1>
            <p>Har post ko title aur description ke basis par smart category milti hai, jisse community ko samajhna easy ho jata hai ki issue water ka hai, animal ka hai, safety ka hai ya general support ka.</p>
          </div>
          <div class="hero-actions">
            <a href="new_post.php" class="solid-link">Report a New Problem</a>
            <a href="new_post.php?mode=sos" class="solid-link sos-link">Emergency SOS</a>
            <a href="<?php echo $user ? 'messages.php' : 'login.php'; ?>" class="solid-link chat-link">Go to Chat Inbox</a>
            <a href="#feed" class="ghost-link">Browse Feed</a>
          </div>
        </div>

        <aside class="stats-panel">
          <h2>Feed Snapshot</h2>
          <div class="stat-grid">
            <div class="stat-card">
              <strong><?php echo e((string) $totalPosts); ?></strong>
              <span>Total community reports</span>
            </div>
            <div class="stat-card">
              <strong><?php echo e((string) $urgentPosts); ?></strong>
              <span>Pending issues needing attention</span>
            </div>
            <div class="stat-card">
              <strong><?php echo e((string) $resolvedPosts); ?></strong>
              <span>Solved posts recorded so far</span>
            </div>
            <div class="stat-card">
              <strong><?php echo e((string) $visiblePosts); ?></strong>
              <span>Posts visible with current filters</span>
            </div>
            <div class="stat-card">
              <strong><?php echo e((string) $sosPosts); ?></strong>
              <span>Emergency SOS alerts now active</span>
            </div>
            <div class="stat-card">
              <strong><?php echo e((string) $currentUserPoints); ?></strong>
              <span>Your helper points from volunteering</span>
            </div>
          </div>

          <h2 style="margin-top:22px;">Helper Leaderboard</h2>
          <div class="leaderboard-list">
            <?php if (empty($helperLeaderboard)): ?>
              <div class="leaderboard-item">
                <div class="leaderboard-meta">
                  <strong>No helpers ranked yet</strong>
                  <span>First volunteer will start the board.</span>
                </div>
              </div>
            <?php else: ?>
              <?php foreach ($helperLeaderboard as $index => $leader): ?>
                <div class="leaderboard-item">
                  <div class="leaderboard-rank">#<?php echo (int) $index + 1; ?></div>
                  <div class="leaderboard-meta">
                    <strong><?php echo e($leader['name']); ?></strong>
                    <span><?php echo e($leader['area']); ?></span>
                  </div>
                  <div class="leaderboard-points"><?php echo e((string) $leader['total_points']); ?> pts</div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </aside>
      </section>

      <section class="filters-panel">
        <form method="get" class="filters-row">
          <div class="filter-intro">
            <h2>Explore by area and issue type</h2>
            <p>Filter the feed to focus on nearby reports or narrow the list to a specific issue category detected by the system.</p>
          </div>
          <div class="filter-controls">
            <div class="select-row">
              <label class="select-field">
                <span>Area</span>
                <select name="area" onchange="this.form.submit()">
                  <option value="all" <?php if ($filter_area === 'all') echo 'selected'; ?>>All areas</option>
                  <option value="Central" <?php if ($filter_area === 'Central') echo 'selected'; ?>>Central</option>
                  <option value="North" <?php if ($filter_area === 'North') echo 'selected'; ?>>North</option>
                  <option value="South" <?php if ($filter_area === 'South') echo 'selected'; ?>>South</option>
                  <option value="East" <?php if ($filter_area === 'East') echo 'selected'; ?>>East</option>
                  <option value="West" <?php if ($filter_area === 'West') echo 'selected'; ?>>West</option>
                </select>
              </label>
              <label class="select-field">
                <span>AI Category</span>
                <select name="category" onchange="this.form.submit()">
                  <option value="all" <?php if ($filter_category === 'all') echo 'selected'; ?>>All categories</option>
                  <?php foreach ($categories as $category): ?>
                    <option value="<?php echo e($category); ?>" <?php if ($filter_category === $category) echo 'selected'; ?>>
                      <?php echo e($category); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>

            <div class="chip-row">
              <button class="filter-chip <?php if ($filter_category === 'all') echo 'active'; ?>" type="submit" name="category" value="all">All</button>
              <button class="filter-chip <?php if ($filter_sos === '1') echo 'active'; ?>" type="submit" name="sos" value="1">SOS Only</button>
              <button class="filter-chip <?php if ($filter_sos === '0') echo 'active'; ?>" type="submit" name="sos" value="0">Hide SOS Filter</button>
              <?php foreach ($categories as $category): ?>
                <button class="filter-chip <?php if ($filter_category === $category) echo 'active'; ?>" type="submit" name="category" value="<?php echo e($category); ?>">
                  <?php echo e($category); ?>
                </button>
              <?php endforeach; ?>
              <input type="hidden" name="area" value="<?php echo e($filter_area); ?>">
            </div>
          </div>
        </form>
      </section>

      <section class="highlights-grid">
        <div class="highlight-card highlight-water">
          <strong>Water Alerts</strong>
          <span>Leakage, dirty water, supply issues aur overflow problems ko fast spot karo.</span>
        </div>
        <div class="highlight-card highlight-animal">
          <strong>Animal Reports</strong>
          <span>Stray, injured ya risky animal-related issues alag se identify hote hain.</span>
        </div>
        <div class="highlight-card highlight-safety">
          <strong>Safety Focus</strong>
          <span>Emergency, fire, theft aur unsafe area reports ko clear priority milti hai.</span>
        </div>
        <div class="highlight-card highlight-general">
          <strong>General Support</strong>
          <span>Baaki neighborhood concerns bhi neatly grouped aur easy to browse hain.</span>
        </div>
      </section>

      <main id="feed" class="feed-grid">
        <?php if (empty($processedPosts)): ?>
          <section class="empty-state">
            <h3>No posts found for this filter</h3>
            <p>Area ya category change karke dekhiye, ya phir naya issue post karke community ko alert kijiye.</p>
          </section>
        <?php endif; ?>

        <?php foreach ($processedPosts as $post): ?>
          <?php
            $category = $post['resolved_category'];
            $themeClass = $categoryThemes[$category] ?? 'theme-general';
          ?>
          <article class="feed-card <?php echo e($themeClass); ?>">
            <div class="card-top">
              <div>
                <div class="card-tags">
                  <span class="category-badge"><?php echo e($category); ?></span>
                  <span class="status-badge <?php echo e($post['status']); ?>"><?php echo e(str_replace('_', ' ', ucfirst($post['status']))); ?></span>
                  <?php if (!empty($post['is_sos'])): ?>
                    <span class="sos-badge">SOS Alert</span>
                  <?php endif; ?>
                </div>
                <h3><?php echo e($post['title']); ?></h3>
                <div class="card-meta">
                  Posted by <?php echo e($post['name']); ?> • <?php echo e($post['area']); ?> • <?php echo e($post['created_at']); ?>
                </div>
              </div>
              <div class="ai-note">AI-tagged issue</div>
            </div>

            <p class="card-snippet"><?php echo nl2br(e($post['description'])); ?></p>

            <?php if (!empty($post['image'])): ?>
              <img src="assets/uploads/<?php echo e($post['image']); ?>" alt="Post image" class="card-image">
            <?php endif; ?>

            <div class="card-actions">
              <div class="card-actions-left">
                <a href="view_post.php?id=<?php echo (int) $post['id']; ?>" class="action-link action-primary">View Details</a>
                <?php if ($user && (int) $user['id'] !== (int) $post['user_id'] && !empty($post['chat_link'])): ?>
                  <a href="<?php echo e($post['chat_link']); ?>" class="action-link action-secondary">Message Owner</a>
                <?php endif; ?>
                <?php if ($user && (int) $user['id'] !== (int) $post['user_id']): ?>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="offer_help_post_id" value="<?php echo (int) $post['id']; ?>">
                    <button type="submit" class="action-link action-secondary" style="border:0; cursor:pointer;">
                      <?php echo $post['current_user_helping'] ? 'Helping Now' : 'I Can Help'; ?>
                    </button>
                  </form>
                <?php endif; ?>
                <?php if ($user && ($user['id'] == $post['user_id'] || $user['role'] === 'admin')): ?>
                  <a href="view_post.php?id=<?php echo (int) $post['id']; ?>" class="action-link action-secondary">Open Delete Option</a>
                <?php endif; ?>
                <?php if ($user && $user['role'] === 'admin'): ?>
                  <a href="admin.php?id=<?php echo (int) $post['id']; ?>" class="action-link action-secondary">Manage</a>
                <?php endif; ?>
              </div>
              <div class="meta-text">
                <?php echo e($post['location'] ?: 'Location not provided'); ?><br>
                <?php echo e((string) $post['helper_count']); ?> helper<?php echo (int) $post['helper_count'] === 1 ? '' : 's'; ?> ready
              </div>
            </div>

            <div class="card-bottom">
              <div class="urgency-strip">
                <div class="urgency-metrics">
                  <span class="urgency-score">Priority <?php echo e((string) $post['urgency_score']); ?></span>
                  <span><?php echo e((string) $post['urgent_yes']); ?> marked urgent</span>
                  <span><?php echo e((string) $post['not_urgent']); ?> said not urgent</span>
                </div>
                <?php if ($user && (int) $user['id'] !== (int) $post['user_id']): ?>
                  <div class="vote-actions">
                    <form method="post">
                      <input type="hidden" name="urgency_post_id" value="<?php echo (int) $post['id']; ?>">
                      <input type="hidden" name="urgency_vote" value="1">
                      <button type="submit" class="vote-button <?php echo (int) $post['current_user_urgency_vote'] === 1 ? 'active-urgent' : ''; ?>">This is urgent</button>
                    </form>
                    <form method="post">
                      <input type="hidden" name="urgency_post_id" value="<?php echo (int) $post['id']; ?>">
                      <input type="hidden" name="urgency_vote" value="0">
                      <button type="submit" class="vote-button <?php echo $post['current_user_urgency_vote'] === 0 ? 'active-calm' : ''; ?>">Not urgent</button>
                    </form>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </main>
    </div>
    <a href="new_post.php?mode=sos" class="sos-pulse-button">SOS Emergency</a>
  </div>
  <script>
    (function () {
      const menus = document.querySelectorAll('.notif-menu');
      menus.forEach((menu) => {
        const trigger = menu.querySelector('[data-notif-toggle]');
        const dropdown = menu.querySelector('[data-notif-menu]');
        if (!trigger || !dropdown) return;

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
    })();
  </script>
  <?php include __DIR__ . '/inc/chatbot_widget.php'; ?>
</body>
</html>
