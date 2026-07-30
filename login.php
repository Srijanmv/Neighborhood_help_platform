<?php
require 'inc/config.php';
ensure_users_verification_columns();

$err = '';
$emailInput = '';
$hasVerifiedColumn = db_column_exists('users', 'is_verified');
$canUseGoogle = $google_button_enabled;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $emailInput = $email;

    if ($email && $password) {
        $selectFields = $hasVerifiedColumn ? 'id, password, is_verified' : 'id, password';
        $stmt = $pdo->prepare("SELECT $selectFields FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if ($hasVerifiedColumn && (int) $user['is_verified'] === 0) {
                $err = 'Please verify your email first.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                header('Location: community.php');
                exit;
            }
        } else {
            $err = 'Invalid credentials.';
        }
    } else {
        $err = 'Enter email and password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - Neighborhood Help</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #f6f1e7;
      --card: rgba(255, 255, 255, 0.82);
      --ink: #20302a;
      --muted: #62706a;
      --line: rgba(20, 83, 45, 0.12);
      --brand: #14532d;
      --accent: #f97316;
      --danger-bg: #fef2f2;
      --danger-text: #b91c1c;
      --shadow: 0 24px 60px rgba(32, 48, 42, 0.14);
      --radius-xl: 30px;
      --radius-lg: 22px;
      --radius-md: 14px;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      min-height: 100vh;
      font-family: 'Manrope', sans-serif;
      color: var(--ink);
      background:
        radial-gradient(circle at top left, rgba(249, 115, 22, 0.18), transparent 24%),
        radial-gradient(circle at 90% 10%, rgba(34, 197, 94, 0.14), transparent 22%),
        linear-gradient(180deg, #fbf7ef 0%, #f7f1e7 100%);
      padding: 28px 16px;
    }

    a {
      color: inherit;
      text-decoration: none;
    }

    .shell {
      width: min(1120px, 100%);
      margin: 0 auto;
    }

    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      padding: 16px 18px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.7);
      border: 1px solid rgba(255, 255, 255, 0.72);
      backdrop-filter: blur(14px);
      box-shadow: 0 12px 26px rgba(20, 83, 45, 0.08);
      margin-bottom: 24px;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .brand-mark {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      background: linear-gradient(135deg, #14532d, #22c55e);
      color: #fff;
      display: grid;
      place-items: center;
      font-weight: 800;
    }

    .brand-copy strong {
      display: block;
      line-height: 1.05;
    }

    .brand-copy span {
      display: block;
      color: var(--muted);
      font-size: 0.88rem;
    }

    .topbar-links {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    .topbar-links a {
      padding: 10px 14px;
      border-radius: 999px;
      font-weight: 700;
    }

    .topbar-links a:last-child {
      background: var(--brand);
      color: #fff;
    }

    .layout {
      display: grid;
      grid-template-columns: minmax(0, 0.95fr) minmax(340px, 1.05fr);
      gap: 24px;
      align-items: start;
    }

    .hero-panel,
    .form-panel {
      background: var(--card);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.75);
      box-shadow: var(--shadow);
      border-radius: var(--radius-xl);
    }

    .hero-panel {
      padding: 34px;
      position: sticky;
      top: 24px;
    }

    .eyebrow {
      display: inline-block;
      padding: 10px 14px;
      background: rgba(217, 249, 157, 0.64);
      color: var(--brand);
      border-radius: 999px;
      font-size: 0.86rem;
      font-weight: 800;
      margin-bottom: 18px;
    }

    .hero-panel h1 {
      font-size: clamp(2.1rem, 4vw, 3.4rem);
      line-height: 0.98;
      letter-spacing: -0.04em;
      margin-bottom: 16px;
    }

    .hero-panel p {
      color: var(--muted);
      line-height: 1.8;
      margin-bottom: 24px;
    }

    .hero-list {
      display: grid;
      gap: 14px;
    }

    .hero-item {
      padding: 18px;
      border-radius: var(--radius-lg);
      background: rgba(255, 255, 255, 0.78);
      border: 1px solid var(--line);
    }

    .hero-item strong {
      display: block;
      margin-bottom: 8px;
    }

    .hero-item span {
      color: var(--muted);
      line-height: 1.7;
      font-size: 0.96rem;
    }

    .form-panel {
      padding: 30px;
    }

    .panel-heading {
      margin-bottom: 20px;
    }

    .panel-heading h2 {
      font-size: 2rem;
      margin-bottom: 8px;
    }

    .panel-heading p {
      color: var(--muted);
      line-height: 1.7;
    }

    .alert {
      border-radius: 18px;
      padding: 14px 16px;
      margin-bottom: 18px;
      line-height: 1.6;
      background: var(--danger-bg);
      color: var(--danger-text);
      border: 1px solid rgba(185, 28, 28, 0.12);
    }

    .field {
      display: grid;
      gap: 8px;
      margin-bottom: 16px;
    }

    label {
      font-weight: 700;
      font-size: 0.95rem;
    }

    input {
      width: 100%;
      border: 1px solid var(--line);
      background: #fff;
      border-radius: var(--radius-md);
      padding: 12px 14px;
      color: var(--ink);
      outline: none;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    input:focus {
      border-color: rgba(20, 83, 45, 0.45);
      box-shadow: 0 0 0 4px rgba(20, 83, 45, 0.12);
    }

    .submit-btn {
      width: 100%;
      border: 0;
      border-radius: 999px;
      padding: 12px 20px;
      background: linear-gradient(120deg, #14532d, #15803d);
      color: #fff;
      font-weight: 800;
      font-size: 1rem;
      cursor: pointer;
      box-shadow: 0 14px 26px rgba(20, 83, 45, 0.24);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      margin-top: 4px;
    }

    .submit-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 16px 30px rgba(20, 83, 45, 0.28);
    }

    .links {
      margin-top: 18px;
      display: flex;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      font-size: 0.95rem;
    }

    .divider {
      display: flex;
      align-items: center;
      gap: 12px;
      color: var(--muted);
      font-size: 0.92rem;
      margin: 24px 0 20px;
    }

    .divider::before,
    .divider::after {
      content: "";
      height: 1px;
      background: rgba(20, 83, 45, 0.12);
      flex: 1;
    }

    .social-card {
      margin-top: 18px;
      background: rgba(255, 255, 255, 0.84);
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      padding: 20px;
      display: grid;
      gap: 16px;
    }

    .social-card h3 {
      font-size: 1.08rem;
    }

    .social-card p,
    .social-helper {
      color: var(--muted);
      line-height: 1.7;
    }

    .social-helper {
      font-size: 0.92rem;
    }

    .social-message {
      display: none;
      border-radius: 14px;
      padding: 12px 14px;
      font-size: 0.94rem;
      line-height: 1.5;
    }

    .social-message.error {
      display: block;
      background: var(--danger-bg);
      color: var(--danger-text);
    }

    .social-message.success {
      display: block;
      background: #ecfdf3;
      color: #166534;
    }

    .social-fallback {
      width: 100%;
      min-height: 52px;
      border: 0;
      border-radius: 999px;
      font: inherit;
      font-weight: 800;
      cursor: pointer;
      background: linear-gradient(120deg, #d1d5db, #e5e7eb);
      color: #1f2937;
    }

    .links a {
      color: var(--brand);
      font-weight: 800;
    }

    @media (max-width: 960px) {
      .layout {
        grid-template-columns: 1fr;
      }

      .hero-panel {
        position: static;
      }
    }

    @media (max-width: 640px) {
      .topbar {
        border-radius: 28px;
        flex-direction: column;
        align-items: flex-start;
      }

      .form-panel,
      .hero-panel {
        padding: 22px;
      }
    }
  </style>
</head>
<body>
  <div class="shell">
    <header class="topbar">
      <a href="home.php" class="brand">
        <span class="brand-mark">NH</span>
        <span class="brand-copy">
          <strong>NeighborHelp</strong>
          <span>Welcome back</span>
        </span>
      </a>
      <div class="topbar-links">
        <a href="community.php">Community</a>
        <a href="register.php">Register</a>
      </div>
    </header>

    <div class="layout">
      <aside class="hero-panel">
        <span class="eyebrow">Trusted local network</span>
        <h1>Login and help your neighborhood today.</h1>
        <p>
          Access your area feed, post community issues, and track progress with nearby residents in one place.
        </p>
        <div class="hero-list">
          <div class="hero-item">
            <strong>Stay connected</strong>
            <span>Get updates from your area without missing important local posts.</span>
          </div>
          <div class="hero-item">
            <strong>Secure sign-in</strong>
            <span>Your account uses password hashing and optional email verification checks.</span>
          </div>
          <div class="hero-item">
            <strong>Faster reporting</strong>
            <span>Login once and quickly share road, safety, or utility concerns anytime.</span>
          </div>
        </div>
      </aside>

      <main class="form-panel">
        <div class="panel-heading">
          <h2>Login to your account</h2>
          <p>Use your registered email and password to continue.</p>
        </div>

        <?php if ($err): ?>
          <div class="alert"><?php echo e($err); ?></div>
        <?php endif; ?>

        <form method="post">
          <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?php echo e($emailInput); ?>" required>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>
          </div>

          <button class="submit-btn" type="submit">Login</button>
        </form>

        <div class="divider">or continue with</div>

        <section class="social-card">
          <div>
            <h3>Google</h3>
            <p>Use the same Google account you used during signup. Returning users can log in directly.</p>
          </div>
          <div id="googleMessage" class="social-message" aria-live="polite"></div>
          <?php if ($canUseGoogle): ?>
            <div id="googleButton"></div>
            <p class="social-helper">If this Google account is new, complete signup from the register page first.</p>
          <?php else: ?>
            <button class="social-fallback" type="button" disabled>Google sign-in needs a client ID in inc/config.php</button>
          <?php endif; ?>
        </section>

        <div class="links">
          <a href="register.php">Create new account</a>
          <a href="verify_otp.php">Verify OTP</a>
        </div>
      </main>
    </div>
  </div>
  <?php if ($canUseGoogle): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
  <?php endif; ?>
  <script>
    const canUseGoogle = <?php echo $canUseGoogle ? 'true' : 'false'; ?>;
    const googleClientId = <?php echo json_encode($google_client_id); ?>;

    const googleMessage = document.getElementById('googleMessage');

    function setMessage(el, type, text) {
      if (!el) return;
      el.className = 'social-message ' + type;
      el.textContent = text;
    }

    function clearMessage(el) {
      if (!el) return;
      el.className = 'social-message';
      el.textContent = '';
    }

    function postSocial(endpoint, payload, okEl, failPrefix) {
      return fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(async (res) => {
          const data = await res.json();
          if (!res.ok || !data.success) {
            throw new Error(data.message || failPrefix + ' failed.');
          }
          setMessage(okEl, 'success', data.message || 'Sign-in successful. Redirecting...');
          window.location.href = data.redirect || 'community.php';
        });
    }

    window.addEventListener('load', () => {
      if (canUseGoogle) {
        if (!window.google || !window.google.accounts || !window.google.accounts.id) {
          setMessage(googleMessage, 'error', 'Google sign-in script did not load.');
        } else {
          window.google.accounts.id.initialize({
            client_id: googleClientId,
            callback: (response) => {
              clearMessage(googleMessage);
              postSocial('google_register.php', {
                credential: response.credential
              }, googleMessage, 'Google sign-in')
                .catch((err) => setMessage(googleMessage, 'error', err.message));
            }
          });

          window.google.accounts.id.renderButton(
            document.getElementById('googleButton'),
            { theme: 'outline', size: 'large', shape: 'pill', width: 320, text: 'continue_with' }
          );
        }
      }

    });
  </script>
  <?php include __DIR__ . '/inc/chatbot_widget.php'; ?>
</body>
</html>
