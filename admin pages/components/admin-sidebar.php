<?php
require_once __DIR__ . '/../../components/app.php';

if (!function_exists('admin_sidebar_current_page')) {
    function admin_sidebar_current_page(): string
    {
        $page = basename((string) ($_SERVER['PHP_SELF'] ?? ''));

        return match ($page) {
            'admin-home.php' => 'dashboard',
            'admin-order-details.php' => 'orders-details',
            'admin-orders-list.php' => 'orders-list',
            'admin-add-product.php' => 'add-product',
            'admin-manage-product.php' => 'manage-product',
            'admin-edit-product.php' => 'edit-product',
            'admin-users-list.php' => 'users',
            'admin-contact-messages.php' => 'contact-messages',
            'admin-analytics.php' => 'analytics',
            'admin-settings.php' => 'settings',
            default => '',
        };
    }
}

if (!function_exists('admin_sidebar_link_class')) {
    function admin_sidebar_link_class(string $pageKey, string $currentPage): string
    {
        return 'nav-link' . ($pageKey === $currentPage ? ' active' : '');
    }
}

$adminCurrentPage = (string) ($currentPage ?? admin_sidebar_current_page());
$adminOrderDetailsHref = 'admin-order-details.php';

if ($adminCurrentPage === 'orders-details' && isset($orderId) && (int) $orderId > 0) {
    $adminOrderDetailsHref .= '?id=' . (int) $orderId;
}
?>
<aside class="sidebar">
    <div class="sidebar-top">
        <a href="admin-home.php" class="brand" aria-label="JKC Cafe Admin Home">
            <img src="../images/logo.svg" alt="JKC Cafe logo">
            <span>JKC CAFE</span>
        </a>

        <nav class="sidebar-nav" aria-label="Sidebar Navigation">
            <ul class="nav-list">
                <li class="nav-item" data-tooltip="Dashboard">
                    <a href="admin-home.php" class="<?php echo e(admin_sidebar_link_class('dashboard', $adminCurrentPage)); ?>">
                        <i class="fa-solid fa-table-cells-large"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item" data-tooltip="Orders Details">
                    <a href="<?php echo e($adminOrderDetailsHref); ?>" class="<?php echo e(admin_sidebar_link_class('orders-details', $adminCurrentPage)); ?>">
                        <i class="fa-regular fa-rectangle-list"></i>
                        <span>Orders Details</span>
                    </a>
                </li>
                <li class="nav-item" data-tooltip="Orders List">
                    <a href="admin-orders-list.php" class="<?php echo e(admin_sidebar_link_class('orders-list', $adminCurrentPage)); ?>">
                        <i class="fa-solid fa-scroll"></i>
                        <span>Orders List</span>
                    </a>
                </li>
                <li class="nav-item" data-tooltip="Add Product">
                    <a href="admin-add-product.php" class="<?php echo e(admin_sidebar_link_class('add-product', $adminCurrentPage)); ?>">
                        <i class="fa-solid fa-box-open"></i>
                        <span>Add Product</span>
                    </a>
                </li>
                <li class="nav-item" data-tooltip="Manage Product">
                    <a href="admin-manage-product.php" class="<?php echo e(admin_sidebar_link_class('manage-product', $adminCurrentPage)); ?>">
                        <i class="fa-regular fa-clipboard"></i>
                        <span>Manage Product</span>
                    </a>
                </li>
                <li class="nav-item" data-tooltip="Edit Product">
                    <a href="admin-edit-product.php" class="<?php echo e(admin_sidebar_link_class('edit-product', $adminCurrentPage)); ?>">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <span>Edit Product</span>
                    </a>
                </li>
                <li class="nav-item" data-tooltip="Users">
                    <a href="admin-users-list.php" class="<?php echo e(admin_sidebar_link_class('users', $adminCurrentPage)); ?>">
                        <i class="fa-solid fa-users"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li class="nav-item" data-tooltip="Messages">
                    <a href="admin-contact-messages.php" class="<?php echo e(admin_sidebar_link_class('contact-messages', $adminCurrentPage)); ?>">
                        <i class="fa-regular fa-envelope"></i>
                        <span>Messages</span>
                    </a>
                </li>
                <li class="nav-item" data-tooltip="Analytics">
                    <a href="admin-analytics.php" class="<?php echo e(admin_sidebar_link_class('analytics', $adminCurrentPage)); ?>">
                        <i class="fa-solid fa-chart-column"></i>
                        <span>Analytics</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <div class="sidebar-bottom">
        <ul class="nav-list">
            <li class="nav-item" data-tooltip="Settings">
                <a href="admin-settings.php" class="<?php echo e(admin_sidebar_link_class('settings', $adminCurrentPage)); ?>">
                    <i class="fa-solid fa-gear"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </div>
</aside>
