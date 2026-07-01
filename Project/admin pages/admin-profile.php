<?php
require_once __DIR__ . '/../components/app.php';

app_require_admin();

$admin = [
    'id' => (int) ($_SESSION['user_id'] ?? 0),
    'username' => (string) ($_SESSION['username'] ?? 'Admin'),
    'email' => (string) ($_SESSION['email'] ?? 'Not available'),
    'role' => (string) ($_SESSION['role'] ?? '1'),
    'profile_image' => (string) ($_SESSION['profile_image'] ?? ''),
];

try {
    $mysqli = app_db();
    app_ensure_profile_image_column($mysqli);

    $adminId = (int) $admin['id'];
    $adminStatement = $mysqli->prepare('SELECT id, username, email, role, profile_image FROM users WHERE id = ? LIMIT 1');
    $adminStatement->bind_param('i', $adminId);
    $adminStatement->execute();
    $adminResult = $adminStatement->get_result();
    $adminRow = $adminResult->fetch_assoc();

    if ($adminRow !== null) {
        $admin = [
            'id' => (int) $adminRow['id'],
            'username' => (string) $adminRow['username'],
            'email' => (string) $adminRow['email'],
            'role' => (string) $adminRow['role'],
            'profile_image' => app_profile_image_src((string) ($adminRow['profile_image'] ?? '')),
        ];

        $_SESSION['username'] = $admin['username'];
        $_SESSION['email'] = $admin['email'];
        $_SESSION['role'] = (int) $admin['role'];
        $_SESSION['profile_image'] = $admin['profile_image'];
    }
} catch (mysqli_sql_exception $exception) {
    $admin['profile_image'] = app_profile_image_src((string) ($admin['profile_image'] ?? ''));
}

$username = (string) ($admin['username'] ?? 'Admin');
$email = (string) ($admin['email'] ?? 'Not available');
$role = (string) ($admin['role'] ?? '1');
$roleLabel = $role === '1' ? 'Administrator' : ucfirst($role);
$avatarInitial = strtoupper(substr(trim($username) !== '' ? trim($username) : 'A', 0, 1));
$profileImage = app_profile_image_src((string) ($admin['profile_image'] ?? ''));
$profileImageSrc = app_admin_asset_src($profileImage);
$profileSourceLabel = $profileImageSrc !== '' ? 'Profile photo' : 'Initial fallback';

$safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safeRole = htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8');
$safeAvatarInitial = htmlspecialchars($avatarInitial, ENT_QUOTES, 'UTF-8');
$safeProfileSource = htmlspecialchars($profileSourceLabel, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Profile | JKC Cafe Admin</title>
        <link rel="icon" href="../images/logo.svg" type="image/svg+xml">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <link rel="stylesheet" href="css/admin-shell.css">
        <link rel="stylesheet" href="css/admin-style.css">
        <link rel="stylesheet" href="css/admin-profile.css">
    </head>
    <body class="admin-profile-page">
        <div class="admin-layout">
            <?php require __DIR__ . '/components/admin-sidebar.php'; ?>

            <main class="admin-main">
                <?php require __DIR__ . '/components/admin-topbar.php'; ?>

                <section class="profile-dashboard" aria-label="Admin Profile Dashboard">
                    <article class="profile-card" aria-labelledby="profile-title">
                        <div class="profile-hero-copy">
                            <span class="profile-eyebrow">Admin profile</span>
                            <h1 id="profile-title">Your JKC Cafe command center.</h1>
                            <p>Keep the account behind orders, menu edits, messages, and reports easy to recognize and ready for the next shift.</p>
                            <div class="profile-hero-actions">
                                <a href="admin-home.php" class="profile-action-button primary">
                                    <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i>
                                    Dashboard
                                </a>
                                <a href="admin-settings.php" class="profile-action-button secondary">
                                    <i class="fa-solid fa-gear" aria-hidden="true"></i>
                                    Account Settings
                                </a>
                            </div>
                        </div>

                        <div class="profile-identity-card" aria-label="Current admin identity">
                            <div class="profile-avatar" aria-hidden="true">
                                <?php if ($profileImageSrc !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($profileImageSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="">
                                <?php else: ?>
                                    <?php echo $safeAvatarInitial; ?>
                                <?php endif; ?>
                            </div>
                            <div class="profile-identity-copy">
                                <span class="profile-card-kicker">Signed in as</span>
                                <h2><?php echo $safeUsername; ?></h2>
                                <p class="profile-email"><?php echo $safeEmail; ?></p>
                                <span class="role-badge">
                                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                                    <?php echo $safeRole; ?>
                                </span>
                            </div>
                        </div>

                        <div class="profile-snapshot" aria-label="Account snapshot">
                            <span>
                                <strong>Active</strong>
                                Session status
                            </span>
                            <span>
                                <strong><?php echo $safeRole; ?></strong>
                                Access level
                            </span>
                            <span>
                                <strong><?php echo $safeProfileSource; ?></strong>
                                Profile source
                            </span>
                        </div>
                    </article>

                    <div class="profile-content">
                        <section class="profile-panel" aria-labelledby="account-info-title">
                            <div class="profile-panel-header">
                                <span class="profile-panel-label">Account details</span>
                                <h2 class="profile-panel-title" id="account-info-title">
                                    <i class="fa-regular fa-id-card" aria-hidden="true"></i>
                                    Account Information
                                </h2>
                                <p>These details identify the admin account used across the cafe dashboard.</p>
                            </div>

                            <div class="account-list">
                                <div class="account-row">
                                    <span class="account-label">Display name</span>
                                    <span class="account-value"><?php echo $safeUsername; ?></span>
                                </div>
                                <div class="account-row">
                                    <span class="account-label">Email Address</span>
                                    <span class="account-value"><?php echo $safeEmail; ?></span>
                                </div>
                                <div class="account-row">
                                    <span class="account-label">Role</span>
                                    <span class="account-value"><?php echo $safeRole; ?></span>
                                </div>
                                <div class="account-row">
                                    <span class="account-label">Avatar</span>
                                    <span class="account-value"><?php echo $safeProfileSource; ?></span>
                                </div>
                            </div>
                        </section>

                        <section class="profile-panel" aria-labelledby="security-title">
                            <div class="profile-panel-header">
                                <span class="profile-panel-label">Security posture</span>
                                <h2 class="profile-panel-title" id="security-title">
                                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                    Security
                                </h2>
                                <p>Small habits keep menu changes, order data, and customer messages protected.</p>
                            </div>

                            <p class="security-copy">
                                Update your password from Settings when a device changes hands, and sign out before leaving a shared counter or office computer.
                            </p>
                            <a href="admin-settings.php" class="change-password-button profile-action-button primary">
                                <i class="fa-solid fa-key" aria-hidden="true"></i>
                                Change Password
                            </a>
                        </section>

                        <section class="activity-grid" aria-label="Activity Summary">
                            <article class="activity-card">
                                <span class="activity-icon">
                                    <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                                </span>
                                <div>
                                    <span class="activity-label">Now</span>
                                    <h3>Session Status</h3>
                                    <p>Active and ready</p>
                                </div>
                            </article>

                            <article class="activity-card">
                                <span class="activity-icon">
                                    <i class="fa-solid fa-shield" aria-hidden="true"></i>
                                </span>
                                <div>
                                    <span class="activity-label">Access</span>
                                    <h3>Access Level</h3>
                                    <p><?php echo $safeRole; ?></p>
                                </div>
                            </article>

                            <article class="activity-card">
                                <span class="activity-icon">
                                    <i class="fa-solid fa-database" aria-hidden="true"></i>
                                </span>
                                <div>
                                    <span class="activity-label">Source</span>
                                    <h3>Profile Source</h3>
                                    <p><?php echo $safeProfileSource; ?></p>
                                </div>
                            </article>
                        </section>
                    </div>
                </section>
            </main>
        </div>

        <script src="js/admin_script.js"></script>
    </body>
</html>
