<?php
require 'inc/config.php';

function hard_redirect($url) {
    header('Location: ' . $url);
    header('Refresh: 0;url=' . $url);
    echo '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . e($url) . '"><script>window.location.href=' . json_encode($url) . ';</script></head><body>Redirecting... <a href="' . e($url) . '">Continue</a></body></html>';
    exit;
}

$errors = [];
$success = '';
$form = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'area' => ''
];

$areas = ['Central', 'North', 'South', 'East', 'West'];
$canUseGoogle = $google_button_enabled;
ensure_users_verification_columns();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name'] = trim($_POST['name'] ?? '');
    $form['email'] = trim($_POST['email'] ?? '');
    $form['phone'] = trim($_POST['phone'] ?? '');
    $form['area'] = trim($_POST['area'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!$form['name'] || !$form['email'] || !$form['area'] || !$password || !$password2) {
        $errors[] = 'Please fill all required fields.';
    }

    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!in_array($form['area'], $areas, true)) {
        $errors[] = 'Please choose a valid area.';
    }

    if ($password !== $password2) {
        $errors[] = 'Passwords do not match.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id, " . (db_column_exists('users', 'is_verified') ? "is_verified" : "1 as is_verified") . " FROM users WHERE email = ?");
        $stmt->execute([$form['email']]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            $existingUserId = (int) $existingUser['id'];
            $isVerified = (int) ($existingUser['is_verified'] ?? 1) === 1;

            if ($isVerified) {
                $errors[] = 'Email already registered.';
            } else {
                $otp = (string) random_int(100000, 999999);

                if (db_column_exists('users', 'otp') && db_column_exists('users', 'is_verified')) {
                    $otpStmt = $pdo->prepare("UPDATE users SET otp = ?, is_verified = 0 WHERE id = ?");
                    $otpStmt->execute([$otp, $existingUserId]);
                }

                $_SESSION['pending_verify_user_id'] = $existingUserId;
                $_SESSION['pending_verify_email'] = $form['email'];
                $_SESSION['pending_verify_otp'] = $otp;

                if (send_verification_otp_email($form['email'], $otp)) {
                    $_SESSION['otp_notice'] = 'Your account is pending verification. Fresh OTP sent to email.';
                } else {
                    $mailErr = otp_mail_last_error();
                    $_SESSION['otp_notice'] = 'Could not send OTP right now. Use Resend OTP on next page.' .
                        ($mailErr ? (' Reason: ' . $mailErr) : '');
                }

                hard_redirect('verify_otp.php');
            }
        } else {
            $columns = ['name', 'email', 'phone', 'area', 'password'];
            $values = [$form['name'], $form['email'], $form['phone'], $form['area'], password_hash($password, PASSWORD_DEFAULT)];

            if (db_column_exists('users', 'otp')) {
                $columns[] = 'otp';
                $values[] = null;
            }

            if (db_column_exists('users', 'is_verified')) {
                $columns[] = 'is_verified';
                $values[] = 0;
            }

            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $sql = "INSERT INTO users (" . implode(',', $columns) . ") VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);

            $newUserId = (int) $pdo->lastInsertId();
            $hasOtpColumn = db_column_exists('users', 'otp');
            $hasVerifiedColumn = db_column_exists('users', 'is_verified');
            $otp = (string) random_int(100000, 999999);

            if ($hasOtpColumn && $hasVerifiedColumn) {
                $otpStmt = $pdo->prepare("UPDATE users SET otp = ?, is_verified = 0 WHERE id = ?");
                $otpStmt->execute([$otp, $newUserId]);
            }

            $_SESSION['pending_verify_user_id'] = $newUserId;
            $_SESSION['pending_verify_email'] = $form['email'];
            $_SESSION['pending_verify_otp'] = $otp;
            if (send_verification_otp_email($form['email'], $otp)) {
                $_SESSION['otp_notice'] = 'OTP sent to your email. Please verify to continue.';
            } else {
                $mailErr = otp_mail_last_error();
                $_SESSION['otp_notice'] = 'Account created but OTP email could not be sent. Click resend OTP on verification page.' .
                    ($mailErr ? (' Reason: ' . $mailErr) : '');
            }

            hard_redirect('verify_otp.php');
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register - Neighborhood Help</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #f6f1e7;
      --card: rgba(255, 255, 255, 0.82);
      --card-strong: #ffffff;
      --ink: #20302a;
      --muted: #62706a;
      --line: rgba(20, 83, 45, 0.12);
      --brand: #14532d;
      --accent: #f97316;
      --success-bg: #ecfdf3;
      --success-text: #166534;
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
      grid-template-columns: minmax(0, 0.95fr) minmax(360px, 1.05fr);
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
      font-size: clamp(2.2rem, 4vw, 3.8rem);
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

    .alert,
    .notice {
      border-radius: 18px;
      padding: 14px 16px;
      margin-bottom: 18px;
      line-height: 1.6;
    }

    .alert {
      background: var(--danger-bg);
      color: var(--danger-text);
      border: 1px solid rgba(185, 28, 28, 0.12);
    }

    .notice {
      background: var(--success-bg);
      color: var(--success-text);
      border: 1px solid rgba(22, 101, 52, 0.12);
    }

    .register-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 16px;
    }

    .field,
    .google-setup {
      display: grid;
      gap: 8px;
    }

    .field.full {
      grid-column: 1 / -1;
    }

    label {
      font-weight: 700;
      font-size: 0.95rem;
    }

    input,
    select {
      width: 100%;
      border: 1px solid rgba(20, 83, 45, 0.16);
      border-radius: 14px;
      background: rgba(255, 255, 255, 0.92);
      min-height: 50px;
      padding: 0 14px;
      font: inherit;
      color: var(--ink);
    }

    input:focus,
    select:focus {
      outline: 2px solid rgba(34, 197, 94, 0.28);
      border-color: rgba(20, 83, 45, 0.26);
    }

    .submit-btn,
    .google-fallback {
      width: 100%;
      min-height: 54px;
      border: 0;
      border-radius: 999px;
      font: inherit;
      font-weight: 800;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .submit-btn:hover,
    .google-fallback:hover {
      transform: translateY(-1px);
    }

    .submit-btn {
      margin-top: 8px;
      background: linear-gradient(135deg, #14532d, #22c55e);
      color: #fff;
      box-shadow: 0 14px 30px rgba(20, 83, 45, 0.22);
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

    .google-card {
      background: rgba(255, 255, 255, 0.84);
      border: 1px solid var(--line);
      border-radius: 24px;
      padding: 20px;
      display: grid;
      gap: 16px;
    }

    .google-card h3 {
      font-size: 1.15rem;
    }

    .google-card p,
    .helper,
    .signin-link {
      color: var(--muted);
      line-height: 1.7;
    }

    .helper {
      font-size: 0.9rem;
    }

    .google-message {
      display: none;
      border-radius: 14px;
      padding: 12px 14px;
      font-size: 0.94rem;
      line-height: 1.5;
    }

    .google-message.error {
      display: block;
      background: var(--danger-bg);
      color: var(--danger-text);
    }

    .google-message.success {
      display: block;
      background: var(--success-bg);
      color: var(--success-text);
    }

    .signin-link {
      margin-top: 20px;
      text-align: center;
    }

    .signin-link a {
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
      .register-grid {
        grid-template-columns: 1fr;
      }

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
          <span>Register your local account</span>
        </span>
      </a>
      <div class="topbar-links">
        <a href="community.php">Community</a>
        <a href="login.php">Login</a>
      </div>
    </header>

    <div class="layout">
      <aside class="hero-panel">
        <span class="eyebrow">Safer signup flow</span>
        <h1>Join your area in a few minutes.</h1>
        <p>
          Create a standard account with email and password, or continue with Google/Apple.
          Your website will never ask users to type social account passwords into your own form.
        </p>
        <div class="hero-list">
          <div class="hero-item">
            <strong>Email signup still works</strong>
            <span>Residents can create a regular NeighborHelp account with their own password.</span>
          </div>
          <div class="hero-item">
            <strong>Google signup is safer</strong>
            <span>Google handles the account authentication and your app receives verified profile details.</span>
          </div>
          <div class="hero-item">
            <strong>Area stays part of registration</strong>
            <span>Users still choose the neighborhood area they belong to, so your local feed remains useful.</span>
          </div>
        </div>
      </aside>

      <main class="form-panel">
        <div class="panel-heading">
          <h2>Create your account</h2>
          <p>Register with email, or use Google for a faster first login.</p>
        </div>

        <?php if ($errors): ?>
          <div class="alert"><?php echo e(implode(' ', $errors)); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="notice"><?php echo e($success); ?></div>
        <?php endif; ?>

        <form method="post">
          <div class="register-grid">
            <div class="field">
              <label for="name">Full Name</label>
              <input id="name" name="name" value="<?php echo e($form['name']); ?>" required>
            </div>

            <div class="field">
              <label for="email">Email</label>
              <input id="email" name="email" type="email" value="<?php echo e($form['email']); ?>" required>
            </div>

            <div class="field">
              <label for="phone">Phone</label>
              <input id="phone" name="phone" value="<?php echo e($form['phone']); ?>" placeholder="Optional phone number">
            </div>

            <div class="field">
              <label for="area">Area</label>
              <select id="area" name="area" required>
                <option value="">Select your area</option>
                <?php foreach ($areas as $area): ?>
                  <option value="<?php echo e($area); ?>" <?php echo $form['area'] === $area ? 'selected' : ''; ?>>
                    <?php echo e($area); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field">
              <label for="password">Password</label>
              <input id="password" name="password" type="password" minlength="6" required>
            </div>

            <div class="field">
              <label for="password2">Confirm Password</label>
              <input id="password2" name="password2" type="password" minlength="6" required>
            </div>
          </div>

          <button class="submit-btn" type="submit">Register with Email</button>
        </form>

        <div class="divider">or</div>

        <section class="google-card">
          <div>
            <h3>Continue with Google</h3>
            <p>
              Users sign in through Google and your app creates their NeighborHelp account using the verified Google profile.
            </p>
          </div>

          <div class="register-grid">
            <div class="google-setup">
              <label for="googleArea">Area</label>
              <select id="googleArea" required>
                <option value="">Select your area</option>
                <?php foreach ($areas as $area): ?>
                  <option value="<?php echo e($area); ?>"><?php echo e($area); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="google-setup">
              <label for="googlePhone">Phone</label>
              <input id="googlePhone" placeholder="Optional phone number">
            </div>
          </div>

          <div id="googleMessage" class="google-message" aria-live="polite"></div>

          <?php if ($canUseGoogle): ?>
            <div id="googleButton"></div>
            <p class="helper">If this is a returning Google user, we will log them in directly.</p>
          <?php else: ?>
            <button class="google-fallback" type="button" disabled>Google sign-in needs a client ID in inc/config.php</button>
            <p class="helper">Add your Google OAuth client ID to enable the Google registration button.</p>
          <?php endif; ?>
        </section>

        <p class="signin-link">Already registered? <a href="login.php">Log in here</a>.</p>
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
    const googleArea = document.getElementById('googleArea');
    const googlePhone = document.getElementById('googlePhone');

    function setMessage(el, type, text) {
      if (!el) return;
      el.className = 'google-message ' + type;
      el.textContent = text;
    }

    function clearMessage(el) {
      if (!el) return;
      el.className = 'google-message';
      el.textContent = '';
    }

    function currentArea() {
      return googleArea ? googleArea.value : '';
    }

    function currentPhone() {
      return googlePhone ? googlePhone.value.trim() : '';
    }

    function requireArea(el) {
      if (!currentArea()) {
        setMessage(el, 'error', 'Please select an area first.');
        return false;
      }
      return true;
    }

    function postSocial(endpoint, payload, okEl, failPrefix) {
      return fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then((res) => res.json())
        .then((data) => {
          if (!data.success) {
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
              if (!requireArea(googleMessage)) return;

              postSocial('google_register.php', {
                credential: response.credential,
                area: currentArea(),
                phone: currentPhone()
              }, googleMessage, 'Google sign-in')
                .catch((err) => setMessage(googleMessage, 'error', err.message));
            }
          });

          window.google.accounts.id.renderButton(
            document.getElementById('googleButton'),
            { theme: 'outline', size: 'large', shape: 'pill', width: 320, text: 'signup_with' }
          );
        }
      }

    });
  </script>
  <?php include __DIR__ . '/inc/chatbot_widget.php'; ?>
</body>
</html>
