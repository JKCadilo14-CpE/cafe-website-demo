<?php
require_once __DIR__ . '/components/app.php';

$redirect = (string) ($_GET['redirect'] ?? $_POST['redirect'] ?? '');
$isCartRedirect = $redirect === 'cart';
$email = trim((string) ($_POST['email'] ?? ''));
$message = '';
$messageType = '';

if (isset($_GET['account_deleted']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $message = 'Your account has been deleted. You have been signed out.';
    $messageType = 'success';
}

if (app_is_logged_in()) {
    header('Location: ' . ($isCartRedirect && !app_is_admin() ? 'cart.php' : app_dashboard_for_current_user()));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_require_csrf();

    $password = (string) ($_POST['password'] ?? '');
    $loginRateLimit = app_rate_limit_check('login', 5, 600, 600);

    if (!$loginRateLimit['allowed']) {
        $message = app_rate_limit_message($loginRateLimit, 'login attempts');
        $messageType = 'error';
    } elseif ($email === '' || $password === '') {
        $message = 'Please enter your email and password.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } else {
        try {
            $mysqli = app_db();
            app_ensure_profile_image_column($mysqli);
            $statement = $mysqli->prepare('SELECT id, username, email, password, role, account_status, profile_image FROM users WHERE email = ? LIMIT 1');
            $statement->bind_param('s', $email);
            $statement->execute();
            $result = $statement->get_result();
            $user = $result->fetch_assoc();
            $result->free();
            $statement->close();
            $mysqli->close();

            if ($user !== null && password_verify($password, (string) $user['password'])) {
                $accountStatus = trim((string) ($user['account_status'] ?? 'active'));

                if ($accountStatus !== '' && strcasecmp($accountStatus, 'deleted') === 0) {
                    app_rate_limit_hit('login', 5, 600, 600);
                    $message = 'This account is no longer available.';
                    $messageType = 'error';
                } else {
                    app_rate_limit_clear('login');
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['username'] = (string) $user['username'];
                    $_SESSION['email'] = (string) $user['email'];
                    $_SESSION['role'] = (int) $user['role'];
                    $_SESSION['profile_image'] = app_profile_image_src((string) ($user['profile_image'] ?? ''));

                    if ((int) $user['role'] === 1) {
                        header('Location: admin pages/admin-home.php');
                        exit();
                    }

                    header('Location: ' . ($isCartRedirect ? 'cart.php' : 'profile.php'));
                    exit();
                }
            }

            if ($message === '') {
                app_rate_limit_hit('login', 5, 600, 600);
                $message = 'Invalid email or password.';
                $messageType = 'error';
            }
        } catch (mysqli_sql_exception $exception) {
            $message = 'Unable to log in right now. Please check the database connection.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Log In | JKC Cafe</title>
  <meta name="description" content="Log in to your JKC Cafe account to continue your cart and checkout.">
  <link rel="icon" type="image/svg+xml" href="./images/logo.svg">
  <link rel="stylesheet" href="user-css/style.css">
  <link rel="stylesheet" href="user-css/auth.css">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main" class="login-page" data-auth-page>
    <section class="section login-shell" aria-labelledby="login-title">
      <figure class="login-visual" data-auth-panel>
        <img src="images/contact-cafe.png" alt="Warm JKC Cafe counter with coffee cups, pastries, and soft natural light." width="1774" height="887" fetchpriority="high">
        <span class="login-ambient login-ambient-one" aria-hidden="true"></span>
        <span class="login-ambient login-ambient-two" aria-hidden="true"></span>
        <figcaption class="login-visual-copy">
          <span>JKC Cafe account</span>
          <strong>Come back to your usual, faster.</strong>
          <p>Sign in to keep your cart, profile, rewards, and checkout details ready for the next cafe stop.</p>
        </figcaption>
        <div class="login-visual-strip" aria-label="Account benefits">
          <span>Saved cart</span>
          <span>Profile ready</span>
          <span>Rewards in reach</span>
        </div>
      </figure>

      <div class="login-card" data-auth-card>
        <div class="login-heading">
          <p class="eyebrow">Welcome back</p>
          <h1 id="login-title">Sign in before your next cup.</h1>
          <p>Use the email and password connected to your JKC Cafe account.</p>
        </div>

        <?php if ($message !== ''): ?>
          <p class="login-message <?php echo $messageType === 'error' ? 'is-error' : ''; ?>" role="<?php echo $messageType === 'error' ? 'alert' : 'status'; ?>" aria-live="polite"><?php echo e($message); ?></p>
        <?php endif; ?>

        <form class="login-form" action="login.php" method="post" data-auth-form>
          <?php echo app_csrf_field(); ?>
          <input type="hidden" name="redirect" value="<?php echo e($redirect); ?>">

          <div class="login-form-intro" aria-label="Sign in benefits">
            <span>Quick return</span>
            <p><?php echo $isCartRedirect ? 'Sign in to return to your cart with your order still in place.' : 'Sign in once and keep your cafe details ready for orders, rewards, and profile updates.'; ?></p>
          </div>

          <div class="login-field" data-input-glow>
            <label for="login-email">Email</label>
            <input id="login-email" name="email" type="email" value="<?php echo e($email); ?>" placeholder="you@example.com" autocomplete="email" aria-describedby="login-email-help" required>
          </div>
          <p id="login-email-help" class="login-field-note">Use the email you used when creating your JKC Cafe account.</p>

          <div class="login-field" data-input-glow>
            <label for="login-password">Password</label>
            <div class="password-control">
              <input id="login-password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" aria-describedby="login-password-help" required data-password-input>
              <button type="button" aria-label="Show password" data-password-toggle>
                <span data-password-toggle-text>Show</span>
              </button>
            </div>
          </div>
          <p id="login-password-help" class="login-field-note">Your password stays private and is checked securely.</p>

          <div class="login-options">
            <label>
              <input type="checkbox" name="remember" value="1">
              <span class="login-checkbox-mark" aria-hidden="true"></span>
              Remember me
            </label>
            <a href="forgot-password.php">Forgot Password?</a>
          </div>

          <button class="button button-primary auth-submit" type="submit" data-auth-submit>
            <span data-auth-submit-text>Sign in</span>
            <span data-auth-submit-loading hidden>Checking account...</span>
          </button>
        </form>

        <p class="login-switch">
          New to JKC Cafe? <a href="signup.php<?php echo $redirect === 'cart' ? '?redirect=cart' : ''; ?>" data-auth-transition-link>Create an account</a>
        </p>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>

  <script src="user-js/script.js"></script>
</body>
</html>
