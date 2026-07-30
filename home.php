<?php
require 'inc/config.php';

$homeTopUrgentPosts = [];
$homeLeaderboard = [];

ensure_posts_category_column();
ensure_posts_sos_column();
ensure_post_urgency_votes_table();
ensure_helper_points_tables();

try {
    $stmt = $pdo->query("SELECT p.*, u.name FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC");
    $homePosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($homePosts as $post) {
        $post['resolved_category'] = !empty($post['category'])
            ? $post['category']
            : detect_post_category($post['title'] ?? '', $post['description'] ?? '');
        $post['helper_count'] = count(get_post_helpers((int) $post['id']));
        $urgency = get_post_urgency_summary((int) $post['id']);
        $post['urgent_yes'] = $urgency['urgent_yes'];
        $post['not_urgent'] = $urgency['not_urgent'];
        $post['urgency_score'] = $urgency['score'];
        $post['priority_score'] = get_post_priority_score($post);
        $homeTopUrgentPosts[] = $post;
    }

    usort($homeTopUrgentPosts, static function (array $left, array $right): int {
        $priorityComparison = ($right['priority_score'] ?? 0) <=> ($left['priority_score'] ?? 0);
        if ($priorityComparison !== 0) {
            return $priorityComparison;
        }

        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });

    $homeTopUrgentPosts = array_slice($homeTopUrgentPosts, 0, 3);
    $homeLeaderboard = get_helper_leaderboard(5);
} catch (Throwable $e) {
    $homeTopUrgentPosts = [];
    $homeLeaderboard = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Neighborhood Help Platform</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #f7f2e8;
      --surface: rgba(255, 255, 255, 0.72);
      --surface-strong: #ffffff;
      --ink: #1f2937;
      --muted: #5b6472;
      --brand: #14532d;
      --brand-soft: #d9f99d;
      --accent: #f97316;
      --line: rgba(20, 83, 45, 0.12);
      --shadow: 0 24px 60px rgba(31, 41, 55, 0.12);
      --radius-xl: 32px;
      --radius-lg: 22px;
      --radius-md: 16px;
      --max: 1180px;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: 'Manrope', sans-serif;
      color: var(--ink);
      background:
        radial-gradient(circle at top left, rgba(249, 115, 22, 0.16), transparent 28%),
        radial-gradient(circle at 85% 10%, rgba(34, 197, 94, 0.14), transparent 24%),
        linear-gradient(180deg, #fbf7f0 0%, #f6efe2 45%, #f8f4ec 100%);
      min-height: 100vh;
    }

    a {
      color: inherit;
      text-decoration: none;
    }

    img {
      display: block;
      max-width: 100%;
    }

    .page-shell {
      position: relative;
      overflow: hidden;
    }

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
      background: rgba(249, 115, 22, 0.15);
      top: 560px;
      right: -80px;
    }

    .container {
      width: min(calc(100% - 32px), var(--max));
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }

    .navbar {
      padding: 22px 0;
    }

    .nav-content {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 24px;
      padding: 14px 18px;
      background: rgba(255, 255, 255, 0.62);
      border: 1px solid rgba(255, 255, 255, 0.72);
      backdrop-filter: blur(14px);
      border-radius: 999px;
      box-shadow: 0 10px 30px rgba(20, 83, 45, 0.08);
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      min-width: 0;
    }

    .brand-badge {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      background: linear-gradient(135deg, #14532d, #22c55e);
      color: #fff;
      font-weight: 800;
      box-shadow: 0 10px 24px rgba(20, 83, 45, 0.25);
    }

    .brand-copy strong {
      display: block;
      font-size: 1rem;
      line-height: 1.1;
    }

    .brand-copy span {
      display: block;
      font-size: 0.82rem;
      color: var(--muted);
    }

    .nav-links {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .nav-links a {
      padding: 10px 14px;
      border-radius: 999px;
      color: #23402f;
      font-weight: 700;
      transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
    }

    .nav-links a:hover,
    .nav-links a:focus-visible {
      background: rgba(20, 83, 45, 0.08);
      color: var(--brand);
      transform: translateY(-1px);
      outline: none;
    }

    .nav-links .nav-cta {
      background: var(--brand);
      color: #fff;
      box-shadow: 0 12px 22px rgba(20, 83, 45, 0.18);
    }

    .nav-links .nav-cta:hover,
    .nav-links .nav-cta:focus-visible {
      background: #0f4424;
      color: #fff;
    }

    .menu-toggle {
      display: none;
      width: 46px;
      height: 46px;
      border: 0;
      border-radius: 14px;
      background: rgba(20, 83, 45, 0.08);
      color: var(--brand);
      font-size: 1.45rem;
      cursor: pointer;
    }

    .hero {
      padding: 18px 0 42px;
    }

    .hero-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr);
      gap: 28px;
      align-items: stretch;
    }

    .hero-copy,
    .hero-visual,
    .stats-strip,
    .feature-card,
    .cta-panel,
    .signal-card,
    .leader-card {
      background: var(--surface);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.7);
      box-shadow: var(--shadow);
    }

    .hero-copy {
      padding: 48px;
      border-radius: var(--radius-xl);
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(217, 249, 157, 0.56);
      color: var(--brand);
      font-size: 0.92rem;
      font-weight: 800;
      width: fit-content;
      margin-bottom: 20px;
    }

    .hero-copy h1 {
      font-size: clamp(2.6rem, 5vw, 5rem);
      line-height: 0.98;
      letter-spacing: -0.04em;
      margin-bottom: 18px;
      max-width: 11ch;
    }

    .hero-copy p {
      font-size: 1.08rem;
      line-height: 1.75;
      color: var(--muted);
      max-width: 62ch;
    }

    .hero-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
      margin-top: 28px;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      min-height: 54px;
      padding: 0 22px;
      border-radius: 999px;
      border: 0;
      cursor: pointer;
      font: inherit;
      font-weight: 800;
      transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }

    .btn:hover,
    .btn:focus-visible {
      transform: translateY(-2px);
      outline: none;
    }

    .btn-primary {
      background: linear-gradient(135deg, #14532d, #22c55e);
      color: #fff;
      box-shadow: 0 16px 28px rgba(20, 83, 45, 0.22);
    }

    .btn-secondary {
      background: rgba(255, 255, 255, 0.9);
      color: var(--brand);
      border: 1px solid rgba(20, 83, 45, 0.12);
    }

    .hero-note {
      display: flex;
      flex-wrap: wrap;
      gap: 12px 18px;
      margin-top: 26px;
      color: #355344;
      font-size: 0.95rem;
      font-weight: 700;
    }

    .hero-note span {
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .hero-visual {
      border-radius: var(--radius-xl);
      padding: 22px;
      display: grid;
      gap: 18px;
    }

    .image-card {
      overflow: hidden;
      border-radius: 24px;
      min-height: 100%;
      background:
        linear-gradient(180deg, rgba(15, 68, 36, 0.1), rgba(15, 68, 36, 0.02)),
        linear-gradient(135deg, #d9f99d, #fdba74);
      border: 1px solid rgba(20, 83, 45, 0.08);
    }

    .image-card img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      mix-blend-mode: multiply;
    }

    .floating-card {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }

    .mini-panel {
      padding: 18px;
      border-radius: 20px;
      background: var(--surface-strong);
      border: 1px solid var(--line);
    }

    .mini-panel strong {
      display: block;
      font-size: 1.7rem;
      line-height: 1;
      margin-bottom: 6px;
      color: var(--brand);
    }

    .mini-panel span {
      color: var(--muted);
      font-size: 0.92rem;
      line-height: 1.5;
    }

    .stats-strip {
      margin-top: 26px;
      border-radius: 28px;
      padding: 16px;
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 14px;
    }
    .hero-sos-banner {
      margin-top: 24px;
      display: grid;
      grid-template-columns: minmax(0, 1.15fr) minmax(280px, 0.85fr);
      gap: 18px;
    }
    .hero-sos-card,
    .hero-leader-card {
      background: rgba(255, 255, 255, 0.78);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.74);
      box-shadow: var(--shadow);
      border-radius: 28px;
      padding: 22px;
    }
    .hero-sos-card {
      background:
        linear-gradient(135deg, rgba(127, 29, 29, 0.96), rgba(220, 38, 38, 0.86)),
        rgba(255, 255, 255, 0.78);
      color: #fff;
    }
    .hero-sos-card p,
    .hero-sos-card .hero-sos-meta,
    .hero-sos-card .hero-sos-empty {
      color: rgba(255, 255, 255, 0.9);
    }
    .hero-sos-label {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.16);
      font-size: 0.8rem;
      font-weight: 800;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      margin-bottom: 12px;
    }
    .hero-sos-card h2,
    .hero-leader-card h3 {
      margin-bottom: 10px;
      line-height: 1.1;
    }
    .hero-sos-actions,
    .hero-leader-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 16px;
    }
    .hero-sos-button,
    .hero-leader-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 10px 14px;
      border-radius: 999px;
      font-weight: 800;
    }
    .hero-sos-button {
      background: #fff;
      color: #991b1b;
    }
    .hero-leader-button {
      background: rgba(20, 83, 45, 0.08);
      color: var(--brand);
    }
    .hero-sos-meta {
      margin-top: 14px;
      font-size: 0.94rem;
      line-height: 1.7;
    }
    .hero-leader-list {
      display: grid;
      gap: 12px;
      margin-top: 14px;
    }
    .hero-leader-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding: 12px 14px;
      border-radius: 18px;
      background: rgba(255, 255, 255, 0.86);
      border: 1px solid var(--line);
    }
    .hero-leader-rank,
    .hero-leader-points {
      font-weight: 800;
      color: var(--brand);
      white-space: nowrap;
    }
    .hero-leader-name {
      flex: 1;
      min-width: 0;
    }
    .hero-leader-name strong {
      display: block;
      margin-bottom: 2px;
    }
    .hero-leader-name span {
      color: var(--muted);
      font-size: 0.88rem;
    }

    .stat-card {
      padding: 18px 20px;
      border-radius: 20px;
      background: rgba(255, 255, 255, 0.78);
      border: 1px solid rgba(20, 83, 45, 0.08);
    }

    .stat-card strong {
      display: block;
      font-size: 1.5rem;
      color: var(--brand);
      margin-bottom: 6px;
    }

    .stat-card span {
      color: var(--muted);
      font-size: 0.94rem;
    }

    .section {
      padding: 26px 0;
    }

    .section-header {
      max-width: 760px;
      margin-bottom: 24px;
    }

    .section-header span {
      display: inline-block;
      color: var(--accent);
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-size: 0.78rem;
      margin-bottom: 12px;
    }

    .section-header h2 {
      font-size: clamp(2rem, 3vw, 3rem);
      line-height: 1.08;
      letter-spacing: -0.03em;
      margin-bottom: 12px;
    }

    .section-header p {
      color: var(--muted);
      font-size: 1.02rem;
      line-height: 1.75;
    }

    .feature-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 20px;
    }
    .signal-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.15fr) minmax(300px, 0.85fr);
      gap: 24px;
      align-items: start;
    }
    .urgent-list,
    .leader-home-list {
      display: grid;
      gap: 16px;
    }
    .signal-card,
    .leader-card {
      border-radius: var(--radius-xl);
      padding: 24px;
    }
    .signal-card h3,
    .leader-card h3 {
      margin-bottom: 12px;
      font-size: 1.15rem;
    }
    .signal-card p,
    .leader-card p,
    .signal-meta,
    .leader-home-item span {
      color: var(--muted);
      line-height: 1.7;
    }
    .signal-top {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 12px;
    }
    .signal-badge,
    .signal-priority {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 800;
      text-transform: uppercase;
    }
    .signal-badge {
      background: rgba(185, 28, 28, 0.12);
      color: #b91c1c;
    }
    .signal-priority {
      background: rgba(249, 115, 22, 0.12);
      color: #c2410c;
    }
    .signal-link {
      display: inline-flex;
      margin-top: 16px;
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(20, 83, 45, 0.08);
      color: var(--brand);
      font-weight: 700;
    }
    .leader-home-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 14px 16px;
      border-radius: 18px;
      background: rgba(255, 255, 255, 0.88);
      border: 1px solid var(--line);
    }
    .leader-home-name {
      flex: 1;
      min-width: 0;
    }
    .leader-home-name strong {
      display: block;
      margin-bottom: 4px;
    }
    .leader-home-rank,
    .leader-home-points {
      font-weight: 800;
      color: var(--brand);
      white-space: nowrap;
    }

    .feature-card {
      border-radius: 26px;
      padding: 28px;
      transform: translateY(18px);
      opacity: 0;
      transition: transform 0.5s ease, opacity 0.5s ease;
    }

    .feature-card.show {
      transform: translateY(0);
      opacity: 1;
    }

    .feature-icon {
      width: 54px;
      height: 54px;
      display: grid;
      place-items: center;
      border-radius: 18px;
      background: linear-gradient(135deg, rgba(20, 83, 45, 0.12), rgba(34, 197, 94, 0.18));
      color: var(--brand);
      font-weight: 800;
      margin-bottom: 18px;
    }

    .feature-card h3 {
      font-size: 1.25rem;
      margin-bottom: 10px;
    }

    .feature-card p {
      color: var(--muted);
      line-height: 1.7;
      margin-bottom: 14px;
    }

    .feature-card a {
      color: var(--brand);
      font-weight: 800;
    }

    .steps {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 18px;
      margin-top: 10px;
    }

    .step {
      padding: 22px 24px;
      border-radius: 24px;
      background: rgba(255, 255, 255, 0.72);
      border: 1px solid rgba(20, 83, 45, 0.08);
      box-shadow: 0 14px 34px rgba(31, 41, 55, 0.08);
    }

    .step-number {
      width: 38px;
      height: 38px;
      border-radius: 999px;
      display: grid;
      place-items: center;
      background: var(--brand);
      color: #fff;
      font-weight: 800;
      margin-bottom: 16px;
    }

    .step h3 {
      margin-bottom: 10px;
      font-size: 1.1rem;
    }

    .step p {
      color: var(--muted);
      line-height: 1.7;
    }

    .cta-panel {
      margin: 18px 0 54px;
      border-radius: 32px;
      padding: 34px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
    }

    .cta-panel h2 {
      font-size: clamp(1.9rem, 3vw, 2.9rem);
      line-height: 1.08;
      margin-bottom: 10px;
    }

    .cta-panel p {
      color: var(--muted);
      max-width: 56ch;
      line-height: 1.7;
    }

    .footer {
      padding: 0 0 34px;
    }

    .footer-card {
      border-radius: 26px;
      background: #173927;
      color: rgba(255, 255, 255, 0.88);
      padding: 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
    }

    .footer-card strong {
      display: block;
      color: #fff;
      margin-bottom: 6px;
      font-size: 1rem;
    }

    .footer-card span {
      color: rgba(255, 255, 255, 0.72);
      font-size: 0.94rem;
    }

    .footer-links {
      display: flex;
      gap: 18px;
      flex-wrap: wrap;
    }

    .footer-links a {
      color: rgba(255, 255, 255, 0.88);
      font-weight: 700;
    }

    @media (max-width: 980px) {
      .nav-content {
        border-radius: 28px;
        align-items: flex-start;
        flex-wrap: wrap;
      }

      .menu-toggle {
        display: inline-grid;
        place-items: center;
        margin-left: auto;
      }

      .nav-links {
        width: 100%;
        display: none;
        flex-direction: column;
        align-items: stretch;
        padding-top: 8px;
      }

      .nav-links.active {
        display: flex;
      }

      .hero-grid,
      .hero-sos-banner,
      .feature-grid,
      .signal-grid,
      .steps,
      .stats-strip,
      .cta-panel {
        grid-template-columns: 1fr;
      }

      .cta-panel,
      .footer-card {
        flex-direction: column;
        align-items: flex-start;
      }
    }

    @media (max-width: 720px) {
      .hero-copy,
      .hero-visual,
      .hero-sos-card,
      .hero-leader-card,
      .feature-card,
      .signal-card,
      .leader-card,
      .cta-panel {
        padding: 24px;
      }

      .hero-copy h1 {
        max-width: none;
      }

      .floating-card {
        grid-template-columns: 1fr;
      }

      .hero-actions .btn {
        width: 100%;
      }

      .stats-strip {
        padding: 12px;
      }

      .footer-card {
        padding: 20px;
      }
    }
  </style>
</head>
<body>
  <div class="page-shell">
    <header class="navbar">
      <div class="container">
        <div class="nav-content">
          <a class="brand" href="#top">
            <span class="brand-badge">NH</span>
            <span class="brand-copy">
              <strong>NeighborHelp</strong>
              <span>Local support, faster connections</span>
            </span>
          </a>
          <button class="menu-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="mainNav">
            &#9776;
          </button>
          <nav class="nav-links" id="mainNav">
            <a href="#features">Features</a>
            <a href="#how-it-works">How it Works</a>
            <a href="community.php">Community Feed</a>
            <a href="login.php">Login</a>
            <a class="nav-cta" href="register.php">Join Free</a>
          </nav>
        </div>
      </div>
    </header>

    <main id="top">
      <section class="hero">
        <div class="container">
          <div class="hero-grid">
            <div class="hero-copy">
              <span class="eyebrow">Built for real neighborhoods</span>
              <h1>Help your area feel smaller, safer, and more connected.</h1>
              <p>
                NeighborHelp gives people a simple place to ask for support, offer practical help,
                and spot local needs before they turn into bigger problems.
              </p>
              <div class="hero-actions">
                <button class="btn btn-primary" id="joinBtn" type="button">Create an Account</button>
                <button class="btn btn-secondary" id="offerBtn" type="button">Start a Post</button>
              </div>
              <div class="hero-note">
                <span>Verified local accounts</span>
                <span>Location-aware requests</span>
                <span>Quick community updates</span>
              </div>
            </div>

            <div class="hero-visual">
              <div class="image-card">
                <img src="home.png" alt="Illustration representing neighborhood support" />
              </div>
              <div class="floating-card">
                <div class="mini-panel">
                  <strong>Fast</strong>
                  <span>Share requests and offers in just a few taps.</span>
                </div>
                <div class="mini-panel">
                  <strong>Local</strong>
                  <span>Keep help grounded in your own area and streets.</span>
                </div>
              </div>
            </div>
          </div>

          <div class="stats-strip" aria-label="Platform highlights">
            <div class="stat-card">
              <strong>1 place</strong>
              <span>for requests, offers, and neighborhood updates</span>
            </div>
            <div class="stat-card">
              <strong>5 areas</strong>
              <span>supported out of the box for quick local filtering</span>
            </div>
            <div class="stat-card">
              <strong>Map-based</strong>
              <span>posting so helpers can understand where support is needed</span>
            </div>
            <div class="stat-card">
              <strong>Community-led</strong>
              <span>coordination designed around neighbors helping neighbors</span>
            </div>
          </div>

          <div class="hero-sos-banner">
            <section class="hero-sos-card">
              <span class="hero-sos-label">Emergency SOS</span>
              <?php if (!empty($homeTopUrgentPosts)): ?>
                <?php $topSignal = $homeTopUrgentPosts[0]; ?>
                <h2>Top urgent issue is now visible right here.</h2>
                <p><?php echo e($topSignal['title']); ?></p>
                <div class="hero-sos-meta">
                  <?php if (!empty($topSignal['is_sos'])): ?>
                    SOS marked post •
                  <?php endif; ?>
                  <?php echo e($topSignal['resolved_category']); ?> issue • <?php echo e((string) $topSignal['urgent_yes']); ?> urgent votes • <?php echo e((string) $topSignal['helper_count']); ?> helpers
                </div>
                <div class="hero-sos-actions">
                  <a href="view_post.php?id=<?php echo (int) $topSignal['id']; ?>" class="hero-sos-button">Open urgent issue</a>
                  <a href="new_post.php" class="hero-sos-button">Create SOS post</a>
                </div>
              <?php else: ?>
                <h2>Emergency SOS alerts appear here on the homepage.</h2>
                <p class="hero-sos-empty">Abhi tak koi SOS ya high-priority issue nahi hai. Jaise hi emergency post banegi ya urgent votes milenge, woh yahan top par show hogi.</p>
                <div class="hero-sos-actions">
                  <a href="new_post.php" class="hero-sos-button">Create SOS post</a>
                  <a href="community.php" class="hero-sos-button">Open community feed</a>
                </div>
              <?php endif; ?>
            </section>

            <aside class="hero-leader-card">
              <h3>Helper Leaderboard</h3>
              <p>Top helpers ko yahin quick view me dekho.</p>
              <div class="hero-leader-list">
                <?php if (empty($homeLeaderboard)): ?>
                  <div class="hero-leader-item">
                    <div class="hero-leader-name">
                      <strong>No helpers ranked yet</strong>
                      <span>First volunteer will appear here.</span>
                    </div>
                  </div>
                <?php else: ?>
                  <?php foreach (array_slice($homeLeaderboard, 0, 3) as $index => $leader): ?>
                    <div class="hero-leader-item">
                      <div class="hero-leader-rank">#<?php echo (int) $index + 1; ?></div>
                      <div class="hero-leader-name">
                        <strong><?php echo e($leader['name']); ?></strong>
                        <span><?php echo e($leader['area']); ?></span>
                      </div>
                      <div class="hero-leader-points"><?php echo e((string) $leader['total_points']); ?> pts</div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <div class="hero-leader-actions">
                <a href="community.php" class="hero-leader-button">Open full leaderboard</a>
              </div>
            </aside>
          </div>
        </div>
      </section>

      <section class="section" id="features">
        <div class="container">
          <div class="section-header">
            <span>Features</span>
            <h2>A cleaner path from “I need help” to “I can help.”</h2>
            <p>
              The homepage now points people straight into the real actions your app supports,
              while keeping the message focused on trust, speed, and neighborhood collaboration.
            </p>
          </div>

          <div class="feature-grid">
            <article class="feature-card">
              <div class="feature-icon">01</div>
              <h3>Create a request or offer</h3>
              <p>Residents can publish a post with a title, details, image, and location so others know exactly what is needed.</p>
              <a href="new_post.php">Open posting flow</a>
            </article>

            <article class="feature-card">
              <div class="feature-icon">02</div>
              <h3>Browse the community feed</h3>
              <p>People can quickly scan current neighborhood needs, recent updates, and the status of local problem-solving.</p>
              <a href="community.php">View live feed</a>
            </article>

            <article class="feature-card">
              <div class="feature-icon">03</div>
              <h3>Find nearby issues on a map</h3>
              <p>Location-aware views make it easier to understand urgency, proximity, and the best way to step in and help.</p>
              <a href="map_posts.php">See map view</a>
            </article>
          </div>
        </div>
      </section>

      <section class="section" id="how-it-works">
        <div class="container">
          <div class="section-header">
            <span>How It Works</span>
            <h2>Simple enough for anyone in the neighborhood to use.</h2>
            <p>
              The flow is straightforward by design: join, post, respond, and keep everyone updated as a request moves forward.
            </p>
          </div>

          <div class="steps">
            <article class="step">
              <div class="step-number">1</div>
              <h3>Join your local network</h3>
              <p>Create an account, verify your email, and enter the area you belong to so your feed starts local.</p>
            </article>

            <article class="step">
              <div class="step-number">2</div>
              <h3>Share a need or an offer</h3>
              <p>Post what is happening, attach an image if useful, and pinpoint the location so people understand the context.</p>
            </article>

            <article class="step">
              <div class="step-number">3</div>
              <h3>Respond and coordinate</h3>
              <p>Neighbors can view updates, step in faster, and keep requests moving from open to in progress to solved.</p>
            </article>
          </div>
        </div>
      </section>

      <section class="section" id="live-signals">
        <div class="container">
          <div class="section-header">
            <span>Live Signals</span>
            <h2>SOS alerts, important issues, and helper leaderboard ab homepage par bhi visible hain.</h2>
            <p>
              Agar tum seedha homepage dekh rahe ho, to yahin se top urgent reports aur active helpers ko spot kar sakte ho.
            </p>
          </div>

          <div class="signal-grid">
            <div class="urgent-list">
              <?php if (empty($homeTopUrgentPosts)): ?>
                <article class="signal-card">
                  <h3>No urgent issues yet</h3>
                  <p>Jaise hi log SOS report create karenge ya urgent votes denge, top priority issues yahan show honge.</p>
                  <a href="new_post.php" class="signal-link">Create first urgent post</a>
                </article>
              <?php else: ?>
                <?php foreach ($homeTopUrgentPosts as $urgentPost): ?>
                  <?php
                    $urgentDescription = trim((string) ($urgentPost['description'] ?? ''));
                    if ($urgentDescription === '') {
                        $urgentPreview = 'Open the post to see full context and support options.';
                    } elseif (function_exists('mb_strimwidth')) {
                        $urgentPreview = mb_strimwidth($urgentDescription, 0, 130, '...');
                    } else {
                        $urgentPreview = strlen($urgentDescription) > 130 ? substr($urgentDescription, 0, 127) . '...' : $urgentDescription;
                    }
                  ?>
                  <article class="signal-card">
                    <div class="signal-top">
                      <?php if (!empty($urgentPost['is_sos'])): ?>
                        <span class="signal-badge">SOS Alert</span>
                      <?php endif; ?>
                      <span class="signal-priority">Priority <?php echo e((string) $urgentPost['urgency_score']); ?></span>
                    </div>
                    <h3><?php echo e($urgentPost['title']); ?></h3>
                    <p><?php echo e($urgentPreview); ?></p>
                    <div class="signal-meta">
                      <?php echo e($urgentPost['resolved_category']); ?> issue • <?php echo e((string) $urgentPost['urgent_yes']); ?> urgent votes • <?php echo e((string) $urgentPost['helper_count']); ?> helpers
                    </div>
                    <a href="view_post.php?id=<?php echo (int) $urgentPost['id']; ?>" class="signal-link">Open issue</a>
                  </article>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <aside class="leader-card">
              <h3>Helper Leaderboard</h3>
              <p>Jo log help offer karte hain unko points milte hain, aur top contributors yahan show hote hain.</p>
              <div class="leader-home-list" style="margin-top:16px;">
                <?php if (empty($homeLeaderboard)): ?>
                  <div class="leader-home-item">
                    <div class="leader-home-name">
                      <strong>No ranking yet</strong>
                      <span>First helper will appear here.</span>
                    </div>
                  </div>
                <?php else: ?>
                  <?php foreach ($homeLeaderboard as $index => $leader): ?>
                    <div class="leader-home-item">
                      <div class="leader-home-rank">#<?php echo (int) $index + 1; ?></div>
                      <div class="leader-home-name">
                        <strong><?php echo e($leader['name']); ?></strong>
                        <span><?php echo e($leader['area']); ?></span>
                      </div>
                      <div class="leader-home-points"><?php echo e((string) $leader['total_points']); ?> pts</div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <a href="community.php" class="signal-link">Open full community feed</a>
            </aside>
          </div>
        </div>
      </section>

      <section class="section">
        <div class="container">
          <div class="cta-panel">
            <div>
              <h2>Ready to make the first connection in your neighborhood?</h2>
              <p>Start with a profile if you are new, or jump straight into the community feed if you already have an account.</p>
            </div>
            <div class="hero-actions">
              <a class="btn btn-primary" href="register.php">Join NeighborHelp</a>
              <a class="btn btn-secondary" href="community.php">Explore Posts</a>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer class="footer">
      <div class="container">
        <div class="footer-card">
          <div>
            <strong>NeighborHelp</strong>
            <span>&copy; <?php echo date('Y'); ?> Building stronger communities through local action.</span>
          </div>
          <div class="footer-links">
            <a href="#top">Home</a>
            <a href="#features">Features</a>
            <a href="community.php">Community</a>
            <a href="login.php">Login</a>
          </div>
        </div>
      </div>
    </footer>
  </div>

  <script>
    const menuToggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelector('.nav-links');

    menuToggle.addEventListener('click', () => {
      const isActive = navLinks.classList.toggle('active');
      menuToggle.setAttribute('aria-expanded', isActive ? 'true' : 'false');
    });

    document.getElementById('joinBtn').addEventListener('click', () => {
      window.location.href = 'register.php';
    });

    document.getElementById('offerBtn').addEventListener('click', () => {
      window.location.href = 'new_post.php';
    });

    const featureCards = document.querySelectorAll('.feature-card');
    const revealCards = () => {
      const triggerBottom = window.innerHeight * 0.88;
      featureCards.forEach((card) => {
        const boxTop = card.getBoundingClientRect().top;
        if (boxTop < triggerBottom) {
          card.classList.add('show');
        }
      });
    };

    window.addEventListener('scroll', revealCards, { passive: true });
    revealCards();
  </script>
  <?php include __DIR__ . '/inc/chatbot_widget.php'; ?>
</body>
</html>
