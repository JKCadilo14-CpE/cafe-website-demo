<?php
require_once __DIR__ . '/../../components/app.php';

$adminTopbarPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$adminTopbarTitles = [
    'admin-home.php' => ['Dashboard', 'Cafe overview'],
    'admin-orders-list.php' => ['Orders', 'Review queue'],
    'admin-order-details.php' => ['Order Details', 'Customer handoff'],
    'admin-add-product.php' => ['Add Product', 'Menu builder'],
    'admin-manage-product.php' => ['Manage Products', 'Menu catalog'],
    'admin-edit-product.php' => ['Edit Product', 'Menu details'],
    'admin-users-list.php' => ['Users', 'Customer accounts'],
    'admin-contact-messages.php' => ['Messages', 'Guest inbox'],
    'admin-analytics.php' => ['Analytics', 'Cafe reporting'],
    'admin-profile.php' => ['Profile', 'Admin identity'],
    'admin-settings.php' => ['Settings', 'Account care'],
];
$adminSearchPlaceholders = [
    'admin-home.php' => 'Search dashboard cards',
    'admin-orders-list.php' => 'Search orders',
    'admin-order-details.php' => 'Search order details',
    'admin-add-product.php' => 'Search add product panel',
    'admin-contact-messages.php' => 'Search messages',
    'admin-users-list.php' => 'Search users',
    'admin-analytics.php' => 'Search reports',
    'admin-profile.php' => 'Search profile',
    'admin-settings.php' => 'Search settings',
    'admin-edit-product.php' => 'Search edit product panel',
    'admin-manage-product.php' => 'Search products or categories',
];
$adminSearchTargetsMap = [
    'admin-home.php' => '.stat-card',
    'admin-orders-list.php' => '.orders-list-card',
    'admin-order-details.php' => '.order-details-card, .order-info-card, .order-section-card, .order-summary-card',
    'admin-add-product.php' => '.add-product-panel',
    'admin-edit-product.php' => '.edit-product-panel',
    'admin-users-list.php' => '.users-table tbody tr',
    'admin-contact-messages.php' => '.contact-message-card',
    'admin-analytics.php' => '.analytics-stat-card, .analytics-health-item, .analytics-table tbody tr',
    'admin-profile.php' => '.profile-card, .profile-panel, .activity-card',
    'admin-settings.php' => '.settings-card, .settings-panel',
];

[$adminPageTitle, $adminPageKicker] = $adminTopbarTitles[$adminTopbarPage] ?? ['Admin', 'JKC Cafe'];

$adminSearchPlaceholder = (string) (
    $adminSearchPlaceholder
    ?? $adminSearchPlaceholders[$adminTopbarPage]
    ?? 'Search here'
);
$adminSearchIsForm = (bool) ($adminSearchIsForm ?? ($adminTopbarPage === 'admin-manage-product.php'));
$adminSearchAction = (string) ($adminSearchAction ?? 'admin-manage-product.php');
$adminSearchMethod = strtoupper((string) ($adminSearchMethod ?? 'GET'));
$adminSearchName = (string) ($adminSearchName ?? 'search');
$adminSearchValue = (string) ($adminSearchValue ?? ($searchTerm ?? ''));
$adminSearchTargets = (string) ($adminSearchTargets ?? ($adminSearchTargetsMap[$adminTopbarPage] ?? ''));
$adminProfileImage = app_profile_image_src((string) ($_SESSION['profile_image'] ?? ''));
$adminProfileImageSrc = app_admin_asset_src($adminProfileImage);
?>
<header class="topbar" aria-label="Admin toolbar">
    <button type="button" class="icon-button sidebar-toggle" aria-label="Open admin navigation" aria-expanded="false" aria-controls="adminSidebar" data-admin-sidebar-toggle>
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>

    <div class="topbar-context" aria-label="Current admin page">
        <span class="topbar-kicker"><?php echo e($adminPageKicker); ?></span>
        <strong><?php echo e($adminPageTitle); ?></strong>
    </div>

    <div class="topbar-search-area">
        <?php if ($adminSearchIsForm): ?>
            <form class="search-shell" action="<?php echo e($adminSearchAction); ?>" method="<?php echo e($adminSearchMethod); ?>" role="search">
                <label class="sr-only" for="admin-topbar-search">Search admin content</label>
                <i class="fa-solid fa-magnifying-glass search-leading-icon" aria-hidden="true"></i>
                <input id="admin-topbar-search" type="search" name="<?php echo e($adminSearchName); ?>" placeholder="<?php echo e($adminSearchPlaceholder); ?>" value="<?php echo e($adminSearchValue); ?>">
                <button type="submit" class="search-submit-button" aria-label="Search">
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
            </form>
        <?php else: ?>
            <div class="search-shell" role="search" data-admin-search-shell data-admin-search-targets="<?php echo e($adminSearchTargets); ?>">
                <label class="sr-only" for="admin-topbar-search">Search admin content</label>
                <i class="fa-solid fa-magnifying-glass search-leading-icon" aria-hidden="true"></i>
                <input id="admin-topbar-search" type="search" placeholder="<?php echo e($adminSearchPlaceholder); ?>" autocomplete="off" aria-describedby="admin-search-status">
                <button type="button" class="search-clear-button" data-admin-search-clear aria-label="Clear search" hidden>
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
                <button type="button" class="search-submit-button" aria-label="Search">
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
            </div>
        <?php endif; ?>
        <?php if (!$adminSearchIsForm): ?>
            <p class="admin-search-status" id="admin-search-status" data-admin-search-status role="status" aria-live="polite">Search filters this page instantly.</p>
        <?php endif; ?>
    </div>

    <div class="topbar-actions" aria-label="Quick Actions">
        <button type="button" class="icon-button" aria-label="Notifications" title="Notifications">
            <i class="fa-regular fa-bell"></i>
        </button>
        <button type="button" class="icon-button profile-button<?php echo $adminProfileImageSrc !== '' ? ' has-profile-image' : ''; ?>" aria-label="Admin Profile" title="Admin profile" id="profileBtn">
            <span class="admin-topbar-avatar" aria-hidden="true">
                <?php if ($adminProfileImageSrc !== ''): ?>
                    <img src="<?php echo e($adminProfileImageSrc); ?>" alt="">
                <?php else: ?>
                    <i class="fa-regular fa-circle-user"></i>
                <?php endif; ?>
            </span>
        </button>
        <div class="more-menu" aria-label="More admin actions">
            <button type="button" class="icon-button more-menu-button" aria-label="More actions" title="More actions" aria-expanded="false" aria-haspopup="true">
                <i class="fa-solid fa-ellipsis"></i>
            </button>
            <div class="more-dropdown" role="menu">
                <div class="more-dropdown-heading" aria-hidden="true">
                    <span>Quick tools</span>
                    <small>Workspace actions</small>
                </div>
                <button type="button" class="more-dropdown-item" data-action="refresh" role="menuitem">
                    <i class="fa-solid fa-rotate-right"></i>
                    <span>Refresh Data</span>
                </button>
                <button type="button" class="more-dropdown-item" data-action="dark-mode" role="menuitem">
                    <i class="fa-regular fa-moon"></i>
                    <span>Toggle Dark Mode</span>
                </button>
                <a href="../logout.php" class="more-dropdown-item logout-item" role="menuitem">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</header>
