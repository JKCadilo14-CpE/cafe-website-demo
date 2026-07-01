<?php
require_once __DIR__ . '/app.php';

$cartCount = app_cart_count();
$isLoggedIn = app_is_logged_in();
$displayName = trim((string) ($_SESSION['username'] ?? 'Account'));
$displayEmail = trim((string) ($_SESSION['email'] ?? ''));
$accountInitials = app_initials($displayName !== '' ? $displayName : 'Account');
$profileImage = app_profile_image_src((string) ($_SESSION['profile_image'] ?? ''));
?>
<header class="site-header" data-site-header>
  <div class="header-inner">
    <a class="brand" href="index.php" aria-label="JKC Cafe home">
      <img src="images/logo.svg" alt="" width="44" height="44">
      <span class="brand-copy">
        <strong>JKC Cafe</strong>
        <small>coffee & pastries</small>
      </span>
    </a>

    <button class="nav-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="site-nav" data-nav-toggle>
      <span aria-hidden="true"></span>
      <span aria-hidden="true"></span>
      <span aria-hidden="true"></span>
    </button>

    <nav class="site-nav" id="site-nav" aria-label="Primary navigation" data-nav>
      <div class="nav-main-links" aria-label="Main pages">
        <a class="nav-link" href="about.php" data-nav-link>About</a>
        <a class="nav-link" href="menu.php" data-nav-link>Menu</a>
        <a class="nav-link" href="rewards.php" data-nav-link>Rewards</a>
        <a class="nav-link" href="contact.php" data-nav-link>Contact</a>
      </div>
      <div class="nav-actions" aria-label="Account and cart">
        <a class="cart-link" href="cart.php" aria-label="<?php echo $cartCount > 0 ? 'View cart with ' . $cartCount . ' items' : 'View cart'; ?>" data-nav-link>
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M6.2 6h15l-1.4 7.4a2 2 0 0 1-2 1.6H9.3a2 2 0 0 1-2-1.7L6.2 6Z"></path>
            <path d="M6.2 6 5.8 3.8A1 1 0 0 0 4.8 3H3"></path>
            <path d="M9.5 20a.8.8 0 1 0 0-1.6.8.8 0 0 0 0 1.6Z"></path>
            <path d="M18 20a.8.8 0 1 0 0-1.6.8.8 0 0 0 0 1.6Z"></path>
          </svg>
          <?php if ($cartCount > 0): ?>
            <span class="cart-badge" aria-hidden="true"><?php echo e((string) min($cartCount, 99)); ?></span>
          <?php endif; ?>
        </a>
        <?php if ($isLoggedIn): ?>
          <div class="account-menu" data-account-menu>
            <button class="account-avatar-button" type="button" aria-label="Open account menu" aria-expanded="false" aria-controls="account-menu-panel" data-account-toggle>
              <span class="account-avatar" aria-hidden="true">
                <?php if ($profileImage !== ''): ?>
                  <img src="<?php echo e($profileImage); ?>" alt="">
                <?php else: ?>
                  <?php echo e($accountInitials); ?>
                <?php endif; ?>
              </span>
            </button>
            <div class="account-popover" id="account-menu-panel" hidden data-account-popover>
              <div class="account-popover-header">
                <span class="account-avatar account-avatar-large" aria-hidden="true">
                  <?php if ($profileImage !== ''): ?>
                    <img src="<?php echo e($profileImage); ?>" alt="">
                  <?php else: ?>
                    <?php echo e($accountInitials); ?>
                  <?php endif; ?>
                </span>
                <div>
                  <span class="account-popover-kicker">Signed in</span>
                  <p class="account-popover-title"><?php echo e($displayName !== '' ? $displayName : 'Account'); ?></p>
                  <?php if ($displayEmail !== ''): ?>
                    <p class="account-popover-description"><?php echo e($displayEmail); ?></p>
                  <?php else: ?>
                    <p class="account-popover-description">Manage your cafe profile</p>
                  <?php endif; ?>
                </div>
              </div>
              <div class="account-popover-body">
                <a class="account-menu-link" href="profile.php" data-nav-link>
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path>
                    <path d="M4 20a8 8 0 0 1 16 0"></path>
                  </svg>
                  <span>
                    <strong>Dashboard</strong>
                    <small>Cart, orders, and favorites</small>
                  </span>
                </a>
                <a class="account-menu-link" href="settings.php" data-nav-link>
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"></path>
                    <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 0 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21a2 2 0 0 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1A2 2 0 0 1 4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H3a2 2 0 0 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.3 7A2 2 0 0 1 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 .9-1.6V3a2 2 0 0 1 4 0v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1A2 2 0 0 1 19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.6.9h.1a2 2 0 0 1 0 4H21a1.7 1.7 0 0 0-1.6 1Z"></path>
                  </svg>
                  <span>
                    <strong>Settings</strong>
                    <small>Photo, name, and password</small>
                  </span>
                </a>
              </div>
              <div class="account-popover-footer">
                <a class="button button-secondary account-signout" href="logout.php">
                  <span>Sign out</span>
                </a>
              </div>
            </div>
          </div>
        <?php else: ?>
          <a class="nav-link" href="login.php" data-nav-link>Log in</a>
          <a class="button button-primary nav-cta" href="signup.php" data-nav-link>Sign Up</a>
        <?php endif; ?>
      </div>
    </nav>
    <button class="nav-backdrop" type="button" aria-label="Close navigation" tabindex="-1" hidden data-nav-backdrop></button>
  </div>
</header>
