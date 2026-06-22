<?php
require_once __DIR__ . '/components/app.php';

$accountEmail = trim((string) ($_POST['account_email'] ?? ''));
$recoveryEmail = trim((string) ($_POST['recovery_email'] ?? ''));
$recoveryPhone = trim((string) ($_POST['recovery_phone'] ?? ''));
$message = '';
$messageType = '';

function forgot_password_valid_phone(string $phone): bool
{
    return strlen($phone) <= 30 && preg_match('/^\+?[0-9][0-9\s().-]{6,29}$/', $phone) === 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($accountEmail === '') {
        $message = 'Please enter your account email.';
        $messageType = 'error';
    } elseif (!filter_var($accountEmail, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid account email address.';
        $messageType = 'error';
    } elseif ($recoveryEmail === '' && $recoveryPhone === '') {
        $message = 'Please enter a recovery email or recovery phone.';
        $messageType = 'error';
    } elseif ($recoveryEmail !== '' && !filter_var($recoveryEmail, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid recovery email address.';
        $messageType = 'error';
    } elseif ($recoveryPhone !== '' && !forgot_password_valid_phone($recoveryPhone)) {
        $message = 'Please enter a valid recovery phone number.';
        $messageType = 'error';
    } else {
        $message = 'Demo Mode: If the information matches our records, a password recovery message would normally be sent to your recovery contact.';
        $messageType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Demo Password Recovery | JKC Cafe</title>
  <meta name="description" content="Demo-only JKC Cafe password recovery form.">
  <link rel="icon" type="image/png" href="./images/logo.svg">
  <link rel="stylesheet" href="user-css/style.css">
  <link rel="stylesheet" href="user-css/auth.css">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main" class="login-page" data-auth-page>
    <section class="section login-shell" aria-labelledby="recovery-title">
      <figure class="login-visual" data-auth-panel>
        <img src="images/contact-cafe.png" alt="Warm JKC Cafe counter with coffee cups, pastries, and soft natural light." width="1774" height="887" fetchpriority="high">
        <span class="login-ambient login-ambient-one" aria-hidden="true"></span>
        <span class="login-ambient login-ambient-two" aria-hidden="true"></span>
        <figcaption class="login-visual-copy">
          <span>Account help demo</span>
          <strong>A calm path back to your cafe account.</strong>
          <p>This sample recovery page shows the form experience without sending real messages or changing passwords.</p>
        </figcaption>
        <div class="login-visual-strip" aria-label="Demo recovery notes">
          <span>Demo only</span>
          <span>No OTP</span>
          <span>No reset</span>
        </div>
      </figure>

      <div class="login-card" data-auth-card>
        <div class="login-heading">
          <p class="eyebrow">Account recovery</p>
          <h1 id="recovery-title">Demo password recovery</h1>
          <p>Enter your account email and one recovery contact to preview how a recovery request would feel.</p>
        </div>

        <p class="login-message" role="note">Demo Mode: This page does not send real emails, SMS, OTPs, or reset passwords.</p>

        <?php if ($message !== ''): ?>
          <p class="login-message <?php echo $messageType === 'error' ? 'is-error' : ''; ?>" role="<?php echo $messageType === 'error' ? 'alert' : 'status'; ?>" aria-live="polite"><?php echo e($message); ?></p>
        <?php endif; ?>

        <form class="login-form" action="forgot-password.php" method="post" data-auth-form>
          <div class="login-form-intro" aria-label="Recovery demo notice">
            <span>Demo recovery</span>
            <p>For privacy, the response stays the same whether an account exists or not.</p>
          </div>

          <div class="login-field" data-input-glow>
            <label for="recovery-account-email">Account email</label>
            <input id="recovery-account-email" name="account_email" type="email" value="<?php echo e($accountEmail); ?>" placeholder="you@example.com" autocomplete="email" aria-describedby="recovery-account-email-help" required>
          </div>
          <p id="recovery-account-email-help" class="login-field-note">Use the email connected to the account you would recover.</p>

          <div class="login-field" data-input-glow>
            <label for="recovery-email">Recovery email</label>
            <input id="recovery-email" name="recovery_email" type="email" value="<?php echo e($recoveryEmail); ?>" placeholder="backup@example.com" autocomplete="email" aria-describedby="recovery-contact-help">
          </div>

          <div class="login-field" data-input-glow>
            <label for="recovery-phone">Recovery phone</label>
            <input id="recovery-phone" name="recovery_phone" type="tel" value="<?php echo e($recoveryPhone); ?>" placeholder="+63 912 345 6789" autocomplete="tel" aria-describedby="recovery-contact-help">
          </div>
          <p id="recovery-contact-help" class="login-field-note">Enter at least one recovery contact. Phone numbers may use numbers, spaces, dashes, parentheses, periods, and an optional leading plus sign.</p>

          <button class="button button-primary auth-submit" type="submit" data-auth-submit>
            <span data-auth-submit-text>Check recovery contact</span>
            <span data-auth-submit-loading hidden>Checking demo request...</span>
          </button>
        </form>

        <p class="login-switch">
          Remembered your password? <a href="login.php" data-auth-transition-link>Log in</a>
        </p>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>

  <script src="user-js/script.js"></script>
</body>
</html>
