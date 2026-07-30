<?php
require 'inc/config.php';
require 'inc/auth.php';

require_login();

$user = current_user();
$errors = [];
$categoryColumnAvailable = ensure_posts_category_column();
$sosColumnAvailable = ensure_posts_sos_column();
$unreadMessageCount = $user ? get_unread_message_count($user['id']) : 0;
$sosMode = isset($_GET['mode']) && $_GET['mode'] === 'sos';
$selectedEmergencyType = trim((string) ($_POST['emergency_type'] ?? ($_GET['emergency_type'] ?? '')));
$emergencyTypeOptions = [
    'medical' => ['label' => 'Medical Emergency', 'title' => 'Urgent medical help needed', 'description' => 'A person needs immediate medical assistance here. Please respond quickly with first-aid support, ambulance coordination, or urgent transport help.'],
    'fire' => ['label' => 'Fire / Smoke', 'title' => 'Fire or smoke emergency reported', 'description' => 'Fire, smoke, or a burning hazard has been reported at this location. Please alert nearby people and contact emergency services immediately.'],
    'safety' => ['label' => 'Safety Threat', 'title' => 'Immediate safety risk in the area', 'description' => 'There is an active safety threat here. Nearby residents should stay alert, avoid the area if needed, and coordinate urgent help.'],
    'electricity' => ['label' => 'Electric Hazard', 'title' => 'Dangerous electrical issue reported', 'description' => 'Live wire, sparking line, transformer risk, or another dangerous electrical issue is creating an emergency at this location.'],
    'water' => ['label' => 'Flood / Water Burst', 'title' => 'Serious water emergency reported', 'description' => 'Major water leakage, flooding, or burst pipeline is causing urgent risk and needs immediate local response.'],
];

if ($sosMode && $selectedEmergencyType !== '' && isset($emergencyTypeOptions[$selectedEmergencyType])) {
    if (empty($_POST['title'])) {
        $_POST['title'] = $emergencyTypeOptions[$selectedEmergencyType]['title'];
    }
    if (empty($_POST['description'])) {
        $_POST['description'] = $emergencyTypeOptions[$selectedEmergencyType]['description'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $area = trim($_POST['area'] ?? $user['area']);
    $location = trim($_POST['location'] ?? '');
    $lat = trim($_POST['lat'] ?? '');
    $lng = trim($_POST['lng'] ?? '');
    $isSos = (!empty($_POST['is_sos']) || $sosMode) ? 1 : 0;

    if (!$title || !$area) {
        $errors[] = 'Title and area required.';
    }

    if (!$lat || !$lng) {
        $errors[] = 'Please pick location on map.';
    }

    if ($isSos && strlen($description) < 20) {
        $errors[] = 'For SOS alerts, please add a little more detail so helpers can respond correctly.';
    }

    $imageName = null;
    if (!empty($_FILES['image']['name'])) {
        $allowed = ['png', 'jpg', 'jpeg', 'gif'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $errors[] = 'Invalid image type.';
        } else {
            $imageName = uniqid() . '.' . $ext;
            $target = __DIR__ . '/assets/uploads/' . $imageName;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                $errors[] = 'Failed to upload image.';
            }
        }
    }

    if (empty($errors)) {
        $detectedCategory = detect_post_category($title, $description);
        if ($categoryColumnAvailable && $sosColumnAvailable) {
            $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, description, category, is_sos, image, area, location, lat, lng) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $title, $description, $detectedCategory, $isSos, $imageName, $area, $location, $lat, $lng]);
        } elseif ($categoryColumnAvailable) {
            $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, description, category, image, area, location, lat, lng) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $title, $description, $detectedCategory, $imageName, $area, $location, $lat, $lng]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, description, image, area, location, lat, lng) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $title, $description, $imageName, $area, $location, $lat, $lng]);
        }
        $newPostId = (int) $pdo->lastInsertId();
        if ($isSos) {
            broadcast_notification($user['id'], $newPostId, 'sos_alert', 'SOS ALERT: ' . $user['name'] . ' reported an emergency - ' . $title);
        } else {
            broadcast_notification($user['id'], $newPostId, 'new_post', $user['name'] . ' created a new post: ' . $title);
        }
        notify_user(
            (int) $user['id'],
            (int) $user['id'],
            $newPostId,
            $isSos ? 'sos_created' : 'post_created',
            $isSos
                ? 'Your SOS alert is now live and highlighted for urgent response: ' . $title
                : 'Your post has been published successfully: ' . $title
        );
        header('Location: community.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>New Post - Neighborhood Help</title>
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
      --max: 1180px;
      --sos-red: #b91c1c;
      --sos-red-deep: #7f1d1d;
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
      top: 100px;
      left: -90px;
    }
    .page-shell::after {
      width: 240px;
      height: 240px;
      background: rgba(249, 115, 22, 0.16);
      bottom: 70px;
      right: -80px;
    }
    .container {
      width: min(calc(100% - 32px), var(--max));
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }
    .navbar { padding: 22px 0 18px; }
    .nav-content {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      padding: 14px 18px;
      background: rgba(255, 255, 255, 0.62);
      border: 1px solid rgba(255, 255, 255, 0.72);
      backdrop-filter: blur(14px);
      border-radius: 999px;
      box-shadow: 0 10px 30px rgba(20, 83, 45, 0.08);
    }
    .brand { display: flex; align-items: center; gap: 12px; }
    .brand-badge {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      background: linear-gradient(135deg, #14532d, #22c55e);
      color: #fff;
      font-weight: 800;
    }
    .brand-copy strong { display: block; line-height: 1.1; }
    .brand-copy span,
    .user-chip,
    .intro p,
    .field-hint,
    .helper-card p { color: var(--muted); }
    .nav-actions {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }
    .pill-link,
    .cta-button,
    .submit-button {
      border: 0;
      cursor: pointer;
      padding: 11px 16px;
      border-radius: 999px;
      font-weight: 700;
      font-family: inherit;
    }
    .pill-link { background: rgba(20, 83, 45, 0.08); color: var(--brand); }
    .cta-button,
    .submit-button { background: var(--brand); color: #fff; }
    .user-chip {
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.74);
      border: 1px solid rgba(20, 83, 45, 0.08);
      font-size: 0.92rem;
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
      background: rgba(20, 83, 45, 0.08);
      color: var(--brand);
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
    .content-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(280px, 0.74fr);
      gap: 24px;
      padding-bottom: 34px;
      align-items: start;
    }
    .form-panel,
    .helper-card {
      background: var(--surface);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.7);
      box-shadow: var(--shadow);
      border-radius: var(--radius-xl);
    }
    .form-panel { padding: 28px; }
    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(217, 249, 157, 0.56);
      color: var(--brand);
      font-size: 0.88rem;
      font-weight: 800;
    }
    .eyebrow.sos-mode {
      background: rgba(254, 226, 226, 0.9);
      color: var(--sos-red-deep);
      box-shadow: 0 10px 24px rgba(185, 28, 28, 0.12);
    }
    .intro { margin: 18px 0 24px; }
    .intro h1 {
      margin: 0 0 10px;
      font-size: clamp(2rem, 4vw, 3.2rem);
      line-height: 1;
      letter-spacing: -0.04em;
    }
    .intro p { margin: 0; line-height: 1.7; }
    .error-box {
      margin-bottom: 20px;
      padding: 14px 16px;
      border-radius: 16px;
      border: 1px solid rgba(220, 38, 38, 0.14);
      background: rgba(254, 226, 226, 0.86);
      color: #b91c1c;
      line-height: 1.7;
    }
    .form-grid { display: grid; gap: 18px; }
    label { display: grid; gap: 8px; font-weight: 700; }
    input, textarea, select {
      width: 100%;
      border: 1px solid rgba(20, 83, 45, 0.12);
      background: rgba(255, 255, 255, 0.9);
      color: var(--ink);
      border-radius: 16px;
      padding: 14px 15px;
      font: inherit;
      outline: none;
    }
    textarea { min-height: 140px; resize: vertical; }
    .field-hint { margin: 0; font-size: 0.9rem; font-weight: 500; line-height: 1.5; }
    .map-picker {
      position: relative;
      overflow: hidden;
      border-radius: 24px;
      border: 1px solid rgba(20, 83, 45, 0.12);
      background: #dbe7d7;
    }
    #map { height: 330px; width: 100%; }
    .picker-tools {
      position: absolute;
      z-index: 5;
      top: 12px;
      left: 12px;
      right: 12px;
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 8px;
      pointer-events: none;
    }
    .picker-search,
    .picker-actions {
      pointer-events: auto;
      display: flex;
      gap: 8px;
      padding: 8px;
      border-radius: 16px;
      background: rgba(255, 255, 255, 0.96);
      border: 1px solid rgba(20, 83, 45, 0.12);
      box-shadow: 0 14px 30px rgba(31, 41, 55, 0.14);
    }
    .picker-search input {
      min-width: 0;
      border: 0;
      border-radius: 10px;
      background: transparent;
      padding: 8px 10px;
    }
    .picker-search button,
    .picker-actions button {
      width: auto;
      border: 0;
      cursor: pointer;
      border-radius: 10px;
      padding: 8px 11px;
      background: rgba(20, 83, 45, 0.08);
      color: var(--brand);
      font: inherit;
      font-weight: 800;
      white-space: nowrap;
    }
    .picker-search button {
      background: var(--brand);
      color: #fff;
    }
    .picker-actions button.active {
      background: var(--brand);
      color: #fff;
    }
    @media (max-width: 760px) {
      .picker-tools {
        grid-template-columns: 1fr;
      }
      .picker-actions {
        flex-wrap: wrap;
      }
    }
    .location-preview {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-top: 10px;
      padding: 8px 12px;
      border-radius: 999px;
      background: rgba(20, 83, 45, 0.08);
      color: var(--brand);
      font-size: 0.84rem;
      font-weight: 700;
    }
    .helper-stack { display: grid; gap: 18px; }
    .helper-card { padding: 22px; }
    .helper-card h3 { margin: 0 0 10px; font-size: 1.05rem; }
    .helper-card p { margin: 0; line-height: 1.7; font-size: 0.94rem; }
    .helper-list { margin: 14px 0 0; padding-left: 18px; color: var(--muted); line-height: 1.7; }
    .submit-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      flex-wrap: wrap;
      margin-top: 6px;
    }
    .sos-hero-card {
      display: grid;
      gap: 14px;
      margin-bottom: 22px;
      padding: 20px 22px;
      border-radius: 28px;
      background:
        radial-gradient(circle at top right, rgba(252, 165, 165, 0.2), transparent 28%),
        linear-gradient(135deg, rgba(127, 29, 29, 0.96), rgba(185, 28, 28, 0.92));
      color: #fff;
      box-shadow: 0 22px 40px rgba(127, 29, 29, 0.24);
    }
    .sos-hero-card strong {
      font-size: 1.18rem;
      letter-spacing: 0.02em;
    }
    .sos-hero-card p {
      margin: 0;
      color: rgba(255, 255, 255, 0.88);
      line-height: 1.75;
    }
    .quick-sos-chip-row {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }
    .quick-sos-chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.14);
      border: 1px solid rgba(255, 255, 255, 0.16);
      color: #fff;
      font-size: 0.84rem;
      font-weight: 800;
      letter-spacing: 0.02em;
      text-transform: uppercase;
    }
    .sos-toggle {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      padding: 18px 20px;
      border-radius: 24px;
      background: linear-gradient(135deg, rgba(185, 28, 28, 0.08), rgba(249, 115, 22, 0.12));
      border: 1px solid rgba(185, 28, 28, 0.14);
    }
    .sos-toggle input {
      width: 20px;
      height: 20px;
      margin-top: 2px;
      accent-color: #b91c1c;
    }
    .sos-copy strong {
      display: block;
      margin-bottom: 6px;
      color: #991b1b;
    }
    .sos-copy p {
      margin: 0;
      color: var(--muted);
      line-height: 1.7;
    }
    .sos-type-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }
    .sos-type-option input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }
    .sos-type-option span {
      display: block;
      padding: 14px 16px;
      border-radius: 20px;
      background: rgba(255, 255, 255, 0.92);
      border: 1px solid rgba(185, 28, 28, 0.16);
      color: var(--sos-red-deep);
      font-weight: 800;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .sos-type-option span small {
      display: block;
      margin-top: 5px;
      color: #7c2d12;
      font-size: 0.8rem;
      font-weight: 600;
    }
    .sos-type-option input:checked + span {
      background: linear-gradient(135deg, #7f1d1d, #dc2626);
      color: #fff;
      border-color: transparent;
      box-shadow: 0 16px 30px rgba(185, 28, 28, 0.24);
      transform: translateY(-1px);
    }
    .sos-type-option input:checked + span small {
      color: rgba(255, 255, 255, 0.82);
    }
    .submit-row span { color: var(--muted); font-size: 0.92rem; }
    .ai-detection-card {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      padding: 14px 16px;
      border-radius: 20px;
      background: rgba(20, 83, 45, 0.08);
      border: 1px solid rgba(20, 83, 45, 0.12);
    }
    .ai-detection-card strong {
      display: block;
      margin-bottom: 4px;
      color: var(--brand);
    }
    .ai-detection-card p {
      margin: 0;
      color: var(--muted);
      font-size: 0.92rem;
      line-height: 1.6;
    }
    .detected-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 120px;
      padding: 10px 14px;
      border-radius: 999px;
      background: linear-gradient(135deg, #14532d, #22c55e);
      color: #fff;
      font-weight: 800;
      letter-spacing: 0.02em;
    }
    .submit-button.sos-submit {
      background: linear-gradient(135deg, #7f1d1d, #dc2626);
      box-shadow: 0 18px 30px rgba(185, 28, 28, 0.24);
    }
    @media (max-width: 960px) {
      .nav-content, .content-grid { display: block; }
      .nav-actions, .helper-stack { margin-top: 14px; }
      .sos-type-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
      .container { width: min(calc(100% - 20px), var(--max)); }
      .nav-content, .form-panel, .helper-card { border-radius: 24px; }
      .form-panel { padding: 22px; }
    }
  </style>
</head>
<body>
  <div class="page-shell">
    <div class="container">
      <header class="navbar">
        <div class="nav-content">
          <div class="brand">
            <div class="brand-badge">NH</div>
            <div class="brand-copy">
              <strong>Neighborhood Help</strong>
              <span>Publish a location-based request</span>
            </div>
          </div>
          <div class="nav-actions">
            <a href="community.php" class="pill-link">Back to Feed</a>
            <?php if ($user): ?>
              <a href="messages.php" class="pill-link">Messages<?php if ($unreadMessageCount > 0): ?> (<?php echo e((string) $unreadMessageCount); ?>)<?php endif; ?></a>
              <?php include __DIR__ . '/inc/notification_dropdown.php'; ?>
              <span class="user-chip">Hi, <?php echo e($user['name']); ?></span>
              <a href="logout.php" class="cta-button">Logout</a>
            <?php endif; ?>
          </div>
        </div>
      </header>

      <main class="content-grid">
        <section class="form-panel">
          <span class="eyebrow <?php echo $sosMode ? 'sos-mode' : ''; ?>">
            <?php echo $sosMode ? 'Emergency SOS Mode' : 'Create a new problem post'; ?>
          </span>
          <div class="intro">
            <h1><?php echo $sosMode ? 'Raise an emergency alert in seconds.' : 'Pin the issue so nearby people can act faster.'; ?></h1>
            <p><?php echo $sosMode
              ? 'SOS mode makes your alert more visible in the feed, sends a stronger notification, and helps nearby residents understand the emergency fast.'
              : 'Add a short title, the important details, and a precise location to help neighbors understand what is happening right away.'; ?></p>
          </div>

          <?php if ($sosMode): ?>
            <div class="sos-hero-card">
              <strong>Emergency response mode is active</strong>
              <p>Use this only for real urgent situations. Your alert will be highlighted at the top of the feed with an SOS badge and urgent community notification.</p>
              <div class="quick-sos-chip-row">
                <span class="quick-sos-chip">High Visibility</span>
                <span class="quick-sos-chip">Urgent Alert</span>
                <span class="quick-sos-chip">Fast Response</span>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($errors): ?>
            <div class="error-box">
              <?php foreach ($errors as $error): ?>
                <div><?php echo e($error); ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="post" enctype="multipart/form-data" class="form-grid">
            <?php if ($sosMode): ?>
              <div>
                <label><span>Emergency Type</span></label>
                <div class="sos-type-grid">
                  <?php foreach ($emergencyTypeOptions as $typeKey => $typeMeta): ?>
                    <label class="sos-type-option">
                      <input type="radio" name="emergency_type" value="<?php echo e($typeKey); ?>" <?php echo $selectedEmergencyType === $typeKey ? 'checked' : ''; ?>>
                      <span>
                        <?php echo e($typeMeta['label']); ?>
                        <small><?php echo e($typeMeta['title']); ?></small>
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <label>
              <span>Title</span>
              <input name="title" id="title" value="<?php echo e($_POST['title'] ?? ''); ?>" placeholder="<?php echo $sosMode ? 'Example: Fire near Block C transformer' : ''; ?>" required>
            </label>

            <label>
              <span>Description</span>
              <textarea name="description" id="description" rows="5" placeholder="<?php echo $sosMode ? 'Explain what is happening right now, what risk exists, and what urgent help is needed.' : ''; ?>"><?php echo e($_POST['description'] ?? ''); ?></textarea>
            </label>

            <div class="ai-detection-card">
              <div>
                <strong>AI Category Detection</strong>
                <p>Title aur description ke basis par system auto-detect karega ki issue kis type ka hai.</p>
              </div>
              <span class="detected-badge" id="detectedCategory">General</span>
            </div>

            <label class="sos-toggle">
              <input type="checkbox" name="is_sos" value="1" <?php if (!empty($_POST['is_sos']) || $sosMode) echo 'checked'; ?>>
              <div class="sos-copy">
                <strong>Emergency SOS Alert</strong>
                <p>Use this option when the issue involves immediate danger, a medical emergency, or a high-risk safety situation. SOS posts will be highlighted at the top of the feed.</p>
              </div>
            </label>

            <label>
              <span>Area</span>
              <select name="area" required>
                <?php $selectedArea = $_POST['area'] ?? $user['area']; ?>
                <option value="<?php echo e($user['area']); ?>" <?php if ($selectedArea === $user['area']) echo 'selected'; ?>>
                  <?php echo e($user['area']); ?> (Your area)
                </option>
                <?php foreach (['Central', 'North', 'South', 'East', 'West'] as $areaOption): ?>
                  <?php if ($areaOption === $user['area']) continue; ?>
                  <option value="<?php echo e($areaOption); ?>" <?php if ($selectedArea === $areaOption) echo 'selected'; ?>>
                    <?php echo e($areaOption); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>

            <label>
              <span>Pick location on map</span>
              <p class="field-hint">Click once to drop a pin. We will auto-fill the nearest address using reverse geocoding.</p>
              <div class="map-picker">
                <div class="picker-tools">
                  <form class="picker-search" id="pickerSearchForm">
                    <input id="pickerSearchInput" type="search" placeholder="Search address or landmark">
                    <button type="submit">Search</button>
                  </form>
                  <div class="picker-actions">
                    <button type="button" id="pickerLocateButton">My Location</button>
                    <button type="button" id="pickerSatelliteButton">Satellite</button>
                  </div>
                </div>
                <div id="map"></div>
              </div>
              <div class="location-preview">Selected coordinates saved with your post</div>
              <input type="text" id="location_text" name="location" placeholder="Location will appear here" value="<?php echo e($_POST['location'] ?? ''); ?>" readonly required>
              <input type="hidden" name="lat" id="lat" value="<?php echo e($_POST['lat'] ?? ''); ?>">
              <input type="hidden" name="lng" id="lng" value="<?php echo e($_POST['lng'] ?? ''); ?>">
            </label>

            <label>
              <span>Image</span>
              <input type="file" name="image" accept="image/*">
            </label>

            <div class="submit-row">
              <span><?php echo $sosMode ? 'Accurate location matters most in emergency mode. Drop the pin on the exact risk area.' : 'Your pin helps neighbors quickly understand context and proximity.'; ?></span>
              <button class="submit-button <?php echo $sosMode ? 'sos-submit' : ''; ?>" type="submit"><?php echo $sosMode ? 'Send SOS Alert' : 'Publish Post'; ?></button>
            </div>
          </form>
        </section>

        <aside class="helper-stack">
          <div class="helper-card">
            <h3><?php echo $sosMode ? 'What makes a strong SOS alert?' : 'What makes a strong post?'; ?></h3>
            <p><?php echo $sosMode
              ? 'Mention the danger clearly, share the immediate need, and pin the exact location so nearby helpers can react without confusion.'
              : 'Keep the title direct, explain what support is needed, and use the map pin to show the exact spot instead of only the broad area.'; ?></p>
            <ul class="helper-list">
              <?php if ($sosMode): ?>
                <li>State what emergency is happening right now.</li>
                <li>Mention injuries, smoke, flooding, electric risk, or access blockers if relevant.</li>
                <li>Add a photo only if it is safe to do so.</li>
              <?php else: ?>
                <li>Use a short, specific title.</li>
                <li>Mention urgency, access issues, or landmarks.</li>
                <li>Add a photo if it helps volunteers assess the situation.</li>
              <?php endif; ?>
            </ul>
          </div>

          <div class="helper-card">
            <h3>Why the location picker matters</h3>
            <p>Map-based posts are easier to prioritize, easier to route to nearby helpers, and much more useful inside the shared community map view.</p>
          </div>
        </aside>
      </main>
    </div>
  </div>

  <script>
    if (window.location.hostname === '127.0.0.1') {
      window.location.replace(window.location.href.replace('//127.0.0.1', '//localhost'));
    }

    function detectCategoryFromText(title, description) {
      const text = `${title} ${description}`.toLowerCase();
      const categories = {
        Animal: ['animal', 'dog', 'cat', 'cow', 'buffalo', 'monkey', 'pet', 'puppy', 'kitten', 'stray', 'snake', 'bird', 'janwar', 'kutta', 'billi', 'gaay', 'bandar', 'saap', 'pashu'],
        Water: ['water', 'leak', 'pipeline', 'pipe burst', 'drain overflow', 'sewage water', 'no water', 'water supply', 'tap water', 'flooding', 'overflow', 'dirty water', 'pani', 'paani', 'jal', 'nal', 'leakage', 'ganda pani'],
        Road: ['road', 'street', 'pothole', 'traffic', 'footpath', 'sidewalk', 'divider', 'accident', 'broken road', 'signal', 'parking issue', 'sadak', 'gadda', 'khadda', 'raasta', 'rasta'],
        Electricity: ['electricity', 'power cut', 'short circuit', 'transformer', 'wire', 'street light', 'voltage', 'blackout', 'sparking', 'electric pole', 'bijli', 'current', 'tar', 'pole', 'streetlight'],
        Sanitation: ['garbage', 'trash', 'waste', 'cleaning', 'sewer', 'drain', 'toilet', 'smell', 'sanitation', 'dump', 'mosquito', 'dirty', 'kachra', 'gandagi', 'safai', 'naali', 'nali', 'badbu', 'machhar'],
        Safety: ['theft', 'fight', 'unsafe', 'security', 'harassment', 'crime', 'suspicious', 'emergency', 'fire', 'smoke', 'danger', 'police', 'chori', 'jagda', 'suraksha', 'aag', 'dhua', 'khatra'],
        Medical: ['medical', 'ambulance', 'injury', 'injured', 'hospital', 'blood', 'doctor', 'emergency help', 'health', 'sick', 'chot', 'beemar', 'bimaar']
      };

      let bestCategory = 'General';
      let bestScore = 0;

      Object.entries(categories).forEach(([category, keywords]) => {
        let score = 0;
        keywords.forEach(keyword => {
          if (text.includes(keyword)) {
            score += 1;
          }
        });
        if (score > bestScore) {
          bestScore = score;
          bestCategory = category;
        }
      });

      return bestCategory;
    }

    const categoryBadge = document.getElementById('detectedCategory');
    const titleInput = document.getElementById('title');
    const descriptionInput = document.getElementById('description');
    const emergencyTypeInputs = document.querySelectorAll('input[name="emergency_type"]');
    const isSosCheckbox = document.querySelector('input[name="is_sos"]');
    const emergencyPresets = <?php echo json_encode($emergencyTypeOptions); ?>;

    function updateDetectedCategory() {
      categoryBadge.textContent = detectCategoryFromText(titleInput.value, descriptionInput.value);
    }

    emergencyTypeInputs.forEach((input) => {
      input.addEventListener('change', () => {
        if (!input.checked) return;
        const preset = emergencyPresets[input.value];
        if (!preset) return;
        titleInput.value = preset.title;
        descriptionInput.value = preset.description;
        if (isSosCheckbox) {
          isSosCheckbox.checked = true;
        }
        updateDetectedCategory();
      });
    });

    titleInput.addEventListener('input', updateDetectedCategory);
    descriptionInput.addEventListener('input', updateDetectedCategory);
    updateDetectedCategory();

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

    let map;
    let marker;
    let geocoder;

    function initPostLocationMap() {
      const defaultCenter = { lat: 28.6129, lng: 77.2295 };
      const savedLat = parseFloat(document.getElementById('lat').value);
      const savedLng = parseFloat(document.getElementById('lng').value);
      const hasSavedCoords = Number.isFinite(savedLat) && Number.isFinite(savedLng);
      const startCenter = hasSavedCoords ? { lat: savedLat, lng: savedLng } : defaultCenter;
      const pickerSearchForm = document.getElementById('pickerSearchForm');
      const pickerSearchInput = document.getElementById('pickerSearchInput');
      const pickerLocateButton = document.getElementById('pickerLocateButton');
      const pickerSatelliteButton = document.getElementById('pickerSatelliteButton');
      const locationText = document.getElementById('location_text');

      geocoder = new google.maps.Geocoder();
      map = new google.maps.Map(document.getElementById('map'), {
        center: startCenter,
        zoom: hasSavedCoords ? 15 : 12,
        mapTypeControl: true,
        mapTypeControlOptions: {
          style: google.maps.MapTypeControlStyle.HORIZONTAL_BAR,
          position: google.maps.ControlPosition.BOTTOM_LEFT
        },
        streetViewControl: true,
        fullscreenControl: true
      });

      function setMarker(latLng) {
        if (!marker) {
          marker = new google.maps.Marker({
            position: latLng,
            map,
            draggable: true,
            title: 'Selected location'
          });
          marker.addListener('dragend', () => updateLocationFromLatLng(marker.getPosition()));
        } else {
          marker.setPosition(latLng);
        }

        document.getElementById('lat').value = latLng.lat().toFixed(7);
        document.getElementById('lng').value = latLng.lng().toFixed(7);
      }

      function updateLocationFromLatLng(latLng) {
        setMarker(latLng);
        geocoder.geocode({ location: latLng }, (results, status) => {
          const fallback = `${latLng.lat().toFixed(5)}, ${latLng.lng().toFixed(5)}`;
          locationText.value =
            status === 'OK' && results && results[0] ? results[0].formatted_address : fallback;
        });
      }

      function searchLocation(query) {
        const value = String(query || '').trim();
        if (!value) {
          locationText.value = 'Type an address or landmark to search.';
          return;
        }

        geocoder.geocode({ address: value }, (results, status) => {
          if (status !== 'OK' || !results || !results[0]) {
            locationText.value = 'Location not found. Try a more specific address.';
            return;
          }

          const result = results[0];
          map.panTo(result.geometry.location);
          map.setZoom(16);
          setMarker(result.geometry.location);
          locationText.value = result.formatted_address;
        });
      }

      function useCurrentLocation() {
        if (!navigator.geolocation) {
          locationText.value = 'Live location is not supported in this browser.';
          return;
        }

        locationText.value = 'Fetching your current location...';
        navigator.geolocation.getCurrentPosition(position => {
          const latLng = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
          map.panTo(latLng);
          map.setZoom(16);
          updateLocationFromLatLng(latLng);
        }, () => {
          locationText.value = 'Location permission is blocked or unavailable.';
        }, {
          enableHighAccuracy: true,
          timeout: 10000,
          maximumAge: 15000
        });
      }

      if (hasSavedCoords) {
        setMarker(new google.maps.LatLng(savedLat, savedLng));
      } else if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(position => {
          map.setCenter({ lat: position.coords.latitude, lng: position.coords.longitude });
          map.setZoom(14);
        });
      }

      map.addListener('click', (event) => {
        updateLocationFromLatLng(event.latLng);
      });

      pickerSearchForm.addEventListener('submit', (event) => {
        event.preventDefault();
        searchLocation(pickerSearchInput.value);
      });

      pickerLocateButton.addEventListener('click', useCurrentLocation);

      pickerSatelliteButton.addEventListener('click', () => {
        const satelliteActive = map.getMapTypeId() === 'hybrid';
        map.setMapTypeId(satelliteActive ? 'roadmap' : 'hybrid');
        pickerSatelliteButton.classList.toggle('active', !satelliteActive);
      });
    }
  </script>
  <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo rawurlencode($google_maps_api_key); ?>&callback=initPostLocationMap&loading=async"></script>
  <?php include __DIR__ . '/inc/chatbot_widget.php'; ?>
</body>
</html>
