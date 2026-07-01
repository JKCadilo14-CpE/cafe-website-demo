<?php
require_once __DIR__ . '/components/app.php';

if (!app_is_logged_in()) {
    header('Location: login.php');
    exit();
}

function settings_upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Profile photo is too large. Please choose an image under 2MB.',
        UPLOAD_ERR_PARTIAL => 'The profile photo upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Please choose a profile photo to upload.',
        default => 'Unable to upload that profile photo. Please try again.',
    };
}

function settings_store_profile_image(array $file, int $userId): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['path' => '', 'error' => settings_upload_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE))];
    }

    if ((int) ($file['size'] ?? 0) > APP_PROFILE_IMAGE_MAX_SIZE) {
        return ['path' => '', 'error' => 'Profile photo is too large. Please choose an image under 2MB.'];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['path' => '', 'error' => 'Unable to read that profile photo. Please choose another image.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? (string) finfo_file($finfo, $tmpName) : '';

    if ($finfo) {
        finfo_close($finfo);
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($extensions[$mimeType])) {
        return ['path' => '', 'error' => 'Please upload a JPG, PNG, WebP, or GIF image.'];
    }

    $uploadDir = app_profile_image_upload_dir();

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return ['path' => '', 'error' => 'Unable to prepare the profile photo folder.'];
    }

    $filename = 'user-' . $userId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extensions[$mimeType];
    $absolutePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        return ['path' => '', 'error' => 'Unable to save that profile photo. Please try again.'];
    }

    return ['path' => APP_PROFILE_IMAGE_DIR . '/' . $filename, 'error' => ''];
}

function settings_is_valid_recovery_phone(string $phone): bool
{
    return strlen($phone) <= 30 && preg_match('/^\+?[0-9][0-9\s().-]{6,29}$/', $phone) === 1;
}

function settings_destroy_current_session(): void
{
    app_destroy_session();
}

$userId = (int) $_SESSION['user_id'];
$account = [
    'username' => (string) ($_SESSION['username'] ?? 'Account'),
    'email' => (string) ($_SESSION['email'] ?? ''),
    'recovery_email' => '',
    'recovery_phone' => '',
    'password' => '',
    'account_status' => 'active',
    'deleted_at' => '',
    'profile_image' => (string) ($_SESSION['profile_image'] ?? ''),
];
$message = '';
$messageType = '';

try {
    $mysqli = app_db();
    app_ensure_profile_image_column($mysqli);

    $statement = $mysqli->prepare('SELECT username, email, recovery_email, recovery_phone, password, account_status, deleted_at, profile_image FROM users WHERE id = ? LIMIT 1');
    $statement->bind_param('i', $userId);
    $statement->execute();
    $result = $statement->get_result();
    $user = $result->fetch_assoc();
    $result->free();
    $statement->close();

    if ($user === null) {
        $message = 'Unable to find this account right now.';
        $messageType = 'error';
    } else {
        $account = [
            'username' => (string) $user['username'],
            'email' => (string) $user['email'],
            'recovery_email' => (string) ($user['recovery_email'] ?? ''),
            'recovery_phone' => (string) ($user['recovery_phone'] ?? ''),
            'password' => (string) $user['password'],
            'account_status' => trim((string) ($user['account_status'] ?? 'active')) !== '' ? (string) $user['account_status'] : 'active',
            'deleted_at' => (string) ($user['deleted_at'] ?? ''),
            'profile_image' => (string) ($user['profile_image'] ?? ''),
        ];

        if (strcasecmp($account['account_status'], 'deleted') === 0) {
            $mysqli->close();
            settings_destroy_current_session();
            header('Location: login.php?account_deleted=1');
            exit();
        }

        $_SESSION['username'] = $account['username'];
        $_SESSION['email'] = $account['email'];
        $_SESSION['profile_image'] = app_profile_image_src($account['profile_image']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            app_require_csrf();

            $action = (string) ($_POST['action'] ?? '');

            if ($action === 'profile_image') {
                $upload = settings_store_profile_image($_FILES['profile_image'] ?? [], $userId);

                if ($upload['error'] !== '') {
                    $message = $upload['error'];
                    $messageType = 'error';
                } else {
                    $previousImage = $account['profile_image'];
                    $profileImage = $upload['path'];
                    $updateStatement = $mysqli->prepare('UPDATE users SET profile_image = ? WHERE id = ?');
                    $updateStatement->bind_param('si', $profileImage, $userId);
                    $updateStatement->execute();
                    $updateStatement->close();

                    app_delete_profile_image($previousImage);
                    $account['profile_image'] = $profileImage;
                    $_SESSION['profile_image'] = $profileImage;
                    $message = 'Profile photo updated successfully.';
                    $messageType = 'success';
                }
            } elseif ($action === 'remove_profile_image') {
                $previousImage = $account['profile_image'];
                $emptyImage = null;
                $updateStatement = $mysqli->prepare('UPDATE users SET profile_image = ? WHERE id = ?');
                $updateStatement->bind_param('si', $emptyImage, $userId);
                $updateStatement->execute();
                $updateStatement->close();

                app_delete_profile_image($previousImage);
                $account['profile_image'] = '';
                $_SESSION['profile_image'] = '';
                $message = 'Profile photo removed successfully.';
                $messageType = 'success';
            } elseif ($action === 'username') {
                $username = trim((string) ($_POST['username'] ?? ''));

                if ($username === '') {
                    $message = 'Please enter a username.';
                    $messageType = 'error';
                } else {
                    $updateStatement = $mysqli->prepare('UPDATE users SET username = ? WHERE id = ?');
                    $updateStatement->bind_param('si', $username, $userId);
                    $updateStatement->execute();
                    $updateStatement->close();

                    $account['username'] = $username;
                    $_SESSION['username'] = $username;
                    $message = 'Username updated successfully.';
                    $messageType = 'success';
                }
            } elseif ($action === 'recovery_contact') {
                $recoveryEmail = trim((string) ($_POST['recovery_email'] ?? ''));
                $recoveryPhone = trim((string) ($_POST['recovery_phone'] ?? ''));

                if ($recoveryEmail !== '' && strlen($recoveryEmail) > 150) {
                    $message = 'Recovery email must be 150 characters or fewer.';
                    $messageType = 'error';
                } elseif ($recoveryEmail !== '' && !filter_var($recoveryEmail, FILTER_VALIDATE_EMAIL)) {
                    $message = 'Please enter a valid recovery email address.';
                    $messageType = 'error';
                } elseif ($recoveryPhone !== '' && !settings_is_valid_recovery_phone($recoveryPhone)) {
                    $message = 'Please enter a valid recovery phone number.';
                    $messageType = 'error';
                } else {
                    $recoveryEmailValue = $recoveryEmail !== '' ? $recoveryEmail : null;
                    $recoveryPhoneValue = $recoveryPhone !== '' ? $recoveryPhone : null;
                    $recoveryStatement = $mysqli->prepare('UPDATE users SET recovery_email = ?, recovery_phone = ? WHERE id = ?');
                    $recoveryStatement->bind_param('ssi', $recoveryEmailValue, $recoveryPhoneValue, $userId);
                    $recoveryStatement->execute();
                    $recoveryStatement->close();

                    $account['recovery_email'] = $recoveryEmail;
                    $account['recovery_phone'] = $recoveryPhone;
                    $message = 'Recovery contact updated successfully.';
                    $messageType = 'success';
                }
            } elseif ($action === 'password') {
                $currentPassword = (string) ($_POST['current_password'] ?? '');
                $newPassword = (string) ($_POST['new_password'] ?? '');
                $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

                if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                    $message = 'Please complete all password fields.';
                    $messageType = 'error';
                } elseif (!password_verify($currentPassword, $account['password'])) {
                    $message = 'Current password is incorrect.';
                    $messageType = 'error';
                } elseif ($newPassword !== $confirmPassword) {
                    $message = 'New passwords do not match.';
                    $messageType = 'error';
                } else {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $passwordStatement = $mysqli->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $passwordStatement->bind_param('si', $hashedPassword, $userId);
                    $passwordStatement->execute();
                    $passwordStatement->close();

                    $message = 'Password updated successfully.';
                    $messageType = 'success';
                }
            } elseif ($action === 'delete_account') {
                $deletePassword = (string) ($_POST['delete_password'] ?? '');
                $deleteConfirmation = trim((string) ($_POST['delete_confirmation'] ?? ''));

                if ($deletePassword === '' || $deleteConfirmation === '') {
                    $message = 'Please enter your password and type DELETE to confirm.';
                    $messageType = 'error';
                } elseif ($deleteConfirmation !== 'DELETE') {
                    $message = 'Please type DELETE exactly to confirm account deletion.';
                    $messageType = 'error';
                } elseif (!password_verify($deletePassword, $account['password'])) {
                    $message = 'Password confirmation is incorrect.';
                    $messageType = 'error';
                } else {
                    $transactionStarted = false;
                    $profileImageToDelete = '';

                    try {
                        $mysqli->begin_transaction();
                        $transactionStarted = true;

                        $deleteUserStatement = $mysqli->prepare('SELECT id, password, profile_image, role FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
                        $deleteUserStatement->bind_param('i', $userId);
                        $deleteUserStatement->execute();
                        $deleteUserResult = $deleteUserStatement->get_result();
                        $deleteUser = $deleteUserResult->fetch_assoc();
                        $deleteUserResult->free();
                        $deleteUserStatement->close();

                        if ($deleteUser === null) {
                            throw new RuntimeException('Account not found.');
                        }

                        if (!password_verify($deletePassword, (string) $deleteUser['password'])) {
                            throw new RuntimeException('Password confirmation failed.');
                        }

                        $profileImageToDelete = (string) ($deleteUser['profile_image'] ?? '');

                        $cancelOrdersStatement = $mysqli->prepare(
                            "UPDATE orders
                             SET status = 'Cancelled',
                                 cancel_reason = 'User account permanently deleted.',
                                 updated_at = NOW()
                             WHERE user_id = ?
                               AND status IN ('Pending', 'Preparing', 'Out for Delivery')"
                        );
                        $cancelOrdersStatement->bind_param('i', $userId);
                        $cancelOrdersStatement->execute();
                        $cancelOrdersStatement->close();

                        $deleteItemsStatement = $mysqli->prepare(
                            'DELETE oi
                             FROM order_items oi
                             INNER JOIN orders o ON o.id = oi.order_id
                             WHERE o.user_id = ?'
                        );
                        $deleteItemsStatement->bind_param('i', $userId);
                        $deleteItemsStatement->execute();
                        $deleteItemsStatement->close();

                        $deleteOrdersStatement = $mysqli->prepare('DELETE FROM orders WHERE user_id = ?');
                        $deleteOrdersStatement->bind_param('i', $userId);
                        $deleteOrdersStatement->execute();
                        $deleteOrdersStatement->close();

                        $deleteNotificationsStatement = $mysqli->prepare('DELETE FROM user_notifications WHERE user_id = ?');
                        $deleteNotificationsStatement->bind_param('i', $userId);
                        $deleteNotificationsStatement->execute();
                        $deleteNotificationsStatement->close();

                        $deleteRemindersStatement = $mysqli->prepare('DELETE FROM product_reminders WHERE user_id = ?');
                        $deleteRemindersStatement->bind_param('i', $userId);
                        $deleteRemindersStatement->execute();
                        $deleteRemindersStatement->close();

                        $deleteAccountStatement = $mysqli->prepare('DELETE FROM users WHERE id = ?');
                        $deleteAccountStatement->bind_param('i', $userId);
                        $deleteAccountStatement->execute();
                        $deleteAccountStatement->close();

                        $mysqli->commit();
                        $mysqli->close();

                        app_delete_profile_image($profileImageToDelete);
                        settings_destroy_current_session();
                        header('Location: login.php?account_deleted=1');
                        exit();
                    } catch (Throwable $exception) {
                        if ($transactionStarted) {
                            $mysqli->rollback();
                        }

                        $message = 'Unable to delete your account right now. Please try again.';
                        $messageType = 'error';
                    }
                }
            }
        }
    }

    $mysqli->close();
} catch (mysqli_sql_exception $exception) {
    $message = 'Unable to update your settings right now. Please check the database connection.';
    $messageType = 'error';
}

$displayName = trim($account['username']) !== '' ? trim($account['username']) : 'Account';
$displayEmail = trim($account['email']) !== '' ? trim($account['email']) : 'No email saved';
$displayRecoveryEmail = trim($account['recovery_email']) !== '' ? trim($account['recovery_email']) : 'Not set';
$displayRecoveryPhone = trim($account['recovery_phone']) !== '' ? trim($account['recovery_phone']) : 'Not set';
$displayAccountStatus = strcasecmp((string) $account['account_status'], 'deleted') === 0 ? 'Deleted' : 'Active';
$profileImage = app_profile_image_src($account['profile_image']);
$initials = app_initials($displayName);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings | JKC Cafe</title>
  <meta name="description" content="Update your JKC Cafe account settings and profile photo.">
  <link rel="icon" type="image/svg+xml" href="./images/logo.svg">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="user-css/style.css">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main" class="settings-page">
    <section class="section settings-shell" aria-labelledby="settings-title">
      <section class="settings-hero" aria-labelledby="settings-title" data-reveal>
        <div class="settings-hero-copy">
          <p class="eyebrow">Account care</p>
          <h1 id="settings-title">Keep your cafe profile feeling current.</h1>
          <p>Update the details that make ordering feel familiar: your photo, display name, and password.</p>
          <div class="settings-hero-actions">
            <a class="button button-primary" href="profile.php"><i class="fa-solid fa-table-columns" aria-hidden="true"></i> Back to dashboard</a>
            <a class="button button-secondary" href="menu.php"><i class="fa-solid fa-mug-hot" aria-hidden="true"></i> Browse menu</a>
          </div>
          <div class="settings-care-strip" aria-label="Settings highlights">
            <span><i class="fa-solid fa-image" aria-hidden="true"></i> Photo ready</span>
            <span><i class="fa-solid fa-user-check" aria-hidden="true"></i> Name visible</span>
            <span><i class="fa-solid fa-lock" aria-hidden="true"></i> Password protected</span>
          </div>
        </div>
        <div class="settings-hero-visual">
          <figure class="settings-hero-media">
            <img src="images/contact-cafe.png" width="1717" height="916" alt="Warm JKC Cafe counter with coffee and pastries.">
            <figcaption><i class="fa-solid fa-mug-saucer" aria-hidden="true"></i> Your account, ready for the next visit</figcaption>
          </figure>
          <aside class="settings-hero-card" aria-label="Account snapshot">
            <span class="settings-avatar-preview" aria-hidden="true">
              <?php if ($profileImage !== ''): ?>
                <img src="<?php echo e($profileImage); ?>" alt="">
              <?php else: ?>
                <?php echo e($initials); ?>
              <?php endif; ?>
            </span>
            <div>
              <strong><?php echo e($displayName); ?></strong>
              <span><?php echo e($displayEmail); ?></span>
            </div>
            <div class="settings-account-badges" aria-label="Account status">
              <span><i class="fa-solid fa-image" aria-hidden="true"></i><?php echo $profileImage !== '' ? 'Custom photo' : 'Initials avatar'; ?></span>
              <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>Password protected</span>
            </div>
          </aside>
        </div>
      </section>

      <nav class="settings-section-nav" aria-label="Settings sections" data-settings-nav data-reveal>
        <a class="is-active" href="#settings-photo" aria-current="true" data-settings-nav-link>
          <i class="fa-solid fa-camera" aria-hidden="true"></i>
          <span>Photo</span>
        </a>
        <a href="#settings-display-name" data-settings-nav-link>
          <i class="fa-solid fa-user-pen" aria-hidden="true"></i>
          <span>Display Name</span>
        </a>
        <a href="#settings-recovery" data-settings-nav-link>
          <i class="fa-solid fa-address-card" aria-hidden="true"></i>
          <span>Recovery</span>
        </a>
        <a href="#settings-password" data-settings-nav-link>
          <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
          <span>Security</span>
        </a>
        <a href="#settings-danger" data-settings-nav-link>
          <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
          <span>Danger Zone</span>
        </a>
        <a href="#settings-summary" data-settings-nav-link>
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          <span>Summary</span>
        </a>
      </nav>

      <?php if ($message !== ''): ?>
        <p class="login-message settings-message <?php echo $messageType === 'error' ? 'is-error' : ''; ?>" role="<?php echo $messageType === 'error' ? 'alert' : 'status'; ?>" aria-live="polite"><?php echo e($message); ?></p>
      <?php endif; ?>

      <div class="settings-dashboard-grid">
        <section id="settings-photo" class="settings-modern-panel settings-photo-panel" aria-labelledby="photo-title" data-settings-section data-reveal>
          <div class="settings-panel-title">
            <span><i class="fa-solid fa-camera" aria-hidden="true"></i></span>
            <div>
              <p class="eyebrow">Profile photo</p>
              <h2 id="photo-title">Your cafe avatar</h2>
            </div>
          </div>

          <div class="settings-photo-preview">
            <span class="settings-photo-frame" aria-hidden="true">
              <?php if ($profileImage !== ''): ?>
                <img src="<?php echo e($profileImage); ?>" alt="">
              <?php else: ?>
                <?php echo e($initials); ?>
              <?php endif; ?>
            </span>
            <div>
              <span class="settings-status-chip"><?php echo $profileImage !== '' ? 'Photo live' : 'Initials live'; ?></span>
              <h3><?php echo $profileImage !== '' ? 'Your photo is active' : 'Your initials are active'; ?></h3>
              <p id="settings-upload-help">Use a clear JPG, PNG, WebP, or GIF under 2MB. Square photos crop the cleanest.</p>
            </div>
          </div>

          <form class="settings-upload-form" action="settings.php#settings-photo" method="post" enctype="multipart/form-data">
            <?php echo app_csrf_field(); ?>
            <input type="hidden" name="action" value="profile_image">
            <label class="settings-file-drop" for="profile-image">
              <span class="settings-file-icon"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></span>
              <span class="settings-file-main">Upload a friendly profile photo</span>
              <span class="settings-file-label" data-file-label data-default-label="No file selected">No file selected</span>
              <input id="profile-image" name="profile_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" aria-describedby="settings-upload-help" required>
            </label>
            <button class="button button-primary" type="submit"><i class="fa-solid fa-upload" aria-hidden="true"></i> Upload photo</button>
          </form>

          <?php if ($profileImage !== ''): ?>
            <form action="settings.php#settings-photo" method="post">
              <?php echo app_csrf_field(); ?>
              <input type="hidden" name="action" value="remove_profile_image">
              <button class="button button-secondary settings-remove-photo" type="submit"><i class="fa-solid fa-trash" aria-hidden="true"></i> Remove photo</button>
            </form>
          <?php endif; ?>
        </section>

        <section id="settings-display-name" class="settings-modern-panel" aria-labelledby="username-title" data-settings-section data-reveal>
          <div class="settings-panel-title">
            <span><i class="fa-solid fa-user-pen" aria-hidden="true"></i></span>
            <div>
              <p class="eyebrow">Display name</p>
              <h2 id="username-title">Name shown on your account</h2>
            </div>
          </div>

          <div class="settings-form-intro">
            <i class="fa-solid fa-id-badge" aria-hidden="true"></i>
            <p>This name appears in your account menu, dashboard, and saved cafe details.</p>
          </div>

          <form class="login-form" action="settings.php#settings-display-name" method="post">
            <?php echo app_csrf_field(); ?>
            <input type="hidden" name="action" value="username">
            <div class="login-field" data-input-glow>
              <label for="settings-username">Username</label>
              <input id="settings-username" name="username" type="text" value="<?php echo e($displayName); ?>" autocomplete="username" aria-describedby="settings-username-help" required>
            </div>
            <p id="settings-username-help" class="settings-field-note">Use the name you want cafe staff and your dashboard to show.</p>
            <button class="button button-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save username</button>
          </form>
        </section>

        <section id="settings-recovery" class="settings-modern-panel" aria-labelledby="recovery-title" data-settings-section data-reveal>
          <div class="settings-panel-title">
            <span><i class="fa-solid fa-address-card" aria-hidden="true"></i></span>
            <div>
              <p class="eyebrow">Recovery contact</p>
              <h2 id="recovery-title">Backup ways to reach you</h2>
            </div>
          </div>

          <div class="settings-form-intro">
            <i class="fa-solid fa-life-ring" aria-hidden="true"></i>
            <p>These details are separate from your main account email and help with future account support.</p>
          </div>

          <form class="login-form settings-recovery-form" action="settings.php#settings-recovery" method="post">
            <?php echo app_csrf_field(); ?>
            <input type="hidden" name="action" value="recovery_contact">

            <div class="settings-recovery-grid">
              <div class="login-field" data-input-glow>
                <label for="settings-recovery-email">Recovery email</label>
                <input id="settings-recovery-email" name="recovery_email" type="email" value="<?php echo e($account['recovery_email']); ?>" placeholder="backup@example.com" autocomplete="email" aria-describedby="settings-recovery-email-help">
              </div>

              <div class="login-field" data-input-glow>
                <label for="settings-recovery-phone">Recovery phone</label>
                <input id="settings-recovery-phone" name="recovery_phone" type="tel" value="<?php echo e($account['recovery_phone']); ?>" placeholder="+63 912 345 6789" autocomplete="tel" aria-describedby="settings-recovery-phone-help">
              </div>
            </div>

            <div class="settings-contact-card" aria-label="Current recovery contact">
              <div>
                <span>Saved recovery email</span>
                <strong><?php echo e($displayRecoveryEmail); ?></strong>
              </div>
              <div>
                <span>Saved recovery phone</span>
                <strong><?php echo e($displayRecoveryPhone); ?></strong>
              </div>
            </div>

            <p id="settings-recovery-email-help" class="settings-field-note">Recovery email is optional and does not replace your main account email.</p>
            <p id="settings-recovery-phone-help" class="settings-field-note">Recovery phone is optional. Use numbers, spaces, dashes, parentheses, periods, and an optional leading plus sign.</p>
            <button class="button button-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save recovery contact</button>
          </form>
        </section>

        <section id="settings-password" class="settings-modern-panel" aria-labelledby="password-title" data-settings-section data-reveal>
          <div class="settings-panel-title">
            <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
            <div>
              <p class="eyebrow">Security</p>
              <h2 id="password-title">Change password</h2>
            </div>
          </div>

          <div class="settings-form-intro">
            <i class="fa-solid fa-key" aria-hidden="true"></i>
            <p>Your current password is required before JKC Cafe saves a new one.</p>
          </div>

          <form class="login-form" action="settings.php#settings-password" method="post">
            <?php echo app_csrf_field(); ?>
            <input type="hidden" name="action" value="password">

            <div class="login-field" data-input-glow>
              <label for="settings-current-password">Current password</label>
              <div class="password-control">
                <input id="settings-current-password" name="current_password" type="password" autocomplete="current-password" required data-password-input>
                <button type="button" aria-label="Show password" data-password-toggle>
                  <span data-password-toggle-text>Show</span>
                </button>
              </div>
            </div>

            <div class="login-field" data-input-glow>
              <label for="settings-new-password">New password</label>
              <div class="password-control">
                <input id="settings-new-password" name="new_password" type="password" autocomplete="new-password" required data-password-input>
                <button type="button" aria-label="Show password" data-password-toggle>
                  <span data-password-toggle-text>Show</span>
                </button>
              </div>
            </div>

            <div class="login-field" data-input-glow>
              <label for="settings-confirm-password">Confirm new password</label>
              <div class="password-control">
                <input id="settings-confirm-password" name="confirm_password" type="password" autocomplete="new-password" required data-password-input>
                <button type="button" aria-label="Show password" data-password-toggle>
                  <span data-password-toggle-text>Show</span>
                </button>
              </div>
            </div>

            <ul class="settings-security-list" aria-label="Password tips">
              <li><i class="fa-solid fa-check" aria-hidden="true"></i>Use a password you do not reuse elsewhere.</li>
              <li><i class="fa-solid fa-check" aria-hidden="true"></i>Mix letters, numbers, and a symbol for a stronger key.</li>
            </ul>

            <p class="settings-field-note">No password changes are saved until you submit this form.</p>
            <button class="button button-primary" type="submit"><i class="fa-solid fa-lock" aria-hidden="true"></i> Save password</button>
          </form>
        </section>

        <section id="settings-danger" class="settings-modern-panel settings-danger-panel" aria-labelledby="danger-title" data-settings-section data-reveal>
          <div class="settings-panel-title">
            <span><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
            <div>
              <p class="eyebrow">Danger zone</p>
              <h2 id="danger-title">Permanently delete account</h2>
            </div>
          </div>

          <div class="settings-danger-intro">
            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
            <div>
              <strong>This permanently deletes your account.</strong>
              <p>Your account, order history, notifications, and reminders will be removed after your password and confirmation are accepted.</p>
            </div>
          </div>

          <form class="login-form settings-delete-form" action="settings.php#settings-danger" method="post">
            <?php echo app_csrf_field(); ?>
            <input type="hidden" name="action" value="delete_account">

            <div class="login-field" data-input-glow>
              <label for="settings-delete-password">Confirm password</label>
              <div class="password-control">
                <input id="settings-delete-password" name="delete_password" type="password" autocomplete="current-password" required data-password-input>
                <button type="button" aria-label="Show password" data-password-toggle>
                  <span data-password-toggle-text>Show</span>
                </button>
              </div>
            </div>

            <div class="login-field settings-delete-confirmation" data-input-glow>
              <label for="settings-delete-confirmation">Type DELETE to confirm</label>
              <input id="settings-delete-confirmation" name="delete_confirmation" type="text" autocomplete="off" inputmode="latin" required>
            </div>

            <ul class="settings-danger-list" aria-label="Delete account effects">
              <li><i class="fa-solid fa-check" aria-hidden="true"></i>Your account is permanently deleted.</li>
              <li><i class="fa-solid fa-check" aria-hidden="true"></i>Orders and order items are permanently deleted.</li>
              <li><i class="fa-solid fa-check" aria-hidden="true"></i>Notifications and reminders are permanently deleted.</li>
              <li><i class="fa-solid fa-check" aria-hidden="true"></i>This cannot be undone.</li>
            </ul>

            <button class="button settings-danger-button" type="submit"><i class="fa-solid fa-user-slash" aria-hidden="true"></i> Delete my account</button>
          </form>
        </section>

        <aside id="settings-summary" class="settings-modern-panel settings-summary-panel" aria-labelledby="summary-title" data-settings-section data-reveal>
          <div class="settings-panel-title">
            <span><i class="fa-solid fa-circle-info" aria-hidden="true"></i></span>
            <div>
              <p class="eyebrow">Summary</p>
              <h2 id="summary-title">Saved account details</h2>
            </div>
          </div>

          <dl class="settings-summary-list">
            <div>
              <dt>Email</dt>
              <dd><?php echo e($displayEmail); ?></dd>
            </div>
            <div>
              <dt>Avatar</dt>
              <dd><?php echo $profileImage !== '' ? 'Custom photo' : 'Initials fallback'; ?></dd>
            </div>
            <div>
              <dt>Password</dt>
              <dd>Protected with secure hashing</dd>
            </div>
            <div>
              <dt>Recovery email</dt>
              <dd><?php echo e($displayRecoveryEmail); ?></dd>
            </div>
            <div>
              <dt>Recovery phone</dt>
              <dd><?php echo e($displayRecoveryPhone); ?></dd>
            </div>
            <div>
              <dt>Account status</dt>
              <dd><?php echo e($displayAccountStatus); ?></dd>
            </div>
          </dl>

          <div class="settings-tip-card">
            <i class="fa-solid fa-mug-hot" aria-hidden="true"></i>
            <p>Keep your profile photo friendly and your password private. The cafe remembers your cozy picks; you keep the keys.</p>
          </div>

          <div class="settings-summary-actions">
            <a class="button button-secondary" href="profile.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to dashboard</a>
          </div>
        </aside>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>

  <script src="user-js/script.js"></script>
  <script src="user-js/settings.js"></script>
</body>
</html>
