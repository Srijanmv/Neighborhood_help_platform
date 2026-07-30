<?php
require 'inc/config.php';

$user = current_user();
$stmt = $pdo->query("SELECT id, title, description, area, location, status, lat, lng FROM posts WHERE lat IS NOT NULL AND lng IS NOT NULL");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusCounts = [
    'pending' => 0,
    'in_progress' => 0,
    'solved' => 0,
];

foreach ($posts as $post) {
    if (isset($statusCounts[$post['status']])) {
        $statusCounts[$post['status']]++;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Map View - Neighborhood Help</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --surface: rgba(255, 255, 255, 0.84);
      --ink: #182033;
      --muted: #647084;
      --brand: #14532d;
      --brand-dark: #052e16;
      --accent: #22c55e;
      --warm: #f97316;
      --line: rgba(24, 32, 51, 0.12);
      --shadow: 0 24px 60px rgba(24, 32, 51, 0.16);
      --radius-xl: 24px;
      --radius-md: 16px;
      --max: 1240px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      min-height: 100vh;
      font-family: 'Manrope', sans-serif;
      color: var(--ink);
      background:
        linear-gradient(135deg, rgba(20, 83, 45, 0.12), transparent 28%),
        linear-gradient(225deg, rgba(249, 115, 22, 0.1), transparent 24%),
        linear-gradient(180deg, #fbf8f1 0%, #eef7ed 44%, #f8faf5 100%);
    }
    body.dark {
      --surface: rgba(17, 24, 39, 0.84);
      --ink: #e5e7eb;
      --muted: #aab4c3;
      --line: rgba(255, 255, 255, 0.12);
      background:
        linear-gradient(135deg, rgba(34, 197, 94, 0.16), transparent 30%),
        linear-gradient(225deg, rgba(249, 115, 22, 0.15), transparent 24%),
        linear-gradient(180deg, #0f172a 0%, #111827 52%, #0b1120 100%);
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
      top: 110px;
      left: -90px;
    }
    .page-shell::after {
      width: 260px;
      height: 260px;
      background: rgba(249, 115, 22, 0.18);
      bottom: 40px;
      right: -90px;
    }
    .container {
      width: min(calc(100% - 20px), 1440px);
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }
    .navbar { padding: 12px 0 10px; }
    .nav-content {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      padding: 14px 18px;
      background: rgba(255, 255, 255, 0.74);
      border: 1px solid rgba(255, 255, 255, 0.92);
      backdrop-filter: blur(18px);
      border-radius: 999px;
      box-shadow: 0 10px 30px rgba(24, 32, 51, 0.1);
    }
    .brand { display: flex; align-items: center; gap: 12px; }
    .brand-badge {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      background: linear-gradient(135deg, var(--brand), var(--accent));
      color: #fff;
      font-weight: 800;
    }
    .brand-copy strong { display: block; font-size: 1rem; line-height: 1.1; }
    .brand-copy span { display: block; font-size: 0.82rem; color: var(--muted); }
    .nav-links, .nav-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .nav-pill, .nav-solid {
      padding: 10px 14px;
      border-radius: 999px;
      font-weight: 700;
    }
    .nav-pill { background: rgba(20, 83, 45, 0.09); color: var(--brand); }
    .nav-solid { background: var(--brand); color: #fff; }
    .user-chip {
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(255,255,255,0.74);
      border: 1px solid rgba(20, 83, 45, 0.1);
      color: var(--muted);
      font-size: 0.92rem;
    }
    .map-layout {
      position: relative;
      min-height: calc(100vh - 92px);
      padding: 0 0 12px;
    }
    .panel, .map-panel {
      background: #fff;
      border: 1px solid rgba(31, 41, 55, 0.1);
      box-shadow: 0 12px 32px rgba(31, 41, 55, 0.18);
      border-radius: 18px;
    }
    body.dark .panel,
    body.dark .map-panel,
    body.dark .nav-content,
    body.dark .stat-card,
    body.dark .legend-card,
    body.dark .tips-card,
    body.dark .route-card,
    body.dark .directions-card {
      background: rgba(17, 24, 39, 0.86);
      border-color: rgba(255, 255, 255, 0.12);
      color: var(--ink);
    }
    body.dark .map-search-box,
    body.dark .map-control-group,
    body.dark .map-layer-strip,
    body.dark .map-focus-card,
    body.dark .map-caption {
      background: rgba(17, 24, 39, 0.88);
      border-color: rgba(255, 255, 255, 0.12);
      color: var(--ink);
    }
    body.dark .map-control-button,
    body.dark .layer-chip,
    body.dark .route-input,
    body.dark .route-select {
      background: rgba(255, 255, 255, 0.08);
      color: var(--ink);
      border-color: rgba(255, 255, 255, 0.12);
    }
    .panel {
      position: absolute;
      top: 82px;
      left: 16px;
      z-index: 6;
      width: min(360px, calc(100% - 32px));
      max-height: calc(100vh - 122px);
      overflow: auto;
      padding: 18px;
      display: grid;
      gap: 14px;
      backdrop-filter: blur(18px);
    }
    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 9px 14px;
      border-radius: 999px;
      background: rgba(20, 83, 45, 0.1);
      color: var(--brand);
      font-size: 0.85rem;
      font-weight: 800;
      width: fit-content;
    }
    .panel h1 {
      font-size: 1.45rem;
      line-height: 1.08;
      letter-spacing: 0;
      margin-top: 8px;
    }
    .panel p { color: var(--muted); line-height: 1.7; font-size: 0.97rem; }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }
    .stat-card, .legend-card, .tips-card {
      background: rgba(255, 255, 255, 0.72);
      border: 1px solid var(--line);
      border-radius: var(--radius-md);
      padding: 16px;
    }
    .stat-card strong {
      display: block;
      font-size: 1.7rem;
      line-height: 1;
      margin-bottom: 8px;
    }
    .stat-card span, .legend-card p, .tips-card p { color: var(--muted); font-size: 0.9rem; line-height: 1.5; }
    .legend-list { display: grid; gap: 12px; margin-top: 14px; }
    .legend-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .legend-left { display: flex; align-items: center; gap: 10px; font-weight: 700; }
    .legend-dot {
      width: 14px;
      height: 14px;
      border-radius: 999px;
      box-shadow: 0 0 0 4px rgba(20, 83, 45, 0.07);
    }
    .legend-count { color: var(--muted); font-size: 0.9rem; }
    .map-panel {
      position: relative;
      padding: 0;
      overflow: hidden;
      border-radius: 22px;
    }
    .route-card {
      background: rgba(255, 255, 255, 0.72);
      border: 1px solid var(--line);
      border-radius: var(--radius-md);
      padding: 16px;
      display: grid;
      gap: 12px;
    }
    .route-card h2 {
      font-size: 1rem;
      line-height: 1.3;
    }
    .route-search {
      display: grid;
      gap: 10px;
    }
    .route-grid-2 {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(116px, 0.42fr);
      gap: 10px;
      align-items: end;
    }
    .field-label {
      display: block;
      font-size: 0.84rem;
      font-weight: 800;
      color: var(--muted);
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    .route-input,
    .route-select {
      width: 100%;
      border: 1px solid rgba(20, 83, 45, 0.12);
      background: rgba(255, 255, 255, 0.92);
      color: var(--ink);
      border-radius: 14px;
      padding: 12px 14px;
      font: inherit;
      outline: none;
    }
    .toggle-row {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
      color: var(--muted);
      font-size: 0.88rem;
      font-weight: 700;
    }
    .toggle-chip {
      border: 0;
      border-radius: 999px;
      padding: 8px 12px;
      font: inherit;
      font-weight: 800;
      cursor: pointer;
      background: rgba(20, 83, 45, 0.08);
      color: var(--brand);
    }
    .toggle-chip.active {
      background: var(--brand);
      color: #fff;
    }
    .route-actions {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }
    .route-button,
    .route-button-alt {
      border: 0;
      border-radius: 999px;
      padding: 11px 14px;
      font: inherit;
      font-weight: 800;
      cursor: pointer;
      transition: transform 0.2s ease, opacity 0.2s ease;
    }
    .route-button {
      background: var(--brand);
      color: #fff;
      box-shadow: 0 12px 22px rgba(20, 83, 45, 0.18);
    }
    .route-button-alt {
      background: rgba(20, 83, 45, 0.08);
      color: var(--brand);
    }
    .route-button:hover,
    .route-button-alt:hover {
      transform: translateY(-1px);
    }
    .route-meta {
      display: grid;
      gap: 8px;
    }
    .route-result-card {
      display: grid;
      gap: 10px;
      padding: 14px;
      border-radius: 18px;
      background: linear-gradient(135deg, rgba(20, 83, 45, 0.1), rgba(249, 115, 22, 0.08));
      border: 1px solid rgba(20, 83, 45, 0.12);
    }
    .route-status {
      color: var(--muted);
      font-size: 0.88rem;
      line-height: 1.5;
    }
    .route-open-link {
      display: none;
      width: fit-content;
      padding: 9px 12px;
      border-radius: 999px;
      background: linear-gradient(135deg, var(--brand), #15803d);
      color: #fff;
      font-size: 0.86rem;
      font-weight: 800;
      box-shadow: 0 10px 18px rgba(20, 83, 45, 0.16);
    }
    .route-open-link.show { display: inline-flex; }
    .route-meta-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      color: var(--muted);
      font-size: 0.9rem;
    }
    .route-meta-row strong {
      color: var(--ink);
      font-size: 0.95rem;
      text-align: right;
    }
    .directions-card {
      background: rgba(255, 255, 255, 0.72);
      border: 1px solid var(--line);
      border-radius: var(--radius-md);
      padding: 16px;
      display: grid;
      gap: 12px;
    }
    .directions-card h3 {
      font-size: 1rem;
      line-height: 1.3;
    }
    .directions-list {
      display: grid;
      gap: 10px;
      max-height: 260px;
      overflow: auto;
      padding-right: 4px;
    }
    .direction-step {
      display: grid;
      grid-template-columns: 28px minmax(0, 1fr);
      gap: 10px;
      align-items: start;
      padding: 10px 0;
      border-top: 1px solid rgba(20, 83, 45, 0.08);
    }
    .direction-step:first-child {
      border-top: 0;
      padding-top: 0;
    }
    .step-index {
      width: 28px;
      height: 28px;
      border-radius: 999px;
      display: grid;
      place-items: center;
      background: rgba(20, 83, 45, 0.08);
      color: var(--brand);
      font-size: 0.78rem;
      font-weight: 800;
    }
    .step-text {
      color: #374151;
      font-size: 0.9rem;
      line-height: 1.55;
    }
    .step-distance {
      color: var(--muted);
      font-size: 0.82rem;
      margin-top: 4px;
    }
    body.dark .step-text { color: var(--ink); }
    .empty-directions {
      color: var(--muted);
      font-size: 0.9rem;
      line-height: 1.6;
    }
    .live-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      width: fit-content;
      padding: 8px 12px;
      border-radius: 999px;
      background: rgba(20, 83, 45, 0.08);
      color: var(--brand);
      font-size: 0.82rem;
      font-weight: 800;
    }
    .dot-pulse {
      width: 10px;
      height: 10px;
      border-radius: 999px;
      background: var(--accent);
      box-shadow: 0 0 0 0 rgba(15, 118, 110, 0.38);
      animation: pulse 1.8s infinite;
    }
    .map-frame {
      position: relative;
      border-radius: 22px;
      overflow: hidden;
      border: 0;
      background: #dce7ef;
    }
    #map {
      height: calc(100vh - 104px);
      min-height: 640px;
      width: 100%;
    }
    .map-caption {
      position: absolute;
      right: 18px;
      bottom: 18px;
      z-index: 5;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 10px 12px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.95);
      box-shadow: 0 10px 24px rgba(31, 41, 55, 0.16);
      backdrop-filter: blur(16px);
      color: var(--muted);
      font-size: 0.84rem;
    }
    .map-toolbar {
      position: absolute;
      top: 18px;
      left: 396px;
      right: 18px;
      z-index: 5;
      display: grid;
      grid-template-columns: minmax(280px, 520px) auto;
      gap: 12px;
      align-items: start;
      pointer-events: none;
    }
    .map-search-box,
    .map-control-group {
      pointer-events: auto;
      background: rgba(255, 255, 255, 0.98);
      border: 1px solid rgba(31, 41, 55, 0.1);
      box-shadow: 0 8px 24px rgba(31, 41, 55, 0.18);
      border-radius: 24px;
      backdrop-filter: blur(18px);
    }
    .map-search-box {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 8px;
      padding: 8px;
    }
    .map-search-box input {
      min-width: 0;
      border: 0;
      outline: none;
      background: transparent;
      color: var(--ink);
      font: inherit;
      padding: 9px 10px;
    }
    .map-search-box button,
    .map-control-button {
      border: 0;
      cursor: pointer;
      border-radius: 999px;
      background: rgba(31, 41, 55, 0.06);
      color: #3c4043;
      font: inherit;
      font-weight: 800;
      padding: 9px 12px;
      white-space: nowrap;
      transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease, color 0.18s ease;
    }
    .map-search-box button:hover,
    .map-control-button:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 18px rgba(20, 83, 45, 0.16);
    }
    .map-search-box button {
      background: linear-gradient(135deg, var(--brand), #15803d);
      color: #fff;
    }
    .map-control-group {
      display: flex;
      gap: 6px;
      padding: 8px;
      flex-wrap: wrap;
      justify-content: flex-end;
      max-width: 460px;
    }
    .map-control-button.active {
      background: linear-gradient(135deg, var(--brand), #15803d);
      color: #fff;
    }
    .map-layer-strip {
      position: absolute;
      left: 396px;
      bottom: 18px;
      z-index: 5;
      display: flex;
      gap: 8px;
      align-items: center;
      pointer-events: auto;
      padding: 8px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.92);
      border: 1px solid rgba(31, 41, 55, 0.1);
      box-shadow: 0 14px 30px rgba(31, 41, 55, 0.16);
      backdrop-filter: blur(16px);
    }
    .layer-chip {
      border: 0;
      border-radius: 999px;
      cursor: pointer;
      padding: 8px 11px;
      font: inherit;
      font-size: 0.82rem;
      font-weight: 800;
      color: #334155;
      background: rgba(31, 41, 55, 0.06);
      transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease;
    }
    .layer-chip:hover { transform: translateY(-1px); }
    .layer-chip.active { background: var(--brand); color: #fff; }
    .map-focus-card {
      position: absolute;
      right: 18px;
      top: 100px;
      z-index: 5;
      width: min(252px, calc(100% - 36px));
      padding: 14px;
      border-radius: 18px;
      background: rgba(255, 255, 255, 0.92);
      border: 1px solid rgba(31, 41, 55, 0.1);
      box-shadow: 0 14px 30px rgba(31, 41, 55, 0.16);
      backdrop-filter: blur(16px);
      pointer-events: none;
    }
    .map-focus-card small {
      display: block;
      color: var(--muted);
      font-weight: 800;
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    .map-focus-card strong {
      display: block;
      color: var(--ink);
      font-size: 0.96rem;
      line-height: 1.35;
    }
    .map-focus-card span {
      display: block;
      color: var(--muted);
      font-size: 0.82rem;
      line-height: 1.45;
      margin-top: 6px;
    }
    .map-toast {
      position: absolute;
      left: 24px;
      bottom: 24px;
      z-index: 5;
      max-width: min(340px, calc(100% - 48px));
      padding: 11px 14px;
      border-radius: 14px;
      background: rgba(31, 41, 55, 0.92);
      color: #fff;
      font-size: 0.88rem;
      line-height: 1.45;
      box-shadow: 0 18px 36px rgba(31, 41, 55, 0.18);
      display: none;
    }
    .map-toast.show { display: block; }
    .status-marker {
      width: 22px;
      height: 22px;
      border-radius: 999px;
      border: 3px solid rgba(255, 255, 255, 0.92);
      box-shadow: 0 10px 18px rgba(31, 41, 55, 0.18);
      position: relative;
    }
    .status-marker::after {
      content: "";
      position: absolute;
      inset: 5px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.92);
    }
    .status-marker.pending { background: #ef4444; }
    .status-marker.in-progress { background: #f97316; }
    .status-marker.solved { background: #16a34a; }
    .gm-style .gm-style-iw-c {
      border-radius: 20px;
      box-shadow: 0 22px 42px rgba(31, 41, 55, 0.18);
      padding: 0;
    }
    .gm-style .gm-style-iw-d {
      overflow: auto !important;
      max-width: 280px !important;
      font-family: 'Manrope', sans-serif;
    }
    .popup-card { padding: 18px; }
    .popup-status {
      display: inline-flex;
      padding: 7px 10px;
      border-radius: 999px;
      font-size: 0.77rem;
      font-weight: 800;
      text-transform: uppercase;
      margin-bottom: 12px;
    }
    .popup-status.pending { background: rgba(239, 68, 68, 0.12); color: #b91c1c; }
    .popup-status.in_progress { background: rgba(249, 115, 22, 0.14); color: #c2410c; }
    .popup-status.solved { background: rgba(22, 163, 74, 0.12); color: #15803d; }
    .popup-card h3 { font-size: 1rem; line-height: 1.35; margin-bottom: 8px; }
    .popup-meta { color: var(--muted); font-size: 0.82rem; margin-bottom: 10px; }
    .popup-card p { color: #374151; font-size: 0.9rem; line-height: 1.55; margin-bottom: 14px; }
    .popup-link { color: var(--brand); font-weight: 800; font-size: 0.88rem; }
    @keyframes pulse {
      0% { box-shadow: 0 0 0 0 rgba(15, 118, 110, 0.38); }
      70% { box-shadow: 0 0 0 10px rgba(15, 118, 110, 0); }
      100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }
    @media (max-width: 980px) {
      .nav-content, .map-caption { display: block; }
      .nav-links, .nav-actions { margin-top: 14px; }
      .map-layout { padding-bottom: 0; }
      .panel {
        position: relative;
        top: auto;
        left: auto;
        width: 100%;
        max-height: none;
        margin-bottom: 10px;
      }
      .map-toolbar {
        position: absolute;
        top: 14px;
        left: 14px;
        right: 14px;
        grid-template-columns: 1fr;
      }
      .map-control-group {
        max-width: none;
        justify-content: flex-start;
      }
      .map-layer-strip {
        left: 14px;
        right: 14px;
        bottom: 14px;
        overflow-x: auto;
      }
      .map-focus-card { display: none; }
      #map { height: 70vh; min-height: 480px; }
    }
    @media (max-width: 640px) {
      .container { width: min(calc(100% - 20px), var(--max)); }
      .nav-content, .panel, .map-panel { border-radius: 24px; }
      .stats-grid { grid-template-columns: 1fr; }
      .route-grid-2 { grid-template-columns: 1fr; }
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
              <span>Live issues across your community</span>
            </div>
          </div>
          <div class="nav-links">
            <a href="community.php" class="nav-pill">Back to Feed</a>
            <a href="new_post.php" class="nav-pill">Create Post</a>
          </div>
          <div class="nav-actions">
            <?php if ($user): ?>
              <span class="user-chip">Hi, <?php echo e($user['name']); ?></span>
              <a href="logout.php" class="nav-solid">Logout</a>
            <?php else: ?>
              <a href="login.php" class="nav-solid">Login</a>
            <?php endif; ?>
          </div>
        </div>
      </header>

      <main class="map-layout">
        <aside class="panel">
          <span class="eyebrow">Location-aware requests</span>
          <div>
            <h1>See where help is needed, not just what happened.</h1>
            <p>Every marker represents a real neighborhood issue so volunteers can understand urgency, proximity, and where to step in first.</p>
          </div>
          <div class="stats-grid">
            <div class="stat-card">
              <strong><?php echo count($posts); ?></strong>
              <span>Total mapped posts</span>
            </div>
            <div class="stat-card">
              <strong><?php echo count(array_unique(array_filter(array_column($posts, 'area')))); ?></strong>
              <span>Areas represented</span>
            </div>
          </div>
          <div class="legend-card">
            <p>Issue status</p>
            <div class="legend-list">
              <div class="legend-item">
                <div class="legend-left"><span class="legend-dot" style="background:#ef4444;"></span><span>Pending</span></div>
                <span class="legend-count"><?php echo $statusCounts['pending']; ?></span>
              </div>
              <div class="legend-item">
                <div class="legend-left"><span class="legend-dot" style="background:#f97316;"></span><span>In progress</span></div>
                <span class="legend-count"><?php echo $statusCounts['in_progress']; ?></span>
              </div>
              <div class="legend-item">
                <div class="legend-left"><span class="legend-dot" style="background:#16a34a;"></span><span>Solved</span></div>
                <span class="legend-count"><?php echo $statusCounts['solved']; ?></span>
              </div>
            </div>
          </div>
          <div class="route-card">
            <h2>Live route planner</h2>
            <div class="live-pill"><span class="dot-pulse"></span><span id="liveStatus">Waiting for live location</span></div>
            <div class="toggle-row">
              <span>Source mode</span>
              <button type="button" class="toggle-chip active" id="liveModeButton">Live</button>
              <button type="button" class="toggle-chip" id="manualModeButton">Manual</button>
            </div>
            <div>
              <label class="field-label" for="sourceInput">Source</label>
              <input id="sourceInput" class="route-input" type="text" value="Use my live location" readonly>
            </div>
            <div class="route-grid-2">
              <div class="route-search">
                <label class="field-label" for="destinationSearch">Search destination</label>
                <input id="destinationSearch" class="route-input" type="text" placeholder="Type title, area, or location">
              </div>
              <div>
                <label class="field-label" for="routeModeSelect">Mode</label>
                <select id="routeModeSelect" class="route-select">
                  <option value="DRIVING">Driving</option>
                  <option value="WALKING">Walking</option>
                  <option value="BICYCLING">Bike</option>
                  <option value="TRANSIT">Transit</option>
                </select>
              </div>
            </div>
            <div>
              <label class="field-label" for="destinationSelect">Destination</label>
              <select id="destinationSelect" class="route-select">
                <option value="">Choose a mapped post</option>
              </select>
            </div>
            <div class="route-actions">
              <button type="button" class="route-button" id="routeButton">Show Route</button>
              <button type="button" class="route-button-alt" id="locateButton">Refresh Live</button>
            </div>
            <div class="route-result-card">
              <div class="route-meta">
                <div class="route-meta-row"><span>Destination</span><strong id="routeDestination">Not selected</strong></div>
                <div class="route-meta-row"><span>Distance</span><strong id="routeDistance">-</strong></div>
                <div class="route-meta-row"><span>ETA</span><strong id="routeTime">-</strong></div>
              </div>
              <div class="route-status" id="routeStatus">Choose a destination. Live location or manual source dono work karenge.</div>
              <a class="route-open-link" id="routeOpenLink" href="#" target="_blank" rel="noopener">Open in Google Maps</a>
            </div>
          </div>
          <div class="directions-card">
            <h3>Turn-by-turn directions</h3>
            <div class="directions-list" id="directionsList">
              <div class="empty-directions">Choose a destination and draw a route to see step-by-step directions here.</div>
            </div>
          </div>
          <div class="tips-card">
            <p>Tip: open any marker to jump into the full post, check updates, and coordinate help with comments.</p>
          </div>
        </aside>

        <section class="map-panel">
          <div class="map-frame">
            <div class="map-toolbar">
              <form class="map-search-box" id="mapSearchForm">
                <input id="mapSearchInput" type="search" placeholder="Search place, landmark, or area">
                <button type="submit">Search</button>
              </form>
              <div class="map-control-group" aria-label="Map tools">
                <button type="button" class="map-control-button active" id="roadmapButton">Map</button>
                <button type="button" class="map-control-button" id="satelliteButton">Satellite</button>
                <button type="button" class="map-control-button" id="trafficButton">Traffic</button>
                <button type="button" class="map-control-button" id="myLocationButton">My Location</button>
                <button type="button" class="map-control-button" id="nearestButton">Nearest</button>
                <button type="button" class="map-control-button" id="fitPinsButton">Fit Pins</button>
              </div>
            </div>
            <div id="map"></div>
            <div class="map-focus-card" id="mapFocusCard">
              <small>Map focus</small>
              <strong>Hover a community pin</strong>
              <span>Markers lift, highlight, and show quick details before you open the post.</span>
            </div>
            <div class="map-layer-strip" aria-label="Quick map layers">
              <button type="button" class="layer-chip active" id="defaultLayerButton">Default</button>
              <button type="button" class="layer-chip" id="darkLayerButton">Dark</button>
            </div>
            <div class="map-toast" id="mapToast"></div>
          </div>
          <div class="map-caption">
            <span>Powered by Google Maps with live post coordinates from your app.</span>
            <span><?php echo count($posts); ?> active map pins</span>
          </div>
        </section>
      </main>
    </div>
  </div>

  <script>
    if (window.location.hostname === '127.0.0.1') {
      window.location.replace(window.location.href.replace('//127.0.0.1', '//localhost'));
    }

    const posts = <?php echo json_encode($posts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

    function escapeHtml(value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function createMarkerIcon(status, active = false) {
      const colors = {
        pending: '#f97316',
        in_progress: '#f97316',
        solved: '#0f766e'
      };
      return {
        path: 'M 0,-22 C 11,-22 20,-13 20,-2 C 20,12 0,24 0,24 C 0,24 -20,12 -20,-2 C -20,-13 -11,-22 0,-22 Z',
        scale: active ? 1.12 : 0.92,
        strokeColor: '#ffffff',
        strokeWeight: active ? 4 : 3,
        fillColor: colors[status] || colors.pending,
        fillOpacity: 1,
        anchor: new google.maps.Point(0, 24),
        labelOrigin: new google.maps.Point(0, -3)
      };
    }

    function createSourceIcon(color, active = false) {
      return {
        path: google.maps.SymbolPath.CIRCLE,
        scale: active ? 11 : 8,
        strokeColor: '#ffffff',
        strokeWeight: 4,
        fillColor: color,
        fillOpacity: 0.95
      };
    }

    const nightMapStyle = [
      { elementType: 'geometry', stylers: [{ color: '#1f2937' }] },
      { elementType: 'labels.text.fill', stylers: [{ color: '#d1d5db' }] },
      { elementType: 'labels.text.stroke', stylers: [{ color: '#111827' }] },
      { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#374151' }] },
      { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#22c55e' }] },
      { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#0f172a' }] },
      { featureType: 'poi', stylers: [{ visibility: 'simplified' }] }
    ];

    function formatDistance(meters) {
      if (!Number.isFinite(meters)) return '-';
      return meters >= 1000 ? `${(meters / 1000).toFixed(1)} km` : `${Math.round(meters)} m`;
    }
    function formatTime(seconds) {
      if (!Number.isFinite(seconds)) return '-';
      const minutes = Math.round(seconds / 60);
      if (minutes < 60) return `${minutes} min`;
      const hours = Math.floor(minutes / 60);
      const remainingMinutes = minutes % 60;
      return remainingMinutes ? `${hours} hr ${remainingMinutes} min` : `${hours} hr`;
    }

    function stripDirectionHtml(value) {
      const element = document.createElement('div');
      element.innerHTML = value || '';
      return element.textContent || element.innerText || 'Continue';
    }

    function coordText(latLng) {
      return `${latLng.lat().toFixed(5)}, ${latLng.lng().toFixed(5)}`;
    }

    function initCommunityMap() {
      const mapSearchForm = document.getElementById('mapSearchForm');
      const mapSearchInput = document.getElementById('mapSearchInput');
      const roadmapButton = document.getElementById('roadmapButton');
      const satelliteButton = document.getElementById('satelliteButton');
      const trafficButton = document.getElementById('trafficButton');
      const myLocationButton = document.getElementById('myLocationButton');
      const nearestButton = document.getElementById('nearestButton');
      const fitPinsButton = document.getElementById('fitPinsButton');
      const mapToast = document.getElementById('mapToast');
      const destinationSelect = document.getElementById('destinationSelect');
      const destinationSearch = document.getElementById('destinationSearch');
      const sourceInput = document.getElementById('sourceInput');
      const routeDestination = document.getElementById('routeDestination');
      const routeDistance = document.getElementById('routeDistance');
      const routeTime = document.getElementById('routeTime');
      const routeStatus = document.getElementById('routeStatus');
      const routeOpenLink = document.getElementById('routeOpenLink');
      const routeModeSelect = document.getElementById('routeModeSelect');
      const liveStatus = document.getElementById('liveStatus');
      const routeButton = document.getElementById('routeButton');
      const locateButton = document.getElementById('locateButton');
      const directionsList = document.getElementById('directionsList');
      const liveModeButton = document.getElementById('liveModeButton');
      const manualModeButton = document.getElementById('manualModeButton');
      const defaultLayerButton = document.getElementById('defaultLayerButton');
      const darkLayerButton = document.getElementById('darkLayerButton');
      const mapFocusCard = document.getElementById('mapFocusCard');

      const map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: 28.6129, lng: 77.2295 },
        zoom: 11,
        gestureHandling: 'greedy',
        mapTypeControl: true,
        mapTypeControlOptions: {
          style: google.maps.MapTypeControlStyle.HORIZONTAL_BAR,
          position: google.maps.ControlPosition.BOTTOM_LEFT
        },
        streetViewControl: true,
        streetViewControlOptions: { position: google.maps.ControlPosition.RIGHT_BOTTOM },
        fullscreenControl: true,
        scaleControl: true,
        zoomControlOptions: { position: google.maps.ControlPosition.RIGHT_BOTTOM }
      });
      const geocoder = new google.maps.Geocoder();
      const trafficLayer = new google.maps.TrafficLayer();
      const directionsService = new google.maps.DirectionsService();
      const directionsRenderer = new google.maps.DirectionsRenderer({
        map,
        suppressMarkers: true,
        polylineOptions: {
          strokeColor: '#14532d',
          strokeOpacity: 0.92,
          strokeWeight: 7
        }
      });
      const infoWindow = new google.maps.InfoWindow();
      const bounds = new google.maps.LatLngBounds();
      const postMap = new Map();
      const markerPositions = [];
      let hasBounds = false;
      let liveLatLng = null;
      let liveMarker = null;
      let sourceMode = 'live';
      let manualSourceMarker = null;
      let manualSourceLatLng = null;
      let searchedPlaceMarker = null;
      let toastTimer = null;
      let fallbackRouteLine = null;

      function showMapToast(message) {
        mapToast.textContent = message;
        mapToast.classList.add('show');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => mapToast.classList.remove('show'), 3200);
      }

      function setMapType(type) {
        map.setMapTypeId(type);
        const satelliteActive = type === 'satellite' || type === 'hybrid';
        roadmapButton.classList.toggle('active', !satelliteActive);
        satelliteButton.classList.toggle('active', satelliteActive);
      }

      function setVisualLayer(layer) {
        const darkActive = layer === 'dark';
        defaultLayerButton.classList.toggle('active', !darkActive);
        darkLayerButton.classList.toggle('active', darkActive);
        document.body.classList.toggle('dark', darkActive);
        map.setOptions({ styles: darkActive ? nightMapStyle : null });
        showMapToast(darkActive ? 'Dark map mode enabled.' : 'Default Google map mode enabled.');
      }

      function updateFocusCard(title, meta, description) {
        mapFocusCard.innerHTML = `
          <small>Map focus</small>
          <strong>${escapeHtml(title)}</strong>
          <span>${escapeHtml(meta || description || 'Community issue marker')}</span>
        `;
      }

      function setRouteStatus(message) {
        routeStatus.textContent = message;
      }

      function clearFallbackRouteLine() {
        if (fallbackRouteLine) {
          fallbackRouteLine.setMap(null);
          fallbackRouteLine = null;
        }
      }

      function distanceBetweenMeters(a, b) {
        const toRad = value => value * Math.PI / 180;
        const lat1 = typeof a.lat === 'function' ? a.lat() : a.lat;
        const lng1 = typeof a.lng === 'function' ? a.lng() : a.lng;
        const lat2 = typeof b.lat === 'function' ? b.lat() : b.lat;
        const lng2 = typeof b.lng === 'function' ? b.lng() : b.lng;
        const earthRadius = 6371000;
        const dLat = toRad(lat2 - lat1);
        const dLng = toRad(lng2 - lng1);
        const h =
          Math.sin(dLat / 2) * Math.sin(dLat / 2) +
          Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
          Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return 2 * earthRadius * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
      }

      function pointDistanceFromMedian(point, median) {
        return distanceBetweenMeters(
          new google.maps.LatLng(point.lat, point.lng),
          new google.maps.LatLng(median.lat, median.lng)
        );
      }

      function median(values) {
        const sorted = values.slice().sort((a, b) => a - b);
        const mid = Math.floor(sorted.length / 2);
        return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
      }

      function googleMapsDirectionsUrl(sourceLatLng, destination) {
        const source = `${sourceLatLng.lat().toFixed(7)},${sourceLatLng.lng().toFixed(7)}`;
        const target = `${destination.lat.toFixed(7)},${destination.lng.toFixed(7)}`;
        return `https://www.google.com/maps/dir/?api=1&origin=${encodeURIComponent(source)}&destination=${encodeURIComponent(target)}&travelmode=${routeModeSelect.value.toLowerCase()}`;
      }

      function showRouteLink(sourceLatLng, destination) {
        routeOpenLink.href = googleMapsDirectionsUrl(sourceLatLng, destination);
        routeOpenLink.classList.add('show');
      }

      function clearRouteLink() {
        routeOpenLink.classList.remove('show');
        routeOpenLink.href = '#';
      }

      function findMatchingDestination(query) {
        const value = String(query || '').trim().toLowerCase();
        if (!value) return null;
        return posts.find(post => {
          const haystack = [
            post.title,
            post.area,
            post.location,
            post.description
          ].map(part => String(part || '').toLowerCase()).join(' ');
          return haystack.includes(value);
        }) || null;
      }

      function selectDestination(post, announce = true) {
        if (!post) return null;
        const id = String(post.id);
        const stored = postMap.get(id);
        if (!stored) return null;
        destinationSelect.value = id;
        routeDestination.textContent = stored.title || 'Selected post';
        destinationSearch.value = stored.title || stored.area || '';
        clearRouteMetrics();
        clearDirections();
        clearRouteLink();
        directionsRenderer.set('directions', null);
        clearFallbackRouteLine();
        map.panTo({ lat: stored.lat, lng: stored.lng });
        map.setZoom(Math.max(map.getZoom() || 12, 14));
        if (announce) {
          setRouteStatus('Destination selected. Press Show Route to draw the route and steps.');
        }
        return stored;
      }

      function selectNearestDestination() {
        const sourceLatLng = sourceMode === 'live' ? liveLatLng : manualSourceLatLng;
        if (!sourceLatLng) {
          setRouteStatus(sourceMode === 'live' ? 'Need live location first, or switch to Manual source.' : 'Click the map to set manual source first.');
          showMapToast(sourceMode === 'live' ? 'Need source first.' : 'Click the map to set source first.');
          return;
        }

        let nearest = null;
        let nearestMeters = Infinity;
        postMap.forEach(post => {
          const meters = distanceBetweenMeters(sourceLatLng, new google.maps.LatLng(post.lat, post.lng));
          if (meters < nearestMeters) {
            nearestMeters = meters;
            nearest = post;
          }
        });

        if (!nearest) {
          showMapToast('No mapped destination found.');
          return;
        }

        selectDestination(nearest, false);
        routeDistance.textContent = `${formatDistance(nearestMeters)} approx`;
        routeTime.textContent = 'Ready';
        setRouteStatus(`Nearest issue selected: ${nearest.title || 'Community post'} (${formatDistance(nearestMeters)} away approx). Press Show Route.`);
        showMapToast('Nearest issue selected.');
      }

      function fitSmartPins(showMessage = true) {
        if (!markerPositions.length) {
          if (showMessage) showMapToast('No mapped posts are available yet.');
          return;
        }

        const medianPoint = {
          lat: median(markerPositions.map(point => point.lat)),
          lng: median(markerPositions.map(point => point.lng))
        };
        const distances = markerPositions.map(point => pointDistanceFromMedian(point, medianPoint));
        const medianDistance = median(distances);
        const localRadius = Math.max(25000, Math.min(90000, medianDistance * 2.8 || 25000));
        let visiblePins = markerPositions.filter((point, index) => distances[index] <= localRadius);

        if (visiblePins.length < Math.min(2, markerPositions.length)) {
          visiblePins = markerPositions.slice();
        }

        const smartBounds = new google.maps.LatLngBounds();
        visiblePins.forEach(point => smartBounds.extend({ lat: point.lat, lng: point.lng }));
        map.fitBounds(smartBounds, 78);

        google.maps.event.addListenerOnce(map, 'idle', () => {
          if ((map.getZoom() || 0) > 15) {
            map.setZoom(15);
          }
        });

        const ignored = markerPositions.length - visiblePins.length;
        if (showMessage) {
          showMapToast(ignored > 0 ? `Fit Pins focused on local cluster and ignored ${ignored} far ${ignored === 1 ? 'pin' : 'pins'}.` : 'All community pins are now in view.');
        }
      }

      function fitAllPins() {
        fitSmartPins(true);
      }

      function searchMapPlace(query) {
        const value = String(query || '').trim();
        if (!value) {
          showMapToast('Type a place, landmark, or area to search.');
          return;
        }

        geocoder.geocode({ address: value }, (results, status) => {
          if (status !== 'OK' || !results || !results[0]) {
            showMapToast('Place not found. Try a more specific search.');
            return;
          }

          const result = results[0];
          const location = result.geometry.location;
          map.panTo(location);
          map.setZoom(15);

          if (!searchedPlaceMarker) {
            searchedPlaceMarker = new google.maps.Marker({
              map,
              position: location,
              title: result.formatted_address,
              icon: {
                path: google.maps.SymbolPath.BACKWARD_CLOSED_ARROW,
                scale: 7,
                strokeColor: '#0f766e',
                strokeWeight: 2,
                fillColor: '#14b8a6',
                fillOpacity: 1
              }
            });
          } else {
            searchedPlaceMarker.setPosition(location);
            searchedPlaceMarker.setTitle(result.formatted_address);
          }

          infoWindow.setContent(`
            <div class="popup-card">
              <div class="popup-status solved">Search Result</div>
              <h3>${escapeHtml(value)}</h3>
              <div class="popup-meta">${escapeHtml(result.formatted_address)}</div>
            </div>
          `);
          infoWindow.open({ anchor: searchedPlaceMarker, map });
          showMapToast('Search result pinned on the map.');
          setRouteStatus('Place pinned on the map. Community route destinations are selected from mapped posts.');
        });
      }

      posts.forEach(post => {
        const lat = parseFloat(post.lat);
        const lng = parseFloat(post.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        const position = { lat, lng };
        bounds.extend(position);
        markerPositions.push(position);
        hasBounds = true;
        postMap.set(String(post.id), { ...post, lat, lng });

        const destinationLabel = `${post.title} (${post.area || 'Unknown area'})`;
        const option = document.createElement('option');
        option.value = String(post.id);
        option.textContent = destinationLabel;
        destinationSelect.appendChild(option);

        const status = post.status || 'pending';
        const statusLabel = status === 'in_progress' ? 'In Progress' : status.charAt(0).toUpperCase() + status.slice(1);
        const snippet = post.description ? `${post.description.slice(0, 100)}${post.description.length > 100 ? '...' : ''}` : 'Open this post to see the details and offer help.';
        const marker = new google.maps.Marker({
          position,
          map,
          title: post.title || 'Community post',
          icon: createMarkerIcon(status),
          label: {
            text: status === 'solved' ? 'S' : '!',
            color: '#ffffff',
            fontSize: '13px',
            fontWeight: '900'
          },
          optimized: false
        });

        marker.addListener('mouseover', () => {
          marker.setIcon(createMarkerIcon(status, true));
          marker.setZIndex(google.maps.Marker.MAX_ZINDEX + 1);
          updateFocusCard(post.title || 'Community post', post.area || statusLabel, snippet);
        });

        marker.addListener('mouseout', () => {
          marker.setIcon(createMarkerIcon(status));
          marker.setZIndex(null);
          updateFocusCard('Hover a community pin', 'Markers lift, highlight, and show quick details before you open the post.', '');
        });

        marker.addListener('click', () => {
          selectDestination(post, false);
          routeDestination.textContent = destinationLabel;
          setRouteStatus('Destination locked from map pin. Press Show Route to navigate.');
          infoWindow.setContent(`
            <div class="popup-card">
              <div class="popup-status ${escapeHtml(status)}">${escapeHtml(statusLabel)}</div>
              <h3>${escapeHtml(post.title)}</h3>
              <div class="popup-meta">${escapeHtml(post.area || 'Unknown area')}</div>
              <p>${escapeHtml(snippet)}</p>
              <a class="popup-link" href="view_post.php?id=${encodeURIComponent(post.id)}">View post &rarr;</a>
            </div>
          `);
          infoWindow.open({ anchor: marker, map });
        });
      });

      if (hasBounds) {
        fitSmartPins(false);
      }

      function updateLiveLocation(position) {
        liveLatLng = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
        sourceInput.value = coordText(liveLatLng);
        liveStatus.textContent = 'Live location ready';

        if (!liveMarker) {
          liveMarker = new google.maps.Marker({
            position: liveLatLng,
            map,
            title: 'Your live location',
            icon: createSourceIcon('#0f766e', true),
            optimized: false
          });
        } else {
          liveMarker.setPosition(liveLatLng);
        }
        myLocationButton.classList.add('active');
        setRouteStatus('Source ready. Choose a destination and press Show Route.');
      }

      function handleLocationError() {
        liveStatus.textContent = 'Live location unavailable';
        sourceInput.value = 'Switch to Manual, then click map to set source';
        setRouteStatus('Live location blocked. Use Manual source mode and click your starting point on the map.');
        showMapToast('Location blocked. Switch to Manual and click your source point.');
      }

      function setSourceMode(mode) {
        sourceMode = mode;
        const liveActive = mode === 'live';
        liveModeButton.classList.toggle('active', liveActive);
        manualModeButton.classList.toggle('active', !liveActive);
        sourceInput.readOnly = liveActive;

        if (liveActive) {
          sourceInput.value = liveLatLng ? coordText(liveLatLng) : 'Use my live location';
          liveStatus.textContent = liveLatLng ? 'Live location ready' : 'Waiting for live location';
        } else {
          sourceInput.value = manualSourceLatLng ? coordText(manualSourceLatLng) : 'Click anywhere on the map to set source';
          liveStatus.textContent = 'Manual source mode active';
        }
      }

      function requestLiveLocation() {
        if (!navigator.geolocation) {
          handleLocationError();
          return;
        }
        liveStatus.textContent = 'Fetching live location';
        navigator.geolocation.getCurrentPosition(updateLiveLocation, handleLocationError, {
          enableHighAccuracy: true,
          timeout: 10000,
          maximumAge: 15000
        });
      }

      function clearRouteMetrics() {
        routeDistance.textContent = '-';
        routeTime.textContent = '-';
        setRouteStatus('Choose a destination. Live location or manual source dono work karenge.');
        clearRouteLink();
      }

      function clearDirections() {
        directionsList.innerHTML = '<div class="empty-directions">Choose a destination and draw a route to see step-by-step directions here.</div>';
      }

      function renderDirections(steps) {
        if (!steps || !steps.length) {
          clearDirections();
          return;
        }

        directionsList.innerHTML = steps.map((step, index) => `
          <div class="direction-step">
            <div class="step-index">${index + 1}</div>
            <div>
              <div class="step-text">${escapeHtml(stripDirectionHtml(step.instructions))}</div>
              <div class="step-distance">${step.distance ? escapeHtml(step.distance.text) : '-'}</div>
            </div>
          </div>
        `).join('');
      }

      function renderFallbackDirections(sourceLatLng, destination, meters) {
        const modeLabel = routeModeSelect.options[routeModeSelect.selectedIndex]?.text || 'Route';
        directionsList.innerHTML = `
          <div class="direction-step">
            <div class="step-index">1</div>
            <div>
              <div class="step-text">Start from ${escapeHtml(coordText(sourceLatLng))}.</div>
              <div class="step-distance">Source pinned on this map</div>
            </div>
          </div>
          <div class="direction-step">
            <div class="step-index">2</div>
            <div>
              <div class="step-text">${escapeHtml(modeLabel)} toward ${escapeHtml(destination.title || 'selected destination')}.</div>
              <div class="step-distance">Approx ${escapeHtml(formatDistance(meters))}</div>
            </div>
          </div>
          <div class="direction-step">
            <div class="step-index">3</div>
            <div>
              <div class="step-text">Open in Google Maps for live turn-by-turn navigation and road traffic.</div>
              <div class="step-distance">External navigation link available below</div>
            </div>
          </div>
        `;
      }

      function drawFallbackRoute(sourceLatLng, destination, reason) {
        clearFallbackRouteLine();
        const targetLatLng = new google.maps.LatLng(destination.lat, destination.lng);
        const meters = distanceBetweenMeters(sourceLatLng, targetLatLng);
        routeDistance.textContent = `${formatDistance(meters)} approx`;
        routeTime.textContent = 'Open Maps';
        fallbackRouteLine = new google.maps.Polyline({
          map,
          path: [sourceLatLng, targetLatLng],
          geodesic: true,
          strokeColor: '#f97316',
          strokeOpacity: 0.95,
          strokeWeight: 6,
          icons: [{
            icon: { path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW, scale: 3, strokeColor: '#f97316' },
            offset: '100%'
          }]
        });
        const routeBounds = new google.maps.LatLngBounds();
        routeBounds.extend(sourceLatLng);
        routeBounds.extend(targetLatLng);
        map.fitBounds(routeBounds, 80);
        renderFallbackDirections(sourceLatLng, destination, meters);
        showRouteLink(sourceLatLng, destination);
        setRouteStatus(`Google route service did not return turn-by-turn steps (${reason}). Approx route shown; open Google Maps for live navigation.`);
      }

      function drawRoute() {
        let selectedId = destinationSelect.value;
        let destination = postMap.get(selectedId);
        const sourceLatLng = sourceMode === 'live' ? liveLatLng : manualSourceLatLng;

        if (!sourceLatLng) {
          liveStatus.textContent = sourceMode === 'live' ? 'Need live location first' : 'Set source on map first';
          setRouteStatus(sourceMode === 'live' ? 'Allow location access or switch to Manual source.' : 'Manual mode active. Click your starting point on the map.');
          showMapToast(sourceMode === 'live' ? 'Need live location first.' : 'Click the map to set source first.');
          return;
        }

        if (!destination) {
          const match = findMatchingDestination(destinationSearch.value);
          if (match) {
            destination = selectDestination(match, false);
            selectedId = String(match.id);
          }
          if (!destination) {
            routeDestination.textContent = 'Choose a destination';
            clearRouteMetrics();
            setRouteStatus('Type a post title/area, select a destination, or click any marker on the map.');
            showMapToast('Choose a mapped post destination first.');
            return;
          }
        }

        routeDestination.textContent = `${destination.title}`;
        routeDistance.textContent = 'Calculating...';
        routeTime.textContent = 'Calculating...';
        setRouteStatus('Drawing route and loading step-by-step directions...');
        clearFallbackRouteLine();
        clearRouteLink();
        directionsService.route({
          origin: sourceLatLng,
          destination: { lat: destination.lat, lng: destination.lng },
          travelMode: google.maps.TravelMode[routeModeSelect.value] || google.maps.TravelMode.DRIVING,
          provideRouteAlternatives: false
        }, (result, status) => {
          if (status !== 'OK' || !result || !result.routes.length) {
            drawFallbackRoute(sourceLatLng, destination, status || 'unavailable');
            return;
          }

          clearFallbackRouteLine();
          directionsRenderer.setDirections(result);
          const leg = result.routes[0].legs[0];
          routeDistance.textContent = leg.distance ? leg.distance.text : '-';
          routeTime.textContent = leg.duration ? leg.duration.text : '-';
          renderDirections(leg.steps || []);
          showRouteLink(sourceLatLng, destination);
          setRouteStatus(`${leg.distance ? leg.distance.text : 'Route'} route ready with ${leg.steps ? leg.steps.length : 0} steps.`);
          showMapToast('Route ready with distance, ETA, and steps.');
        });
      }

      destinationSelect.addEventListener('change', function() {
        const destination = postMap.get(this.value);
        routeDestination.textContent = destination ? destination.title : 'Not selected';
        clearRouteMetrics();
        clearDirections();
        directionsRenderer.set('directions', null);
        clearFallbackRouteLine();
        if (destination) {
          destinationSearch.value = destination.title || destination.area || '';
          setRouteStatus('Destination selected. Press Show Route to draw route.');
          map.panTo({ lat: destination.lat, lng: destination.lng });
        }
      });

      destinationSearch.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        const previousValue = destinationSelect.value;
        destinationSelect.innerHTML = '<option value="">Choose a mapped post</option>';
        let firstMatch = null;

        posts.forEach(post => {
          const haystack = [
            post.title,
            post.area,
            post.location,
            post.description
          ].map(part => String(part || '').toLowerCase()).join(' ');
          if (query && !haystack.includes(query)) {
            return;
          }
          if (!firstMatch) firstMatch = post;
          const option = document.createElement('option');
          option.value = String(post.id);
          option.textContent = `${post.title} (${post.area || 'Unknown area'})`;
          if (option.value === previousValue) {
            option.selected = true;
          }
          destinationSelect.appendChild(option);
        });

        if (query && firstMatch && previousValue !== String(firstMatch.id)) {
          const selected = postMap.get(String(firstMatch.id));
          destinationSelect.value = String(firstMatch.id);
          routeDestination.textContent = selected ? selected.title : firstMatch.title;
          clearRouteMetrics();
          setRouteStatus('Best matching destination selected. Press Show Route.');
        }
      });

      destinationSearch.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          drawRoute();
        }
      });

      mapSearchForm.addEventListener('submit', function(event) {
        event.preventDefault();
        searchMapPlace(mapSearchInput.value);
      });

      roadmapButton.addEventListener('click', function() {
        setMapType('roadmap');
      });

      satelliteButton.addEventListener('click', function() {
        setMapType('hybrid');
      });

      defaultLayerButton.addEventListener('click', function() {
        setVisualLayer('default');
      });

      darkLayerButton.addEventListener('click', function() {
        setVisualLayer('dark');
      });

      trafficButton.addEventListener('click', function() {
        const active = trafficButton.classList.toggle('active');
        trafficLayer.setMap(active ? map : null);
        showMapToast(active ? 'Traffic layer enabled.' : 'Traffic layer hidden.');
      });

      myLocationButton.addEventListener('click', function() {
        requestLiveLocation();
        if (liveLatLng) {
          map.panTo(liveLatLng);
          map.setZoom(15);
        }
      });

      nearestButton.addEventListener('click', selectNearestDestination);
      fitPinsButton.addEventListener('click', fitAllPins);

      map.addListener('click', function(event) {
        if (sourceMode !== 'manual') {
          return;
        }

        manualSourceLatLng = event.latLng;
        sourceInput.value = coordText(manualSourceLatLng);
        liveStatus.textContent = 'Manual source pinned';
        setRouteStatus('Manual source pinned. Choose destination and press Show Route.');

        if (!manualSourceMarker) {
          manualSourceMarker = new google.maps.Marker({
            position: manualSourceLatLng,
            map,
            draggable: true,
            title: 'Manual source',
            icon: createSourceIcon('#f97316'),
            optimized: false
          });
          manualSourceMarker.addListener('dragend', function() {
            manualSourceLatLng = manualSourceMarker.getPosition();
            sourceInput.value = coordText(manualSourceLatLng);
            setRouteStatus('Manual source moved. Press Show Route again to refresh route.');
          });
        } else {
          manualSourceMarker.setPosition(manualSourceLatLng);
        }
      });

      liveModeButton.addEventListener('click', function() {
        setSourceMode('live');
        clearRouteMetrics();
        clearDirections();
        directionsRenderer.set('directions', null);
        clearFallbackRouteLine();
      });

      manualModeButton.addEventListener('click', function() {
        setSourceMode('manual');
        clearRouteMetrics();
        clearDirections();
        directionsRenderer.set('directions', null);
        clearFallbackRouteLine();
        showMapToast('Manual mode: click the map to set your source.');
      });

      routeButton.addEventListener('click', drawRoute);
      routeModeSelect.addEventListener('change', function() {
        if (destinationSelect.value && (liveLatLng || manualSourceLatLng)) {
          drawRoute();
        }
      });
      locateButton.addEventListener('click', requestLiveLocation);
      clearDirections();
      requestLiveLocation();
    }
  </script>
  <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo rawurlencode($google_maps_api_key); ?>&callback=initCommunityMap&loading=async"></script>
  <?php include __DIR__ . '/inc/chatbot_widget.php'; ?>
</body>
</html>
