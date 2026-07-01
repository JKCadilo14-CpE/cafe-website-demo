<?php
require_once __DIR__ . '/../components/app.php';

app_require_admin();

$currentPage = 'settings';
$message = '';
$messageType = '';
$admin = [
    'id' => (int) $_SESSION['user_id'],
    'username' => (string) ($_SESSION['username'] ?? 'Admin'),
    'email' => (string) ($_SESSION['email'] ?? ''),
    'role' => (int) ($_SESSION['role'] ?? 1),
    'password' => '',
    'profile_image' => (string) ($_SESSION['profile_image'] ?? ''),
];

function admin_settings_upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Profile photo is too large. Please choose an image under 2MB.',
        UPLOAD_ERR_PARTIAL => 'The profile photo upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Please choose a profile photo before uploading.',
        default => 'Unable to upload that profile photo. Please try again.',
    };
}

function admin_settings_store_profile_image(array $file, int $userId): array
{
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($uploadError !== UPLOAD_ERR_OK) {
        return ['path' => '', 'error' => admin_settings_upload_error_message($uploadError)];
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

    $filename = 'admin-' . $userId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extensions[$mimeType];
    $absolutePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        return ['path' => '', 'error' => 'Unable to save that profile photo. Please try again.'];
    }

    return ['path' => APP_PROFILE_IMAGE_DIR . '/' . $filename, 'error' => ''];
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = app_db();
    app_ensure_profile_image_column($mysqli);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        app_require_csrf();

        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'profile_image') {
            $upload = admin_settings_store_profile_image($_FILES['profile_image'] ?? [], $admin['id']);

            if ($upload['error'] !== '') {
                $message = $upload['error'];
                $messageType = 'error';
            } else {
                $imageStatement = $mysqli->prepare('SELECT profile_image FROM users WHERE id = ? LIMIT 1');
                $imageStatement->bind_param('i', $admin['id']);
                $imageStatement->execute();
                $imageRow = $imageStatement->get_result()->fetch_assoc();
                $imageStatement->close();

                $previousImage = (string) ($imageRow['profile_image'] ?? '');
                $profileImage = $upload['path'];
                $updateStatement = $mysqli->prepare('UPDATE users SET profile_image = ? WHERE id = ?');
                $updateStatement->bind_param('si', $profileImage, $admin['id']);
                $updateStatement->execute();
                $updateStatement->close();

                app_delete_profile_image($previousImage);
                $_SESSION['profile_image'] = $profileImage;

                header('Location: admin-settings.php?updated=profile_image#settings-photo');
                exit();
            }
        } elseif ($action === 'remove_profile_image') {
            $imageStatement = $mysqli->prepare('SELECT profile_image FROM users WHERE id = ? LIMIT 1');
            $imageStatement->bind_param('i', $admin['id']);
            $imageStatement->execute();
            $imageRow = $imageStatement->get_result()->fetch_assoc();
            $imageStatement->close();

            $previousImage = (string) ($imageRow['profile_image'] ?? '');
            $emptyImage = null;
            $updateStatement = $mysqli->prepare('UPDATE users SET profile_image = ? WHERE id = ?');
            $updateStatement->bind_param('si', $emptyImage, $admin['id']);
            $updateStatement->execute();
            $updateStatement->close();

            app_delete_profile_image($previousImage);
            $_SESSION['profile_image'] = '';

            header('Location: admin-settings.php?updated=remove_profile_image#settings-photo');
            exit();
        } elseif ($action === 'account') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));

            if ($username === '' || $email === '') {
                $message = 'Username and email are required.';
                $messageType = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Please enter a valid email address.';
                $messageType = 'error';
            } else {
                $checkStatement = $mysqli->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
                $checkStatement->bind_param('si', $email, $admin['id']);
                $checkStatement->execute();
                $existingUser = $checkStatement->get_result()->fetch_assoc();
                $checkStatement->close();

                if ($existingUser !== null) {
                    $message = 'That email address is already used by another account.';
                    $messageType = 'error';
                } else {
                    $updateStatement = $mysqli->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?');
                    $updateStatement->bind_param('ssi', $username, $email, $admin['id']);
                    $updateStatement->execute();
                    $updateStatement->close();

                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;

                    header('Location: admin-settings.php?updated=account');
                    exit();
                }
            }
        } elseif ($action === 'password') {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            $passwordStatement = $mysqli->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
            $passwordStatement->bind_param('i', $admin['id']);
            $passwordStatement->execute();
            $passwordRow = $passwordStatement->get_result()->fetch_assoc();
            $passwordStatement->close();

            if ($passwordRow === null || !password_verify($currentPassword, (string) $passwordRow['password'])) {
                $message = 'Current password is incorrect.';
                $messageType = 'error';
            } elseif (strlen($newPassword) < 8) {
                $message = 'New password must be at least 8 characters.';
                $messageType = 'error';
            } elseif ($newPassword !== $confirmPassword) {
                $message = 'New password and confirmation do not match.';
                $messageType = 'error';
            } else {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updatePasswordStatement = $mysqli->prepare('UPDATE users SET password = ? WHERE id = ?');
                $updatePasswordStatement->bind_param('si', $passwordHash, $admin['id']);
                $updatePasswordStatement->execute();
                $updatePasswordStatement->close();

                header('Location: admin-settings.php?updated=password');
                exit();
            }
        }
    }

    if (isset($_GET['updated'])) {
        $message = match ($_GET['updated']) {
            'password' => 'Password changed successfully.',
            'profile_image' => 'Profile photo updated successfully.',
            'remove_profile_image' => 'Profile photo removed successfully.',
            default => 'Account settings updated successfully.',
        };
        $messageType = 'success';
    }

    $adminStatement = $mysqli->prepare('SELECT id, username, email, role, password, profile_image FROM users WHERE id = ? LIMIT 1');
    $adminStatement->bind_param('i', $admin['id']);
    $adminStatement->execute();
    $adminResult = $adminStatement->get_result();
    $adminRow = $adminResult->fetch_assoc();
    $adminResult->free();
    $adminStatement->close();
    $mysqli->close();

    if ($adminRow !== null) {
        $admin = $adminRow;
        $_SESSION['username'] = (string) $admin['username'];
        $_SESSION['email'] = (string) $admin['email'];
        $_SESSION['profile_image'] = app_profile_image_src((string) ($admin['profile_image'] ?? ''));
    }
} catch (mysqli_sql_exception $exception) {
    $message = 'Unable to load settings right now. Please check the database connection.';
    $messageType = 'error';
}

$safeUsername = e((string) ($admin['username'] ?? 'Admin'));
$safeEmail = e((string) ($admin['email'] ?? ''));
$safeRoleLabel = (int) ($admin['role'] ?? 1) === 1 ? 'Administrator' : 'Team Member';
$profileImage = app_profile_image_src((string) ($admin['profile_image'] ?? ''));
$profileImageSrc = app_admin_asset_src($profileImage);
$avatarInitial = strtoupper(substr(trim((string) ($admin['username'] ?? 'A')) !== '' ? trim((string) ($admin['username'] ?? 'A')) : 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Settings | JKC Cafe Admin</title>
        <link rel="icon" href="../images/logo.svg" type="image/svg+xml">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <link rel="stylesheet" href="css/admin-shell.css">
        <link rel="stylesheet" href="css/admin-style.css">
        <link rel="stylesheet" href="css/admin-settings.css">
    </head>
    <body class="admin-settings-page">
        <div class="admin-layout">
            <?php require __DIR__ . '/components/admin-sidebar.php'; ?>

            <main class="admin-main">
                <?php require __DIR__ . '/components/admin-topbar.php'; ?>

                <section class="settings-dashboard" aria-label="Admin Settings">
                    <div class="settings-hero">
                        <div class="settings-hero-copy">
                            <span class="settings-eyebrow">Admin account care</span>
                            <h1 class="settings-title">Keep your JKC Cafe dashboard ready for the day.</h1>
                            <p class="settings-subtitle">Update your admin identity, tune the display, and protect access before the next batch of orders comes in.</p>
                            <div class="settings-hero-actions">
                                <a href="admin-home.php" class="settings-action-button primary">
                                    <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i>
                                    Back to Dashboard
                                </a>
                                <button type="button" class="settings-action-button secondary" data-action="dark-mode">
                                    <i class="fa-regular fa-moon" aria-hidden="true"></i>
                                    Toggle Dark Mode
                                </button>
                            </div>
                        </div>

                        <aside class="settings-account-card settings-card" aria-label="Current admin account">
                            <div class="settings-avatar" aria-hidden="true">
                                <?php if ($profileImageSrc !== ''): ?>
                                    <img src="<?php echo e($profileImageSrc); ?>" alt="">
                                <?php else: ?>
                                    <?php echo e($avatarInitial); ?>
                                <?php endif; ?>
                            </div>
                            <div class="settings-account-copy">
                                <span class="settings-card-kicker">Signed in as</span>
                                <h2><?php echo $safeUsername; ?></h2>
                                <p><?php echo $safeEmail !== '' ? $safeEmail : 'No email saved yet'; ?></p>
                            </div>
                            <div class="settings-account-meta" aria-label="Account snapshot">
                                <span>
                                    <strong><?php echo e($safeRoleLabel); ?></strong>
                                    Role
                                </span>
                                <span>
                                    <strong>#<?php echo e((string) ($admin['id'] ?? '')); ?></strong>
                                    Account ID
                                </span>
                                <span>
                                    <strong>Private</strong>
                                    Password
                                </span>
                                <span>
                                    <strong><?php echo $profileImageSrc !== '' ? 'Photo' : 'Default'; ?></strong>
                                    Avatar
                                </span>
                            </div>
                        </aside>
                    </div>

                    <?php if ($message !== ''): ?>
                        <div class="admin-message settings-message <?php echo e($messageType); ?>" role="status">
                            <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>" aria-hidden="true"></i>
                            <?php echo e($message); ?>
                        </div>
                    <?php endif; ?>

                    <div class="settings-grid">
                        <section id="settings-photo" class="settings-panel settings-photo-panel">
                            <div class="settings-panel-header">
                                <span class="settings-panel-label">Profile photo</span>
                                <h2>
                                    <i class="fa-solid fa-camera" aria-hidden="true"></i>
                                    Admin Avatar
                                </h2>
                                <p>Upload a friendly admin photo for the dashboard topbar. If you remove it, the default avatar icon comes back.</p>
                            </div>

                            <div class="settings-photo-preview">
                                <span class="settings-photo-frame" aria-hidden="true">
                                    <?php if ($profileImageSrc !== ''): ?>
                                        <img src="<?php echo e($profileImageSrc); ?>" alt="">
                                    <?php else: ?>
                                        <i class="fa-regular fa-circle-user"></i>
                                    <?php endif; ?>
                                </span>
                                <div>
                                    <span class="settings-status-chip"><?php echo $profileImageSrc !== '' ? 'Photo active' : 'Default avatar'; ?></span>
                                    <h3><?php echo $profileImageSrc !== '' ? 'Your topbar photo is live' : 'No profile photo yet'; ?></h3>
                                    <p id="admin-settings-upload-help">Use a JPG, PNG, WebP, or GIF under 2MB. Square images crop the cleanest in the topbar.</p>
                                </div>
                            </div>

                            <form class="settings-form settings-upload-form" action="admin-settings.php#settings-photo" method="POST" enctype="multipart/form-data">
                                <?php echo app_csrf_field(); ?>
                                <input type="hidden" name="action" value="profile_image">
                                <label class="settings-file-drop" for="admin-profile-image">
                                    <span class="settings-file-icon" aria-hidden="true">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                    </span>
                                    <span class="settings-file-main">Choose an admin profile photo</span>
                                    <span class="settings-file-label" data-admin-file-label data-default-label="No file selected">No file selected</span>
                                    <input id="admin-profile-image" name="profile_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" aria-describedby="admin-settings-upload-help" required>
                                </label>
                                <button type="submit" class="settings-action-button primary">
                                    <i class="fa-solid fa-upload" aria-hidden="true"></i>
                                    Upload Photo
                                </button>
                            </form>

                            <?php if ($profileImageSrc !== ''): ?>
                                <form class="settings-remove-photo-form" action="admin-settings.php#settings-photo" method="POST">
                                    <?php echo app_csrf_field(); ?>
                                    <input type="hidden" name="action" value="remove_profile_image">
                                    <button type="submit" class="settings-action-button secondary settings-remove-photo">
                                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                        Remove Photo
                                    </button>
                                </form>
                            <?php endif; ?>
                        </section>

                        <section class="settings-panel settings-account-panel">
                            <div class="settings-panel-header">
                                <span class="settings-panel-label">Profile basics</span>
                                <h2>
                                    <i class="fa-regular fa-id-card" aria-hidden="true"></i>
                                    Account Settings
                                </h2>
                                <p>These details appear inside the admin dashboard and help the team know who made changes.</p>
                            </div>
                            <form class="settings-form" action="admin-settings.php" method="POST">
                                <?php echo app_csrf_field(); ?>
                                <input type="hidden" name="action" value="account">
                                <div class="form-field">
                                    <label for="username">Username</label>
                                    <input type="text" id="username" name="username" value="<?php echo $safeUsername; ?>" autocomplete="username" required>
                                    <p class="settings-field-help">Use a clear name for order notes, product edits, and message follow-ups.</p>
                                </div>
                                <div class="form-field">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?php echo $safeEmail; ?>" autocomplete="email" required>
                                    <p class="settings-field-help">Keep this current for account recovery and admin communication.</p>
                                </div>
                                <button type="submit" class="settings-action-button primary">
                                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                                    Save Account
                                </button>
                            </form>
                        </section>

                        <section class="settings-panel settings-security-panel">
                            <div class="settings-panel-header">
                                <span class="settings-panel-label">Security check</span>
                                <h2>
                                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                    Change Password
                                </h2>
                                <p>Refresh access when devices change hands or after a busy admin session.</p>
                            </div>
                            <form class="settings-form" action="admin-settings.php" method="POST">
                                <?php echo app_csrf_field(); ?>
                                <input type="hidden" name="action" value="password">
                                <div class="form-field">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                                    <p class="settings-field-help">Confirm the password you use to sign in now.</p>
                                </div>
                                <div class="form-field">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
                                    <p class="settings-field-help">Use at least 8 characters. A longer phrase is easier to remember and harder to guess.</p>
                                </div>
                                <div class="form-field">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                                    <p class="settings-field-help">Type the same new password again before saving.</p>
                                </div>
                                <button type="submit" class="settings-action-button primary">
                                    <i class="fa-solid fa-key" aria-hidden="true"></i>
                                    Change Password
                                </button>
                            </form>
                        </section>

                        <div class="settings-side-stack">
                            <section class="settings-panel settings-display-panel">
                                <div class="settings-panel-header">
                                    <span class="settings-panel-label">Workspace comfort</span>
                                    <h2>
                                        <i class="fa-regular fa-moon" aria-hidden="true"></i>
                                        Display
                                    </h2>
                                    <p>Switch the dashboard tone for brighter counter shifts or quieter evening work.</p>
                                </div>
                                <div class="settings-card">
                                    <div class="settings-card-icon" aria-hidden="true">
                                        <i class="fa-regular fa-moon"></i>
                                    </div>
                                    <div class="settings-card-body">
                                        <span class="settings-card-kicker">Saved in this browser</span>
                                        <h3>Dark Mode</h3>
                                        <p>Use a softer dark surface when reviewing orders, products, and messages late in the day.</p>
                                    </div>
                                    <button type="button" class="settings-action-button secondary" data-action="dark-mode">
                                        <i class="fa-regular fa-moon" aria-hidden="true"></i>
                                        Toggle Dark Mode
                                    </button>
                                </div>
                            </section>

                            <section class="settings-panel settings-signout-panel">
                                <div class="settings-panel-header">
                                    <span class="settings-panel-label">Session control</span>
                                    <h2>
                                        <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                                        Sign Out
                                    </h2>
                                    <p>End this admin session when you are leaving the register, office, or shared device.</p>
                                </div>
                                <div class="settings-card">
                                    <div class="settings-card-icon danger" aria-hidden="true">
                                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                                    </div>
                                    <div class="settings-card-body">
                                        <span class="settings-card-kicker">Protect the dashboard</span>
                                        <h3>End Session</h3>
                                        <p>You can sign back in anytime with your admin email and password.</p>
                                    </div>
                                    <a href="../logout.php" class="settings-action-button danger">
                                        <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                                        Sign Out
                                    </a>
                                </div>
                            </section>
                        </div>
                    </div>
                </section>
            </main>
        </div>

        <script src="js/admin_script.js"></script>
        <script src="js/admin-settings.js"></script>
    </body>
</html>
