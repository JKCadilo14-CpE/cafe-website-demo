<?php
require_once __DIR__ . '/components/app.php';

$redirect = (string) ($_GET['redirect'] ?? $_POST['redirect'] ?? '');
$isCartRedirect = $redirect === 'cart';
$redirectTarget = $isCartRedirect ? 'cart.php' : 'profile.php';
$username = trim((string) ($_POST['username'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$message = '';
$messageType = '';

if (app_is_logged_in()) {
    header('Location: ' . ($isCartRedirect && !app_is_admin() ? 'cart.php' : app_dashboard_for_current_user()));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($username === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $message = 'Please complete all signup fields.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } elseif ($password !== $confirmPassword) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
    } else {
        try {
            $mysqli = app_db();
            app_ensure_profile_image_column($mysqli);
            $checkStatement = $mysqli->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $checkStatement->bind_param('s', $email);
            $checkStatement->execute();
            $result = $checkStatement->get_result();
            $existingUser = $result->fetch_assoc();
            $result->free();
            $checkStatement->close();

            if ($existingUser !== null) {
                $message = 'An account with this email already exists.';
                $messageType = 'error';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $role = 0;
                $insertStatement = $mysqli->prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)');
                $insertStatement->bind_param('sssi', $username, $email, $hashedPassword, $role);
                $insertStatement->execute();
                $userId = (int) $mysqli->insert_id;
                $insertStatement->close();
                $mysqli->close();

                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = $role;
                $_SESSION['profile_image'] = '';

                header('Location: ' . $redirectTarget);
                exit();
            }

            $mysqli->close();
        } catch (mysqli_sql_exception $exception) {
            $message = 'Unable to create your account right now. Please check the database connection.';
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
  <title>Sign Up | JKC Cafe</title>
  <meta name="description" content="Create your JKC Cafe account to save your cart, rewards, and checkout details.">
  <link rel="icon" type="image/png" href="./images/logo.svg">
  <link rel="stylesheet" href="user-css/style.css">
  <link rel="stylesheet" href="user-css/auth.css">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main" class="login-page signup-page" data-auth-page>
    <section class="section login-shell" aria-labelledby="signup-title">
      <figure class="login-visual" data-auth-panel>
        <img src="images/contact-cafe.png" alt="JKC Cafe counter with coffee, pastries, and a warm space for guests." width="1774" height="887" fetchpriority="high">
        <span class="login-ambient login-ambient-one" aria-hidden="true"></span>
        <span class="login-ambient login-ambient-two" aria-hidden="true"></span>
        <figcaption class="login-visual-copy">
          <span>JKC Cafe membership</span>
          <strong>Your cafe stop, remembered.</strong>
          <p>Join once so your profile, rewards, and next checkout are ready when the craving shows up.</p>
        </figcaption>
        <div class="login-visual-strip" aria-label="Account benefits">
          <span>Faster checkout</span>
          <span>Saved profile</span>
          <span>Rewards ready</span>
        </div>
      </figure>

      <div class="login-card" data-auth-card>
        <div class="login-heading">
          <p class="eyebrow">Join JKC Cafe</p>
          <h1 id="signup-title">Create your cafe account in a minute.</h1>
          <p>Save the small details now so ordering coffee, pastries, and treats feels easier next time.</p>
        </div>

        <?php if ($message !== ''): ?>
          <p class="login-message <?php echo $messageType === 'error' ? 'is-error' : ''; ?>" role="<?php echo $messageType === 'error' ? 'alert' : 'status'; ?>" aria-live="polite"><?php echo e($message); ?></p>
        <?php endif; ?>

        <form class="login-form" action="signup.php" method="post" data-auth-form>
          <input type="hidden" name="redirect" value="<?php echo e($redirect); ?>">

          <div class="login-form-intro signup-form-intro" aria-label="Signup benefits">
            <span>Before your first order</span>
            <p><?php echo $isCartRedirect ? 'Create your account to keep this cart ready and finish checkout with less typing.' : 'Create a free account for faster checkout, a saved profile, and rewards ready for future visits.'; ?></p>
          </div>

          <div class="login-field" data-input-glow>
            <label for="signup-username">Username</label>
            <input id="signup-username" name="username" type="text" value="<?php echo e($username); ?>" placeholder="Your name" autocomplete="username" aria-describedby="signup-username-help" required>
          </div>
          <p id="signup-username-help" class="login-field-note">This name appears in your account menu and dashboard.</p>

          <div class="login-field" data-input-glow>
            <label for="signup-email">Email</label>
            <input id="signup-email" name="email" type="email" value="<?php echo e($email); ?>" placeholder="you@example.com" autocomplete="email" aria-describedby="signup-email-help" required>
          </div>
          <p id="signup-email-help" class="login-field-note">Use an email you can access for account support.</p>

          <div class="login-field" data-input-glow>
            <label for="signup-password">Password</label>
            <div class="password-control">
              <input id="signup-password" name="password" type="password" placeholder="Create a password" autocomplete="new-password" aria-describedby="signup-password-help" required data-password-input>
              <button type="button" aria-label="Show password" data-password-toggle>
                <span data-password-toggle-text>Show</span>
              </button>
            </div>
          </div>
          <p id="signup-password-help" class="login-field-note">Choose a private password you do not reuse elsewhere.</p>

          <div class="login-field" data-input-glow>
            <label for="signup-confirm-password">Confirm password</label>
            <div class="password-control">
              <input id="signup-confirm-password" name="confirm_password" type="password" placeholder="Confirm your password" autocomplete="new-password" aria-describedby="signup-confirm-password-help" required data-password-input>
              <button type="button" aria-label="Show password" data-password-toggle>
                <span data-password-toggle-text>Show</span>
              </button>
            </div>
          </div>
          <p id="signup-confirm-password-help" class="login-field-note">Retype it once so we can keep your account setup tidy.</p>

          <p class="signup-reassurance">No payment details needed to create an account.</p>

          <button class="button button-primary auth-submit" type="submit" data-auth-submit>
            <span data-auth-submit-text>Create account</span>
            <span data-auth-submit-loading hidden>Creating account...</span>
          </button>
        </form>

        <p class="login-switch">
          Already have an account? <a href="login.php<?php echo $redirect === 'cart' ? '?redirect=cart' : ''; ?>" data-auth-transition-link>Sign in</a>
        </p>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>

  <script src="user-js/script.js"></script>
</body>
</html>
