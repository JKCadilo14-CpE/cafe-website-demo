<?php
require_once __DIR__ . '/../components/app.php';

app_require_admin();

$dashboardStats = [
    'products' => 0,
    'users' => 0,
    'orders' => 0,
    'revenue' => 0,
    'active_orders' => 0,
    'pending_orders' => 0,
    'completed_orders' => 0,
    'unread_messages' => 0,
];
$adminName = trim((string) ($_SESSION['username'] ?? 'Admin'));
$dashboardDate = date('M j, Y');
$dashboardDaypart = date('g:i A');
$recentOrders = [];
$dashboardMessage = '';
$dashboardMessageType = '';

function dashboard_status_class(string $status): string
{
    return strtolower(str_replace(' ', '-', $status));
}

function dashboard_format_date(?string $date): string
{
    $timestamp = strtotime((string) $date);

    return $timestamp ? date('M j, g:i A', $timestamp) : 'Not available';
}

function dashboard_attention_state(int $count): string
{
    return $count > 0 ? 'Needs attention' : 'All clear';
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = app_db();

    $orderStatsResult = $mysqli->query(
        "SELECT
            COALESCE(SUM(total_amount), 0) AS revenue,
            COUNT(id) AS orders,
            COALESCE(SUM(CASE WHEN status IN ('Pending', 'Preparing', 'Out for Delivery') THEN 1 ELSE 0 END), 0) AS active_orders,
            COALESCE(SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending_orders,
            COALESCE(SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END), 0) AS completed_orders
         FROM `orders`"
    );
    $orderStats = $orderStatsResult->fetch_assoc() ?: [];
    $orderStatsResult->free();

    $dashboardStats['revenue'] = (float) ($orderStats['revenue'] ?? 0);
    $dashboardStats['orders'] = (int) ($orderStats['orders'] ?? 0);
    $dashboardStats['active_orders'] = (int) ($orderStats['active_orders'] ?? 0);
    $dashboardStats['pending_orders'] = (int) ($orderStats['pending_orders'] ?? 0);
    $dashboardStats['completed_orders'] = (int) ($orderStats['completed_orders'] ?? 0);
    $dashboardStats['products'] = (int) ($mysqli->query('SELECT COUNT(*) FROM products')->fetch_row()[0] ?? 0);
    $dashboardStats['users'] = (int) ($mysqli->query('SELECT COUNT(*) FROM users')->fetch_row()[0] ?? 0);

    $recentLimit = 5;
    $recentStatement = $mysqli->prepare(
        'SELECT
            id,
            customer_name,
            status,
            total_amount,
            created_at
         FROM `orders`
         ORDER BY created_at DESC, id DESC
         LIMIT ?'
    );
    $recentStatement->bind_param('i', $recentLimit);
    $recentStatement->execute();
    $recentResult = $recentStatement->get_result();

    while ($row = $recentResult->fetch_assoc()) {
        $recentOrders[] = $row;
    }

    $recentResult->free();
    $recentStatement->close();

    $mysqli->close();
} catch (mysqli_sql_exception $exception) {
    $dashboardStats = [
        'products' => 0,
        'users' => 0,
        'orders' => 0,
        'revenue' => 0,
        'active_orders' => 0,
        'pending_orders' => 0,
        'completed_orders' => 0,
        'unread_messages' => 0,
    ];
    $dashboardMessage = 'Unable to load the dashboard numbers right now. Please check the database connection.';
    $dashboardMessageType = 'error';
}

try {
    foreach (app_contact_messages() as $contactMessage) {
        if ((string) ($contactMessage['status'] ?? 'unread') !== 'read') {
            $dashboardStats['unread_messages']++;
        }
    }
} catch (mysqli_sql_exception $exception) {
    if ($dashboardMessage === '') {
        $dashboardMessage = 'Unable to load guest message counts right now.';
        $dashboardMessageType = 'error';
    }
}

$latestOrder = $recentOrders[0] ?? null;
$latestOrderLabel = $latestOrder !== null ? dashboard_format_date((string) ($latestOrder['created_at'] ?? '')) : 'No orders yet';
$completionRate = $dashboardStats['orders'] > 0 ? (int) round(($dashboardStats['completed_orders'] / $dashboardStats['orders']) * 100) : 0;
$needsAttentionCount = $dashboardStats['active_orders'] + $dashboardStats['unread_messages'];
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Home | JKC Cafe Admin</title>
        <link rel="icon" href="../images/logo.svg" type="image/svg+xml">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <link rel="stylesheet" href="css/admin-shell.css">
        <link rel="stylesheet" href="css/admin-style.css">
    </head>
    <body class="admin-home-page">
        <div class="admin-layout">
            <?php require __DIR__ . '/components/admin-sidebar.php'; ?>

            <main class="admin-main">
                <?php require __DIR__ . '/components/admin-topbar.php'; ?>

                <section class="dashboard-home" aria-labelledby="dashboard-home-title">
                    <div class="dashboard-hero-panel">
                        <div class="dashboard-hero-copy">
                            <p class="dashboard-eyebrow">Daily cafe command center</p>
                            <h1 id="dashboard-home-title">Good shift, <?php echo e($adminName !== '' ? $adminName : 'Admin'); ?>.</h1>
                            <p>Start with what needs attention, then jump into orders, menu updates, guest messages, and the cafe numbers that matter today.</p>
                            <div class="dashboard-hero-actions" aria-label="Primary dashboard actions">
                                <a href="admin-orders-list.php">
                                    <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                                    Review orders
                                </a>
                                <a href="admin-manage-product.php">
                                    <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                                    Manage menu
                                </a>
                                <a href="admin-contact-messages.php">
                                    <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                                    Guest messages
                                </a>
                            </div>
                        </div>
                        <div class="dashboard-hero-meta" aria-label="Dashboard status">
                            <span>
                                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                <?php echo e($dashboardDate); ?>
                            </span>
                            <span>
                                <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                <?php echo e($dashboardDaypart); ?>
                            </span>
                            <span class="<?php echo $needsAttentionCount > 0 ? 'needs-work' : 'is-calm'; ?>">
                                <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                                <?php echo e(dashboard_attention_state($needsAttentionCount)); ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($dashboardMessage !== ''): ?>
                        <div class="admin-message dashboard-message <?php echo e($dashboardMessageType); ?>" role="<?php echo $dashboardMessageType === 'error' ? 'alert' : 'status'; ?>">
                            <?php echo e($dashboardMessage); ?>
                        </div>
                    <?php endif; ?>

                    <div class="dashboard-grid" aria-label="Dashboard summary shortcuts">
                        <a class="stat-card revenue-card" href="admin-analytics.php" aria-label="View revenue analytics">
                            <span class="stat-icon" aria-hidden="true">
                                <i class="fa-solid fa-chart-line"></i>
                            </span>
                            <span class="stat-copy">
                                <span class="stat-kicker">Revenue</span>
                                <strong>P<?php echo number_format($dashboardStats['revenue'], 2); ?></strong>
                                <small><?php echo number_format($completionRate); ?>% completion rate</small>
                            </span>
                            <span class="stat-arrow" aria-hidden="true">
                                <i class="fa-solid fa-arrow-right"></i>
                            </span>
                        </a>

                        <a class="stat-card orders-card" href="admin-orders-list.php" aria-label="Manage cafe orders">
                            <span class="stat-icon" aria-hidden="true">
                                <i class="fa-solid fa-receipt"></i>
                            </span>
                            <span class="stat-copy">
                                <span class="stat-kicker">Orders</span>
                                <strong><?php echo number_format($dashboardStats['orders']); ?></strong>
                                <small><?php echo number_format($dashboardStats['active_orders']); ?> active tickets</small>
                            </span>
                            <span class="stat-arrow" aria-hidden="true">
                                <i class="fa-solid fa-arrow-right"></i>
                            </span>
                        </a>

                        <a class="stat-card users-card" href="admin-users-list.php" aria-label="Manage user accounts">
                            <span class="stat-icon" aria-hidden="true">
                                <i class="fa-solid fa-user-group"></i>
                            </span>
                            <span class="stat-copy">
                                <span class="stat-kicker">Users</span>
                                <strong><?php echo number_format($dashboardStats['users']); ?></strong>
                                <small><?php echo number_format($dashboardStats['unread_messages']); ?> unread messages</small>
                            </span>
                            <span class="stat-arrow" aria-hidden="true">
                                <i class="fa-solid fa-arrow-right"></i>
                            </span>
                        </a>

                        <a class="stat-card products-card" href="admin-manage-product.php" aria-label="Manage cafe products">
                            <span class="stat-icon product-icon" aria-hidden="true">
                                <i class="fa-solid fa-box-open"></i>
                            </span>
                            <span class="stat-copy">
                                <span class="stat-kicker">Products</span>
                                <strong><?php echo number_format($dashboardStats['products']); ?></strong>
                                <small><?php echo $dashboardStats['products'] > 0 ? 'Menu ready to sell' : 'Add first product'; ?></small>
                            </span>
                            <span class="stat-arrow" aria-hidden="true">
                                <i class="fa-solid fa-arrow-right"></i>
                            </span>
                        </a>
                    </div>

                    <div class="dashboard-workspace">
                        <section class="dashboard-panel dashboard-attention-panel" aria-labelledby="dashboard-attention-title">
                            <div class="dashboard-panel-header">
                                <div>
                                    <p class="dashboard-panel-eyebrow">Today&apos;s focus</p>
                                    <h2 id="dashboard-attention-title">Needs attention</h2>
                                </div>
                                <span class="dashboard-count-pill"><?php echo number_format($needsAttentionCount); ?> open</span>
                            </div>

                            <div class="dashboard-attention-list">
                                <a class="dashboard-attention-item <?php echo $dashboardStats['active_orders'] > 0 ? 'is-urgent' : 'is-clear'; ?>" href="admin-orders-list.php">
                                    <span class="dashboard-attention-icon" aria-hidden="true">
                                        <i class="fa-solid fa-mug-hot"></i>
                                    </span>
                                    <span>
                                        <strong><?php echo number_format($dashboardStats['active_orders']); ?> active orders</strong>
                                        <small><?php echo $dashboardStats['active_orders'] > 0 ? 'Review pending, preparing, or delivery tickets.' : 'No active order tickets right now.'; ?></small>
                                    </span>
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </a>

                                <a class="dashboard-attention-item <?php echo $dashboardStats['unread_messages'] > 0 ? 'is-urgent' : 'is-clear'; ?>" href="admin-contact-messages.php">
                                    <span class="dashboard-attention-icon" aria-hidden="true">
                                        <i class="fa-regular fa-envelope"></i>
                                    </span>
                                    <span>
                                        <strong><?php echo number_format($dashboardStats['unread_messages']); ?> unread messages</strong>
                                        <small><?php echo $dashboardStats['unread_messages'] > 0 ? 'Guest questions are waiting in the cafe inbox.' : 'Guest messages are caught up.'; ?></small>
                                    </span>
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </a>

                                <a class="dashboard-attention-item <?php echo $dashboardStats['products'] > 0 ? 'is-clear' : 'is-urgent'; ?>" href="<?php echo $dashboardStats['products'] > 0 ? 'admin-manage-product.php' : 'admin-add-product.php'; ?>">
                                    <span class="dashboard-attention-icon" aria-hidden="true">
                                        <i class="fa-solid fa-box-open"></i>
                                    </span>
                                    <span>
                                        <strong><?php echo number_format($dashboardStats['products']); ?> menu products</strong>
                                        <small><?php echo $dashboardStats['products'] > 0 ? 'Menu catalog is available for customer ordering.' : 'Add products so customers can place orders.'; ?></small>
                                    </span>
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>
                        </section>

                        <section class="dashboard-panel dashboard-orders-panel" aria-labelledby="dashboard-recent-orders-title">
                            <div class="dashboard-panel-header">
                                <div>
                                    <p class="dashboard-panel-eyebrow">Order counter</p>
                                    <h2 id="dashboard-recent-orders-title">Recent orders</h2>
                                </div>
                                <a class="dashboard-panel-link" href="admin-orders-list.php">View all</a>
                            </div>

                            <?php if ($recentOrders === []): ?>
                                <div class="dashboard-empty-state">
                                    <span aria-hidden="true"><i class="fa-regular fa-rectangle-list"></i></span>
                                    <h3>No orders yet</h3>
                                    <p>Completed checkouts will appear here with customer, status, and total.</p>
                                    <a href="admin-manage-product.php">Review menu setup</a>
                                </div>
                            <?php else: ?>
                                <div class="dashboard-order-list">
                                    <?php foreach ($recentOrders as $order): ?>
                                        <?php
                                            $orderId = (int) ($order['id'] ?? 0);
                                            $status = (string) ($order['status'] ?? 'Pending');
                                            $customerName = trim((string) ($order['customer_name'] ?? ''));
                                        ?>
                                        <a class="dashboard-order-row" href="admin-order-details.php?id=<?php echo $orderId; ?>" aria-label="View order <?php echo $orderId; ?>">
                                            <span class="dashboard-order-number">#<?php echo $orderId; ?></span>
                                            <span class="dashboard-order-copy">
                                                <strong><?php echo e($customerName !== '' ? $customerName : 'Guest customer'); ?></strong>
                                                <small><?php echo e(dashboard_format_date((string) ($order['created_at'] ?? ''))); ?></small>
                                            </span>
                                            <span class="dashboard-status-badge <?php echo e(dashboard_status_class($status)); ?>"><?php echo e($status); ?></span>
                                            <span class="dashboard-order-total">P<?php echo number_format((float) ($order['total_amount'] ?? 0), 2); ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>

                    <section class="dashboard-panel dashboard-quick-panel" aria-labelledby="dashboard-quick-title">
                        <div class="dashboard-panel-header">
                            <div>
                                <p class="dashboard-panel-eyebrow">Fast paths</p>
                                <h2 id="dashboard-quick-title">Quick operations</h2>
                            </div>
                            <span class="dashboard-latest-order">Latest order: <?php echo e($latestOrderLabel); ?></span>
                        </div>

                        <div class="dashboard-quick-grid">
                            <a class="dashboard-quick-card" href="admin-add-product.php">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                <span>
                                    <strong>Add menu item</strong>
                                    <small>Create a new drink, pastry, or cafe item.</small>
                                </span>
                            </a>
                            <a class="dashboard-quick-card" href="admin-manage-product.php">
                                <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
                                <span>
                                    <strong>Manage products</strong>
                                    <small>Edit prices, images, and menu availability.</small>
                                </span>
                            </a>
                            <a class="dashboard-quick-card" href="admin-users-list.php">
                                <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
                                <span>
                                    <strong>Review users</strong>
                                    <small>Check customer and admin account roles.</small>
                                </span>
                            </a>
                            <a class="dashboard-quick-card" href="admin-analytics.php">
                                <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                                <span>
                                    <strong>Open analytics</strong>
                                    <small>View revenue, order mix, and best sellers.</small>
                                </span>
                            </a>
                        </div>
                    </section>
                </section>
            </main>
        </div>

        <script src="js/admin_script.js"></script>
    </body>
</html>
