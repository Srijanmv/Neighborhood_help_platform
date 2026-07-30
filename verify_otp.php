<?php
require 'inc/config.php';
ensure_users_verification_columns();

$pendingUserId = (int) ($_SESSION['pending_verify_user_id'] ?? 0);
$pendingEmail = trim($_SESSION['pending_verify_email'] ?? '');
$pendingSessionOtp = trim($_SESSION['pending_verify_otp'] ?? '');
$message = trim($_SESSION['otp_notice'] ?? '');
$error = '';
unset($_SESSION['otp_notice']);

if (!$pendingUserId || !$pendingEmail) {
    header('Location: register.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'verify';
    $hasOtpColumn = db_column_exists('users', 'otp');
    $hasVerifiedColumn = db_column_exists('users', 'is_verified');

    if ($action === 'resend') {
        $otp = (string) random_int(100000, 999999);
        $_SESSION['pending_verify_otp'] = $otp;

        if ($hasOtpColumn && $hasVerifiedColumn) {
            $stmt = $pdo->prepare("UPDATE users SET otp = ?, is_verified = 0 WHERE id = ?");
            $stmt->execute([$otp, $pendingUserId]);
        }

        if (send_verification_otp_email($pendingEmail, $otp)) {
            $message = 'A new OTP has been sent to your email.';
        } else {
            $mailErr = otp_mail_last_error();
            $error = 'OTP email could not be sent right now. Please try again.' .
                ($mailErr ? (' Reason: ' . $mailErr) : '');
        }
    } else {
        $userOtp = trim($_POST['otp'] ?? '');

        if (!preg_match('/^\d{6}$/', $userOtp)) {
            $error = 'Enter a valid 6-digit OTP.';
        } else {
            $isValidOtp = false;

            if ($hasOtpColumn && $hasVerifiedColumn) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND email = ? AND otp = ? LIMIT 1");
                $stmt->execute([$pendingUserId, $pendingEmail, $userOtp]);
                $isValidOtp = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $isValidOtp = hash_equals($pendingSessionOtp, $userOtp);
            }

            if ($isValidOtp) {
                if ($hasOtpColumn && $hasVerifiedColumn) {
                    $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, otp = NULL WHERE id = ?");
                    $stmt->execute([$pendingUserId]);
                }

                unset($_SESSION['pending_verify_user_id'], $_SESSION['pending_verify_email'], $_SESSION['pending_verify_otp']);
                $_SESSION['user_id'] = $pendingUserId;
                header('Location: community.php');
                exit;
            }

            $error = 'Invalid OTP. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verify OTP - Neighborhood Help</title>
  <style>
    :root {
      --bg: #f4f7f4;
      --ink: #1f2937;
      --muted: #6b7280;
      --brand: #166534;
      --danger: #b91c1c;
      --danger-bg: #fef2f2;
      --ok: #14532d;
      --ok-bg: #ecfdf3;
      --card: #ffffff;
      --line: #d1d5db;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: Arial, sans-serif;
      color: var(--ink);
      background: radial-gradient(circle at 20% 20%, #dcfce7, transparent 40%), var(--bg);
      display: grid;
      place-items: center;
      padding: 16px;
    }

    .card {
      width: min(420px, 100%);
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 18px;
      padding: 24px;
      box-shadow: 0 16px 44px rgba(15, 23, 42, 0.12);
    }

    h1 {
      margin: 0 0 8px;
      font-size: 1.5rem;
    }

    p {
      margin: 0 0 16px;
      color: var(--muted);
      line-height: 1.5;
    }

    .alert,
    .notice {
      border-radius: 12px;
      padding: 10px 12px;
      margin-bottom: 14px;
      font-size: 0.95rem;
    }

    .alert {
      background: var(--danger-bg);
      color: var(--danger);
      border: 1px solid #fecaca;
    }

    .notice {
      background: var(--ok-bg);
      color: var(--ok);
      border: 1px solid #bbf7d0;
    }

    label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
    }

    input[type="text"] {
      width: 100%;
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 11px 12px;
      font-size: 1rem;
      margin-bottom: 12px;
    }

    .btn {
      width: 100%;
      border: 0;
      border-radius: 10px;
      padding: 11px 12px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
    }

    .btn-primary {
      background: var(--brand);
      color: #fff;
      margin-bottom: 10px;
    }

    .btn-secondary {
      background: #f3f4f6;
      color: #111827;
      border: 1px solid #d1d5db;
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>Email OTP Verification</h1>
    <p>OTP sent to <strong><?php echo e($pendingEmail); ?></strong>. Enter it to activate your account.</p>

    <?php if ($message): ?>
      <div class="notice"><?php echo e($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="action" value="verify">
      <label for="otp">6-digit OTP</label>
      <input id="otp" name="otp" type="text" inputmode="numeric" maxlength="6" pattern="\d{6}" required>
      <button class="btn btn-primary" type="submit">Verify OTP</button>
    </form>

    <form method="post">
      <input type="hidden" name="action" value="resend">
      <button class="btn btn-secondary" type="submit">Resend OTP</button>
    </form>
  </div>
  <?php include __DIR__ . '/inc/chatbot_widget.php'; ?>
</body>
</html>
